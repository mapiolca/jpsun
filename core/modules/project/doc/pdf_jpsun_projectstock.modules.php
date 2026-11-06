<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later */
require_once DOL_DOCUMENT_ROOT.'/core/modules/project/modules_project.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

/** A4 landscape site-stock notice, using the supplied Word composition. */
class pdf_jpsun_projectstock extends ModelePDFProjects
{
	public $name = 'jpsun_projectstock';
	public $type = 'pdf';
	public $version = 'dolibarr';
	public $update_main_doc_field = 1;
	public $page_largeur = 297;
	public $page_hauteur = 210;

	/** @param DoliDB $db Database */
	public function __construct($db)
	{
		global $langs;
		$this->db = $db;
		$langs->load('jpsun@jpsun');
		$this->description = $langs->trans('JpsunProjectStockModel');
	}

	/**
	 * @param Project $object Project loaded by the native document action
	 * @param Translate|null $outputlangs Output language
	 * @param string $srctemplatepath Unused
	 * @param int $hidedetails Unused
	 * @param int $hidedesc Unused
	 * @param int $hideref Unused (the reference identifies the reserved stock)
	 * @return int 1 on success, 0 on failure
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $langs, $user;
		$outputlangs = is_object($outputlangs) ? $outputlangs : $langs;
		$outputlangs->loadLangs(array('main', 'projects', 'companies', 'jpsun@jpsun'));
		// Some native CSRF levels do not classify GET builddoc as sensitive.
		if (PHP_SAPI !== 'cli' && in_array(GETPOST('action', 'aZ09'), array('builddoc', 'specimen'), true)
			&& (!GETPOST('token', 'alpha') || !hash_equals(currentToken(), GETPOST('token', 'alpha')))) {
			$this->error = $outputlangs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry');
			return 0;
		}
		$specimen = !empty($object->specimen) && (int) $object->id === 0 && $object->ref === 'SPECIMEN';
		if (!isModEnabled('jpsun') || !isModEnabled('project') || getDolGlobalString('MAIN_USE_FPDF')
			|| !$user->hasRight('projet', 'lire') || !$user->hasRight('projet', 'creer')
			|| ((int) $object->entity !== (int) $conf->entity && isModEnabled('multicompany') && !$user->hasRight('multicompany', 'project', 'write'))
			|| !empty($user->socid) || (!$specimen && (int) $object->id <= 0) || (int) $object->entity <= 0) {
			$this->error = $outputlangs->trans('NotEnoughPermissions');
			return 0;
		}
		if (!$specimen) { restrictedArea($user, 'projet', $object->id, 'projet&project'); }
		if (!$specimen && $object->restrictedProjectArea($user, 'write') <= 0) {
			$this->error = $outputlangs->trans('NotEnoughPermissions');
			return 0;
		}
		$client = '';
		if (!$specimen && (int) $object->socid > 0) {
			// The parent thirdparty's native commercial scope applies to its name too.
			if (!$user->hasRight('societe', 'lire')) {
				$this->error = $outputlangs->trans('NotEnoughPermissions');
				return 0;
			}
			restrictedArea($user, 'societe', $object->socid, 'societe');
			$thirdparty = new Societe($this->db);
			if ($thirdparty->fetch($object->socid) <= 0) {
				$this->error = $outputlangs->trans('JpsunProjectStockReadError');
				return 0;
			}
			$client = $thirdparty->name;
		}
		if (!isset($conf->project->multidir_output[$object->entity])
			|| !is_string($conf->project->multidir_output[$object->entity])
			|| trim($conf->project->multidir_output[$object->entity]) === ''
			|| $object->ref === '' || dol_sanitizeFileName($object->ref) !== $object->ref) {
			$this->error = $outputlangs->trans('JpsunCoverDirectoryError');
			return 0;
		}
		$base = realpath($conf->project->multidir_output[$object->entity]);
		$dir = getMultidirOutput($object, 'project', 1);
		if (!$base || !is_string($dir) || $dir === '' || strpos($dir, 'error-') === 0) {
			$this->error = $outputlangs->trans('JpsunCoverDirectoryError');
			return 0;
		}
		// Reject existing symlink ancestors escaping the owner's document root.
		$ancestor = $dir;
		while (!file_exists($ancestor) && dirname($ancestor) !== $ancestor) {
			$ancestor = dirname($ancestor);
		}
		$resolved = realpath($ancestor);
		if (!$resolved || ($resolved !== $base && strpos($resolved, $base.DIRECTORY_SEPARATOR) !== 0) || dol_mkdir($dir) < 0) {
			$this->error = $outputlangs->trans('JpsunCoverDirectoryError');
			return 0;
		}
		$file = rtrim($dir, '/\\').'/'.($specimen ? 'SPECIMEN.pdf' : $object->ref.'-stock.pdf');
		if (is_link($file)) {
			$this->error = $outputlangs->trans('JpsunCoverDirectoryError');
			return 0;
		}
		$pdf = pdf_getInstance(array(297, 210), 'mm', 'l');
		$this->drawPoster($pdf, $outputlangs, $client, $object->title, $object->ref);
		$pdf->Output($file, 'F');
		if (!is_file($file) || filesize($file) <= 0) {
			$this->error = $outputlangs->trans('JpsunCoverWriteError');
			return 0;
		}
		dolChmod($file);
		$this->result = array('fullpath' => $file);
		return 1;
	}

	/**
	 * @param TCPDF $pdf Native PDF instance
	 * @param Translate $langs Output language
	 * @param string $client Thirdparty name, empty when no thirdparty is linked
	 * @param string|null $title Project title (null in the native specimen)
	 * @param string $ref Native project reference
	 * @return void
	 */
	protected function drawPoster($pdf, $langs, string $client, ?string $title, string $ref): void
	{
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->SetMargins(0, 0, 0);
		$pdf->SetAutoPageBreak(false, 0);
		$pdf->SetCreator('Dolibarr');
		$pdf->SetTitle($langs->convToOutputCharset($ref.' - '.$langs->transnoentities('JpsunProjectStockModel')));
		$pdf->AddPage();
		$pdf->Image(__DIR__.'/../../../../img/project_stock_background.png', 0, 0, 297, 210);
		$pdf->SetFillColor(17, 17, 17);
		$pdf->Rect(20, 24, 257, 40, 'F');
		$pdf->SetTextColor(255, 209, 0);
		$this->text($pdf, $langs, $langs->transnoentities('JpsunProjectStockHeading'), 46, 26, 205, 23, 54, 'B');
		// Vector safety signs avoid missing emoji glyphs in server PDF fonts.
		foreach (array(26, 257) as $x) {
			$pdf->SetFillColor(255, 209, 0);
			$pdf->Rect($x, 33, 14, 5, 'F');
			$pdf->SetDrawColor(255, 209, 0);
			$pdf->Line($x + 2, 38, $x + 2, 43);
			$pdf->Line($x + 12, 38, $x + 12, 43);
			$pdf->SetDrawColor(17, 17, 17);
			for ($i = 1; $i < 14; $i += 4) { $pdf->Line($x + $i, 33, $x + $i + 3, 38); }
		}
		$pdf->SetTextColor(255, 255, 255);
		$this->text($pdf, $langs, $langs->transnoentities('JpsunProjectStockWarning'), 39, 51, 219, 10, 20, 'B');
		foreach (array(29, 261) as $x) {
			$pdf->SetFillColor(255, 209, 0);
			$pdf->Polygon(array($x, 59, $x + 3.5, 52, $x + 7, 59), 'F');
			$pdf->SetTextColor(17, 17, 17);
			$this->text($pdf, $langs, '!', $x, 53, 7, 6, 13, 'B');
		}
		$pdf->SetTextColor(119, 119, 119);
		$this->text($pdf, $langs, $langs->transnoentities('Customer'), 25, 79, 247, 7, 12, 'B');
		$pdf->SetFillColor(255, 209, 0);
		$pdf->Circle(136, 81, 2, 0, 360, 'F');
		$pdf->Rect(133.5, 81, 5, 1, 'F');
		$pdf->SetTextColor(17, 17, 17);
		$this->text($pdf, $langs, $client, 25, 86, 247, 44, 115, 'B');
		$this->text($pdf, $langs, $title ?? '', 45, 134, 227, 30, 55, '');
		$pdf->SetDrawColor(85, 85, 85);
		$pdf->SetLineWidth(0.8);
		$pdf->Line(31, 140, 31, 155);
		$pdf->Line(26, 141, 40, 141);
		$pdf->Line(31, 137, 40, 141);
		$pdf->Line(31, 137, 26, 141);
		$pdf->Line(39, 141, 39, 149);
		$pdf->Line(39, 149, 37, 150);
		$pdf->Line(28, 155, 34, 155);
		$pdf->SetTextColor(85, 85, 85);
		$this->text($pdf, $langs, $langs->transnoentities('JpsunProjectStockReference'), 104, 177, 89, 7, 13, 'B', 'L');
		$pdf->SetTextColor(17, 17, 17);
		$this->text($pdf, $langs, $ref, 104, 184, 89, 12, 28, '', 'L');
		$pdf->SetDrawColor(17, 17, 17);
		$pdf->SetLineWidth(0.5);
		$pdf->Line(104, 197, 193, 197);
	}

	/** Fit a field in its reserved area without truncating long names.
	 * @param TCPDF $pdf PDF
	 * @param Translate $langs Language
	 * @param string $value Text
	 * @param float $x X
	 * @param float $y Y
	 * @param float $w Width
	 * @param float $h Height
	 * @param float $size Maximum point size
	 * @param string $style Font style
	 * @param string $align Alignment
	 * @return void
	 */
	private function text($pdf, $langs, string $value, float $x, float $y, float $w, float $h, float $size, string $style, string $align = 'C'): void
	{
		$value = $langs->convToOutputCharset($value);
		while ($size > 6) {
			$pdf->SetFont(pdf_getPDFFont($langs), $style, $size);
			if ($pdf->getStringHeight($w, $value) <= $h) { break; }
			$size -= 0.5;
		}
		$pdf->SetXY($x, $y);
		$pdf->MultiCell($w, $h, $value, 0, $align, false, 1, null, null, true, 0, false, true, $h, 'M');
	}
}
