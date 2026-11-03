#!/usr/bin/env python3
"""Execute the migration's SQL with SQLite; native Dolibarr constant helpers run in PHP."""
import json
from pathlib import Path
import sqlite3
import subprocess
import sys
import unittest

CORE = Path(sys.argv.pop(1)).resolve()
MODULE = Path(__file__).resolve().parents[1]
FIELDS = {
    'product': ('jpsun_marque',),
    'projet': ('jpsun_project_pc', 'jpsun_project_n_ddr', 'jpsun_project_t0',
               'jpsun_project_prix_rachat', 'jpsun_project_type_racc', 'jpsun_project_mes_date'),
    'contrat': ('jpsun_site_name', 'jpsun_installed_power_kwc', 'jpsun_pv_module_product',
                'jpsun_pv_module_qty', 'jpsun_inverter_product', 'jpsun_inverter_qty',
                'jpsun_dc_boxes_qty', 'jpsun_ac_boxes_qty', 'jpsun_access_code', 'jpsun_pdl_number'),
}


class MigrationTests(unittest.TestCase):
    def setUp(self):
        self.db = sqlite3.connect(':memory:')
        self.db.row_factory = sqlite3.Row
        self.db.create_function('CHAR_LENGTH', 1, lambda s: len(s) if s is not None else None)
        self.db.executescript('''
            CREATE TABLE fixture_product(rowid INTEGER PRIMARY KEY, entity INTEGER);
            CREATE TABLE fixture_product_extrafields(fk_object INTEGER PRIMARY KEY,
                jpsun_marque TEXT, product_photovoltaic_brand TEXT);
            CREATE TABLE fixture_extrafields(name TEXT, elementtype TEXT, entity INTEGER,
                enabled TEXT, list TEXT, printable INTEGER, label TEXT, fieldvalue TEXT);
            CREATE TABLE fixture_const(name TEXT, value TEXT, type TEXT, visible INTEGER,
                note TEXT, entity INTEGER, UNIQUE(name, entity));
            INSERT INTO fixture_product VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1),(7,1),(8,2);
            INSERT INTO fixture_product_extrafields VALUES
                (1,'Marque française',NULL),(2,'OLD','Manual'),(3,'0',''),(4,'',''),
                (5,NULL,NULL),(6,'Space brand','   '),(7,'Source','0'),(8,'Other entity',NULL);
        ''')
        self.target_type = 'varchar(128)'
        self.has_source = True
        for entity in (0, 1, 2):
            for element, names in FIELDS.items():
                for name in names:
                    self.db.execute('INSERT INTO fixture_extrafields VALUES (?,?,?,?,?,?,?,?)',
                                    (name, element, entity, 'custom expression', '-1', 1, 'Custom label', '0'))
            self.db.execute('INSERT INTO fixture_extrafields VALUES (?,?,?,?,?,?,?,?)',
                            ('unrelated', 'projet', entity, '1', '1', 1, 'Keep', 'kept'))
        self.db.commit()
        self.addCleanup(self.db.close)

    def run_migration(self, version='1.24.1', entity=1, fail=None, requested_entity=None):
        marker = self.db.execute('SELECT value FROM fixture_const WHERE name=? AND entity=?',
                                 ('JPSUN_MIGRATION_1241_DONE', entity)).fetchone()
        process = subprocess.Popen(['php', str(MODULE / 'test/migration1241.php'), str(CORE)],
                                   stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                                   text=True, encoding='utf-8')
        self.addCleanup(lambda: process.kill() if process.poll() is None else None)
        process.stdin.write(json.dumps({'version': version, 'entity': entity,
                                        'done': marker['value'] if marker else 0,
                                        'requested_entity': entity if requested_entity is None else requested_entity}) + '\n')
        process.stdin.flush()
        queries = []
        for line in process.stdout:
            item = json.loads(line)
            if 'output' in item:
                output = item['output']
                break
            sql = item['sql']
            queries.append(sql)
            try:
                if fail and fail in sql:
                    raise sqlite3.OperationalError('Injected failure')
                if sql.startswith('DESC '):
                    rows = ([{'Field': 'jpsun_marque', 'Type': 'varchar(255)'}] if self.has_source else [])
                    if self.target_type:
                        rows.append({'Field': 'product_photovoltaic_brand', 'Type': self.target_type})
                else:
                    cursor = self.db.execute(sql)
                    rows = [dict(row) for row in cursor.fetchall()] if cursor.description else []
                reply = {'rows': rows}
            except sqlite3.Error as error:
                reply = {'error': str(error)}
            process.stdin.write(json.dumps(reply) + '\n')
            process.stdin.flush()
        else:
            self.fail(process.stderr.read())
        process.stdin.close()
        code = process.wait()
        errors = process.stderr.read()
        self.assertEqual(code, 0, errors)
        self.assertEqual(errors, '')
        process.stdout.close()
        process.stderr.close()
        return output, queries

    def brands(self):
        return [tuple(row) for row in self.db.execute('SELECT * FROM fixture_product_extrafields ORDER BY fk_object')]

    def test_copy_only_empty_targets_and_keep_source_values(self):
        source = [row[1] for row in self.brands()]
        output, _ = self.run_migration()
        self.assertEqual((output['result'], output['pending']), (1, 0))
        self.assertEqual([row[2] for row in self.brands()],
                         ['Marque française', 'Manual', '0', '', None, 'Space brand', '0', None])
        self.assertEqual([row[1] for row in self.brands()], source)

    def test_visibility_metadata_changes_only_for_shared_and_current_entity(self):
        self.run_migration()
        for row in self.db.execute('SELECT * FROM fixture_extrafields'):
            hidden = row['entity'] in (0, 1) and row['name'] != 'unrelated'
            self.assertEqual((row['enabled'], row['list'], row['printable']),
                             ('0', '0', 0) if hidden else
                             (('1', '1', 1) if row['name'] == 'unrelated' else ('custom expression', '-1', 1)))
            self.assertEqual(row['fieldvalue'], 'kept' if row['name'] == 'unrelated' else '0')
            self.assertEqual(row['label'], 'Keep' if row['name'] == 'unrelated' else 'Custom label')

    def test_marker_is_per_entity_and_preserved_on_later_activation(self):
        self.run_migration()
        output, queries = self.run_migration()
        self.assertEqual(output['result'], 0)
        self.assertEqual(queries, [])
        self.db.execute("UPDATE fixture_product_extrafields SET product_photovoltaic_brand='' WHERE fk_object=1")
        self.db.commit()
        self.run_migration()
        self.assertEqual(self.brands()[0][2], '')
        output, _ = self.run_migration(entity=2)
        self.assertEqual(output['result'], 1)
        self.assertEqual(self.brands()[7][2], 'Other entity')
        self.assertEqual(self.db.execute('SELECT COUNT(*) FROM fixture_const').fetchone()[0], 2)

    def test_other_versions_never_query_or_write(self):
        before = self.brands()
        for version in ('1.24.0', '1.24.1-beta', '1.24.2', '1.25.0'):
            output, queries = self.run_migration(version)
            self.assertEqual((output['result'], queries), (0, []))
        self.assertEqual(self.brands(), before)

    def test_entity_must_match_the_current_context(self):
        for requested_entity in (0, 2):
            output, queries = self.run_migration(requested_entity=requested_entity)
            self.assertEqual((output['result'], queries), (-1, []))

    def test_oversize_brand_is_preserved_pending_and_can_be_retried(self):
        self.db.execute('UPDATE fixture_product_extrafields SET jpsun_marque=? WHERE fk_object=1', ('é' * 129,))
        self.db.commit()
        output, _ = self.run_migration()
        self.assertEqual((output['result'], output['pending'], output['cached']), (0, 1, 0))
        self.assertIsNone(self.brands()[0][2])
        self.assertEqual(self.brands()[0][1], 'é' * 129)
        self.target_type = 'varchar(255)'
        output, _ = self.run_migration()
        self.assertEqual(output['result'], 1)
        self.assertEqual(self.brands()[0][2], 'é' * 129)

    def test_missing_or_incompatible_target_is_pending_and_legacy_fields_hidden(self):
        for target in (None, 'integer'):
            self.target_type = target
            output, _ = self.run_migration()
            self.assertEqual((output['result'], output['pending'], output['cached']), (0, 5, 0))
            self.assertEqual(self.db.execute("SELECT enabled FROM fixture_extrafields WHERE name='jpsun_marque' AND entity=1").fetchone()[0], '0')
        self.target_type = 'varchar(128)'
        output, _ = self.run_migration()
        self.assertEqual(output['result'], 1)

    def test_missing_source_is_a_completed_visibility_migration(self):
        self.has_source = False
        output, _ = self.run_migration()
        self.assertEqual((output['result'], output['pending']), (1, 0))

    def test_failed_write_or_commit_rolls_back_data_metadata_and_marker(self):
        for failure in ('BEGIN', 'DESC ', 'SELECT COUNT', 'SET product_photovoltaic_brand',
                        "SET enabled = '0'", 'INSERT INTO fixture_const', 'COMMIT'):
            with self.subTest(failure=failure):
                before = self.brands()
                metadata = list(self.db.execute('SELECT * FROM fixture_extrafields'))
                output, _ = self.run_migration(fail=failure)
                self.assertEqual((output['result'], output['cached']), (-1, 0))
                self.assertEqual(self.brands(), before)
                self.assertEqual(list(self.db.execute('SELECT * FROM fixture_extrafields')), metadata)
                self.assertEqual(self.db.execute('SELECT COUNT(*) FROM fixture_const').fetchone()[0], 0)


if __name__ == '__main__':
    unittest.main(verbosity=2)
