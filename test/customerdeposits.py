"""Execute production SELECTs on SQLite fixtures; no Dolibarr instance or business writes.

Usage: python test/customerdeposits.py [Dolibarr htdocs] [extracted core contract directory]
"""
import json
from html import unescape
from pathlib import Path
import sqlite3
import subprocess
import sys
import unittest

HERE = Path(__file__).resolve().parent
ROOT = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else HERE / '.runtime/24.0.2/htdocs'
CONTRACTS = Path(sys.argv[2]).resolve() if len(sys.argv) > 2 else ROOT / 'core/lib'
sys.argv[1:] = []


class DepositControlTest(unittest.TestCase):
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
        self.assertEqual(len(french), 22)
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
