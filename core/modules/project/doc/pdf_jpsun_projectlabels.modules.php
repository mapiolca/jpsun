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
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	* GNU General Public License for more details.
	*
	* You should have received a copy of the GNU General Public License
	* along with this program. If not, see <https://www.gnu.org/licenses/>.
	*/

/**
	* \file		core/modules/project/doc/pdf_jpsun_projectlabels.modules.php
	* \ingroup		jpsun
	* \brief		PDF project labels generator.
	*/

require_once DOL_DOCUMENT_ROOT.'/core/modules/project/modules_project.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

/**
	* Class to generate project label PDF documents.
	*/
class pdf_jpsun_projectlabels extends ModelePDFProjects
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Model name.
	 */
	public $name;

	/**
	 * @var string Model description.
	 */
	public $description;

	/**
	 * @var string Document type.
	 */
	public $type;

	/**
	 * @var string Dolibarr version of the loaded document.
	 */
	public $version = 'dolibarr';

	/**
	 * @var int Display logo option.
	 */
	public $option_logo;

	/**
	 * @var Societe Issuer company.
	 */
	public $emetteur;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$this->db = $db;

		$langs->loadLangs(array('main', 'projects', 'companies', 'jpsun@jpsun'));

		$this->name = 'jpsun_projectlabels';
		$this->description = $langs->trans('JpsunProjectLabelsPdfModel');
		$this->type = 'pdf';

		// Force A4 format for the labels sheet.
		$this->page_largeur = 210;
		$this->page_hauteur = 297;
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

		$this->option_logo = 1;
		$this->emetteur = $mysoc;
		if (!$this->emetteur->country_code) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2);
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Build project labels PDF on disk.
	 *
	 * @param Project   $object          Project object to generate
	 * @param Translate $outputlangs     Output language object
	 * @param string    $srctemplatepath Source template path
	 * @param int       $hidedetails     Hide details flag
	 * @param int       $hidedesc        Hide description flag
	 * @param int       $hideref         Hide reference flag
	 * @return int                       1 if OK, 0 if KO
	 */
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
			$this->error = $langs->transnoentities('ErrorConstantNotDefined', 'PROJECT_OUTPUTDIR');
			return 0;
		}

		$objectref = dol_sanitizeFileName($object->ref);
		$dir = $conf->project->multidir_output[$object->entity];
		if (!preg_match('/specimen/i', $objectref)) {
			$dir .= '/'.$objectref;
		}

		if (!dol_is_dir($dir)) {
			if (dol_mkdir($dir) < 0) {
				$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
				return 0;
			}
		}

		$file = $dir.'/'.$objectref.'_LABELS.pdf';

		// Create a single PDF instance and add exactly one A4 page.
		$pdf = pdf_getInstance($this->format);
		$pdf->SetCreator('Dolibarr');
		$pdf->SetTitle($objectref.' - '.$outputlangs->transnoentities('JpsunProjectLabelsPdfModel'));
		$pdf->SetSubject($outputlangs->transnoentities('JpsunProjectLabelsPdfModel'));
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetAutoPageBreak(false, $this->marge_basse);
		$pdf->AddPage();
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetDrawColor(80, 80, 80);

		$logo = '';
		if (!empty($this->emetteur->logo)) {
			$logo = $conf->mycompany->dir_output.'/logos/'.$this->emetteur->logo;
		}

		$startX = 24.5;
		$startY = 30;
		$formats = array(
			array('w' => 50, 'h' => 158),
			array('w' => 25, 'h' => 158),
			array('w' => 28, 'h' => 185),
			array('w' => 58, 'h' => 185),
		);

		$x = $startX;
		foreach ($formats as $format) {
			$this->drawProjectLabel($pdf, $object, $outputlangs, $x, $startY, $format['w'], $format['h'], $logo);
			$x += $format['w'];
		}

		$pdf->Output($file, 'F');
		dolChmod($file);

		return 1;
	}

	/**
	 * Return project thirdparty when available.
	 *
	 * @param Project $object Project object
	 * @return Societe|null Thirdparty object or null
	 */
	private function getProjectThirdparty($object)
	{
		$thirdparty = null;

		if (!empty($object->thirdparty) && is_object($object->thirdparty)) {
			$thirdparty = $object->thirdparty;
		} elseif (!empty($object->socid)) {
			$thirdparty = new Societe($this->db);
			$result = $thirdparty->fetch($object->socid);
			if ($result <= 0) {
				$thirdparty = null;
			}
		}

		return $thirdparty;
	}

	/**
	 * Draw one project label.
	 *
	 * @param TCPDF     $pdf         PDF instance
	 * @param Project   $object      Project object
	 * @param Translate $outputlangs Output language object
	 * @param float     $x           X position
	 * @param float     $y           Y position
	 * @param float     $w           Label width
	 * @param float     $h           Label height
	 * @param string    $logo        Logo path
	 * @return void
	 */
	private function drawProjectLabel(&$pdf, $object, $outputlangs, $x, $y, $w, $h, $logo)
	{
		global $conf, $mysoc;

		$pdf->Rect($x, $y, $w, $h);

		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$font_size = $default_font_size;
		if ($w <= 28) {
			$font_size = $default_font_size - 2;
		}

		$thirdparty = $this->getProjectThirdparty($object);
		$thirdparty_name = '';
		if (is_object($thirdparty)) {
			$thirdparty_name = pdfBuildThirdpartyName($thirdparty, $outputlangs);
		}

		$padding = 2;
		$inner_x = $x + $padding;
		$inner_y = $y + $padding;
		$inner_width = max(1, $w - (2 * $padding));
		$bottom_y = $y + $h - $padding;
		$current_y = $inner_y;
		$spacing = ($w <= 28) ? 0.8 : 1.2;
		$line_height = ($w <= 28) ? 3.5 : 4.5;

		// Keep a local logo fallback for older calls but prefer the current company logo.
		$logo_file = $logo;
		if (!empty($mysoc->logo)) {
			$logo_file = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
		}

		// Draw the company logo at the top of each label when the file is readable.
		$maxLogoWidth = min($w - 4, 42);
		if ($w <= 28) {
			$maxLogoWidth = min($maxLogoWidth, $w - 6);
		}

		if (!empty($logo_file) && is_readable($logo_file) && $maxLogoWidth > 0 && $current_y < $bottom_y) {
			$logo_width = min($inner_width, $maxLogoWidth);
			$logo_height = min(pdf_getHeightForLogo($logo_file), ($w <= 28) ? 8 : 12, $bottom_y - $current_y);
			$logo_x = $x + (($w - $logo_width) / 2);
			if ($logo_width > 0 && $logo_height > 0) {
				$pdf->Image($logo_file, $logo_x, $current_y, $logo_width, $logo_height);
				$current_y += $logo_height + $spacing;
			}
		} elseif (!empty($mysoc->name) && $current_y < $bottom_y) {
			$current_y = $this->drawLimitedMultiCell(
				$pdf, $outputlangs, $mysoc->name, $inner_x, $current_y, $inner_width,
				$bottom_y - $current_y, $line_height, 'C', 'B', $font_size
			);
			$current_y += $spacing;
		}

		$current_y = $this->drawLimitedMultiCell(
			$pdf, $outputlangs, $object->ref, $inner_x, $current_y, $inner_width,
			$bottom_y - $current_y, $line_height, 'C', 'B', $font_size + 1
		);
		$current_y += $spacing;

		$current_y = $this->drawLimitedMultiCell(
			$pdf, $outputlangs, $object->title, $inner_x, $current_y, $inner_width,
			$bottom_y - $current_y, $line_height, 'L', '', $font_size
		);
		$current_y += $spacing;

		$this->drawLimitedMultiCell(
			$pdf, $outputlangs, $thirdparty_name, $inner_x, $current_y, $inner_width,
			$bottom_y - $current_y, $line_height, 'L', '', $font_size
		);
	}

	/**
	 * Draw text inside a limited area without overflowing the label.
	 *
	 * @param TCPDF     $pdf         PDF instance
	 * @param Translate $outputlangs Output language object
	 * @param string    $text        Text to print
	 * @param float     $x           X position
	 * @param float     $y           Y position
	 * @param float     $w           Cell width
	 * @param float     $max_height  Maximum cell height
	 * @param float     $line_height Line height
	 * @param string    $align       Text alignment
	 * @param string    $style       Font style
	 * @param float     $font_size   Font size
	 * @return float                 New Y position
	 */
	private function drawLimitedMultiCell(&$pdf, $outputlangs, $text, $x, $y, $w, $max_height, $line_height, $align, $style, $font_size)
	{
		if ((string) $text === '' || $max_height <= 0) {
			return $y;
		}

		$pdf->SetFont(pdf_getPDFFont($outputlangs), $style, max(4, $font_size));
		$pdf->SetXY($x, $y);
		$pdf->MultiCell($w, $line_height, $outputlangs->convToOutputCharset($text), 0, $align, false, 1, '', '', true, 0, false, true, $max_height, 'T', true);

		return min($pdf->GetY(), $y + $max_height);
	}
}
