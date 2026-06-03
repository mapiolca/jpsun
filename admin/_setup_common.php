<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * Shared bootstrap for JPSUN admin setup pages.
 */

$res = @include("../../main.inc.php");
if (!$res) {
	$res = @include("../../../main.inc.php");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once '../lib/jpsun.lib.php';

$langs->load("jpsun@jpsun");

if (!$user->admin) {
	accessforbidden();
}

if (empty($form) || !is_object($form)) {
	$form = new Form($db);
}
