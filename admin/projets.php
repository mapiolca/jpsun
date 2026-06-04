<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 *	\file		admin/projets.php
 *	\ingroup	jpsun
 *	\brief		JPSUN projects setup page.
 */

require_once __DIR__.'/_setup_common.php';

jpsunHandleAdminSetupActions();
jpsunPrintAdminSetupHeader('projects');

if (floatval(DOL_VERSION) >= 13.0) {
	setup_print_title($langs->trans("Project"));
	if (getDolGlobalInt('SHOW_DEPRECATED_FEATURES')) {
		setup_print_on_off('JPSUN_PROJECT_SHOW_FORECAST_PROFIT_BOARD');
	}
	setup_print_input_form_part('JPSUN_PROJECT_FORECAST_DEFAULT_THM');
	setup_print_on_off('JPSUN_PROJECTSYNTHESIS_SHOW_PROPOSAL');
	setup_print_on_off('JPSUN_PROJECTSYNTHESIS_SHOW_ORDER');
	setup_print_on_off('JPSUN_PROJECTSYNTHESIS_SHOW_FICHINTER');
	setup_print_on_off('JPSUN_PROJECTSYNTHESIS_SHOW_STOCKTRANSFER');
}

jpsunPrintAdminSetupFooter();
