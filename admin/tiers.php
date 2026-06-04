<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 *	\file		admin/tiers.php
 *	\ingroup	jpsun
 *	\brief		JPSUN thirdparty setup page.
 */

require_once __DIR__.'/_setup_common.php';

jpsunHandleAdminSetupActions();
jpsunPrintAdminSetupHeader('thirdparties');

setup_print_title($langs->trans("Thirdparty"));
setup_print_on_off('MAIN_ALL_TO_UPPER');

jpsunPrintAdminSetupFooter();
