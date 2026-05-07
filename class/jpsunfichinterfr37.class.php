<?php
/* Copyright (C) 2026 JPSUN
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * FR37 data attached to an intervention card.
 */
class JpsunFichinterFr37
{
	const PRODUCT_ROLE_PANEL = 'PANEL';
	const PRODUCT_ROLE_INVERTER_SOLD = 'INVERTER_SOLD';

	/** @var DoliDB */
	public $db;

	/** @var int */
	public $id = 0;

	/** @var int */
	public $fk_fichinter = 0;

	/** @var array<string,mixed> */
	public $values = array();

	/** @var array<string,array<int>> */
	public $products = array(
		self::PRODUCT_ROLE_PANEL => array(),
		self::PRODUCT_ROLE_INVERTER_SOLD => array(),
	);

	/** @var array<int,array<string,mixed>> */
	public $strings = array();

	/** @var string */
	public $error = '';

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->values = $this->getDefaultValues();
	}

	/**
	 * Main table field definitions.
	 *
	 * @return array<string,string>
	 */
	public static function getFieldDefinitions()
	{
		return array(
			'present_on_site' => 'int',
			'intervention_object' => 'string',
			'intervention_object_other' => 'text',
			'panel_qty' => 'int',
			'roof_type' => 'string',
			'roof_type_other' => 'text',
			'install_type' => 'string',
			'install_type_other' => 'text',
			'roof_access_json' => 'json',
			'electrical_connection' => 'string',
			'risk_identified_json' => 'json',
			'risk_other' => 'text',
			'prevention_measures_json' => 'json',
			'collective_protection_json' => 'json',
			'individual_protection_json' => 'json',
			'lift_planned' => 'int',
			'ladder_planned' => 'int',
			'lifeline_planned' => 'int',
			'epi_checked' => 'int',
			'safety_rules_json' => 'json',
			'safety_instructions' => 'text',
			'inverter_location' => 'text',
			'panel_layout' => 'text',
			'works_done' => 'text',
			'consuel_case' => 'string',
			'check_dc_connectors' => 'int',
			'check_ac_box' => 'int',
			'check_cables_trunking' => 'int',
			'check_grounds' => 'int',
			'check_labels' => 'int',
			'earth_value' => 'float',
			'inverter_type' => 'string',
			'inverter_serial' => 'string',
			'inverter_power' => 'float',
			'connection_type' => 'string',
			'wifi_reason' => 'text',
			'sim_info' => 'text',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getDefaultValues()
	{
		$values = array();
		foreach (self::getFieldDefinitions() as $field => $type) {
			if ($type === 'int') {
				$values[$field] = null;
			} elseif ($type === 'float') {
				$values[$field] = null;
			} elseif ($type === 'json') {
				$values[$field] = '[]';
			} else {
				$values[$field] = '';
			}
		}

		$values['check_dc_connectors'] = 0;
		$values['check_ac_box'] = 0;
		$values['check_cables_trunking'] = 0;
		$values['check_grounds'] = 0;
		$values['check_labels'] = 0;

		return $values;
	}

	/**
	 * Load the FR37 report attached to an intervention.
	 *
	 * @param int $fkFichinter Intervention id
	 * @return int 1 found, 0 not found, <0 error
	 */
	public function fetchByFichinter($fkFichinter)
	{
		$this->id = 0;
		$this->fk_fichinter = (int) $fkFichinter;
		$this->values = $this->getDefaultValues();
		$this->products = array(self::PRODUCT_ROLE_PANEL => array(), self::PRODUCT_ROLE_INVERTER_SOLD => array());
		$this->strings = array();

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX."jpsun_fichinter_fr37";
		$sql .= " WHERE fk_fichinter = ".((int) $fkFichinter);
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		if (!$obj) {
			return 0;
		}

		$this->id = (int) $obj->rowid;
		$this->fk_fichinter = (int) $obj->fk_fichinter;
		foreach (self::getFieldDefinitions() as $field => $type) {
			if (property_exists($obj, $field)) {
				$this->values[$field] = $obj->$field;
			}
		}

		$this->products = $this->fetchProducts();
		$this->strings = $this->fetchStrings();

		return 1;
	}

	/**
	 * Create or update the FR37 report.
	 *
	 * @param User  $user         Current user
	 * @param int   $fkFichinter  Intervention id
	 * @param array $values       Main values
	 * @param array $products     Product ids by role
	 * @param array $strings      String rows
	 * @return int >0 ok, <0 error
	 */
	public function save($user, $fkFichinter, $values, $products, $strings)
	{
		global $conf;

		$fkFichinter = (int) $fkFichinter;
		$existing = $this->fetchByFichinter($fkFichinter);
		if ($existing < 0) {
			return -1;
		}

		$this->db->begin();

		$cleanValues = $this->sanitizeValues($values);
		if ($existing > 0) {
			$sets = array();
			foreach (self::getFieldDefinitions() as $field => $type) {
				$sets[] = $field." = ".$this->sqlValue($cleanValues[$field], $type);
			}
			$sets[] = "fk_user_modif = ".((int) $user->id);
			$sql = "UPDATE ".MAIN_DB_PREFIX."jpsun_fichinter_fr37 SET ".implode(', ', $sets);
			$sql .= " WHERE rowid = ".((int) $this->id);
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
		} else {
			$fields = array('entity', 'fk_fichinter', 'fk_user_creat', 'datec');
			$sqlValues = array(((int) $conf->entity), $fkFichinter, ((int) $user->id), "'".$this->db->idate(dol_now())."'");
			foreach (self::getFieldDefinitions() as $field => $type) {
				$fields[] = $field;
				$sqlValues[] = $this->sqlValue($cleanValues[$field], $type);
			}
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."jpsun_fichinter_fr37 (".implode(', ', $fields).")";
			$sql .= " VALUES (".implode(', ', $sqlValues).")";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
			$this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'jpsun_fichinter_fr37');
			$this->fk_fichinter = $fkFichinter;
		}

		if ($this->replaceProducts($products) < 0 || $this->replaceStrings($strings) < 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		$this->fetchByFichinter($fkFichinter);

		return $this->id;
	}

	/**
	 * @param array $values Raw values
	 * @return array<string,mixed>
	 */
	public function sanitizeValues($values)
	{
		$clean = $this->getDefaultValues();
		foreach (self::getFieldDefinitions() as $field => $type) {
			$value = isset($values[$field]) ? $values[$field] : null;
			if ($type === 'int') {
				$clean[$field] = ($value === '' || $value === null ? null : (int) $value);
			} elseif ($type === 'float') {
				$clean[$field] = ($value === '' || $value === null ? null : price2num($value, 'MU'));
			} elseif ($type === 'json') {
				$clean[$field] = $this->encodeList((array) $value);
			} else {
				$clean[$field] = (string) $value;
			}
		}

		foreach (array('check_dc_connectors', 'check_ac_box', 'check_cables_trunking', 'check_grounds', 'check_labels') as $checkboxField) {
			$clean[$checkboxField] = empty($values[$checkboxField]) ? 0 : 1;
		}

		return $clean;
	}

	/**
	 * @param string $field Field name
	 * @return array<int,string>
	 */
	public function getJsonList($field)
	{
		$value = isset($this->values[$field]) ? $this->values[$field] : '[]';
		$decoded = json_decode((string) $value, true);

		return is_array($decoded) ? $decoded : array();
	}

	/**
	 * @param string $role Product role
	 * @return array<int>
	 */
	public function getProductIds($role)
	{
		return isset($this->products[$role]) && is_array($this->products[$role]) ? $this->products[$role] : array();
	}

	/**
	 * @param string $role Product role
	 * @return array<int,array<string,string>>
	 */
	public function getProductLabels($role)
	{
		$ids = $this->getProductIds($role);
		if (empty($ids)) {
			return array();
		}

		$sql = "SELECT rowid, ref, label FROM ".MAIN_DB_PREFIX."product";
		$sql .= " WHERE rowid IN (".implode(',', array_map('intval', $ids)).")";
		$sql .= " ORDER BY ref ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return array();
		}

		$labels = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$labels[] = array(
				'id' => (int) $obj->rowid,
				'label' => trim($obj->ref.' - '.$obj->label),
			);
		}

		return $labels;
	}

	/**
	 * @param string $role Product role
	 * @return string
	 */
	public function getProductLabelsText($role)
	{
		$labels = array();
		foreach ($this->getProductLabels($role) as $product) {
			$labels[] = $product['label'];
		}

		return implode(', ', $labels);
	}

	/**
	 * @return array<string,array<int>>
	 */
	private function fetchProducts()
	{
		$products = array(self::PRODUCT_ROLE_PANEL => array(), self::PRODUCT_ROLE_INVERTER_SOLD => array());
		if (empty($this->id)) {
			return $products;
		}

		$sql = "SELECT role, fk_product FROM ".MAIN_DB_PREFIX."jpsun_fichinter_fr37_product";
		$sql .= " WHERE fk_fr37 = ".((int) $this->id);
		$sql .= " ORDER BY rowid ASC";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $products;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			if (!isset($products[$obj->role])) {
				$products[$obj->role] = array();
			}
			$products[$obj->role][] = (int) $obj->fk_product;
		}

		return $products;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function fetchStrings()
	{
		$strings = array();
		if (empty($this->id)) {
			return $strings;
		}

		$sql = "SELECT string_no, voltage, pv_count FROM ".MAIN_DB_PREFIX."jpsun_fichinter_fr37_string";
		$sql .= " WHERE fk_fr37 = ".((int) $this->id);
		$sql .= " ORDER BY position ASC, string_no ASC";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $strings;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$strings[] = array(
				'string_no' => (int) $obj->string_no,
				'voltage' => $obj->voltage,
				'pv_count' => ($obj->pv_count === null ? null : (int) $obj->pv_count),
			);
		}

		return $strings;
	}

	/**
	 * @param array $products Product ids by role
	 * @return int
	 */
	private function replaceProducts($products)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."jpsun_fichinter_fr37_product WHERE fk_fr37 = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		foreach (array(self::PRODUCT_ROLE_PANEL, self::PRODUCT_ROLE_INVERTER_SOLD) as $role) {
			$ids = isset($products[$role]) ? (array) $products[$role] : array();
			$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
			foreach ($ids as $productId) {
				$sql = "INSERT INTO ".MAIN_DB_PREFIX."jpsun_fichinter_fr37_product (fk_fr37, role, fk_product)";
				$sql .= " VALUES (".((int) $this->id).", '".$this->db->escape($role)."', ".((int) $productId).")";
				if (!$this->db->query($sql)) {
					$this->error = $this->db->lasterror();
					return -1;
				}
			}
		}

		return 1;
	}

	/**
	 * @param array $strings String rows
	 * @return int
	 */
	private function replaceStrings($strings)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."jpsun_fichinter_fr37_string WHERE fk_fr37 = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$position = 0;
		foreach ((array) $strings as $row) {
			$stringNo = isset($row['string_no']) ? (int) $row['string_no'] : 0;
			$voltage = isset($row['voltage']) && $row['voltage'] !== '' ? price2num($row['voltage'], 'MU') : null;
			$pvCount = isset($row['pv_count']) && $row['pv_count'] !== '' ? (int) $row['pv_count'] : null;
			if ($stringNo <= 0 && $voltage === null && $pvCount === null) {
				continue;
			}
			if ($stringNo <= 0) {
				$stringNo = $position + 1;
			}
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."jpsun_fichinter_fr37_string (fk_fr37, string_no, voltage, pv_count, position)";
			$sql .= " VALUES (".((int) $this->id).", ".((int) $stringNo).", ".$this->sqlFloat($voltage).", ".($pvCount === null ? 'NULL' : (int) $pvCount).", ".((int) $position).")";
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$position++;
		}

		return 1;
	}

	/**
	 * @param mixed  $value Field value
	 * @param string $type  Field type
	 * @return string
	 */
	private function sqlValue($value, $type)
	{
		if ($type === 'int') {
			return ($value === null || $value === '' ? 'NULL' : (string) ((int) $value));
		}
		if ($type === 'float') {
			return $this->sqlFloat($value);
		}

		return "'".$this->db->escape((string) $value)."'";
	}

	/**
	 * @param mixed $value Numeric value
	 * @return string
	 */
	private function sqlFloat($value)
	{
		if ($value === null || $value === '') {
			return 'NULL';
		}

		return (string) price2num($value, 'MU');
	}

	/**
	 * @param array $values List values
	 * @return string
	 */
	private function encodeList($values)
	{
		$clean = array();
		foreach ($values as $value) {
			$value = trim((string) $value);
			if ($value !== '') {
				$clean[] = $value;
			}
		}

		return json_encode(array_values(array_unique($clean)));
	}

	/**
	 * @param string $categoryCode Technical category code
	 * @param string $search       Search string
	 * @param int    $limit        Max rows
	 * @return array<int,array{id:int,text:string}>
	 */
	public function searchProductsByTechnicalCategory($categoryCode, $search = '', $limit = 20)
	{
		$categoryCode = trim((string) $categoryCode);
		$limit = max(1, min(100, (int) $limit));

		$sql = "SELECT p.rowid, p.ref, p.label";
		$sql .= " FROM ".MAIN_DB_PREFIX."product AS p";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product_extrafields AS pe ON pe.fk_object = p.rowid";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."c_technical_category AS ctc ON ctc.rowid = pe.pppv_technical_category";
		$sql .= " WHERE p.entity IN (".getEntity('product').")";
		$sql .= " AND ctc.code = '".$this->db->escape($categoryCode)."'";
		$sql .= " AND ctc.active = 1";
		if ($search !== '') {
			$like = method_exists($this->db, 'escapeforlike') ? $this->db->escapeforlike($search) : str_replace(array('%', '_'), array('\%', '\_'), $search);
			$sql .= " AND (p.ref LIKE '%".$this->db->escape($like)."%' OR p.label LIKE '%".$this->db->escape($like)."%')";
		}
		$sql .= " ORDER BY p.ref ASC";
		$sql .= $this->db->plimit($limit);

		$resql = $this->db->query($sql);
		if (!$resql) {
			return array();
		}

		$rows = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$rows[] = array('id' => (int) $obj->rowid, 'text' => trim($obj->ref.' - '.$obj->label));
		}

		return $rows;
	}

	/**
	 * @return array<int,object>
	 */
	public function getConsuelCases()
	{
		$sql = "SELECT code, label, description, illustration";
		$sql .= " FROM ".MAIN_DB_PREFIX."c_jpsun_consuel_case";
		$sql .= " WHERE active = 1 AND entity IN (0, ".((int) $GLOBALS['conf']->entity).")";
		$sql .= " ORDER BY position ASC, code ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return array();
		}

		$rows = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$rows[] = $obj;
		}

		return $rows;
	}

	/**
	 * @param string $code Consuel case code
	 * @return object|null
	 */
	public function getConsuelCase($code)
	{
		$sql = "SELECT code, label, description, illustration";
		$sql .= " FROM ".MAIN_DB_PREFIX."c_jpsun_consuel_case";
		$sql .= " WHERE active = 1 AND code = '".$this->db->escape($code)."'";
		$sql .= " AND entity IN (0, ".((int) $GLOBALS['conf']->entity).")";
		$sql .= " ORDER BY entity DESC";
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return null;
		}

		$obj = $this->db->fetch_object($resql);
		return $obj ?: null;
	}

	/**
	 * @param Fichinter $object Intervention
	 * @param string    $bucket before|after
	 * @return string
	 */
	public static function getPhotoDir($object, $bucket)
	{
		$bucket = ($bucket === 'after' ? 'fr37_after' : 'fr37_before');
		$ref = dol_sanitizeFileName($object->ref);

		return rtrim(getMultidirOutput($object), '/').'/'.$ref.'/'.$bucket;
	}

	/**
	 * @param Fichinter $object Intervention
	 * @param string    $bucket before|after
	 * @return array<int,array<string,mixed>>
	 */
	public static function getPhotos($object, $bucket)
	{
		$dir = self::getPhotoDir($object, $bucket);
		if (!dol_is_dir($dir)) {
			return array();
		}

		$files = dol_dir_list($dir, 'files', 0, '', '(\.meta$|\/thumbs\/|_preview\.png$)', 'name', SORT_ASC, 1);
		return is_array($files) ? $files : array();
	}
}
