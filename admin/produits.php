<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 *	\file		admin/produits.php
 *	\ingroup	jpsun
 *	\brief		JPSUN products setup page.
 */

require_once __DIR__.'/_setup_common.php';

jpsunHandleAdminSetupActions(false);
jpsunPrintAdminSetupHeader('products');

setup_print_title($langs->trans("Products"));
setup_print_on_off('PRODUCT_USE_UNITS');
setup_print_on_off('MAIN_SEARCH_PRODUCT_BY_FOURN_REF');

jpsunPrintAdminSetupFooter();
