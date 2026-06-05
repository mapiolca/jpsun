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

jpsunHandleAdminSetupActions();
jpsunPrintAdminSetupHeader('setup');

// Global
setup_print_title($langs->trans("Global"));
setup_print_on_off('MAIN_DISABLE_TRUNC');

// Documents PDF
setup_print_title($langs->trans("JpsunPdfDocuments"));
setup_print_on_off('PDF_SHOW_PROJECT_TITLE');
setup_print_on_off('PRODUIT_PDF_MERGE_PROPAL');
jpsunPdfPrintAttachmentSetupRows();

// Contrat SOLEIL AQUITAIN
setup_print_title($langs->trans("JpsunSoleilAquitainContractSettings"));
setup_print_input_form_part('JPSUN_SOLEIL_AQUITAIN_RC_PRO', false, '', array('size' => '80'));
setup_print_input_form_part('JPSUN_SOLEIL_AQUITAIN_DECENNALE', false, '', array('size' => '80'));
jpsunSetupPrintSupplierThirdpartySelect('JPSUN_SOLEIL_AQUITAIN_MEDIATOR', false, '', 360);
setup_print_input_form_part('JPSUN_SOLEIL_AQUITAIN_WITHDRAWAL_URL', false, '', array('size' => '80'));
jpsunSetupPrintPaymentModeSelect('JPSUN_SOLEIL_AQUITAIN_DEFAULT_PAYMENT_MODE', false, '', 300);
jpsunSetupPrintRecurrenceSelect('JPSUN_SOLEIL_AQUITAIN_DEFAULT_RECURRENCE', false, '', 300);
$soleilAquitainPaymentDay = getDolGlobalString('JPSUN_SOLEIL_AQUITAIN_DEFAULT_PAYMENT_DAY');
setup_print_input_form_part('JPSUN_SOLEIL_AQUITAIN_DEFAULT_PAYMENT_DAY', false, '', array(
	'type' => 'number',
	'min' => '1',
	'max' => '31',
	'step' => '1',
	'value' => (ctype_digit((string) $soleilAquitainPaymentDay) && (int) $soleilAquitainPaymentDay >= 1 && (int) $soleilAquitainPaymentDay <= 31) ? (string) $soleilAquitainPaymentDay : '',
), 'input', false, 120);
$soleilAquitainOfferValidity = getDolGlobalString('JPSUN_SOLEIL_AQUITAIN_OFFER_VALIDITY');
setup_print_input_form_part('JPSUN_SOLEIL_AQUITAIN_OFFER_VALIDITY', false, '', array(
	'type' => 'number',
	'min' => '1',
	'step' => '1',
	'value' => (ctype_digit((string) $soleilAquitainOfferValidity) && (int) $soleilAquitainOfferValidity > 0) ? (string) $soleilAquitainOfferValidity : '',
), 'input', false, 120);

// Workflow
setup_print_title($langs->trans("Workflow"));
setup_print_on_off('JPSUN_AUTOPROJECT_ON_PROPAL_SIGNED');
setup_print_input_form_part('JPSUN_AUTOPROJECT_DELIVERY_WINDOW_WORKDAYS', false, '', array('type' => 'number', 'min' => '1', 'step' => '1', 'value' => getDolGlobalInt('JPSUN_AUTOPROJECT_DELIVERY_WINDOW_WORKDAYS', 5)));
setup_print_on_off('JPSUN_PROJECT_CLOSE_SET_TASK_END_DATE');
setup_print_on_off('JPSUN_PROJECT_CLOSE_COMPLETE_TASKS');
setup_print_on_off('JPSUN_PROJECT_CLOSE_FORCE_PROJECT_END_DATE');

jpsunPrintAdminSetupFooter();
