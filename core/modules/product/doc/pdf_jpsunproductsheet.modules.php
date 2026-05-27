<?php
/* Copyright (C) 2026	Pierre Ardoin		<developpeur@lesmetiersdubatiment.fr>
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
 *	\file       htdocs/core/modules/product/doc/pdf_jpsunproductsheet.modules.php
 *	\ingroup    product
 *	\brief      File of class to build JPSUN product sheet PDF documents.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/product/modules_product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';


/**
 *	Class to generate JPSUN product sheet PDF documents.
 */
class pdf_jpsunproductsheet extends ModelePDFProduct
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string model name
	 */
	public $name;

	/**
	 * @var string model description
	 */
	public $description;

	/**
	 * @var int Save the generated filename as main document.
	 */
	public $update_main_doc_field;

	/**
	 * @var string document type
	 */
	public $type;

	/**
	 * Dolibarr version of the loaded document.
	 *
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * @var Societe Object that emits the document
	 */
	public $emetteur;

	/**
	 * @var float|int
	 */
	public $corner_radius;


	/**
	 * Constructor.
	 *
	 * @param	DoliDB	$db		Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$langs->loadLangs(array('main', 'companies', 'products', 'categories', 'jpsun@jpsun'));

		$this->db = $db;
		$this->name = $langs->trans('JpsunProductSheetName');
		$this->description = $langs->trans('DocModelJpsunProductSheetDescription');
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
		$this->corner_radius = getDolGlobalInt('MAIN_PDF_FRAME_CORNER_RADIUS', 2);

		$this->option_logo = 1;
		$this->option_multilang = 1;
		$this->option_freetext = 1;

		if ($mysoc === null) {
			dol_syslog(get_class($this).'::__construct() Global $mysoc should not be null.'.getCallerInfoString(), LOG_ERR);
			return;
		}

		$this->emetteur = $mysoc;
		if (empty($this->emetteur->country_code)) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2);
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Build a PDF document onto disk.
	 *
	 * @param	Product		$object				Object to generate
	 * @param	Translate	$outputlangs		Lang output object
	 * @param	string		$srctemplatepath	Full path of source filename for generator using a template file
	 * @param	int			$hidedetails		Do not show line details
	 * @param	int			$hidedesc			Do not show description
	 * @param	int			$hideref			Do not show reference
	 * @return	int								1=OK, 0=KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $user, $langs, $conf, $hookmanager;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		if (getDolGlobalString('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		$outputlangs->loadLangs(array('main', 'dict', 'companies', 'products', 'categories', 'jpsun@jpsun'));

		if (empty($conf->product->dir_output)) {
			$this->error = $langs->trans('ErrorConstantNotDefined', 'PRODUCT_OUTPUTDIR');
			return 0;
		}

		if ($object->specimen) {
			$dir = $conf->product->dir_output;
			$file = $dir.'/SPECIMEN.pdf';
		} else {
			$objectref = dol_sanitizeFileName($object->ref);
			$dir = $conf->product->dir_output.'/'.$objectref;
			$file = $dir.'/'.$objectref.'.pdf';
		}

		if (!file_exists($dir)) {
			if (dol_mkdir($dir) < 0) {
				$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
				return 0;
			}
		}
		if (!file_exists($dir)) {
			$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
			return 0;
		}

		if (!is_object($hookmanager)) {
			include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}
		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		global $action;
		$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);
		if ($reshook < 0) {
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
			return 0;
		}

		$pdf = pdf_getInstance($this->format);
		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$pdf->SetAutoPageBreak(1, 0);

		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs));

		$tplidx = null;
		if (!getDolGlobalString('MAIN_DISABLE_FPDI') && getDolGlobalString('MAIN_ADD_PDF_BACKGROUND')) {
			$logodir = $conf->mycompany->dir_output;
			if (!empty($conf->mycompany->multidir_output[$object->entity])) {
				$logodir = $conf->mycompany->multidir_output[$object->entity];
			}
			$pagecount = $pdf->setSourceFile($logodir.'/'.getDolGlobalString('MAIN_ADD_PDF_BACKGROUND'));
			$tplidx = $pdf->importPage(1);
		}

		$pdf->Open();
		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetSubject($outputlangs->transnoentities('JpsunProductSheetTitle'));
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
		$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref.' '.$object->label));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}

		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->AddPage();
		if (!empty($tplidx)) {
			$pdf->useTemplate($tplidx);
		}

		$this->_pagehead($pdf, $object, 0, $outputlangs);
		$this->renderProductSheet($pdf, $object, $outputlangs, $default_font_size);
		$this->_pagefoot($pdf, $object, $outputlangs);

		if (method_exists($pdf, 'AliasNbPages')) {
			$pdf->AliasNbPages();
		}
		$pdf->Close();
		$pdf->Output($file, 'F');

		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);
		if ($reshook < 0) {
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
		}

		dolChmod($file);
		$this->result = array('fullpath' => $file);

		return 1;
	}

	/**
	 * Render the one-page product sheet body.
	 *
	 * @param	TCPDF		$pdf					PDF object
	 * @param	Product		$object					Product object
	 * @param	Translate	$outputlangs			Output language
	 * @param	int			$default_font_size		Default PDF font size
	 * @return	void
	 */
	private function renderProductSheet(&$pdf, $object, $outputlangs, $default_font_size)
	{
		$text = $this->getLocalizedProductText($object, $outputlangs);
		$images = $this->getProductImages($object);
		$features = $this->buildFeatureRows($object, $outputlangs);
		$categories = $this->getProductCategoryLabels($object);
		$publicNotes = $this->getPublicNotesText($object, $outputlangs);

		$dark = array(0, 58, 79);
		$accent = array(247, 184, 32);
		$soft = array(255, 249, 232);
		$light = array(245, 246, 247);
		$muted = array(78, 83, 89);

		// Large visual accent inspired by the provided one-pager.
		$pdf->SetFillColor($accent[0], $accent[1], $accent[2]);
		$pdf->Rect(55, 35, $this->page_largeur - 55, 92, 'F');

		$this->drawCompanyLogo($pdf, $object, $outputlangs, 10, 10, 26, 16);

		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', 'B', $default_font_size + 9);
		$pdf->SetXY(38, 11);
		$pdf->MultiCell(160, 12, $outputlangs->convToOutputCharset($text['label']), 0, 'L', false, 1, '', '', true, 0, false, true, 18, 'T', true);

		$pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
		$pdf->SetFont('', '', $default_font_size + 1);
		$pdf->SetXY(38, 30);
		$pdf->MultiCell(160, 5, $outputlangs->transnoentities('Ref').' '.$outputlangs->convToOutputCharset($object->ref), 0, 'L', false, 1, '', '', true, 0, false, true, 8, 'T', true);

		$bodyX = 39;
		$bodyW = 151;
		$mainImageX = 124;
		$mainImageY = 48;
		$mainImageW = 76;
		$mainImageH = 76;
		$mainImageBottomY = $mainImageY + $mainImageH;
		$tableGap = 2;
		$sheetBottomY = min($this->page_hauteur - $this->marge_basse - 17, 270);
		$categoriesHeight = $this->getCategoriesTableHeight($pdf, $categories, $outputlangs, $default_font_size, $bodyW, 48);
		$featuresHeight = empty($categories) ? 68 : 58;
		$notesHeight = ($publicNotes === '') ? 0 : 24;
		$categoriesY = $sheetBottomY - $categoriesHeight;
		$featuresY = $categoriesY - ($categoriesHeight > 0 ? $tableGap : 0) - $featuresHeight;
		$notesY = ($notesHeight > 0) ? $featuresY - $tableGap - $notesHeight : 0;
		$descriptionBottomY = (($notesHeight > 0) ? $notesY : $featuresY) - $tableGap;
		$descriptionBodyY = 83;
		$descriptionBelowImageY = $mainImageBottomY + 5;
		$descriptionZones = array();
		if ($mainImageBottomY > $descriptionBodyY) {
			$descriptionZones[] = array('x' => $bodyX, 'y' => $descriptionBodyY, 'w' => $mainImageX - $bodyX - 6, 'h' => $mainImageBottomY - $descriptionBodyY);
		}
		if ($descriptionBottomY > $descriptionBelowImageY) {
			$descriptionZones[] = array('x' => $bodyX, 'y' => $descriptionBelowImageY, 'w' => $bodyW, 'h' => $descriptionBottomY - $descriptionBelowImageY);
		}

		$this->renderVisualColumn($pdf, $images, 12, 68, 23, 23, 9);
		$this->renderMainImage($pdf, $images, $outputlangs, $default_font_size, $mainImageX, $mainImageY, $mainImageW, $mainImageH, $soft, $muted);
		$this->renderDescriptionFlowBlock($pdf, $text['description'], $outputlangs, $default_font_size, $bodyX, 61, $descriptionZones, $dark);
		$this->renderPublicNotesBlock($pdf, $publicNotes, $outputlangs, $default_font_size, $bodyX, $notesY, $bodyW, $notesHeight, $dark, $light);
		$this->renderFeaturesTable($pdf, $features, $outputlangs, $default_font_size, $bodyX, $featuresY, $bodyW, $featuresHeight, $dark, $light);
		$this->renderCategoriesTable($pdf, $categories, $outputlangs, $default_font_size, $bodyX, $categoriesY, $bodyW, $categoriesHeight, $dark, $light);

		$pdf->SetTextColor(0, 0, 0);
	}

	/**
	 * Return localized product label and description when Dolibarr multilingual data exists.
	 *
	 * @param	Product		$object			Product object
	 * @param	Translate	$outputlangs	Output language
	 * @return	array{label:string,description:string}
	 */
	private function getLocalizedProductText($object, $outputlangs)
	{
		$label = empty($object->label) ? $object->ref : $object->label;
		$description = empty($object->description) ? '' : $object->description;

		if (!empty($object->id) && getDolGlobalInt('MAIN_MULTILANGS') && !empty($outputlangs->defaultlang)) {
			$sql = 'SELECT label, description';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'product_lang';
			$sql .= ' WHERE fk_product = '.((int) $object->id);
			$sql .= " AND lang = '".$this->db->escape($outputlangs->defaultlang)."'";
			$sql .= ' LIMIT 1';

			$resql = $this->db->query($sql);
			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				if ($obj) {
					if ($obj->label !== '') {
						$label = $obj->label;
					}
					if ($obj->description !== '') {
						$description = $obj->description;
					}
				}
				$this->db->free($resql);
			}
		}

		return array('label' => $label, 'description' => $description);
	}

	/**
	 * Return product images in Dolibarr display order.
	 *
	 * @param	Product	$object		Product object
	 * @return	array<int,array{photo:string,thumb:string}>
	 */
	private function getProductImages($object)
	{
		global $conf;

		$pdir = array();

		if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO')) {
			$pdir[] = get_exdir($object->id, 2, 0, 0, $object, 'product').$object->id.'/photos/';
			$pdir[] = get_exdir(0, 0, 0, 0, $object, 'product').dol_sanitizeFileName($object->ref).'/';
		} else {
			$pdir[] = get_exdir(0, 0, 0, 0, $object, 'product');
			$pdir[] = get_exdir($object->id, 2, 0, 0, $object, 'product').$object->id.'/photos/';
		}

		foreach ($pdir as $midir) {
			if (!empty($conf->product->multidir_output[$object->entity]) && $conf->entity != $object->entity) {
				$dir = $conf->product->multidir_output[$object->entity].'/'.$midir;
			} else {
				$dir = $conf->product->dir_output.'/'.$midir;
			}

			if (!is_dir($dir)) {
				continue;
			}

			$firstPhotos = $object->liste_photos($dir, 1);
			if (empty($firstPhotos)) {
				continue;
			}

			$images = array();
			$alreadyseen = array();
			foreach ($firstPhotos as $photo) {
				$image = $this->buildProductImagePath($dir, $photo);
				if (!empty($image['photo'])) {
					$sourcepath = $image['source'];
					unset($image['source']);
					$images[] = $image;
					$alreadyseen[$sourcepath] = 1;
					break;
				}
			}

			if (empty($images)) {
				continue;
			}

			$photos = $object->liste_photos($dir);
			foreach ($photos as $photo) {
				if (empty($photo['photo']) || !empty($alreadyseen[$dir.$photo['photo']])) {
					continue;
				}

				$image = $this->buildProductImagePath($dir, $photo);
				if (empty($image['photo'])) {
					continue;
				}

				$sourcepath = $image['source'];
				unset($image['source']);
				$images[] = $image;
				$alreadyseen[$sourcepath] = 1;
			}

			return $images;
		}

		return array();
	}

	/**
	 * Build the product image path according to the same quality rule as JPSUN quote/order PDFs.
	 *
	 * @param	string	$dir		Product image directory
	 * @param	array	$photo		Photo metadata returned by liste_photos()
	 * @return	array{photo:string,thumb:string,source:string}
	 */
	private function buildProductImagePath($dir, $photo)
	{
		if (empty($photo['photo'])) {
			return array('photo' => '', 'thumb' => '', 'source' => '');
		}

		$sourcepath = $dir.$photo['photo'];
		$displaypath = $sourcepath;
		if (!getDolGlobalInt('CAT_HIGH_QUALITY_IMAGES') && !empty($photo['photo_vignette']) && is_readable($dir.$photo['photo_vignette'])) {
			$displaypath = $dir.$photo['photo_vignette'];
		}
		if (!is_readable($displaypath)) {
			return array('photo' => '', 'thumb' => '', 'source' => '');
		}

		return array('photo' => $displaypath, 'thumb' => $displaypath, 'source' => $sourcepath);
	}

	/**
	 * Build visible standard feature rows.
	 *
	 * @param	Product		$object			Product object
	 * @param	Translate	$outputlangs	Output language
	 * @return	array<int,array{label:string,value:string}>
	 */
	private function buildFeatureRows($object, $outputlangs)
	{
		$rows = array();

		$type = ((int) $object->type === 1) ? $outputlangs->transnoentities('Service') : $outputlangs->transnoentities('Product');
		$this->addFeatureRow($rows, $outputlangs->transnoentities('Type'), $type);

		$this->addFeatureRow($rows, $outputlangs->transnoentities('Weight'), $this->formatMeasure($object, 'weight', 'weight', $outputlangs));
		$this->addFeatureRow($rows, $outputlangs->transnoentities('JpsunProductSheetDimensions'), $this->formatDimensions($object, $outputlangs));
		$this->addFeatureRow($rows, $outputlangs->transnoentities('Surface'), $this->formatMeasure($object, 'surface', 'surface', $outputlangs));
		$this->addFeatureRow($rows, $outputlangs->transnoentities('Volume'), $this->formatMeasure($object, 'volume', 'volume', $outputlangs));
		$this->addFeatureRow($rows, $outputlangs->transnoentities('Barcode'), isset($object->barcode) ? $object->barcode : '');
		$this->addFeatureRow($rows, $outputlangs->transnoentities('JpsunProductSheetCountryOrigin'), $this->formatCountryOrigin($object, $outputlangs));
		$this->addFeatureRow($rows, $outputlangs->transnoentities('JpsunProductSheetCustomsCode'), isset($object->customcode) ? $object->customcode : '');
		$this->addPrintableExtrafieldRows($rows, $object, $outputlangs);

		return $rows;
	}

	/**
	 * Add non-empty product extrafields flagged as printable.
	 *
	 * @param	array<int,array{label:string,value:string}>	$rows			Feature rows
	 * @param	Product										$object			Product object
	 * @param	Translate									$outputlangs	Output language
	 * @return	void
	 */
	private function addPrintableExtrafieldRows(&$rows, $object, $outputlangs)
	{
		$extrafields = new ExtraFields($this->db);
		$extralabels = $extrafields->fetch_name_optionals_label('product');
		if (empty($extralabels) || empty($extrafields->attributes['product']['label'])) {
			return;
		}

		if (method_exists($object, 'fetch_optionals')) {
			$object->fetch_optionals();
		}

		$attributes = $extrafields->attributes['product'];
		$keys = array_keys($extralabels);
		usort($keys, function ($a, $b) use ($attributes) {
			$posA = isset($attributes['pos'][$a]) ? (int) $attributes['pos'][$a] : 0;
			$posB = isset($attributes['pos'][$b]) ? (int) $attributes['pos'][$b] : 0;
			if ($posA === $posB) {
				return strcmp($a, $b);
			}

			return ($posA < $posB) ? -1 : 1;
		});

		foreach ($keys as $key) {
			if (empty($attributes['printable'][$key])) {
				continue;
			}

			$optionKey = 'options_'.$key;
			if (!isset($object->array_options[$optionKey]) || $object->array_options[$optionKey] === '') {
				continue;
			}

			$value = $extrafields->showOutputField($key, $object->array_options[$optionKey], '', 'product', $outputlangs, $object);
			$value = $this->plainTextForPdf($value);
			if ($value === '') {
				continue;
			}

			$label = $this->getExtrafieldLabel($attributes, $key, $outputlangs);
			$this->addFeatureRow($rows, $label, $value);
		}
	}

	/**
	 * Return a translated extrafield label when possible.
	 *
	 * @param	array		$attributes		Extrafield attributes for products
	 * @param	string		$key			Extrafield key
	 * @param	Translate	$outputlangs	Output language
	 * @return	string
	 */
	private function getExtrafieldLabel($attributes, $key, $outputlangs)
	{
		if (!empty($attributes['langfile'][$key])) {
			$outputlangs->load($attributes['langfile'][$key]);
		}

		$label = isset($attributes['label'][$key]) ? $attributes['label'][$key] : $key;
		$translated = $outputlangs->transnoentities($label);
		if ($translated !== '' && $translated !== $label) {
			return $translated;
		}

		return $label;
	}

	/**
	 * Return labels of categories linked to a product.
	 *
	 * @param	Product	$object		Product object
	 * @return	string[]			Category labels
	 */
	private function getProductCategoryLabels($object)
	{
		$labels = array();

		if (empty($object->id)) {
			return $labels;
		}

		$categorie = new Categorie($this->db);
		$categories = $categorie->containing($object->id, 'product', 'label');
		if (is_array($categories)) {
			$labels = $categories;
		}

		return $labels;
	}

	/**
	 * Return public notes prepared for PDF output.
	 *
	 * @param	Product		$object			Product object
	 * @param	Translate	$outputlangs	Output language
	 * @return	string						Public notes as plain text
	 */
	private function getPublicNotesText($object, $outputlangs)
	{
		$notetoshow = empty($object->note_public) ? '' : $object->note_public;
		if ($notetoshow === '') {
			return '';
		}

		$substitutionarray = pdf_getSubstitutionArray($outputlangs, null, $object);
		complete_substitutions_array($substitutionarray, $outputlangs, $object);
		$notetoshow = make_substitutions($notetoshow, $substitutionarray, $outputlangs);
		if (function_exists('convertBackOfficeMediasLinksToPublicLinks')) {
			$notetoshow = convertBackOfficeMediasLinksToPublicLinks($notetoshow);
		}

		return $this->plainTextForPdf($notetoshow);
	}

	/**
	 * Add a non-empty feature row.
	 *
	 * @param	array<int,array{label:string,value:string}>	$rows	Rows
	 * @param	string										$label	Label
	 * @param	string										$value	Value
	 * @return	void
	 */
	private function addFeatureRow(&$rows, $label, $value)
	{
		if ($value === null || $value === '') {
			return;
		}

		$rows[] = array('label' => $label, 'value' => (string) $value);
	}

	/**
	 * Format a measurable product field.
	 *
	 * @param	Product		$object			Product object
	 * @param	string		$field			Value field
	 * @param	string		$style			Unit style
	 * @param	Translate	$outputlangs	Output language
	 * @return	string
	 */
	private function formatMeasure($object, $field, $style, $outputlangs)
	{
		$value = isset($object->{$field}) ? $object->{$field} : '';
		if (!$this->hasMeasure($value)) {
			return '';
		}

		$unitfield = $field.'_units';
		$unit = '';
		if (isset($object->{$unitfield}) && $object->{$unitfield} !== '') {
			$unit = measuring_units_string($object->{$unitfield}, $style, 0, 1, $outputlangs);
		}

		return trim(price2num($value, 'MS').' '.(($unit === -1 || $unit === '-1') ? '' : $unit));
	}

	/**
	 * Format length x width x height values.
	 *
	 * @param	Product		$object			Product object
	 * @param	Translate	$outputlangs	Output language
	 * @return	string
	 */
	private function formatDimensions($object, $outputlangs)
	{
		$parts = array();
		foreach (array('length', 'width', 'height') as $field) {
			$value = isset($object->{$field}) ? $object->{$field} : '';
			if (!$this->hasMeasure($value)) {
				continue;
			}

			$unit = '';
			$unitfield = $field.'_units';
			if (isset($object->{$unitfield}) && $object->{$unitfield} !== '') {
				$unit = measuring_units_string($object->{$unitfield}, 'size', 0, 1, $outputlangs);
			}
			$parts[] = trim(price2num($value, 'MS').' '.(($unit === -1 || $unit === '-1') ? '' : $unit));
		}

		return implode(' x ', $parts);
	}

	/**
	 * Return true if a physical measurement has a meaningful value.
	 *
	 * @param	mixed	$value	Value
	 * @return	bool
	 */
	private function hasMeasure($value)
	{
		if ($value === null || $value === '') {
			return false;
		}

		if (is_numeric($value) && (float) $value == 0.0) {
			return false;
		}

		return true;
	}

	/**
	 * Format country of origin.
	 *
	 * @param	Product		$object			Product object
	 * @param	Translate	$outputlangs	Output language
	 * @return	string
	 */
	private function formatCountryOrigin($object, $outputlangs)
	{
		if (!empty($object->country)) {
			return $object->country;
		}
		if (!empty($object->country_id) && function_exists('getCountry')) {
			$country = getCountry($object->country_id, 0, $this->db, $outputlangs);
			if (!empty($country) && $country !== 'NotDefined') {
				return $country;
			}
		}
		if (!empty($object->country_code)) {
			return $object->country_code;
		}

		return '';
	}

	/**
	 * Draw company logo.
	 *
	 * @param	TCPDF		$pdf			PDF object
	 * @param	Product		$object			Product object
	 * @param	Translate	$outputlangs	Output language
	 * @param	float|int	$x				X
	 * @param	float|int	$y				Y
	 * @param	float|int	$w				Max width
	 * @param	float|int	$h				Max height
	 * @return	void
	 */
	private function drawCompanyLogo(&$pdf, $object, $outputlangs, $x, $y, $w, $h)
	{
		global $conf;

		if (getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO')) {
			return;
		}

		if (empty($this->emetteur->logo)) {
			$pdf->SetTextColor(0, 58, 79);
			$pdf->SetFont('', 'B', pdf_getPDFFontSize($outputlangs));
			$pdf->SetXY($x, $y);
			$pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
			return;
		}

		$logodir = $conf->mycompany->dir_output;
		if (!empty($conf->mycompany->multidir_output[$object->entity])) {
			$logodir = $conf->mycompany->multidir_output[$object->entity];
		}

		if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') && !empty($this->emetteur->logo_small)) {
			$logo = $logodir.'/logos/thumbs/'.$this->emetteur->logo_small;
		} else {
			$logo = $logodir.'/logos/'.$this->emetteur->logo;
		}

		if (is_readable($logo)) {
			$size = $this->fitImageSize($logo, $w, $h);
			$pdf->Image($logo, $x, $y, $size['width'], $size['height'], '', '', '', 2, 300);
			return;
		}

		$pdf->SetTextColor(200, 0, 0);
		$pdf->SetFont('', '', pdf_getPDFFontSize($outputlangs) - 3);
		$pdf->SetXY($x, $y);
		$pdf->MultiCell(80, 3, $outputlangs->transnoentities('ErrorLogoFileNotFound', $logo), 0, 'L');
	}

	/**
	 * Render secondary images in a left column.
	 *
	 * @param	TCPDF										$pdf		PDF object
	 * @param	array<int,array{photo:string,thumb:string}>	$images		Images
	 * @param	float|int									$x			X
	 * @param	float|int									$y			Y
	 * @param	float|int									$w			Width
	 * @param	float|int									$h			Height
	 * @param	float|int									$gap		Gap
	 * @return	void
	 */
	private function renderVisualColumn(&$pdf, $images, $x, $y, $w, $h, $gap)
	{
		$max = min(5, max(0, count($images) - 1));
		for ($i = 0; $i < $max; $i++) {
			$img = $images[$i + 1];
			$curY = $y + ($i * ($h + $gap));
			$this->drawRoundedRect($pdf, $x, $curY, $w, $h, 2, 'DF', array('color' => array(223, 226, 229)), array(255, 255, 255));

			$size = $this->fitImageSize($img['thumb'], $w - 3, $h - 3);
			$pdf->Image($img['thumb'], $x + (($w - $size['width']) / 2), $curY + (($h - $size['height']) / 2), $size['width'], $size['height'], '', '', '', 2, 300);
		}
	}

	/**
	 * Render description in successive zones so text can flow around the main image.
	 *
	 * @param	TCPDF		$pdf					PDF object
	 * @param	string		$description			Product description
	 * @param	Translate	$outputlangs			Output language
	 * @param	int			$default_font_size		Default font size
	 * @param	float|int	$x						Title X
	 * @param	float|int	$y						Title Y
	 * @param	array		$zones					Text zones
	 * @param	array		$dark					Dark color
	 * @return	void
	 */
	private function renderDescriptionFlowBlock(&$pdf, $description, $outputlangs, $default_font_size, $x, $y, $zones, $dark)
	{
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', 'B', $default_font_size + 6);
		$pdf->SetXY($x, $y);
		$pdf->MultiCell(84, 8, $outputlangs->transnoentities('Description'), 0, 'L', false, 1, '', '', true, 0, false, true, 18, 'T', true);

		$description = $this->plainTextForPdf($description);
		if ($description === '') {
			$description = $outputlangs->transnoentities('JpsunProductSheetNoDescription');
		}

		$pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
		$pdf->SetFont('', '', $default_font_size - 1);
		$this->renderFlowingPlainText($pdf, $description, $outputlangs, $zones, 4.4, true);
	}

	/**
	 * Render public notes between product copy and feature tables.
	 *
	 * @param	TCPDF		$pdf					PDF object
	 * @param	string		$notes					Public notes
	 * @param	Translate	$outputlangs			Output language
	 * @param	int			$default_font_size		Default font size
	 * @param	float|int	$x						X
	 * @param	float|int	$y						Y
	 * @param	float|int	$w						Width
	 * @param	float|int	$h						Height
	 * @param	array		$dark					Dark color
	 * @param	array		$light					Light fill color
	 * @return	void
	 */
	private function renderPublicNotesBlock(&$pdf, $notes, $outputlangs, $default_font_size, $x, $y, $w, $h, $dark, $light)
	{
		if ($notes === '' || $h <= 8) {
			return;
		}

		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', 'B', $default_font_size + 1);
		$pdf->SetXY($x, $y);
		$pdf->MultiCell($w, 5, $this->transNativeOrCustom($outputlangs, 'Notes', 'JpsunProductSheetPublicNotes'), 0, 'L', false, 1);

		$bodyY = $y + 7;
		$bodyH = $h - 7;
		$pdf->SetFillColor($light[0], $light[1], $light[2]);
		$pdf->Rect($x, $bodyY, $w, $bodyH, 'F');
		$pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
		$pdf->SetFont('', '', $default_font_size - 2);
		$this->renderFlowingPlainText(
			$pdf,
			$notes,
			$outputlangs,
			array(array('x' => $x + 2, 'y' => $bodyY + 1, 'w' => $w - 4, 'h' => $bodyH - 2)),
			4.1,
			true
		);
	}

	/**
	 * Render plain text across a list of bounded zones.
	 *
	 * @param	TCPDF		$pdf					PDF object
	 * @param	string		$text					Text to render
	 * @param	Translate	$outputlangs			Output language
	 * @param	array		$zones					Text zones
	 * @param	float|int	$lineHeight				Line height
	 * @param	bool		$showOverflowNotice	Show an overflow notice in the last zone
	 * @return	void
	 */
	private function renderFlowingPlainText(&$pdf, $text, $outputlangs, $zones, $lineHeight, $showOverflowNotice)
	{
		$remaining = trim((string) $text);
		$zoneCount = count($zones);
		foreach ($zones as $index => $zone) {
			if ($remaining === '') {
				break;
			}
			if (empty($zone['w']) || empty($zone['h']) || $zone['w'] <= 0 || $zone['h'] <= 0) {
				continue;
			}

			$split = $this->splitTextForBox($pdf, $remaining, $zone['w'], $zone['h'], $lineHeight);
			$textToPrint = $split['text'];
			$remaining = $split['remaining'];

			if ($index === $zoneCount - 1 && $showOverflowNotice && $remaining !== '') {
				$textToPrint = $this->appendOverflowNotice($textToPrint, $outputlangs->transnoentities('JpsunProductSheetMoreFields'), $this->maxLinesForHeight($zone['h'], $lineHeight));
				$remaining = '';
			}

			if ($textToPrint === '') {
				continue;
			}

			$pdf->SetXY($zone['x'], $zone['y']);
			$pdf->MultiCell($zone['w'], $lineHeight, $outputlangs->convToOutputCharset($textToPrint), 0, 'L', false, 1, '', '', true, 0, false, true, $zone['h'], 'T', false);
		}
	}

	/**
	 * Split text into the part that fits a box and the remaining text.
	 *
	 * @param	TCPDF		$pdf			PDF object
	 * @param	string		$text			Text to split
	 * @param	float|int	$w				Box width
	 * @param	float|int	$h				Box height
	 * @param	float|int	$lineHeight		Line height
	 * @return	array{text:string,remaining:string}
	 */
	private function splitTextForBox(&$pdf, $text, $w, $h, $lineHeight)
	{
		$maxLines = $this->maxLinesForHeight($h, $lineHeight);
		$text = trim((string) $text);
		if ($maxLines <= 0 || $text === '') {
			return array('text' => '', 'remaining' => $text);
		}

		$paragraphs = preg_split("/\r\n|\r|\n/", $text);
		$lines = array();
		$paragraphCount = is_array($paragraphs) ? count($paragraphs) : 0;
		if ($paragraphCount === 0) {
			return array('text' => '', 'remaining' => '');
		}

		for ($paragraphIndex = 0; $paragraphIndex < $paragraphCount; $paragraphIndex++) {
			$paragraph = trim($paragraphs[$paragraphIndex]);
			if ($paragraph === '') {
				if (count($lines) >= $maxLines) {
					return array('text' => rtrim(implode("\n", $lines)), 'remaining' => $this->buildRemainingText($paragraphs, $paragraphIndex, ''));
				}
				$lines[] = '';
				continue;
			}

			$words = preg_split('/\s+/', $paragraph, -1, PREG_SPLIT_NO_EMPTY);
			$line = '';
			foreach ($words as $wordIndex => $word) {
				$candidate = ($line === '') ? $word : $line.' '.$word;
				if ($line === '' || $this->getTextWidth($pdf, $candidate) <= $w) {
					$line = $candidate;
					continue;
				}

				if (count($lines) >= $maxLines) {
					$remaining = trim($line.' '.implode(' ', array_slice($words, $wordIndex)));
					return array('text' => rtrim(implode("\n", $lines)), 'remaining' => $this->buildRemainingText($paragraphs, $paragraphIndex, $remaining));
				}

				$lines[] = $line;
				$line = $word;
			}

			if ($line !== '') {
				if (count($lines) >= $maxLines) {
					return array('text' => rtrim(implode("\n", $lines)), 'remaining' => $this->buildRemainingText($paragraphs, $paragraphIndex, $line));
				}
				$lines[] = $line;
			}

			if ($paragraphIndex < $paragraphCount - 1) {
				if (count($lines) >= $maxLines) {
					return array('text' => rtrim(implode("\n", $lines)), 'remaining' => $this->buildRemainingText($paragraphs, $paragraphIndex, ''));
				}
				$lines[] = '';
			}
		}

		return array('text' => rtrim(implode("\n", $lines)), 'remaining' => '');
	}

	/**
	 * Build remaining text from the current paragraph and the following ones.
	 *
	 * @param	array		$paragraphs			Paragraphs
	 * @param	int			$paragraphIndex		Current paragraph index
	 * @param	string		$currentText		Current paragraph remaining text
	 * @return	string
	 */
	private function buildRemainingText($paragraphs, $paragraphIndex, $currentText)
	{
		$remaining = array();
		if (trim($currentText) !== '') {
			$remaining[] = trim($currentText);
		}

		for ($index = $paragraphIndex + 1; $index < count($paragraphs); $index++) {
			if (trim($paragraphs[$index]) !== '') {
				$remaining[] = trim($paragraphs[$index]);
			}
		}

		return trim(implode("\n", $remaining));
	}

	/**
	 * Append or replace the last visible line with the overflow notice.
	 *
	 * @param	string		$text		Visible text
	 * @param	string		$notice		Overflow notice
	 * @param	int			$maxLines	Maximum number of lines
	 * @return	string
	 */
	private function appendOverflowNotice($text, $notice, $maxLines)
	{
		if ($maxLines <= 0) {
			return '';
		}

		$lines = ($text === '') ? array() : explode("\n", $text);
		if (count($lines) >= $maxLines) {
			$lines = array_slice($lines, 0, $maxLines);
			$lines[$maxLines - 1] = $notice;
			return implode("\n", $lines);
		}

		$lines[] = $notice;
		return implode("\n", $lines);
	}

	/**
	 * Return the number of text lines available in a fixed height.
	 *
	 * @param	float|int	$h				Height
	 * @param	float|int	$lineHeight		Line height
	 * @return	int
	 */
	private function maxLinesForHeight($h, $lineHeight)
	{
		if ($h <= 0 || $lineHeight <= 0) {
			return 0;
		}

		return (int) floor($h / $lineHeight);
	}

	/**
	 * Return text width with a conservative fallback.
	 *
	 * @param	TCPDF		$pdf	PDF object
	 * @param	string		$text	Text
	 * @return	float|int
	 */
	private function getTextWidth(&$pdf, $text)
	{
		if (method_exists($pdf, 'GetStringWidth')) {
			return $pdf->GetStringWidth($text);
		}
		if (method_exists($pdf, 'getStringWidth')) {
			return $pdf->getStringWidth($text);
		}

		return strlen($text) * 1.8;
	}

	/**
	 * Use a Dolibarr native label when available, with a module fallback.
	 *
	 * @param	Translate	$outputlangs	Output language
	 * @param	string		$nativeKey		Native translation key
	 * @param	string		$fallbackKey	Module fallback key
	 * @return	string
	 */
	private function transNativeOrCustom($outputlangs, $nativeKey, $fallbackKey)
	{
		$label = $outputlangs->transnoentities($nativeKey);
		if ($label === '' || $label === $nativeKey) {
			$label = $outputlangs->transnoentities($fallbackKey);
		}

		return $label;
	}

	/**
	 * Render the main product image.
	 *
	 * @param	TCPDF										$pdf					PDF object
	 * @param	array<int,array{photo:string,thumb:string}>	$images					Images
	 * @param	Translate									$outputlangs			Output language
	 * @param	int											$default_font_size		Default font size
	 * @param	float|int									$x						X
	 * @param	float|int									$y						Y
	 * @param	float|int									$w						Width
	 * @param	float|int									$h						Height
	 * @param	array										$soft					Soft fill color
	 * @param	array										$muted					Muted text color
	 * @return	void
	 */
	private function renderMainImage(&$pdf, $images, $outputlangs, $default_font_size, $x, $y, $w, $h, $soft, $muted)
	{
		$this->drawRoundedRect($pdf, $x, $y, $w, $h, 4, 'DF', array('color' => array(230, 230, 230)), array(255, 255, 255));

		if (!empty($images[0]['photo'])) {
			$size = $this->fitImageSize($images[0]['photo'], $w - 4, $h - 4, true);
			$pdf->Image($images[0]['photo'], $x + (($w - $size['width']) / 2), $y + (($h - $size['height']) / 2), $size['width'], $size['height'], '', '', '', 2, 300);
			return;
		}

		$pdf->SetFillColor($soft[0], $soft[1], $soft[2]);
		$pdf->Rect($x + 3, $y + 3, $w - 6, $h - 6, 'F');
		$pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
		$pdf->SetFont('', '', $default_font_size - 1);
		$pdf->SetXY($x + 6, $y + ($h / 2) - 5);
		$pdf->MultiCell($w - 12, 10, $outputlangs->transnoentities('JpsunProductSheetNoImage'), 0, 'C');
	}

	/**
	 * Render the standard features table.
	 *
	 * @param	TCPDF										$pdf					PDF object
	 * @param	array<int,array{label:string,value:string}>	$rows					Rows
	 * @param	Translate									$outputlangs			Output language
	 * @param	int											$default_font_size		Default font size
	 * @param	float|int									$x						X
	 * @param	float|int									$y						Y
	 * @param	float|int									$w						Width
	 * @param	float|int									$h						Height
	 * @param	array										$dark					Dark color
	 * @param	array										$light					Light color
	 * @return	void
	 */
	private function renderFeaturesTable(&$pdf, $rows, $outputlangs, $default_font_size, $x, $y, $w, $h, $dark, $light)
	{
		$col1 = 50;
		$col2 = $w - $col1;
		$currentY = $y;
		$bottomY = $y + $h;

		$pdf->SetFillColor($dark[0], $dark[1], $dark[2]);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->Rect($x, $currentY, $w, 7, 'F');
		$pdf->SetXY($x + 2, $currentY + 1.5);
		$pdf->Cell($col1 - 3, 4, $outputlangs->transnoentities('JpsunProductSheetFeatures'), 0, 0, 'L');
		$pdf->SetXY($x + $col1 + 2, $currentY + 1.5);
		$pdf->Cell($col2 - 3, 4, $outputlangs->transnoentities('Value'), 0, 0, 'L');
		$currentY += 7;

		$pdf->SetFont('', '', $default_font_size - 2);
		$pdf->SetTextColor(0, 0, 0);
		$index = 0;
		foreach ($rows as $row) {
			$label = $outputlangs->convToOutputCharset($row['label']);
			$value = $outputlangs->convToOutputCharset($row['value']);
			$heightLabel = $this->getStringHeight($pdf, $col1 - 4, $label);
			$heightValue = $this->getStringHeight($pdf, $col2 - 4, $value);
			$rowHeight = max(6, $heightLabel + 2, $heightValue + 2);

			if ($currentY + $rowHeight > $bottomY) {
				$pdf->SetXY($x, $currentY);
				$pdf->MultiCell($w, 5, $outputlangs->transnoentities('JpsunProductSheetMoreFields'), 1, 'L');
				break;
			}

			$fill = ($index % 2 === 0);
			if ($fill) {
				$pdf->SetFillColor($light[0], $light[1], $light[2]);
			} else {
				$pdf->SetFillColor(255, 255, 255);
			}

			$pdf->SetXY($x, $currentY);
			$pdf->MultiCell($col1, $rowHeight, $label, 1, 'L', $fill, 0, '', '', true, 0, false, true, $rowHeight, 'M', true);
			$pdf->SetXY($x + $col1, $currentY);
			$pdf->MultiCell($col2, $rowHeight, $value, 1, 'L', $fill, 1, '', '', true, 0, false, true, $rowHeight, 'M', true);
			$currentY += $rowHeight;
			$index++;
		}
	}

	/**
	 * Calculate the compact category table height.
	 *
	 * @param	TCPDF		$pdf					PDF object
	 * @param	string[]	$categories				Category labels
	 * @param	Translate	$outputlangs			Output language
	 * @param	int			$default_font_size		Default font size
	 * @param	float|int	$w						Width
	 * @param	float|int	$maxH					Maximum height
	 * @return	float|int
	 */
	private function getCategoriesTableHeight(&$pdf, $categories, $outputlangs, $default_font_size, $w, $maxH)
	{
		if (empty($categories) || $maxH <= 0) {
			return 0;
		}

		$height = 7;
		$pdf->SetFont('', '', $default_font_size - 2);
		foreach (array_values($categories) as $category) {
			$category = $outputlangs->convToOutputCharset((string) $category);
			$rowHeight = max(6, $this->getStringHeight($pdf, $w - 4, $category) + 2);
			if ($height + $rowHeight > $maxH) {
				return $maxH;
			}

			$height += $rowHeight;
		}

		return $height;
	}

	/**
	 * Render linked product category labels as a compact table.
	 *
	 * @param	TCPDF		$pdf					PDF object
	 * @param	string[]	$categories				Category labels
	 * @param	Translate	$outputlangs			Output language
	 * @param	int			$default_font_size		Default font size
	 * @param	float|int	$x						X
	 * @param	float|int	$y						Y
	 * @param	float|int	$w						Width
	 * @param	float|int	$h						Height
	 * @param	array		$dark					Dark color
	 * @param	array		$light					Light color
	 * @return	void
	 */
	private function renderCategoriesTable(&$pdf, $categories, $outputlangs, $default_font_size, $x, $y, $w, $h, $dark, $light)
	{
		if (empty($categories)) {
			return;
		}

		$currentY = $y;
		$bottomY = $y + $h;

		$pdf->SetFillColor($dark[0], $dark[1], $dark[2]);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->Rect($x, $currentY, $w, 7, 'F');
		$pdf->SetXY($x + 2, $currentY + 1.5);
		$pdf->Cell($w - 4, 4, $outputlangs->transnoentities('Categories'), 0, 0, 'L');
		$currentY += 7;

		$pdf->SetFont('', '', $default_font_size - 2);
		$pdf->SetTextColor(0, 0, 0);

		foreach (array_values($categories) as $index => $category) {
			$category = $outputlangs->convToOutputCharset((string) $category);
			$rowHeight = max(6, $this->getStringHeight($pdf, $w - 4, $category) + 2);

			if ($currentY + $rowHeight > $bottomY) {
				$pdf->SetXY($x, $currentY);
				$pdf->MultiCell($w, 5, $outputlangs->transnoentities('JpsunProductSheetMoreFields'), 1, 'L');
				break;
			}

			if ($index % 2 === 0) {
				$pdf->SetFillColor($light[0], $light[1], $light[2]);
			} else {
				$pdf->SetFillColor(255, 255, 255);
			}

			$pdf->SetXY($x, $currentY);
			$pdf->MultiCell($w, $rowHeight, $category, 1, 'L', true, 1, '', '', true, 0, false, true, $rowHeight, 'M', true);
			$currentY += $rowHeight;
		}
	}

	/**
	 * Fit an image into a given box while preserving ratio.
	 *
	 * @param	string		$path		Image path
	 * @param	float|int	$maxW		Max width
	 * @param	float|int	$maxH		Max height
	 * @param	bool		$allowGrow	Allow image upscaling
	 * @return	array{width:float|int,height:float|int}
	 */
	private function fitImageSize($path, $maxW, $maxH, $allowGrow = false)
	{
		$size = pdf_getSizeForImage($path);
		$width = empty($size['width']) ? $maxW : $size['width'];
		$height = empty($size['height']) ? $maxH : $size['height'];

		if ($width <= 0 || $height <= 0) {
			return array('width' => $maxW, 'height' => $maxH);
		}

		$ratio = min($maxW / $width, $maxH / $height);
		if (!$allowGrow && $ratio > 1) {
			$ratio = 1;
		}

		return array('width' => $width * $ratio, 'height' => $height * $ratio);
	}

	/**
	 * Get text height with fallback for older PDF engines.
	 *
	 * @param	TCPDF		$pdf	PDF object
	 * @param	float|int	$w		Width
	 * @param	string		$text	Text
	 * @return	float|int
	 */
	private function getStringHeight(&$pdf, $w, $text)
	{
		if (method_exists($pdf, 'getStringHeight')) {
			return $pdf->getStringHeight($w, $text, false, true, '', 1);
		}

		return 4;
	}

	/**
	 * Draw rounded rectangle when supported, otherwise a standard rectangle.
	 *
	 * @param	TCPDF		$pdf			PDF object
	 * @param	float|int	$x				X
	 * @param	float|int	$y				Y
	 * @param	float|int	$w				Width
	 * @param	float|int	$h				Height
	 * @param	float|int	$r				Radius
	 * @param	string		$style			Draw style
	 * @param	array		$borderStyle	Border style
	 * @param	array		$fillColor		Fill color
	 * @return	void
	 */
	private function drawRoundedRect(&$pdf, $x, $y, $w, $h, $r, $style, $borderStyle = array(), $fillColor = array())
	{
		if (!empty($fillColor)) {
			$pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
		}
		if (!empty($borderStyle['color'])) {
			$pdf->SetDrawColor($borderStyle['color'][0], $borderStyle['color'][1], $borderStyle['color'][2]);
		}

		if (method_exists($pdf, 'RoundedRect')) {
			$pdf->RoundedRect($x, $y, $w, $h, $r, '1111', $style, $borderStyle, $fillColor);
			return;
		}

		$pdf->Rect($x, $y, $w, $h, $style);
	}

	/**
	 * Convert simple HTML descriptions into fitting plain text.
	 *
	 * @param	string	$text	HTML or plain text
	 * @return	string
	 */
	private function plainTextForPdf($text)
	{
		$text = (string) $text;
		$text = preg_replace('/<br\s*\/?>/i', "\n", $text);
		$text = preg_replace('/<\/p\s*>/i', "\n", $text);
		$text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace("/[ \t]+/", ' ', $text);
		$text = preg_replace("/\n{3,}/", "\n\n", $text);

		return trim($text);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Show top header of page.
	 *
	 * @param	TCPDF		$pdf			PDF object
	 * @param	Product		$object			Product object
	 * @param	int			$showaddress	0=no, 1=yes
	 * @param	Translate	$outputlangs	Output language
	 * @return	int
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
	{
		// phpcs:enable
		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		return 0;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Show footer of page.
	 *
	 * @param	TCPDF		$pdf			PDF object
	 * @param	Product		$object			Product object
	 * @param	Translate	$outputlangs	Output language
	 * @param	int			$hidefreetext	1=Hide free text
	 * @return	int
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		// phpcs:enable
		$showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);
		return pdf_pagefoot($pdf, $outputlangs, 'PRODUCT_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext);
	}
}
