<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later */
$res = @include __DIR__.'/../main.inc.php';
if (!$res) {
	$res = @include __DIR__.'/../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}
global $db, $conf, $langs, $user;
/** @var DoliDB $db */
/** @var Conf $conf */
/** @var Translate $langs */
/** @var User $user */
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once __DIR__.'/class/jpsunproposalcover.class.php';
$langs->loadLangs(array('jpsun@jpsun', 'propal', 'companies'));
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
if (!isModEnabled('jpsun') || !isModEnabled('propal') || !$user->hasRight('propal', 'lire') || !empty($user->socid)) {
	accessforbidden();
}
$object = new Propal($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}
restrictedArea($user, 'propal', $id, 'propal', '', 'fk_soc', 'rowid', 0);
if ($object->fetch_optionals() < 0) {
	dol_print_error($db);
	exit;
}
$cover = new JpsunProposalCover($db);
if (!$cover->isEnabled((int) $conf->entity)) {
	accessforbidden();
}
if ($action === 'preview') {
	$file = $cover->image($object);
	if (!$file) {
		accessforbidden();
	}
	header('Content-Type: image/png');
	header('X-Content-Type-Options: nosniff');
	header('Cache-Control: private, no-store');
	@readfile($file);
	exit;
}
if (!$user->hasRight('propal', 'creer')) {
	accessforbidden();
}
$caption = (string) ($object->array_options['options_jpsun_cover_caption'] ?? '');
$revision = hash('sha256', ($object->array_options['options_jpsun_cover_image'] ?? '').'\0'.$caption);
if ($action === 'savecover' || $action === 'removecover') {
	// main.inc.php enforces the native CSRF token; never bypass it.
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		accessforbidden();
	}
	$caption = GETPOST('caption', 'alphanohtml');
	$upload = null;
	if (isset($_FILES['illustration']) && is_array($_FILES['illustration']) && isset($_FILES['illustration']['error'], $_FILES['illustration']['tmp_name'], $_FILES['illustration']['size']) && !is_array($_FILES['illustration']['error'])) {
		$upload = array('error' => (int) $_FILES['illustration']['error'], 'tmp_name' => (string) $_FILES['illustration']['tmp_name'], 'size' => (int) $_FILES['illustration']['size']);
	}
	if ($cover->save($object, $user, $upload, $caption, GETPOST('revision', 'aZ09'), $action === 'removecover')) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.DOL_URL_ROOT.'/comm/propal/card.php?id='.$id);
		exit;
	}
	setEventMessages($langs->trans($cover->error), null, 'errors');
}
llxHeader('', $langs->trans('JpsunCoverIllustration'));
print load_fiche_titre($langs->trans('JpsunCoverIllustration'), '<a href="'.DOL_URL_ROOT.'/comm/propal/card.php?id='.$id.'">'.$langs->trans('Back').'</a>');
print '<div id="jpsun-cover-editor">';
if (!JpsunProposalCover::canUpload()) {
	print '<div class="warning">'.$langs->trans('JpsunCoverImageLibraries').'</div>';
}
print '<form method="POST" enctype="multipart/form-data" action="'.dol_buildpath('/jpsun/proposal_cover.php', 1).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="id" value="'.$id.'">';
print '<input type="hidden" name="revision" value="'.dol_escape_htmltag($revision).'">';
$file = $cover->image($object);
if ($file) {
	print '<img class="maxwidth500 centpercent" alt="'.dol_escape_htmltag($langs->trans('Preview')).'" src="'.dol_buildpath('/jpsun/proposal_cover.php', 1).'?id='.$id.'&action=preview">';
}
print '<div class="div-table-responsive-no-min"><table class="border centpercent">';
print '<tr><td><label for="jpsun-illustration">'.$langs->trans('JpsunCoverIllustration').'</label></td><td><input id="jpsun-illustration" type="file" name="illustration" accept="image/jpeg,image/png"'.(!JpsunProposalCover::canUpload() ? ' disabled' : '').'><br>'.$langs->trans('JpsunCoverImageHelp').'</td></tr>';
print '<tr><td><label for="jpsun-caption">'.$langs->trans('JpsunCoverCaption').'</label></td><td><input class="minwidth300" id="jpsun-caption" name="caption" maxlength="255" value="'.dol_escape_htmltag($caption).'"></td></tr></table></div>';
print '<div class="center"><button class="button" name="action" value="savecover">'.$langs->trans('Save').'</button>';
if (!empty($object->array_options['options_jpsun_cover_image'])) {
	print ' <button class="button" name="action" value="removecover">'.$langs->trans('JpsunCoverRemove').'</button>';
}
print '</div></form></div>';
llxFooter();
$db->close();
