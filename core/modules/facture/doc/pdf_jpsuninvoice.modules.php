<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * The invoice header adapts the upstream Sponge model from Dolibarr 24.0.2.
 * Copyright (C) 2004-2024	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012	Regis Houssin			<regis.houssin@inodbox.com>
 * Copyright (C) 2008		Raphael Bertrand		<raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2014	Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2012		Christophe Battarel		<christophe.battarel@altairis.fr>
 * Copyright (C) 2012		Cédric Salvador			<csalvador@gpcsolutions.fr>
 * Copyright (C) 2012-2014	Raphaël Doursenaud		<rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2015		Marcos García			<marcosgdf@gmail.com>
 * Copyright (C) 2017		Ferran Marcet			<fmarcet@2byte.es>
 * Copyright (C) 2018-2026  Frédéric France			<frederic.france@free.fr>
 * Copyright (C) 2018-2024	Anthony Berton			<anthony.berton@bb2a.fr>
 * Copyright (C) 2022-2025	Alexandre Spangaro		<alexandre@inovea-conseil.com>
 * Copyright (C) 2024-2025	MDW						<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024-2025  Nick Fragoulis
 * Copyright (C) 2024       Franck Moreau
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
		$this->name = $langs->trans('JpsunInvoiceModelTitle');
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

	// Adapt the Sponge header only; the parent still generates the table, totals and footer.
	/**
	 *  Render the invoice header with measured spacing between fields and address blocks.
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Facture		$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes (usually set to 1 for first page, and 0 for next pages)
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @param  ?Translate	$outputlangsbis	Object lang for output bis
	 *  @return	array{top_shift:float,shipp_shift:float}	Native offsets for the table and following content
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $outputlangsbis = null)
	{
		global $conf, $langs;

		$ltrdirection = 'L';
		if ($outputlangs->trans("DIRECTION") == 'rtl') {
			$ltrdirection = 'R';
		}

		$outputlangs->loadLangs(array("main", "bills", "propal", "companies", "compta"));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$w = 110;

		$posy = $this->marge_haute;
		$posx = $this->page_largeur - $this->marge_droite - $w;

		$pdf->SetXY($this->marge_gauche, $posy);

		$logodir = $conf->mycompany->multidir_output[$object->entity] ?? $conf->mycompany->dir_output ?? '';
		if (function_exists('pdf_writeLogoOrCompanyName')) {
			pdf_writeLogoOrCompanyName($pdf, $outputlangs, $this->emetteur, $logodir, $this->marge_gauche, $posy, $w, $default_font_size, $ltrdirection);
		} elseif (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO')) {
			if ($this->emetteur->logo) {
				$logo = getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') ? $logodir.'/logos/'.$this->emetteur->logo : $logodir.'/logos/thumbs/'.$this->emetteur->logo_small;
				if (is_readable($logo)) {
					$pdf->Image($logo, $this->marge_gauche, $posy, 0, pdf_getHeightForLogo($logo));
				} else {
					$pdf->SetTextColor(200, 0, 0);
					$pdf->SetFont('', 'B', $default_font_size - 2);
					$pdf->MultiCell($w, 3, $outputlangs->transnoentities('ErrorLogoFileNotFound', $logo), 0, 'L');
					$pdf->MultiCell($w, 3, $outputlangs->transnoentities('ErrorGoToGlobalSetup'), 0, 'L');
				}
			} else {
				$pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, $ltrdirection);
			}
		}

		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$subtitle = "";
		$title = $outputlangs->transnoentities("PdfInvoiceTitle");
		if ($object->type == 1) {
			$title = $outputlangs->transnoentities("InvoiceReplacement");
		}
		if ($object->type == 2) {
			$title = $outputlangs->transnoentities("InvoiceAvoir");
		}
		if ($object->type == 3) {
			$title = $outputlangs->transnoentities("PdfInvoiceDepositTitle");
		}
		if ($this->situationinvoice) {
			$outputlangs->loadLangs(array("other"));
			$title = $outputlangs->transnoentities("PDFInvoiceSituation") . " " . $outputlangs->transnoentities("NumberingShort") . $object->situation_counter . " -";
			$subtitle = $outputlangs->transnoentities("PDFSituationTitle", (string) $object->situation_counter);
		}
		if (getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE') && is_object($outputlangsbis)) {
			$title .= ' - ';
			if ($object->type == 0) {
				if ($this->situationinvoice) {
					$title .= $outputlangsbis->transnoentities("PDFInvoiceSituation");
				}
				$title .= $outputlangsbis->transnoentities("PdfInvoiceTitle");
			} elseif ($object->type == 1) {
				$title .= $outputlangsbis->transnoentities("InvoiceReplacement");
			} elseif ($object->type == 2) {
				$title .= $outputlangsbis->transnoentities("InvoiceAvoir");
			} elseif ($object->type == 3) {
				$title .= $outputlangsbis->transnoentities("InvoiceDeposit");
			} elseif ($object->type == 4) {
				$title .= $outputlangsbis->transnoentities("InvoiceProForma");
			}
		}
		$title .= ' '.$outputlangs->convToOutputCharset($object->ref);
		if ($object->status == $object::STATUS_DRAFT) {
			$pdf->SetTextColor(128, 0, 0);
			$title .= ' - '.$outputlangs->transnoentities("NotValidated");
		}

		$fieldgap = 0.5;
		$pdf->MultiCell($w, 3, $title, '', 'R');
		$posy = $pdf->GetY();

		if (!empty($subtitle)) {
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->SetXY($posx, $posy + $fieldgap);
			$pdf->MultiCell($w, 3, $subtitle, '', 'R');
			$posy = $pdf->GetY();
		}

		$pdf->SetFont('', '', $default_font_size - 2);

		$additionalTitleY = $posy + $fieldgap;
		pdfWriteAdditionnalTitle($pdf, $outputlangs, $this->page_hauteur, $object, $w, $posx, $additionalTitleY);

		$posy = max($posy, $pdf->GetY());
		$fields = array();
		if ($object->ref_customer) {
			$fields[] = $outputlangs->transnoentities("RefCustomer")." : ".dol_trunc($outputlangs->convToOutputCharset($object->ref_customer), 65);
		}

		$showProjectTitle = getDolGlobalString('PDF_SHOW_PROJECT_TITLE');
		$showProjectReference = getDolGlobalString('PDF_SHOW_PROJECT');
		if ($showProjectTitle || $showProjectReference) {
			$object->fetchProject();
		}
		if ($showProjectTitle) {
			if (!empty($object->project->title)) {
				$fields[] = $outputlangs->transnoentities("Project")." : ".dol_trunc($object->project->title, 50);
			}
		}

		if ($showProjectReference) {
			if (!empty($object->project->ref)) {
				$outputlangs->load("projects");
				$fields[] = $outputlangs->transnoentities("RefProject")." : ".$object->project->ref;
			}
		}

		$objectidnext = $object->getIdReplacingInvoice('validated');
		if ($object->type == 0 && $objectidnext) {
			$objectreplacing = new Facture($this->db);
			if ($objectreplacing->fetch($objectidnext) > 0) {
				$fields[] = $outputlangs->transnoentities("ReplacementByInvoice").' : '.$outputlangs->convToOutputCharset($objectreplacing->ref);
			}
		}
		if ($object->type == 1) {
			$objectreplaced = new Facture($this->db);
			if ($objectreplaced->fetch($object->fk_facture_source) > 0) {
				$fields[] = $outputlangs->transnoentities("ReplacementInvoice").' : '.$outputlangs->convToOutputCharset($objectreplaced->ref);
			}
		}
		if ($object->type == 2 && !empty($object->fk_facture_source)) {
			$objectreplaced = new Facture($this->db);
			if ($objectreplaced->fetch($object->fk_facture_source) > 0) {
				$fields[] = $outputlangs->transnoentities("CorrectionInvoice").' : '.$outputlangs->convToOutputCharset($objectreplaced->ref);
			}
		}


		$title = $outputlangs->transnoentities("DateInvoice");
		if (getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE') && is_object($outputlangsbis)) {
			$title .= ' - '.$outputlangsbis->transnoentities("DateInvoice");
		}
		$fields[] = $title." : ".dol_print_date($object->date, "day", false, $outputlangs, true);

		if (getDolGlobalString('INVOICE_POINTOFTAX_DATE')) {
			$fields[] = $outputlangs->transnoentities("DatePointOfTax")." : ".dol_print_date($object->date_pointoftax, "day", false, $outputlangs);
		}

		if ($object->type != 2) {
			$title = $outputlangs->transnoentities("DateDue");
			if (getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE') && is_object($outputlangsbis)) {
				$title .= ' - '.$outputlangsbis->transnoentities("DateDue");
			}
			$fields[] = $title." : ".dol_print_date($object->date_lim_reglement, "day", false, $outputlangs, true);
		}

		if (!getDolGlobalString('MAIN_PDF_HIDE_CUSTOMER_CODE') && $object->thirdparty->code_client) {
			$fields[] = $outputlangs->transnoentities("CustomerCode")." : ".$outputlangs->transnoentities((string) $object->thirdparty->code_client);
		}

		if (!getDolGlobalString('MAIN_PDF_HIDE_CUSTOMER_ACCOUNTING_CODE') && $object->thirdparty->code_compta_client) {
			$fields[] = $outputlangs->transnoentities("CustomerAccountancyCode")." : ".$outputlangs->transnoentities((string) $object->thirdparty->code_compta_client);
		}

		// Get contact
		if (getDolGlobalString('DOC_SHOW_FIRST_SALES_REP')) {
			$arrayidcontact = $object->getIdContact('internal', 'SALESREPFOLL');
			if ($arrayidcontact) {
				$usertmp = new User($this->db);
				if ($usertmp->fetch($arrayidcontact[0]) > 0) {
					$fields[] = $outputlangs->transnoentities("SalesRepresentative")." : ".$usertmp->getFullName($langs);
				}
			}
		}

		$pdf->SetTextColor(0, 0, 60);
		foreach ($fields as $field) {
			$pdf->SetXY($posx, $posy + $fieldgap);
			$pdf->MultiCell($w, 3, $field, '', 'R');
			$posy = $pdf->GetY();
		}
		if (!getDolGlobalString('INVOICE_HIDE_LINKED_OBJECT')) {
			$posy = $this->drawHeaderLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, $w, $default_font_size, $fieldgap);
		}

		$iso = (bool) getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION');
		$addressTop = $this->marge_haute + ($iso ? 30 : 32);
		$senderWidth = $iso ? 92 : 82;
		$recipientWidth = $this->page_largeur < 210 ? 84 : ($iso ? 92 : 100);
		$senderCaption = '';
		$recipientCaption = '';
		$captionHeight = 5.0;
		if ($showaddress) {
			$pdf->SetFont('', '', $default_font_size - 2);
			if (!getDolGlobalString('MAIN_PDF_NO_SENDER_FRAME')) {
				$senderCaption = $outputlangs->transnoentities('BillFrom');
				$captionHeight = max($captionHeight, $pdf->getStringHeight($senderWidth, $senderCaption));
			}
			if (!getDolGlobalString('MAIN_PDF_NO_RECIPENT_FRAME')) {
				$recipientCaption = $outputlangs->transnoentities('BillTo');
				$captionHeight = max($captionHeight, $pdf->getStringHeight($recipientWidth - 2, $recipientCaption));
			}
		}

		// Both captions start above their frames, after the last measured field.
		$top_shift = max(0.0, $posy + $fieldgap + $captionHeight - $addressTop);
		$addressTop += $top_shift;
		$shipp_shift = 0.0;

		if ($showaddress) {
			// Sender properties
			$carac_emetteur = '';
			// Add internal contact of object if defined
			$arrayidcontact = $object->getIdContact('internal', 'BILLING');
			if ($arrayidcontact && $object->fetch_user($arrayidcontact[0]) > 0 && is_object($object->user)) {
				$labelbeforecontactname = ($outputlangs->transnoentities("FromContactName") != 'FromContactName' ? $outputlangs->transnoentities("FromContactName") : $outputlangs->transnoentities("Name"));
				$carac_emetteur .= $labelbeforecontactname." ".$outputlangs->convToOutputCharset($object->user->getFullName($outputlangs));
				$carac_emetteur .= "\n";
			}

			$carac_emetteur .= pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object);

			// Show sender
			$posy = $addressTop;
			$posx = $this->marge_gauche;
			if (getDolGlobalString('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->page_largeur - $this->marge_droite - 80;
			}

			$hautcadre = $iso ? 38 : 40;
			$widthrecbox = $senderWidth;

			// Show sender frame
			if (!getDolGlobalString('MAIN_PDF_NO_SENDER_FRAME')) {
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posx, $posy - $captionHeight);
				$pdf->MultiCell($widthrecbox, $captionHeight, $senderCaption, 0, $ltrdirection);
				$pdf->SetXY($posx, $posy);
				$pdf->SetFillColor(230, 230, 230);
				$pdf->RoundedRect($posx, $posy, $widthrecbox, $hautcadre, $this->corner_radius, '1234', 'F');
				$pdf->SetTextColor(0, 0, 60);
			}

			// Show sender name
			if (!getDolGlobalString('MAIN_PDF_HIDE_SENDER_NAME')) {
				$pdf->SetXY($posx + 2, $posy + 3);
				$pdf->SetFont('', 'B', $default_font_size);
				$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, $ltrdirection);
				$posy = $pdf->getY();
			}

			// Show sender information
			$pdf->SetXY($posx + 2, $posy);
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->MultiCell($widthrecbox - 2, 4, $carac_emetteur, 0, $ltrdirection);

			// If BILLING contact defined on invoice, we use it
			$usecontact = false;
			$arrayidcontact = $object->getIdContact('external', 'BILLING');
			if ($arrayidcontact) {
				$usecontact = $object->fetch_contact($arrayidcontact[0]) > 0 && is_object($object->contact);
			}

			// Recipient name
			if ($usecontact && ($object->contact->socid != $object->thirdparty->id && getDolGlobalInt('MAIN_USE_COMPANY_NAME_OF_CONTACT', 1))) {
				$thirdparty = $object->contact;
			} else {
				$thirdparty = $object->thirdparty;
			}

			$carac_client_name = is_object($thirdparty) ? pdfBuildThirdpartyName($thirdparty, $outputlangs) : '';

			$carac_client = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, ($usecontact ? $object->contact : ''), ($usecontact ? 1 : 0), 'target', $object);

			// Show recipient
			$widthrecbox = $recipientWidth;
			$posy = $addressTop;
			$posx = $this->page_largeur - $this->marge_droite - $widthrecbox;
			if (getDolGlobalString('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->marge_gauche;
			}

			// Show recipient frame
			if (!getDolGlobalString('MAIN_PDF_NO_RECIPENT_FRAME')) {
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posx + 2, $posy - $captionHeight);
				$pdf->MultiCell($widthrecbox - 2, $captionHeight, $recipientCaption, 0, $ltrdirection);
				$pdf->RoundedRect($posx, $posy, $widthrecbox, $hautcadre, $this->corner_radius, '1234', 'D');
			}

			// Show recipient name
			$pdf->SetXY($posx + 2, $posy + 3);
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->MultiCell($widthrecbox - 2, 2, $carac_client_name, 0, $ltrdirection);

			$posy = $pdf->getY();

			// Show recipient information
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetXY($posx + 2, $posy);
			$pdf->MultiCell($widthrecbox - 2, 4, $carac_client, 0, $ltrdirection);

			// Show shipping/delivery address
			if (getDolGlobalInt('INVOICE_SHOW_SHIPPING_ADDRESS')) {
				$idaddressshipping = $object->getIdContact('external', 'SHIPPING');

				$carac_client_name_shipping = '';
				$carac_client_shipping = '';
				if ($idaddressshipping && $object->fetch_contact($idaddressshipping[0]) > 0 && is_object($object->contact)) {
					$companystatic = new Societe($this->db);
					if ($companystatic->fetch($object->contact->fk_soc) > 0) {
						$carac_client_name_shipping = pdfBuildThirdpartyName($object->contact, $outputlangs);
						$shippingMode = version_compare(DOL_VERSION, '25.0.0-alpha', '>=') ? 'targetshipping' : 'target';
						$carac_client_shipping = pdf_build_address($outputlangs, $this->emetteur, $companystatic, $object->contact, 1, $shippingMode, $object);
					}
				}
				if (!empty($carac_client_shipping)) {
					$posy += $hautcadre;

					$hautcadre -= 10;	// Height for the shipping address does not need to be as high as main box

					// Show shipping frame
					$pdf->SetXY($posx + 2, $posy - 5);
					$pdf->SetFont('', '', $default_font_size - 2);
					$pdf->MultiCell($widthrecbox, 0, $outputlangs->transnoentities('ShippingTo'), 0, 'L', false);
					$pdf->RoundedRect($posx, $posy, $widthrecbox, $hautcadre, $this->corner_radius, '1234', 'D');

					// Show shipping name
					$pdf->SetXY($posx + 2, $posy + 3);
					$pdf->SetFont('', 'B', $default_font_size);
					$pdf->MultiCell($widthrecbox - 2, 2, $carac_client_name_shipping, '', 'L');

					$posy = $pdf->getY();

					// Show shipping information
					$pdf->SetXY($posx + 2, $posy);
					$pdf->SetFont('', '', $default_font_size - 1);
					$pdf->MultiCell($widthrecbox - 2, 2, $carac_client_shipping, '', 'L');

					$shipp_shift += $hautcadre + 10;
				}
			}
		}

		$pdf->SetTextColor(0, 0, 0);

		return array('top_shift' => $top_shift, 'shipp_shift' => $shipp_shift);
	}

	/**
	 * Render the native linked objects below the previous cell, including wrapped references.
	 *
	 * @param TCPDF $pdf PDF renderer
	 * @param Facture $object Invoice
	 * @param Translate $outputlangs Output language
	 * @param float $posx Left edge of the reference column
	 * @param float $posy Bottom of the previous cell
	 * @param float $width Reference column width
	 * @param float $fontSize Default PDF font size
	 * @param float $gap Gap between cells
	 * @return float Bottom of the rendered references
	 */
	private function drawHeaderLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, $width, $fontSize, $gap)
	{
		global $hookmanager;

		if (version_compare(DOL_VERSION, '25.0.0-alpha', '>=') && is_object($hookmanager)) {
			$parameters = array(
				'pdf' => $pdf, 'outputlangs' => $outputlangs, 'posx' => $posx,
				'posy' => $posy, 'w' => $width, 'h' => 3, 'align' => 'R', 'default_font_size' => $fontSize,
			);
			$action = '';
			$reshook = $hookmanager->executeHooks('pdf_writeLinkedObjects', $parameters, $object, $action);
			$posy = max($posy, $pdf->GetY());
			if ($reshook > 0) {
				return max($posy, (float) ($hookmanager->resArray['posy'] ?? $posy));
			}
		}

		$pdf->SetFont('', '', (float) $fontSize - 2);
		foreach (pdf_getLinkedObjects($object, $outputlangs) as $linkedobject) {
			$reference = $linkedobject['ref_title'].' : '.$linkedobject['ref_value'];
			if (!empty($linkedobject['date_value'])) {
				$reference .= ' / '.$linkedobject['date_value'];
			}
			$pdf->SetXY($posx, $posy + $gap);
			$pdf->MultiCell($width, 3, $reference, '', 'R');
			$posy = $pdf->GetY();
		}

		return $posy;
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
