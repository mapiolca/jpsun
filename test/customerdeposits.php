<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/** CLI worker for customerdeposits.py; real core helpers, simulated actor and SQL transport. */
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
$root = realpath($argv[1] ?? __DIR__.'/.runtime/24.0.2/htdocs');
if (!$root || !is_file($root.'/compta/facture/class/facture.class.php')) {
	throw new RuntimeException('Pass an existing Dolibarr htdocs directory');
}
$contracts = $argv[2] ?? $root.'/core/lib';
define('DOL_DOCUMENT_ROOT', $root);
define('DOL_VERSION', $input['version'] ?? (getenv('DOLIBARR_TEST_VERSION') ?: '24.0.0'));
define('DOL_DATA_ROOT', __DIR__.'/.runtime');
define('DOL_URL_ROOT', '');
define('MAIN_DB_PREFIX', 'fixture_');
$conf = (object) array(
	'entity' => $input['entity'] ?? 1, 'currency' => 'EUR', 'theme' => 'eldy',
	'modules' => array_fill_keys($input['modules'] ?? array('jpsun', 'accounting', 'invoice', 'order'), 1),
	'global' => (object) array('MAIN_MAX_DECIMALS_TOT' => $input['precision'] ?? 2, 'MAIN_MAX_DECIMALS_UNIT' => 5),
	'file' => (object) array('dol_document_root' => array('main' => $root, 'alt0' => dirname(__DIR__, 2)), 'dol_url_root' => array('alt0' => '/custom')),
	'modules_parts' => array('hooks' => array('jpsun' => array('toprightmenu', 'leftblock'))),
	'liste_limit' => 50,
);
require_once $contracts.'/functions.lib.php';
if (is_file($contracts.'/html.lib.php')) {
	require_once $contracts.'/html.lib.php';
}
require_once (is_file($contracts.'/hookmanager.class.php') ? $contracts.'/hookmanager.class.php' : $root.'/core/class/hookmanager.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once __DIR__.'/../class/jpsuncustomerdepositreport.class.php';
require_once __DIR__.'/../class/actions_jpsun.class.php';
$langs = new Translate('', $conf);
$language = $input['language'] ?? 'en_US';
if (!in_array($language, array('en_US', 'fr_FR'), true)) {
	throw new RuntimeException('Unsupported fixture language');
}
$langs->setDefaultLang($language);
$langs->load('jpsun@jpsun');

class DepositFixtureActor
{
	public $id = 7;
	public $socid = 0;
	public $admin = 0;
	public $denied = array();
	public function hasRight(...$parts) { return !in_array(implode('.', $parts), $this->denied, true); }
}

/** Transport generated production SELECT statements to Python's SQLite fixture. */
class DepositFixtureDatabase
{
	public $queries = array();
	public $error = '';
	public $stopAtQuery = false;
	public function prefix() { return MAIN_DB_PREFIX; }
	public function escape($value) { return str_replace("'", "''", $value); }
	public function sanitize($value) {
		if (!preg_match('/^[a-zA-Z0-9_.,-]+$/', $value)) { throw new RuntimeException('Invalid SQL identifier'); }
		return $value;
	}
	public function idate($time) { return date('Y-m-d H:i:s', $time); }
	public function jdate($value) { return $value ? strtotime($value) : null; }
	public function order($field, $direction) { return ' ORDER BY '.$field.' '.$direction; }
	public function plimit($limit, $offset = 0) { return ' LIMIT '.((int) $limit).' OFFSET '.((int) $offset); }
	public function query($sql) {
		if (strpos($sql, 'SELECT ') !== 0) { throw new RuntimeException('Unexpected write'); }
		$this->queries[] = $sql;
		if ($this->stopAtQuery) { throw new DepositFixturePageStop('Query reached'); }
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
}
$db = new DepositFixtureDatabase();
$hookmanager = new HookManager($db);
$user = new DepositFixtureActor();
$user->denied = $input['denied'] ?? array();
$user->socid = $input['socid'] ?? 0;
$user->admin = $input['admin'] ?? 0;
$mode = $input['mode'] ?? 'report';
$output = array();
if ($mode === 'page') {
	// Execute the actual page through its bootstrap, stopping before business reads/rendering.
	class DepositFixturePageStop extends RuntimeException { }
	function accessforbidden($message = '', $printheader = 1, $printfooter = 1, $showonlymessage = 0, $params = null) {
		throw new DepositFixturePageStop('Forbidden', 403);
	}
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['PHP_SELF'] = '/custom/jpsun/customerdeposits.php';
	$_SESSION = array();
	$db->stopAtQuery = true;
	try {
		include $input['page_path'];
		throw new RuntimeException('Page did not stop at its access guard or first query');
	} catch (DepositFixturePageStop $exception) {
		$output = array('denied' => $exception->getCode() === 403, 'queries' => $db->queries,
			'cutoff' => isset($date_cutoff) ? $date_cutoff : null);
	}
} elseif ($mode === 'translations') {
	foreach (file(__DIR__.'/../langs/en_US/jpsun.lang') as $entry) {
		$key = explode('=', trim($entry), 2)[0];
		if (preg_match('/^(DepositControl\w+|CustomerDepositControl|MenuAccountancyAdjustments|JpsunDepositControlRequirement)$/', $key)) {
			$output[$key] = $langs->transnoentities($key, '60', '40', '20');
		}
	}
} elseif ($mode === 'descriptor') {
	require_once __DIR__.'/../core/modules/modJpsun.class.php';
	$module = new modJpsun($db);
	$output = array('picto' => $module->picto, 'hooks' => $module->module_parts['hooks'], 'id' => $module->numero);
} elseif ($mode === 'hook') {
	$hookmanager->initHooks(array('toprightmenu', 'leftblock'));
	$menu = $input['menu'];
	$action = '';
	$returns = array();
	foreach (array('accountancy', 'accountancy', 'home', 'accountancy') as $mainmenu) {
		$ret = $hookmanager->executeHooks('menuLeftMenuItems', array('mainmenu' => $mainmenu), $menu, $action);
		if ($ret === 1) { $menu = $hookmanager->resArray; }
		$returns[] = $ret;
	}
	$output = array('menu' => $menu, 'returns' => $returns, 'hooks' => array_keys($hookmanager->hooks));
} elseif ($mode === 'icon') {
	$output = array('picto' => img_picto('JPSUN', 'jpsun@jpsun', '', 0, 1), 'object' => img_object('JPSUN', 'jpsun@jpsun', '', 0, 1));
} elseif ($mode === 'calculate') {
	$output = JpsunCustomerDepositReport::calculate(...$input['amounts']);
} else {
	$report = new JpsunCustomerDepositReport($db);
	$result = $report->fetchAll($user, strtotime($input['cutoff'] ?? '2026-12-31'), $input['filter'] ?? array(), $input['limit'] ?? 100, $input['offset'] ?? 0, $input['sortfield'] ?? 'f.datef', $input['sortorder'] ?? 'ASC');
	$output = array('result' => $result, 'lines' => $report->lines, 'total' => $report->num_total, 'totals' => $report->totals, 'error' => $report->error, 'queries' => count($db->queries));
}
echo json_encode(array('output' => $output), JSON_THROW_ON_ERROR)."\n";
