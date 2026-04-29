<?php
/* Copyright (C) 2026	Pierre Ardoin	<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

class jpsun_graph_puissancecrete_totaleannee extends ModeleBoxes
{
	public $boxcode = 'jpsun_pc_total_year';
	public $boximg = 'object_project';
	public $boxlabel = 'JpsunWidgetPuissanceCreteTotalTitle';
	public $depends = array('propal');
	public $version = 'dolibarr';

	public function loadBox($max = 5)
	{
		global $db, $langs;

		$langs->loadLangs(array('jpsun@jpsun'));
		$yearCurrent = (int) dol_print_date(dol_now(), '%Y');
		$totalCurrentYear = $this->fetchTotalYear($db, $yearCurrent);

		$this->info_box_head = array('text' => $langs->trans('JpsunWidgetPuissanceCreteTotalTitle'), 'limit' => 0);
		$this->info_box_contents[0][0] = array('td' => 'class="right"', 'text' => '<span class="amount">'.price($totalCurrentYear, 0, '', 1, -1, -1, 'auto').'</span> kWc');
	}

	private function fetchTotalYear($db, $year)
	{
		if (!$this->hasPowerColumn($db)) {
			return 0.0;
		}

		$entitySql = getEntity('propal');
		$sql = "SELECT SUM(COALESCE(pef.options_jpsun_pc_install, 0)) as total";
		$sql .= " FROM ".MAIN_DB_PREFIX."propal as p";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."propal_extrafields as pef ON pef.fk_object = p.rowid";
		$sql .= " WHERE p.fk_statut = 4";
		$sql .= " AND p.entity IN (".$entitySql.")";
		$sql .= " AND p.date_cloture IS NOT NULL";
		$sql .= " AND p.date_cloture >= '".$db->idate(dol_get_first_day($year, 1, false))."'";
		$sql .= " AND p.date_cloture <= '".$db->idate(dol_get_last_day($year, 12, false))."'";

		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			return (float) ($obj->total ?? 0);
		}
		return 0.0;
	}

	private function hasPowerColumn($db)
	{
		static $hasColumn = null;
		if ($hasColumn !== null) {
			return $hasColumn;
		}

		$sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."propal_extrafields LIKE 'options_jpsun_pc_install'";
		$resql = $db->query($sql);
		$hasColumn = ($resql && $db->num_rows($resql) > 0);

		return $hasColumn;
	}
}
