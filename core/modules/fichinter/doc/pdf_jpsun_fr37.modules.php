<?php
/* Copyright (C) 2026 JPSUN
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Combined intervention + FR37 PDF model.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/fichinter/modules_fichinter.php';
require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

dol_include_once('/jpsun/class/jpsunfichinterfr37.class.php');

class pdf_jpsun_fr37 extends ModelePDFFicheinter
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $name;

	/** @var string */
	public $description;

	/** @var int */
	public $update_main_doc_field;

	/** @var string */
	public $type;

	/** @var string */
	public $version = 'dolibarr';

	/** @var Societe */
	public $emetteur;

	public function __construct($db)
	{
		global $langs, $mysoc;

		$this->db = $db;
		$this->name = 'jpsun_fr37';
		$this->description = $langs->trans('DocModelJpsunFr37Description');
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
		$this->corner_radius = getDolGlobalInt('MAIN_PDF_FRAME_CORNER_RADIUS', 0);
		$this->option_logo = 1;
		$this->option_multilang = 1;
		$this->option_draft_watermark = 1;

		$this->emetteur = $mysoc;
		if (empty($this->emetteur->country_code)) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2);
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $conf, $langs;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		$outputlangs->loadLangs(array('main', 'interventions', 'companies', 'products', 'jpsun@jpsun'));

		if (getDolGlobalString('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		$objectref = dol_sanitizeFileName($object->ref);
		$baseOutput = rtrim((string) getMultidirOutput($object), '/');
		if (empty($baseOutput)) {
			$this->error = $langs->transnoentities('ErrorConstantNotDefined', 'FICHINTER_OUTPUTDIR');
			return 0;
		}

		$dir = $baseOutput.'/'.$objectref;
		if (!dol_is_dir($dir) && dol_mkdir($dir) < 0) {
			$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
			return 0;
		}

		$file = $dir.'/'.$objectref.'_FR37.pdf';
		$report = new JpsunFichinterFr37($this->db);
		$report->fetchByFichinter($object->id);

		if (method_exists($object, 'fetch_thirdparty')) {
			$object->fetch_thirdparty();
		}

		$pdf = pdf_getInstance($this->format);
		if (method_exists($pdf, 'setPrintHeader')) {
			$pdf->setPrintHeader(false);
		}
		if (method_exists($pdf, 'setPrintFooter')) {
			$pdf->setPrintFooter(false);
		}
		$pdf->SetAutoPageBreak(true, 0);
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetCreator('Dolibarr');
		$pdf->SetTitle($objectref.' - FR37');
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', pdf_getPDFFontSize($outputlangs));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}

		$basePdf = $this->generateStandardFichinterPdf($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref, $dir, $objectref);
		if ($basePdf && dol_is_file($basePdf)) {
			$this->importPdf($pdf, $basePdf);
		}

		$pdf->AddPage();
		$this->_pagehead($pdf, $object, $outputlangs);
		$this->printReport($pdf, $object, $report, $outputlangs);
		$this->_pagefoot($pdf, $object, $outputlangs);

		$this->printConsuelPages($pdf, $object, $report, $outputlangs);
		$this->printSignaturePage($pdf, $object, $outputlangs);

		$pdf->Output($file, 'F');
		$this->result = array('fullpath' => $file);

		return 1;
	}

	/**
	 * @return string
	 */
	protected function out($outputlangs, $text)
	{
		$text = (string) $text;
		if (method_exists($outputlangs, 'convToOutputCharset')) {
			return $outputlangs->convToOutputCharset($text);
		}

		return $text;
	}

	/**
	 * @return string
	 */
	protected function formatBool($outputlangs, $value)
	{
		return $value ? $outputlangs->trans('Yes') : $outputlangs->trans('No');
	}

	/**
	 * @return string
	 */
	protected function formatList($value)
	{
		$decoded = json_decode((string) $value, true);
		return is_array($decoded) ? implode(', ', $decoded) : '';
	}

	/**
	 * @return string
	 */
	protected function generateStandardFichinterPdf($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref, $dir, $objectref)
	{
		$modelFile = DOL_DOCUMENT_ROOT.'/core/modules/fichinter/doc/pdf_soleil.modules.php';
		if (!dol_is_file($modelFile)) {
			return '';
		}

		require_once $modelFile;
		if (!class_exists('pdf_soleil')) {
			return '';
		}

		$model = new pdf_soleil($this->db);
		$result = $model->write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);
		if ($result <= 0) {
			return '';
		}
		if (!empty($model->result['fullpath']) && dol_is_file($model->result['fullpath'])) {
			return $model->result['fullpath'];
		}

		$candidate = $dir.'/'.$objectref.'.pdf';
		return dol_is_file($candidate) ? $candidate : '';
	}

	/**
	 * @return int
	 */
	protected function importPdf($pdf, $filepath)
	{
		if (!method_exists($pdf, 'setSourceFile') || !method_exists($pdf, 'importPage')) {
			return 0;
		}

		try {
			$pagecount = $pdf->setSourceFile($filepath);
			for ($page = 1; $page <= $pagecount; $page++) {
				$tpl = $pdf->importPage($page);
				$size = $pdf->getTemplateSize($tpl);
				$width = isset($size['width']) ? $size['width'] : $size['w'];
				$height = isset($size['height']) ? $size['height'] : $size['h'];
				$orientation = isset($size['orientation']) ? $size['orientation'] : ($height > $width ? 'P' : 'L');
				$pdf->AddPage($orientation, array($width, $height));
				$pdf->useTemplate($tpl, 0, 0, $width, $height, true);
			}

			return (int) $pagecount;
		} catch (Exception $e) {
			return 0;
		}
	}

	protected function printReport($pdf, $object, $report, $outputlangs)
	{
		$values = $report->values;
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', pdf_getPDFFontSize($outputlangs) - 1);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetY(44);

		$this->section($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37Context'));
		$address = '';
		if (!empty($object->thirdparty->id)) {
			$address = $object->thirdparty->name."\n".$object->thirdparty->getFullAddress(1);
		}
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37CustomerAddress'), $address);
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('Ref'), $object->ref);
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37InterventionDate'), !empty($object->datei) ? dol_print_date($object->datei, 'dayhour', false, $outputlangs, true) : '');

		$this->section($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37InterventionObject'));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37PresentOnSite'), $this->formatBool($outputlangs, $values['present_on_site']));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37InterventionObject'), $values['intervention_object']);
		if ($values['intervention_object_other']) {
			$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37ObjectOther'), $values['intervention_object_other']);
		}

		$this->section($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37Installation'));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37PanelsRef'), $report->getProductLabelsText(JpsunFichinterFr37::PRODUCT_ROLE_PANEL));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37InvertersSold'), $report->getProductLabelsText(JpsunFichinterFr37::PRODUCT_ROLE_INVERTER_SOLD));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37PanelQty'), $values['panel_qty']);
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37RoofType'), trim($values['roof_type'].' '.$values['roof_type_other']));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37InstallType'), trim($values['install_type'].' '.$values['install_type_other']));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37RoofAccess'), $this->formatList($values['roof_access_json']));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37ElectricalConnection'), $values['electrical_connection']);

		$this->section($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37RiskAnalysis'));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37Risks'), $this->formatList($values['risk_identified_json']));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37RiskOther'), $values['risk_other']);
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37PreventionMeasures'), $this->formatList($values['prevention_measures_json']));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37CollectiveProtection'), $this->formatList($values['collective_protection_json']));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37IndividualProtection'), $this->formatList($values['individual_protection_json']));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37SafetyRules'), $this->formatList($values['safety_rules_json']));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37SafetyInstructions'), $values['safety_instructions']);

		$this->section($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37SoldWork'));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37InverterLocation'), $values['inverter_location']);
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37PanelLayout'), $values['panel_layout']);

		$this->section($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37WorksDone'));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37WorksDone'), $values['works_done']);
		$this->printPhotos($pdf, $object, $outputlangs, 'before', $outputlangs->trans('JpsunFr37BeforePhotos'));
		$this->printPhotos($pdf, $object, $outputlangs, 'after', $outputlangs->trans('JpsunFr37AfterPhotos'));

		$this->section($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37Tests'));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37ConsuelCase'), $values['consuel_case']);
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37CheckDcConnectors'), $this->formatBool($outputlangs, $values['check_dc_connectors']));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37CheckAcBox'), $this->formatBool($outputlangs, $values['check_ac_box']));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37CheckCablesTrunking'), $this->formatBool($outputlangs, $values['check_cables_trunking']));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37CheckGrounds'), $this->formatBool($outputlangs, $values['check_grounds']));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37CheckLabels'), $this->formatBool($outputlangs, $values['check_labels']));
		$this->printStrings($pdf, $object, $report, $outputlangs);
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37EarthValue'), $values['earth_value']);
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37InverterType'), $values['inverter_type']);
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37InverterSerial'), $values['inverter_serial']);
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37InverterPower'), $values['inverter_power']);
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37Connection'), $values['connection_type']);
		if ($values['connection_type'] === 'WIFI') {
			$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37WifiReason'), $values['wifi_reason']);
		}
		if ($values['connection_type'] === 'CELLULAR') {
			$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37SimInfo'), $values['sim_info']);
		}

		$this->section($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37ObservationsConclusion'));
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37ObservationsConclusion'), $values['observations_conclusion']);
	}

	protected function section($pdf, $object, $outputlangs, $title)
	{
		$this->pageBreakIfNeeded($pdf, $object, $outputlangs, 12);
		$pdf->Ln(2);
		$pdf->SetFillColor(230, 230, 230);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', 'B', pdf_getPDFFontSize($outputlangs));
		$pdf->Cell(0, 7, $this->out($outputlangs, $title), 0, 1, 'L', true);
		$pdf->SetFont('', '', pdf_getPDFFontSize($outputlangs) - 1);
	}

	protected function row($pdf, $object, $outputlangs, $label, $value)
	{
		$value = trim((string) $value);
		if ($value === '') {
			return;
		}

		$this->pageBreakIfNeeded($pdf, $object, $outputlangs, 10);
		$x = $this->marge_gauche;
		$y = $pdf->GetY();
		$labelWidth = 58;
		$valueWidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite - $labelWidth;
		$pdf->SetFont('', 'B', pdf_getPDFFontSize($outputlangs) - 1);
		$pdf->SetXY($x, $y);
		$pdf->MultiCell($labelWidth, 5, $this->out($outputlangs, $label), 0, 'L', false, 0);
		$pdf->SetFont('', '', pdf_getPDFFontSize($outputlangs) - 1);
		$pdf->SetXY($x + $labelWidth, $y);
		$pdf->MultiCell($valueWidth, 5, $this->out($outputlangs, $value), 0, 'L', false, 1);
		$pdf->Ln(1);
	}

	protected function printStrings($pdf, $object, $report, $outputlangs)
	{
		if (empty($report->strings)) {
			return;
		}

		$rows = array();
		foreach ($report->strings as $row) {
			$rows[] = $outputlangs->trans('JpsunFr37StringNo').' '.$row['string_no'].' : '.$row['voltage'].' V - '.$row['pv_count'].' PV';
		}
		$this->row($pdf, $object, $outputlangs, $outputlangs->trans('JpsunFr37StringVoltages'), implode("\n", $rows));
	}

	protected function printPhotos($pdf, $object, $outputlangs, $bucket, $title)
	{
		$photos = JpsunFichinterFr37::getPhotos($object, $bucket);
		if (empty($photos)) {
			return;
		}

		$this->section($pdf, $object, $outputlangs, $title);
		$maxWidth = 58;
		$maxHeight = 40;
		$x = $this->marge_gauche;
		foreach ($photos as $photo) {
			$path = $photo['fullname'];
			if (!dol_is_file($path)) {
				continue;
			}
			$this->pageBreakIfNeeded($pdf, $object, $outputlangs, $maxHeight + 12);
			$y = $pdf->GetY();
			try {
				$pdf->Image($path, $x, $y, $maxWidth, $maxHeight, '', '', '', true);
			} catch (Exception $e) {
				continue;
			}
			$pdf->SetXY($x, $y + $maxHeight + 1);
			$pdf->SetFont('', '', pdf_getPDFFontSize($outputlangs) - 2);
			$pdf->MultiCell($maxWidth, 4, $this->out($outputlangs, $photo['name']), 0, 'L');
			$x += $maxWidth + 5;
			if ($x + $maxWidth > $this->page_largeur - $this->marge_droite) {
				$x = $this->marge_gauche;
				$pdf->Ln(4);
			}
		}
		$pdf->Ln(4);
	}

	protected function printConsuelPages($pdf, $object, $report, $outputlangs)
	{
		$cases = array();
		if (!empty($report->values['consuel_case'])) {
			$case = $report->getConsuelCase($report->values['consuel_case']);
			if ($case) {
				$cases[] = $case;
			}
		}
		if (empty($cases)) {
			$cases = $report->getConsuelCases();
		}

		foreach ($cases as $case) {
			$pdf->AddPage();
			$this->_pagehead($pdf, $object, $outputlangs);
			$pdf->SetY(44);
			$this->section($pdf, $object, $outputlangs, $case->label);
			$this->row($pdf, $object, $outputlangs, $outputlangs->trans('Description'), $case->description);
			$image = DOL_DOCUMENT_ROOT.'/custom/jpsun/img/consuel/'.basename($case->illustration);
			if (!dol_is_file($image)) {
				$image = dol_buildpath('/jpsun/img/consuel/'.basename($case->illustration), 0);
			}
			if (dol_is_file($image)) {
				$maxWidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
				$maxHeight = $this->page_hauteur - $pdf->GetY() - $this->marge_basse - 18;
				try {
					$pdf->Image($image, $this->marge_gauche, $pdf->GetY() + 2, $maxWidth, $maxHeight, '', '', '', true, 300, '', false, false, 0, true);
				} catch (Exception $e) {
					$this->row($pdf, $object, $outputlangs, $outputlangs->trans('File'), basename($image));
				}
			}
			$this->_pagefoot($pdf, $object, $outputlangs);
		}
	}

	protected function printSignaturePage($pdf, $object, $outputlangs)
	{
		$pdf->AddPage();
		$this->_pagehead($pdf, $object, $outputlangs);
		$pdf->SetY(55);
		$this->section($pdf, $object, $outputlangs, $outputlangs->trans('Signature'));
		$pdf->SetFont('', '', pdf_getPDFFontSize($outputlangs));
		$pdf->MultiCell(0, 6, $this->out($outputlangs, $outputlangs->trans('CustomerSignature')), 0, 'L');

		$x = 120;
		$y = 210;
		$w = 70;
		$h = 25;
		$pdf->SetDrawColor(120, 120, 120);
		$pdf->Rect($x, $y, $w, $h);
		$pdf->SetXY($x, $y - 7);
		$pdf->SetFont('', '', pdf_getPDFFontSize($outputlangs) - 1);
		$pdf->Cell($w, 5, $this->out($outputlangs, $outputlangs->trans('Signature')), 0, 1, 'L');
		$this->_pagefoot($pdf, $object, $outputlangs);
	}

	protected function pageBreakIfNeeded($pdf, $object, $outputlangs, $height)
	{
		if ($pdf->GetY() + $height > $this->page_hauteur - $this->marge_basse - 16) {
			$this->_pagefoot($pdf, $object, $outputlangs);
			$pdf->AddPage();
			$this->_pagehead($pdf, $object, $outputlangs);
			$pdf->SetY(44);
		}
	}

	protected function _pagehead($pdf, $object, $outputlangs)
	{
		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', pdf_getPDFFontSize($outputlangs) + 3);
		$pdf->SetXY($this->marge_gauche, $this->marge_haute);
		$pdf->MultiCell(100, 5, $this->out($outputlangs, $outputlangs->trans('JpsunFr37Report')), 0, 'L');

		$pdf->SetFont('', '', pdf_getPDFFontSize($outputlangs) - 1);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetXY($this->marge_gauche, $this->marge_haute + 15);
		$pdf->MultiCell(120, 5, $this->out($outputlangs, $outputlangs->trans('Ref').' '.$object->ref), 0, 'L');
		if (!empty($object->thirdparty->name)) {
			$pdf->SetXY($this->marge_gauche, $this->marge_haute + 21);
			$pdf->MultiCell(120, 5, $this->out($outputlangs, $object->thirdparty->name), 0, 'L');
		}
	}

	protected function _pagefoot($pdf, $object, $outputlangs)
	{
		$showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);
		return pdf_pagefoot($pdf, $outputlangs, 'FICHINTER_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails);
	}
}
