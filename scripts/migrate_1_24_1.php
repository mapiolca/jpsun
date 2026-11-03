<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

// Internal migration, called by the descriptor; never a public execution endpoint.
if (!defined('DOL_DOCUMENT_ROOT')) {
	http_response_code(403);
	exit;
}

/** Retire legacy fields and migrate product brands once per entity, only in JPSUN 1.24.1. */
class JpsunMigration1241
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';
	/** @var int Brands still requiring a destination field or a larger destination size. */
	public $pending = 0;

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @param string $moduleVersion Version from the module descriptor
	 * @param int $entity Owner entity, never the product-sharing scope
	 * @return int 1 completed, 0 skipped or pending, -1 error (transaction rolled back)
	 */
	public function run($moduleVersion, $entity)
	{
		global $conf;
		$this->error = '';
		$this->pending = 0;
		if ($moduleVersion !== '1.24.1' || getDolGlobalInt('JPSUN_MIGRATION_1241_DONE')) {
			return 0;
		}
		if ($entity <= 0 || $entity !== (int) $conf->entity) {
			$this->error = 'Invalid migration entity';
			return -1;
		}
		if (!$this->db->begin()) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		// Product extrafields already exist after the descriptor's regular product setup.
		$resql = $this->db->DDLDescTable($this->db->prefix().'product_extrafields');
		if (!$resql) {
			return $this->fail();
		}
		$hasSource = false;
		$targetSize = 0;
		while (is_object($column = $this->db->fetch_object($resql))) {
			if ($column->Field === 'jpsun_marque') {
				$hasSource = true;
			}
			if ($column->Field === 'product_photovoltaic_brand'
				&& preg_match('/^varchar\(([1-9][0-9]*)\)$/i', $column->Type, $matches)) {
				$targetSize = (int) $matches[1];
			}
		}
		$this->db->free($resql);

		if ($hasSource) {
			$where = 'fk_object IN (SELECT rowid FROM '.$this->db->prefix().'product WHERE entity = '.((int) $entity).')';
			$where .= " AND jpsun_marque IS NOT NULL AND TRIM(jpsun_marque) <> ''";
			if ($targetSize > 0) {
				$where .= " AND (product_photovoltaic_brand IS NULL OR TRIM(product_photovoltaic_brand) = '')";
				// Never truncate the old 255-character field into PowerPlantPV's 128-character field.
				$sql = 'UPDATE '.$this->db->prefix().'product_extrafields SET product_photovoltaic_brand = jpsun_marque';
				$sql .= ' WHERE '.$where.' AND CHAR_LENGTH(jpsun_marque) <= '.$targetSize;
				if (!$this->db->query($sql)) {
					return $this->fail();
				}
			}
			$resql = $this->db->query('SELECT COUNT(*) as pending FROM '.$this->db->prefix().'product_extrafields WHERE '.$where);
			if (!$resql) {
				return $this->fail();
			}
			$row = $this->db->fetch_object($resql);
			if (!is_object($row)) {
				return $this->fail();
			}
			$this->pending = (int) $row->pending;
			$this->db->free($resql);
		}

		// Configuration metadata only: retain values, labels, defaults and foreign entities.
		$fields = array(
			'product' => array('jpsun_marque'),
			'projet' => array('jpsun_project_pc', 'jpsun_project_n_ddr', 'jpsun_project_t0',
				'jpsun_project_prix_rachat', 'jpsun_project_type_racc', 'jpsun_project_mes_date'),
			'contrat' => array('jpsun_site_name', 'jpsun_installed_power_kwc', 'jpsun_pv_module_product',
				'jpsun_pv_module_qty', 'jpsun_inverter_product', 'jpsun_inverter_qty',
				'jpsun_dc_boxes_qty', 'jpsun_ac_boxes_qty', 'jpsun_access_code', 'jpsun_pdl_number'),
		);
		foreach ($fields as $element => $names) {
			$sql = 'UPDATE '.$this->db->prefix()."extrafields SET enabled = '0', list = '0', printable = 0";
			$sql .= " WHERE elementtype = '".$this->db->escape($element)."' AND (entity = 0 OR entity = ".((int) $entity).')';
			$conditions = array();
			foreach ($names as $name) {
				$conditions[] = "name = '".$this->db->escape($name)."'";
			}
			$sql .= ' AND ('.implode(' OR ', $conditions).')';
			if (!$this->db->query($sql)) {
				return $this->fail();
			}
		}

		if (!$this->pending) {
			if (!function_exists('dolibarr_set_const')) {
				require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
			}
			if (dolibarr_set_const($this->db, 'JPSUN_MIGRATION_1241_DONE', '1', 'chaine', 0,
				'JPSUN 1.24.1 migration completed for this entity', (int) $entity) <= 0) {
				return $this->fail();
			}
		}
		if (!$this->db->commit()) {
			// The native constant writer updates this cache before the outer commit.
			$conf->global->JPSUN_MIGRATION_1241_DONE = '0';
			return $this->fail();
		}
		return $this->pending ? 0 : 1;
	}

	/** @return int Always -1 after a database failure */
	private function fail()
	{
		global $conf;
		$this->error = $this->db->lasterror();
		if (!$this->db->rollback()) {
			$this->error .= ' (rollback failed: '.$this->db->lasterror().')';
		}
		$conf->global->JPSUN_MIGRATION_1241_DONE = '0';
		return -1;
	}
}
