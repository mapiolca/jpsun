<?php
/* Copyright (C) 2026	Pierre Ardoin	<developpeur@lesmetiersdubatiment.fr> */
include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
class box_jpsun_pc_weekly extends ModeleBoxes
{
	public $boxcode = 'jpsun_pc_weekly';
	public $boximg = 'object_project';
	public $boxlabel = 'JpsunWidgetPuissanceCreteHebdoTitle';
	public $depends = array('propal');
	public $version = 'dolibarr';
	public function loadBox($max = 5)
	{
		global $db, $langs;
		$langs->loadLangs(array('jpsun@jpsun'));
		$y = (int) dol_print_date(dol_now(), '%Y');
		$this->info_box_head = array('text' => $langs->trans('JpsunWidgetPuissanceCreteHebdoTitle'), 'limit' => 0);
		$this->info_box_contents[0][0] = array('text' => $this->renderGraph($this->fetchByWeek($db, $y), $this->fetchByWeek($db, $y - 1), $y, $y - 1), 'asis' => 1);
	}
	private function fetchByWeek($db, $year)
	{
		$result = array_fill(1, 53, 0.0);
		$sql = "SELECT WEEK(p.date_cloture, 3) as idx, SUM(COALESCE(pef.options_jpsun_pc_install,0)) as total FROM ".MAIN_DB_PREFIX."propal p LEFT JOIN ".MAIN_DB_PREFIX."propal_extrafields pef ON pef.fk_object=p.rowid WHERE p.fk_statut=4 AND p.entity IN (".getEntity('propal').") AND p.date_cloture IS NOT NULL AND p.date_cloture >= '".$db->idate(dol_get_first_day($year,1,false))."' AND p.date_cloture <= '".$db->idate(dol_get_last_day($year,12,false))."' GROUP BY idx";
		$resql = $db->query($sql);
		if ($resql) while ($o = $db->fetch_object($resql)) { $i=(int)$o->idx; if($i===0)$i=53; $result[$i]=(float)$o->total; }
		return $result;
	}
	private function renderGraph($a, $b, $y1, $y0)
	{
		$max = max(1, max(array_merge(array_values($a), array_values($b))));
		return '<div class="opacitymedium">'.$y1.' / '.$y0.'</div><svg viewBox="0 0 320 90" width="100%" height="100" style="display:block;border:1px solid #ddd;background:#fff;"><polyline fill="none" stroke="#0074d9" stroke-width="2" points="'.$this->pts($a,$max).'"></polyline><polyline fill="none" stroke="#ff851b" stroke-width="2" points="'.$this->pts($b,$max).'"></polyline></svg>';
	}
	private function pts($s, $m) { $p=array(); $c=count($s); $i=0; foreach($s as $v){ $x=10+($i*(300/($c-1))); $y=80-(($v/$m)*70); $p[]=round($x,2).','.round($y,2); $i++; } return implode(' ',$p);} 
}
