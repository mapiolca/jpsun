<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Availability of the explicitly requested backport of Dolibarr PR #42407. */
class JpsunCustomerDepositCompatibility
{
	/** @return bool */
	public static function isAvailable()
	{
		return version_compare(DOL_VERSION, '20.0.0', '>=')
			&& version_compare(DOL_VERSION, '26.0', '<')
			&& isModEnabled('jpsun') && isModEnabled('accounting') && isModEnabled('invoice');
	}
}
