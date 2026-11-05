<?php
/** CLI: php -d extension=pdo_sqlite test/proposal_cover_owner.php */
if (PHP_SAPI !== 'cli') { exit(1); }
define('MAIN_DB_PREFIX', 'fixture_');
require_once __DIR__.'/../class/jpsunproposalcover.class.php';
$conf = (object) array('entity' => 1);
$db = new class {
	public $pdo;
	public $fail = false;
	public function __construct() { $this->pdo = new PDO('sqlite::memory:'); }
	public function query($sql) { return $this->fail ? false : $this->pdo->query($sql); }
	public function fetch_object($result) { return $result->fetchObject(); }
	public function free($result) { $result->closeCursor(); }
	public function sanitize($value) { return $value; }
};
$db->pdo->exec('CREATE TABLE fixture_extrafields(name TEXT, elementtype TEXT, entity INTEGER)');
$db->pdo->exec('CREATE TABLE fixture_propal(rowid INTEGER, entity INTEGER)');
$db->pdo->exec('CREATE TABLE fixture_propal_extrafields(fk_object INTEGER, jpsun_cover_image TEXT, jpsun_cover_caption TEXT, powerplantpv_peak_power REAL, powerplantpv_storage_capacity REAL)');
$db->pdo->exec("INSERT INTO fixture_extrafields VALUES ('jpsun_cover_image','propal',2), ('jpsun_cover_caption','propal',2), ('powerplantpv_peak_power','propal',2), ('powerplantpv_storage_capacity','propal',2), ('powerplantpv_consumption_type','propal',1)");
$db->pdo->exec('INSERT INTO fixture_propal VALUES (42,2), (43,1)');
$db->pdo->exec("INSERT INTO fixture_propal_extrafields VALUES (42,'owner.png','Crédit propriétaire',2.25,0), (43,'viewer.png','Other entity',99,99)");
$object = (object) array('id' => 42, 'entity' => 2, 'array_options' => array('options_powerplantpv_consumption_type' => 'Viewer default', 'options_unrelated' => 'Keep'));
$cover = new JpsunProposalCover($db);
function checkOwner($condition, $label) {
	if (!$condition) { throw new RuntimeException($label); }
	print 'OK '.$label."\n";
}
checkOwner($cover->loadOwnerValues($object), 'shared values load without viewer definitions');
checkOwner($object->array_options['options_jpsun_cover_image'] === 'owner.png', 'owner image retained');
checkOwner($object->array_options['options_jpsun_cover_caption'] === 'Crédit propriétaire', 'owner caption retained');
checkOwner($object->array_options['options_powerplantpv_peak_power'] == 2.25, 'stored peak power read without recalculation');
checkOwner($object->array_options['options_powerplantpv_storage_capacity'] == 0, 'explicit stored zero retained');
checkOwner(!isset($object->array_options['options_powerplantpv_consumption_type']), 'viewer-only field omitted without nonexistent-column SQL');
checkOwner($object->array_options['options_unrelated'] === 'Keep', 'unrelated native options unchanged');
checkOwner($conf->entity === 1, 'authenticated entity unchanged');
$object->id = 43;
checkOwner(!$cover->loadOwnerValues($object), 'wrong owner and identifier refused');
$object->id = 42;
$db->fail = true;
checkOwner(!$cover->loadOwnerValues($object), 'SQL failure is not an empty illustration');
checkOwner($cover->error === 'JpsunCoverReadError', 'read error reported');
$db->fail = false;
checkOwner($db->pdo->query('SELECT jpsun_cover_caption FROM fixture_propal_extrafields WHERE fk_object = 42')->fetchColumn() === 'Crédit propriétaire', 'render read does not mutate stored metadata');
