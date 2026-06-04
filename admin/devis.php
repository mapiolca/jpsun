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
