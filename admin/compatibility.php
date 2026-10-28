<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/_setup_common.php';
require_once __DIR__.'/../class/jpsuninvoicecompatibility.class.php';

jpsunPrintAdminSetupHeader('compatibility');
setup_print_title($langs->trans('Compatibility'));

$rows = array(
	array('Dolibarr', DOL_VERSION),
	array('PHP', PHP_VERSION),
	array($langs->trans('JpsunAboutMinimumVersions'), 'Dolibarr 20 / PHP 8.0'),
	array($langs->trans('JpsunInvoiceModelTitle'), $langs->trans(JpsunInvoiceCompatibility::isAvailable() ? 'Available' : 'NotAvailable')),
);
foreach ($rows as $row) {
	print '<tr class="oddeven"><td class="titlefield">'.dol_escape_htmltag($row[0]).'</td><td>'.dol_escape_htmltag($row[1]).'</td></tr>';
}
print '<tr class="oddeven"><td colspan="2">'.$langs->trans('JpsunInvoiceModelRequirement').'</td></tr>';
print '<tr class="oddeven"><td colspan="2">'.$langs->trans('JpsunInvoiceValidationStatus').'</td></tr>';
jpsunPrintAdminSetupFooter();
