<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file class/jpsununfinishedprojectreport.class.php
 * \ingroup jpsun
 * \brief Data provider for the unfinished projects accounting report.
 */

require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/expensereport/class/expensereport.class.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

/**
 * @phpstan-type UnfinishedProjectReportFilters array{
 *     project_ref?: string,
 *     project_title?: string,
 *     thirdparty?: string,
 *     entities?: list<int>
 * }
 * @phpstan-type UnfinishedProjectReportRow array{
 *     project_id: int,
 *     entity: int,
 *     project_ref: string,
 *     project_title: string,
 *     project_public: int,
 *     thirdparty_id: int,
 *     thirdparty_name: string,
 *     thirdparty_name_alias: string,
 *     order_count: int,
 *     orders_ht: float,
 *     deposits_ht: float,
 *     invoices_ht: float,
 *     remaining_ht: float,
 *     supplier_invoices_ht: float|null,
 *     expenses_paid_ht: float|null,
 *     miscellaneous_purchases: float|null,
 *     time_spent_ht: float,
 *     time_without_rate_count: int,
 *     shipments_valued: float|null,
 *     stock_missing_cost_count: int
 * }
 */
class JpsunUnfinishedProjectReport
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var list<string> */
	public $errors = array();

	/** @var array<string, bool> */
	private $availableSources = array();

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->availableSources = array(
			'supplier_invoices' => isModEnabled('supplier_invoice'),
			'expenses' => isModEnabled('expensereport'),
			'miscellaneous_purchases' => isModEnabled('bank') && !getDolGlobalString('BANK_USE_OLD_VARIOUS_PAYMENT'),
			'shipments' => isModEnabled('shipping') && isModEnabled('stock') && isModEnabled('margin'),
		);
	}

	/**
	 * Check access to the consolidated accounting report.
	 *
	 * The accounting report right is deliberately the authority for the
	 * consolidated figures. Project visibility is restricted separately.
	 *
	 * @param User $user Current user
	 * @return bool
	 */
	public static function canAccess($user)
	{
		if (!is_object($user) || !empty($user->socid)) {
			return false;
		}

		return $user->hasRight('accounting', 'comptarapport', 'lire')
			|| $user->hasRight('compta', 'resultat', 'lire');
	}

	/**
	 * Return availability of optional report sources.
	 *
	 * @return array<string, bool>
	 */
	public function getAvailableSources()
	{
		return $this->availableSources;
	}

	/**
	 * Return entity labels for entities accessible through project sharing.
	 *
	 * @return array<int, string>
	 */
	public function getAccessibleEntityLabels()
	{
		$entityIds = $this->parseEntityList(getEntity('project'));
		$options = array();
		foreach ($entityIds as $entityId) {
			$options[$entityId] = (string) $entityId;
		}

		if (empty($entityIds) || !isModEnabled('multicompany')) {
			return $options;
		}

		$sql = 'SELECT rowid, label';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'entity';
		$sql .= ' WHERE rowid IN ('.implode(',', $entityIds).')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_WARNING);
			return $options;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$entityId = (int) $obj->rowid;
			$label = isset($obj->label) ? trim((string) $obj->label) : '';
			if (isset($options[$entityId]) && $label !== '') {
				$options[$entityId] = $label;
			}
		}
		$this->db->free($resql);

		return $options;
	}

	/**
	 * Fetch all filtered rows. Pagination is applied by the page only after
	 * native amount normalization has removed fully invoiced projects.
	 *
	 * @param User $user Current user
	 * @param UnfinishedProjectReportFilters $filters List filters
	 * @param string $sortfield Allowed logical sort field
	 * @param string $sortorder ASC or DESC
	 * @return list<UnfinishedProjectReportRow>|false
	 */
	public function fetchRows($user, $filters, $sortfield = 'remaining_ht', $sortorder = 'DESC')
	{
		$this->error = '';
		$this->errors = array();

		if (!self::canAccess($user)) {
			$this->error = 'NotEnoughPermissions';
			$this->errors[] = $this->error;
			return false;
		}

		$sql = $this->buildBaseQuery($user, $filters);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			dol_syslog(__METHOD__.': '.$this->error, LOG_ERR);
			return false;
		}

		$rows = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$ordersHt = (float) price2num((float) $obj->orders_ht, 'MT');
			$depositsHt = (float) price2num((float) $obj->deposits_ht, 'MT');
			$invoicesHt = (float) price2num((float) $obj->invoices_ht, 'MT');
			$remainingHt = (float) price2num($ordersHt - $invoicesHt, 'MT');

			if ($remainingHt == 0.0) {
				continue;
			}

			$supplierInvoicesHt = null;
			if ($this->availableSources['supplier_invoices']) {
				$supplierInvoicesHt = (float) price2num((float) $obj->supplier_invoices_ht, 'MT');
			}

			$expensesPaidHt = null;
			if ($this->availableSources['expenses']) {
				$expensesPaidHt = (float) price2num((float) $obj->expenses_paid_ht, 'MT');
			}

			$miscellaneousPurchases = null;
			if ($this->availableSources['miscellaneous_purchases']) {
				$miscellaneousPurchases = (float) price2num((float) $obj->miscellaneous_purchases, 'MT');
			}

			$projectId = (int) $obj->project_id;
			$rows[$projectId] = array(
				'project_id' => $projectId,
				'entity' => (int) $obj->entity,
				'project_ref' => (string) $obj->project_ref,
				'project_title' => (string) $obj->project_title,
				'project_public' => (int) $obj->project_public,
				'thirdparty_id' => (int) $obj->thirdparty_id,
				'thirdparty_name' => (string) $obj->thirdparty_name,
				'thirdparty_name_alias' => (string) $obj->thirdparty_name_alias,
				'order_count' => (int) $obj->order_count,
				'orders_ht' => $ordersHt,
				'deposits_ht' => $depositsHt,
				'invoices_ht' => $invoicesHt,
				'remaining_ht' => $remainingHt,
				'supplier_invoices_ht' => $supplierInvoicesHt,
				'expenses_paid_ht' => $expensesPaidHt,
				'miscellaneous_purchases' => $miscellaneousPurchases,
				'time_spent_ht' => (float) price2num((float) $obj->time_spent_ht, 'MT'),
				'time_without_rate_count' => (int) $obj->time_without_rate_count,
				'shipments_valued' => $this->availableSources['shipments'] ? 0.0 : null,
				'stock_missing_cost_count' => 0,
			);
		}
		$this->db->free($resql);

		if ($this->availableSources['shipments'] && !empty($rows)) {
			if (!$this->loadStockValuation($rows)) {
				return false;
			}
		}

		$rows = array_values($rows);
		$this->sortRows($rows, $sortfield, $sortorder);

		return $rows;
	}

	/**
	 * @param User $user Current user
	 * @param UnfinishedProjectReportFilters $filters List filters
	 * @return string
	 */
	private function buildBaseQuery($user, $filters)
	{
		$orderStatuses = implode(',', array(
			Commande::STATUS_VALIDATED,
			Commande::STATUS_SHIPMENTONPROCESS,
			Commande::STATUS_CLOSED,
		));
		$invoiceStatuses = implode(',', array(Facture::STATUS_VALIDATED, Facture::STATUS_CLOSED));

		$sql = "SELECT p.rowid AS project_id, p.entity, p.ref AS project_ref, p.title AS project_title, p.public AS project_public,";
		$sql .= " p.fk_soc AS thirdparty_id, COALESCE(s.nom, '') AS thirdparty_name, COALESCE(s.name_alias, '') AS thirdparty_name_alias,";
		$sql .= " orders.order_count, orders.orders_ht,";
		$sql .= " COALESCE(invoices.deposits_ht, 0) AS deposits_ht, COALESCE(invoices.invoices_ht, 0) AS invoices_ht,";
		$sql .= $this->availableSources['supplier_invoices'] ? " COALESCE(supplier_invoices.supplier_invoices_ht, 0) AS supplier_invoices_ht," : " NULL AS supplier_invoices_ht,";
		$sql .= $this->availableSources['expenses'] ? " COALESCE(expenses.expenses_paid_ht, 0) AS expenses_paid_ht," : " NULL AS expenses_paid_ht,";
		$sql .= $this->availableSources['miscellaneous_purchases'] ? " COALESCE(miscellaneous.miscellaneous_purchases, 0) AS miscellaneous_purchases," : " NULL AS miscellaneous_purchases,";
		$sql .= " COALESCE(spent_time.time_spent_ht, 0) AS time_spent_ht, COALESCE(spent_time.time_without_rate_count, 0) AS time_without_rate_count";
		$sql .= " FROM ".MAIN_DB_PREFIX."projet AS p";
		$sql .= " INNER JOIN (";
		$sql .= " SELECT c.fk_projet, COUNT(c.rowid) AS order_count, SUM(c.total_ht) AS orders_ht";
		$sql .= " FROM ".MAIN_DB_PREFIX."commande AS c";
		$sql .= " WHERE c.entity IN (".getEntity('order').")";
		$sql .= " AND c.fk_statut IN (".$orderStatuses.")";
		$sql .= " AND c.fk_projet IS NOT NULL AND c.fk_projet > 0";
		$sql .= " GROUP BY c.fk_projet";
		$sql .= ") AS orders ON orders.fk_projet = p.rowid";
		$sql .= " LEFT JOIN (";
		$sql .= " SELECT f.fk_projet,";
		$sql .= " SUM(CASE WHEN f.type = ".Facture::TYPE_DEPOSIT." THEN f.total_ht ELSE 0 END) AS deposits_ht,";
		$sql .= " SUM(f.total_ht) AS invoices_ht";
		$sql .= " FROM ".MAIN_DB_PREFIX."facture AS f";
		$sql .= " WHERE f.entity IN (".getEntity('invoice').")";
		$sql .= " AND f.fk_statut IN (".$invoiceStatuses.")";
		$sql .= " AND f.type <> ".Facture::TYPE_PROFORMA;
		$sql .= " AND (f.close_code IS NULL OR f.close_code <> 'replaced')";
		$sql .= " AND f.fk_projet IS NOT NULL AND f.fk_projet > 0";
		$sql .= " GROUP BY f.fk_projet";
		$sql .= ") AS invoices ON invoices.fk_projet = p.rowid";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe AS s ON s.rowid = p.fk_soc AND s.entity IN (".getEntity('societe').")";

		if ($this->availableSources['supplier_invoices']) {
			$supplierInvoiceStatuses = implode(',', array(FactureFournisseur::STATUS_VALIDATED, FactureFournisseur::STATUS_CLOSED));
			$sql .= " LEFT JOIN (";
			$sql .= " SELECT ff.fk_projet, SUM(ff.total_ht) AS supplier_invoices_ht";
			$sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn AS ff";
			$sql .= " WHERE ff.entity IN (".getEntity('invoice_supplier').")";
			$sql .= " AND ff.fk_statut IN (".$supplierInvoiceStatuses.")";
			$sql .= " AND (ff.close_code IS NULL OR ff.close_code <> 'replaced')";
			$sql .= " AND ff.fk_projet IS NOT NULL AND ff.fk_projet > 0";
			$sql .= " GROUP BY ff.fk_projet";
			$sql .= ") AS supplier_invoices ON supplier_invoices.fk_projet = p.rowid";
		}

		if ($this->availableSources['expenses']) {
			$sql .= " LEFT JOIN (";
			$sql .= " SELECT ed.fk_projet, SUM(ed.total_ht) AS expenses_paid_ht";
			$sql .= " FROM ".MAIN_DB_PREFIX."expensereport_det AS ed";
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expensereport AS er ON er.rowid = ed.fk_expensereport";
			$sql .= " WHERE er.entity IN (".getEntity('expensereport').")";
			$sql .= " AND er.fk_statut = ".ExpenseReport::STATUS_CLOSED;
			$sql .= " AND er.paid = 1";
			$sql .= " AND ed.fk_projet IS NOT NULL AND ed.fk_projet > 0";
			$sql .= " GROUP BY ed.fk_projet";
			$sql .= ") AS expenses ON expenses.fk_projet = p.rowid";
		}

		if ($this->availableSources['miscellaneous_purchases']) {
			$sql .= " LEFT JOIN (";
			$sql .= " SELECT pv.fk_projet, SUM(pv.amount) AS miscellaneous_purchases";
			$sql .= " FROM ".MAIN_DB_PREFIX."payment_various AS pv";
			$sql .= " WHERE pv.entity IN (".getEntity('payment_various').")";
			$sql .= " AND pv.sens = 0";
			$sql .= " AND pv.fk_projet IS NOT NULL AND pv.fk_projet > 0";
			$sql .= " GROUP BY pv.fk_projet";
			$sql .= ") AS miscellaneous ON miscellaneous.fk_projet = p.rowid";
		}

		$sql .= " LEFT JOIN (";
		$sql .= " SELECT pt.fk_projet,";
		$sql .= " SUM((et.element_duration / 3600) * COALESCE(et.thm, 0)) AS time_spent_ht,";
		$sql .= " SUM(CASE WHEN et.element_duration > 0 AND COALESCE(et.thm, 0) <= 0 THEN 1 ELSE 0 END) AS time_without_rate_count";
		$sql .= " FROM ".MAIN_DB_PREFIX."projet_task AS pt";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet AS time_project ON time_project.rowid = pt.fk_projet";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."element_time AS et ON et.fk_element = pt.rowid AND et.elementtype = 'task'";
		$sql .= " WHERE time_project.entity IN (".getEntity('project').")";
		$sql .= " GROUP BY pt.fk_projet";
		$sql .= ") AS spent_time ON spent_time.fk_projet = p.rowid";

		$sql .= " WHERE p.entity IN (".getEntity('project').")";
		$sql .= " AND p.fk_statut = ".Project::STATUS_VALIDATED;

		if (empty($user->admin) && !$user->hasRight('projet', 'all', 'lire')) {
			$projectStatic = new Project($this->db);
			$authorizedProjects = $projectStatic->getProjectsAuthorizedForUser($user, 0, 1, 0);
			if (!is_string($authorizedProjects) || !preg_match('/^[0-9]+(?:,[0-9]+)*$/', $authorizedProjects)) {
				$authorizedProjects = '0';
			}
			$sql .= " AND p.rowid IN (".$this->db->sanitize($authorizedProjects).")";
		}

		$projectRef = isset($filters['project_ref']) ? trim((string) $filters['project_ref']) : '';
		if ($projectRef !== '') {
			$sql .= natural_search('p.ref', $projectRef);
		}
		$projectTitle = isset($filters['project_title']) ? trim((string) $filters['project_title']) : '';
		if ($projectTitle !== '') {
			$sql .= natural_search('p.title', $projectTitle);
		}
		$thirdparty = isset($filters['thirdparty']) ? trim((string) $filters['thirdparty']) : '';
		if ($thirdparty !== '') {
			$sql .= natural_search(array('s.nom', 's.name_alias'), $thirdparty);
		}

		$requestedEntities = isset($filters['entities']) && is_array($filters['entities']) ? $filters['entities'] : array();
		$allowedEntities = $this->parseEntityList(getEntity('project'));
		$selectedEntities = array_values(array_intersect($allowedEntities, array_map('intval', $requestedEntities)));
		if (!empty($requestedEntities)) {
			$sql .= " AND p.entity IN (".(!empty($selectedEntities) ? implode(',', $selectedEntities) : '0').")";
		}

		return $sql;
	}

	/**
	 * Add stock movement valuation to report rows.
	 *
	 * @param array<int, UnfinishedProjectReportRow> $rows Rows indexed by project id
	 * @return bool
	 */
	private function loadStockValuation(&$rows)
	{
		$projectIds = array_keys($rows);
		if (empty($projectIds)) {
			return true;
		}

		$sql = "SELECT e.fk_projet AS project_id, sm.fk_product, sm.value, sm.price";
		$sql .= " FROM ".MAIN_DB_PREFIX."stock_mouvement AS sm";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expedition AS e ON e.rowid = sm.fk_origin AND sm.origintype = 'shipping'";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."entrepot AS warehouse ON warehouse.rowid = sm.fk_entrepot";
		$sql .= " WHERE e.fk_projet IN (".implode(',', array_map('intval', $projectIds)).")";
		$sql .= " AND e.entity IN (".getEntity('shipping').")";
		$sql .= " AND warehouse.entity IN (".getEntity('stock').")";
		$sql .= " AND e.fk_statut IN (".Expedition::STATUS_VALIDATED.','.Expedition::STATUS_CLOSED.")";
		$sql .= " AND sm.type_mouvement = 2";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			dol_syslog(__METHOD__.': '.$this->error, LOG_ERR);
			return false;
		}

		$productPriceResolver = new Product($this->db);
		$costCache = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$projectId = (int) $obj->project_id;
			if (!isset($rows[$projectId])) {
				continue;
			}

			$productId = (int) $obj->fk_product;
			$movementPrice = (float) $obj->price;
			$cacheKey = $productId.'|'.((string) $obj->price);
			if (!array_key_exists($cacheKey, $costCache)) {
				$costCache[$cacheKey] = $productPriceResolver->defineBuyPrice($movementPrice, 0, $productId);
			}

			$unitCost = (float) $costCache[$cacheKey];
			if ($unitCost <= 0) {
				$rows[$projectId]['stock_missing_cost_count']++;
				continue;
			}

			$movementValue = -((float) $obj->value) * $unitCost;
			$rows[$projectId]['shipments_valued'] = (float) price2num(((float) $rows[$projectId]['shipments_valued']) + $movementValue, 'MT');
		}
		$this->db->free($resql);

		return true;
	}

	/**
	 * @param list<UnfinishedProjectReportRow> $rows Rows to sort
	 * @param string $sortfield Logical sort field
	 * @param string $sortorder ASC or DESC
	 * @return void
	 */
	private function sortRows(&$rows, $sortfield, $sortorder)
	{
		$allowedFields = array(
			'entity', 'project_ref', 'project_title', 'thirdparty_name', 'orders_ht', 'deposits_ht',
			'invoices_ht', 'remaining_ht', 'supplier_invoices_ht', 'expenses_paid_ht',
			'miscellaneous_purchases', 'time_spent_ht', 'shipments_valued',
		);
		if (!in_array($sortfield, $allowedFields, true)) {
			$sortfield = 'remaining_ht';
		}
		$direction = strtoupper($sortorder) === 'ASC' ? 1 : -1;

		usort($rows, static function ($left, $right) use ($sortfield, $direction) {
			$leftValue = $left[$sortfield];
			$rightValue = $right[$sortfield];
			if (is_string($leftValue) || is_string($rightValue)) {
				$comparison = strnatcasecmp((string) $leftValue, (string) $rightValue);
			} else {
				$comparison = ((float) $leftValue <=> (float) $rightValue);
			}
			if ($comparison === 0 && $sortfield !== 'project_ref') {
				$comparison = strnatcasecmp($left['project_ref'], $right['project_ref']);
			}

			return $comparison * $direction;
		});
	}

	/**
	 * @param string $entityList Comma-separated entity ids
	 * @return list<int>
	 */
	private function parseEntityList($entityList)
	{
		$entityIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $entityList)), static function ($value) {
			return $value > 0;
		})));

		return $entityIds;
	}
}
