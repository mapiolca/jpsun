<?php
/* Copyright (C) 2026	Pierre Ardoin	<developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';

class jpsun_graph_puissancecrete_mensuelle extends ModeleBoxes
{
	public $boxcode = 'jpsun_pc_monthly';
	public $boximg = 'chart';
	public $boxlabel = 'JpsunWidgetPuissanceCreteMensuelTitle';
	public $depends = array('propal');
	public $lang = 'jpsun@jpsun';

	public function __construct($db, $param = '')
	{
		parent::__construct($db, $param);
		$this->db = $db;
		$this->param = $param;
	}

	public function loadBox($max = 5)
	{
		global $conf, $langs;
		$langs->loadLangs(array('jpsun@jpsun'));
		$y = (int) dol_print_date(dol_now(), '%Y');
		$dataCurrent = $this->fetchByMonth($this->db, $y);
		$dataPrevious = $this->fetchByMonth($this->db, $y - 1);
		$this->info_box_head = array('text' => $langs->trans('JpsunWidgetPuissanceCreteMensuelTitle'), 'limit' => 0);
		$graphData = array();
		$total = 0.0;
		for ($m = 1; $m <= 12; $m++) {
			$v1 = isset($dataCurrent[$m]) ? (float) $dataCurrent[$m] : 0.0;
			$v0 = isset($dataPrevious[$m]) ? (float) $dataPrevious[$m] : 0.0;
			$total += $v1 + $v0;
			$monthLabel = dol_print_date(dol_mktime(0, 0, 0, $m, 1, 2000), '%b');
			$graphData[] = array($monthLabel, $v1, $v0);
		}
		$contentHtml = '';
		if ($total <= 0) {
			$contentHtml = '<div class="center opacitymedium">'.$langs->trans('JpsunWidgetNoData').'</div>';
		} else {
			$graph = new DolGraph();
			$graph->SetData($graphData);
			$graph->SetLegend(array((string) $y.' (kWc)', (string) ($y - 1).' (kWc)'));
			$graph->SetDataColor(array('#2e78c2', '#a3a3a3'));
			$graph->SetType(array('lines'));
			$graph->setHeight(!empty($conf->dol_optimize_smallscreen) ? '220' : '260');
			$graph->setWidth(!empty($conf->dol_optimize_smallscreen) ? '320' : '680');
			$graph->setShowLegend(1);
			$graph->setMinValue(0);
			$graphId = 'jpsunpcmonthly_e'.((int) $conf->entity);
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

	private function fetchByMonth($db, $year)
	{
		$result = array_fill(1, 12, 0.0);
		if (!$this->hasPowerColumn($db)) return $result;
		$sql = "SELECT MONTH(p.date_cloture) as idx, SUM(COALESCE(pef.jpsun_pc_install,0)) as total FROM ".MAIN_DB_PREFIX."propal p LEFT JOIN ".MAIN_DB_PREFIX."propal_extrafields pef ON pef.fk_object=p.rowid WHERE p.fk_statut=4 AND p.entity IN (".getEntity('propal').") AND p.date_cloture IS NOT NULL AND p.date_cloture >= '".$db->idate(dol_get_first_day($year,1,false))."' AND p.date_cloture <= '".$db->idate(dol_get_last_day($year,12,false))."' GROUP BY idx";
		$resql = $db->query($sql);
		if ($resql) while ($o = $db->fetch_object($resql)) $result[(int) $o->idx] = (float) $o->total;
		return $result;
	}

	private function hasPowerColumn($db)
	{
		static $hasColumn = null;
		if ($hasColumn !== null) return $hasColumn;
		$sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."propal_extrafields LIKE 'jpsun_pc_install'";
		$resql = $db->query($sql);
		$hasColumn = ($resql && $db->num_rows($resql) > 0);
		return $hasColumn;
	}
}
