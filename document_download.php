<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    jpsun/document_download.php
 * \ingroup jpsun
 * \brief   Forced document download and ZIP download for native document lists.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"]) && file_exists($_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php")) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

$langs->loadLangs(array('jpsun@jpsun', 'errors'));

if (!isModEnabled('jpsun')) {
	accessforbidden();
}

/**
 * Print a plain HTTP error and stop.
 *
 * @param	string	$message	Translated error message
 * @param	int		$status		HTTP status code
 * @return	void
 */
function jpsunDocumentDownloadFail($message, $status = 400)
{
	top_httphead('text/plain; charset=utf-8');
	http_response_code($status);
	print $message;
	exit;
}

/**
 * Normalize a relative Dolibarr document path like document.php does.
 *
 * @param	string	$originalFile	Relative path from the modulepart document directory
 * @return	string					Normalized relative path
 */
function jpsunNormalizeOriginalFile($originalFile)
{
	$originalFile = preg_replace('/\.\.+/', '..', $originalFile);
	$originalFile = str_replace('../', '/', (string) $originalFile);
	return str_replace('..\\', '/', $originalFile);
}

/**
 * Check the external-user SQL protection returned by dol_check_secure_access_document().
 *
 * @param	string	$sqlprotectagainstexternals	SQL query provided by Dolibarr
 * @param	string	$modulepart					Document modulepart
 * @return	bool								True when access is allowed
 */
function jpsunDocumentExternalAccessAllowed($sqlprotectagainstexternals, $modulepart)
{
	global $db, $user;

	if ($user->socid > 0) {
		if ($sqlprotectagainstexternals) {
			$resql = $db->query($sqlprotectagainstexternals);
			if ($resql) {
				while (is_object($obj = $db->fetch_object($resql))) {
					if ($user->socid != $obj->fk_soc) {
						return false;
					}
				}
			}
		}
	} elseif ($modulepart == 'ticket' && !getDolGlobalString('TICKET_EMAIL_MUST_EXISTS')) {
		if ($sqlprotectagainstexternals) {
			$resql = $db->query($sqlprotectagainstexternals);
			if ($resql && $db->num_rows($resql) > 0) {
				return true;
			}
		}
	}

	return true;
}

/**
 * Resolve and validate a native Dolibarr document link.
 *
 * @param	string	$modulepart		Document modulepart
 * @param	string	$originalFile	Relative file path
 * @param	int		$entity			Document entity
 * @return	array{fullpath:string,fullpath_osencoded:string,filename:string,mimetype:string}
 */
function jpsunResolveDocumentForDownload($modulepart, $originalFile, $entity)
{
	global $conf, $langs, $user;

	$modulepart = trim($modulepart);
	$originalFile = jpsunNormalizeOriginalFile($originalFile);

	if ($modulepart === '' || $originalFile === '') {
		jpsunDocumentDownloadFail($langs->trans('ErrorBadParameters'), 400);
	}
	if (!preg_match('/^[a-zA-Z0-9_|-]+$/', $modulepart)) {
		jpsunDocumentDownloadFail($langs->trans('ErrorBadParameters'), 400);
	}
	if ($modulepart == 'fckeditor') {
		$modulepart = 'medias';
	}
	if (in_array($modulepart, array('facture_paiement', 'unpaid')) && !$user->hasRight('societe', 'client', 'voir')) {
		$originalFile = 'private/'.$user->id.'/'.$originalFile;
	}

	$checkAccess = dol_check_secure_access_document($modulepart, $originalFile, (int) $entity, $user, '', 'read');
	if (!is_array($checkAccess) || empty($checkAccess['accessallowed']) || empty($checkAccess['original_file'])) {
		jpsunDocumentDownloadFail($langs->trans('ErrorForbidden'), 403);
	}

	$sqlprotectagainstexternals = isset($checkAccess['sqlprotectagainstexternals']) ? (string) $checkAccess['sqlprotectagainstexternals'] : '';
	if (!jpsunDocumentExternalAccessAllowed($sqlprotectagainstexternals, $modulepart)) {
		jpsunDocumentDownloadFail($langs->trans('ErrorForbidden'), 403);
	}

	$fullpath = (string) $checkAccess['original_file'];
	if (preg_match('/\.\./', $fullpath) || preg_match('/[<>|]/', $fullpath)) {
		dol_syslog('JPSUN refused invalid document path '.$fullpath, LOG_WARNING);
		jpsunDocumentDownloadFail($langs->trans('ErrorFileNameInvalid'), 400);
	}

	$fullpathosencoded = dol_osencode($fullpath);
	if (!file_exists($fullpathosencoded)) {
		dol_syslog('JPSUN document download file not found '.$fullpath, LOG_WARNING);
		jpsunDocumentDownloadFail($langs->trans('ErrorFileDoesNotExists', $originalFile), 404);
	}

	$filename = basename($fullpath);
	$filename = preg_replace('/\.noexe$/i', '', $filename);

	return array(
		'fullpath' => $fullpath,
		'fullpath_osencoded' => $fullpathosencoded,
		'filename' => $filename,
		'mimetype' => dol_mimetype($filename),
	);
}

/**
 * Send a file with a forced attachment disposition.
 *
 * @param	string	$fullpath			Source path
 * @param	string	$filename			Download filename
 * @param	string	$mimetype			MIME type
 * @param	bool	$deleteafter		Delete source file after sending
 * @return	void
 */
function jpsunSendAttachment($fullpath, $filename, $mimetype, $deleteafter = false)
{
	global $db;

	$filenameforheader = str_replace(array("\r", "\n", '"'), '', $filename);
	$fullpathosencoded = dol_osencode($fullpath);

	while (ob_get_level() > 0) {
		ob_end_clean();
	}

	top_httphead($mimetype);
	header('Content-Description: File Transfer');
	header('Content-Disposition: attachment; filename="'.$filenameforheader.'"; filename*=UTF-8\'\''.rawurlencode($filename));
	header('Content-Type: '.$mimetype);
	header('Content-Transfer-Encoding: binary');
	header('Content-Length: '.filesize($fullpathosencoded));
	header('Cache-Control: public, must-revalidate');
	header('Pragma: public');
	header('X-Content-Type-Options: nosniff');

	if (is_object($db)) {
		$db->close();
	}

	readfileLowMemory($fullpathosencoded);

	if ($deleteafter) {
		dol_delete_file($fullpath, 1, 1, 1, null, false, 0, 1);
	}

	exit;
}

/**
 * Decode a selected document payload sent by the hook JavaScript.
 *
 * @param	string	$encoded	Encoded item
 * @return	array{modulepart:string,file:string,entity:int}|null
 */
function jpsunDecodeDownloadItem($encoded)
{
	global $conf;

	$encoded = trim($encoded);
	if ($encoded === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $encoded)) {
		return null;
	}

	$base64 = strtr($encoded, '-_', '+/');
	$padding = strlen($base64) % 4;
	if ($padding) {
		$base64 .= str_repeat('=', 4 - $padding);
	}

	$json = base64_decode($base64, true);
	if ($json === false) {
		return null;
	}

	$data = json_decode($json, true);
	if (!is_array($data)) {
		return null;
	}

	$modulepart = isset($data['modulepart']) && is_scalar($data['modulepart']) ? (string) $data['modulepart'] : '';
	$file = isset($data['file']) && is_scalar($data['file']) ? (string) $data['file'] : '';
	$entity = isset($data['entity']) && $data['entity'] !== '' ? (int) $data['entity'] : (int) $conf->entity;

	if ($modulepart === '' || $file === '') {
		return null;
	}

	return array(
		'modulepart' => $modulepart,
		'file' => $file,
		'entity' => $entity,
	);
}

/**
 * Return a unique flat filename for a ZIP entry.
 *
 * @param	string				$filename	Source filename
 * @param	array<string,bool>	$usedNames	Already used ZIP names
 * @return	string							ZIP entry name
 */
function jpsunGetUniqueZipEntryName($filename, &$usedNames)
{
	$name = str_replace(array('/', '\\'), '_', $filename);
	if ($name === '') {
		$name = 'document';
	}

	$candidate = $name;
	$counter = 2;
	while (!empty($usedNames[$candidate])) {
		$extension = pathinfo($name, PATHINFO_EXTENSION);
		$basename = $extension ? substr($name, 0, -1 * (strlen($extension) + 1)) : $name;
		$candidate = $basename.'-'.$counter.($extension ? '.'.$extension : '');
		$counter++;
	}

	$usedNames[$candidate] = true;
	return $candidate;
}

$mode = GETPOST('mode', 'aZ09');
if ($mode === '') {
	$mode = 'file';
}

if ($mode === 'file') {
	$modulepart = GETPOST('modulepart', 'alphanohtml');
	$originalFile = GETPOST('file', 'alphanohtml');
	$entity = GETPOSTISSET('entity') ? GETPOSTINT('entity') : (int) $conf->entity;

	$document = jpsunResolveDocumentForDownload($modulepart, $originalFile, $entity);
	dol_syslog('JPSUN forced document download '.$document['fullpath'], LOG_DEBUG);
	jpsunSendAttachment($document['fullpath'], $document['filename'], $document['mimetype']);
}

if ($mode !== 'zip') {
	jpsunDocumentDownloadFail($langs->trans('ErrorBadParameters'), 400);
}

if (!class_exists('ZipArchive')) {
	jpsunDocumentDownloadFail($langs->trans('JpsunZipArchiveUnavailable'), 500);
}

$items = GETPOST('items', 'array');
if (!is_array($items) || empty($items)) {
	jpsunDocumentDownloadFail($langs->trans('JpsunNoDocumentSelected'), 400);
}

$documents = array();
foreach ($items as $encodedItem) {
	if (!is_scalar($encodedItem)) {
		continue;
	}

	$item = jpsunDecodeDownloadItem((string) $encodedItem);
	if ($item === null) {
		continue;
	}

	$documents[] = jpsunResolveDocumentForDownload($item['modulepart'], $item['file'], $item['entity']);
}

if (empty($documents)) {
	jpsunDocumentDownloadFail($langs->trans('JpsunNoDocumentSelected'), 400);
}

$tempdir = '';
if (!empty($conf->jpsun->dir_output)) {
	$tempdir = rtrim((string) $conf->jpsun->dir_output, '/').'/temp';
} else {
	$tempdir = DOL_DATA_ROOT.'/jpsun/temp';
}
if (dol_mkdir($tempdir) < 0) {
	jpsunDocumentDownloadFail($langs->trans('ErrorFailedToCreateDir'), 500);
}

$zipfilename = 'documents-'.dol_print_date(dol_now(), '%Y%m%d-%H%M%S').'-'.((int) $user->id).'.zip';
$zippath = $tempdir.'/'.$zipfilename;
$zippathosencoded = dol_osencode($zippath);

$zip = new ZipArchive();
$zipopen = $zip->open($zippathosencoded, ZipArchive::CREATE | ZipArchive::OVERWRITE);
if ($zipopen !== true) {
	jpsunDocumentDownloadFail($langs->trans('JpsunUnableToCreateZipArchive'), 500);
}

$usedNames = array();
$documentCount = 0;
foreach ($documents as $document) {
	$entryname = jpsunGetUniqueZipEntryName($document['filename'], $usedNames);
	if ($zip->addFile($document['fullpath_osencoded'], $entryname)) {
		$documentCount++;
	}
}

$zip->close();

if ($documentCount === 0) {
	dol_delete_file($zippath, 1, 1, 1, null, false, 0, 1);
	jpsunDocumentDownloadFail($langs->trans('JpsunNoDocumentSelected'), 400);
}

dol_syslog('JPSUN ZIP document download '.$zippath.' with '.$documentCount.' file(s)', LOG_DEBUG);
jpsunSendAttachment($zippath, $zipfilename, 'application/zip', true);
