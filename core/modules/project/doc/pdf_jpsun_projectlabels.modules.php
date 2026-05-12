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
		$pdf_model_label = $outputlangs->transnoentities('JpsunProjectLabelsPdfModel');
		$pdf_labels_label = $outputlangs->transnoentities('JpsunProjectLabels');
		$pdf_format_label = $outputlangs->transnoentities('JpsunProjectLabelFormat');
		$pdf->SetTitle($objectref.' - '.$pdf_labels_label);
		$pdf->SetSubject($pdf_model_label.' - '.$pdf_format_label);
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

		// Keep the label layout horizontal. If rotation is approved later, wrap the rotated block with TCPDF StartTransform(), Rotate() and StopTransform().
		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$max_ref_font_size = ($w <= 25) ? max(9, $default_font_size) : (($w <= 28) ? max(10, $default_font_size) : max(18, $default_font_size + 7));
		$max_text_font_size = ($w <= 25) ? max(7, $default_font_size - 1) : (($w <= 28) ? max(8, $default_font_size) : max(13, $default_font_size + 2));

		$padding = 2;
		$inner_x = $x + $padding;
		$inner_y = $y + $padding;
		$inner_width = max(1, $w - (2 * $padding));
		$bottom_y = $y + $h - $padding;
		$current_y = $inner_y;
		$spacing = ($w <= 28) ? 0.8 : 1.2;
		$line_height = ($w <= 28) ? 3.5 : 4.5;

		$thirdparty = $this->getProjectThirdparty($object);
		$thirdparty_name = '';
		if (is_object($thirdparty)) {
			$thirdparty_name = pdfBuildThirdpartyName($thirdparty, $outputlangs);
		}

		$project_title = '';
		if (!empty($object->title)) {
			$project_title = $object->title;
		} elseif (!empty($object->name)) {
			$project_title = $object->name;
		}

		$drawLimitedMultiCell = function ($text, $cell_x, $cell_y, $cell_width, $max_height, $cell_line_height, $align, $style, $cell_font_size) use (&$pdf, $outputlangs) {
			if ((string) $text === '' || $max_height <= 0) {
				return $cell_y;
			}

			$output_text = $outputlangs->convToOutputCharset($text);
			$pdf->SetFont(pdf_getPDFFont($outputlangs), $style, max(1, $cell_font_size));
			$line_count = method_exists($pdf, 'getNumLines') ? $pdf->getNumLines($output_text, $cell_width) : 1;
			$cell_height = min($max_height, max($cell_line_height, $line_count * $cell_line_height));

			$pdf->SetXY($cell_x, $cell_y);
			$pdf->MultiCell($cell_width, $cell_line_height, $output_text, 0, $align, false, 1, '', '', true, 1, false, true, $cell_height, 'T', false);

			return $cell_y + $cell_height;
		};

		$getNoWordCutFontSize = function ($text, $base_font_size, $style) use (&$pdf, $outputlangs, $inner_width) {
			$font_size_to_try = $base_font_size;
			$min_font_size = max(4, $base_font_size - 3);
			$words = preg_split('/\s+/u', trim((string) $text));

			if (empty($words)) {
				return $font_size_to_try;
			}

			while ($font_size_to_try > $min_font_size) {
				$pdf->SetFont(pdf_getPDFFont($outputlangs), $style, $font_size_to_try);
				$longest_word_width = 0;

				foreach ($words as $word) {
					if ($word === '') {
						continue;
					}

					$longest_word_width = max($longest_word_width, $pdf->GetStringWidth($outputlangs->convToOutputCharset($word)));
				}

				if ($longest_word_width <= $inner_width) {
					break;
				}

				$font_size_to_try -= 0.5;
			}

			return max($min_font_size, $font_size_to_try);
		};

		$getLineHeight = function ($cell_font_size) use ($line_height) {
			return max($line_height, $cell_font_size * 0.5);
		};

		$getTextBlockHeight = function ($text, $cell_width, $cell_line_height, $style, $cell_font_size) use (&$pdf, $outputlangs) {
			if ((string) $text === '') {
				return 0;
			}

			$output_text = $outputlangs->convToOutputCharset($text);
			$pdf->SetFont(pdf_getPDFFont($outputlangs), $style, max(1, $cell_font_size));
			$line_count = method_exists($pdf, 'getNumLines') ? $pdf->getNumLines($output_text, $cell_width) : 1;

			return max($cell_line_height, $line_count * $cell_line_height);
		};

		$pdf->SetLineWidth(0.1);
		$pdf->Rect($x, $y, $w, $h);

		$logo_file = '';
		if (!empty($mysoc->logo)) {
			$logo_file = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
		}

		$logo_width = 0;
		$logo_height = 0;
		if (!empty($logo_file) && is_readable($logo_file)) {
			$maxLogoWidth = min($inner_width, ($w <= 28) ? $w - 6 : 42);
			$maxLogoHeight = min(($w <= 28) ? 8 : 12, $h - (2 * $padding));
			$logo_size = getimagesize($logo_file);

			if (is_array($logo_size) && !empty($logo_size[0]) && !empty($logo_size[1]) && $maxLogoWidth > 0 && $maxLogoHeight > 0) {
				$logo_ratio = min($maxLogoWidth / $logo_size[0], $maxLogoHeight / $logo_size[1]);
				$logo_width = $logo_size[0] * $logo_ratio;
				$logo_height = $logo_size[1] * $logo_ratio;
			}
		}

		$ref_font_size = $getNoWordCutFontSize($object->ref, $max_ref_font_size, 'B');
		$title_font_size = $getNoWordCutFontSize($project_title, $max_text_font_size, '');
		$thirdparty_font_size = $getNoWordCutFontSize($thirdparty_name, $max_text_font_size, '');
		$ref_line_height = $getLineHeight($ref_font_size);
		$title_line_height = $getLineHeight($title_font_size);
		$thirdparty_line_height = $getLineHeight($thirdparty_font_size);
		$ref_height = $getTextBlockHeight($object->ref, $inner_width, $ref_line_height, 'B', $ref_font_size);
		$title_height = $getTextBlockHeight($project_title, $inner_width, $title_line_height, '', $title_font_size);
		$thirdparty_height = $getTextBlockHeight($thirdparty_name, $inner_width, $thirdparty_line_height, '', $thirdparty_font_size);
		$content_height = $ref_height + $title_height + $thirdparty_height;

		if ($logo_height > 0) {
			$content_height += $logo_height + $spacing;
		}
		if ($title_height > 0) {
			$content_height += $spacing;
		}
		if ($thirdparty_height > 0) {
			$content_height += $spacing;
		}

		$current_y = $inner_y + max(0, (($bottom_y - $inner_y) - $content_height) / 2);

		if ($logo_width > 0 && $logo_height > 0 && $current_y < $bottom_y) {
			$logo_x = $x + (($w - $logo_width) / 2);
			$pdf->Image($logo_file, $logo_x, $current_y, $logo_width, $logo_height);
			$current_y += $logo_height + $spacing;
		}

		$current_y = $drawLimitedMultiCell(
			$object->ref, $inner_x, $current_y, $inner_width,
			$bottom_y - $current_y, $ref_line_height, 'C', 'B', $ref_font_size
		);

		if ($title_height > 0) {
			$current_y += $spacing;
			$current_y = $drawLimitedMultiCell(
				$project_title, $inner_x, $current_y, $inner_width,
				$bottom_y - $current_y, $title_line_height, 'C', '', $title_font_size
			);
		}

		if ($thirdparty_height > 0) {
			$current_y += $spacing;
			$drawLimitedMultiCell(
				$thirdparty_name, $inner_x, $current_y, $inner_width,
				$bottom_y - $current_y, $thirdparty_line_height, 'C', '', $thirdparty_font_size
			);
		}
	}
}
