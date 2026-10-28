<?php
/** Targeted CLI tests using the installed native Sponge class and PDF helpers.
 * Usage: php test/invoice.php /path/to/dolibarr/htdocs [version]
 * No business writes, mail, activation or network access. Database replies are simulated.
 */

error_reporting(E_ALL & ~E_DEPRECATED);
set_error_handler(static function ($level, $message, $file, $line) {
	if (error_reporting() & $level) {
		throw new ErrorException($message, 0, $level, $file, $line);
	}
	return false;
});
define('DOL_DOCUMENT_ROOT', realpath($argv[1] ?? __DIR__.'/../../dolibarr/htdocs'));
define('DOL_VERSION', $argv[2] ?? '24.0.0');
define('MAIN_DB_PREFIX', 'test_');
define('DOL_DATA_ROOT', __DIR__.'/.runtime');
define('DOL_URL_ROOT', '');

$conf = (object) array(
	'modules' => array('jpsun' => 1, 'invoice' => 1, 'facture' => 1),
	'entity' => 1, 'currency' => 'EUR', 'global' => (object) array(
		'MAIN_MAX_DECIMALS_UNIT' => 5, 'MAIN_MAX_DECIMALS_TOT' => 2,
		'MAIN_MAX_DECIMALS_SHOWN' => 2,
		'MAIN_PDF_FORMAT' => 'A4',
	),
	'jpsun' => (object) array('enabled' => 1),
	'facture' => (object) array('enabled' => 1, 'multidir_output' => array(1 => DOL_DATA_ROOT, 2 => DOL_DATA_ROOT.'/owner')),
	'multicurrency' => (object) array('enabled' => 0),
	'file' => (object) array('dol_document_root' => array(DOL_DOCUMENT_ROOT)),
);
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/blockedlog/lib/blockedlog.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once __DIR__.'/../core/modules/facture/doc/pdf_jpsuninvoice.modules.php';

$langs = new Translate(DOL_DOCUMENT_ROOT, $conf);
$langs->setDefaultLang('fr_FR');
$langs->loadLangs(array('main', 'bills'));
foreach (file(__DIR__.'/../langs/fr_FR/jpsun.lang') as $entry) {
	$parts = explode('=', trim($entry), 2);
	if (count($parts) === 2) {
		$langs->tab_translate[$parts[0]] = $parts[1];
	}
}
$mysoc = (object) array('country_code' => 'FR', 'currency_code' => 'EUR');
$hookmanager = new class {
	public $resArray = array();
	public function executeHooks($name, $parameters, &$object = null, &$action = null) { return 0; }
};

class InvoiceTestDatabase
{
	public $rows = array();
	public $queries = array();
	public $fail = false;
	public function prefix() { return MAIN_DB_PREFIX; }
	public function escape($value) { return addslashes($value); }
	public function query($sql) {
		$this->queries[] = $sql;
		if ($this->fail) { return false; }
		preg_match('/sr.rowid = (\d+)/', $sql, $match);
		return (object) array('rows' => isset($this->rows[(int) ($match[1] ?? 0)]) ? array($this->rows[(int) $match[1]]) : array());
	}
	public function num_rows($result) { return count($result->rows); }
	public function fetch_object($result) { return $result->rows[0] ?? false; }
	public function free($result) {}
	public function jdate($date) { return 0; }
	public function error() { return 'simulated database failure'; }
}

class InvoiceTestDocument extends pdf_jpsuninvoice
{
	public function totals(&$pdf, $object) { return $this->drawTotalTable($pdf, $object, 0, 200, $GLOBALS['langs'], null); }
}
class InvoiceTestInvoice extends Facture
{
	public function getSumCreditNotesUsed($multicurrency = 0) { return 0; }
	public function getSumDepositsUsed($multicurrency = 0) { return 0; }
	public function displayRetainedWarranty() { return false; }
}
class InvoiceTestPDF
{
	public $cells = array();
	public $x = 0;
	public $y = 0;
	public function SetFont(...$args) {}
	public function SetTextColor(...$args) {}
	public function SetFillColor(...$args) {}
	public function SetXY($x, $y) { $this->x = $x; $this->y = $y; }
	public function MultiCell($width, $height, $text, ...$args) { $this->cells[] = array($this->x, $this->y, $text); }
	public function getStringHeight($width, $text) { return 4; }
}

function expect($condition, $message) {
	if (!$condition) { throw new RuntimeException($message); }
}
function line($id, $ht, $gross, $discount = 0) {
	$result = new FactureLigne($GLOBALS['db']);
	$result->id = $id;
	$result->total_ht = $ht;
	$result->multicurrency_total_ht = $ht * 2;
	$result->subprice = $gross;
	$result->multicurrency_subprice = $gross * 2;
	$result->qty = 1;
	$result->fk_remise_except = $discount;
	$result->special_code = 0;
	$result->situation_percent = 100;
	$result->product_type = 0;
	return $result;
}
function discountRow($id, $line, $type) {
	$row = (object) array_fill_keys(array('fk_user', 'discount_type', 'amount_ht', 'amount_tva', 'amount_ttc', 'amount_localtax1', 'amount_localtax2', 'tva_tx', 'localtax1_tx', 'localtax2_tx', 'localtax1_type', 'localtax2_type', 'vat_src_code', 'multicurrency_amount_ht', 'multicurrency_amount_tva', 'multicurrency_amount_ttc', 'fk_facture', 'fk_invoice_supplier_line', 'fk_invoice_supplier', 'fk_invoice_supplier_source', 'description', 'datec', 'ref_facture_source', 'ref_invoice_supplier_source', 'type_invoice_supplier_source'), 0);
	$row->rowid = $id;
	$row->fk_soc = 10;
	$row->fk_facture_line = $line;
	$row->fk_facture_source = 100 + $id;
	$row->type_facture_source = $type;
	return $row;
}

$db = new InvoiceTestDatabase();
$discovered = new pdf_jpsuninvoice($db);
expect($discovered->name === 'jpsuninvoice', 'Native model discovery');
expect($discovered->version === (version_compare(DOL_VERSION, '24.0.0', '>=') ? 'dolibarr' : 'disabled'), 'Unsupported model hidden during discovery');
$model = (new ReflectionClass(InvoiceTestDocument::class))->newInstanceWithoutConstructor();
$model->db = $db;
$model->page_largeur = 210;
$model->marge_droite = 10;
$model->tva_array = array();
$model->localtax1 = array();
$model->localtax2 = array();
$model->showAmountBeforeDiscount = 1;
$model->showDiscountAmount = 1;
$invoice = (new ReflectionClass(InvoiceTestInvoice::class))->newInstanceWithoutConstructor();
$invoice->db = $db;
$invoice->id = 1;
$invoice->socid = 10;
$invoice->entity = 2;
$invoice->type = Facture::TYPE_STANDARD;
$invoice->multicurrency_tx = 1;
$invoice->total_ht = 2432.50;
$invoice->total_tva = 486.50;
$invoice->total_ttc = 2919;
$invoice->revenuestamp = 0;
$invoice->lines = array(line(1, 3625, 3625), line(2, -1192.50, -1192.50, 7));
$db->rows[7] = discountRow(7, 2, Facture::TYPE_DEPOSIT);

expect(JpsunInvoiceCompatibility::isAvailable() === version_compare(DOL_VERSION, '24.0.0', '>='), 'Version gate');
if (!JpsunInvoiceCompatibility::isAvailable()) {
	expect($model->write_file($invoice, $langs) === -1, 'Unsupported generation refused');
	echo "OK: unsupported version refused before generation\n";
	exit(0);
}

$summary = $model->getDepositSummary($invoice, $langs);
expect($summary === array('deposits' => 1192.5, 'discounts' => 0.0, 'total' => 2432.5), 'Screenshot deposits separated');
$baseline = (new ReflectionClass(pdf_sponge::class))->newInstanceWithoutConstructor();
foreach (get_object_vars($model) as $key => $value) { $baseline->$key = $value; }
$baselinePdf = new InvoiceTestPDF();
$nativeTotals = new ReflectionMethod(pdf_sponge::class, 'drawTotalTable');
$nativeTotals->setAccessible(true);
$nativeTotals->invokeArgs($baseline, array(&$baselinePdf, $invoice, 0, 200, $langs, null));
$discountRow = array_values(array_filter($baselinePdf->cells, static function ($cell) use ($langs) { return $cell[2] === $langs->transnoentities('TotalDiscount'); }));
expect(count($discountRow) === 1, 'Native baseline discount row');
$baselineAmounts = array_values(array_filter($baselinePdf->cells, static function ($cell) use ($discountRow) { return $cell[0] == 170 && $cell[1] == $discountRow[0][1]; }));
expect($baselineAmounts[0][2] === price(1192.5, 0, $langs), 'Original Sponge includes deposits in total discount');
$pdf = new InvoiceTestPDF();
$before = serialize(array($invoice->total_ht, $invoice->total_tva, $invoice->total_ttc, array_map(static function ($line) { $copy = clone $line; $copy->db = null; return $copy; }, $invoice->lines)));
$model->totals($pdf, $invoice);
expect(serialize(array($invoice->total_ht, $invoice->total_tva, $invoice->total_ttc, array_map(static function ($line) { $copy = clone $line; $copy->db = null; return $copy; }, $invoice->lines))) === $before, 'Invoice not mutated');
$labels = array_column(array_filter($pdf->cells, static function ($cell) { return $cell[0] == 120; }), 2);
expect(array_slice($labels, 0, 4) === array($langs->transnoentities('TotalHTBeforeDiscount'), 'Acomptes déduits', $langs->transnoentities('TotalDiscount'), $langs->transnoentities('TotalHT')), 'Summary ordering');
expect($model->showAmountBeforeDiscount === 1 && $model->showDiscountAmount === 1, 'Native flags restored');
expect(count(array_filter($db->queries, static function ($sql) { return strpos($sql, 'sr.entity IN (1)') !== false; })) > 0, 'Native entity scope retained');
$model->heightforinfotot = 50;
$model->defineColumnField($invoice, $langs);
expect($model->heightforinfotot === 74, 'Summary reserved before native pagination');
$model->showAmountBeforeDiscount = 0;
$model->showDiscountAmount = 0;
$hiddenPdf = new InvoiceTestPDF();
$model->totals($hiddenPdf, $invoice);
expect($hiddenPdf->cells[0][2] === 'Acomptes déduits', 'Deposits remain explicit when native discounts are hidden');
expect($model->showDiscountAmount === 0 && $model->showAmountBeforeDiscount === 0, 'Hidden flags restored');
$model->showAmountBeforeDiscount = 1;
$model->showDiscountAmount = 1;

if (isset($argv[3]) && is_file($argv[3])) {
	// Optional visual QA with real TCPDF; this is a synthetic summary, not an issued invoice.
	define('K_PATH_CACHE', DOL_DATA_ROOT.'/');
	if (!is_dir(DOL_DATA_ROOT)) { mkdir(DOL_DATA_ROOT, 0777, true); }
	require_once $argv[3];
	$render = new TCPDF();
	$render->setPrintHeader(false);
	$render->setPrintFooter(false);
	$render->SetMargins(10, 10, 10);
	$render->SetAutoPageBreak(true, 25);
	$render->AddPage();
	$render->SetFont('helvetica', '', 10);
	$render->SetXY(10, 15);
	$render->MultiCell(190, 8, 'Simulation du récapitulatif JPSUN - données de test', 0, 'L');
	$model->tva_array = array('20' => array('vatrate' => '20', 'vatcode' => '', 'amount' => 486.5));
	$model->totals($render, $invoice);
	$render->Output(DOL_DATA_ROOT.'/invoice-summary.pdf', 'F');
	$model->tva_array = array();
}

$invoice->lines[0] = line(1, 3500, 3625);
$invoice->total_ht = 2307.50;
$summary = $model->getDepositSummary($invoice, $langs);
expect($summary['discounts'] === 125.0 && $summary['deposits'] === 1192.5, 'Commercial discount retained');
$invoice->lines[] = line(3, -50, -50, 8);
$db->rows[8] = discountRow(8, 3, Facture::TYPE_CREDIT_NOTE);
expect($model->getDepositSummary($invoice, $langs)['deposits'] === 1192.5, 'Credit note not treated as deposit');
$invoice->lines[] = line(4, -10, -10);
expect($model->getDepositSummary($invoice, $langs)['deposits'] === 1192.5, 'Negative line not treated as deposit');
$invoice->lines[] = line(5, -100, -100, 9);
$db->rows[9] = discountRow(9, 5, Facture::TYPE_DEPOSIT);
expect($model->getDepositSummary($invoice, $langs)['deposits'] === 1292.5, 'Multiple deposits aggregated');
$conf->multicurrency->enabled = 1;
$conf->modules['multicurrency'] = 1;
$invoice->multicurrency_tx = 2;
$invoice->multicurrency_total_ht = $invoice->total_ht * 2;
expect($model->getDepositSummary($invoice, $langs)['deposits'] === 2585.0, 'Foreign currency line amounts used');
$conf->multicurrency->enabled = 0;
unset($conf->modules['multicurrency']);
$invoice->multicurrency_tx = 1;

$savedLines = $invoice->lines;
$invoice->lines = array(line(1, 3500, 3625));
$nativePdf = new InvoiceTestPDF();
$newPdf = new InvoiceTestPDF();
$nativeTotals->invokeArgs($baseline, array(&$nativePdf, $invoice, 0, 200, $langs, null));
$model->totals($newPdf, $invoice);
expect($nativePdf->cells === $newPdf->cells, 'No deposit: native summary unchanged');
$invoice->lines = $savedLines;

foreach (array('missing', 'company', 'line', 'sql') as $case) {
	$saved = clone $db->rows[7];
	if ($case === 'missing') { unset($db->rows[7]); }
	if ($case === 'company') { $db->rows[7]->fk_soc = 99; }
	if ($case === 'line') { $db->rows[7]->fk_facture_line = 99; }
	if ($case === 'sql') { $db->fail = true; }
	$refused = false;
	try { $model->getDepositSummary($invoice, $langs); } catch (RuntimeException $exception) { $refused = true; }
	expect($refused, 'Unsafe deduction refused: '.$case);
	$db->rows[7] = $saved;
	$db->fail = false;
}
unset($conf->facture->multidir_output[2]);
expect($model->write_file($invoice, $langs) === -1, 'Missing owner directory refused');
unset($conf->modules['jpsun']);
$conf->jpsun->enabled = 0;
expect(!JpsunInvoiceCompatibility::isAvailable(), 'Disabled JPSUN refuses generation');
expect(count($db->queries) > 0, 'Native discount reads executed');
echo "OK: native Sponge totals, ordering, immutable invoice, commercial discounts, multiple deposits, credit note, negative line, foreign currency, native entity filter and error paths\n";
