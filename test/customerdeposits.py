"""Execute production SELECTs on SQLite fixtures; no Dolibarr instance or business writes.

Usage: python test/customerdeposits.py [Dolibarr htdocs] [extracted core contract directory]
"""
import json
from datetime import datetime, timezone
from html import unescape
from pathlib import Path
import sqlite3
import subprocess
import sys
import tempfile
import unittest

HERE = Path(__file__).resolve().parent
ROOT = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else HERE / '.runtime/24.0.2/htdocs'
CONTRACTS = Path(sys.argv[2]).resolve() if len(sys.argv) > 2 else ROOT / 'core/lib'
sys.argv[1:] = []


class DepositControlTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        runtime = HERE / '.runtime'
        runtime.mkdir(exist_ok=True)
        cls.page_temp = tempfile.TemporaryDirectory(prefix='deposit-page-', dir=runtime)
        cls.page_module = Path(cls.page_temp.name) / 'htdocs/custom/jpsun'
        (cls.page_module / 'class').mkdir(parents=True)
        for name in ('customerdeposits.php', 'class/jpsuncustomerdepositreport.class.php',
                     'class/jpsuncustomerdepositcompatibility.class.php'):
            if name == 'customerdeposits.php':
                (cls.page_module / name).write_bytes((HERE.parent / name).read_bytes())
            else:
                source = (HERE.parent / name).as_posix().replace("'", "\\'")
                (cls.page_module / name).write_text("<?php require_once '" + source + "';\n", encoding='utf-8')
        (cls.page_module.parents[1] / 'main.inc.php').write_text('<?php return 1;\n', encoding='utf-8')

    @classmethod
    def tearDownClass(cls):
        cls.page_temp.cleanup()

    def setUp(self):
        self.db = sqlite3.connect(':memory:')
        self.db.row_factory = sqlite3.Row
        self.db.create_function('GREATEST', -1, max)
        self.db.create_function('LEAST', -1, min)
        self.db.executescript('''
            CREATE TABLE fixture_societe(rowid INTEGER, nom TEXT);
            CREATE TABLE fixture_facture(rowid INTEGER, ref TEXT, type INTEGER, entity INTEGER,
                fk_soc INTEGER, datef TEXT, date_valid TEXT, fk_statut INTEGER,
                total_ttc REAL, fk_facture_source INTEGER);
            CREATE TABLE fixture_paiement(rowid INTEGER, entity INTEGER, datep TEXT);
            CREATE TABLE fixture_paiement_facture(fk_paiement INTEGER, fk_facture INTEGER, amount REAL);
            CREATE TABLE fixture_societe_remise_except(fk_facture_source INTEGER, amount_ttc REAL,
                fk_facture_line INTEGER, fk_facture INTEGER, discount_type INTEGER, entity INTEGER);
            CREATE TABLE fixture_facturedet(rowid INTEGER, fk_facture INTEGER);
            CREATE TABLE fixture_societe_commerciaux(fk_soc INTEGER, fk_user INTEGER);
            CREATE TABLE fixture_element_element(fk_source INTEGER, fk_target INTEGER, sourcetype TEXT, targettype TEXT);
            CREATE TABLE fixture_commande(rowid INTEGER, ref TEXT, fk_statut INTEGER, date_commande TEXT,
                date_livraison TEXT, date_cloture TEXT, entity INTEGER, fk_soc INTEGER);
            INSERT INTO fixture_societe VALUES(1, 'Client One'), (2, 'Client Two');
            INSERT INTO fixture_facture VALUES
                (1, 'D1', 3, 1, 1, '2026-01-01', '2026-01-01', 1, 120, NULL),
                (2, 'D2', 3, 2, 1, '2026-01-01', '2026-01-01', 1, 300, NULL),
                (3, 'D3', 3, 1, 2, '2026-01-01', '2026-01-01', 1, 200, NULL),
                (4, 'UNPAID', 3, 1, 1, '2026-01-01', '2026-01-01', 1, 100, NULL),
                (10, 'INV1', 0, 1, 1, '2026-06-01', '2026-06-01', 1, 40, NULL),
                (11, 'INV2', 0, 1, 1, '2027-01-01', '2027-01-01', 1, 80, NULL);
            INSERT INTO fixture_paiement VALUES
                (1, 1, '2026-06-01'), (2, 1, '2027-01-10'), (3, 2, '2026-06-01'), (4, 1, '2026-06-01');
            INSERT INTO fixture_paiement_facture VALUES(1, 1, 60), (2, 1, 60), (3, 2, 300), (4, 3, 200);
            INSERT INTO fixture_facturedet VALUES(100, 10), (101, 11);
            INSERT INTO fixture_societe_remise_except VALUES(1, 40, 100, NULL, 0, 1), (1, 80, 101, NULL, 0, 1);
            INSERT INTO fixture_societe_commerciaux VALUES(1, 7), (1, 7);
            INSERT INTO fixture_commande VALUES(1, 'ORDER1', 1, '2026-01-01', '2027-01-01', NULL, 1, 1);
            INSERT INTO fixture_element_element VALUES(1, 1, 'commande', 'facture'), (1, 1, 'commande', 'facture');
        ''')

    def tearDown(self):
        self.db.close()

    def run_worker(self, *, fail_query=0, **options):
        proc = subprocess.Popen(['php', str(HERE / 'customerdeposits.php'), str(ROOT), str(CONTRACTS)],
                                stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                                text=True, encoding='utf-8')
        proc.stdin.write(json.dumps(options) + '\n')
        proc.stdin.flush()
        queries = 0
        output = None
        for line in proc.stdout:
            message = json.loads(line)
            if 'sql' in message:
                queries += 1
                if queries == fail_query:
                    reply = {'error': 'Fixture evidence query failed'}
                else:
                    sql = message['sql']
                    self.assertTrue(sql.startswith('SELECT '), sql)
                    try:
                        reply = {'rows': [dict(row) for row in self.db.execute(sql)]}
                    except Exception:
                        proc.kill()
                        raise
                proc.stdin.write(json.dumps(reply) + '\n')
                proc.stdin.flush()
            else:
                output = message['output']
        stderr = proc.stderr.read()
        proc.stdin.close()
        returncode = proc.wait(timeout=20)
        proc.stdout.close()
        proc.stderr.close()
        self.assertEqual(returncode, 0, stderr)
        self.assertIsNotNone(output, stderr)
        return output

    def deposit(self, **options):
        return self.run_worker(**options)['lines']['1']

    def test_partial_receipt_application_and_evidence(self):
        data = self.run_worker()
        self.assertEqual(data['total'], 2)
        line = data['lines']['1']
        self.assertEqual(line['calculation']['remaining'], 20)
        self.assertEqual(line['calculation']['received'], 60)
        self.assertEqual(line['applied'], 40)
        self.assertEqual([item['at_cutoff'] for item in line['applications']], [True, False])
        self.assertEqual(len(line['orders']), 1)
        self.assertEqual(data['queries'], 4)

    def test_full_application_stays_in_all_and_cleared(self):
        data = self.run_worker(cutoff='2027-01-31', filter={'status': 'cleared'})
        self.assertEqual(list(data['lines']), ['1'])
        self.assertEqual(data['lines']['1']['calculation']['remaining'], 0)
        self.assertIn('1', self.run_worker(cutoff='2027-01-31')['lines'])
        self.assertNotIn('1', self.run_worker(cutoff='2027-01-31', filter={'status': 'outstanding'})['lines'])

    def test_later_validation_and_draft_are_not_applied(self):
        for update in ["date_valid='2027-01-01'", 'fk_statut=0']:
            with self.subTest(update=update):
                self.db.execute('UPDATE fixture_facture SET ' + update + ' WHERE rowid=10')
                line = self.deposit()
                self.assertEqual(line['applied'], 0)
                self.assertFalse(line['applications'][0]['at_cutoff'])

    def test_unpaid_and_later_deposits_excluded(self):
        self.db.execute("UPDATE fixture_facture SET datef='2027-01-01' WHERE rowid=1")
        data = self.run_worker()
        self.assertEqual(list(data['lines']), ['3'])

    def test_entity_and_commercial_restrictions(self):
        self.assertEqual(list(self.run_worker(entity=2)['lines']), ['2'])
        data = self.run_worker(denied=['societe.client.voir'])
        self.assertEqual(list(data['lines']), ['1'])
        self.assertEqual(data['total'], 1)
        self.assertEqual(data['totals']['received'], 60)

    def test_access_denied_without_queries_even_for_admin(self):
        for options in [dict(denied=['accounting.comptarapport.lire']),
                        dict(admin=1, denied=['accounting.comptarapport.lire']), dict(socid=1),
                        dict(version='26.0.0-alpha'), dict(version='26.0.0'), dict(version='27.0.0'),
                        dict(modules=['accounting', 'invoice']), dict(modules=['jpsun', 'invoice']),
                        dict(modules=['jpsun', 'accounting'])]:
            with self.subTest(options=options):
                data = self.run_worker(**options)
                self.assertEqual(data['result'], -1)
                self.assertEqual(data['queries'], 0)
                self.assertFalse(data['lines'])

    def test_order_access_is_optional(self):
        for options in [dict(denied=['commande.lire']), dict(modules=['jpsun', 'invoice', 'accounting'])]:
            data = self.run_worker(**options)
            self.assertEqual(data['queries'], 3)
            self.assertFalse(data['lines']['1']['orders'])
            self.assertEqual(data['lines']['1']['calculation']['remaining'], 20)

    def test_foreign_evidence_is_excluded(self):
        self.db.executescript('''
            UPDATE fixture_facture SET fk_soc=2 WHERE rowid=10;
            UPDATE fixture_commande SET fk_soc=2;
        ''')
        line = self.deposit()
        self.assertEqual(line['applied'], 0)
        self.assertEqual(len(line['applications']), 1)
        self.assertFalse(line['orders'])

    def test_refund_and_credit_note_are_separate(self):
        self.db.executescript('''
            INSERT INTO fixture_facture VALUES(20, 'CREDIT', 2, 1, 1, '2026-07-01', '2026-07-01', 1, -20, 1);
            INSERT INTO fixture_paiement VALUES(20, 1, '2026-07-01');
            INSERT INTO fixture_paiement_facture VALUES(20, 1, -10);
        ''')
        line = self.deposit()
        self.assertEqual(line['credited'], 20)
        self.assertEqual(line['refunds'], 10)
        self.assertEqual(line['calculation']['remaining'], 10)
        self.db.execute("UPDATE fixture_facture SET date_valid='2027-01-01' WHERE rowid=20")
        self.assertEqual(self.deposit()['credited'], 0)

    def test_applications_exceeding_receipts_are_flagged(self):
        self.db.execute("UPDATE fixture_facture SET datef='2026-07-01', date_valid='2026-07-01' WHERE rowid=11")
        self.assertEqual(self.deposit()['calculation']['status'], 'DepositControlInconsistent')

    def test_credit_note_refund_is_attributed_to_source_deposit(self):
        self.db.executescript('''
            DELETE FROM fixture_societe_remise_except WHERE fk_facture_source=1;
            UPDATE fixture_paiement SET datep='2026-07-01' WHERE rowid=2;
            INSERT INTO fixture_facture VALUES(20, 'CN-FULL', 2, 1, 1, '2026-07-01', '2026-07-01', 1, -120, 1);
            INSERT INTO fixture_paiement VALUES(20, 1, '2026-08-01');
            INSERT INTO fixture_paiement_facture VALUES(20, 20, -120);
        ''')
        line = self.deposit()
        self.assertEqual(line['receipts'], 120)
        self.assertEqual(line['refunds'], 120)
        self.assertEqual(line['credited'], 120)
        self.assertEqual(line['calculation'], dict(received=0, remaining=0, status='DepositControlCleared'))
        self.assertIn('1', self.run_worker(filter={'status': 'cleared'})['lines'])
        self.assertNotIn('1', self.run_worker(filter={'status': 'outstanding'})['lines'])

    def test_credit_note_refunds_preserve_cutoff_entity_and_client_scope(self):
        self.db.executescript('''
            DELETE FROM fixture_societe_remise_except WHERE fk_facture_source=1;
            UPDATE fixture_paiement SET datep='2026-07-01' WHERE rowid=2;
            INSERT INTO fixture_facture VALUES
                (20, 'CN-ONE', 2, 1, 1, '2026-07-01', '2026-07-01', 1, -10, 1),
                (21, 'CN-TWO', 2, 1, 1, '2026-07-01', '2026-07-01', 1, -20, 1),
                (22, 'CN-OTHER-ENTITY', 2, 2, 1, '2026-07-01', '2026-07-01', 1, -40, 1),
                (23, 'CN-OTHER-CLIENT', 2, 1, 2, '2026-07-01', '2026-07-01', 1, -40, 1),
                (24, 'CN-DRAFT', 2, 1, 1, '2026-07-01', NULL, 0, -40, 1);
            INSERT INTO fixture_paiement VALUES
                (20, 1, '2026-08-01'), (21, 1, '2026-08-02'), (22, 1, '2027-01-01'),
                (23, 2, '2026-08-01'), (24, 1, '2026-08-01'), (25, 1, '2026-08-01'),
                (26, 2, '2026-08-01');
            INSERT INTO fixture_paiement_facture VALUES
                (20, 20, -5), (21, 21, -10), (22, 21, -10), (23, 22, -40),
                (24, 23, -40), (25, 24, -40), (26, 20, -40), (21, 1, -5);
        ''')
        line = self.deposit()
        self.assertEqual(line['receipts'], 120)
        self.assertEqual(line['credited'], 30)
        self.assertEqual(line['refunds'], 20)
        self.assertEqual(line['calculation']['received'], 100)
        self.assertEqual(line['calculation']['remaining'], 90)
        later = self.deposit(cutoff='2027-01-31')
        self.assertEqual(later['refunds'], 30)
        self.assertEqual(later['calculation'], dict(received=90, remaining=90, status='DepositControlOutstanding'))
        for datefield in ['datef', 'date_valid']:
            with self.subTest(datefield=datefield):
                self.db.execute('UPDATE fixture_facture SET '+datefield+"='2027-01-01' WHERE rowid=21")
                self.assertEqual(self.deposit()['refunds'], 10)
                self.db.execute('UPDATE fixture_facture SET '+datefield+"='2026-07-01' WHERE rowid=21")

    def test_failure_does_not_present_incomplete_report(self):
        for query in [1, 2, 3, 4]:
            with self.subTest(query=query):
                data = self.run_worker(fail_query=query)
                self.assertEqual(data['result'], -1)
                self.assertFalse(data['lines'])
                self.assertEqual(data['totals']['received'], 0)

    def test_filters_sort_pagination_and_quotes(self):
        data = self.run_worker(limit=1, offset=1, sortfield='f.ref', sortorder='DESC')
        self.assertEqual(data['total'], 2)
        self.assertEqual(list(data['lines']), ['1'])
        self.assertEqual(self.run_worker(filter={'ref': 'D3'})['total'], 1)
        self.assertEqual(self.run_worker(filter={'thirdparty': 'Client Two'})['total'], 1)
        self.assertEqual(self.run_worker(filter={'ref': "' OR 1=1 --"})['total'], 0)
        self.assertEqual(self.run_worker(sortfield='f.ref; DROP TABLE fixture_facture')['total'], 2)

    def test_all_amount_columns_filter_before_count_and_pagination(self):
        cases = [('invoiced', '=120', {'1'}), ('received', '<100', {'1'}),
                 ('refunds', '0', {'1', '3'}), ('credited', '=0', {'1', '3'}),
                 ('applied', '>=40', {'1'}), ('remaining', '>20', {'3'})]
        for field, value, expected in cases:
            with self.subTest(field=field):
                output = self.run_worker(filter={field: value})
                self.assertEqual(set(output['lines']), expected)
                self.assertEqual(output['total'], len(expected))
        combined = dict(invoiced='>=100 <200', received='>0', refunds='0', credited='0', applied='40', remaining='20')
        data = self.run_worker(filter=combined, limit=1, offset=1)
        self.assertEqual((data['total'], data['result']), (1, 0))
        self.assertEqual(self.run_worker(filter={'ref': '0'})['total'], 0)

    def test_both_date_columns_have_inclusive_day_ranges(self):
        def day(value):
            return int(datetime.fromisoformat(value).replace(tzinfo=timezone.utc).timestamp())
        self.db.executescript("""
            UPDATE fixture_facture SET datef='2026-02-01' WHERE rowid=3;
            UPDATE fixture_paiement SET datep='2026-06-02 23:59:59' WHERE rowid=4;
        """)
        cases = [({'invoice_start': day('2026-02-01'), 'invoice_end': day('2026-02-01')}, {'3'}),
                 ({'invoice_end': day('2026-01-31')}, {'1'}),
                 ({'invoice_start': day('2026-01-01')}, {'1', '3'}),
                 ({'payment_start': day('2026-06-02'), 'payment_end': day('2026-06-02')}, {'3'}),
                 ({'payment_end': day('2026-06-01')}, {'1'})]
        for filters, expected in cases:
            self.assertEqual(set(self.run_worker(filter=filters)['lines']), expected)

    def test_linked_document_filters_match_visible_evidence(self):
        for reference in ('INV1', 'INV2'):
            self.assertEqual(set(self.run_worker(filter={'applications': reference})['lines']), {'1'})
        self.assertEqual(set(self.run_worker(filter={'orders': 'ORDER1'})['lines']), {'1'})
        self.assertEqual(self.run_worker(filter={'applications': "' OR 1=1 --"})['total'], 0)
        self.db.executescript("""
            INSERT INTO fixture_facture VALUES(12,'PRIVATE',0,2,1,'2026-01-01','2026-01-01',1,10,NULL);
            INSERT INTO fixture_facturedet VALUES(102,12);
            INSERT INTO fixture_societe_remise_except VALUES(1,10,102,NULL,0,1);
            INSERT INTO fixture_commande VALUES(2,'PRIVATE',1,'2026-01-01',NULL,NULL,2,1);
            INSERT INTO fixture_element_element VALUES(2,1,'commande','facture');
        """)
        for key in ('applications', 'orders'):
            self.assertEqual(self.run_worker(filter={key: 'PRIVATE'})['total'], 0)
        self.db.execute("UPDATE fixture_commande SET date_commande='2027-01-01' WHERE rowid=1")
        self.assertEqual(self.run_worker(filter={'orders': 'ORDER1'})['total'], 0)
        for options in (dict(denied=['commande.lire']), dict(modules=['jpsun', 'accounting', 'invoice'])):
            data = self.run_worker(filter={'orders': 'ORDER1'}, **options)
            self.assertEqual((data['result'], data['queries']), (-1, 0))

    def test_badge_status_filter_is_distinct_from_remaining_balance(self):
        self.db.executescript("""
            UPDATE fixture_facture SET total_ttc=150 WHERE rowid=3;
            INSERT INTO fixture_facture VALUES(5,'CLEARED',3,1,1,'2026-01-01','2026-01-01',1,50,NULL);
            INSERT INTO fixture_paiement_facture VALUES(1,5,50);
            INSERT INTO fixture_societe_remise_except VALUES(5,50,100,NULL,0,1);
        """)
        for status, expected in [('DepositControlOutstanding', {'1'}), ('DepositControlCleared', {'5'}),
                                 ('DepositControlInconsistent', {'3'})]:
            data = self.run_worker(filter={'control_status': status})
            self.assertEqual(set(data['lines']), expected)
            self.assertTrue(all(row['calculation']['status'] == status for row in data['lines'].values()))
        self.db.executescript("""
            INSERT INTO fixture_facture VALUES(13,'USED3',0,1,2,'2026-06-01','2026-06-01',1,250,NULL);
            INSERT INTO fixture_facturedet VALUES(103,13);
            INSERT INTO fixture_societe_remise_except VALUES(3,250,103,NULL,0,1);
        """)
        self.assertEqual(set(self.run_worker(filter={'status': 'cleared', 'control_status': 'DepositControlInconsistent'})['lines']), {'3'})
        self.db.execute('UPDATE fixture_facture SET date_valid=NULL WHERE rowid=1')
        self.assertIn('1', self.run_worker(filter={'control_status': 'DepositControlInconsistent'})['lines'])

    def test_status_filter_keeps_native_precision_for_fractional_sums(self):
        self.db.executescript("""
            UPDATE fixture_facture SET total_ttc=.3 WHERE rowid=1;
            DELETE FROM fixture_paiement_facture WHERE fk_facture=1;
            INSERT INTO fixture_paiement_facture VALUES(1,1,.1),(1,1,.2);
            DELETE FROM fixture_societe_remise_except WHERE fk_facture_source=1;
            INSERT INTO fixture_societe_remise_except VALUES(1,.1,100,NULL,0,1),(1,.2,100,NULL,0,1);
        """)
        row = self.deposit()
        self.assertEqual(row['calculation']['status'], 'DepositControlCleared')
        self.assertIn('1', self.run_worker(filter={'control_status': 'DepositControlCleared'})['lines'])
        self.assertNotIn('1', self.run_worker(filter={'control_status': 'DepositControlInconsistent'})['lines'])

    def test_invalid_column_filters_fail_without_queries(self):
        for filters in ({'invoiced': '==0'}, {'invoiced': '='}, {'received': []}, {'invoice_start': 'bad'},
                        {'invoice_start': 2000000000, 'invoice_end': 1000000000},
                        {'control_status': 'bad'}):
            data = self.run_worker(filter=filters)
            self.assertEqual((data['result'], data['queries']), (-1, 0))

    def test_page_parses_and_resets_all_column_filters(self):
        query = dict(search_invoiced='<=200', search_received='>=0', search_ref='D1',
                     search_refunds='0', search_credited='0', search_applied='40', search_remaining='20',
                     search_thirdparty='Client', search_applications='INV', search_orders='ORDER',
                     search_control_status='DepositControlInconsistent', page='2', button_search='1')
        for prefix, day, month in [('invoice_start',1,1), ('invoice_end',31,1),
                                   ('payment_start',1,6), ('payment_end',2,6)]:
            query.update({f'search_{prefix}day': str(day), f'search_{prefix}month': str(month),
                          f'search_{prefix}year': '2026'})
        options = dict(mode='page', page_path=str(self.page_module / 'customerdeposits.php'),
                       modules=['jpsun', 'comptabilite', 'invoice', 'order'])
        data = self.run_worker(**options, get=query)
        self.assertFalse(data['denied'])
        self.assertEqual(data['page'], 0)
        self.assertEqual(data['filter']['invoiced'], '<=200')
        self.assertEqual(data['filter']['orders'], 'ORDER')
        self.assertIsInstance(data['filter']['invoice_start'], int)
        self.assertIn('2026-01-31', data['queries'][0])
        self.assertIn('2026-06-02 23:59:59', data['queries'][0])
        reset = self.run_worker(**options, get={**query, 'button_removefilter': '1'})
        self.assertEqual(reset['page'], 0)
        for key, value in reset['filter'].items():
            self.assertEqual(value, 'all' if key == 'status' else 0 if key.endswith(('_start', '_end')) else '')

    def test_native_precision(self):
        data = self.run_worker(mode='calculate', precision=3, amounts=[1, .98765, 0, 0, .12345])
        self.assertAlmostEqual(data['received'], .988)
        self.assertAlmostEqual(data['remaining'], .865)

    def test_status_filters_match_native_rounded_remaining(self):
        self.db.executescript('''
            UPDATE fixture_paiement SET datep='2026-07-01' WHERE rowid=2;
            DELETE FROM fixture_societe_remise_except WHERE fk_facture_source=1;
            INSERT INTO fixture_societe_remise_except VALUES(1, 0, 100, NULL, 0, 1);
        ''')
        cases = [
            (2, 1, .10, .20, .30, 0),
            (2, 1, .10, .20, .299, 0),
            (3, 1, .10, .20, .299, .001),
            (0, 2, 1, 0, .6, 0),
            (0, 2, 1, 0, .4, 1),
            (0, 2, 1, 0, .5, 1),
            (2, 1, .006, 0, .004, .01),
            (2, .006, .01, 0, .004, .01),
            (4, 1, .1234, .0001, .1235, 0),
        ]
        for precision, invoiced, first, second, applied, remaining in cases:
            with self.subTest(precision=precision, receipts=[first, second], applied=applied, invoiced=invoiced):
                self.db.execute('UPDATE fixture_facture SET total_ttc=? WHERE rowid=1', (invoiced,))
                self.db.execute('UPDATE fixture_paiement_facture SET amount=? WHERE fk_paiement=1', (first,))
                self.db.execute('UPDATE fixture_paiement_facture SET amount=? WHERE fk_paiement=2', (second,))
                self.db.execute('UPDATE fixture_societe_remise_except SET amount_ttc=? WHERE fk_facture_source=1', (applied,))
                line = self.deposit(precision=precision)
                self.assertEqual(line['calculation']['remaining'], remaining)
                self.assertEqual(line['calculation']['status'], 'DepositControlOutstanding' if remaining else 'DepositControlCleared')
                for status in ['cleared', 'outstanding']:
                    matches = (remaining == 0) if status == 'cleared' else (remaining > 0)
                    data = self.run_worker(precision=precision, filter={'ref': 'D1', 'status': status}, limit=1)
                    self.assertEqual(data['total'], int(matches))
                    self.assertEqual(bool(data['lines']), matches)

    def test_fractional_application_and_credit_sums_do_not_flag_inconsistency(self):
        self.db.executescript('''
            UPDATE fixture_facture SET total_ttc=.3 WHERE rowid=1;
            UPDATE fixture_facture SET datef='2026-07-01', date_valid='2026-07-01' WHERE rowid=11;
            UPDATE fixture_paiement SET datep='2026-07-01' WHERE rowid=2;
            UPDATE fixture_paiement_facture SET amount=.3 WHERE fk_paiement=1;
            UPDATE fixture_paiement_facture SET amount=0 WHERE fk_paiement=2;
            UPDATE fixture_societe_remise_except SET amount_ttc=.1 WHERE fk_facture_line=100;
            UPDATE fixture_societe_remise_except SET amount_ttc=.2 WHERE fk_facture_line=101;
        ''')
        self.assertEqual(self.deposit()['calculation']['status'], 'DepositControlCleared')
        self.db.executescript('''
            DELETE FROM fixture_societe_remise_except WHERE fk_facture_source=1;
            INSERT INTO fixture_facture VALUES
                (20, 'CN-ONE', 2, 1, 1, '2026-07-01', '2026-07-01', 1, -.1, 1),
                (21, 'CN-TWO', 2, 1, 1, '2026-07-01', '2026-07-01', 1, -.2, 1);
            INSERT INTO fixture_paiement VALUES(20, 1, '2026-08-01');
            INSERT INTO fixture_paiement_facture VALUES(20, 1, -.3);
        ''')
        self.assertEqual(self.deposit()['calculation']['status'], 'DepositControlCleared')

    def test_menu_cycle_and_suppression(self):
        menu = [dict(url='/accountancy/index.php?leftmenu=accountancy_accountancy', level=0, enabled=1),
                dict(url='/other/module.php', level=1, enabled=1),
                dict(url='/asset/list.php', level=0, enabled=1)]
        data = self.run_worker(mode='hook', menu=menu)
        self.assertEqual(data['returns'], [1, 0, 0, 0])
        self.assertEqual(len(data['menu']), 5)
        self.assertEqual(data['menu'][1], menu[1])
        self.assertEqual(data['menu'][4], menu[2])
        self.assertEqual([item['level'] for item in data['menu']], [0, 1, 1, 2, 0])
        for options in [dict(version='26.0.0-alpha'), dict(version='26.0.0'), dict(socid=1),
                        dict(admin=1, denied=['accounting.comptarapport.lire'])]:
            self.assertEqual(self.run_worker(mode='hook', menu=menu, **options)['menu'], menu)
        native = menu + [dict(url='/accountancy/customerdeposits.php', level=2)]
        self.assertEqual(self.run_worker(mode='hook', menu=native)['menu'], native)

    def test_simple_accounting_report_uses_its_own_permission(self):
        simple = dict(modules=['jpsun', 'comptabilite', 'invoice', 'order'])
        data = self.run_worker(**simple, denied=['accounting.comptarapport.lire'])
        self.assertEqual(data['result'], 2)
        self.assertEqual(data['queries'], 4)
        self.assertEqual(data['lines']['1']['calculation']['remaining'], 20)
        self.assertEqual(self.run_worker(**simple)['lines'], self.run_worker()['lines'])
        for admin in (0, 1):
            denied = self.run_worker(**simple, admin=admin, denied=['compta.resultat.lire'])
            self.assertEqual((denied['result'], denied['queries']), (-1, 0))
        denied = self.run_worker(modules=['jpsun', 'invoice'], denied=[])
        self.assertEqual((denied['result'], denied['queries']), (-1, 0))

    def test_simple_accounting_keeps_entity_scope_and_version_guard(self):
        simple = dict(modules=['jpsun', 'comptabilite', 'invoice'])
        self.assertEqual(set(self.run_worker(**simple, entity=2)['lines']), {'2'})
        for options in (dict(version='26.0.0-alpha'), dict(version='26.0.0'), dict(socid=1)):
            data = self.run_worker(**simple, **options)
            self.assertEqual((data['result'], data['queries']), (-1, 0))
        # A permission from an inactive module never grants access to the other report.
        denied = self.run_worker(denied=['accounting.comptarapport.lire'])
        self.assertEqual((denied['result'], denied['queries']), (-1, 0))

    def test_simple_accounting_menu_is_a_single_readonly_report(self):
        simple = dict(modules=['jpsun', 'comptabilite', 'invoice', 'order'])
        menu = [dict(url='/compta/resultat/index.php?mainmenu=accountancy&leftmenu=report', level=0, enabled=1),
                dict(url='/compta/resultat/projects.php?leftmenu=report', level=1, enabled=1),
                dict(url='/asset/list.php', level=0, enabled=1)]
        data = self.run_worker(mode='hook', menu=menu, denied=['accounting.comptarapport.lire'], **simple)
        self.assertEqual(data['returns'], [1, 0, 0, 0])
        self.assertEqual([item['level'] for item in data['menu']], [0, 1, 1, 0])
        report = data['menu'][2]
        self.assertIn('leftmenu=accountancy_report_customerdeposits', report['url'])
        self.assertEqual(data['menu'][1], menu[1])
        for options in (dict(admin=1, denied=['compta.resultat.lire']), dict(version='26.0.0'), dict(socid=1)):
            self.assertEqual(self.run_worker(mode='hook', menu=menu, **simple, **options)['menu'], menu)
        native = menu + [dict(url='/compta/customerdeposits.php', level=1)]
        self.assertEqual(self.run_worker(mode='hook', menu=native, **simple)['menu'], native)

    def test_simple_accounting_page_access_and_no_double_entry_dependency(self):
        simple = dict(mode='page', page_path=str(self.page_module / 'customerdeposits.php'),
                      modules=['jpsun', 'comptabilite', 'invoice', 'order'])
        data = self.run_worker(**simple, denied=['accounting.comptarapport.lire'])
        self.assertFalse(data['denied'])
        self.assertTrue(data['queries'][0].startswith('SELECT COUNT('))
        self.assertNotIn('accounting_fiscalyear', data['queries'][0])
        self.assertIsInstance(data['cutoff'], int)
        for options in (dict(admin=1, denied=['compta.resultat.lire']), dict(version='26.0.0-alpha'), dict(socid=1)):
            denied = self.run_worker(**simple, **options)
            self.assertEqual((denied['denied'], denied['queries']), (True, []))

    def test_icon_paths_resolve_and_legacy_image_is_identical(self):
        output = self.run_worker(mode='icon')
        for path in output.values():
            self.assertIn('/jpsun/img/', path)
            self.assertTrue((HERE.parent / 'img' / path.rsplit('/', 1)[1]).is_file(), path)
        self.assertEqual((HERE.parent / 'img/jpsun.png').read_bytes(), (HERE.parent / 'img/object_jpsun.png').read_bytes())

    def test_descriptor_discovery_and_versioned_hook(self):
        data = self.run_worker(mode='descriptor')
        self.assertEqual(str(data['id']), '999000')
        self.assertEqual(data['picto'], 'jpsun@jpsun')
        self.assertIn('leftblock', data['hooks'])
        self.assertNotIn('leftblock', self.run_worker(mode='descriptor', version='26.0.0-alpha')['hooks'])

    def test_french_catalog_loads_natively_without_english_fallback(self):
        french = self.run_worker(mode='translations', language='fr_FR')
        english = self.run_worker(mode='translations', language='en_US')
        self.assertEqual(len(french), 23)
        self.assertEqual(set(french), set(english))
        for key, translated in french.items():
            with self.subTest(key=key):
                self.assertNotEqual(translated, key)
                self.assertNotEqual(translated, english[key])
        self.assertEqual(french['CustomerDepositControl'], 'Contrôle des acomptes clients')
        self.assertEqual(french['MenuAccountancyAdjustments'], 'Régularisations')
        self.assertEqual(french['DepositControlCutoffDate'], 'Date d’arrêté')
        self.assertEqual(french['DepositControlApplications'], 'Factures consommatrices')
        self.assertEqual(french['DepositControlPageTotal'], 'Cette page, TTC : encaissements nets 60 ; utilisés 40 ; reliquats 20')
        menu = [dict(url='/accountancy/index.php?leftmenu=accountancy_accountancy', level=0, enabled=1)]
        output = self.run_worker(mode='hook', language='fr_FR', menu=menu)
        self.assertEqual([unescape(item['titre']) for item in output['menu'][1:]],
                         ['Régularisations', 'Contrôle des acomptes clients'])


if __name__ == '__main__':
    unittest.main(verbosity=2)
