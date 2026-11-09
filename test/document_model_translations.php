<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/** CLI discovery and native translation/selector fixture; no web entry point or business writes. */
if (PHP_SAPI !== 'cli') {
	exit(1);
}
error_reporting(E_ALL & ~E_DEPRECATED);
set_error_handler(static function ($level, $message, $file, $line) {
	if (error_reporting() & $level) {
		throw new ErrorException($message, 0, $level, $file, $line);
	}
	return false;
});
$core = realpath($argv[1] ?? '');
if (!$core || !is_file($core.'/core/class/translate.class.php')) {
	throw new RuntimeException('Pass a Dolibarr htdocs directory');
}
define('DOL_DOCUMENT_ROOT', $core);
define('DOL_VERSION', $argv[2] ?? '25.0.0-alpha');
define('DOL_DATA_ROOT', sys_get_temp_dir());
define('DOL_URL_ROOT', '');
define('MAIN_DB_PREFIX', 'fixture_');
$module = dirname(__DIR__);
$conf = (object) array(
	'entity' => 2, 'currency' => 'EUR', 'global' => (object) array(),
	'modules' => array_fill_keys(array('jpsun', 'project', 'invoice', 'propal'), 1),
	'file' => (object) array('dol_document_root' => array('main' => $core, 'alt0' => dirname($module)), 'dol_url_root' => array('alt0' => '/custom')),
	'modules_parts' => array('hooks' => array('jpsun' => array('formfile'))),
);
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
require_once $module.'/class/actions_jpsun.class.php';
class DoliDB {
	public $rows = array();
	public $index = 0;
	public function prefix() { return MAIN_DB_PREFIX; }
	public function escape($value) { return addslashes($value); }
	public function query($sql) { $this->index = 0; return strpos($sql, 'document_model') !== false; }
	public function num_rows($result) { return count($this->rows); }
	public function fetch_object($result) { return isset($this->rows[$this->index]) ? (object) $this->rows[$this->index++] : false; }
}
$db = new DoliDB();
$mysoc = (object) array('country_code' => 'FR', 'name' => 'Fixture');
$user = (object) array('id' => 1);
$hookmanager = new HookManager($db);
$hookmanager->initHooks(array('formfile'));
$names = JpsunDocumentModelTranslations::getNameKeys();
$languages = array('fr_FR', 'en_US', 'de_DE', 'es_ES', 'it_IT');
$modelPath = $argv[3] ?? '';
$checks = 0;
foreach ($languages as $language) {
	$langs = new Translate('', $conf);
	$langs->setDefaultLang($language);
	$langs->load('main');
	if ($modelPath !== '') {
		// Each file runs in its own process: historical classes share the name pdf_jpsun.
		if (!preg_match('~^core/modules/[a-z_]+/doc/pdf_[a-z0-9_]+\.modules\.php$~', $modelPath)) {
			throw new RuntimeException('Invalid fixture model path');
		}
		$source = file_get_contents($module.'/'.$modelPath);
		preg_match("~\\\$this->name = \\\$langs->trans\\('([^']+)'\\)~", $source, $nameMatch);
		preg_match("~\\\$this->description = \\\$langs->trans\\('([^']+)'\\)~", $source, $descriptionMatch);
		if (!$nameMatch || !$descriptionMatch) {
			throw new RuntimeException('Missing translatable metadata: '.$modelPath);
		}
		require_once $module.'/'.$modelPath;
		$class = basename($modelPath, '.modules.php');
		$model = new $class($db);
		foreach (array($nameMatch[1] => $model->name, $descriptionMatch[1] => $model->description) as $key => $value) {
			if ($value === '' || $value === $key || $value !== $langs->trans($key)) {
				throw new RuntimeException($language.' missing '.$key);
			}
			$checks++;
		}
	} else {
		foreach ($names as $type => $models) {
			$db->rows = array();
			foreach ($models as $identifier => $key) {
				$db->rows[] = array('id' => $identifier, 'doc_template_name' => $identifier, 'label' => 'Previously saved label', 'description' => '');
			}
			$db->rows[] = array('id' => 'foreign_model', 'doc_template_name' => 'foreign_model', 'label' => 'Custom label', 'description' => '');
			$before = $db->rows;
			$list = getListOfModels($db, $type);
			foreach ($models as $identifier => $key) {
				$expected = version_compare(DOL_VERSION, '23.0.0', '>=') ? $langs->trans($key) : 'Previously saved label';
				if ($list[$identifier] !== $expected || count($list) !== count($models) + 1 || $db->rows !== $before || $list['foreign_model'] !== 'Custom label') {
					throw new RuntimeException('Native selector changed keys, data or unrelated labels');
				}
				$checks++;
			}
		}
	}
}
echo $checks." native metadata/selector checks passed\n";
