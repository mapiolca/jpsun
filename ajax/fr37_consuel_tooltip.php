<?php
/* Copyright (C) 2026 JPSUN */

/**
 * Consuel case preview for FR37 reports.
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

if (!isModEnabled('jpsun') || !getDolGlobalInt('JPSUN_FICHINTER_FR37_ENABLE') || !$user->hasRight('jpsun', 'fr37', 'read')) {
	http_response_code(403);
	exit;
}

$code = GETPOST('code', 'alpha');
$report = new JpsunFichinterFr37($db);
$case = $report->getConsuelCase($code);
if (!$case) {
	exit;
}

$imageUrl = '';
if (!empty($case->illustration)) {
	$imageUrl = dol_buildpath('/jpsun/img/consuel/'.basename($case->illustration), 1);
}

print '<div class="opacitymedium">';
print '<strong>'.dol_escape_htmltag($case->label).'</strong>';
if (!empty($case->description)) {
	print '<br>'.dol_nl2br(dol_escape_htmltag($case->description));
}
if ($imageUrl) {
	print '<br><img src="'.$imageUrl.'" alt="'.dol_escape_htmltag($case->label).'">';
}
print '</div>';
