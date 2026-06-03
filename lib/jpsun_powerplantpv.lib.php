<?php
/* Copyright (C) 2026	Pierre Ardoin	<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Helpers for optional PowerPlantPV integration.
 *
 * @file		lib/jpsun_powerplantpv.lib.php
 * @ingroup		jpsun
 */

/**
 * Check if the PowerPlantPV module is enabled.
 *
 * @return bool
 */
function jpsunIsPowerPlantPVEnabled()
{
	return function_exists('isModEnabled') && isModEnabled('powerplantpv');
}

/**
 * Check if a user can read PowerPlantPV power plants.
 *
 * @param User|null $user Current user
 * @return bool
 */
function jpsunCanReadPowerPlantPV($user = null)
{
	if (!jpsunIsPowerPlantPVEnabled()) {
		return false;
	}

	if (!is_object($user)) {
		$user = isset($GLOBALS['user']) ? $GLOBALS['user'] : null;
	}
	if (!is_object($user)) {
		return false;
	}

	if (method_exists($user, 'hasRight')) {
		return (bool) $user->hasRight('powerplantpv', 'powerplant', 'read');
	}

	return !empty($user->rights->powerplantpv->powerplant->read);
}

/**
 * Return an entity SQL list for a Dolibarr element.
 *
 * @param string $element Element key
 * @return string
 */
function jpsunPowerPlantPVGetEntitySql($element)
{
	global $conf;

	if (function_exists('getEntity')) {
		return getEntity($element);
	}

	return isset($conf->entity) ? (string) ((int) $conf->entity) : '1';
}

/**
 * Build a quoted SQL string list.
 *
 * @param DoliDB $db Database handler
 * @param string[] $values Values
 * @return string
 */
function jpsunPowerPlantPVSqlStringList($db, $values)
{
	$escaped = array();
	foreach ($values as $value) {
		$escaped[] = "'".$db->escape((string) $value)."'";
	}

	return implode(',', $escaped);
}

/**
 * Fetch PowerPlantPV power plants linked to a Dolibarr contract.
 *
 * @param DoliDB $db Database handler
 * @param Contrat|CommonObject $contract Contract object
 * @param User|null $user Current user
 * @return array{result:int,error:string,powerplants:array<int,array<string,mixed>>}
 */
function jpsunPowerPlantPVFetchLinkedPowerPlants($db, $contract, $user = null)
{
	if (!jpsunIsPowerPlantPVEnabled()) {
		return array('result' => 0, 'error' => '', 'powerplants' => array());
	}
	if (!jpsunCanReadPowerPlantPV($user)) {
		return array('result' => -1, 'error' => 'JpsunPowerPlantPVPermissionDenied', 'powerplants' => array());
	}

	$contractid = 0;
	if (!empty($contract->id)) {
		$contractid = (int) $contract->id;
	} elseif (!empty($contract->rowid)) {
		$contractid = (int) $contract->rowid;
	}
	if ($contractid <= 0) {
		return array('result' => 0, 'error' => '', 'powerplants' => array());
	}

	$powerplanttypes = array('powerplant@powerplantpv', 'powerplantpv_powerplant', 'powerplant');
	$contracttypes = array('contrat', 'contract');

	$sql = "SELECT DISTINCT p.rowid, p.ref, p.label, p.entity, p.commissioning_date, p.prm_pdl_number,";
	$sql .= " p.address, p.zip, p.town, p.fk_country, p.installed_power, p.connection_contract_power,";
	$sql .= " p.connection_type, p.enedis_commissioning_date, p.connection_request_number,";
	$sql .= " p.t0_obtention_date, p.buyback_contract_number, p.buyback_tariff, p.fk_soc, p.fk_project,";
	$sql .= " p.description, p.note_public, p.status";
	$sql .= " FROM ".MAIN_DB_PREFIX."element_element as ee";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."powerplantpv_powerplant as p ON (";
	$sql .= " (ee.fk_source = ".$contractid." AND ee.sourcetype IN (".jpsunPowerPlantPVSqlStringList($db, $contracttypes).")";
	$sql .= " AND ee.fk_target = p.rowid AND ee.targettype IN (".jpsunPowerPlantPVSqlStringList($db, $powerplanttypes)."))";
	$sql .= " OR (ee.fk_target = ".$contractid." AND ee.targettype IN (".jpsunPowerPlantPVSqlStringList($db, $contracttypes).")";
	$sql .= " AND ee.fk_source = p.rowid AND ee.sourcetype IN (".jpsunPowerPlantPVSqlStringList($db, $powerplanttypes)."))";
	$sql .= ")";
	$sql .= " WHERE p.entity IN (".jpsunPowerPlantPVGetEntitySql('powerplant').")";
	$sql .= " ORDER BY p.ref ASC, p.rowid ASC";

	$resql = $db->query($sql);
	if (!$resql) {
		return array('result' => -1, 'error' => $db->lasterror(), 'powerplants' => array());
	}

	$powerplants = array();
	while ($obj = $db->fetch_object($resql)) {
		$powerplants[] = array(
			'id' => (int) $obj->rowid,
			'rowid' => (int) $obj->rowid,
			'ref' => (string) $obj->ref,
			'label' => (string) $obj->label,
			'entity' => (int) $obj->entity,
			'commissioning_date' => $obj->commissioning_date,
			'prm_pdl_number' => (string) $obj->prm_pdl_number,
			'address' => (string) $obj->address,
			'zip' => (string) $obj->zip,
			'town' => (string) $obj->town,
			'fk_country' => (int) $obj->fk_country,
			'installed_power' => (float) $obj->installed_power,
			'connection_contract_power' => (float) $obj->connection_contract_power,
			'connection_type' => (string) $obj->connection_type,
			'enedis_commissioning_date' => $obj->enedis_commissioning_date,
			'connection_request_number' => (string) $obj->connection_request_number,
			't0_obtention_date' => $obj->t0_obtention_date,
			'buyback_contract_number' => (string) $obj->buyback_contract_number,
			'buyback_tariff' => (float) $obj->buyback_tariff,
			'fk_soc' => (int) $obj->fk_soc,
			'fk_project' => (int) $obj->fk_project,
			'description' => (string) $obj->description,
			'note_public' => (string) $obj->note_public,
			'status' => (int) $obj->status,
		);
	}
	$db->free($resql);

	return array('result' => 1, 'error' => '', 'powerplants' => $powerplants);
}

/**
 * Fetch component lines of a PowerPlantPV power plant.
 *
 * @param DoliDB $db Database handler
 * @param int $powerplantid Power plant id
 * @return array{result:int,error:string,components:array<int,array<string,mixed>>}
 */
function jpsunPowerPlantPVFetchComponents($db, $powerplantid)
{
	$powerplantid = (int) $powerplantid;
	if ($powerplantid <= 0 || !jpsunIsPowerPlantPVEnabled()) {
		return array('result' => 0, 'error' => '', 'components' => array());
	}

	$sql = "SELECT pc.rowid, pc.fk_powerplant, pc.fk_product, pc.fk_status, pc.qty, pc.serial_number, pc.commissioning_date,";
	$sql .= " p.ref as product_ref, p.label as product_label, pe.categorie_photovoltaique as category_id,";
	$sql .= " cpv.code as category_code, cpv.label as category_label, pv.pmax, inv.ac_nominal_power, inv.ac_max_power";
	$sql .= " FROM ".MAIN_DB_PREFIX."powerplantpv_powerplantcomp as pc";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = pc.fk_product";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields as pe ON pe.fk_object = pc.fk_product";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_powerplantpv_categorypv as cpv ON cpv.rowid = pe.categorie_photovoltaique";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."powerplantpv_product_pvpanel as pv ON pv.fk_product = pc.fk_product AND pv.entity IN (".jpsunPowerPlantPVGetEntitySql('product').")";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."powerplantpv_product_inverter as inv ON inv.fk_product = pc.fk_product AND inv.entity IN (".jpsunPowerPlantPVGetEntitySql('product').")";
	$sql .= " WHERE pc.fk_powerplant = ".$powerplantid;
	$sql .= " AND pc.entity IN (".jpsunPowerPlantPVGetEntitySql('powerplant').")";
	$sql .= " AND (p.rowid IS NULL OR p.entity IN (".jpsunPowerPlantPVGetEntitySql('product')."))";
	$sql .= " ORDER BY cpv.code ASC, p.ref ASC, pc.rowid ASC";

	$resql = $db->query($sql);
	if (!$resql) {
		return array('result' => -1, 'error' => $db->lasterror(), 'components' => array());
	}

	$components = array();
	while ($obj = $db->fetch_object($resql)) {
		$components[] = array(
			'id' => (int) $obj->rowid,
			'rowid' => (int) $obj->rowid,
			'fk_powerplant' => (int) $obj->fk_powerplant,
			'fk_product' => (int) $obj->fk_product,
			'fk_status' => (int) $obj->fk_status,
			'qty' => (float) $obj->qty,
			'serial_number' => (string) $obj->serial_number,
			'commissioning_date' => $obj->commissioning_date,
			'product_ref' => (string) $obj->product_ref,
			'product_label' => (string) $obj->product_label,
			'category_id' => (int) $obj->category_id,
			'category_code' => (string) $obj->category_code,
			'category_label' => (string) $obj->category_label,
			'pmax' => (float) $obj->pmax,
			'ac_nominal_power' => (float) $obj->ac_nominal_power,
			'ac_max_power' => (float) $obj->ac_max_power,
		);
	}
	$db->free($resql);

	return array('result' => 1, 'error' => '', 'components' => $components);
}

/**
 * Return components for one or more photovoltaic category codes.
 *
 * @param array<int,array<string,mixed>> $components Components
 * @param string[] $categorycodes Category codes
 * @return array<int,array<string,mixed>>
 */
function jpsunPowerPlantPVFilterComponentsByCategory($components, $categorycodes)
{
	$normalizedcodes = array();
	foreach ($categorycodes as $categorycode) {
		$normalizedcodes[strtoupper((string) $categorycode)] = true;
	}

	$filtered = array();
	foreach ($components as $component) {
		$code = isset($component['category_code']) ? strtoupper((string) $component['category_code']) : '';
		if ($code !== '' && isset($normalizedcodes[$code])) {
			$filtered[] = $component;
		}
	}

	return $filtered;
}

/**
 * Sum component quantities.
 *
 * @param array<int,array<string,mixed>> $components Components
 * @return float
 */
function jpsunPowerPlantPVSumComponentQty($components)
{
	$total = 0.0;
	foreach ($components as $component) {
		$total += isset($component['qty']) ? (float) $component['qty'] : 0.0;
	}

	return $total;
}

/**
 * Format a component list for PDF output.
 *
 * @param array<int,array<string,mixed>> $components Components
 * @param string $powerfield Optional power field to append
 * @param string $unit Unit for power value
 * @return string
 */
function jpsunPowerPlantPVFormatComponentSummary($components, $powerfield = '', $unit = '')
{
	$labels = array();
	foreach ($components as $component) {
		$label = jpsunPowerPlantPVBuildComponentLabel($component);

		if ($powerfield !== '' && !empty($component[$powerfield])) {
			$label .= ' ('.rtrim(rtrim(sprintf('%.2F', (float) $component[$powerfield]), '0'), '.').' '.$unit.')';
		}
		if (!empty($component['qty'])) {
			$label .= ' x '.rtrim(rtrim(sprintf('%.2F', (float) $component['qty']), '0'), '.');
		}
		$labels[] = $label;
	}

	return implode("\n", $labels);
}

/**
 * Build a display label for one PowerPlantPV component.
 *
 * @param array<string,mixed> $component Component data
 * @return string
 */
function jpsunPowerPlantPVBuildComponentLabel($component)
{
	$label = trim((string) ($component['product_ref'] ?? ''));
	$productlabel = trim((string) ($component['product_label'] ?? ''));
	if ($label !== '' && $productlabel !== '') {
		$label .= ' - '.$productlabel;
	} elseif ($label === '') {
		$label = $productlabel;
	}
	if ($label === '') {
		$label = (string) ($component['category_label'] ?? '');
	}
	if ($label === '') {
		$label = (string) ($component['fk_product'] ?? '');
	}

	return $label;
}

/**
 * Group components by product and power characteristics for PDF material tables.
 *
 * @param array<int,array<string,mixed>> $components Components
 * @param string[] $categorycodes Category codes
 * @param string[] $powerfields Power fields to expose
 * @return array<int,array<string,mixed>>
 */
function jpsunPowerPlantPVGroupComponentsByProduct($components, $categorycodes, $powerfields = array())
{
	$filtered = jpsunPowerPlantPVFilterComponentsByCategory($components, $categorycodes);
	$groups = array();

	foreach ($filtered as $component) {
		$powerparts = array();
		foreach ($powerfields as $powerfield) {
			$powerparts[] = isset($component[$powerfield]) ? (string) $component[$powerfield] : '';
		}

		$key = implode('|', array(
			(string) ($component['fk_product'] ?? ''),
			jpsunPowerPlantPVBuildComponentLabel($component),
			implode('|', $powerparts),
		));

		if (!isset($groups[$key])) {
			$groups[$key] = array(
				'label' => jpsunPowerPlantPVBuildComponentLabel($component),
				'qty' => 0.0,
				'category_code' => (string) ($component['category_code'] ?? ''),
				'category_label' => (string) ($component['category_label'] ?? ''),
				'pmax' => isset($component['pmax']) ? (float) $component['pmax'] : 0.0,
				'ac_nominal_power' => isset($component['ac_nominal_power']) ? (float) $component['ac_nominal_power'] : 0.0,
				'ac_max_power' => isset($component['ac_max_power']) ? (float) $component['ac_max_power'] : 0.0,
			);
		}

		$groups[$key]['qty'] += isset($component['qty']) ? (float) $component['qty'] : 0.0;
	}

	return array_values($groups);
}

/**
 * Normalize text for robust type/contact matching.
 *
 * @param string $text Text
 * @return string
 */
function jpsunPowerPlantPVNormalizeMatchText($text)
{
	$text = strtolower((string) $text);
	$text = strtr($text, array(
		'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
		'ç' => 'c',
		'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
		'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
		'ñ' => 'n',
		'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
		'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
		'ý' => 'y', 'ÿ' => 'y',
		'æ' => 'ae', 'œ' => 'oe',
	));
	$text = preg_replace('/[^a-z0-9]+/', ' ', $text);

	return trim((string) $text);
}

/**
 * Check if a contact type row matches a PowerPlantPV site role.
 *
 * @param array<string,string> $contact Contact row
 * @param string $role Role code
 * @return bool
 */
function jpsunPowerPlantPVContactMatchesSiteRole($contact, $role)
{
	$text = jpsunPowerPlantPVNormalizeMatchText(implode(' ', array(
		$contact['code'] ?? '',
		$contact['typecode'] ?? '',
		$contact['libelle'] ?? '',
		$contact['label'] ?? '',
	)));

	if ($role === 'technical') {
		return (strpos($text, 'technique') !== false || strpos($text, 'technical') !== false || strpos($text, 'technic') !== false || strpos($text, 'tech ') !== false);
	}
	if ($role === 'administrative') {
		return (strpos($text, 'administratif') !== false || strpos($text, 'administrative') !== false || strpos($text, 'admin') !== false);
	}

	return false;
}

/**
 * Build a normalized contact data array.
 *
 * @param object $obj SQL contact row
 * @return array<string,string>
 */
function jpsunPowerPlantPVBuildContactData($obj)
{
	$fullname = trim((string) $obj->firstname.' '.(string) $obj->lastname);
	if ($fullname === '') {
		$fullname = (string) $obj->lastname;
	}

	return array(
		'fullname' => $fullname,
		'job' => (string) $obj->poste,
		'phone' => (string) ($obj->phone_pro ?: $obj->phone_mobile),
		'email' => (string) $obj->email,
		'code' => (string) $obj->code,
		'label' => (string) $obj->libelle,
	);
}

/**
 * Fetch PowerPlantPV customer technical and administrative contacts.
 *
 * @param DoliDB $db Database handler
 * @param int $powerplantid Power plant id
 * @return array{technical:array<string,string>,administrative:array<string,string>}
 */
function jpsunPowerPlantPVFetchSiteContacts($db, $powerplantid)
{
	$empty = array('fullname' => '', 'job' => '', 'phone' => '', 'email' => '', 'code' => '', 'label' => '');
	$contacts = array('technical' => $empty, 'administrative' => $empty);
	$powerplantid = (int) $powerplantid;
	if ($powerplantid <= 0) {
		return $contacts;
	}

	$sql = "SELECT ec.rowid, tc.code, tc.libelle, tc.source, sp.lastname, sp.firstname, sp.poste,";
	$sql .= " sp.phone as phone_pro, sp.phone_mobile, sp.email";
	$sql .= " FROM ".MAIN_DB_PREFIX."element_contact as ec";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."c_type_contact as tc ON tc.rowid = ec.fk_c_type_contact";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."socpeople as sp ON sp.rowid = ec.fk_socpeople";
	$sql .= " WHERE ec.element_id = ".$powerplantid;
	$sql .= " AND tc.element IN ('powerplant', 'powerplant@powerplantpv', 'powerplantpv_powerplant')";
	$sql .= " AND tc.source = 'external'";
	$sql .= " AND tc.active = 1";
	$sql .= " AND sp.entity IN (".jpsunPowerPlantPVGetEntitySql('contact').")";
	$sql .= " ORDER BY tc.position ASC, ec.rowid ASC";

	$resql = $db->query($sql);
	if (!$resql) {
		return $contacts;
	}

	while ($obj = $db->fetch_object($resql)) {
		$row = array(
			'code' => (string) $obj->code,
			'typecode' => (string) $obj->code,
			'libelle' => (string) $obj->libelle,
			'label' => (string) $obj->libelle,
		);
		if ($contacts['technical']['fullname'] === '' && jpsunPowerPlantPVContactMatchesSiteRole($row, 'technical')) {
			$contacts['technical'] = jpsunPowerPlantPVBuildContactData($obj);
		}
		if ($contacts['administrative']['fullname'] === '' && jpsunPowerPlantPVContactMatchesSiteRole($row, 'administrative')) {
			$contacts['administrative'] = jpsunPowerPlantPVBuildContactData($obj);
		}
	}
	$db->free($resql);

	return $contacts;
}

/**
 * Return the PowerPlantPV document upload directory for a power plant row.
 *
 * @param array<string,mixed> $powerplant Power plant data
 * @return string
 */
function jpsunPowerPlantPVGetDocumentUploadDir($powerplant)
{
	global $conf;

	if (function_exists('dol_include_once')) {
		@include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		@dol_include_once('/powerplantpv/lib/powerplantpv_powerplant.lib.php');
	}

	$ref = isset($powerplant['ref']) ? (string) $powerplant['ref'] : '';
	if ($ref === '') {
		return '';
	}

	$object = new stdClass();
	$object->ref = $ref;
	$object->entity = isset($powerplant['entity']) ? (int) $powerplant['entity'] : (isset($conf->entity) ? (int) $conf->entity : 1);

	if (function_exists('powerplantGetDocumentUploadDir')) {
		return powerplantGetDocumentUploadDir($object);
	}

	$base = '';
	if (!empty($conf->powerplantpv->multidir_output[$object->entity])) {
		$base = $conf->powerplantpv->multidir_output[$object->entity];
	} elseif (!empty($conf->powerplantpv->dir_output)) {
		$base = $conf->powerplantpv->dir_output;
	}
	if ($base === '') {
		return '';
	}

	return $base.'/powerplant/'.dol_sanitizeFileName($ref);
}

/**
 * Return the first image attached to a PowerPlantPV power plant, sorted by filename.
 *
 * @param array<string,mixed> $powerplant Power plant data
 * @return string
 */
function jpsunPowerPlantPVGetFirstImagePath($powerplant)
{
	$upload_dir = jpsunPowerPlantPVGetDocumentUploadDir($powerplant);
	if ($upload_dir === '' || !is_dir($upload_dir)) {
		return '';
	}

	$imagepattern = '\.(jpe?g|png|gif|webp)$';
	$excludepattern = '(\.meta|_preview.*\.png)$';
	if (function_exists('dol_dir_list')) {
		$files = dol_dir_list($upload_dir, 'files', 0, $imagepattern, $excludepattern, 'name', SORT_ASC, 1);
		if (is_array($files)) {
			foreach ($files as $file) {
				$path = !empty($file['fullname']) ? $file['fullname'] : $upload_dir.'/'.(isset($file['name']) ? $file['name'] : '');
				if ($path !== '' && is_readable($path)) {
					return $path;
				}
			}
		}
	}

	$names = @scandir($upload_dir);
	if (!is_array($names)) {
		return '';
	}
	natcasesort($names);
	foreach ($names as $name) {
		if ($name === '.' || $name === '..') {
			continue;
		}
		if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $name) || preg_match('/(\.meta|_preview.*\.png)$/i', $name)) {
			continue;
		}
		$path = $upload_dir.'/'.$name;
		if (is_readable($path)) {
			return $path;
		}
	}

	return '';
}

/**
 * Build a PDF-ready dataset for all power plants linked to a contract.
 *
 * @param DoliDB $db Database handler
 * @param Contrat|CommonObject $contract Contract object
 * @param User|null $user Current user
 * @return array{result:int,error:string,powerplants:array<int,array<string,mixed>>}
 */
function jpsunPowerPlantPVBuildContractDataset($db, $contract, $user = null)
{
	$result = jpsunPowerPlantPVFetchLinkedPowerPlants($db, $contract, $user);
	if ($result['result'] <= 0) {
		return $result;
	}

	foreach ($result['powerplants'] as $key => $powerplant) {
		$componentresult = jpsunPowerPlantPVFetchComponents($db, (int) $powerplant['id']);
		if ($componentresult['result'] < 0) {
			return array('result' => -1, 'error' => $componentresult['error'], 'powerplants' => array());
		}

		$components = $componentresult['components'];
		$modules = jpsunPowerPlantPVFilterComponentsByCategory($components, array('MODULE'));
		$inverters = jpsunPowerPlantPVFilterComponentsByCategory($components, array('ONDULE'));
		$dcboxes = jpsunPowerPlantPVFilterComponentsByCategory($components, array('COFFDC'));
		$acboxes = jpsunPowerPlantPVFilterComponentsByCategory($components, array('COFFAC'));
		$sitecontacts = jpsunPowerPlantPVFetchSiteContacts($db, (int) $powerplant['id']);

		$result['powerplants'][$key]['components'] = $components;
		$result['powerplants'][$key]['site_name'] = trim((string) $powerplant['label']) !== '' ? (string) $powerplant['label'] : (string) $powerplant['ref'];
		$result['powerplants'][$key]['full_address'] = trim((string) $powerplant['address']."\n".trim((string) $powerplant['zip'].' '.(string) $powerplant['town']));
		$result['powerplants'][$key]['modules_label'] = jpsunPowerPlantPVFormatComponentSummary($modules, 'pmax', 'Wc');
		$result['powerplants'][$key]['modules_qty'] = jpsunPowerPlantPVSumComponentQty($modules);
		$result['powerplants'][$key]['modules_rows'] = jpsunPowerPlantPVGroupComponentsByProduct($components, array('MODULE'), array('pmax'));
		$result['powerplants'][$key]['inverters_label'] = jpsunPowerPlantPVFormatComponentSummary($inverters, 'ac_nominal_power', 'kVA');
		$result['powerplants'][$key]['inverters_qty'] = jpsunPowerPlantPVSumComponentQty($inverters);
		$result['powerplants'][$key]['inverters_rows'] = jpsunPowerPlantPVGroupComponentsByProduct($components, array('ONDULE'), array('ac_nominal_power', 'ac_max_power'));
		$result['powerplants'][$key]['dc_boxes_qty'] = jpsunPowerPlantPVSumComponentQty($dcboxes);
		$result['powerplants'][$key]['dc_box_rows'] = jpsunPowerPlantPVGroupComponentsByProduct($components, array('COFFDC'));
		$result['powerplants'][$key]['ac_boxes_qty'] = jpsunPowerPlantPVSumComponentQty($acboxes);
		$result['powerplants'][$key]['ac_box_rows'] = jpsunPowerPlantPVGroupComponentsByProduct($components, array('COFFAC'));
		$result['powerplants'][$key]['technical_contact'] = $sitecontacts['technical'];
		$result['powerplants'][$key]['administrative_contact'] = $sitecontacts['administrative'];
		$result['powerplants'][$key]['first_image'] = jpsunPowerPlantPVGetFirstImagePath($result['powerplants'][$key]);
	}

	return $result;
}
