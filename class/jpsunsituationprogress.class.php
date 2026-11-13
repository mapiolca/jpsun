<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Temporary, explicitly requested backport of Dolibarr PR #43005. */
class JpsunSituationProgress
{
	/** @return bool */
	public static function isAvailable()
	{
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		return version_compare(DOL_VERSION, '20.0', '>=')
			&& version_compare(DOL_VERSION, '26.0', '<')
			&& isModEnabled('jpsun') && isModEnabled('invoice')
			&& !method_exists('Facture', 'getSituationProgress');
	}

	/**
	 * Use already loaded native lines; never fetch another object from this helper.
	 * Same calculation as PR #43005 at 1f2010eef91651cfeba501074affad06fc72bb3f.
	 * @param Facture $invoice Invoice whose access was checked by the native card
	 * @return float|null HT-weighted percentage, null for an unavailable/zero net base
	 */
	public static function calculate($invoice)
	{
		global $mysoc;
		require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
		$total = 0.0;
		$progress = 0.0;
		foreach ($invoice->lines as $line) {
			if ($line->product_type != 0 && $line->product_type != 1) {
				continue;
			}
			$amounts = calcul_price_total($line->qty, $line->subprice, $line->remise_percent, 0, 0, 0, 0, 'HT', 0, $line->product_type, $mysoc, array('0', '0', '0', '0'));
			if (!isset($amounts[0])) {
				return null;
			}
			$weight = (float) $amounts[0];
			$total += $weight;
			$progress += $weight * (float) $line->situation_percent;
		}
		return $total != 0.0 ? $progress / $total : null;
	}
}
