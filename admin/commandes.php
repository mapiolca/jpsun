<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 *	\file		admin/commandes.php
 *	\ingroup	jpsun
 *	\brief		JPSUN orders setup page.
 */

require_once __DIR__.'/_setup_common.php';

jpsunHandleAdminSetupActions();
jpsunPrintAdminSetupHeader('orders');

setup_print_title($langs->trans("CustomerOrder"));
if (floatval(DOL_VERSION) >= 7.0) {
	setup_print_on_off('MAIN_USE_PROPAL_REFCLIENT_FOR_ORDER');
}

setup_print_title($langs->trans("SupplierOrder"));
if (floatval(DOL_VERSION) >= 20.0) {
	setup_print_on_off('SUPPLIER_ORDER_AUTOADD_USER_CONTACT');
}
if (floatval(DOL_VERSION) >= 10.0) {
	setup_print_on_off('MAIN_CAN_EDIT_SUPPLIER_ON_SUPPLIER_ORDER');
}

jpsunPrintAdminSetupFooter();
