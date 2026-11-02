"""Execute production SELECTs on SQLite fixtures; no Dolibarr instance or business writes.

Usage: python test/customerdeposits.py [Dolibarr htdocs] [extracted core contract directory]
"""
import json
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


if __name__ == '__main__':
    unittest.main(verbosity=2)
