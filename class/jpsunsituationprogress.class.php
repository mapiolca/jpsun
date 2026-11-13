<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Temporary, explicitly requested backport of Dolibarr PR #43005. */
class JpsunSituationProgress
{
	/** @return bool */
	public static function isAvailable()
	{
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		return self::isGlobalAvailable() && !method_exists('Facture', 'getSituationProgress');
	}

	/** @return bool Global totals also complement the native per-invoice column. */
	public static function isGlobalAvailable()
	{
		return version_compare(DOL_VERSION, '20.0', '>=')
			&& version_compare(DOL_VERSION, '26.0', '<')
			&& isModEnabled('jpsun') && isModEnabled('invoice');
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
	/**
	 * Sum the same signed HT amounts as the native history, not cumulative percentages.
	 * The last situation in this displayed scope supplies its full, discounted market.
	 * Credit notes reduce the numerator without replacing the market with their own base.
	 * @param array<Facture> $invoices Authorized, chronologically loaded cycle invoices
	 * @return float|null Global percentage; null when the market is unavailable or zero
	 */
	public static function calculateGlobal($invoices)
	{
		global $mysoc;
		require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
		$total = 0.0;
		$market = null;
		foreach ($invoices as $invoice) {
			$total += (float) $invoice->total_ht;
			if ($invoice->type == Facture::TYPE_SITUATION) {
				$market = $invoice;
			}
		}
		if ($market === null) {
			return null;
		}
		$base = 0.0;
		foreach ($market->lines as $line) {
			if ($line->product_type != 0 && $line->product_type != 1) {
				continue;
			}
			$amounts = calcul_price_total($line->qty, $line->subprice, $line->remise_percent, 0, 0, 0, 0, 'HT', 0, $line->product_type, $mysoc, array('0', '0', '0', '0'));
			if (!isset($amounts[0])) {
				return null;
			}
			$base += (float) $amounts[0];
		}
		return $base != 0.0 ? $total * 100 / $base : null;
	}

}
