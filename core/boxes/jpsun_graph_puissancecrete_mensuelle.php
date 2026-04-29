<?php
/* Copyright (C) 2026	Pierre Ardoin	<developpeur@lesmetiersdubatiment.fr> */
include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
class box_jpsun_graph_puissancecrete_mensuelle extends ModeleBoxes
{
	public $boxcode = 'jpsun_pc_monthly';
	public $boximg = 'object_project';
	public $boxlabel = 'JpsunWidgetPuissanceCreteMensuelTitle';
	public $depends = array('propal');
	public $version = 'dolibarr';
	public function loadBox($max = 5)
	{
		global $db, $langs;
		$langs->loadLangs(array('jpsun@jpsun'));
		$y = (int) dol_print_date(dol_now(), '%Y');
		$this->info_box_head = array('text' => $langs->trans('JpsunWidgetPuissanceCreteMensuelTitle'), 'limit' => 0);
		$this->info_box_contents[0][0] = array('text' => $this->renderGraph($this->fetchByMonth($db, $y), $this->fetchByMonth($db, $y - 1), $y, $y - 1), 'asis' => 1);
	}
	private function fetchByMonth($db, $year)
	{
		$result = array_fill(1, 12, 0.0);
		if (!$this->hasPowerColumn($db)) {
			return $result;
		}
		$sql = "SELECT MONTH(p.date_cloture) as idx, SUM(COALESCE(pef.jpsun_pc_install,0)) as total FROM ".MAIN_DB_PREFIX."propal p LEFT JOIN ".MAIN_DB_PREFIX."propal_extrafields pef ON pef.fk_object=p.rowid WHERE p.fk_statut=4 AND p.entity IN (".getEntity('propal').") AND p.date_cloture IS NOT NULL AND p.date_cloture >= '".$db->idate(dol_get_first_day($year,1,false))."' AND p.date_cloture <= '".$db->idate(dol_get_last_day($year,12,false))."' GROUP BY idx";
		$resql = $db->query($sql);
		if ($resql) while ($o = $db->fetch_object($resql)) $result[(int) $o->idx] = (float) $o->total;
		return $result;
	}
	private function renderGraph($a, $b, $y1, $y0)
	{
		$max = max(1, max(array_merge(array_values($a), array_values($b))));
		return '<div class="opacitymedium">'.$y1.' / '.$y0.'</div><svg viewBox="0 0 320 90" width="100%" height="100" style="display:block;border:1px solid #ddd;background:#fff;"><polyline fill="none" stroke="#0074d9" stroke-width="2" points="'.$this->pts($a,$max).'"></polyline><polyline fill="none" stroke="#ff851b" stroke-width="2" points="'.$this->pts($b,$max).'"></polyline></svg>';
	}
	private function pts($s, $m) { $p=array(); $c=count($s); $i=0; foreach($s as $v){ $x=10+($i*(300/($c-1))); $y=80-(($v/$m)*70); $p[]=round($x,2).','.round($y,2); $i++; } return implode(' ',$p);} 

	private function hasPowerColumn($db)
	{
		static $hasColumn = null;
		if ($hasColumn !== null) {
			return $hasColumn;
		}

		$sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."propal_extrafields LIKE 'jpsun_pc_install'";
		$resql = $db->query($sql);
		$hasColumn = ($resql && $db->num_rows($resql) > 0);

		return $hasColumn;
	}
}
