#!/usr/bin/env python3
"""Regression checks: real descriptor, simulated installation services and SQLite dictionary."""
import json
from pathlib import Path
import sqlite3
import subprocess
import sys
import tempfile
import unittest

MODULE = Path(__file__).resolve().parents[1]
CORE = Path(sys.argv.pop(1)).resolve() if len(sys.argv) > 1 else None
RETIRED = (
    'jpsun_site_name', 'jpsun_installed_power_kwc', 'jpsun_pv_module_product',
    'jpsun_pv_module_qty', 'jpsun_inverter_product', 'jpsun_inverter_qty',
    'jpsun_dc_boxes_qty', 'jpsun_ac_boxes_qty', 'jpsun_access_code', 'jpsun_pdl_number',
)
KEPT = (
    'jpsun_contract_recurrence', 'jpsun_contract_payment_day', 'jpsun_distance_company_km',
    'jpsun_revaluation_index_sn', 'jpsun_inverter_install_height_m',
    'jpsun_dc_box_install_height_m', 'jpsun_ac_box_install_height_m',
)
EVENTS = tuple('JPSUN_DDR_' + suffix for suffix in (
    'INFO', 'R_CLIENT', 'REL', 'SEND', 'R_ENEDIS', 'R_JPSUN', 'R_TIERS', 'COMP',
    'TR', 'OK', 'AC', 'JPS_TRAV', 'ENE_TRAV', 'PAY', 'CONS_OK', 'MES_ASK', 'MES_OK',
))
HARNESS = r'''<?php
error_reporting(E_ALL & ~E_DEPRECATED);
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
define('DOL_DOCUMENT_ROOT', $argv[1]);
define('DOL_VERSION', getenv('DOLIBARR_TEST_VERSION') ?: '24.0.0');
define('MAIN_DB_PREFIX', 'fixture_');
$conf = (object) array('entity' => $input['entity'], 'global' => new stdClass());
$langs = new class { public function trans($key) { return $key; } };
function img_picto(...$args) { return ''; }
function isModEnabled($module) { return $module === 'powerplantpv'; }
// This suite checks later activations; the migration itself has its own SQL regression suite.
function getDolGlobalInt($key, $default = 0) { return $key === 'JPSUN_MIGRATION_1241_DONE' ? 1 : $default; }
function dol_include_once($path) {
    if ($path !== '/core/class/extrafields.class.php') { throw new RuntimeException($path); }
    return 1;
}
function dolibarr_set_const(...$args) { return 1; }
class ExtraFields {
    public static $definitions = array();
    public static $writes = array();
    public $attributes = array();
    public $error = '';
    public $errors = array();
    public function __construct($db) { }
    public function fetch_name_optionals_label($element) {
        $this->attributes[$element]['label'] = array();
        foreach (self::$definitions[$element] ?? array() as $key => $field) {
            $this->attributes[$element]['label'][$key] = $field['label'];
        }
    }
    public function addExtraField(...$args) { return $this->record($args[5], $args); }
    public function update(...$args) { return $this->record($args[4], $args); }
    private function record($element, $args) {
        self::$writes[] = array($element, $args[0]);
        self::$definitions[$element][$args[0]] = array('label' => $args[1], 'args' => $args);
        return 1;
    }
}
$db = new class {
    public $queries = array();
    public function escape($value) { return str_replace("'", "''", $value); }
    public function query($sql) { $this->queries[] = $sql; return new stdClass(); }
    public function num_rows($result) { return 0; }
    public function free($result) { }
};
require $argv[2].'/core/modules/modJpsun.class.php';
class LegacySetupModule extends modJpsun {
    public $installationSql = array();
    public function load_tables() { return 1; }
    protected function _init($sql, $options = '') { $this->installationSql = $sql; return 1; }
}
ExtraFields::$definitions = $input['definitions'];
$module = new LegacySetupModule($db);
$result = $module->init();
echo json_encode(array('result' => $result, 'definitions' => ExtraFields::$definitions,
    'writes' => ExtraFields::$writes, 'sql' => array_merge($db->queries, $module->installationSql),
    'tabs' => $module->tabs, 'version' => $module->version), JSON_THROW_ON_ERROR);
'''


class LegacySetupTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        if CORE is None or not (CORE / 'core/modules/DolibarrModules.class.php').is_file():
            raise RuntimeError('Usage: python test/legacysetup.py <Dolibarr htdocs>')
        runtime = MODULE / 'test/.runtime'
        runtime.mkdir(exist_ok=True)
        cls.temp = tempfile.TemporaryDirectory(prefix='legacysetup-', dir=runtime)
        cls.root = Path(cls.temp.name)
        cls.harness = cls.root / 'installation.php'
        cls.harness.write_text(HARNESS, encoding='utf-8', newline='\n')

    @classmethod
    def tearDownClass(cls):
        cls.temp.cleanup()

    def activate(self, definitions, entity):
        process = subprocess.run(
            ['php', str(self.harness), str(CORE), str(MODULE)],
            input=json.dumps({'definitions': definitions, 'entity': entity}),
            text=True, encoding='utf-8', capture_output=True, check=True,
        )
        self.assertEqual(process.stderr, '')
        result = json.loads(process.stdout)
        self.assertEqual(result['result'], 1)
        self.assertEqual(result['tabs'], [])
        self.assertEqual(result['version'], '1.24.1')
        return result

    def test_fresh_and_repeated_activation_do_not_create_retired_fields(self):
        for entity in (1, 2):
            state = {}
            for _ in range(2):
                result = self.activate(state, entity)
                state = result['definitions']
                self.assertTrue(set(KEPT).issubset(state['contrat']))
                self.assertTrue(set(RETIRED).isdisjoint(state['contrat']))
                self.assertNotIn('jpsun_marque', state['product'])
                self.assertTrue(all(not key.startswith('jpsun_project_') for key in state.get('projet', {})))

    def test_existing_definitions_and_values_remain_untouched(self):
        for entity in (1, 2):
            original = {key: {'label': 'Customized ' + key, 'enabled': '0',
                             'default': '', 'value': 0, 'entity': entity} for key in RETIRED}
            state = {'contrat': original.copy()}
            for _ in range(2):
                result = self.activate(state, entity)
                state = result['definitions']
                self.assertEqual({key: state['contrat'][key] for key in RETIRED}, original)
                self.assertTrue(set(RETIRED).isdisjoint(key for element, key in result['writes']
                                                      if element == 'contrat'))
                self.assertFalse(any(key in sql for key in RETIRED for sql in result['sql']))

    def test_dictionary_and_translation_settings_are_preserved(self):
        db = sqlite3.connect(':memory:')
        self.addCleanup(db.close)
        db.executescript('''
            CREATE TABLE llx_const(name, entity, value, type, visible, note);
            CREATE TABLE llx_c_actioncomm(id, code, type, libelle, module, active, todo, color, picto, position);
            CREATE TABLE llx_overwrite_trans(rowid INTEGER PRIMARY KEY, entity, lang, transkey, transvalue);
            CREATE TABLE llx_c_type_contact(element, source, code, libelle, active, module, position);
        ''')
        for index, code in enumerate(EVENTS):
            db.execute('INSERT INTO llx_c_actioncomm VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                       (1000 + index, code, 'user', 'Custom label', 'procedurespv', 0, 0, 'custom', 'custom', 99))
            for entity in (1, 2):
                for prefix in ('', 'Action'):
                    db.execute('INSERT INTO llx_overwrite_trans VALUES (NULL, ?, ?, ?, ?)',
                               (entity, 'fr_FR', prefix + code, 'Custom translation'))
        def snapshot():
            return (db.execute('SELECT * FROM llx_c_actioncomm').fetchall(),
                    db.execute("SELECT * FROM llx_overwrite_trans WHERE transkey LIKE '%JPSUN_DDR_%'").fetchall())
        before = snapshot()
        sql = '\n'.join(line for line in (MODULE / 'sql/data.sql').read_text(encoding='utf-8').splitlines()
                        if not line.lstrip().startswith('--'))
        for _ in range(2):
            for entity in (1, 2):
                # SQLite spells MySQL's INSERT IGNORE as INSERT OR IGNORE.
                db.executescript(sql.replace('__ENTITY__', str(entity))
                                 .replace('INSERT IGNORE', 'INSERT OR IGNORE'))
                self.assertEqual(snapshot(), before)
        self.assertEqual(db.execute('SELECT COUNT(*) FROM llx_c_type_contact').fetchone()[0], 4)

    def test_retired_url_stops_before_legacy_code_in_both_layouts(self):
        page = (MODULE / 'tabs/projet_enedis.php').read_bytes()
        for layout in ('jpsun', 'custom/jpsun'):
            for message in ('Fonctionnalité désactivée', 'Feature disabled'):
                with self.subTest(layout=layout, message=message):
                    site = self.root / layout.replace('/', '-') / str(len(message)) / 'htdocs'
                    target = site / layout / 'tabs/projet_enedis.php'
                    target.parent.mkdir(parents=True)
                    target.write_bytes(page)
                    bootstrap = ('<?php $langs = new class { public function trans($key) { return '
                                 + json.dumps(message, ensure_ascii=False)
                                 + '; } }; function accessforbidden($message) { http_response_code(403); '
                                 + 'echo json_encode(array(http_response_code(), $message)); exit; } return 1;')
                    (site / 'main.inc.php').write_text(bootstrap, encoding='utf-8', newline='\n')
                    result = subprocess.run(['php', str(target)], text=True, encoding='utf-8',
                                            capture_output=True, check=True)
                    self.assertEqual(result.stderr, '')
                    self.assertEqual(json.loads(result.stdout), [403, message])
                    (site / 'main.inc.php').unlink()
                    result = subprocess.run(['php', str(target)], text=True, capture_output=True, check=True)
                    self.assertEqual(result.stdout, 'Include of main fails')


if __name__ == '__main__':
    unittest.main(verbosity=2)
