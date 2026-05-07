<?php
/* Copyright (C) 2026 JPSUN
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * FR37 report tab on intervention cards.
 */

require '../../../main.inc.php';

require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/fichinter.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/jpsun/class/jpsunfichinterfr37.class.php');

$langs->loadLangs(array('interventions', 'companies', 'projects', 'products', 'other', 'jpsun@jpsun'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$bucket = GETPOST('bucket', 'aZ09');

if (!isModEnabled('jpsun') || (!isModEnabled('ficheinter') && !isModEnabled('intervention')) || !getDolGlobalInt('JPSUN_FICHINTER_FR37_ENABLE')) {
	accessforbidden();
}

$object = new Fichinter($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	dol_print_error($db, 'Intervention not found');
	exit;
}
$object->fetch_thirdparty();

restrictedArea($user, 'ficheinter', $object->id, 'fichinter');
if (!$user->hasRight('jpsun', 'fr37', 'read')) {
	accessforbidden();
}
$canEdit = ($user->hasRight('jpsun', 'fr37', 'write') && $user->hasRight('ficheinter', 'creer'));

$form = new Form($db);
$formfile = new FormFile($db);
$report = new JpsunFichinterFr37($db);
$report->fetchByFichinter($object->id);

/**
 * @param string $name GETPOST key
 * @return array<int,string>
 */
function jpsun_fr37_getpost_array($name)
{
	$value = GETPOST($name, 'array');
	if (!is_array($value)) {
		return array();
	}

	$clean = array();
	foreach ($value as $entry) {
		$entry = trim((string) $entry);
		if ($entry !== '') {
			$clean[] = $entry;
		}
	}

	return $clean;
}

/**
 * @param string       $name    Input name
 * @param array        $entries Checkbox values
 * @param array|string $checked Current checked values
 * @param int          $columns Number of native tagtable columns
 * @return string
 */
function jpsun_fr37_checkbox_list($name, $entries, $checked, $columns = 2)
{
	$checked = (array) $checked;
	$columns = max(1, (int) $columns);
	$out = '<div class="tagtable noborder centpercent">';

	foreach (array_chunk((array) $entries, $columns) as $row) {
		$out .= '<div class="tagtr">';
		foreach ($row as $entry) {
			$out .= '<div class="tagtd maxwidthonsmartphone">';
			$out .= '<label><input type="checkbox" name="'.$name.'[]" value="'.dol_escape_htmltag($entry).'"'.(in_array($entry, $checked, true) ? ' checked' : '').'> '.dol_escape_htmltag($entry).'</label>';
			$out .= '</div>';
		}
		for ($i = count($row); $i < $columns; $i++) {
			$out .= '<div class="tagtd"></div>';
		}
		$out .= '</div>';
	}
	$out .= '</div>';

	return $out;
}

/**
 * @param string              $name    Field name
 * @param string              $value   Current value
 * @param array<string,string> $options Value => translation key
 * @param string              $attrs   Extra attributes
 * @return string
 */
function jpsun_fr37_select($name, $value, $options, $attrs = '')
{
	global $langs;

	$out = '<select class="flat minwidth200 maxwidthonsmartphone" name="'.$name.'" '.$attrs.'>';
	$out .= '<option value="">&nbsp;</option>';
	foreach ($options as $optionValue => $labelKey) {
		$out .= '<option value="'.dol_escape_htmltag($optionValue).'"'.($value === $optionValue ? ' selected' : '').'>'.$langs->trans($labelKey).'</option>';
	}
	$out .= '</select>';

	return $out;
}

/**
 * @param JpsunFichinterFr37 $report   Report
 * @param string             $role     Product role
 * @param string             $category Technical category
 * @param string             $name     Field name
 * @return string
 */
function jpsun_fr37_product_select($report, $role, $category, $name)
{
	$out = '<select class="flat minwidth300 maxwidthonsmartphone jpsun-fr37-product-select" multiple name="'.$name.'[]" data-category="'.dol_escape_htmltag($category).'">';
	foreach ($report->getProductLabels($role) as $product) {
		$out .= '<option value="'.((int) $product['id']).'" selected>'.dol_escape_htmltag($product['label']).'</option>';
	}
	$out .= '</select>';

	return $out;
}

/**
 * @param string $value Raw value
 * @return string
 */
function jpsun_fr37_textarea($name, $value, $rows = 3, $attrs = '')
{
	return '<textarea class="flat centpercent maxwidthonsmartphone" name="'.$name.'" rows="'.((int) $rows).'" '.$attrs.'>'.dol_escape_htmltag((string) $value).'</textarea>';
}

/**
 * @param Fichinter $object Intervention object
 * @return array<int,array{type:string,user_html:string}>
 */
function jpsun_fr37_fetch_contacts($object)
{
	global $db;

	$rows = array();
	if (!method_exists($object, 'liste_contact')) {
		return $rows;
	}

	$userstatic = new User($db);
	$contactstatic = new Contact($db);

	foreach (array('internal', 'external') as $source) {
		$contactlist = $object->liste_contact(-1, $source);
		if (!is_array($contactlist)) {
			continue;
		}

		foreach ($contactlist as $contact) {
			$contactHtml = '';
			if ($source === 'internal') {
				if ($userstatic->fetch((int) $contact['id']) > 0) {
					$contactHtml = $userstatic->getNomUrl(-1, '', 0, 0, 0, 0, '', 'valignmiddle');
				}
			} else {
				if ($contactstatic->fetch((int) $contact['id']) > 0) {
					$contactHtml = $contactstatic->getNomUrl(1, '', 0, '', 0, 0);
				}
			}

			if ($contactHtml !== '') {
				$rows[] = array(
					'type' => !empty($contact['libelle']) ? $contact['libelle'] : (!empty($contact['code']) ? $contact['code'] : ''),
					'user_html' => $contactHtml,
				);
			}
		}
	}

	return $rows;
}

/**
 * @return string
 */
function jpsun_fr37_string_remove_link()
{
	global $langs;

	return '<a class="reposition jpsun-fr37-remove-string" href="" title="'.dol_escape_htmltag($langs->trans('Delete')).'">'.img_delete().'</a>';
}

/**
 * @param Fichinter $object Intervention object
 * @return array<int,string>
 */
function jpsun_fr37_fetch_linked_objects($object)
{
	$rows = array();
	if (!method_exists($object, 'fetchObjectLinked')) {
		return $rows;
	}

	$element = !empty($object->element) ? $object->element : 'fichinter';
	$object->fetchObjectLinked($object->id, $element);
	if (empty($object->linkedObjects) || !is_array($object->linkedObjects)) {
		return $rows;
	}

	foreach ($object->linkedObjects as $type => $objects) {
		foreach ((array) $objects as $linkedObject) {
			if (method_exists($linkedObject, 'getNomUrl')) {
				$rows[] = $linkedObject->getNomUrl(1);
			} elseif (!empty($linkedObject->ref)) {
				$rows[] = dol_escape_htmltag($type.' '.$linkedObject->ref);
			}
		}
	}

	return $rows;
}

/**
 * @param Fichinter $object Intervention object
 * @param string    $bucket before|after
 * @return void
 */
function jpsun_fr37_print_photos($object, $bucket)
{
	global $langs, $canEdit;

	$photos = JpsunFichinterFr37::getPhotos($object, $bucket);
	if (empty($photos)) {
		print '<span class="opacitymedium">'.$langs->trans('JpsunFr37NoFile').'</span>';
		return;
	}

	$docBucket = ($bucket === 'after' ? 'fr37_after' : 'fr37_before');
	$objectref = dol_sanitizeFileName($object->ref);
	print '<div class="jpsun-fr37-photo-grid">';
	foreach ($photos as $photo) {
		$filename = $photo['name'];
		$url = DOL_URL_ROOT.'/document.php?modulepart=fichinter&file='.urlencode($objectref.'/'.$docBucket.'/'.$filename);
		print '<div class="jpsun-fr37-photo">';
		print '<a href="'.$url.'" target="_blank" rel="noopener">';
		print '<img src="'.$url.'" alt="'.dol_escape_htmltag($filename).'">';
		print '</a>';
		print '<div class="small wordbreak">'.dol_escape_htmltag($filename).'</div>';
		if ($canEdit) {
			$deleteUrl = $_SERVER['PHP_SELF'].'?id='.((int) $object->id).'&action=deletefile&bucket='.urlencode($bucket).'&file='.urlencode($filename).'&token='.newToken();
			print '<a class="reposition small" href="'.$deleteUrl.'">'.$langs->trans('Delete').'</a>';
		}
		print '</div>';
	}
	print '</div>';
}

if ($action === 'save' && $canEdit) {
	$values = array(
		'present_on_site' => GETPOST('present_on_site', 'alpha'),
		'intervention_object' => GETPOST('intervention_object', 'alpha'),
		'intervention_object_other' => GETPOST('intervention_object_other', 'restricthtml'),
		'panel_qty' => GETPOST('panel_qty', 'alpha'),
		'roof_type' => GETPOST('roof_type', 'alpha'),
		'roof_type_other' => GETPOST('roof_type_other', 'restricthtml'),
		'install_type' => GETPOST('install_type', 'alpha'),
		'install_type_other' => GETPOST('install_type_other', 'restricthtml'),
		'roof_access_json' => jpsun_fr37_getpost_array('roof_access'),
		'electrical_connection' => GETPOST('electrical_connection', 'restricthtml'),
		'risk_identified_json' => jpsun_fr37_getpost_array('risk_identified'),
		'risk_other' => GETPOST('risk_other', 'restricthtml'),
		'prevention_measures_json' => jpsun_fr37_getpost_array('prevention_measures'),
		'collective_protection_json' => jpsun_fr37_getpost_array('collective_protection'),
		'individual_protection_json' => jpsun_fr37_getpost_array('individual_protection'),
		'lift_planned' => GETPOST('lift_planned', 'alpha'),
		'ladder_planned' => GETPOST('ladder_planned', 'alpha'),
		'lifeline_planned' => GETPOST('lifeline_planned', 'alpha'),
		'epi_checked' => GETPOST('epi_checked', 'alpha'),
		'safety_rules_json' => jpsun_fr37_getpost_array('safety_rules'),
		'safety_instructions' => GETPOST('safety_instructions', 'restricthtml'),
		'inverter_location' => GETPOST('inverter_location', 'restricthtml'),
		'panel_layout' => GETPOST('panel_layout', 'restricthtml'),
		'works_done' => GETPOST('works_done', 'restricthtml'),
		'consuel_case' => GETPOST('consuel_case', 'alpha'),
		'check_dc_connectors' => GETPOST('check_dc_connectors', 'alpha'),
		'check_ac_box' => GETPOST('check_ac_box', 'alpha'),
		'check_cables_trunking' => GETPOST('check_cables_trunking', 'alpha'),
		'check_grounds' => GETPOST('check_grounds', 'alpha'),
		'check_labels' => GETPOST('check_labels', 'alpha'),
		'earth_value' => GETPOST('earth_value', 'alpha'),
		'inverter_type' => GETPOST('inverter_type', 'restricthtml'),
		'inverter_serial' => GETPOST('inverter_serial', 'restricthtml'),
		'inverter_power' => GETPOST('inverter_power', 'alpha'),
		'connection_type' => GETPOST('connection_type', 'alpha'),
		'wifi_reason' => GETPOST('wifi_reason', 'restricthtml'),
		'sim_info' => GETPOST('sim_info', 'restricthtml'),
		'observations_conclusion' => GETPOST('observations_conclusion', 'restricthtml'),
	);

	$products = array(
		JpsunFichinterFr37::PRODUCT_ROLE_PANEL => GETPOST('products_panel', 'array'),
		JpsunFichinterFr37::PRODUCT_ROLE_INVERTER_SOLD => GETPOST('products_inverter_sold', 'array'),
	);

	$stringNos = GETPOST('string_no', 'array');
	$voltages = GETPOST('string_voltage', 'array');
	$pvCounts = GETPOST('string_pv_count', 'array');
	$strings = array();
	$count = max(count((array) $stringNos), count((array) $voltages), count((array) $pvCounts));
	for ($i = 0; $i < $count; $i++) {
		$strings[] = array(
			'string_no' => isset($stringNos[$i]) ? $stringNos[$i] : '',
			'voltage' => isset($voltages[$i]) ? $voltages[$i] : '',
			'pv_count' => isset($pvCounts[$i]) ? $pvCounts[$i] : '',
		);
	}

	if ($report->save($user, $object->id, $values, $products, $strings) > 0) {
		setEventMessages($langs->trans('JpsunFr37Saved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	}
	setEventMessages($report->error, null, 'errors');
}

if (($action === 'uploadbefore' || $action === 'uploadafter') && $canEdit) {
	$uploadBucket = ($action === 'uploadafter' ? 'after' : 'before');
	$dir = JpsunFichinterFr37::getPhotoDir($object, $uploadBucket);
	dol_mkdir($dir);

	if (!empty($_FILES['userfile']['name'])) {
		$names = is_array($_FILES['userfile']['name']) ? $_FILES['userfile']['name'] : array($_FILES['userfile']['name']);
		$tmpNames = is_array($_FILES['userfile']['tmp_name']) ? $_FILES['userfile']['tmp_name'] : array($_FILES['userfile']['tmp_name']);
		$errors = is_array($_FILES['userfile']['error']) ? $_FILES['userfile']['error'] : array($_FILES['userfile']['error']);
		foreach ($names as $idx => $name) {
			if ($name === '') {
				continue;
			}
			$dest = $dir.'/'.dol_sanitizeFileName($name);
			$result = dol_move_uploaded_file($tmpNames[$idx], $dest, 1, 0, isset($errors[$idx]) ? $errors[$idx] : 0, 0);
			if ($result <= 0) {
				setEventMessages($langs->trans('ErrorFileNotUploaded'), null, 'errors');
			}
		}
		setEventMessages($langs->trans('JpsunFr37UploadOk'), null, 'mesgs');
	}

	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

if ($action === 'deletefile' && $canEdit) {
	$deleteBucket = ($bucket === 'after' ? 'after' : 'before');
	$filename = basename(GETPOST('file', 'restricthtml'));
	$dir = JpsunFichinterFr37::getPhotoDir($object, $deleteBucket);
	$file = $dir.'/'.$filename;
	if ($filename && dol_is_file($file)) {
		dol_delete_file($file);
		setEventMessages($langs->trans('JpsunFr37DeleteOk'), null, 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

$project = null;
$projectId = !empty($object->fk_project) ? $object->fk_project : (!empty($object->fk_projet) ? $object->fk_projet : 0);
if ($projectId > 0) {
	$project = new Project($db);
	if ($project->fetch($projectId) <= 0) {
		$project = null;
	}
}

$values = $report->values;
$inverterTypeValue = $values['inverter_type'];
if ($inverterTypeValue === '') {
	$inverterTypeValue = $report->getProductLabelsText(JpsunFichinterFr37::PRODUCT_ROLE_INVERTER_SOLD);
}
$consuelCases = $report->getConsuelCases();

$title = $langs->trans('JpsunFr37Report').' - '.$object->ref;
llxHeader('', $title);

$head = fichinter_prepare_head($object);
print dol_get_fiche_head($head, 'jpsun_fr37', $langs->trans('InterventionCard'), -1, 'intervention');

$linkback = '<a href="'.DOL_URL_ROOT.'/fichinter/list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
$morehtmlref = '<div class="refidno">';
if (!empty($object->thirdparty->id)) {
	$morehtmlref .= $object->thirdparty->getNomUrl(1);
}
if ($project) {
	$morehtmlref .= '<br>'.$project->getNomUrl(1);
}
$morehtmlref .= '</div>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

print '<style>
.jpsun-fr37 .select2-container{max-width:100%}
.jpsun-fr37 .jpsun-fr37-string-actions{white-space:nowrap}
.jpsun-fr37-photo-grid{margin-top:8px}
.jpsun-fr37-photo{display:inline-block;vertical-align:top;width:120px;max-width:45%;margin:0 8px 10px 0}
.jpsun-fr37-photo img{display:block;max-width:100%;height:auto;border:1px solid var(--border-color,#ddd);background:#fff}
.jpsun-fr37-consuel-slide{display:none}
.jpsun-fr37-consuel-slide img{max-width:100%;height:auto;border:1px solid var(--border-color,#ddd);background:#fff}
</style>';

print '<div class="fichecenter jpsun-fr37">';

print '<div class="underbanner clearboth"></div>';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.((int) $object->id).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<div class="fichehalfleft">';
print load_fiche_titre($langs->trans('JpsunFr37InterventionObject'), '', '');
print '<div class="div-table-responsive">';
print '<table class="border centpercent tableforfieldcreate">';
print '<tr><td class="titlefieldcreate">'.$langs->trans('JpsunFr37PresentOnSite').'</td><td><input type="checkbox" name="present_on_site" value="1"'.($values['present_on_site'] ? ' checked' : '').'></td></tr>';
print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('JpsunFr37InterventionObject').'</td><td>';
print jpsun_fr37_select('intervention_object', $values['intervention_object'], array(
	'VISIT' => 'JpsunFr37ObjectVisit',
	'INSTALLATION' => 'JpsunFr37ObjectInstallation',
	'MAINTENANCE' => 'JpsunFr37ObjectMaintenance',
	'TROUBLESHOOTING' => 'JpsunFr37ObjectTroubleshooting',
	'OTHER' => 'JpsunFr37ObjectOther',
), 'id="jpsun_fr37_intervention_object"');
print '<div id="jpsun_fr37_intervention_object_other" class="marginleftonly marginbottomonly">'.jpsun_fr37_textarea('intervention_object_other', $values['intervention_object_other'], 2).'</div>';
print '</td></tr>';
print '</table></div>';

print load_fiche_titre($langs->trans('JpsunFr37Installation'), '', '');
print '<div class="div-table-responsive">';
print '<table class="border centpercent tableforfieldcreate">';
print '<tr><td class="titlefieldcreate">'.$langs->trans('JpsunFr37PanelsRef').'</td><td>'.jpsun_fr37_product_select($report, JpsunFichinterFr37::PRODUCT_ROLE_PANEL, 'PV_PANEL', 'products_panel').'</td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37InvertersSold').'</td><td>'.jpsun_fr37_product_select($report, JpsunFichinterFr37::PRODUCT_ROLE_INVERTER_SOLD, 'INVERTER', 'products_inverter_sold').'</td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37PanelQty').'</td><td><input class="flat width75 maxwidthonsmartphone" type="number" min="0" name="panel_qty" value="'.dol_escape_htmltag((string) $values['panel_qty']).'"></td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37RoofType').'</td><td>';
print jpsun_fr37_select('roof_type', $values['roof_type'], array('TILE' => 'JpsunFr37RoofTile', 'SLATE' => 'JpsunFr37RoofSlate', 'STEEL' => 'JpsunFr37RoofSteel', 'FLAT' => 'JpsunFr37RoofFlat', 'OTHER' => 'JpsunFr37ObjectOther'), 'id="jpsun_fr37_roof_type"');
print '<div id="jpsun_fr37_roof_type_other" class="marginleftonly marginbottomonly">'.jpsun_fr37_textarea('roof_type_other', $values['roof_type_other'], 2).'</div>';
print '</td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37InstallType').'</td><td>';
print jpsun_fr37_select('install_type', $values['install_type'], array('ROOF' => 'JpsunFr37InstallRoof', 'INTEGRATED' => 'JpsunFr37InstallIntegrated', 'GROUND' => 'JpsunFr37InstallGround', 'OTHER' => 'JpsunFr37ObjectOther'), 'id="jpsun_fr37_install_type"');
print '<div id="jpsun_fr37_install_type_other" class="marginleftonly marginbottomonly">'.jpsun_fr37_textarea('install_type_other', $values['install_type_other'], 2).'</div>';
print '</td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37RoofAccess').'</td><td>'.jpsun_fr37_checkbox_list('roof_access', array('Echelle', 'Echafaudage', 'Nacelle', 'Acces interieur'), $report->getJsonList('roof_access_json')).'</td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37ElectricalConnection').'</td><td>'.jpsun_fr37_textarea('electrical_connection', $values['electrical_connection'], 2).'</td></tr>';
print '</table></div>';

print load_fiche_titre($langs->trans('JpsunFr37RiskAnalysis'), '', '');
print '<div class="div-table-responsive">';
print '<table class="border centpercent tableforfieldcreate">';
print '<tr><td class="titlefieldcreate">'.$langs->trans('JpsunFr37Risks').'</td><td>'.jpsun_fr37_checkbox_list('risk_identified', array('Chute de hauteur', 'Risque electrique', 'Manutention', 'Meteo', 'Coactivite', 'Autre'), $report->getJsonList('risk_identified_json')).'</td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37RiskOther').'</td><td>'.jpsun_fr37_textarea('risk_other', $values['risk_other'], 2).'</td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37PreventionMeasures').'</td><td>'.jpsun_fr37_checkbox_list('prevention_measures', array('Balisage', 'Consignation', 'Verification support', 'Meteo favorable', 'Habilitation'), $report->getJsonList('prevention_measures_json')).'</td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37CollectiveProtection').'</td><td>'.jpsun_fr37_checkbox_list('collective_protection', array('Garde-corps', 'Filets', 'Nacelle', 'Echafaudage'), $report->getJsonList('collective_protection_json')).'</td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37IndividualProtection').'</td><td>'.jpsun_fr37_checkbox_list('individual_protection', array('Harnais', 'Casque', 'Gants', 'Chaussures securite', 'Lunettes'), $report->getJsonList('individual_protection_json')).'</td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37SafetyRules').'</td><td>'.jpsun_fr37_checkbox_list('safety_rules', array('Consignation', 'Travail hors tension', 'Zone balisee', 'Absence pluie', 'Travail en binome'), $report->getJsonList('safety_rules_json')).'</td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37LiftPlanned').'</td><td><input type="checkbox" name="lift_planned" value="1"'.($values['lift_planned'] ? ' checked' : '').'></td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37LadderPlanned').'</td><td><input type="checkbox" name="ladder_planned" value="1"'.($values['ladder_planned'] ? ' checked' : '').'></td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37LifelinePlanned').'</td><td><input type="checkbox" name="lifeline_planned" value="1"'.($values['lifeline_planned'] ? ' checked' : '').'></td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37EpiChecked').'</td><td><input type="checkbox" name="epi_checked" value="1"'.($values['epi_checked'] ? ' checked' : '').'></td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37SafetyInstructions').'</td><td>'.jpsun_fr37_textarea('safety_instructions', $values['safety_instructions'], 3).'</td></tr>';
print '</table></div>';

print '</div>';
print '<div class="fichehalfright">';
print load_fiche_titre($langs->trans('JpsunFr37SoldWork'), '', '');
print '<div class="div-table-responsive">';
print '<table class="border centpercent tableforfieldcreate">';
print '<tr><td class="titlefieldcreate">'.$langs->trans('JpsunFr37InverterLocation').'</td><td>'.jpsun_fr37_textarea('inverter_location', $values['inverter_location'], 4).'</td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37PanelLayout').'</td><td>'.jpsun_fr37_textarea('panel_layout', $values['panel_layout'], 4).'</td></tr>';
print '</table></div>';

print load_fiche_titre($langs->trans('JpsunFr37WorksDone'), '', '');
print '<div class="div-table-responsive">';
print '<table class="border centpercent tableforfieldcreate">';
print '<tr><td class="titlefieldcreate">'.$langs->trans('JpsunFr37WorksDone').'</td><td>'.jpsun_fr37_textarea('works_done', $values['works_done'], 4).'</td></tr>';
print '</table></div>';

print load_fiche_titre($langs->trans('JpsunFr37Tests'), '', '');
print '<div class="div-table-responsive">';
print '<table class="border centpercent tableforfieldcreate">';
print '<tr><td class="titlefieldcreate">'.$langs->trans('JpsunFr37ConsuelCase').'</td><td>';
print '<select class="flat minwidth200 maxwidthonsmartphone" name="consuel_case" id="jpsun_fr37_consuel_case"><option value="">&nbsp;</option>';
foreach ($consuelCases as $case) {
	print '<option value="'.dol_escape_htmltag($case->code).'"'.($values['consuel_case'] === $case->code ? ' selected' : '').'>'.dol_escape_htmltag($case->label).'</option>';
}
print '</select> <a href="#" id="jpsun_fr37_consuel_info" class="marginleftonly" title="'.dol_escape_htmltag($langs->trans('Info')).'">'.img_picto($langs->trans('Info'), 'info').'</a></td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37CheckDcConnectors').'</td><td><input type="checkbox" name="check_dc_connectors" value="1"'.($values['check_dc_connectors'] ? ' checked' : '').'></td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37CheckAcBox').'</td><td><input type="checkbox" name="check_ac_box" value="1"'.($values['check_ac_box'] ? ' checked' : '').'></td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37CheckCablesTrunking').'</td><td><input type="checkbox" name="check_cables_trunking" value="1"'.($values['check_cables_trunking'] ? ' checked' : '').'></td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37CheckGrounds').'</td><td><input type="checkbox" name="check_grounds" value="1"'.($values['check_grounds'] ? ' checked' : '').'></td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37CheckLabels').'</td><td><input type="checkbox" name="check_labels" value="1"'.($values['check_labels'] ? ' checked' : '').'></td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37EarthValue').'</td><td><input class="flat width100 maxwidthonsmartphone" type="text" inputmode="decimal" name="earth_value" value="'.dol_escape_htmltag((string) $values['earth_value']).'"> Ohm</td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37InverterType').'</td><td><select class="flat minwidth300 maxwidthonsmartphone jpsun-fr37-inverter-type-select" name="inverter_type" data-category="INVERTER" data-text-value="1">';
if ($inverterTypeValue !== '') {
	print '<option value="'.dol_escape_htmltag($inverterTypeValue).'" selected>'.dol_escape_htmltag($inverterTypeValue).'</option>';
}
print '</select></td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37InverterSerial').'</td><td><input class="flat minwidth300 maxwidthonsmartphone" type="text" name="inverter_serial" value="'.dol_escape_htmltag((string) $values['inverter_serial']).'"></td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37InverterPower').'</td><td><input class="flat width100 maxwidthonsmartphone" type="text" inputmode="decimal" name="inverter_power" value="'.dol_escape_htmltag((string) $values['inverter_power']).'"> W</td></tr>';
print '<tr><td>'.$langs->trans('JpsunFr37Connection').'</td><td>';
print jpsun_fr37_select('connection_type', $values['connection_type'], array('WIFI' => 'JpsunFr37ConnectionWifi', 'ETHERNET' => 'JpsunFr37ConnectionEthernet', 'CELLULAR' => 'JpsunFr37ConnectionCellular'), 'id="jpsun_fr37_connection_type"');
print '<div id="jpsun_fr37_wifi_reason" class="marginleftonly marginbottomonly">'.jpsun_fr37_textarea('wifi_reason', $values['wifi_reason'], 2).'</div>';
print '<div id="jpsun_fr37_sim_info" class="marginleftonly marginbottomonly">'.jpsun_fr37_textarea('sim_info', $values['sim_info'], 2).'</div>';
print '</td></tr>';
print '</table></div>';
print '</div>';
print '<div class="clearboth"></div>';

print load_fiche_titre($langs->trans('JpsunFr37StringVoltages'), '', '');
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent jpsun-fr37-string-table" id="jpsun_fr37_strings"><thead><tr><th>'.$langs->trans('JpsunFr37StringNo').'</th><th>'.$langs->trans('JpsunFr37Voltage').'</th><th>'.$langs->trans('JpsunFr37PvCount').'</th><th></th></tr></thead><tbody>';
$strings = !empty($report->strings) ? $report->strings : array(array('string_no' => 1, 'voltage' => '', 'pv_count' => ''));
foreach ($strings as $stringRow) {
	print '<tr>';
	print '<td><input class="flat width75 maxwidthonsmartphone" type="number" min="1" name="string_no[]" value="'.dol_escape_htmltag((string) $stringRow['string_no']).'"></td>';
	print '<td><input class="flat width75 maxwidthonsmartphone" type="text" inputmode="decimal" name="string_voltage[]" value="'.dol_escape_htmltag((string) $stringRow['voltage']).'"></td>';
	print '<td><input class="flat width75 maxwidthonsmartphone" type="number" min="0" name="string_pv_count[]" value="'.dol_escape_htmltag((string) $stringRow['pv_count']).'"></td>';
	print '<td class="jpsun-fr37-string-actions">'.jpsun_fr37_string_remove_link().'</td>';
	print '</tr>';
}
print '</tbody></table>';
print '</div>';
print '<div class="margintoponly"><a class="editfielda reposition jpsun-fr37-add-string" href="" title="'.dol_escape_htmltag($langs->trans('JpsunFr37AddString')).'">'.img_picto('add', 'add').'</a></div>';

print load_fiche_titre($langs->trans('JpsunFr37ObservationsConclusion'), '', '');
print '<div class="div-table-responsive">';
print '<table class="border centpercent tableforfieldcreate">';
print '<tr><td class="titlefieldcreate">'.$langs->trans('JpsunFr37ObservationsConclusion').'</td><td>'.jpsun_fr37_textarea('observations_conclusion', $values['observations_conclusion'], 4).'</td></tr>';
print '</table></div>';

if ($canEdit) {
	print '<div class="center">';
	print '<input class="button button-save" type="submit" value="'.$langs->trans('Save').'">';
	print '</div>';
}
print '</form>';

print '<div id="jpsun_fr37_consuel_modal" title="'.dol_escape_htmltag($langs->trans('JpsunFr37ConsuelCase')).'" style="display:none">';
if (empty($consuelCases)) {
	print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
} else {
	$consuelCount = count($consuelCases);
	foreach ($consuelCases as $index => $case) {
		$imageUrl = '';
		if (!empty($case->illustration)) {
			$imageUrl = dol_buildpath('/jpsun/img/consuel/'.basename($case->illustration), 1);
		}
		print '<div class="jpsun-fr37-consuel-slide" data-code="'.dol_escape_htmltag($case->code).'">';
		print '<div class="titre">'.dol_escape_htmltag($case->label).'</div>';
		if ($imageUrl) {
			print '<div class="center"><img src="'.$imageUrl.'" alt="'.dol_escape_htmltag($case->label).'"></div>';
		}
		if (!empty($case->description)) {
			print '<div class="margintoponly">'.dol_nl2br(dol_escape_htmltag($case->description)).'</div>';
		}
		print '<div class="right opacitymedium">'.($index + 1).' / '.$consuelCount.'</div>';
		print '</div>';
	}
	print '<div class="center margintoponly">';
	print '<button type="button" class="button jpsun-fr37-consuel-prev">'.$langs->trans('Previous').'</button> ';
	print '<button type="button" class="button button-edit jpsun-fr37-consuel-next">'.$langs->trans('Next').'</button>';
	print '</div>';
}
print '</div>';

print '<div class="fichehalfleft">';
print load_fiche_titre($langs->trans('JpsunFr37BeforePhotos'), '', '');
print '<div class="div-table-responsive"><table class="border centpercent tableforfieldcreate"><tr><td class="titlefieldcreate">'.$langs->trans('JpsunFr37BeforePhotos').'</td><td>';
jpsun_fr37_print_photos($object, 'before');
if ($canEdit) {
	print '<form class="margintoponly" method="POST" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'?id='.((int) $object->id).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="uploadbefore">';
	print '<input class="flat" type="file" name="userfile[]" accept="image/*" multiple>';
	print '<input class="button smallpaddingimp" type="submit" value="'.$langs->trans('JpsunFr37UploadBefore').'">';
	print '</form>';
}
print '</td></tr></table></div>';
print '</div>';

print '<div class="fichehalfright">';
print load_fiche_titre($langs->trans('JpsunFr37AfterPhotos'), '', '');
print '<div class="div-table-responsive"><table class="border centpercent tableforfieldcreate"><tr><td class="titlefieldcreate">'.$langs->trans('JpsunFr37AfterPhotos').'</td><td>';
jpsun_fr37_print_photos($object, 'after');
if ($canEdit) {
	print '<form class="margintoponly" method="POST" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'?id='.((int) $object->id).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="uploadafter">';
	print '<input class="flat" type="file" name="userfile[]" accept="image/*" multiple>';
	print '<input class="button smallpaddingimp" type="submit" value="'.$langs->trans('JpsunFr37UploadAfter').'">';
	print '</form>';
}
print '</td></tr></table></div>';
print '</div>';
print '<div class="clearboth"></div>';

print '</div>';
print dol_get_fiche_end();

print '<script>
jQuery(function($) {
	function toggleBlock(select, value, target) {
		var selected = $(select).val();
		$(target).toggle(selected === value);
	}
	function refreshVisibility() {
		toggleBlock("#jpsun_fr37_intervention_object", "OTHER", "#jpsun_fr37_intervention_object_other");
		toggleBlock("#jpsun_fr37_roof_type", "OTHER", "#jpsun_fr37_roof_type_other");
		toggleBlock("#jpsun_fr37_install_type", "OTHER", "#jpsun_fr37_install_type_other");
		toggleBlock("#jpsun_fr37_connection_type", "WIFI", "#jpsun_fr37_wifi_reason");
		toggleBlock("#jpsun_fr37_connection_type", "CELLULAR", "#jpsun_fr37_sim_info");
	}
	$("#jpsun_fr37_intervention_object,#jpsun_fr37_roof_type,#jpsun_fr37_install_type,#jpsun_fr37_connection_type").on("change", refreshVisibility);
	refreshVisibility();

	if ($.fn.select2) {
		$(".jpsun-fr37-product-select,.jpsun-fr37-inverter-type-select").each(function() {
			var $select = $(this);
			$select.select2({
				width: "100%",
				tags: $select.data("text-value") ? true : false,
				ajax: {
					url: "'.dol_buildpath('/jpsun/ajax/fr37_product_search.php', 1).'",
					dataType: "json",
					delay: 250,
					data: function(params) {
						return { q: params.term || "", category: $select.data("category") || "", text_value: $select.data("text-value") ? 1 : 0 };
					}
				}
			});
		});
	}

	$(".jpsun-fr37-add-string").on("click", function(event) {
		event.preventDefault();
		var rowCount = $("#jpsun_fr37_strings tbody tr").length + 1;
		var removeButton = "'.dol_escape_js(jpsun_fr37_string_remove_link()).'";
		var row = "<tr>"
			+ "<td><input class=\"flat width75 maxwidthonsmartphone\" type=\"number\" min=\"1\" name=\"string_no[]\" value=\"" + rowCount + "\"></td>"
			+ "<td><input class=\"flat width75 maxwidthonsmartphone\" type=\"text\" inputmode=\"decimal\" name=\"string_voltage[]\"></td>"
			+ "<td><input class=\"flat width75 maxwidthonsmartphone\" type=\"number\" min=\"0\" name=\"string_pv_count[]\"></td>"
			+ "<td class=\"jpsun-fr37-string-actions\">" + removeButton + "</td>"
			+ "</tr>";
		$("#jpsun_fr37_strings tbody").append(row);
	});
	$(document).on("click", ".jpsun-fr37-remove-string", function(event) {
		event.preventDefault();
		if ($("#jpsun_fr37_strings tbody tr").length > 1) {
			$(this).closest("tr").remove();
		}
	});

	var consuelIndex = 0;
	var $consuelModal = $("#jpsun_fr37_consuel_modal");
	var $consuelSlides = $consuelModal.find(".jpsun-fr37-consuel-slide");

	function showConsuelSlide(index) {
		if (!$consuelSlides.length) {
			return;
		}
		consuelIndex = (index + $consuelSlides.length) % $consuelSlides.length;
		$consuelSlides.hide().eq(consuelIndex).show();
	}

	function getSelectedConsuelIndex() {
		var selectedCode = $("#jpsun_fr37_consuel_case").val();
		var selectedIndex = 0;
		if (selectedCode) {
			$consuelSlides.each(function(index) {
				if ($(this).data("code") === selectedCode) {
					selectedIndex = index;
					return false;
				}
			});
		}
		return selectedIndex;
	}

	$("#jpsun_fr37_consuel_info").on("click", function(event) {
		event.preventDefault();
		showConsuelSlide(getSelectedConsuelIndex());
		if ($.fn.dialog) {
			if (!$consuelModal.hasClass("ui-dialog-content")) {
				$consuelModal.dialog({
					autoOpen: false,
					modal: true,
					width: Math.min($(window).width() - 40, 860),
					maxHeight: $(window).height() - 60
				});
			} else {
				$consuelModal.dialog("option", "width", Math.min($(window).width() - 40, 860));
				$consuelModal.dialog("option", "maxHeight", $(window).height() - 60);
			}
			$consuelModal.dialog("open");
		} else {
			$consuelModal.show();
		}
	});
	$(".jpsun-fr37-consuel-prev").on("click", function() {
		showConsuelSlide(consuelIndex - 1);
	});
	$(".jpsun-fr37-consuel-next").on("click", function() {
		showConsuelSlide(consuelIndex + 1);
	});
});
</script>';

llxFooter();
$db->close();
