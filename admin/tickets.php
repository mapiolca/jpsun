<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 *	\file		admin/tickets.php
 *	\ingroup	jpsun
 *	\brief		JPSUN tickets setup page.
 */

require_once __DIR__.'/_setup_common.php';

jpsunHandleAdminSetupActions();
jpsunPrintAdminSetupHeader('tickets');

setup_print_title($langs->trans("Tickets"));
if (floatval(DOL_VERSION) >= 20.0) {
	$ajaxConstantOnOffInput = array(
		'set' => array('TICKET_ADD_AUTHOR_AS_CONTACT' => 2)
	);
	setup_print_on_off('TICKET_ADD_AUTHOR_AS_CONTACT', false, '', false, 300, false, $ajaxConstantOnOffInput);
	$ajaxConstantOnOffInput = '';
}
if (floatval(DOL_VERSION) >= 10.0) {
	setup_print_on_off('TICKET_SHOW_MESSAGES_ON_CARD');
}

jpsunPrintAdminSetupFooter();
