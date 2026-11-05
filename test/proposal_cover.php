<?php
/** CLI: php -d extension=gd -d extension=exif test/proposal_cover.php /path/to/htdocs /temporary/output */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
define('DOL_DOCUMENT_ROOT', realpath($argv[1]));
define('DOL_VERSION', $argv[3] ?? '25.0.0-alpha');
define('MAIN_DB_PREFIX', 'fixture_');
define('DOL_URL_ROOT', '');
define('DOL_DATA_ROOT', $argv[2]);
define('TCPDF_PATH', DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/');
define('TCPDI_PATH', DOL_DOCUMENT_ROOT.'/includes/tcpdi/');
$conf = (object) array('entity' => 1, 'currency' => 'EUR', 'modules' => array('jpsun' => 1, 'propal' => 1),
	'modules_parts' => array('hooks' => array()),
	'global' => (object) array('MAIN_PDF_FORMAT' => 'A4', 'MAIN_MAX_DECIMALS_SHOWN' => 2, 'MAIN_MAX_DECIMALS_UNIT' => 5, 'MAIN_MAX_DECIMALS_TOT' => 2, 'MAIN_DISABLE_TCPDI' => 1),
	'file' => (object) array('dol_document_root' => array(DOL_DOCUMENT_ROOT)),
	'propal' => (object) array('multidir_output' => array(1 => DOL_DATA_ROOT, 2 => DOL_DATA_ROOT.'/owner')),
	'mycompany' => (object) array('multidir_output' => array(1 => DOL_DATA_ROOT, 2 => DOL_DATA_ROOT.'/owner')));
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once __DIR__.'/../core/modules/propale/doc/pdf_jpsunwithoutpicturescover.modules.php';
$langs = new Translate(DOL_DOCUMENT_ROOT, $conf);
$langs->setDefaultLang('fr_FR');
$langs->loadLangs(array('main', 'propal'));
foreach (file(__DIR__.'/../langs/fr_FR/jpsun.lang') as $line) {
	$parts = explode('=', trim($line), 2);
	if (count($parts) === 2) {
		$langs->tab_translate[$parts[0]] = $parts[1];
	}
}
$mysoc = (object) array('country_code' => 'FR');
$user = (object) array('id' => 1);
function expect($condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
	print 'OK '.$message."\n";
}
$db = new class {
	public $failCommit = false;
	public $rolledBack = false;
	public $enabled = true;
	public function order($field, $direction = '') { return ''; }
	public function plimit($limit, $offset = 0) { return ''; }
	public function prefix() { return MAIN_DB_PREFIX; }
	public function decrypt($value) { return $value; }
	public function sanitize($value) { return $value; }
	public function jdate($date) { return 0; }
	public function escape($value) { return addslashes($value); }
	public function fetch_object($res) { return false; }
	public function query($sql) { return (object) array('sql' => $sql); }
	public function num_rows($res) { return $this->enabled && (strpos($res->sql, 'document_model') !== false || strpos($res->sql, 'FOR UPDATE') !== false) ? 1 : 0; }
	public function free($res) {}
	public function begin() { return 1; }
	public function commit() { return $this->failCommit ? 0 : 1; }
	public function rollback() { $this->rolledBack = true; return 1; }
};
@mkdir(DOL_DATA_ROOT.'/owner', 0777, true);
$cover = new JpsunProposalCover($db);
expect($cover->isEnabled(1), 'native model enabled');
$db->enabled = false;
expect(!$cover->isEnabled(1), 'disabled model hidden');
$db->enabled = true;
$object = new class {
	public $id = 42;
	public $entity = 2;
	public $element = 'propal';
	public $ref = 'OFFRE-42';
	public $array_options = array();
	public $failField = false;
	public function fetch_optionals() { return 1; }
	public function updateExtraField($key, $trigger, $actor) { return $this->failField ? -1 : 1; }
};
$dir = $cover->directory($object);
expect(str_replace('\\', '/', $dir) === str_replace('\\', '/', DOL_DATA_ROOT).'/owner/OFFRE-42/jpsun-cover', 'owner directory resolved once, no duplicate reference');
unset($conf->propal->multidir_output[2]);
expect($cover->directory($object) === false, 'missing owner directory refuses fallback to viewing entity');
$conf->propal->multidir_output[2] = DOL_DATA_ROOT.'/owner';
expect($cover->image($object) === '', 'no illustration is valid');
$object->array_options['options_jpsun_cover_image'] = '../../secret.png';
expect($cover->image($object) === false, 'path traversal refused');
$object->array_options = array();
$actor = new class { public function hasRight(...$args) { return true; } };
$revision = hash('sha256', '\0');
expect(!$cover->save($object, $actor, null, 'credit', 'stale', false), 'concurrent edit refused');
expect($db->rolledBack, 'concurrent edit rolls back');
$cover->error = '';
expect($cover->save($object, $actor, null, 'credit', $revision, false), 'caption saved without replacing other fields');
$db->failCommit = true;
expect(!$cover->save($object, $actor, null, 'other', hash('sha256', '\0credit'), false), 'commit failure is not success');
$db->failCommit = false;
expect(JpsunProposalCover::placement(100, 200)['x'] > 15, 'portrait leaves side bands');
expect(JpsunProposalCover::placement(500, 100)['x'] < 15, 'wide image clips sides at fixed height');

if (JpsunProposalCover::canUpload()) {
	$img = imagecreatetruecolor(900, 600);
	imagefill($img, 0, 0, imagecolorallocate($img, 80, 140, 180));
	imagefilledrectangle($img, 120, 180, 780, 600, imagecolorallocate($img, 25, 45, 70));
	imagepng($img, DOL_DATA_ROOT.'/source.png');
	imagedestroy($img);
	expect($cover->cleanImage(DOL_DATA_ROOT.'/source.png', DOL_DATA_ROOT.'/clean.png'), 'PNG decoded and re-encoded');
	file_put_contents(DOL_DATA_ROOT.'/fake.jpg', '<?php echo 1;');
	expect(!$cover->cleanImage(DOL_DATA_ROOT.'/fake.jpg', DOL_DATA_ROOT.'/invalid.png'), 'forged image rejected');
	// Real JPEGs with orientation and deliberately identifying EXIF data.
	$img = imagecreatetruecolor(80, 40);
	$rgb = array(array(240, 10, 10), array(10, 240, 10), array(10, 10, 240), array(240, 240, 10));
	foreach ($rgb as $i => $c) {
		imagefilledrectangle($img, ($i % 2) * 40, (int) ($i / 2) * 20, ($i % 2) * 40 + 39, (int) ($i / 2) * 20 + 19, imagecolorallocate($img, ...$c));
	}
	imagejpeg($img, DOL_DATA_ROOT.'/corners.jpg', 100);
	imagedestroy($img);
	$jpeg = file_get_contents(DOL_DATA_ROOT.'/corners.jpg');
	$expected = array(1 => array(0, 1, 2, 3), 2 => array(1, 0, 3, 2), 3 => array(3, 2, 1, 0), 4 => array(2, 3, 0, 1), 5 => array(0, 2, 1, 3), 6 => array(2, 0, 3, 1), 7 => array(3, 1, 2, 0), 8 => array(1, 3, 0, 2));
	foreach ($expected as $orientation => $corners) {
		$private = "COVER-PRIVATE-GPS-CAMERA\0";
		$tiff = "II\x2a\x00".pack('Vv', 8, 2).pack('vvVv', 0x0112, 3, 1, $orientation)."\0\0".pack('vvVV', 0x010e, 2, strlen($private), 38).pack('V', 0).$private;
		$exif = "Exif\0\0".$tiff;
		file_put_contents(DOL_DATA_ROOT.'/oriented.jpg', substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2));
		expect($cover->cleanImage(DOL_DATA_ROOT.'/oriented.jpg', DOL_DATA_ROOT.'/oriented.png'), 'EXIF '.$orientation.' re-encoded');
		expect(strpos(file_get_contents(DOL_DATA_ROOT.'/oriented.png'), 'COVER-PRIVATE') === false, 'EXIF '.$orientation.' metadata removed');
		$out = imagecreatefrompng(DOL_DATA_ROOT.'/oriented.png');
		$actual = array();
		foreach (array(array(2, 2), array(imagesx($out) - 3, 2), array(2, imagesy($out) - 3), array(imagesx($out) - 3, imagesy($out) - 3)) as $xy) {
			$c = imagecolorsforindex($out, imagecolorat($out, $xy[0], $xy[1]));
			$dist = array();
			foreach ($rgb as $i => $reference) {
				$dist[$i] = abs($reference[0] - $c['red']) + abs($reference[1] - $c['green']) + abs($reference[2] - $c['blue']);
			}
			$actual[] = array_search(min($dist), $dist, true);
		}
		imagedestroy($out);
		expect($actual === $corners, 'EXIF '.$orientation.' visual orientation preserved');
	}
}

class CoverFixture extends pdf_jpsunwithoutpicturescover
{
	public function render($pdf, $object, $lang) { $this->printCover($pdf, $object, $lang); }
}
$model = new CoverFixture($db);
$config = array_merge(JpsunProposalCover::COLORS, array('MAIN_INFO_SOCIETE_NOM' => 'JPSUN', 'MAIN_INFO_SOCIETE_ADDRESS' => '4 rue Alfred Kastler', 'MAIN_INFO_SOCIETE_ZIP' => '33380', 'MAIN_INFO_SOCIETE_TOWN' => 'Mios', 'MAIN_INFO_SOCIETE_WEB' => 'jpsun.fr', 'MAIN_INFO_SOCIETE_LOGO' => ''));
foreach (array('coverConfig' => $config, 'coverImage' => is_file(DOL_DATA_ROOT.'/clean.png') ? DOL_DATA_ROOT.'/clean.png' : '', 'moduleCount' => 52.0) as $key => $value) {
	$property = new ReflectionProperty(pdf_jpsunwithoutpicturescover::class, $key);
	$property->setAccessible(true);
	$property->setValue($model, $value);
}
$proposal = new class {
	public const STATUS_DRAFT = 0;
	public $entity = 2;
	public $ref_client = "Gironde Habitat\nLa Réole";
	public $ref = '2026CB146';
	public $date = 1793664000;
	public $status = 0;
	public $user_author_id = 0;
	public $socid = 1;
	public $array_options = array('options_powerplantpv_peak_power' => 24.44, 'options_powerplantpv_storage_capacity' => 0,
		'options_powerplantpv_consumption_type' => 'Autoconsommation totale par logement', 'options_jpsun_cover_caption' => 'Illustration synthétique pour validation de mise en page');
	public $thirdparty;
	public function liste_contact(...$args) { return array(); }
};
$proposal->thirdparty = (object) array('name' => 'INTEGRAL BOIS SYSTEM (IBS)', 'address' => 'ZA La Lande, route de Tabanac', 'zip' => '33880', 'town' => 'Baurech');
$pdf = pdf_getInstance(array(210, 297));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0);
$model->render($pdf, $proposal, $langs);
expect($pdf->getNumPages() === 1, 'cover stays on exactly one A4 page');
$pdf->Output(DOL_DATA_ROOT.'/cover.pdf', 'F');
print 'PDF '.DOL_DATA_ROOT."/cover.pdf\n";

// Full inherited rendering on native specimen objects (no business database).
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
class CoverSpecimen extends Propal
{
    public function fetch_thirdparty($force_thirdparty_id = 0)
    {
        $this->thirdparty = new Societe($this->db);
        $this->thirdparty->initAsSpecimen();
        return 1;
    }
    public function liste_contact($statusoflink = -1, $source = 'external', $list = 0, $code = '', $status = -1, $arrayoftcids = array()) { return array(); }
}
$hookmanager = new HookManager($db);
$user = new User($db);
$user->id = 1;
$user->firstname = 'Auteur';
$user->lastname = 'Recette';
$user->rights = (object) array('propal' => (object) array('lire' => 1, 'creer' => 1));
$mysoc = new Societe($db);
$mysoc->initAsSpecimen();
$conf->propal->dir_output = DOL_DATA_ROOT;
$conf->mycompany->dir_output = DOL_DATA_ROOT;
$conf->multicurrency = (object) array('enabled' => 0);
$conf->global->PROPAL_DISABLE_SIGNATURE = 1;
$conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT = 1;
$conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS = 0;
$doc = new CoverSpecimen($db);
$doc->entity = 1;
$doc->specimen = 1;
$doc->ref = 'SPECIMEN';
$doc->ref_client = 'Projet de recette';
$doc->socid = 1;
$doc->date = dol_now();
$doc->fin_validite = $doc->date + 86400 * 30;
$doc->multicurrency_tx = 1;
$doc->multicurrency_code = 'EUR';
$doc->lines = array();
$doc->total_ht = $doc->total_tva = $doc->total_ttc = 0;
$doc->status = 0;
$doc->fetch_thirdparty();
for ($i = 0; $i < 60; $i++) {
    $line = new PropaleLigne($db);
    $line->desc = 'Équipement photovoltaïque de recette '.$i.' : description suffisamment longue pour vérifier le passage de page.';
    $line->qty = 1;
    $line->subprice = $line->total_ht = $line->total_ttc = 100;
    $line->total_tva = $line->tva_tx = $line->remise_percent = 0;
    $line->localtax1_tx = $line->localtax2_tx = $line->total_localtax1 = $line->total_localtax2 = 0;
    $line->localtax1_type = $line->localtax2_type = '0';
    $line->product_type = 0;
    $line->special_code = 0;
    $line->info_bits = 0;
    $doc->lines[] = $line;
    $doc->total_ht += 100;
    $doc->total_ttc += 100;
}
$plain = new pdf_jpsunwithoutpictures($db);
expect($plain->write_file($doc, $langs) === 1, 'standard proposal still generates');
rename(DOL_DATA_ROOT.'/SPECIMEN.pdf', DOL_DATA_ROOT.'/standard.pdf');
$with = new pdf_jpsunwithoutpicturescover($db);
expect($with->write_file($doc, $langs) === 1, 'cover and inherited commercial pages generate together');
rename(DOL_DATA_ROOT.'/SPECIMEN.pdf', DOL_DATA_ROOT.'/complete.pdf');
