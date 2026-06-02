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
	$categorycodes = array_flip($categorycodes);
	$filtered = array();
	foreach ($components as $component) {
		$code = isset($component['category_code']) ? (string) $component['category_code'] : '';
		if ($code !== '' && isset($categorycodes[$code])) {
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

		$result['powerplants'][$key]['components'] = $components;
		$result['powerplants'][$key]['site_name'] = trim((string) $powerplant['label']) !== '' ? (string) $powerplant['label'] : (string) $powerplant['ref'];
		$result['powerplants'][$key]['full_address'] = trim((string) $powerplant['address']."\n".trim((string) $powerplant['zip'].' '.(string) $powerplant['town']));
		$result['powerplants'][$key]['modules_label'] = jpsunPowerPlantPVFormatComponentSummary($modules, 'pmax', 'Wc');
		$result['powerplants'][$key]['modules_qty'] = jpsunPowerPlantPVSumComponentQty($modules);
		$result['powerplants'][$key]['inverters_label'] = jpsunPowerPlantPVFormatComponentSummary($inverters, 'ac_nominal_power', 'kVA');
		$result['powerplants'][$key]['inverters_qty'] = jpsunPowerPlantPVSumComponentQty($inverters);
		$result['powerplants'][$key]['dc_boxes_qty'] = jpsunPowerPlantPVSumComponentQty($dcboxes);
		$result['powerplants'][$key]['ac_boxes_qty'] = jpsunPowerPlantPVSumComponentQty($acboxes);
	}

	return $result;
}
