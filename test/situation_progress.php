<?php
/** CLI fixture: native calculation and native card table, with no database or business writes. */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
set_error_handler(static function ($level, $message, $file, $line) {
	if (error_reporting() & $level) { throw new ErrorException($message, 0, $level, $file, $line); }
	return false;
});
define('DOL_DOCUMENT_ROOT', realpath($argv[1]));
define('DOL_VERSION', $argv[2] ?? '24.0.2');
define('DOL_URL_ROOT', '/erp');
define('DOL_DATA_ROOT', __DIR__.'/.runtime');
define('MAIN_DB_PREFIX', 'fixture_');
$conf = (object) array('entity' => 1, 'currency' => 'EUR', 'modules' => array('jpsun' => 1, 'invoice' => 1),
	'global' => (object) array('MAIN_MAX_DECIMALS_UNIT' => 5, 'MAIN_MAX_DECIMALS_TOT' => 2,
		'MAIN_MAX_DECIMALS_SHOWN' => 2, 'INVOICE_USE_SITUATION' => 1),
	'file' => (object) array('dol_document_root' => array(DOL_DOCUMENT_ROOT)));
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/../class/actions_jpsun.class.php';
$_SESSION = array('newtoken' => 'fixture', 'token' => 'fixture');
require_once DOL_DOCUMENT_ROOT.'/core/db/sqlite3.class.php';
$db = (new ReflectionClass('DoliDBSqlite3'))->newInstanceWithoutConstructor();
$hookmanager = new class {
	public $resArray = array();
	public function executeHooks($name, $parameters, &$object = null, &$action = null) { return 0; }
};
$mysoc = (object) array('country_code' => 'FR', 'country_id' => 1, 'currency_code' => 'EUR');
$user = new class {
	public $socid = 0;
	public $allowed = true;
	public function hasRight($module, $right, $detail = '') { return $this->allowed; }
};
$langs = new Translate(DOL_DOCUMENT_ROOT, $conf);
$langs->setDefaultLang('fr_FR');
$langs->loadLangs(array('main', 'bills'));
foreach (file(__DIR__.'/../langs/fr_FR/jpsun.lang') as $line) {
	$pair = explode('=', trim($line), 2);
	if (count($pair) === 2) { $langs->tab_translate[$pair[0]] = $pair[1]; }
}
$checks = 0;
function check($ok, $message) {
	global $checks;
	if (!$ok) { throw new RuntimeException($message); }
	$checks++;
}
$cases = array(
	array(19.0, array(array(9000, 1, 0, 10, 0), array(1000, 1, 0, 100, 1))),
	array(10.0, array(array(9000, 1, 0, 0, 0), array(1000, 1, 0, 100, 1))),
	array(50.0, array(array(2000, 1, 50, 0, 0), array(1000, 1, 0, 100, 1))),
	array(25.0, array(array(1000, 3, 0, 0, 0), array(1000, 1, 0, 100, 1))),
	array(0.0, array(array(1000, 1, 0, 0, 0))),
	array(100.0, array(array(1000, 1, 0, 100, 0))),
	array(25.0, array(array(-1000, 1, 0, 25, 0))),
	array(50.0, array(array(2000, 1, 0, 50, 0), array(-1000, 1, 0, 50, 1))),
	array(20.0, array(array(1000, 1, 0, 20, 0), array(9999, 1, 0, 100, 9))),
	array(null, array()), array(null, array(array(0, 1, 0, 100, 0))),
	array(null, array(array(1000, 1, 100, 50, 0))),
	array(null, array(array(1000, 1, 0, 50, 0), array(-1000, 1, 0, 50, 1))),
	array(86.6666666667, array(array(2000, 1, 0, 85, 0), array(1000, 1, 0, 90, 1))),
);
foreach (array(1, 2) as $mode) {
	$conf->global->INVOICE_USE_SITUATION = $mode;
	foreach ($cases as $case) {
		$invoice = new Facture($db);
		$invoice->lines = array();
		foreach ($case[1] as $values) {
			$line = new FactureLigne($db);
			list($line->subprice, $line->qty, $line->remise_percent, $line->situation_percent, $line->product_type) = $values;
			$invoice->lines[] = $line;
		}
		$value = JpsunSituationProgress::calculate($invoice);
		check($case[0] === null ? $value === null : abs($value - $case[0]) < 0.000001, 'Weighted calculation');
	}
}

// Amounts are invoice increments in both native storage modes (legacy totals subtract prior invoices).
foreach (array(1, 2) as $mode) {
	$conf->global->INVOICE_USE_SITUATION = $mode;
	$first = new Facture($db); $first->type = Facture::TYPE_SITUATION;
	$first->lines = $invoice->lines; $first->total_ht = 400;
	$second = clone $first; $second->total_ht = 0;
	$last = clone $first; $last->total_ht = 2600;
	check(abs(JpsunSituationProgress::calculateGlobal(array($first, $second)) - 100 * 400 / 3000) < 0.000001, 'Subtotal against market');
	check(JpsunSituationProgress::calculateGlobal(array($first, $second, $last)) === 100.0, 'Global against market');
	$credit = clone $first; $credit->type = Facture::TYPE_CREDIT_NOTE; $credit->total_ht = -300; $credit->lines = array();
	check(JpsunSituationProgress::calculateGlobal(array($first, $last, $credit)) === 90.0, 'Credit reduces total, keeps market');
	check(JpsunSituationProgress::calculateGlobal(array($credit)) === null, 'No market');
	$last->lines = array();
	check(JpsunSituationProgress::calculateGlobal(array($first, $last)) === null, 'Empty current market cannot silently reuse an old base');
	$line = new FactureLigne($db); $line->product_type = 0; $line->qty = 2; $line->subprice = 1000; $line->remise_percent = 50;
	$last->lines = array($line); $last->total_ht = 600;
	check(JpsunSituationProgress::calculateGlobal(array($first, $last)) === 100.0, 'Changed market, quantity and discount');
	$last->total_ht = -400;
	check(JpsunSituationProgress::calculateGlobal(array($first, $last)) === 0.0, 'Zero numerator is valid');
	$line->subprice = 0;
	check(JpsunSituationProgress::calculateGlobal(array($last)) === null, 'Zero market');
}
// Only the native access/loading layer is simulated; render the original table source below.
class ProgressInvoiceFixture extends Facture
{
	public function getNomUrl(...$args) { return '<a href="/erp/compta/facture/card.php?facid='.$this->id.'">INV-'.$this->id.'</a>'; }
	public function getSommePaiement($multicurrency = 0) { return 0; }
	public function getSumDepositsUsed($multicurrency = 0) { return 0; }
	public function getSumCreditNotesUsed($multicurrency = 0) { return 0; }
	public function getLibStatut($mode = 0, $paid = -1) { return 'Status'; }
}
$object = new ProgressInvoiceFixture($db);
$object->id = 2; $object->socid = 10; $object->entity = 1; $object->situation_cycle_ref = 42;
$object->situation_counter = 2; $object->type = Facture::TYPE_SITUATION;
$object->total_ht = 100; $object->total_ttc = 120;
$object->lines = $invoice->lines;
$previous = clone $object; $previous->id = 1; $previous->situation_counter = 1;
$next = clone $object; $next->id = 3; $next->situation_counter = 3; $next->lines = array();
$object->tab_previous_situation_invoice = array($previous);
$object->tab_next_situation_invoice = array($next);
$action = ''; $parameters = array('context' => 'invoicecard:globalcard');
$hooks = new ActionsJpsun($db);
require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
$nativeManager = new HookManager($db);
$nativeManager->contextarray = array('invoicecard', 'globalcard');
$nativeManager->hooks = array('invoicecard' => array('jpsun' => $hooks));
$nativeManager->hooksSorted = array('invoicecard' => array('50:jpsun' => $hooks));
ob_start();
$result = $nativeManager->executeHooks('addMoreActionsButtons', array(), $object, $action);
$output = ob_get_clean();
check($result === 0 && strpos($output, 'window.jpsunSituationProgress(') !== false, 'Native invoice card must receive hook output');
check($hooks->resprints === null, 'Native HookManager resets the module output');
preg_match('/window\.jpsunSituationProgress\((.*?)\);/s', $output, $matches);
$config = json_decode($matches[1] ?? '', true);
check(is_array($config) && count($config['values']) === 3, 'Authorized payload');
check($config['values'][3] === '–', 'Empty next invoice');
foreach (array('right', 'external', 'entity', 'parent', 'cycle', 'module', 'context') as $refusal) {
	$user->allowed = $refusal !== 'right'; $user->socid = $refusal === 'external' ? 11 : 0;
	$previous->entity = $refusal === 'entity' ? 2 : 1;
	$previous->socid = $refusal === 'parent' ? 11 : 10;
	$previous->situation_cycle_ref = $refusal === 'cycle' ? 43 : 42;
	$conf->modules['jpsun'] = $refusal === 'module' ? 0 : 1;
	$parameters['context'] = $refusal === 'context' ? 'propalcard' : 'invoicecard';
	$hooks->resprints = null;
	ob_start(); $hooks->addMoreActionsButtons($parameters, $object, $action, $hookmanager); $output = ob_get_clean();
	check($output === '' && empty($hooks->resprints), 'Refuse '.$refusal);
}
$parameters['context'] = 'invoicecard';
$source = file_get_contents(DOL_DOCUMENT_ROOT.'/compta/facture/card.php');
$start = strpos($source, '// List of previous situation invoices');
$end = strpos($source, '// List of payments already done', $start);
check($start !== false && $end !== false, 'Native table source');
$tables = array();
foreach (array(0, 1) as $bank) {
	$conf->modules['bank'] = $bank;
	$conf->global->INVOICE_USE_SITUATION = 1;
	$total_prev_ht = $total_prev_ttc = $total_global_ht = $total_global_ttc = 0;
	ob_start(); eval(substr($source, $start, $end - $start)); $html = ob_get_clean();
	$dom = new DOMDocument();
	@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
	$rows = array();
	foreach ($dom->getElementsByTagName('tr') as $row) {
		$cells = array();
		foreach ($row->getElementsByTagName('td') as $cell) {
			$links = array();
			foreach ($cell->getElementsByTagName('a') as $a) { $links[] = $a->getAttribute('href'); }
			$cells[] = array('textContent' => $cell->textContent, 'colSpan' => (int) ($cell->getAttribute('colspan') ?: 1), 'rowSpan' => 1, 'links' => $links);
		}
		$rows[] = array('className' => $row->getAttribute('class'), 'cells' => $cells);
	}
	$tables[] = $rows;
}
file_put_contents($argv[3], json_encode(array('config' => $config, 'tables' => $tables), JSON_UNESCAPED_UNICODE));
echo 'OK '.$checks.' PHP checks ('.DOL_VERSION.")\n";
