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

		// EN: Force A4 format for the labels sheet.
		// FR: Forcer le format A4 pour la planche d'étiquettes.
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

		// EN: Create a single PDF instance and add exactly one A4 page.
		// FR: Créer une seule instance PDF et ajouter une seule page A4.
		$pdf = pdf_getInstance($this->format);
		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$pdf->SetCreator('Dolibarr');
		$pdf->SetTitle($objectref.' - '.$outputlangs->transnoentities('JpsunProjectLabelsPdfModel'));
		$pdf->SetSubject($outputlangs->transnoentities('JpsunProjectLabelsPdfModel'));
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetAutoPageBreak(false, $this->marge_basse);
		$pdf->AddPage();
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetDrawColor(80, 80, 80);

		$thirdparty = $this->getProjectThirdparty($object);
		$labeldata = $this->getLabelData($object, $thirdparty, $outputlangs);

		$label_count = 4;
		$gap = 3;
		$available_width = $this->page_largeur - $this->marge_gauche - $this->marge_droite - ($gap * ($label_count - 1));
		$label_width = $available_width / $label_count;
		$label_height = 55;
		$start_y = $this->marge_haute;

		for ($i = 0; $i < $label_count; $i++) {
			$x = $this->marge_gauche + ($i * ($label_width + $gap));
			$this->drawProjectLabel($pdf, $x, $start_y, $label_width, $label_height, $labeldata, $default_font_size, $outputlangs);
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
	 * Prepare visible label lines.
	 *
	 * @param Project      $object      Project object
	 * @param Societe|null $thirdparty  Thirdparty object
	 * @param Translate    $outputlangs Output language object
	 * @return array<int,array{label:string,value:string}> Label lines
	 */
	private function getLabelData($object, $thirdparty, $outputlangs)
	{
		$thirdparty_name = '';
		if (is_object($thirdparty)) {
			$thirdparty_name = pdfBuildThirdpartyName($thirdparty, $outputlangs);
		}

		return array(
			array('label' => $outputlangs->transnoentities('Ref'), 'value' => $object->ref),
			array('label' => $outputlangs->transnoentities('Project'), 'value' => $object->title),
			array('label' => $outputlangs->transnoentities('ThirdParty'), 'value' => $thirdparty_name),
		);
	}

	/**
	 * Draw one project label.
	 *
	 * @param TCPDF     $pdf               PDF instance
	 * @param float     $x                 X position
	 * @param float     $y                 Y position
	 * @param float     $width             Label width
	 * @param float     $height            Label height
	 * @param array     $labeldata         Label content
	 * @param int       $default_font_size Default PDF font size
	 * @param Translate $outputlangs       Output language object
	 * @return void
	 */
	private function drawProjectLabel(&$pdf, $x, $y, $width, $height, $labeldata, $default_font_size, $outputlangs)
	{
		$pdf->Rect($x, $y, $width, $height);

		$padding = 3;
		$inner_x = $x + $padding;
		$inner_width = $width - (2 * $padding);
		$current_y = $y + $padding;

		$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', $default_font_size + 1);
		$pdf->SetXY($inner_x, $current_y);
		$pdf->MultiCell($inner_width, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Project')), 0, 'C');
		$current_y = $pdf->GetY() + 2;

		foreach ($labeldata as $line) {
			if ($line['value'] === '') {
				continue;
			}

			$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', $default_font_size - 2);
			$pdf->SetXY($inner_x, $current_y);
			$pdf->MultiCell($inner_width, 4, $outputlangs->convToOutputCharset($line['label']), 0, 'L');
			$current_y = $pdf->GetY();

			$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $default_font_size - 1);
			$pdf->SetXY($inner_x, $current_y);
			$pdf->MultiCell($inner_width, 5, $outputlangs->convToOutputCharset($line['value']), 0, 'L');
			$current_y = $pdf->GetY() + 1;
		}
	}
}
