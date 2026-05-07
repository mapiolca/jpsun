<?php
/* Copyright (C) 2026 JPSUN */

/**
 * Product Select2 search for FR37 reports.
 */

if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

require '../../../main.inc.php';

dol_include_once('/jpsun/class/jpsunfichinterfr37.class.php');

header('Content-Type: application/json; charset=UTF-8');

if (!isModEnabled('jpsun') || !getDolGlobalInt('JPSUN_FICHINTER_FR37_ENABLE') || !$user->hasRight('jpsun', 'fr37', 'read')) {
	http_response_code(403);
	echo json_encode(array('results' => array()));
	exit;
}

$category = GETPOST('category', 'alpha');
$search = GETPOST('q', 'restricthtml');
if (!in_array($category, array('PV_PANEL', 'INVERTER'), true)) {
	echo json_encode(array('results' => array()));
	exit;
}

$report = new JpsunFichinterFr37($db);
$rows = $report->searchProductsByTechnicalCategory($category, $search, 30);
if (GETPOSTINT('text_value')) {
	foreach ($rows as $idx => $row) {
		$rows[$idx]['id'] = $row['text'];
	}
}

echo json_encode(array('results' => $rows));
