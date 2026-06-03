<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file		admin/setup.php
 *	\ingroup	jpsun
 *	\brief		Main JPSUN setup page.
 */

require_once __DIR__.'/_setup_common.php';

jpsunHandleAdminSetupActions(true);
jpsunPrintAdminSetupHeader('setup');

// Global
setup_print_title($langs->trans("Global"));
setup_print_on_off('MAIN_DISABLE_TRUNC');

// Documents PDF
setup_print_title($langs->trans("JpsunPdfDocuments"));
setup_print_on_off('PDF_SHOW_PROJECT_TITLE');
setup_print_on_off('PRODUIT_PDF_MERGE_PROPAL');
jpsunPdfPrintAttachmentSetupRows();

// Workflow
setup_print_title($langs->trans("Workflow"));
setup_print_on_off('JPSUN_AUTOPROJECT_ON_PROPAL_SIGNED');
setup_print_input_form_part('JPSUN_AUTOPROJECT_DELIVERY_WINDOW_WORKDAYS', false, '', array('type' => 'number', 'min' => '1', 'step' => '1', 'value' => getDolGlobalInt('JPSUN_AUTOPROJECT_DELIVERY_WINDOW_WORKDAYS', 5)));
setup_print_on_off('JPSUN_PROJECT_CLOSE_SET_TASK_END_DATE');
setup_print_on_off('JPSUN_PROJECT_CLOSE_COMPLETE_TASKS');
setup_print_on_off('JPSUN_PROJECT_CLOSE_FORCE_PROJECT_END_DATE');
if (!jpsunIsPowerPlantPVEnabled()) {
	print '<tr>';
	print '<td>'.$langs->trans('JpsunRecomputePeakPowerLabel').'<br><small>'.$langs->trans('JpsunRecomputePeakPowerDesc').'</small></td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="jpsun_recompute_peak_power">';
	print '<input type="submit" class="button button-edit" value="'.$langs->trans('JpsunRecomputePeakPowerButton').'">';
	print '</form>';
	print '</td>';
	print '</tr>';
}

jpsunPrintAdminSetupFooter();
