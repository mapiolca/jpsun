<?php
/* Copyright (C) 2026	Pierre Ardoin	<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

class box_jpsun_pc_install extends ModeleBoxes
{
	public $boxcode = 'jpsun_pc_install';
	public $boximg = 'object_project';
	public $boxlabel = 'JpsunWidgetPuissanceCrete';
	public $depends = array('commande');
	public $version = 'dolibarr';
	public $hidden = false;

	public function loadBox($max = 5)
	{
		global $db, $langs;

		$langs->loadLangs(array('jpsun@jpsun'));

		$yearCurrent = (int) dol_print_date(dol_now(), '%Y');
		$yearPrevious = $yearCurrent - 1;

		$monthlyCurrent = $this->fetchByPeriod('month', $yearCurrent);
		$monthlyPrevious = $this->fetchByPeriod('month', $yearPrevious);
		$weeklyCurrent = $this->fetchByPeriod('week', $yearCurrent);
		$weeklyPrevious = $this->fetchByPeriod('week', $yearPrevious);

		$totalCurrentYear = array_sum($monthlyCurrent);

		$this->info_box_head = array(
			'text' => $langs->trans('JpsunWidgetPuissanceCreteTitle').' '.img_picto($langs->trans('JpsunWidgetPuissanceCreteInfo'), 'info', 'class="opacitymedium"'),
			'limit' => 0
		);

		$content = '';
		$content .= '<div class="opacitymedium">'.$langs->trans('JpsunWidgetPuissanceCreteYearTotal', $yearCurrent, price($totalCurrentYear, 0, '', 1, -1, -1, 'auto')).'</div>';
		$content .= $this->renderSimpleGraph($monthlyCurrent, $monthlyPrevious, $yearCurrent, $yearPrevious, $langs->trans('JpsunWidgetMensuel'));
		$content .= $this->renderSimpleGraph($weeklyCurrent, $weeklyPrevious, $yearCurrent, $yearPrevious, $langs->trans('JpsunWidgetHebdomadaire'));

		$this->info_box_contents[0][0] = array('td' => 'class="tdoverflowmax200"', 'text' => $content, 'asis' => 1);
	}

	private function fetchByPeriod($period, $year)
	{
		global $db;

		$result = array();
		$maxIndex = ($period === 'month') ? 12 : 53;
		for ($i = 1; $i <= $maxIndex; $i++) {
			$result[$i] = 0.0;
		}

		$entitySql = getEntity('commande');
		$indexExpression = ($period === 'month') ? "MONTH(COALESCE(p.date_livraison, p.date_cloture))" : "WEEK(COALESCE(p.date_livraison, p.date_cloture), 3)";

		$sql = "SELECT ".$indexExpression." as idx, SUM(COALESCE(pef.jpsun_pc_install, 0)) as total";
		$sql .= " FROM ".MAIN_DB_PREFIX."commande as p";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commande_extrafields as pef ON pef.fk_object = p.rowid";
		$sql .= " WHERE p.fk_statut > 0";
		$sql .= " AND p.entity IN (".$entitySql.")";
		$sql .= " AND COALESCE(p.date_livraison, p.date_cloture) IS NOT NULL";
		$sql .= " AND COALESCE(p.date_livraison, p.date_cloture) >= '".$db->idate(dol_get_first_day($year, 1, false))."'";
		$sql .= " AND COALESCE(p.date_livraison, p.date_cloture) <= '".$db->idate(dol_get_last_day($year, 12, false))."'";
		$sql .= " GROUP BY idx";

		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$idx = (int) $obj->idx;
				if ($period === 'week' && $idx === 0) {
					$idx = 53;
				}
				if ($idx >= 1 && $idx <= $maxIndex) {
					$result[$idx] = (float) $obj->total;
				}
			}
		}

		return $result;
	}

	private function renderSimpleGraph($current, $previous, $currentYear, $previousYear, $title)
	{
		$values = array_merge(array_values($current), array_values($previous));
		$max = max(1, (float) max($values));
		$pointsCurrent = $this->buildPoints($current, $max);
		$pointsPrevious = $this->buildPoints($previous, $max);

		$html = '<div style="margin-top: 8px;">';
		$html .= '<strong>'.dol_escape_htmltag($title).'</strong>';
		$html .= ' <span class="opacitymedium">('.$currentYear.' / '.$previousYear.')</span>';
		$html .= '<svg viewBox="0 0 320 90" width="100%" height="100" style="display:block;border:1px solid #ddd;background:#fff;margin-top:4px;">';
		$html .= '<polyline fill="none" stroke="#0074d9" stroke-width="2" points="'.$pointsCurrent.'"></polyline>';
		$html .= '<polyline fill="none" stroke="#ff851b" stroke-width="2" points="'.$pointsPrevious.'"></polyline>';
		$html .= '</svg>';
		$html .= '</div>';

		return $html;
	}

	private function buildPoints($series, $max)
	{
		$points = array();
		$count = count($series);
		$stepX = ($count > 1) ? 300 / ($count - 1) : 300;
		$i = 0;
		foreach ($series as $value) {
			$x = 10 + ($i * $stepX);
			$y = 80 - (($value / $max) * 70);
			$points[] = round($x, 2).','.round($y, 2);
			$i++;
		}

		return implode(' ', $points);
	}
}
