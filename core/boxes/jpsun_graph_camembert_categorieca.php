<?php
/* Copyright (C) 2026	Pierre Ardoin	<developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';

class jpsun_graph_camembert_categorieca extends ModeleBoxes
{
	public $boxcode = 'jpsun_ca_categorie_pie';
	public $boximg = 'chart';
	public $boxlabel = 'JpsunWidgetCamembertCategorieCaTitle';
	public $depends = array('invoice');
	public $lang = 'jpsun@jpsun';

	public function __construct($db, $param = '')
	{
		parent::__construct($db, $param);
		$this->db = $db;
		$this->param = $param;
	}

	public function loadBox($max = 20)
	{
		global $conf, $langs;
		$langs->loadLangs(array('jpsun@jpsun', 'bills'));
		$this->info_box_head = array('text' => $langs->trans('JpsunWidgetCamembertCategorieCaTitle'), 'limit' => 0);

		$data = $this->fetchRevenueByCategory($this->db);
		$total = 0.0;
		$graphData = array();
		foreach ($data as $label => $amount) {
			$total += (float) $amount;
			$graphData[] = array($label, (float) $amount);
		}

		$contentHtml = '';
		if ($total <= 0) {
			$contentHtml = '<div class="center opacitymedium">'.$langs->trans('JpsunWidgetNoData').'</div>';
		} else {
			$graph = new DolGraph();
			$graph->SetData($graphData);
			$graph->SetType(array('pie'));
			$graph->setHeight(!empty($conf->dol_optimize_smallscreen) ? '220' : '280');
			$graph->setWidth(!empty($conf->dol_optimize_smallscreen) ? '320' : '680');
			$graph->setShowLegend(1);
			$graphId = 'jpsuncacatpie_e'.((int) $conf->entity);
			$graph->draw($graphId);
			$contentHtml = '<div class="center">'.$graph->show(0).'</div>';
		}

		$this->info_box_contents = array();
		$this->info_box_contents[] = array(0 => array('td' => 'class="center"', 'asis' => 1, 'text' => $contentHtml));
	}

	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}

	private function fetchRevenueByCategory($db)
	{
		$result = array();
		if (!$this->hasCategoryColumn($db)) return $result;
		$sql = "SELECT COALESCE(NULLIF(fe.jpsuntagcategory, ''), 'Sans catégorie') as category, SUM(f.total_ht) as total";
		$sql .= " FROM ".MAIN_DB_PREFIX."facture as f";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields as fe ON fe.fk_object = f.rowid";
		$sql .= " WHERE f.entity IN (".getEntity('invoice').")";
		$sql .= " AND f.fk_statut > 0";
		$sql .= " GROUP BY category";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$result[(string) $obj->category] = (float) $obj->total;
			}
		}
		return $result;
	}

	private function hasCategoryColumn($db)
	{
		static $hasColumn = null;
		if ($hasColumn !== null) return $hasColumn;
		$sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."facture_extrafields LIKE 'jpsuntagcategory'";
		$resql = $db->query($sql);
		$hasColumn = ($resql && $db->num_rows($resql) > 0);
		return $hasColumn;
	}
}
