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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Helpers for optional lmdbzoning integration.
 *
 * @file		lib/jpsun_lmdbzoning.lib.php
 * @ingroup		jpsun
 */

/**
 * Check if lmdbzoning is enabled.
 *
 * @return bool
 */
function jpsunIsLmdbZoningEnabled()
{
	global $conf;

	if (function_exists('isModEnabled')) {
		return (bool) isModEnabled('lmdbzoning');
	}

	return !empty($conf->lmdbzoning->enabled);
}

/**
 * Check whether the lmdbzoning service class can be loaded.
 *
 * @return bool
 */
function jpsunLmdbZoningServiceClassAvailable()
{
	if (class_exists('LmdbZoningService')) {
		return true;
	}
	if (function_exists('dol_include_once')) {
		dol_include_once('/lmdbzoning/class/lmdbzoningservice.class.php');
	}

	return class_exists('LmdbZoningService');
}

/**
 * Check if one table exists.
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$table	Table name without prefix
 * @return	bool
 */
function jpsunLmdbZoningTableExists($db, $table)
{
	static $cache = array();

	$table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
	if ($table === '') {
		return false;
	}
	if (!array_key_exists($table, $cache)) {
		$sql = "SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.$table)."'";
		$resql = $db->query($sql);
		$cache[$table] = ($resql && $db->num_rows($resql) > 0);
		if ($resql) {
			$db->free($resql);
		}
	}

	return $cache[$table];
}

/**
 * Check if one table column exists.
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$table	Table name without prefix
 * @param	string	$column	Column name
 * @return	bool
 */
function jpsunLmdbZoningColumnExists($db, $table, $column)
{
	static $cache = array();

	$table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
	$column = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
	if ($table === '' || $column === '' || !jpsunLmdbZoningTableExists($db, $table)) {
		return false;
	}

	$key = $table.'.'.$column;
	if (!array_key_exists($key, $cache)) {
		$sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX.$table." LIKE '".$db->escape($column)."'";
		$resql = $db->query($sql);
		$cache[$key] = ($resql && $db->num_rows($resql) > 0);
		if ($resql) {
			$db->free($resql);
		}
	}

	return $cache[$key];
}

/**
 * Check if the lmdbzoning schema can provide contract categories.
 *
 * @param	DoliDB	$db	Database handler
 * @return	bool
 */
function jpsunLmdbZoningContractCategorySchemaAvailable($db)
{
	if (!jpsunIsLmdbZoningEnabled()) {
		return false;
	}
	if (!jpsunLmdbZoningServiceClassAvailable()) {
		return false;
	}

	foreach (array('categorie_contract', 'categorie', 'contrat', 'lmdbzoning_profile', 'lmdbzoning_profile_zone') as $table) {
		if (!jpsunLmdbZoningTableExists($db, $table)) {
			return false;
		}
	}

	return jpsunLmdbZoningColumnExists($db, 'lmdbzoning_profile_zone', 'fk_categorie_contract')
		&& jpsunLmdbZoningColumnExists($db, 'lmdbzoning_profile_zone', 'fk_categorie_default');
}

/**
 * Return a safe SQL entity scope for one Dolibarr element.
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$element	Element key
 * @param	int		$entity		Current entity fallback
 * @return	string
 */
function jpsunLmdbZoningGetEntitySql($db, $element, $entity = 0)
{
	global $conf;

	$entities = array();
	if (function_exists('getEntity')) {
		foreach (explode(',', (string) getEntity($element)) as $value) {
			$value = trim($value);
			if ($value !== '' && preg_match('/^-?[0-9]+$/', $value)) {
				$entities[] = (int) $value;
			}
		}
	}
	if ((int) $entity > 0) {
		$entities[] = (int) $entity;
	}
	if (empty($entities)) {
		$entities[] = isset($conf->entity) ? (int) $conf->entity : 1;
	}

	return implode(',', array_values(array_unique($entities)));
}

/**
 * Return the lmdbzoning default profile reference.
 *
 * @return string
 */
function jpsunLmdbZoningGetDefaultProfileRef()
{
	global $conf;

	if (function_exists('getDolGlobalString')) {
		return trim((string) getDolGlobalString('LMDBZONING_DEFAULT_PROFILE'));
	}

	return !empty($conf->global->LMDBZONING_DEFAULT_PROFILE) ? trim((string) $conf->global->LMDBZONING_DEFAULT_PROFILE) : '';
}

/**
 * Fetch the lmdbzoning contract category zone.
 *
 * @param	DoliDB			$db			Database handler
 * @param	Contrat|object	$contract	Contract object
 * @param	string			$profileRef	Optional lmdbzoning profile ref
 * @return	array{result:int,error:string,zone_label:string,zone_code:string,fk_zone:int,fk_categorie:int,profile_ref:string}
 */
function jpsunLmdbZoningFetchContractCategoryZone($db, $contract, $profileRef = '')
{
	$empty = array(
		'result' => 0,
		'error' => '',
		'zone_label' => '',
		'zone_code' => '',
		'fk_zone' => 0,
		'fk_categorie' => 0,
		'profile_ref' => '',
	);

	$contractid = 0;
	if (!empty($contract->id)) {
		$contractid = (int) $contract->id;
	} elseif (!empty($contract->rowid)) {
		$contractid = (int) $contract->rowid;
	}
	if ($contractid <= 0) {
		return $empty;
	}
	if (!jpsunLmdbZoningContractCategorySchemaAvailable($db)) {
		$empty['error'] = 'LmdbZoningUnavailable';
		return $empty;
	}

	$entity = !empty($contract->entity) ? (int) $contract->entity : 0;
	$profileRef = trim((string) $profileRef);
	if ($profileRef === '') {
		$profileRef = jpsunLmdbZoningGetDefaultProfileRef();
	}

	$sql = "SELECT z.rowid as fk_zone, z.zone_code, z.label as zone_label, z.priority, cc.fk_categorie, p.ref as profile_ref";
	$sql .= " FROM ".MAIN_DB_PREFIX."categorie_contract as cc";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."contrat as co ON co.rowid = cc.fk_contract";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."categorie as c ON c.rowid = cc.fk_categorie";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."lmdbzoning_profile_zone as z ON (";
	$sql .= "z.fk_categorie_contract = cc.fk_categorie";
	$sql .= " OR ((z.fk_categorie_contract IS NULL OR z.fk_categorie_contract = 0) AND z.fk_categorie_default = cc.fk_categorie)";
	$sql .= ")";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."lmdbzoning_profile as p ON p.rowid = z.fk_profile";
	$sql .= " WHERE cc.fk_contract = ".$contractid;
	$sql .= " AND co.entity IN (".jpsunLmdbZoningGetEntitySql($db, 'contrat', $entity).")";
	$sql .= " AND c.entity IN (".jpsunLmdbZoningGetEntitySql($db, 'category', $entity).")";
	$sql .= " AND z.entity IN (".jpsunLmdbZoningGetEntitySql($db, 'lmdbzoning_zone', $entity).")";
	$sql .= " AND p.entity IN (".jpsunLmdbZoningGetEntitySql($db, 'lmdbzoning_profile', $entity).")";
	$sql .= " AND z.active = 1";
	$sql .= " AND p.active = 1";
	if ($profileRef !== '') {
		$sql .= " AND p.ref = '".$db->escape($profileRef)."'";
	}
	$sql .= " ORDER BY z.priority DESC, CASE WHEN z.fk_categorie_contract = cc.fk_categorie THEN 0 ELSE 1 END ASC, z.rowid DESC";
	$sql .= $db->plimit(1);

	$resql = $db->query($sql);
	if (!$resql) {
		$empty['result'] = -1;
		$empty['error'] = $db->lasterror();
		return $empty;
	}

	$obj = $db->fetch_object($resql);
	$db->free($resql);
	if (!$obj) {
		return $empty;
	}

	return array(
		'result' => 1,
		'error' => '',
		'zone_label' => !empty($obj->zone_label) ? (string) $obj->zone_label : (string) $obj->zone_code,
		'zone_code' => (string) $obj->zone_code,
		'fk_zone' => (int) $obj->fk_zone,
		'fk_categorie' => (int) $obj->fk_categorie,
		'profile_ref' => (string) $obj->profile_ref,
	);
}

/**
 * Return the lmdbzoning zone label selected for a contract.
 *
 * @param	DoliDB			$db			Database handler
 * @param	Contrat|object	$contract	Contract object
 * @return	string
 */
function jpsunLmdbZoningGetContractZoneLabel($db, $contract)
{
	$result = jpsunLmdbZoningFetchContractCategoryZone($db, $contract);

	return !empty($result['zone_label']) ? (string) $result['zone_label'] : '';
}
