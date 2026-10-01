<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       admin/about.php
 * \ingroup    jpsun
 * \brief      JPSUN module information.
 */

require_once __DIR__.'/_setup_common.php';
require_once __DIR__.'/../core/modules/modJpsun.class.php';

$module = new modJpsun($db);
$composerPath = __DIR__.'/../composer.json';
$composerMetadata = is_readable($composerPath) ? json_decode((string) file_get_contents($composerPath), true) : null;
$license = is_array($composerMetadata) && isset($composerMetadata['license']) && is_string($composerMetadata['license'])
	? $composerMetadata['license'] : '';

jpsunPrintAdminSetupHeader('about');
setup_print_title($langs->trans('About'));

$rows = array(
	array($langs->trans('Module'), (string) $module->name),
	array($langs->trans('Version'), (string) $module->version),
	array($langs->trans('JpsunAboutPublisher'), (string) $module->editor_name),
	array($langs->trans('Description'), $langs->trans((string) $module->description)),
	array($langs->trans('JpsunAboutMinimumVersions'), 'Dolibarr '.implode('.', $module->need_dolibarr_version).' / PHP '.implode('.', $module->phpmin)),
	array($langs->trans('JpsunAboutDependencies'), implode(', ', $module->depends)),
	array($langs->trans('JpsunAboutFeatures'), $langs->trans('JpsunAboutMainFeatures')),
	array($langs->trans('License'), $license),
);

foreach ($rows as $row) {
	print '<tr class="oddeven"><td class="titlefield">'.dol_escape_htmltag($row[0]).'</td><td>'.dol_escape_htmltag($row[1]).'</td></tr>';
}

if (!empty($module->editor_url)) {
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('JpsunAboutUsefulLinks').'</td><td><a href="'.dol_escape_htmltag($module->editor_url).'" target="_blank" rel="noopener noreferrer">'.dol_escape_htmltag($module->editor_url).'</a></td></tr>';
}

jpsunPrintAdminSetupFooter();
