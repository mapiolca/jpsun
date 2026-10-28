<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Availability of the native Sponge discount display controls. */
class JpsunInvoiceCompatibility
{
	/** @return bool */
	public static function isAvailable()
	{
		return version_compare(DOL_VERSION, '24.0.0', '>=')
			&& isModEnabled('jpsun') && isModEnabled('invoice');
	}
}
