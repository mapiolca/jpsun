<?php
/* Copyright (C) 2025  Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
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
 * \file       core/modules/fichinter/doc/pdf_fichinterjpsun.modules.php
 * \ingroup    fichinter
 * \brief      PDF model with fixed signature placement for interventions
 * @author     Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 */

dol_include_once('/core/modules/fichinter/modules_fichinter.php');
dol_include_once('/core/lib/company.lib.php');
dol_include_once('/core/lib/pdf.lib.php');
dol_include_once('/core/lib/functions2.lib.php');

if (!class_exists('ModelePDFFichinter')) {
	// EN: Abort if the base intervention PDF model is not available
	dol_print_error(null, 'Class ModelePDFFichinter not found');
	return;
}

/**
 * Class to manage generation of intervention document JPSUN with fixed signature
 */
class pdf_fichinterjpsun extends ModelePDFFichinter
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string model name
	 */
	public $name;

	/**
	 * @var string model description
	 */
	public $description;

	/**
	 * @var string document type
	 */
	public $type = 'pdf';

	/**
	 * @var int Save the name of generated file as the main doc
	 */
	public $update_main_doc_field = 1;

	/**
	 * @var int page width
	 */
	public $page_largeur;

	/**
	 * @var int page height
	 */
	public $page_hauteur;

	/**
	 * @var array format
	 */
	public $format;

	/**
	 * @var int left margin
	 */
	public $marge_gauche = 10;

	/**
	 * @var int right margin
	 */
	public $marge_droite = 10;

	/**
	 * @var int top margin
	 */
	public $marge_haute = 10;

	/**
	 * @var int bottom margin
	 */
	public $marge_basse = 10;

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		global $langs;

		$this->db = $db;
		$this->name = 'fichinterjpsun';
		$langs->loadLangs(array('main', 'companies', 'interventions', 'jpsun@jpsun'));
		$this->description = $langs->trans('FichinterJpsunDescription');

		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
	}

	/**
	 * Write the document to disk
	 *
	 * @param	Fichinter	$object		Object to generate
	 * @param	Translate	$outputlangs	Lang object
	 * @param	string		$srctemplatepath	Source template path
	 * @param	int		$hidedetails	Do not show details
	 * @param	int		$hidedesc	Do not show description
	 * @param	int		$hideref	Do not show ref
	 * @return int|void				0 if KO, 1 if OK
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $user, $conf, $langs, $mysoc, $hookmanager, $action;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		$outputlangs->loadLangs(array('main', 'companies', 'interventions', 'products', 'jpsun@jpsun'));

		if ($conf->fichinter->multidir_output[$conf->entity]) {
			$object->fetch_thirdparty();

			if ($object->specimen) {
				$dir = $conf->fichinter->multidir_output[$conf->entity];
				$file = $dir.'/SPECIMEN.pdf';
			} else {
				$objectref = dol_sanitizeFileName($object->ref);
				$dir = $conf->fichinter->multidir_output[$conf->entity].'/'.$objectref;
				$file = $dir.'/'.$objectref.'.pdf';
			}

			if (!file_exists($dir)) {
				if (dol_mkdir($dir) < 0) {
					$this->error = $langs->transnoentitiesnoconv('ErrorCanNotCreateDir', $dir);
					return 0;
				}
			}

			if (!file_exists($dir)) {
				$this->error = $langs->transnoentitiesnoconv('ErrorDirDoesNotExists', $dir);
				return 0;
			}

			if (!is_object($hookmanager)) {
				include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
				$hookmanager = new HookManager($this->db);
			}
			$hookmanager->initHooks(array('pdfgeneration'));

			$pdf = pdf_getInstance($this->format);
			$default_font_size = pdf_getPDFFontSize($outputlangs);
			// EN: Use bottom margin to avoid unexpected automatic page breaks pushing signature placeholders
			$pdf->SetAutoPageBreak(1, $this->marge_basse);
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
			$pdf->SetFont(pdf_getPDFFont($outputlangs));
			$pdf->Open();

			$this->emetteur = $mysoc;
			if (!empty($conf->global->MAIN_INFO_SOCIETE_NOM) && !empty($conf->global->MAIN_SHOW_CUSTOMER_NAME_KEYWORD)) {
				$this->emetteur->name = $conf->global->MAIN_INFO_SOCIETE_NOM;
			}

			$pdf->AddPage();
			$this->_pagehead($pdf, $object, 1, $outputlangs);
			$posy = $this->marge_haute + 40;

			$tab_width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
			$line_height = 6;

			foreach ($object->lines as $line) {
				$desc = dol_htmlentitiesbr($line->desc, 1);
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->MultiCell($tab_width, $line_height, $outputlangs->convToOutputCharset($desc), 0, 'L', 0);
				$posy = $pdf->GetY() + 2;

				if ($posy > ($this->page_hauteur - 60)) {
					$pdf->AddPage();
					$this->_pagehead($pdf, $object, 0, $outputlangs);
					$posy = $this->marge_haute + 20;
				}
			}

			$signatureInfo = $this->drawSignatureArea($pdf, $object, $posy, $outputlangs);
			$this->addSignatureImage($pdf, $object, $signatureInfo, $outputlangs);

			$this->_pagefoot($pdf, $object, $outputlangs);
			if (method_exists($pdf, 'AliasNbPages')) {
				$pdf->AliasNbPages();
			}

			$pdf->Close();

			$hookmanager->executeHooks('beforePDFCreation', array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs), $object, $action);
			dol_delete_file($file, 0, 0, 0, $object);

			if ($pdf->Output($file, 'F') <= 0) {
				$this->error = $langs->transnoentities('ErrorCanNotCreateFile', $file);
				return 0;
			}

			$hookmanager->executeHooks('afterPDFCreation', array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs), $object, $action);
			return 1;
		}

		$this->error = $langs->transnoentities('ErrorModuleSetupNotComplete');
		return 0;
	}

	/**
	 * Draw signature placeholder and return its coordinates
	 *
	 * @param	TCPDF		$pdf		PDF
	 * @param	Fichinter	$object		Intervention object
	 * @param	float		$posy		Current Y position
	 * @param	Translate	$outputlangs	Langs
	 * @return array
	 */
	protected function drawSignatureArea(&$pdf, $object, $posy, $outputlangs)
	{
		$blockHeight = 30;
		$minSpace = $blockHeight + 20;
		if ($posy + $minSpace > ($this->page_hauteur - $this->marge_basse - 20)) {
			$pdf->AddPage();
			$this->_pagehead($pdf, $object, 0, $outputlangs);
			$posy = $this->marge_haute + 20;
		}

		$posx = $this->marge_gauche;
		$width = ($this->page_largeur - $this->marge_gauche - $this->marge_droite) / 2 - 2;
		$posmiddle = $posx + $width + 4;
		$posyblock = $posy + 6;

		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetXY($posx, $posy);
		$pdf->SetFont('', '', pdf_getPDFFontSize($outputlangs));
		$pdf->MultiCell($width, 5, $outputlangs->transnoentities('ContactNameAndSignature', $this->emetteur->name), 0, 'L', 0);
		$pdf->RoundedRect($posx, $posyblock, $width, $blockHeight, 2, '1234', 'D');

		$pdf->SetXY($posmiddle, $posy);
		$pdf->MultiCell($width, 5, $outputlangs->transnoentities('ContactNameAndSignature', $object->thirdparty->name), 0, 'L', 0);
		$pdf->RoundedRect($posmiddle, $posyblock, $width, $blockHeight, 2, '1234', 'D');

		return array(
			'page' => $pdf->getPage(),
			'posx' => $posmiddle,
			'posy' => $posyblock,
			'width' => $width,
			'height' => $blockHeight
		);
	}

	/**
	 * Render signature image at stored coordinates
	 *
	 * @param	TCPDF		$pdf		PDF
	 * @param	Fichinter	$object		Intervention object
	 * @param	array		$signatureInfo	Position and page
	 * @param	Translate	$outputlangs	Langs
	 * @return void
	 */
	protected function addSignatureImage(&$pdf, $object, $signatureInfo, $outputlangs)
	{
		$signatureFile = $this->findSignatureFile($object);
		if (empty($signatureFile)) {
			return;
		}

		$currentPage = $pdf->getPage();
		$targetPage = empty($signatureInfo['page']) ? $currentPage : $signatureInfo['page'];
		$pageCount = $pdf->getNumPages();
		if (!empty($pageCount) && $targetPage > $pageCount) {
			// EN: Clamp the target page to the last generated page if offsets drift
			$targetPage = $pageCount;
		}
		$pdf->setPage($targetPage, true);

		$margin = 2;
		$targetWidth = $signatureInfo['width'] - ($margin * 2);
		$targetHeight = $signatureInfo['height'] - ($margin * 2);
		$pdf->Image($signatureFile, $signatureInfo['posx'] + $margin, $signatureInfo['posy'] + $margin, $targetWidth, $targetHeight);

		$pdf->setPage($currentPage);
	}

	/**
	 * Try to locate stored signature file for the intervention
	 *
	 * @param	Fichinter	$object	Intervention object
	 * @return string
	 */
	protected function findSignatureFile($object)
	{
		global $conf;

		$objectref = dol_sanitizeFileName($object->ref);
		$dir = $conf->fichinter->multidir_output[$object->entity].'/'.$objectref;
		if (!is_dir($dir)) {
			return '';
		}

		$fileList = dol_dir_list($dir, 'files', 0, 'signature', '\\.(png|jpg|jpeg)$');
		if (!empty($fileList[0]['fullname'])) {
			return $fileList[0]['fullname'];
		}

		return '';
	}
}
