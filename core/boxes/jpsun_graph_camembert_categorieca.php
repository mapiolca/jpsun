<?php
/* Copyright (C) 2026	Pierre Ardoin	<developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

class jpsun_graph_camembert_categorieca extends ModeleBoxes
{
	public $boxcode = 'jpsun_ca_categorie_pie';
	public $boximg = 'chart';
	public $boxlabel = 'JpsunWidgetCamembertCategorieCaTitle';
	public $depends = array('invoice');
	public $lang = 'jpsun@jpsun';
	public $widgettype = 'graph';

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

		$text = $langs->trans('JpsunWidgetCamembertCategorieCaTitle');
		$this->info_box_head = array('text' => $text, 'limit' => dol_strlen($text));

		$data = $this->fetchRevenueByCategory($this->db);
		$total = 0.0;
		$dataseries = array();
		foreach ($data as $label => $amount) {
			$total += (float) $amount;
			$dataseries[] = array('label' => $label, 'data' => (float) $amount);
		}

		$stringtoprint = '<div class="div-table-responsive-no-min">';
		if ($total <= 0 || empty($dataseries)) {
			$this->info_box_contents[0][0] = array('td' => 'class="center opacitymedium"', 'text' => '<span class="opacitymedium">'.$langs->trans('JpsunWidgetNoData').'</span>');
			return;
		}

		include_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
		$px1 = new DolGraph();
		$mesg = $px1->isGraphKo();
		if (!$mesg) {
			$graphdata = array();
			$legend = array();
			foreach ($dataseries as $value) {
				$graphdata[] = array($value['label'], $value['data']);
				$legend[] = $value['label'];
			}
			$px1->SetData($graphdata);
			$px1->SetType(array('pie'));
				$px1->SetLegend($legend);
				$px1->setShowLegend(2);
				$px1->SetHeight(!empty($conf->dol_optimize_smallscreen) ? 200 : 240);
				if (!empty($conf->dol_optimize_smallscreen)) $px1->SetWidth(320);
			$px1->SetCssPrefix('cssboxes');
			$px1->mode = 'depth';
			$px1->draw('idgraphjpsuncacatpie');
			$stringtoprint .= '<div class="center" style="font-size:20px;font-weight:700;margin-bottom:8px;">'.$langs->trans('AmountHT').': '.price($total).'</div>';
			$stringtoprint .= $px1->show($total ? 0 : 1);
		}
		$stringtoprint .= '</div>';

		$this->info_box_contents = array();
		$this->info_box_contents[][] = array('td' => 'class="center"', 'text' => $stringtoprint, 'asis' => 1);
	}

	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}

	private function fetchRevenueByCategory($db)
	{
		$result = array();
		if (!$this->hasCategoryColumn($db)) return $result;
		$categoryLabels = $this->getCategoryLabels($db);
		$sql = "SELECT COALESCE(NULLIF(fe.jpsuntagcategory, ''), 'Sans catégorie') as category, SUM(f.total_ht) as total";
		$sql .= " FROM ".MAIN_DB_PREFIX."facture as f";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields as fe ON fe.fk_object = f.rowid";
		$sql .= " WHERE f.entity IN (".getEntity('invoice').")";
		$sql .= " AND f.fk_statut > 0";
		$sql .= " GROUP BY category";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$categoryKey = (string) $obj->category;
				$categoryLabel = isset($categoryLabels[$categoryKey]) ? $categoryLabels[$categoryKey] : $categoryKey;
				$result[$categoryLabel] = (float) $obj->total;
			}
		}
		return $result;
	}

	private function getCategoryLabels($db)
	{
		global $langs;
		$labels = array();
		$sql = "SELECT param FROM ".MAIN_DB_PREFIX."extrafields WHERE name = 'jpsuntagcategory' AND elementtype = 'facture' ORDER BY rowid DESC LIMIT 1";
		$resql = $db->query($sql);
		if (!$resql) return $labels;
		$obj = $db->fetch_object($resql);
		if (empty($obj->param)) return $labels;
		$params = @unserialize($obj->param);
		if (empty($params['options']) || !is_array($params['options'])) return $labels;
		foreach ($params['options'] as $key => $value) $labels[(string) $key] = $langs->trans((string) $value);
		return $labels;
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
