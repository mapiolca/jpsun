<?php
/* Copyright (C) 2026	Pierre Ardoin	<developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

class jpsun_graph_puissancecrete_totaleannee extends ModeleBoxes
{
	public $boxcode = 'jpsun_pc_total_year';
	public $boximg = 'chart';
	public $boxlabel = 'JpsunWidgetPuissanceCreteTotalTitle';
	public $depends = array('commande');
	public $lang = 'jpsun@jpsun';

	public function __construct($db, $param = '')
	{
		parent::__construct($db, $param);
		$this->db = $db;
		$this->param = $param;
	}

	public function loadBox($max = 5)
	{
		global $langs;
		$langs->loadLangs(array('jpsun@jpsun'));
		$yearCurrent = (int) dol_print_date(dol_now(), '%Y');
		$totalCurrentYear = $this->fetchTotalYear($this->db, $yearCurrent);
		$this->info_box_head = array('text' => $langs->trans('JpsunWidgetPuissanceCreteTotalTitle'), 'limit' => 0);
		$this->info_box_contents = array();
		$valueText = dol_escape_htmltag(rtrim(rtrim(sprintf('%.2f', $totalCurrentYear), '0'), '.'));
		$this->info_box_contents[] = array(
			0 => array(
				'td' => 'class="center"',
				'asis' => 1,
				'text' => '<div style="height:100px;display:flex;align-items:center;justify-content:center;font-size:42px;font-weight:700;">'.$valueText.' kWc</div>'
			)
		);
	}

	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}

	private function fetchTotalYear($db, $year)
	{
		if (!$this->hasPowerColumn($db)) return 0.0;
		$entitySql = getEntity('commande');
		$sql = "SELECT SUM(COALESCE(pef.jpsun_pc_install, 0)) as total FROM ".MAIN_DB_PREFIX."commande as p LEFT JOIN ".MAIN_DB_PREFIX."commande_extrafields as pef ON pef.fk_object = p.rowid WHERE p.fk_statut > 0 AND p.entity IN (".$entitySql.") AND p.date_cloture IS NOT NULL AND p.date_cloture >= '".$db->idate(dol_get_first_day($year, 1, false))."' AND p.date_cloture <= '".$db->idate(dol_get_last_day($year, 12, false))."'";
		$resql = $db->query($sql);
		if ($resql) { $obj = $db->fetch_object($resql); return (float) ($obj->total ?? 0); }
		return 0.0;
	}

	private function hasPowerColumn($db)
	{
		static $hasColumn = null;
		if ($hasColumn !== null) return $hasColumn;
		$sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."commande_extrafields LIKE 'jpsun_pc_install'";
		$resql = $db->query($sql);
		$hasColumn = ($resql && $db->num_rows($resql) > 0);
		return $hasColumn;
	}
}
