<?php
/* Copyright (C) 2005-2016 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *      \file       jpsun/tabs/projet_enedis.php
 *      \ingroup    project
 *		\brief      Retired ENEDIS project tab
 */

// Load Dolibarr environment from a standard or custom module installation.
$res = 0;
if (is_file(__DIR__.'/../../main.inc.php')) {
	$res = include __DIR__.'/../../main.inc.php';
}
if (!$res && is_file(__DIR__.'/../../../main.inc.php')) {
	$res = include __DIR__.'/../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

// Retired in 1.24.1. Also refuse bookmarked URLs and stale persisted tabs.
accessforbidden($langs->trans('FeatureDisabled'));
