<?php
/** CLI rendering fixture, not a web entry point or a full-instance authorization test. */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
define('DOL_DOCUMENT_ROOT', realpath($argv[1]));
define('DOL_VERSION', $argv[3] ?? '25.0.0-alpha');
define('DOL_DATA_ROOT', $argv[2]);
define('DOL_URL_ROOT', '');
define('MAIN_DB_PREFIX', 'fixture_');
define('TCPDF_PATH', DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/');
define('TCPDI_PATH', DOL_DOCUMENT_ROOT.'/includes/tcpdi/');
$conf = (object) array('entity' => 1, 'currency' => 'EUR',
	'modules' => array('jpsun' => 1, 'project' => 1),
	'modules_parts' => array('hooks' => array()),
	'file' => (object) array('dol_document_root' => array(DOL_DOCUMENT_ROOT)),
	'global' => (object) array('MAIN_DISABLE_TCPDI' => 1),
	'project' => (object) array('multidir_output' => array(1 => DOL_DATA_ROOT, 2 => DOL_DATA_ROOT.'/owner')));
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once __DIR__.'/../core/modules/project/doc/pdf_jpsun_projectstock.modules.php';
$langs = new Translate(DOL_DOCUMENT_ROOT, $conf);
$langs->setDefaultLang('fr_FR');
$langs->loadLangs(array('main', 'projects', 'companies'));
foreach (file(__DIR__.'/../langs/fr_FR/jpsun.lang') as $line) {
	$parts = explode('=', trim($line), 2);
	if (count($parts) === 2) { $langs->tab_translate[$parts[0]] = $parts[1]; }
}
$db = new class { public function prefix() { return MAIN_DB_PREFIX; } };
$user = new class {
	public $socid = 0;
	public $id = 1;
	public $allowed = true;
	public $sharedAllowed = true;
	public function hasRight($module, $action, $detail = '') { return $this->allowed && ($module !== 'multicompany' || $this->sharedAllowed); }
};
class StockRenderFixture extends pdf_jpsun_projectstock
{
	public function render($langs, $client, $title, $ref, $file)
	{
		$pdf = pdf_getInstance(array(297, 210), 'mm', 'L');
		$this->drawPoster($pdf, $langs, $client, $title, $ref);
		if ($pdf->getNumPages() !== 1) { throw new RuntimeException('Poster must remain one page'); }
		$pdf->Output($file, 'F');
	}
}
dol_mkdir(DOL_DATA_ROOT.'/owner');
$model = new StockRenderFixture($db);
$model->render($langs, 'FOURCADE', 'Résidence Bigart', 'PRJ-2026-001', DOL_DATA_ROOT.'/reference.pdf');
$model->render($langs, 'Société de construction et de rénovation des résidences du littoral et des vallées',
	'Réhabilitation photovoltaïque de la résidence intergénérationnelle de Saint-André-de-Cubzac',
	'PROJET-2026-REFERENCE-TRES-LONGUE-0123456789', DOL_DATA_ROOT.'/long.pdf');
$model->render($langs, '', '', 'PRJ-VIDE', DOL_DATA_ROOT.'/empty.pdf');
$object = new Project($db);
$object->initAsSpecimen();
$object->entity = 2;
if ($model->write_file($object, $langs) !== 1 || !is_file(DOL_DATA_ROOT.'/owner/SPECIMEN-stock.pdf')) {
	throw new RuntimeException('Native owner directory not used: '.$model->error);
}
unset($conf->project->multidir_output[2]);
if ($model->write_file($object, $langs) !== 0) { throw new RuntimeException('Missing owner configuration must fail'); }
$user->allowed = false;
if ($model->write_file($object, $langs) !== 0) { throw new RuntimeException('Missing rights must fail'); }
$user->allowed = true;
$user->sharedAllowed = false;
$conf->modules['multicompany'] = 1;
if ($model->write_file($object, $langs) !== 0) { throw new RuntimeException('Missing shared-write right must fail'); }
print "OK: three single-page posters, native specimen owner output, absent owner configuration and functional rights refusal\n";
