<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 *	\file		admin/factures.php
 *	\ingroup	jpsun
 *	\brief		JPSUN invoices setup page.
 */

require_once __DIR__.'/_setup_common.php';

jpsunHandleAdminSetupActions(false);
jpsunPrintAdminSetupHeader('invoices');

setup_print_title($langs->trans("SetupSituationTitle"));

$ajaxConstantOnOffInput = array(
	'set' => array('INVOICE_USE_SITUATION' => 2)
);
setup_print_on_off('INVOICE_USE_SITUATION', false, '', false, 300, true, $ajaxConstantOnOffInput);
$ajaxConstantOnOffInput = '';

if (getDolGlobalInt('INVOICE_USE_SITUATION')) {
	if (intval(DOL_VERSION) >= 11
		|| file_exists(DOL_DOCUMENT_ROOT.'/admin/facture_situation.php')
		|| file_exists(DOL_DOCUMENT_ROOT.'/admin/invoice_situation.php')
	) {
		if (intval(DOL_VERSION) >= 20) {
			$link = dol_buildpath('admin/invoice_situation.php', 1);
		} else {
			$link = dol_buildpath('admin/facture_situation.php', 1);
		}
		print '<tr>';
		print '<td colspan="3">'.$langs->trans('SituationParamsAvailablesHere').' <a href="'.$link.'">'.$langs->trans("SetupSituationTitle").'</a></td>'."\n";
		print '</tr>';
	} elseif (intval(DOL_VERSION) >= 8) {
		setup_print_on_off('INVOICE_USE_SITUATION_CREDIT_NOTE');
	}
}

jpsunPrintAdminSetupFooter();
