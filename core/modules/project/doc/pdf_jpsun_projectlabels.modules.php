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
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

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

		if (method_exists($object, 'fetch_optionals')) {
			$object->fetch_optionals();
		}

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
		$spacing = ($w <= 28) ? 0.8 : 1.2;
		$line_height = ($w <= 28) ? 3.5 : 4.5;

		$project_title = '';
		if (!empty($object->title)) {
			$project_title = $object->title;
		} elseif (!empty($object->name)) {
			$project_title = $object->name;
		}

		$project_address = empty($object->array_options['options_project_address']) ? '' : $object->array_options['options_project_address'];
		$project_zip = empty($object->array_options['options_project_zip']) ? '' : $object->array_options['options_project_zip'];
		$project_town = empty($object->array_options['options_project_town']) ? '' : $object->array_options['options_project_town'];
		$project_location = trim($project_zip.' '.$project_town);
		$category_labels = $this->getProjectCategoryLabels($object);
		$project_categories = implode(' / ', $category_labels);
		$project_year = '';
		if (!empty($object->date_start)) {
			$project_year = dol_print_date($object->date_start, '%Y');
		}

		$drawLimitedMultiCell = function ($text, $cell_x, $cell_y, $cell_width, $cell_height, $align, $style, $cell_font_size) use (&$pdf, $outputlangs) {
			if ((string) $text === '' || $cell_height <= 0) {
				return;
			}

			$output_text = $outputlangs->convToOutputCharset($text);
			$cell_line_height = max(2, $cell_font_size * 0.5);
			$pdf->SetFont(pdf_getPDFFont($outputlangs), $style, max(1, $cell_font_size));
			$pdf->SetXY($cell_x, $cell_y);
			$pdf->MultiCell($cell_width, $cell_line_height, $output_text, 0, $align, false, 1, '', '', true, 1, false, true, $cell_height, 'M', false);
		};

		$getFittedFontSize = function ($text, $base_font_size, $style, $cell_width, $cell_height) use (&$pdf, $outputlangs) {
			if ((string) $text === '') {
				return $base_font_size;
			}

			$font_size_to_try = $base_font_size;
			$min_font_size = 4;
			$words = preg_split('/\s+/u', trim((string) $text));
			$output_text = $outputlangs->convToOutputCharset($text);

			while ($font_size_to_try > $min_font_size) {
				$pdf->SetFont(pdf_getPDFFont($outputlangs), $style, $font_size_to_try);
				$longest_word_width = 0;

				foreach ($words as $word) {
					if ($word === '') {
						continue;
					}

					$longest_word_width = max($longest_word_width, $pdf->GetStringWidth($outputlangs->convToOutputCharset($word)));
				}

				$cell_line_height = max(2, $font_size_to_try * 0.5);
				$line_count = method_exists($pdf, 'getNumLines') ? $pdf->getNumLines($output_text, $cell_width) : 1;
				$text_height = max($cell_line_height, $line_count * $cell_line_height);

				if ($longest_word_width <= $cell_width && $text_height <= $cell_height) {
					break;
				}

				$font_size_to_try -= 0.5;
			}

			return max($min_font_size, $font_size_to_try);
		};

		$pdf->SetLineWidth(0.1);
		$pdf->Rect($x, $y, $w, $h);

		$logo_file = '';
		if (!empty($mysoc->logo)) {
			$logo_file = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
		}

		$logo_width = 0;
		$logo_height = 0;
		$logo_y = $inner_y;
		if (!empty($logo_file) && is_readable($logo_file)) {
			$maxLogoWidth = min($inner_width, ($w <= 28) ? $w - 6 : 42);
			$maxLogoHeight = min(($w <= 28) ? 8 : 12, max(1, ($h - (2 * $padding)) * 0.14));
			$logo_size = getimagesize($logo_file);

			if (is_array($logo_size) && !empty($logo_size[0]) && !empty($logo_size[1]) && $maxLogoWidth > 0 && $maxLogoHeight > 0) {
				$logo_ratio = min($maxLogoWidth / $logo_size[0], $maxLogoHeight / $logo_size[1]);
				$logo_width = $logo_size[0] * $logo_ratio;
				$logo_height = $logo_size[1] * $logo_ratio;
			}
		}

		if ($logo_width > 0 && $logo_height > 0) {
			$logo_x = $x + (($w - $logo_width) / 2);
			$pdf->Image($logo_file, $logo_x, $logo_y, $logo_width, $logo_height);
		}

		$text_top_y = $inner_y + $logo_height + ($logo_height > 0 ? $spacing : 0);
		$text_available_height = max(1, $bottom_y - $text_top_y);
		$slots = array(
			array('text' => trim($project_address."\n".$project_location), 'style' => 'B', 'weight' => 1.25, 'max_font' => $max_text_font_size),
			array('text' => $project_categories, 'style' => 'B', 'weight' => 0.75, 'max_font' => $max_text_font_size),
			array('text' => $project_title, 'style' => 'B', 'weight' => 3.4, 'max_font' => $max_text_font_size + 2),
			array('text' => $object->ref, 'style' => 'B', 'weight' => 1.05, 'max_font' => $max_ref_font_size),
			array('text' => $project_year, 'style' => 'B', 'weight' => 0.7, 'max_font' => $max_text_font_size),
		);
		$total_weight = 0;

		foreach ($slots as $slot) {
			$total_weight += $slot['weight'];
		}

		$current_y = $text_top_y;
		foreach ($slots as $slot) {
			$slot_height = $text_available_height * ($slot['weight'] / $total_weight);
			$text = trim((string) $slot['text']);

			if ($text !== '') {
				$font_size = $getFittedFontSize($text, $slot['max_font'], $slot['style'], $inner_width, max(1, $slot_height - $spacing));
				$drawLimitedMultiCell($text, $inner_x, $current_y, $inner_width, max(1, $slot_height - $spacing), 'C', $slot['style'], $font_size);
			}

			$current_y += $slot_height;
		}
	}

	/**
	 * Return labels of categories linked to a project.
	 *
	 * @param Project $object Project object
	 * @return string[] Category labels
	 */
	private function getProjectCategoryLabels($object)
	{
		$labels = array();

		if (empty($object->id)) {
			return $labels;
		}

		$categorie = new Categorie($this->db);
		$categories = $categorie->containing($object->id, 'project', 'label');
		if (is_array($categories)) {
			$labels = $categories;
		}

		return $labels;
	}

}
