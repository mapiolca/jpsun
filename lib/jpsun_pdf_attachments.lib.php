<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file		lib/jpsun_pdf_attachments.lib.php
 *	\ingroup	jpsun
 *	\brief		Helpers for JPSUN PDF annex configuration and PDF page merge.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

/**
 * Return configured attachment definitions.
 *
 * @return array<string,array<string,int|string>>
 */
function jpsunPdfAttachmentDefinitions()
{
	return array(
		'COMPANY_PRESENTATION' => array(
			'label' => 'JpsunPdfAttachmentCompanyPresentation',
			'default_order' => 10,
		),
		'SPONSORSHIP' => array(
			'label' => 'JpsunPdfAttachmentSponsorship',
			'default_order' => 20,
		),
		'DECENNIAL_WARRANTY' => array(
			'label' => 'JpsunPdfAttachmentDecennialWarranty',
			'default_order' => 30,
		),
		'CERTIFICATIONS' => array(
			'label' => 'JpsunPdfAttachmentCertifications',
			'default_order' => 35,
		),
		'PROJECT_PLANNING' => array(
			'label' => 'JpsunPdfAttachmentProjectPlanning',
			'default_order' => 40,
		),
		'TERMS_OF_SALE' => array(
			'label' => 'JpsunPdfAttachmentTermsOfSale',
			'default_order' => 50,
		),
	);
}

/**
 * Return a setup constant name for one attachment field.
 *
 * @param	string	$code		Attachment code
 * @param	string	$suffix		Constant suffix
 * @return	string
 */
function jpsunPdfAttachmentConst($code, $suffix)
{
	return 'JPSUN_PDF_ATTACHMENT_'.$code.'_'.$suffix;
}

/**
 * Return the target suffix matching a document type.
 *
 * @param	string	$target		contract|propal
 * @return	string
 */
function jpsunPdfAttachmentTargetSuffix($target)
{
	return ($target === 'contract' ? 'JOIN_CONTRACT' : 'JOIN_PROPAL');
}

/**
 * Return the PDF attachment upload directory for an entity.
 *
 * @param	int	$entity	Entity id
 * @return	string
 */
function jpsunPdfAttachmentUploadDir($entity = 0)
{
	global $conf;

	$entity = (int) ($entity > 0 ? $entity : $conf->entity);
	$base = '';
	if (!empty($conf->jpsun->dir_output)) {
		$base = $conf->jpsun->dir_output;
	} elseif (defined('DOL_DATA_ROOT')) {
		$base = DOL_DATA_ROOT.'/jpsun';
	}

	return rtrim($base, '/').'/'.$entity.'/pdf_attachments';
}

/**
 * Return the full path of a configured attachment.
 *
 * @param	string	$code		Attachment code
 * @param	int		$entity		Entity id
 * @return	string
 */
function jpsunPdfAttachmentPath($code, $entity = 0)
{
	$filename = getDolGlobalString(jpsunPdfAttachmentConst($code, 'FILE'));
	$filename = basename((string) $filename);
	if ($filename === '' || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
		return '';
	}

	$path = jpsunPdfAttachmentUploadDir($entity).'/'.$filename;
	return (is_readable($path) ? $path : '');
}

/**
 * Return whether one attachment is enabled and readable for a target.
 *
 * @param	string	$code		Attachment code
 * @param	string	$target		contract|propal
 * @param	int		$entity		Entity id
 * @return	bool
 */
function jpsunPdfAttachmentIsAvailableForTarget($code, $target, $entity = 0)
{
	$suffix = jpsunPdfAttachmentTargetSuffix($target);
	return (bool) (getDolGlobalInt(jpsunPdfAttachmentConst($code, $suffix)) && jpsunPdfAttachmentPath($code, $entity) !== '');
}

/**
 * Return ordered attachments enabled for a target.
 *
 * @param	string	$target		contract|propal
 * @param	int		$entity		Entity id
 * @return	array<int,array<string,int|string>>
 */
function jpsunPdfAttachmentFilesForTarget($target, $entity = 0)
{
	$definitions = jpsunPdfAttachmentDefinitions();
	$suffix = jpsunPdfAttachmentTargetSuffix($target);
	$items = array();
	$index = 0;

	foreach ($definitions as $code => $definition) {
		if (!getDolGlobalInt(jpsunPdfAttachmentConst($code, $suffix))) {
			$index++;
			continue;
		}

		$path = jpsunPdfAttachmentPath($code, $entity);
		if ($path === '') {
			jpsunPdfAttachmentWarn('JpsunPdfAttachmentMissingFile', $definition['label']);
			$index++;
			continue;
		}

		$items[] = array(
			'code' => $code,
			'label' => $definition['label'],
			'path' => $path,
			'order' => getDolGlobalInt(jpsunPdfAttachmentConst($code, 'ORDER'), (int) $definition['default_order']),
			'index' => $index,
		);
		$index++;
	}

	usort($items, 'jpsunPdfAttachmentSortByOrder');

	return $items;
}

/**
 * Sort callback for attachments.
 *
 * @param	array<string,int|string>	$a	First item
 * @param	array<string,int|string>	$b	Second item
 * @return	int
 */
function jpsunPdfAttachmentSortByOrder($a, $b)
{
	$ordera = (int) $a['order'];
	$orderb = (int) $b['order'];
	if ($ordera === $orderb) {
		return (int) $a['index'] - (int) $b['index'];
	}

	return $ordera - $orderb;
}

/**
 * Append configured PDF attachments as pages.
 *
 * @param	TCPDF	$pdf		PDF instance
 * @param	string	$target		contract|propal
 * @param	int		$entity		Entity id
 * @return	int					Number of appended files
 */
function jpsunPdfAppendConfiguredAttachments(&$pdf, $target, $entity = 0)
{
	$count = 0;
	foreach (jpsunPdfAttachmentFilesForTarget($target, $entity) as $item) {
		$result = jpsunPdfAppendPdfFileAsPages($pdf, $item['path'], $item['label']);
		if ($result < 0) {
			jpsunPdfAttachmentWarn('JpsunPdfAttachmentAppendError', $item['label']);
			continue;
		}
		$count++;
	}

	return $count;
}

/**
 * Append a PDF file as pages.
 *
 * @param	TCPDF	$pdf		PDF instance
 * @param	string	$filepath	PDF path
 * @param	string	$label		Attachment label
 * @return	int					Page count if OK, <0 if KO
 */
function jpsunPdfAppendPdfFileAsPages(&$pdf, $filepath, $label = '')
{
	if (empty($filepath) || !is_readable($filepath)) {
		return -1;
	}
	if (strtolower(pathinfo($filepath, PATHINFO_EXTENSION)) !== 'pdf') {
		return -2;
	}
	if (!method_exists($pdf, 'setSourceFile') || !method_exists($pdf, 'importPage')) {
		return -3;
	}

	try {
		$pagecount = $pdf->setSourceFile($filepath);
		for ($p = 1; $p <= $pagecount; $p++) {
			$tpl = $pdf->importPage($p);
			if ($tpl === false) {
				return -4;
			}

			if (method_exists($pdf, 'getTemplateSize')) {
				$size = $pdf->getTemplateSize($tpl);
			} else {
				$size = $pdf->getTemplatesize($tpl);
			}
			$width = isset($size['width']) ? $size['width'] : $size['w'];
			$height = isset($size['height']) ? $size['height'] : $size['h'];
			$orientation = isset($size['orientation']) ? $size['orientation'] : ($height > $width ? 'P' : 'L');

			$pdf->AddPage($orientation, array($width, $height));
			if (method_exists($pdf, 'useTemplate')) {
				$pdf->useTemplate($tpl, 0, 0, $width, $height, true);
			}
		}

		return (int) $pagecount;
	} catch (Exception $e) {
		dol_syslog('JPSUN PDF attachment '.$label.' cannot be appended: '.$e->getMessage(), LOG_WARNING);
		return -5;
	}
}

/**
 * Generate and append the native intervention specimen PDF.
 *
 * @param	TCPDF		$pdf			PDF instance
 * @param	DoliDB		$db				Database handler
 * @param	Translate	$outputlangs	Output language
 * @param	CommonObject|null	$sourceobject	Source object used to contextualize specimen
 * @return	int							>0 if OK, 0 if skipped, <0 if KO
 */
function jpsunPdfAppendFichinterSpecimen(&$pdf, $db, $outputlangs, $sourceobject = null)
{
	$errorcode = 0;
	$path = jpsunPdfFichinterSpecimenPath($db, $outputlangs, $errorcode, $sourceobject);
	if ($path === '') {
		return $errorcode;
	}

	$result = jpsunPdfAppendPdfFileAsPages($pdf, $path, 'InterventionCard');
	if ($result < 0) {
		jpsunPdfAttachmentWarn('JpsunPdfAttachmentFichinterAppendError');
		return -2;
	}

	return $result;
}

/**
 * Generate and return the native intervention specimen PDF path.
 *
 * @param	DoliDB		$db				Database handler
 * @param	Translate	$outputlangs	Output language
 * @param	int			$errorcode		Output error code
 * @param	CommonObject|null	$sourceobject	Source object used to contextualize specimen
 * @return	string						Readable PDF path, empty if skipped/KO
 */
function jpsunPdfFichinterSpecimenPath($db, $outputlangs, &$errorcode = 0, $sourceobject = null)
{
	global $conf;

	$errorcode = 0;

	$interventionenabled = true;
	if (function_exists('isModEnabled')) {
		$interventionenabled = (isModEnabled('intervention') || isModEnabled('ficheinter'));
	}
	if (!$interventionenabled || empty($conf->ficheinter->dir_output)) {
		jpsunPdfAttachmentWarn('JpsunPdfAttachmentFichinterUnavailable');
		return '';
	}
	if (!file_exists(DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php') || !file_exists(DOL_DOCUMENT_ROOT.'/core/modules/fichinter/modules_fichinter.php')) {
		jpsunPdfAttachmentWarn('JpsunPdfAttachmentFichinterUnavailable');
		return '';
	}

	require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/modules/fichinter/modules_fichinter.php';

	$fichinter = new Fichinter($db);
	if (!method_exists($fichinter, 'initAsSpecimen')) {
		jpsunPdfAttachmentWarn('JpsunPdfAttachmentFichinterUnavailable');
		return '';
	}

	$fichinter->initAsSpecimen();
	jpsunPdfPrepareFichinterSpecimenFromSource($fichinter, $sourceobject);

	$oldwatermark = getDolGlobalString('FICHINTER_DRAFT_WATERMARK');
	$hadwatermark = isset($conf->global->FICHINTER_DRAFT_WATERMARK);
	$conf->global->FICHINTER_DRAFT_WATERMARK = 'SPECIMEN';
	try {
		$result = fichinter_create($db, $fichinter, getDolGlobalString('FICHEINTER_ADDON_PDF'), $outputlangs);
	} catch (Throwable $e) {
		if ($hadwatermark) {
			$conf->global->FICHINTER_DRAFT_WATERMARK = $oldwatermark;
		} else {
			unset($conf->global->FICHINTER_DRAFT_WATERMARK);
		}
		dol_syslog('JPSUN intervention specimen generation failed: '.$e->getMessage(), LOG_WARNING);
		jpsunPdfAttachmentWarn('JpsunPdfAttachmentFichinterGenerationError');
		$errorcode = -1;
		return '';
	}
	if ($hadwatermark) {
		$conf->global->FICHINTER_DRAFT_WATERMARK = $oldwatermark;
	} else {
		unset($conf->global->FICHINTER_DRAFT_WATERMARK);
	}
	if ($result <= 0) {
		jpsunPdfAttachmentWarn('JpsunPdfAttachmentFichinterGenerationError');
		$errorcode = -1;
		return '';
	}

	$path = rtrim($conf->ficheinter->dir_output, '/').'/SPECIMEN.pdf';
	if (!is_readable($path)) {
		jpsunPdfAttachmentWarn('JpsunPdfAttachmentFichinterGenerationError');
		$errorcode = -1;
		return '';
	}

	return $path;
}

/**
 * Apply source object context to a native intervention specimen.
 *
 * @param	Fichinter	$fichinter		Intervention specimen
 * @param	CommonObject|null	$sourceobject	Source object, usually a contract
 * @return	void
 */
function jpsunPdfPrepareFichinterSpecimenFromSource(&$fichinter, $sourceobject = null)
{
	if (!is_object($sourceobject)) {
		return;
	}

	if (method_exists($sourceobject, 'fetch_thirdparty')) {
		$sourceobject->fetch_thirdparty();
	}

	$socid = 0;
	if (!empty($sourceobject->socid)) {
		$socid = (int) $sourceobject->socid;
	} elseif (!empty($sourceobject->fk_soc)) {
		$socid = (int) $sourceobject->fk_soc;
	} elseif (!empty($sourceobject->thirdparty) && !empty($sourceobject->thirdparty->id)) {
		$socid = (int) $sourceobject->thirdparty->id;
	}
	if ($socid > 0) {
		$fichinter->socid = $socid;
		$fichinter->fk_soc = $socid;
	}
	if (!empty($sourceobject->thirdparty)) {
		$fichinter->thirdparty = $sourceobject->thirdparty;
	}
	if (!empty($sourceobject->entity)) {
		$fichinter->entity = (int) $sourceobject->entity;
	}
	if (!empty($sourceobject->id)) {
		$fichinter->fk_contrat = (int) $sourceobject->id;
	}
	if (!empty($sourceobject->fk_project)) {
		$fichinter->fk_project = (int) $sourceobject->fk_project;
	} elseif (!empty($sourceobject->fk_projet)) {
		$fichinter->fk_project = (int) $sourceobject->fk_projet;
	}
	if (!empty($sourceobject->ref_client)) {
		$fichinter->ref_client = (string) $sourceobject->ref_client;
	} elseif (!empty($sourceobject->ref)) {
		$fichinter->ref_client = (string) $sourceobject->ref;
	}
	if (defined('Fichinter::STATUS_DRAFT')) {
		$fichinter->statut = Fichinter::STATUS_DRAFT;
		$fichinter->status = Fichinter::STATUS_DRAFT;
	} else {
		$fichinter->statut = 0;
		$fichinter->status = 0;
	}
}

/**
 * Handle upload/delete actions for PDF attachments.
 *
 * @param	DoliDB	$db	Database handler
 * @return	int			1 if action handled, 0 otherwise
 */
function jpsunPdfHandleAttachmentAdminAction($db)
{
	global $conf, $langs;

	$action = GETPOST('action', 'alpha');
	if ($action !== 'jpsun_upload_pdf_attachment' && $action !== 'jpsun_delete_pdf_attachment') {
		return 0;
	}

	$code = GETPOST('attachment_code', 'alpha');
	$definitions = jpsunPdfAttachmentDefinitions();
	if (empty($definitions[$code])) {
		setEventMessages($langs->trans('ErrorBadParameters'), null, 'errors');
		return 1;
	}

	if ($action === 'jpsun_delete_pdf_attachment') {
		$filename = basename((string) getDolGlobalString(jpsunPdfAttachmentConst($code, 'FILE')));
		if ($filename !== '') {
			$path = jpsunPdfAttachmentUploadDir((int) $conf->entity).'/'.$filename;
			if (is_file($path)) {
				@unlink($path);
			}
		}
		dolibarr_del_const($db, jpsunPdfAttachmentConst($code, 'FILE'), $conf->entity);
		setEventMessages($langs->trans('FileWasRemoved'), null, 'mesgs');
		return 1;
	}

	$inputname = 'attachment_file_'.$code;
	if (empty($_FILES[$inputname]) || !is_array($_FILES[$inputname]) || (int) $_FILES[$inputname]['error'] === UPLOAD_ERR_NO_FILE) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('File')), null, 'errors');
		return 1;
	}
	if ((int) $_FILES[$inputname]['error'] !== UPLOAD_ERR_OK || empty($_FILES[$inputname]['tmp_name'])) {
		setEventMessages($langs->trans('ErrorFileNotUploaded'), null, 'errors');
		return 1;
	}

	$originalname = basename((string) $_FILES[$inputname]['name']);
	if (strtolower(pathinfo($originalname, PATHINFO_EXTENSION)) !== 'pdf') {
		setEventMessages($langs->trans('JpsunPdfAttachmentPdfOnly'), null, 'errors');
		return 1;
	}

	$dir = jpsunPdfAttachmentUploadDir((int) $conf->entity);
	if (!is_dir($dir) && dol_mkdir($dir) < 0) {
		setEventMessages($langs->trans('ErrorCanNotCreateDir', $dir), null, 'errors');
		return 1;
	}

	$filename = strtolower($code).'_'.dol_sanitizeFileName($originalname);
	$dest = $dir.'/'.$filename;
	if (!is_uploaded_file($_FILES[$inputname]['tmp_name']) || !move_uploaded_file($_FILES[$inputname]['tmp_name'], $dest)) {
		setEventMessages($langs->trans('ErrorFileNotUploaded'), null, 'errors');
		return 1;
	}
	dolChmod($dest);

	$oldfilename = basename((string) getDolGlobalString(jpsunPdfAttachmentConst($code, 'FILE')));
	if ($oldfilename !== '' && $oldfilename !== $filename && is_file($dir.'/'.$oldfilename)) {
		@unlink($dir.'/'.$oldfilename);
	}

	dolibarr_set_const($db, jpsunPdfAttachmentConst($code, 'FILE'), $filename, 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans('FileTransferComplete'), null, 'mesgs');

	return 1;
}

/**
 * Print setup rows for PDF attachments.
 *
 * @return	void
 */
function jpsunPdfPrintAttachmentSetupRows()
{
	global $conf, $langs;

	$definitions = jpsunPdfAttachmentDefinitions();
	foreach ($definitions as $code => $definition) {
		$fileconst = jpsunPdfAttachmentConst($code, 'FILE');
		$currentfile = basename((string) getDolGlobalString($fileconst));
		$currentpath = ($currentfile !== '' ? jpsunPdfAttachmentUploadDir((int) $conf->entity).'/'.$currentfile : '');
		$fileisreadable = ($currentpath !== '' && is_readable($currentpath));

		print '<tr class="oddeven">';
		print '<td>';
		print '<strong>'.$langs->trans($definition['label']).'</strong>';
		if ($currentfile !== '') {
			print '<br><small>'.dol_escape_htmltag($currentfile).($fileisreadable ? '' : ' - '.$langs->trans('JpsunPdfAttachmentMissing')).'</small>';
		}
		print '</td>';
		print '<td align="center" width="20">&nbsp;</td>';
		print '<td align="right">';
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" enctype="multipart/form-data" class="inline-block">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="jpsun_upload_pdf_attachment">';
		print '<input type="hidden" name="attachment_code" value="'.dol_escape_htmltag($code).'">';
		print '<input type="file" name="attachment_file_'.dol_escape_htmltag($code).'" accept="application/pdf,.pdf">';
		print '<input type="submit" class="button small" value="'.$langs->trans('Upload').'">';
		print '</form>';
		if ($currentfile !== '') {
			print ' <form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="inline-block">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="jpsun_delete_pdf_attachment">';
			print '<input type="hidden" name="attachment_code" value="'.dol_escape_htmltag($code).'">';
			print '<input type="submit" class="button button-delete small" value="'.$langs->trans('Delete').'">';
			print '</form>';
		}
		print '</td>';
		print '</tr>';

		setup_print_on_off(jpsunPdfAttachmentConst($code, 'JOIN_CONTRACT'), $langs->trans('JpsunPdfAttachmentJoinContracts'));
		setup_print_on_off(jpsunPdfAttachmentConst($code, 'JOIN_PROPAL'), $langs->trans('JpsunPdfAttachmentJoinPropals'));
		setup_print_input_form_part(jpsunPdfAttachmentConst($code, 'ORDER'), $langs->trans('JpsunPdfAttachmentOrder'), '', array(
			'type' => 'number',
			'min' => '1',
			'step' => '1',
			'value' => getDolGlobalInt(jpsunPdfAttachmentConst($code, 'ORDER'), (int) $definition['default_order']),
		));
	}
}

/**
 * Emit a user-visible warning when possible.
 *
 * @param	string	$key		Translation key
 * @param	string	$labelkey	Optional label translation key
 * @return	void
 */
function jpsunPdfAttachmentWarn($key, $labelkey = '')
{
	global $langs;

	$message = $langs->trans($key, ($labelkey !== '' ? $langs->trans($labelkey) : ''));
	if (function_exists('setEventMessages')) {
		setEventMessages(null, array($message), 'warnings');
	} else {
		dol_syslog($message, LOG_WARNING);
	}
}
