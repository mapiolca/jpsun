<?php
/* Copyright (C) 2026	Pierre Ardoin	<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       core/modules/bank/doc/pdf_jpsunsepamandate.modules.php
 * \ingroup    jpsun
 * \brief      JPSUN SEPA mandate PDF template for thirdparty bank accounts.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/bank/modules_bank.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/companybankaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once dirname(__DIR__, 4).'/lib/jpsun_sepa_mandate_pdf.lib.php';

/**
 * Class to generate JPSUN SEPA mandate documents.
 */
class pdf_jpsunsepamandate extends ModeleBankAccountDoc
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Model name
	 */
	public $name;

	/**
	 * @var string Model description
	 */
	public $description;

	/**
	 * @var int Save generated file as main document
	 */
	public $update_main_doc_field;

	/**
	 * @var string Document type
	 */
	public $type;

	/**
	 * @var string Model version
	 */
	public $version = 'dolibarr';

	/**
	 * @var Societe
	 */
	public $emetteur;

	/**
	 * @var float|int
	 */
	public $corner_radius;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$langs->loadLangs(array('main', 'bank', 'withdrawals', 'companies', 'jpsun@jpsun'));

		$this->db = $db;
		$this->name = 'jpsunsepamandate';
		$this->description = $langs->transnoentitiesnoconv('JpsunSepaMandateModelDescription');
		$this->update_main_doc_field = 1;
		$this->type = 'pdf';

		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
		$this->corner_radius = getDolGlobalInt('MAIN_PDF_FRAME_CORNER_RADIUS', 1);
		$this->option_logo = 1;
		$this->option_multilang = 1;
		$this->option_freetext = 1;

		$this->emetteur = $mysoc;
		if (is_object($this->emetteur) && empty($this->emetteur->country_code)) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2);
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Build PDF on disk.
	 *
	 * @param CompanyBankAccount $object Bank account object
	 * @param Translate $outputlangs Output language
	 * @param string $srctemplatepath Source template path
	 * @param int $hidedetails Hide details
	 * @param int $hidedesc Hide description
	 * @param int $hideref Hide reference
	 * @param array<string,mixed>|null $moreparams More parameters
	 * @return int 1 if OK, 0 if KO, <0 on error
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		// phpcs:enable
		global $conf, $hookmanager, $langs, $user, $action;

		if (!$object instanceof CompanyBankAccount) {
			dol_syslog(get_class($this).'::write_file expects a CompanyBankAccount object', LOG_ERR);
			return -1;
		}

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		if (getDolGlobalString('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}
		$outputlangs->loadLangs(array('main', 'dict', 'bank', 'withdrawals', 'companies', 'jpsun@jpsun'));

		$forcedDir = (is_array($moreparams) && !empty($moreparams['force_dir_output'])) ? (string) $moreparams['force_dir_output'] : '';
		$defaultDir = !empty($conf->bank->dir_output) ? (string) $conf->bank->dir_output : '';
		if ($forcedDir === '' && $defaultDir === '') {
			$this->error = $outputlangs->transnoentitiesnoconv('ErrorConstantNotDefined', 'BANK_OUTPUTDIR');
			return 0;
		}

		if (!empty($object->specimen)) {
			$dir = ($forcedDir !== '') ? $forcedDir : $defaultDir;
			$file = $dir.'/SPECIMEN.pdf';
		} else {
			$objectref = !empty($object->ref) ? dol_sanitizeFileName((string) $object->ref) : 'sepa-mandate-'.((int) $object->id);
			$dir = ($forcedDir !== '') ? $forcedDir : $defaultDir.'/'.$objectref;
			$rum = dol_sanitizeFileName((string) $object->rum);
			$filename = dol_sanitizeFileName($outputlangs->transnoentitiesnoconv('SepaMandateShort')).'_'.$objectref;
			if ($rum !== '') {
				$filename .= '-'.$rum;
			}
			$file = $dir.'/'.$filename.'.pdf';
		}

		if (!file_exists($dir) && dol_mkdir($dir) < 0) {
			$this->error = $outputlangs->transnoentitiesnoconv('ErrorCanNotCreateDir', $dir);
			return 0;
		}
		if (!file_exists($dir)) {
			$this->error = $outputlangs->transnoentitiesnoconv('ErrorCanNotCreateDir', $dir);
			return 0;
		}

		if (!is_object($hookmanager)) {
			include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}
		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);
		if ($reshook < 0) {
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
			return -1;
		}

		$pdf = pdf_getInstance($this->format);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs));
		$pdf->Open();
		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetSubject($outputlangs->transnoentities('SepaMandate'));
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getAnonymisableFullName($outputlangs)));
		$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref).' '.$outputlangs->transnoentities('SepaMandate'));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetAutoPageBreak(false, 0);
		if (method_exists($pdf, 'AliasNbPages')) {
			$pdf->AliasNbPages();
		}

		$pdf->AddPage();
		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);
		JpsunSepaMandatePdfRenderer::render(
			$pdf,
			$this->db,
			$object,
			null,
			$outputlangs,
			$this->emetteur,
			array(
				'marge_gauche' => $this->marge_gauche,
				'marge_droite' => $this->marge_droite,
				'page_largeur' => $this->page_largeur,
				'page_hauteur' => $this->page_hauteur,
				'top' => max(24, $this->marge_haute + 14),
				'bottom' => $this->page_hauteur - $this->marge_basse - 12,
				'corner_radius' => $this->corner_radius,
			)
		);
		$this->_pagefoot($pdf, $object, $outputlangs);

		$pdf->Close();
		$pdf->Output($file, 'F');
		dolChmod($file);

		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);
		$this->warnings = $hookmanager->warnings;
		if ($reshook < 0) {
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
			return -1;
		}

		if (getDolGlobalString('MAIN_UMASK')) {
			@chmod($file, octdec(getDolGlobalString('MAIN_UMASK')));
		}

		$this->result = array('fullpath' => $file);

		return 1;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Show page footer.
	 *
	 * @param TCPDF $pdf PDF document
	 * @param CompanyBankAccount $object Bank account object
	 * @param Translate $outputlangs Output language
	 * @param int $hidefreetext Hide free text
	 * @return int
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		// phpcs:enable
		return pdf_pagefoot($pdf, $outputlangs, 'PAYMENTORDER_FREE_TEXT', null, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, 0, $hidefreetext);
	}
}
