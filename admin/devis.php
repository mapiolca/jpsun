<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 *	\file		admin/devis.php
 *	\ingroup	jpsun
 *	\brief		JPSUN proposals setup page.
 */

require_once __DIR__.'/_setup_common.php';

jpsunHandleAdminSetupActions();
jpsunPrintAdminSetupHeader('proposals');

setup_print_title($langs->trans("JpsunCustomerProposal"));
if (floatval(DOL_VERSION) >= 20.0) {
	setup_print_on_off('PROPOSAL_AUTO_ADD_AUTHOR_AS_CONTACT');
}
if (floatval(DOL_VERSION) >= 20.0) {
	setup_print_on_off('JPSUN_GENERATE_PROPALE_WITHOUT_VAT_COLUMN');
}

setup_print_title($langs->trans('JpsunCoverModel'));
require_once __DIR__.'/../class/jpsunproposalcover.class.php';
foreach (JpsunProposalCover::COLORS as $key => $default) {
	$value = getDolGlobalString($key, $default);
	$value = preg_match('/^#[0-9a-f]{6}$/iD', $value) ? $value : $default;
	setup_print_input_form_part($key, false, '', array('type' => 'color', 'value' => $value));
}
print '<tr class="oddeven"><td colspan="3">'.$langs->trans('JpsunCoverActivationHelp').' <a href="'.DOL_URL_ROOT.'/admin/propal.php">'.$langs->trans('Setup').'</a></td></tr>';

setup_print_title($langs->trans("SupplierProposals"));
if (floatval(DOL_VERSION) >= 20.0) {
	setup_print_on_off('SUPPLIER_PROPOSAL_ADD_BILLING_CONTACT');
}
if (floatval(DOL_VERSION) >= 20.0) {
	setup_print_on_off('SUPPLIER_PROPOSAL_AUTOADD_USER_CONTACT');
}
if (floatval(DOL_VERSION) >= 20.0) {
	setup_print_on_off('SUPPLIER_PROPOSAL_ALLOW_EXTERNAL_DOWNLOAD');
}

jpsunPrintAdminSetupFooter();
