<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/doc/pdf_sponge.modules.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/discount.class.php';
require_once __DIR__.'/../../../../class/jpsuninvoicecompatibility.class.php';

/** Native Sponge invoice with deposits separated from commercial discounts. */
class pdf_jpsuninvoice extends pdf_sponge
{
	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		global $langs;
		parent::__construct($db);
		$langs->load('jpsun@jpsun');
		$this->name = 'jpsuninvoice';
		$this->description = $langs->trans('JpsunInvoiceModelDescription');
		$this->version = JpsunInvoiceCompatibility::isAvailable() ? 'dolibarr' : 'disabled';
	}

	/**
	 * Keep the native generation flow and fail before writing on unsupported versions.
	 * @param Facture $object Invoice already authorised by the calling entry point
	 * @param Translate|null $outputlangs Output language
	 * @param string $srctemplatepath Source template
	 * @param int $hidedetails Hide details
	 * @param int $hidedesc Hide descriptions
	 * @param int $hideref Hide references
	 * @return int
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $langs;
		$outputlangs = is_object($outputlangs) ? $outputlangs : $langs;
		$outputlangs->load('jpsun@jpsun');
		if (!JpsunInvoiceCompatibility::isAvailable()) {
			$this->error = $outputlangs->trans('JpsunInvoiceModelRequirement');
			return -1;
		}
		// The owner directory must exist in configuration; never fall back to the viewer's entity.
		$entity = (int) $object->entity;
		if ($entity <= 0 || !isset($conf->facture->multidir_output[$entity])
			|| !is_string($conf->facture->multidir_output[$entity])
			|| trim($conf->facture->multidir_output[$entity]) === '') {
			$this->error = $outputlangs->trans('JpsunInvoiceDocumentDirectoryUnavailable');
			return -1;
		}
		$directory = getMultidirOutput($object, 'facture', 1);
		if (!is_string($directory) || $directory === '' || strpos($directory, 'error-') === 0) {
			$this->error = $outputlangs->trans('JpsunInvoiceDocumentDirectoryUnavailable');
			return -1;
		}
		try {
			// Preflight the relations before the parent can write any file.
			$this->getDepositSummary($object, $outputlangs);
			return parent::write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);
		} catch (RuntimeException $exception) {
			$this->error = $exception->getMessage();
			return -1;
		}
	}

	/**
	 * Reserve additional space before native pagination and footer rendering.
	 * @param Facture $object Invoice
	 * @param Translate $outputlangs Output language
	 * @param int $hidedetails Hide details
	 * @param int $hidedesc Hide descriptions
	 * @param int $hideref Hide references
	 * @return void
	 */
	public function defineColumnField($object, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		parent::defineColumnField($object, $outputlangs, $hidedetails, $hidedesc, $hideref);
		if (!$object->isSituationInvoice() && (int) $object->type !== Facture::TYPE_CREDIT_NOTE
			&& $this->getDepositSummary($object, $outputlangs)['deposits'] != 0.0) {
			// Three summary rows, including wrapped bilingual labels.
			$this->heightforinfotot += 24;
		}
	}

	/**
	 * Classify only consumed negative lines whose source is a deposit invoice.
	 * Native discount helpers retain their hooks, rounding and foreign currency behavior.
	 * @param Facture $object Invoice
	 * @param Translate $outputlangs Output language
	 * @return array{deposits:float,discounts:float,total:float}
	 * @throws RuntimeException When a linked discount cannot be resolved safely
	 */
	public function getDepositSummary($object, $outputlangs)
	{
		$foreign = isModEnabled('multicurrency') && $object->multicurrency_tx != 1;
		$deposits = 0.0;
		$discounts = 0.0;
		foreach ($object->lines as $i => $line) {
			if (!$line instanceof FactureLigne) {
				throw new RuntimeException($outputlangs->trans('JpsunInvoiceDiscountUnavailable'));
			}
			$amount = (float) ($foreign ? $line->multicurrency_total_ht : $line->total_ht);
			if ((float) $line->total_ht < 0 && (int) $line->fk_remise_except > 0) {
				$discount = new DiscountAbsolute($this->db);
				if ($discount->fetch((int) $line->fk_remise_except) <= 0
					|| (int) $discount->socid !== (int) $object->socid
					|| (int) $discount->fk_facture_line !== (int) $line->id) {
					throw new RuntimeException($outputlangs->trans('JpsunInvoiceDiscountUnavailable'));
				}
				if ((int) $discount->fk_facture_source > 0 && (int) $discount->type_facture_source === Facture::TYPE_DEPOSIT) {
					$deposits -= $amount;
					continue;
				}
			}
			$lineDiscount = pdfGetLineTotalDiscountAmount($object, $i, $outputlangs, 2, $foreign ? 1 : 0);
			$discounts += is_numeric($lineDiscount) ? (float) $lineDiscount : 0.0;
			if ((float) $line->total_ht < 0) {
				$discounts -= $amount;
			}
		}
		return array(
			'deposits' => (float) price2num($deposits, 'MT'),
			'discounts' => (float) price2num($discounts, 'MT'),
			'total' => (float) ($foreign ? $object->multicurrency_total_ht : $object->total_ht),
		);
	}

	/**
	 * Print the separate summary, then let Sponge print taxes, payments and balance.
	 * @param TCPDF $pdf PDF renderer
	 * @param Facture $object Invoice
	 * @param float $deja_regle Already paid
	 * @param float $posy Starting ordinate
	 * @param Translate $outputlangs Output language
	 * @param Translate|null $outputlangsbis Second output language
	 * @return float
	 */
	protected function drawTotalTable(&$pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis)
	{
		if ($object->isSituationInvoice() || (int) $object->type === Facture::TYPE_CREDIT_NOTE) {
			return parent::drawTotalTable($pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis);
		}
		$outputlangs->load('jpsun@jpsun');
		if (is_object($outputlangsbis)) {
			$outputlangsbis->load('jpsun@jpsun');
		}
		$summary = $this->getDepositSummary($object, $outputlangs);
		if ($summary['deposits'] == 0.0) {
			return parent::drawTotalTable($pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis);
		}
		$rows = array();
		if ($this->showAmountBeforeDiscount) {
			$rows['TotalHTBeforeDiscount'] = (float) price2num($summary['total'] + $summary['discounts'] + $summary['deposits'], 'MT');
		}
		$rows['JpsunInvoiceDepositsDeducted'] = $summary['deposits'];
		if ($this->showDiscountAmount) {
			$rows['TotalDiscount'] = $summary['discounts'];
		}
		$labelx = $this->page_largeur < 210 ? 105 : 120;
		$amountx = $this->page_largeur < 210 ? 160 : 170;
		$pdf->SetFont('', '', pdf_getPDFFontSize($outputlangs) - (is_object($outputlangsbis) ? 2 : 1));
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFillColor(255, 255, 255);
		foreach ($rows as $key => $amount) {
			$label = $outputlangs->transnoentities($key);
			if (is_object($outputlangsbis)) {
				$label .= ' / '.$outputlangsbis->transnoentities($key);
			}
			$height = max(4, $pdf->getStringHeight($amountx - $labelx, $label));
			$pdf->SetXY($labelx, $posy);
			$pdf->MultiCell($amountx - $labelx, $height, $label, 0, 'L', true);
			$pdf->SetXY($amountx, $posy);
			$pdf->MultiCell($this->page_largeur - $this->marge_droite - $amountx, $height, price($amount, 0, $outputlangs), 0, 'R', true);
			$posy += $height;
		}
		$showBefore = $this->showAmountBeforeDiscount;
		$showDiscount = $this->showDiscountAmount;
		try {
			$this->showAmountBeforeDiscount = 0;
			$this->showDiscountAmount = 0;
			return parent::drawTotalTable($pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis);
		} finally {
			$this->showAmountBeforeDiscount = $showBefore;
			$this->showDiscountAmount = $showDiscount;
		}
	}
}
