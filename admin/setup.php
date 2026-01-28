<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		admin/setup.php
 * 	\ingroup	jpsun
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
$res = @include("../../main.inc.php"); // From htdocs directory
if (! $res) {
	$res = @include("../../../main.inc.php"); // From "custom" directory
}

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/jpsun.lib.php';

// Translations
$langs->loadLangs(array('admin', 'jpsun@jpsun'));

// Access control
if (! $user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */
if (preg_match('/set_(.*)/', $action, $reg)) {
	$code = $reg[1];
	if (dolibarr_set_const($db, $code, GETPOST($code, 'none'), 'chaine', 0, '', $conf->entity) > 0) {
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	} else {
		dol_print_error($db);
	}
}

if (preg_match('/del_(.*)/', $action, $reg)) {
	$code = $reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0) {
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	} else {
		dol_print_error($db);
	}
}

/*
 * View
 */
$page_name = "JpsunSetup";
llxHeader('', $langs->trans($page_name));

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">' . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback, 'tools');

$head = jpsunAdminPrepareHead();
dol_fiche_head($head, 'setup', $langs->trans("Module999999Desc"), -1, "jpsun@jpsun");

print '<table class="noborder" width="100%">';

setup_print_title('JpsunPdfDocuments');
setup_print_on_off('PDF_SHOW_PROJECT_TITLE');
setup_print_on_off('PRODUIT_PDF_MERGE_PROPAL');

setup_print_title('Global');
setup_print_on_off('MAIN_DISABLE_TRUNC');

setup_print_title('Thirdparty');
setup_print_on_off('MAIN_ALL_TO_UPPER');

setup_print_title('Products');
setup_print_on_off('PRODUCT_USE_UNITS');
setup_print_on_off('MAIN_SEARCH_PRODUCT_BY_FOURN_REF');

setup_print_title('JpsunCustomerProposal');
if (floatval(DOL_VERSION) >= 20.0) {
	setup_print_on_off('PROPOSAL_AUTO_ADD_AUTHOR_AS_CONTACT');
	setup_print_on_off('JPSUN_GENERATE_PROPALE_WITHOUT_VAT_COLUMN');
}

setup_print_title('Workflow');
setup_print_on_off('JPSUN_AUTOPROJECT_ON_PROPAL_SIGNED');

setup_print_title('CustomerOrder');
if (floatval(DOL_VERSION) >= 7.0) {
	setup_print_on_off('MAIN_USE_PROPAL_REFCLIENT_FOR_ORDER');
}

setup_print_title('SetupSituationTitle');
$ajaxConstantOnOffInput = array(
	'set' => array('INVOICE_USE_SITUATION' => 2)
);
setup_print_on_off('INVOICE_USE_SITUATION', false, '', false, 300, true, $ajaxConstantOnOffInput);
$ajaxConstantOnOffInput = '';
if (getDolGlobalInt('INVOICE_USE_SITUATION')) {
	if (intval(DOL_VERSION) >= 11
		|| file_exists(DOL_DOCUMENT_ROOT . '/admin/facture_situation.php')
		|| file_exists(DOL_DOCUMENT_ROOT . '/admin/invoice_situation.php')
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

setup_print_title('SupplierProposals');
if (floatval(DOL_VERSION) >= 20.0) {
	setup_print_on_off('SUPPLIER_PROPOSAL_ADD_BILLING_CONTACT');
	setup_print_on_off('SUPPLIER_PROPOSAL_AUTOADD_USER_CONTACT');
	setup_print_on_off('SUPPLIER_PROPOSAL_ALLOW_EXTERNAL_DOWNLOAD');
}

setup_print_title('SupplierOrder');
if (floatval(DOL_VERSION) >= 20.0) {
	setup_print_on_off('SUPPLIER_ORDER_AUTOADD_USER_CONTACT');
}
if (floatval(DOL_VERSION) >= 10.0) {
	setup_print_on_off('MAIN_CAN_EDIT_SUPPLIER_ON_SUPPLIER_ORDER');
}

if (floatval(DOL_VERSION) >= 13.0) {
	setup_print_title('Project');
	setup_print_on_off('JPSUN_PROJECT_SHOW_FORECAST_PROFIT_BOARD');
	setup_print_input_form_part('JPSUN_PROJECT_FORECAST_DEFAULT_THM');
}

setup_print_title('Tickets');
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

print '</table>';

dol_fiche_end();
llxFooter();

$db->close();


