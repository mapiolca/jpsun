<?php
/* Copyright (C) 2026 JPSUN
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/project/modules_project.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

if (isModEnabled('propal') && getDolGlobalInt('JPSUN_PROJECTSYNTHESIS_SHOW_PROPOSAL')) {
	require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
}
if (isModEnabled('commande') && getDolGlobalInt('JPSUN_PROJECTSYNTHESIS_SHOW_ORDER')) {
	require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
}
if (isModEnabled('intervention') && getDolGlobalInt('JPSUN_PROJECTSYNTHESIS_SHOW_FICHINTER')) { // module "Interventions"
	require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
}
if (isModEnabled('stocktransfer') && getDolGlobalInt('JPSUN_PROJECTSYNTHESIS_SHOW_STOCKTRANSFER')) {
	//$stocktransferpath = DOL_DOCUMENT_ROOT.'/product/stock/stocktransfer/class/stocktransfer.class.php';
	if (file_exists($stocktransferpath)) {
		//require_once $stocktransferpath;
	}
}

class pdf_jpsun_projectsynthesis extends ModelePDFProjects
{
	public $db;
	public $name;
	public $description;
	public $update_main_doc_field;
	public $type;
	public $version = 'dolibarr';

	public function __construct($db)
	{
		global $langs, $mysoc;

		$this->db = $db;

		$langs->loadLangs(array('main', 'projects', 'companies'));

		$this->name = 'jpsun_projectsynthesis';
		$this->description = $langs->trans('JPSUNProjectPdfSynthesis');
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

		$this->option_logo = 1;
		$this->option_tva = 1;
		
		// Define position of columns
		$this->posxref = $this->marge_gauche + 1;
		$this->posxlabel = $this->marge_gauche + 25;
		$this->posxworkload = $this->marge_gauche + 117;
		$this->posxprogress = $this->marge_gauche + 137;
		$this->posxdatestart = $this->marge_gauche + 147;
		$this->posxdateend = $this->marge_gauche + 169;

		$this->emetteur = $mysoc;
		if (!$this->emetteur->country_code) {
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

		$outputlangs->loadLangs(array('main', 'projects', 'companies', 'jpsun@jpsun'));

		if (getDolGlobalString('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		if (empty($conf->project->multidir_output[$object->entity])) {
			$this->error = $langs->transnoentities("ErrorConstantNotDefined", "PROJECT_OUTPUTDIR");
			return 0;
		}

		$objectref = dol_sanitizeFileName($object->ref);
		$dir = $conf->project->multidir_output[$object->entity];

		if (!preg_match('/specimen/i', $objectref)) {
			$dir .= "/".$objectref;
		}

		if (!dol_is_dir($dir)) {
			if (dol_mkdir($dir) < 0) {
				$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		}

		$file = $dir.'/'.$objectref.'_SYNTHESIS.pdf';

		/*
		 * 1) Récupérer les objets liés + collecter TOUS les fichiers
		 * - Exclure *_preview.png
		 * - Si une variante signée existe (ex: xxx_signed-20260105115921.pdf), ne garder QUE la/les signée(s)
		 *   -> et s'il y en a plusieurs, garder la plus récente (timestamp du nom, sinon mtime)
		 * - Tous les fichiers retenus sont embarqués en pièces jointes
		 * - Les PDF sont aussi ajoutés en pages (si TCPDI disponible), sinon ils restent en PJ
		 */
		$linked = $this->fetchAllLinkedObjects($object);

		$filesIndex = array(
			'files_by_object' => array(),	// key => array(files)
			'attach' => array(),			// path => label (TOUT)
			'pdf' => array(),				// path => label (PDF à concat)
		);

		$this->collectAllFilesFromLinkedObjects($linked, $filesIndex, $file);

		/*
		 * 2) Générer le PDF (synthèse + annexes)
		 */
		$pdf = pdf_getInstance($this->format);
		$default_font_size = pdf_getPDFFontSize($outputlangs); // Must be after pdf_getInstance
		$pdf->setAutoPageBreak(true, 0);
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetCreator('Dolibarr');
		$pdf->SetTitle($objectref.' - '.$outputlangs->trans('Synthesis'));
		$pdf->AddPage();
		
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right
		$this->_pagehead($pdf, $object, 0, $outputlangs);
        $pdf->SetFont('', '', $default_font_size - 1);
		//$pdf->MultiCell(0, 3, ''); // Set interline to 3
		$pdf->SetTextColor(0, 0, 0);

		$tab_top = 50;
		$tab_top_newpage = (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD') ? 42 : 10);

		$tab_height = $this->page_hauteur - $tab_top - $heightforfooter - $heightforfreetext;

		// Show public note
		// Notes
		$this->printSectionTitle($pdf, $outputlangs->trans('Notes'));
		$notetoshow = empty($object->note_public) ? '' : $object->note_public;
		if ($notetoshow) {
			$substitutionarray = pdf_getSubstitutionArray($outputlangs, null, $object);
			complete_substitutions_array($substitutionarray, $outputlangs, $object);
			$notetoshow = make_substitutions($notetoshow, $substitutionarray, $outputlangs);
			$notetoshow = convertBackOfficeMediasLinksToPublicLinks($notetoshow);

			$tab_top -= 2;

			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->writeHTMLCell(190, 3, $this->posxref - 1, $tab_top - 2, dol_htmlentitiesbr($notetoshow), 0, 1);
			$nexY = $pdf->GetY();
			$height_note = $nexY - $tab_top;

			// Rect takes a length in 3rd parameter
			$pdf->SetDrawColor(192, 192, 192);
			$pdf->RoundedRect($this->marge_gauche, $tab_top - 2, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_note + 2, $this->corner_radius, '1234', 'D');

			$tab_height -= $height_note;
			$tab_top = $nexY + 6;
		} else {
			$height_note = 0;
		}

		// Contacts
		$this->printSectionTitle($pdf, $outputlangs->trans('Contacts'));
		$contacts = $this->getProjectContacts($object, $outputlangs);
		$this->printSimpleTable($pdf, $outputlangs, array(
			$outputlangs->trans('Type'),
			$outputlangs->trans('Role'),
			$outputlangs->trans('Name'),
			$outputlangs->trans('Company'),
			$outputlangs->trans('Email'),
		), $contacts, array(18, 35, 45, 45, 47));
		
		$pdf->Ln(2);

		// Objets liés (Devis / FI / Transfert de stock)
		$this->printSectionTitle($pdf, $outputlangs->trans('LinkedObjects'));
        if (getDolGlobalInt('JPSUN_PROJECTSYNTHESIS_SHOW_PROPOSAL')){
		$this->printLinkedObjectsSectionFromObjects($pdf, $object, $outputlangs, $linked, $filesIndex, 'propal', $outputlangs->trans('Proposals'));
        }
        if (getDolGlobalInt('JPSUN_PROJECTSYNTHESIS_SHOW_ORDER')){
		$this->printLinkedObjectsSectionFromObjects($pdf, $object, $outputlangs, $linked, $filesIndex, 'commande', $outputlangs->trans('Orders'));
        }
        if (getDolGlobalInt('JPSUN_PROJECTSYNTHESIS_SHOW_FICHINTER')){
		$this->printLinkedObjectsSectionFromObjects($pdf, $object, $outputlangs, $linked, $filesIndex, 'fichinter', $outputlangs->trans('Interventions'));
        }
        if (getDolGlobalInt('JPSUN_PROJECTSYNTHESIS_SHOW_STOCKTRANSFERT')){
		$this->printLinkedObjectsSectionFromObjects($pdf, $object, $outputlangs, $linked, $filesIndex, 'stocktransfer', $outputlangs->trans('StockTransfers'));
        }
        $this->_pagefoot($pdf, $object, $outputlangs, 1);
        
		/*
		 * 3) Annexes: liste + PJ + concat PDF
		 */
		$this->printAnnexesAndEmbedFiles($pdf, $outputlangs, $filesIndex);

		$pdf->Output($file, 'F');

		$this->result = array('fullpath'=>$file);
		return 1;
	}

	/* ------------------------------------------------------------
	 *  Linked objects fetch
	 * ------------------------------------------------------------ */

	private function fetchAllLinkedObjects($project)
	{   
		$linked = array(
			'propal' => array('title' => 'Proposals', 'class' => 'Propal', 'table' => 'propal', 'projectkey' => 'fk_projet', 'objects' => array()),
			'commande' => array('title' => 'Orders', 'class' => 'Commande', 'table' => 'commande', 'projectkey' => 'fk_projet', 'objects' => array()),
			'fichinter' => array('title' => 'Interventions', 'class' => 'Fichinter', 'table' => 'fichinter', 'projectkey' => 'fk_projet', 'objects' => array()),
			//'stocktransfer' => array('title' => 'StockTransfers', 'class' => 'StockTransfer', 'table' => 'stocktransfer', 'projectkey' => 'fk_project', 'objects' => array()),
		);

		foreach ($linked as $key => $def) {
			if (!class_exists($def['class'])) {
				continue;
			}

			$ids = $project->get_element_list($key, $def['table'], '', '', '', $def['projectkey']);
			if (!is_array($ids) || empty($ids)) {
				continue;
			}

			foreach ($ids as $id) {
				$o = new $def['class']($this->db);
				if ($o->fetch((int) $id) > 0) {
					$linked[$key]['objects'][] = $o;
				}
			}
		}

		return $linked;
	}

	/* ------------------------------------------------------------
	 *  Files collection + embed
	 * ------------------------------------------------------------ */

	private function collectAllFilesFromLinkedObjects(&$linked, &$filesIndex, $excludeFileFullPath = '')
	{
		foreach ($linked as $key => $def) {
			$srcLabel = $key;
			foreach ($def['objects'] as $o) {
				$objkey = $this->buildObjectKey($key, $o);
				$files = $this->getAllFilesForObject($o, $excludeFileFullPath);

				$filesIndex['files_by_object'][$objkey] = $files;

				foreach ($files as $fullpath) {
					$label = $srcLabel.' - '.$o->ref.' - '.basename($fullpath);

					$filesIndex['attach'][$fullpath] = $label;

					if ($this->isPdfFile($fullpath)) {
						$filesIndex['pdf'][$fullpath] = $label;
					}
				}
			}
		}
	}

	private function getAllFilesForObject($obj, $excludeFileFullPath = '')
	{
		$dir = $this->getObjectDocumentsDir($obj);
		if (empty($dir) || !dol_is_dir($dir)) {
			return array();
		}

		// Récupère tous les fichiers (récursif)
		// Exclure thumbs, .meta, *_preview.png
		$excludepattern = '(\.meta$|\/thumbs\/|_preview\.png$)';
		$list = dol_dir_list($dir, 'files', 1, '', $excludepattern, 'fullname', SORT_ASC, 1);
		if (!is_array($list)) {
			return array();
		}

		/*
		 * Filtre "signed" :
		 * - si xxx_signed-YYYYMMDDhhmmss.ext existe, on ne garde QUE la version signée
		 * - s'il y a plusieurs signées, on garde la plus récente (timestamp du nom, sinon mtime)
		 */
		$selected = array(); // key => array('path'=>..., 'signed'=>0/1, 'score'=>int)

		foreach ($list as $f) {
			$full = $f['fullname'];
			if (empty($full)) continue;
			if (!empty($excludeFileFullPath) && $full === $excludeFileFullPath) continue;
			if (!is_readable($full)) continue;

			// Double sécurité
			if ($this->isPreviewPng($full)) continue;

			list($dedupKey, $isSigned, $score) = $this->getSignedDedupKey($full);

			if (!isset($selected[$dedupKey])) {
				$selected[$dedupKey] = array(
					'path' => $full,
					'signed' => (int) $isSigned,
					'score' => (int) $score,
				);
				continue;
			}

			$curSigned = !empty($selected[$dedupKey]['signed']);
			$curScore = (int) $selected[$dedupKey]['score'];

			// signed > non-signed
			if ($isSigned && !$curSigned) {
				$selected[$dedupKey] = array('path' => $full, 'signed' => 1, 'score' => (int) $score);
				continue;
			}

			// signed vs signed => garder la plus récente
			if ($isSigned && $curSigned) {
				if ((int) $score > $curScore) {
					$selected[$dedupKey] = array('path' => $full, 'signed' => 1, 'score' => (int) $score);
				}
				continue;
			}

			// non-signed vs non-signed => garder la plus récente (optionnel, mais évite doublons exacts)
			if (!$isSigned && !$curSigned) {
				if ((int) $score > $curScore) {
					$selected[$dedupKey] = array('path' => $full, 'signed' => 0, 'score' => (int) $score);
				}
				continue;
			}

			// si on a déjà du signed et que le nouveau ne l'est pas => on ignore
		}

		$res = array();
		foreach ($selected as $v) {
			$res[] = $v['path'];
		}

		$res = array_values(array_unique($res));
		return $res;
	}

	/**
	 * Trouve le bon dossier documents de l'objet sans casser ce qui marche.
	 * Selon les versions/modules, getMultidirOutput peut déjà inclure (ou non) le sous-dossier ref.
	 */
	private function getObjectDocumentsDir($obj)
	{
		$ref = dol_sanitizeFileName((string) $obj->ref);
		if (empty($ref)) return '';

		$candidates = array();

		$base1 = rtrim((string) getMultidirOutput($obj, '', 1), '/');
		if (!empty($base1)) {
			$candidates[] = $base1;
			$candidates[] = $base1.'/'.$ref;
		}

		$modulepart = !empty($obj->element) ? $obj->element : '';
		$base2 = rtrim((string) getMultidirOutput($obj, $modulepart, 1), '/');
		if (!empty($base2) && $base2 !== $base1) {
			$candidates[] = $base2;
			$candidates[] = $base2.'/'.$ref;
		}

		foreach ($candidates as $c) {
			if (!empty($c) && dol_is_dir($c)) {
				return $c;
			}
		}

		return '';
	}

	private function isPdfFile($filepath)
	{
		$ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
		return ($ext === 'pdf');
	}

	private function isPreviewPng($filepath)
	{
		return (preg_match('/_preview\.png$/i', basename($filepath)) ? true : false);
	}

	/**
	 * Retourne :
	 * - dedupKey : permet d'associer "xxx.ext" avec "xxx_signed-YYYYMMDDhhmmss.ext"
	 * - isSigned : 1/0
	 * - score : timestamp du nom si présent, sinon mtime
	 */
	private function getSignedDedupKey($filepath)
	{
		$dir = dirname($filepath);
		$bn = basename($filepath);

		$ext = strtolower(pathinfo($bn, PATHINFO_EXTENSION));
		$name = pathinfo($bn, PATHINFO_FILENAME);

		$isSigned = 0;
		$base = $name;
		$stamp = 0;

		// Match:
		// - xxx_signed
		// - xxx_signed-20260105115921
		// - xxx_signed_20260105115921
		if (preg_match('/^(.*?)(?:_signed(?:[-_](\d{14}))?)$/i', $name, $m)) {
			$base = $m[1];
			$isSigned = 1;
			if (!empty($m[2])) {
				$stamp = (int) $m[2];
			}
		}

		$base = trim((string) $base);
		$key = strtolower($dir.'/'.$base.'.'.$ext);

		$mtime = @filemtime($filepath);
		$score = ($stamp > 0 ? $stamp : (int) ($mtime ? $mtime : 0));

		return array($key, $isSigned, $score);
	}

	private function buildObjectKey($srcKey, $obj)
	{
		return $srcKey.'|'.$obj->ref.'|'.(int) $obj->id;
	}

	private function printAnnexesAndEmbedFiles($pdf, $outputlangs, $filesIndex)
	{
		$pdf->AddPage();

		$pdf->SetFont('', 'B', 12);
		$pdf->MultiCell(0, 6, $outputlangs->trans('Annexes'), 0, 'L', false, 1);

		$pdf->SetFont('', '', 9);

		$nbAttach = is_array($filesIndex['attach']) ? count($filesIndex['attach']) : 0;
		$nbPdf = is_array($filesIndex['pdf']) ? count($filesIndex['pdf']) : 0;

		if ($nbAttach <= 0) {
			$pdf->MultiCell(0, 5, $outputlangs->trans('NoLinkedFilesFound'), 0, 'L', false, 1);
			return;
		}

		$pdf->MultiCell(0, 5, 'Fichiers intégrés (pièces jointes) : '.$nbAttach, 0, 'L', false, 1);
		$pdf->MultiCell(0, 5, 'PDF ajoutés en pages (si support TCPDI) : '.$nbPdf, 0, 'L', false, 1);
		$pdf->Ln(2);

		$pdf->SetFont('', 'B', 10);
		$pdf->MultiCell(0, 6, 'Liste des fichiers intégrés', 0, 'L', false, 1);
		$pdf->SetFont('', '', 8);

		foreach ($filesIndex['attach'] as $path => $label) {
			$pdf->MultiCell(0, 4, '- '.$label, 0, 'L', false, 1);
		}

		// A) Pièces jointes (TOUT)
		foreach ($filesIndex['attach'] as $path => $label) {
			$this->attachFileToPdf($pdf, $path, $label);
		}

		// B) Concat des PDF en pages (si TCPDI dispo)
		if (!method_exists($pdf, 'setSourceFile')) {
			$pdf->Ln(2);
			$pdf->SetFont('', 'I', 9);
			$pdf->MultiCell(0, 5, 'Concat PDF désactivée (TCPDI indisponible). Les PDF sont tout de même intégrés en pièces jointes.', 0, 'L', false, 1);
			return;
		}

		if (method_exists($pdf, 'setPrintHeader')) $pdf->setPrintHeader(false);
		if (method_exists($pdf, 'setPrintFooter')) $pdf->setPrintFooter(false);

		foreach ($filesIndex['pdf'] as $path => $label) {
			$pdf->AddPage();
			$pdf->SetFont('', 'B', 10);
			$pdf->MultiCell(0, 6, 'PDF : '.$label, 0, 'L', false, 1);
			$pdf->SetFont('', '', 9);

			$res = $this->appendPdfFileAsPages($pdf, $path);
			if ($res < 0) {
				$pdf->SetFont('', 'I', 9);
				$pdf->MultiCell(0, 5, 'Impossible de concaténer ce PDF (format/protection/compat). Il reste disponible en pièce jointe.', 0, 'L', false, 1);
				$pdf->SetFont('', '', 9);
			}
		}
	}

	private function attachFileToPdf($pdf, $filepath, $label = '')
	{
		if (empty($filepath) || !is_readable($filepath)) {
			return -1;
		}

		$label = $label ? $label : basename($filepath);

		$pdf->Annotation(0, 0, 0, 0, $label, array(
			'Subtype' => 'FileAttachment',
			'Name' => 'PushPin',
			'FS' => $filepath,
		));

		return 1;
	}

	private function appendPdfFileAsPages($pdf, $filepath)
	{
		if (empty($filepath) || !is_readable($filepath)) {
			return -1;
		}

		if (!method_exists($pdf, 'setSourceFile') || !method_exists($pdf, 'importPage')) {
			return -2;
		}

		try {
			$pagecount = $pdf->setSourceFile($filepath);
			for ($p = 1; $p <= $pagecount; $p++) {
				$tpl = $pdf->importPage($p);
				$size = $pdf->getTemplateSize($tpl);

				$pdf->AddPage($size['orientation'], array($size['width'], $size['height']));
				$pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height'], true);
			}
			return (int) $pagecount;
		} catch (Exception $e) {
			return -3;
		}
	}

	/* ------------------------------------------------------------
	 *  PDF content helpers
	 * ------------------------------------------------------------ */

	private function printSectionTitle($pdf, $title)
	{
		$pdf->SetFont('', 'B', 11);
		$pdf->MultiCell(0, 7, $title, 0, 'L', false, 1);
		$pdf->SetFont('', '', 9);
	}

	private function getProjectContacts($project, $outputlangs)
	{
		global $mysoc;

		$rows = array();

		$sourcearray = array('internal', 'external');
		$contact_array = array();
		foreach ($sourcearray as $source) {
			$tmp = $project->liste_contact(-1, $source);
			if (is_array($tmp) && count($tmp) > 0) $contact_array = array_merge($contact_array, $tmp);
		}

		foreach ($contact_array as $c) {
			$typeLabel = ($c['source'] === 'internal' ? $outputlangs->trans('Internal') : $outputlangs->trans('External'));
			$role = (string) $c['libelle'];
			$name = '';
			$socname = '';
			$email = (string) $c['email'];

			if ($c['source'] === 'internal') {
				$u = new User($this->db);
				$u->fetch((int) $c['id']);
				$name = $u->getFullName($outputlangs, 1);
				$socname = $mysoc->name;
				if (empty($email)) $email = $u->email;
			} else {
				$ct = new Contact($this->db);
				$ct->fetch((int) $c['id']);
				$name = $ct->getFullName($outputlangs, 1);
				if (!empty($c['socid'])) {
					$soc = new Societe($this->db);
					$soc->fetch((int) $c['socid']);
					$socname = $soc->name;
				}
				if (empty($email)) $email = $ct->email;
			}

			$rows[] = array($typeLabel, $role, $name, $socname, $email);
		}

		if (!count($rows)) {
			$rows[] = array('-', '-', '-', '-', '-');
		}

		return $rows;
	}

	private function printLinkedObjectsSectionFromObjects($pdf, $project, $outputlangs, $linked, $filesIndex, $key, $title)
	{
		if (empty($linked[$key]) || !is_array($linked[$key]['objects'])) {
			return;
		}

		$rows = array();

		foreach ($linked[$key]['objects'] as $o) {
			$ref = (string) $o->ref;
			$datec = dol_print_date($o->datec ?? $o->date_creation ?? null, 'day');
			$stat = method_exists($o, 'getLibStatut') ? $o->getLibStatut(0) : '';

			$objkey = $this->buildObjectKey($key, $o);
			$files = !empty($filesIndex['files_by_object'][$objkey]) ? $filesIndex['files_by_object'][$objkey] : array();
			$nbfiles = is_array($files) ? count($files) : 0;

			$rows[] = array($ref, $datec, $stat, (string) $nbfiles);
		}

		$this->printSectionTitle($pdf, $title);
		$this->printSimpleTable($pdf, $outputlangs, array(
			$outputlangs->trans('Ref'),
			$outputlangs->trans('Date'),
			$outputlangs->trans('Status'),
			'Fichiers',
		), (count($rows) ? $rows : array(array('-', '-', '-', '0'))), array(30, 25, 40, 87));

		$pdf->Ln(2);
	}

	private function printSimpleTable($pdf, $outputlangs, $headers, $rows, $widths)
	{
		$pdf->SetFont('', 'B', 8);
		foreach ($headers as $i => $h) {
			$pdf->MultiCell($widths[$i], 6, $h, 1, 'L', false, 0);
		}
		$pdf->Ln();

		$pdf->SetFont('', '', 8);
		foreach ($rows as $r) {
			foreach ($headers as $i => $h) {
				$txt = isset($r[$i]) ? (string) $r[$i] : '';
				$pdf->MultiCell($widths[$i], 6, $txt, 1, 'L', false, 0);
			}
			$pdf->Ln();
		}

		$pdf->SetFont('', '', 9);
	}
	
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show top header of page.
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Project		$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @return	float|int                   Return topshift value
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
	{
		global $langs, $conf, $mysoc;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$posx = $this->page_largeur - $this->marge_droite - 100;
		$posy = $this->marge_haute;

		$pdf->SetXY($this->marge_gauche, $posy);

		// Logo
		$logo = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
		if ($mysoc->logo) {
			if (is_readable($logo)) {
				$height = pdf_getHeightForLogo($logo);
				$pdf->Image($logo, $this->marge_gauche, $posy, 0, $height); // width=0 (auto)
			} else {
				$pdf->SetTextColor(200, 0, 0);
				$pdf->SetFont('', 'B', $default_font_size - 2);
				$pdf->MultiCell(100, 3, $langs->transnoentities("ErrorLogoFileNotFound", $logo), 0, 'L');
				$pdf->MultiCell(100, 3, $langs->transnoentities("ErrorGoToModuleSetup"), 0, 'L');
			}
		} else {
			$pdf->MultiCell(100, 4, $outputlangs->transnoentities($this->emetteur->name), 0, 'L');
		}

		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell(100, 4, $outputlangs->transnoentities("Project")." ".$outputlangs->convToOutputCharset($object->ref), '', 'R');
		$pdf->SetFont('', '', $default_font_size + 2);

		$posy += 6;
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell(100, 4, $outputlangs->transnoentities("DateStart")." : ".dol_print_date($object->date_start, 'day', false, $outputlangs, true), '', 'R');

		if ($object->date_end) {
			$posy += 6;
			$pdf->SetXY($posx, $posy);
			$pdf->MultiCell(100, 4, $outputlangs->transnoentities("DateEnd")." : ".dol_print_date($object->date_end, 'day', false, $outputlangs, true), '', 'R');
		}

		if (is_object($object->thirdparty)) {
			$posy += 6;
			$pdf->SetXY($posx, $posy);
			$pdf->MultiCell(100, 4, $outputlangs->transnoentities("ThirdParty")." : ".$object->thirdparty->getFullName($outputlangs), '', 'R');
		}

		$pdf->SetTextColor(0, 0, 60);

		// Add list of linked objects
		/* Removed: A project can have more than thousands linked objects (orders, invoices, proposals, etc....
		$object->fetchObjectLinked();

		foreach($object->linkedObjects as $objecttype => $objects)
		{
			//var_dump($objects);exit;
			if ($objecttype == 'commande')
			{
				$outputlangs->load('orders');
				$num=count($objects);
				for ($i=0;$i<$num;$i++)
				{
					$posy+=4;
					$pdf->SetXY($posx,$posy);
					$pdf->SetFont('','', $default_font_size - 1);
					$text=$objects[$i]->ref;
					if ($objects[$i]->ref_client) $text.=' ('.$objects[$i]->ref_client.')';
					$pdf->MultiCell(100, 4, $outputlangs->transnoentities("RefOrder")." : ".$outputlangs->transnoentities($text), '', 'R');
				}
			}
		}
		*/

		return 0;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show footer of page. Need this->emetteur object
	 *
	 *  @param	TCPDF		$pdf     			PDF
	 *  @param	Project		$object				Object to show
	 *  @param	Translate	$outputlangs		Object lang for output
	 *  @param	int			$hidefreetext		1=Hide free text
	 *  @return	integer
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		$showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);
		return pdf_pagefoot($pdf, $outputlangs, 'PROJECT_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext);
	}
}
