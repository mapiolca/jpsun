<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/_setup_common.php';
require_once __DIR__.'/../class/jpsuninvoicecompatibility.class.php';
require_once __DIR__.'/../class/jpsuncustomerdepositcompatibility.class.php';

/** @var Translate $langs */
jpsunPrintAdminSetupHeader('compatibility');
setup_print_title($langs->trans('Compatibility'));

$rows = array(
	array('Dolibarr', DOL_VERSION),
	array('PHP', PHP_VERSION),
	array($langs->trans('JpsunAboutMinimumVersions'), 'Dolibarr 20 / PHP 8.0'),
);
foreach ($rows as $row) {
	print '<tr class="oddeven"><td class="titlefield">'.dol_escape_htmltag($row[0]).'</td><td>'.dol_escape_htmltag($row[1]).'</td></tr>';
}
$features = array(
	array('JpsunInvoiceModelTitle', JpsunInvoiceCompatibility::isAvailable(), 'JpsunInvoiceModelRequirement'),
	array('CustomerDepositControl', JpsunCustomerDepositCompatibility::isAvailable(), 'JpsunDepositControlRequirement'),
);
foreach ($features as $feature) {
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans($feature[0]).'</td><td>';
	print dolGetBadge($langs->trans($feature[1] ? 'Available' : 'NotAvailable'), '', $feature[1] ? 'success' : 'danger');
	print ' '.$langs->trans($feature[2]).'</td></tr>';
}
print '<tr class="oddeven"><td colspan="2">'.$langs->trans('JpsunInvoiceValidationStatus').'</td></tr>';
require_once __DIR__.'/../class/jpsunproposalcover.class.php';
print '<tr class="oddeven"><td>'.$langs->trans('JpsunCoverModel').'</td><td>'.dolGetBadge($langs->trans(getDolGlobalString('MAIN_USE_FPDF') ? 'NotAvailable' : 'Available'), '', getDolGlobalString('MAIN_USE_FPDF') ? 'danger' : 'success').' '.$langs->trans('JpsunCoverCompatibility').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('JpsunCoverIllustration').'</td><td>'.dolGetBadge($langs->trans(JpsunProposalCover::canUpload() ? 'Available' : 'NotAvailable'), '', JpsunProposalCover::canUpload() ? 'success' : 'danger').' '.$langs->trans('JpsunCoverImageLibraries').'</td></tr>';
jpsunPrintAdminSetupFooter();
