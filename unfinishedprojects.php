<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file unfinishedprojects.php
 * \ingroup jpsun
 * \brief Accounting report for open projects that are not fully invoiced.
 */

require '../../main.inc.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/jpsun/class/jpsununfinishedprojectreport.class.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */
/** @var HookManager $hookmanager */

$langs->loadLangs(array('jpsun@jpsun', 'projects', 'companies', 'bills', 'orders', 'stocks', 'trips'));

if (!isModEnabled('jpsun')) {
	accessforbidden();
}
if (!isModEnabled('project') || !isModEnabled('order') || !isModEnabled('invoice')) {
	accessforbidden($langs->trans('JpsunUnfinishedProjectsMissingRequiredModules'));
}
if (!JpsunUnfinishedProjectReport::canAccess($user)) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$output = GETPOST('output', 'aZ09');
$contextpage = 'jpsununfinishedprojects';
$pageUrl = dol_buildpath('/jpsun/unfinishedprojects.php', 1);
$sortfield = GETPOST('sortfield', 'aZ09');
$sortorder = strtoupper(GETPOST('sortorder', 'alpha'));
$page = GETPOSTINT('page');
$limit = GETPOSTINT('limit');
$searchProjectRef = GETPOST('search_project_ref', 'alphanohtml');
$searchProjectTitle = GETPOST('search_project_title', 'alphanohtml');
$searchThirdparty = GETPOST('search_thirdparty', 'alphanohtml');
$searchEntities = array_values(array_unique(array_filter(array_map('intval', (array) GETPOST('search_entities', 'array')), static function ($value) {
	return $value > 0;
})));

if ($sortfield === '') {
	$sortfield = 'remaining_ht';
}
if ($sortorder !== 'ASC' && $sortorder !== 'DESC') {
	$sortorder = 'DESC';
}
if ($page < 0) {
	$page = 0;
}
if ($limit <= 0) {
	$limit = getDolGlobalInt('MAIN_SIZE_LISTE_LENGTH', 25);
}

if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$searchProjectRef = '';
	$searchProjectTitle = '';
	$searchThirdparty = '';
	$searchEntities = array();
	$page = 0;
}
if (GETPOST('button_search_x', 'alpha') || GETPOST('button_search', 'alpha')) {
	$page = 0;
}

$report = new JpsunUnfinishedProjectReport($db);
$object = $report;
$entityOptions = $report->getAccessibleEntityLabels();
$showEntityColumn = count($entityOptions) > 1;
$entityFilterOptions = array();
foreach ($entityOptions as $entityId => $entityLabel) {
	$entityFilterOptions[$entityId] = array(
		'id' => $entityId,
		'label' => $entityLabel,
		'labelhtml' => '<div class="refidno multicompany-entity-card-container"><span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entityLabel).'</span></div>',
	);
}

$arrayfields = array(
	'entity' => array('label' => $langs->trans('JpsunUnfinishedProjectsEnvironment'), 'checked' => $showEntityColumn ? 1 : 0, 'enabled' => $showEntityColumn ? 1 : 0, 'position' => 10),
	'project_ref' => array('label' => $langs->trans('Project'), 'checked' => 1, 'position' => 20),
	'project_title' => array('label' => $langs->trans('Label'), 'checked' => 1, 'position' => 30),
	'thirdparty_name' => array('label' => $langs->trans('ThirdParty'), 'checked' => 1, 'position' => 40),
	'orders_ht' => array('label' => $langs->trans('JpsunUnfinishedProjectsOrdersHT'), 'checked' => 1, 'position' => 50),
	'deposits_ht' => array('label' => $langs->trans('JpsunUnfinishedProjectsDepositsHT'), 'checked' => 1, 'position' => 60),
	'invoices_ht' => array('label' => $langs->trans('JpsunUnfinishedProjectsInvoicesHT'), 'checked' => 1, 'position' => 70),
	'remaining_ht' => array('label' => $langs->trans('JpsunUnfinishedProjectsRemainingHT'), 'checked' => 1, 'position' => 80),
	'supplier_invoices_ht' => array('label' => $langs->trans('JpsunUnfinishedProjectsSupplierInvoicesHT'), 'checked' => 1, 'position' => 90),
	'expenses_paid_ht' => array('label' => $langs->trans('JpsunUnfinishedProjectsExpensesPaidHT'), 'checked' => 1, 'position' => 100),
	'miscellaneous_purchases' => array('label' => $langs->trans('JpsunUnfinishedProjectsMiscellaneousPurchases'), 'checked' => 1, 'position' => 110),
	'time_spent_ht' => array('label' => $langs->trans('JpsunUnfinishedProjectsTimeSpentValued'), 'checked' => 1, 'position' => 120),
	'shipments_valued' => array('label' => $langs->trans('JpsunUnfinishedProjectsShipmentsValued'), 'checked' => 1, 'position' => 130),
);
$arrayfields = dol_sort_array($arrayfields, 'position');

$hookmanager->initHooks(array('jpsununfinishedprojectslist'));
$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $report, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

$filters = array(
	'project_ref' => $searchProjectRef,
	'project_title' => $searchProjectTitle,
	'thirdparty' => $searchThirdparty,
	'entities' => $searchEntities,
);
$rows = $report->fetchRows($user, $filters, $sortfield, $sortorder);
if ($rows === false) {
	setEventMessages($report->error, $report->errors, 'errors');
	$rows = array();
}

$availableSources = $report->getAvailableSources();
$missingStockCostCount = 0;
$timeWithoutRateCount = 0;
foreach ($rows as $row) {
	$missingStockCostCount += $row['stock_missing_cost_count'];
	$timeWithoutRateCount += $row['time_without_rate_count'];
}

if ($output === 'csv') {
	jpsunUnfinishedProjectsOutputCsv($rows, $availableSources, $entityOptions, $langs);
	exit;
}

foreach ($availableSources as $source => $available) {
	if (!$available) {
		setEventMessages($langs->trans('JpsunUnfinishedProjectsSourceUnavailable', $langs->trans('JpsunUnfinishedProjectsSource_'.$source)), null, 'warnings');
	}
}
if ($missingStockCostCount > 0) {
	setEventMessages($langs->trans('JpsunUnfinishedProjectsMissingStockCosts', $missingStockCostCount), null, 'warnings');
}
if ($timeWithoutRateCount > 0) {
	setEventMessages($langs->trans('JpsunUnfinishedProjectsMissingTimeRates', $timeWithoutRateCount), null, 'warnings');
}

$totalnboflines = count($rows);
$offset = $page * $limit;
if ($offset >= $totalnboflines && $totalnboflines > 0) {
	$page = 0;
	$offset = 0;
}
$pageRowsWithLookahead = array_slice($rows, $offset, $limit + 1);
$numForNavigation = count($pageRowsWithLookahead);
$pageRows = array_slice($pageRowsWithLookahead, 0, $limit);
$num = count($pageRows);

$totals = array();
$amountFields = array('orders_ht', 'deposits_ht', 'invoices_ht', 'remaining_ht', 'supplier_invoices_ht', 'expenses_paid_ht', 'miscellaneous_purchases', 'time_spent_ht', 'shipments_valued');
foreach ($amountFields as $amountField) {
	$totals[$amountField] = null;
}
foreach ($rows as $row) {
	foreach ($amountFields as $amountField) {
		if ($row[$amountField] !== null) {
			if ($totals[$amountField] === null) {
				$totals[$amountField] = 0.0;
			}
			$totals[$amountField] = (float) price2num(((float) $totals[$amountField]) + ((float) $row[$amountField]), 'MT');
		}
	}
}

$param = '&mainmenu=accountancy&leftmenu=jpsun_unfinished_projects';
if ($searchProjectRef !== '') {
	$param .= '&search_project_ref='.urlencode($searchProjectRef);
}
if ($searchProjectTitle !== '') {
	$param .= '&search_project_title='.urlencode($searchProjectTitle);
}
if ($searchThirdparty !== '') {
	$param .= '&search_thirdparty='.urlencode($searchThirdparty);
}
foreach ($searchEntities as $entityId) {
	$param .= '&search_entities[]='.$entityId;
}

$exportUrl = $pageUrl.'?output=csv&sortfield='.urlencode($sortfield).'&sortorder='.urlencode($sortorder).$param;
$morehtmlright = dolGetButtonTitle($langs->trans('Export'), '', 'fa fa-file-csv', $exportUrl, '', 1);

$title = $langs->trans('JpsunUnfinishedProjectsTitle');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-jpsun page-unfinishedprojects');

$form = new Form($db);

print '<form method="POST" id="searchFormList" action="'.dol_escape_htmltag($pageUrl).'">' . "\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<input type="hidden" name="mainmenu" value="accountancy">';
print '<input type="hidden" name="leftmenu" value="jpsun_unfinished_projects">';

// Dolibarr v20 needs one look-ahead row to render the next-page arrow.
print_barre_liste($title, $page, $pageUrl, $param, $sortfield, $sortorder, '', $numForNavigation, $totalnboflines, 'accounting', 0, $morehtmlright, '', $limit, 0, 0, 1);

$htmlofselectarray = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $contextpage, 0);
$selectedfields = $htmlofselectarray;

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste">' . "\n";

print '<tr class="liste_titre_filter">';
foreach ($arrayfields as $key => $field) {
	if (empty($field['checked']) || (isset($field['enabled']) && empty($field['enabled']))) {
		continue;
	}
	print '<td class="liste_titre'.(in_array($key, $amountFields, true) ? ' right' : '').'">';
	if ($key === 'entity') {
		print Form::multiselectarray('search_entities', $entityFilterOptions, $searchEntities, 0, 0, 'minwidth150', 0, 0, '', '', $langs->trans('JpsunUnfinishedProjectsEnvironment'), 2);
	} elseif ($key === 'project_ref') {
		print '<input class="flat maxwidth100" type="text" name="search_project_ref" value="'.dol_escape_htmltag($searchProjectRef).'">';
	} elseif ($key === 'project_title') {
		print '<input class="flat maxwidth150" type="text" name="search_project_title" value="'.dol_escape_htmltag($searchProjectTitle).'">';
	} elseif ($key === 'thirdparty_name') {
		print '<input class="flat maxwidth150" type="text" name="search_thirdparty" value="'.dol_escape_htmltag($searchThirdparty).'">';
	}
	print '</td>';
}
$parameters = array('arrayfields' => $arrayfields, 'filters' => $filters, 'param' => $param, 'sortfield' => $sortfield, 'sortorder' => $sortorder);
$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
print $hookmanager->resPrint;
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td>';
print '</tr>' . "\n";

print '<tr class="liste_titre">';
foreach ($arrayfields as $key => $field) {
	if (empty($field['checked']) || (isset($field['enabled']) && empty($field['enabled']))) {
		continue;
	}
	$label = $field['label'];
	if ($key === 'invoices_ht') {
		$label = $form->textwithpicto($label, $langs->trans('JpsunUnfinishedProjectsInvoicesIncludeDeposits'));
	} elseif ($key === 'miscellaneous_purchases') {
		$label = $form->textwithpicto($label, $langs->trans('JpsunUnfinishedProjectsMiscellaneousPurchasesHelp'));
	} elseif ($key === 'shipments_valued') {
		$label = $form->textwithpicto($label, $langs->trans('JpsunUnfinishedProjectsShipmentsValuedHelp'));
	}
	print_liste_field_titre($label, $pageUrl, $key, '', $param, '', $sortfield, $sortorder, in_array($key, $amountFields, true) ? 'right ' : 'left ');
}
$parameters = array('arrayfields' => $arrayfields, 'param' => $param, 'sortfield' => $sortfield, 'sortorder' => $sortorder);
$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
print $hookmanager->resPrint;
$hookFieldCount = !empty($hookmanager->resArray['nbfields']) ? (int) $hookmanager->resArray['nbfields'] : 0;
print getTitleFieldOfList($selectedfields, 0, $pageUrl, '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
print '</tr>' . "\n";

$projectStatic = new Project($db);
$thirdpartyStatic = new Societe($db);
foreach ($pageRows as $row) {
	print '<tr class="oddeven">';
	foreach ($arrayfields as $key => $field) {
		if (empty($field['checked']) || (isset($field['enabled']) && empty($field['enabled']))) {
			continue;
		}
		if ($key === 'entity') {
			$entityLabel = isset($entityOptions[$row['entity']]) ? $entityOptions[$row['entity']] : (string) $row['entity'];
			print '<td class="center"><div class="refidno multicompany-entity-card-container"><span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entityLabel).'</span></div></td>';
		} elseif ($key === 'project_ref') {
			$projectStatic->id = $row['project_id'];
			$projectStatic->ref = $row['project_ref'];
			$projectStatic->title = $row['project_title'];
			$projectStatic->entity = $row['entity'];
			$projectStatic->public = $row['project_public'];
			$projectStatic->status = Project::STATUS_VALIDATED;
			$projectStatic->statut = Project::STATUS_VALIDATED;
			print '<td>'.$projectStatic->getNomUrl(1).'</td>';
		} elseif ($key === 'project_title') {
			print '<td>'.dol_escape_htmltag($row['project_title']).'</td>';
		} elseif ($key === 'thirdparty_name') {
			print '<td>';
			if ($row['thirdparty_id'] > 0 && $row['thirdparty_name'] !== '') {
				$thirdpartyStatic->id = $row['thirdparty_id'];
				$thirdpartyStatic->name = $row['thirdparty_name'];
				$thirdpartyStatic->name_alias = $row['thirdparty_name_alias'];
				print $thirdpartyStatic->getNomUrl(1);
			} else {
				print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
			}
			print '</td>';
		} elseif (in_array($key, $amountFields, true)) {
			print '<td class="right nowrap">';
			$value = $row[$key];
			if ($value === null) {
				print '<span class="opacitymedium">'.$form->textwithpicto($langs->trans('JpsunUnfinishedProjectsNotAvailable'), $langs->trans('JpsunUnfinishedProjectsSourceUnavailableShort')).'</span>';
			} else {
				print '<span class="amount">'.price($value, 0, $langs, 1, -1, -1, $conf->currency).'</span>';
				if ($key === 'shipments_valued' && $row['stock_missing_cost_count'] > 0) {
					print ' '.img_warning($langs->trans('JpsunUnfinishedProjectsProjectMissingStockCosts', $row['stock_missing_cost_count']));
				} elseif ($key === 'time_spent_ht' && $row['time_without_rate_count'] > 0) {
					print ' '.img_warning($langs->trans('JpsunUnfinishedProjectsProjectMissingTimeRates', $row['time_without_rate_count']));
				}
			}
			print '</td>';
		}
	}
	$parameters = array('arrayfields' => $arrayfields, 'row' => $row, 'param' => $param, 'sortfield' => $sortfield, 'sortorder' => $sortorder);
	$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters, $object, $action);
	if ($reshook < 0) {
		setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
	}
	print $hookmanager->resPrint;
	print '<td class="center"></td>';
	print '</tr>' . "\n";
}

if ($num === 0) {
	$visibleFieldCount = 0;
	foreach ($arrayfields as $field) {
		if (!empty($field['checked']) && (!isset($field['enabled']) || !empty($field['enabled']))) {
			$visibleFieldCount++;
		}
	}
	print '<tr class="oddeven"><td colspan="'.($visibleFieldCount + $hookFieldCount + 1).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
} else {
	$totalLabelPrinted = false;
	print '<tr class="liste_total">';
	foreach ($arrayfields as $key => $field) {
		if (empty($field['checked']) || (isset($field['enabled']) && empty($field['enabled']))) {
			continue;
		}
		if (in_array($key, $amountFields, true)) {
			print '<td class="right">'.($totals[$key] === null ? '<span class="opacitymedium">'.$langs->trans('JpsunUnfinishedProjectsNotAvailable').'</span>' : price($totals[$key], 0, $langs, 1, -1, -1, $conf->currency)).'</td>';
		} elseif (!$totalLabelPrinted) {
			print '<td>'.$langs->trans('JpsunUnfinishedProjectsFilteredTotal').'</td>';
			$totalLabelPrinted = true;
		} else {
			print '<td></td>';
		}
	}
	for ($hookFieldIndex = 0; $hookFieldIndex < $hookFieldCount; $hookFieldIndex++) {
		print '<td></td>';
	}
	print '<td></td></tr>' . "\n";
}

print '</table></div>' . "\n";
print '</form>' . "\n";

llxFooter();
$db->close();

/**
 * Stream the full filtered report as UTF-8 CSV.
 *
 * @param list<array<string, int|float|string|null>> $rows Report rows
 * @param array<string, bool> $availableSources Optional source availability
 * @param array<int, string> $entityOptions Accessible entity labels
 * @param Translate $langs Language handler
 * @return void
 */
function jpsunUnfinishedProjectsOutputCsv($rows, $availableSources, $entityOptions, $langs)
{
	$showEntityColumn = count($entityOptions) > 1;
	$separator = getDolGlobalString('EXPORT_CSV_SEPARATOR_TO_USE', ',');
	if (strlen($separator) !== 1) {
		$separator = ',';
	}

	top_httphead('text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="jpsun_unfinished_projects_'.dol_print_date(dol_now(), '%Y%m%d').'.csv"');

	$stream = fopen('php://output', 'wb');
	if ($stream === false) {
		return;
	}
	fwrite($stream, "\xEF\xBB\xBF");

	$headers = array(
		$langs->trans('Project'),
		$langs->trans('Label'),
		$langs->trans('ThirdParty'),
		$langs->trans('JpsunUnfinishedProjectsOrdersHT'),
		$langs->trans('JpsunUnfinishedProjectsDepositsHT'),
		$langs->trans('JpsunUnfinishedProjectsInvoicesHT'),
		$langs->trans('JpsunUnfinishedProjectsRemainingHT'),
		$langs->trans('JpsunUnfinishedProjectsSupplierInvoicesHT'),
		$langs->trans('JpsunUnfinishedProjectsExpensesPaidHT'),
		$langs->trans('JpsunUnfinishedProjectsMiscellaneousPurchases'),
		$langs->trans('JpsunUnfinishedProjectsTimeSpentValued'),
		$langs->trans('JpsunUnfinishedProjectsShipmentsValued'),
		$langs->trans('JpsunUnfinishedProjectsAlerts'),
	);
	if ($showEntityColumn) {
		array_unshift($headers, $langs->trans('JpsunUnfinishedProjectsEnvironment'));
	}
	fputcsv($stream, $headers, $separator, '"', '\\');

	foreach ($rows as $row) {
		$alerts = array();
		if ($row['stock_missing_cost_count'] > 0) {
			$alerts[] = $langs->trans('JpsunUnfinishedProjectsProjectMissingStockCosts', $row['stock_missing_cost_count']);
		}
		if ($row['time_without_rate_count'] > 0) {
			$alerts[] = $langs->trans('JpsunUnfinishedProjectsProjectMissingTimeRates', $row['time_without_rate_count']);
		}
		foreach ($availableSources as $source => $available) {
			if (!$available) {
				$alerts[] = $langs->trans('JpsunUnfinishedProjectsSourceUnavailable', $langs->trans('JpsunUnfinishedProjectsSource_'.$source));
			}
		}

		$csvRow = array(
			(string) $row['project_ref'],
			(string) $row['project_title'],
			(string) $row['thirdparty_name'],
			(string) price2num((float) $row['orders_ht'], 'MT'),
			(string) price2num((float) $row['deposits_ht'], 'MT'),
			(string) price2num((float) $row['invoices_ht'], 'MT'),
			(string) price2num((float) $row['remaining_ht'], 'MT'),
			$row['supplier_invoices_ht'] === null ? '' : (string) price2num((float) $row['supplier_invoices_ht'], 'MT'),
			$row['expenses_paid_ht'] === null ? '' : (string) price2num((float) $row['expenses_paid_ht'], 'MT'),
			$row['miscellaneous_purchases'] === null ? '' : (string) price2num((float) $row['miscellaneous_purchases'], 'MT'),
			(string) price2num((float) $row['time_spent_ht'], 'MT'),
			$row['shipments_valued'] === null ? '' : (string) price2num((float) $row['shipments_valued'], 'MT'),
			implode(' | ', $alerts),
		);
		if ($showEntityColumn) {
			array_unshift($csvRow, isset($entityOptions[(int) $row['entity']]) ? (string) $entityOptions[(int) $row['entity']] : (string) $row['entity']);
		}
		fputcsv($stream, $csvRow, $separator, '"', '\\');
	}

	fclose($stream);
}
