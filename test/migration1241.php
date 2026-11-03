<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/** CLI worker: native constant helpers, simulated database transport and transactions. */
if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit;
}
error_reporting(E_ALL & ~E_DEPRECATED);
set_error_handler(static function ($level, $message, $file, $line) {
	if (error_reporting() & $level) {
		throw new ErrorException($message, 0, $level, $file, $line);
	}
	return false;
});
$input = json_decode(trim(fgets(STDIN)), true, 512, JSON_THROW_ON_ERROR);
$root = realpath($argv[1] ?? '');
if (!$root || !is_file($root.'/core/lib/admin.lib.php')) {
	throw new RuntimeException('Pass an existing Dolibarr htdocs directory');
}
define('DOL_DOCUMENT_ROOT', $root);
define('DOL_VERSION', getenv('DOLIBARR_TEST_VERSION') ?: '24.0.0');
define('DOL_DATA_ROOT', __DIR__.'/.runtime');
define('DOL_URL_ROOT', '');
define('MAIN_DB_PREFIX', 'fixture_');
$conf = (object) array(
	'entity' => $input['entity'], 'modules' => array(), 'modules_parts' => array('hooks' => array()),
	'global' => (object) array('JPSUN_MIGRATION_1241_DONE' => $input['done']),
);
require_once $root.'/core/lib/functions.lib.php';
require_once $root.'/core/lib/admin.lib.php';
require_once __DIR__.'/../scripts/migrate_1_24_1.php';

/** Forward production SQL to Python; emulate the native nested transaction contract. */
class MigrationFixtureDatabase
{
	public $error = '';
	public $depth = 0;
	public function prefix() { return MAIN_DB_PREFIX; }
	public function escape($value) { return str_replace("'", "''", $value); }
	public function encrypt($value) { return "'".$this->escape($value)."'"; }
	public function decrypt($value) { return $value; }
	public function query($sql) {
		echo json_encode(array('sql' => $sql), JSON_THROW_ON_ERROR)."\n";
		fflush(STDOUT);
		$reply = json_decode(trim(fgets(STDIN)), true, 512, JSON_THROW_ON_ERROR);
		if (isset($reply['error'])) { $this->error = $reply['error']; return false; }
		return (object) array('rows' => $reply['rows'], 'index' => 0);
	}
	public function fetch_object($result) {
		return isset($result->rows[$result->index]) ? (object) $result->rows[$result->index++] : false;
	}
	public function free($result) { }
	public function lasterror() { return $this->error; }
	public function DDLDescTable($table) { return $this->query('DESC '.$table); }
	public function begin() {
		if (!$this->depth && !$this->query('BEGIN')) { return 0; }
		$this->depth++;
		return 1;
	}
	public function commit() {
		if ($this->depth > 1) { $this->depth--; return 1; }
		if (!$this->query('COMMIT')) { return 0; }
		$this->depth = 0;
		return 1;
	}
	public function rollback() {
		if ($this->depth > 1) { $this->depth--; return 1; }
		$this->depth = 0;
		return $this->query('ROLLBACK') ? 1 : 0;
	}
}
$db = new MigrationFixtureDatabase();
$migration = new JpsunMigration1241($db);
$result = $migration->run($input['version'], $input['requested_entity'] ?? $input['entity']);
echo json_encode(array('output' => array('result' => $result, 'pending' => $migration->pending,
	'error' => $migration->error, 'cached' => getDolGlobalInt('JPSUN_MIGRATION_1241_DONE'))), JSON_THROW_ON_ERROR)."\n";
