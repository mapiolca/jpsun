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
 *	\file       core/modules/contract/doc/pdf_jpsunpro.modules.php
 *	\ingroup    jpsun
 *	\brief      JPSUN PRO contract PDF template.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/contract/modules_contract.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once dirname(__DIR__, 4).'/lib/jpsun_powerplantpv.lib.php';

/**
 * Class to build JPSUN PRO contract documents.
 */
class pdf_jpsunpro extends ModelePDFContract
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Model name
	 */
	public $name;

	/**
	 * @var string Model description
	 */
	public $description;

	/**
	 * @var int Save generated file as main document
	 */
	public $update_main_doc_field;

	/**
	 * @var string Document type
	 */
	public $type;

	/**
	 * @var string Model version
	 */
	public $version = 'dolibarr';

	/**
	 * @var Societe
	 */
	public $emetteur;

	/**
	 * @var Societe
	 */
	public $recipient;

	/**
	 * @var int Annex 1 page in the static template.
	 */
	private $powerPlantAnnexTemplatePage = 10;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$this->db = $db;
		$this->name = 'JPSUN PRO';
		$this->description = $langs->trans('JpsunContractProModelDescription');
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
		$this->option_tva = 0;
		$this->option_modereg = 0;
		$this->option_condreg = 0;
		$this->option_multilang = 0;
		$this->option_draft_watermark = 1;

		$this->emetteur = $mysoc;
		if (is_object($this->emetteur) && empty($this->emetteur->country_code)) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2);
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Build PDF on disk.
	 *
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param string $srctemplatepath Source template path
	 * @param int $hidedetails Hide line details
	 * @param int $hidedesc Hide descriptions
	 * @param int $hideref Hide references
	 * @return int 1 if OK, 0 if KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $user, $langs, $conf, $hookmanager, $action;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		if (getDolGlobalString('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		$outputlangs->loadLangs(array('main', 'dict', 'companies', 'contracts', 'products', 'jpsun@jpsun', 'powerplantpv@powerplantpv'));

		if ($object->status == $object::STATUS_DRAFT && getDolGlobalString('CONTRACT_DRAFT_WATERMARK')) {
			$this->watermark = getDolGlobalString('CONTRACT_DRAFT_WATERMARK');
		}

		if (!jpsunIsPowerPlantPVEnabled()) {
			$this->error = $outputlangs->transnoentitiesnoconv('JpsunPowerPlantPVRequiredForContractPro');
			return 0;
		}
		if (!jpsunCanReadPowerPlantPV($user)) {
			$this->error = $outputlangs->transnoentitiesnoconv('JpsunPowerPlantPVPermissionDenied');
			return 0;
		}

		$dataset = jpsunPowerPlantPVBuildContractDataset($this->db, $object, $user);
		if ($dataset['result'] < 0) {
			$this->error = ($dataset['error'] !== '' && $outputlangs->trans($dataset['error']) !== $dataset['error']) ? $outputlangs->transnoentitiesnoconv($dataset['error']) : $dataset['error'];
			return 0;
		}
		if (empty($dataset['powerplants'])) {
			$this->error = $outputlangs->transnoentitiesnoconv('JpsunContractProNoLinkedPowerPlant');
			return 0;
		}

		$templatefile = __DIR__.'/pdf/Contrat_maintenance_PRO_V4_avec_GA.pdf';
		if (!is_readable($templatefile)) {
			$this->error = $outputlangs->transnoentitiesnoconv('JpsunContractProTemplateMissing', $templatefile);
			return 0;
		}

		if (empty($conf->contract->multidir_output[$conf->entity])) {
			$this->error = $outputlangs->transnoentitiesnoconv('ErrorConstantNotDefined', 'CONTRACT_OUTPUTDIR');
			return 0;
		}

		$object->fetch_thirdparty();
		if (method_exists($object, 'fetch_optionals')) {
			$object->fetch_optionals();
		}

		if ($object->specimen) {
			$dir = getMultidirOutput($object);
			$file = $dir.'/SPECIMEN.pdf';
		} else {
			$objectref = dol_sanitizeFileName($object->ref);
			$dir = getMultidirOutput($object).'/'.$objectref;
			$file = $dir.'/'.$objectref.'.pdf';
		}

		if (!file_exists($dir) && dol_mkdir($dir) < 0) {
			$this->error = $langs->transnoentitiesnoconv('ErrorCanNotCreateDir', $dir);
			return 0;
		}
		if (!file_exists($dir)) {
			$this->error = $langs->transnoentitiesnoconv('ErrorCanNotCreateDir', $dir);
			return 0;
		}

		if (!is_object($hookmanager)) {
			include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}
		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
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
		$pdf->Open();
		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetSubject($outputlangs->transnoentities('Contract'));
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
		$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref).' '.$outputlangs->transnoentities('Contract'));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);

		$pagecount = $pdf->setSourceFile($templatefile);
		$annexpage = min($this->powerPlantAnnexTemplatePage, $pagecount);
		$contactdata = $this->getContractContactData($object, $outputlangs, $dataset['powerplants'][0]);

		for ($page = 1; $page <= $pagecount; $page++) {
			if ($page === $annexpage) {
				$totalplants = count($dataset['powerplants']);
				$plantindex = 1;
				foreach ($dataset['powerplants'] as $powerplant) {
					$this->addTemplatePage($pdf, $page, $outputlangs, $templatefile);
					$this->renderPowerPlantAnnex($pdf, $object, $powerplant, $contactdata, $outputlangs, $plantindex, $totalplants);
					$this->_pagefoot($pdf, $object, $outputlangs);
					$plantindex++;
				}
				continue;
			}

			$this->addTemplatePage($pdf, $page, $outputlangs, $templatefile);
			$this->renderStaticPageOverlay($pdf, $object, $page, $contactdata, $outputlangs, $default_font_size);
			$this->_pagefoot($pdf, $object, $outputlangs);
		}

		$pdf->Close();
		$pdf->Output($file, 'F');
		dolChmod($file);
		$this->result = array('fullpath' => $file);

		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);
		if ($reshook < 0) {
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
			return 0;
		}

		if (getDolGlobalString('MAIN_UMASK')) {
			@chmod($file, octdec(getDolGlobalString('MAIN_UMASK')));
		}

		return 1;
	}

	/**
	 * Add one static template page.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param int $pageno Template page number
	 * @param Translate $outputlangs Output language
	 * @param string $templatefile Template path
	 * @return void
	 */
	private function addTemplatePage(&$pdf, $pageno, $outputlangs, $templatefile)
	{
		$pdf->AddPage();
		$tplidx = $pdf->importPage($pageno);
		if (!empty($tplidx)) {
			$pdf->useTemplate($tplidx);
		}
		if (method_exists($pdf, 'AliasNbPages')) {
			$pdf->AliasNbPages();
		}
	}

	/**
	 * Render overlays for static pages.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param int $page Template page number
	 * @param array<string,array<string,string>> $contactdata Contact data
	 * @param Translate $outputlangs Output language
	 * @param int $default_font_size Default font size
	 * @return void
	 */
	private function renderStaticPageOverlay(&$pdf, $object, $page, $contactdata, $outputlangs, $default_font_size)
	{
		if ($page === 1) {
			$pdf->SetFont('', 'B', $default_font_size + 3);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->writeHTMLCell(150, 4, 80, 220, $this->html($outputlangs->transnoentities('Reference').' '.$object->ref, $outputlangs), 0, 1);
			$pdf->SetTextColor(0, 0, 0);
			return;
		}

		if ($page === 2) {
			$pdf->SetFont('', '', 11);
			$client = '';
			if (is_object($object->thirdparty)) {
				$client = $object->thirdparty->name."\n".$object->thirdparty->address."\n".$object->thirdparty->zip.' '.$object->thirdparty->town;
			}
			$pdf->writeHTMLCell(150, 4, 37, 25, $this->html($client, $outputlangs), 0, 1);
			return;
		}

		if ($page === 3) {
			$this->renderContractContacts($pdf, $contactdata, $outputlangs);
		}
	}

	/**
	 * Render the contract contacts on the template.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param array<string,array<string,string>> $contactdata Contact data
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderContractContacts(&$pdf, $contactdata, $outputlangs)
	{
		$pdf->SetFont('', '', 9);
		if (!empty($contactdata['SITEADDRESS']['address'])) {
			$pdf->writeHTMLCell(120, 4, 25, 39.5, $this->html($contactdata['SITEADDRESS']['address'], $outputlangs), 0, 1);
		}
		if (!empty($contactdata['SITEADDRESS']['town'])) {
			$pdf->writeHTMLCell(80, 4, 99, 39.5, $this->html($contactdata['SITEADDRESS']['town'], $outputlangs), 0, 1);
		}
		if (!empty($contactdata['SITEADDRESS']['zip'])) {
			$pdf->writeHTMLCell(40, 4, 156.5, $this->html($contactdata['SITEADDRESS']['zip'], $outputlangs), 0, 1);
		}

		$this->renderRepresentativeLine($pdf, $contactdata['SITEREPRESANT1'], 71.6, $outputlangs);
		$this->renderRepresentativeLine($pdf, $contactdata['SITEREPRESANT2'], 76, $outputlangs);
	}

	/**
	 * Render one representative contact line.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param array<string,string> $contact Contact data
	 * @param float $y Y position
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderRepresentativeLine(&$pdf, $contact, $y, $outputlangs)
	{
		$pdf->writeHTMLCell(80, 4, 25, $y, $this->html($contact['fullname'], $outputlangs), 0, 1);
		$pdf->writeHTMLCell(80, 4, 66.5, $y, $this->html($contact['job'], $outputlangs), 0, 1);
		$pdf->writeHTMLCell(80, 4, 107, $y, $this->html($contact['phone'], $outputlangs), 0, 1);
		$pdf->writeHTMLCell(80, 4, 147, $y, $this->html($contact['email'], $outputlangs), 0, 1);
	}

	/**
	 * Render one PowerPlantPV Annex 1 page.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param array<string,mixed> $powerplant Power plant data
	 * @param array<string,array<string,string>> $contactdata Contact data
	 * @param Translate $outputlangs Output language
	 * @param int $plantindex Current plant index
	 * @param int $totalplants Total plants
	 * @return void
	 */
	private function renderPowerPlantAnnex(&$pdf, $object, $powerplant, $contactdata, $outputlangs, $plantindex, $totalplants)
	{
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', 'B', 10);
		if ($totalplants > 1) {
			$pdf->writeHTMLCell(180, 4, 10, 31, $this->html($outputlangs->trans('JpsunContractProPowerPlantAnnex', $plantindex, $totalplants), $outputlangs), 0, 1);
		}

		$pdf->SetFont('', '', 9);
		$site = $this->firstNonEmpty($powerplant['site_name'], $this->getExtraOption($object, 'jpsun_site_name'));
		$address = $this->firstNonEmpty($powerplant['full_address'], trim($contactdata['SITEADDRESS']['address']."\n".$contactdata['SITEADDRESS']['zip'].' '.$contactdata['SITEADDRESS']['town']));
		if ($address !== '') {
			$pdf->writeHTMLCell(180, 4, 10, 38, $this->html($outputlangs->trans('Address').': '.$address, $outputlangs), 0, 1);
		}

		$modulelabel = $this->firstNonEmpty($powerplant['modules_label'], $this->getProductLabel($this->getExtraOption($object, 'jpsun_pv_module_product')));
		$inverterlabel = $this->firstNonEmpty($powerplant['inverters_label'], $this->getProductLabel($this->getExtraOption($object, 'jpsun_inverter_product')));
		$moduleqty = $this->firstNonEmpty($this->formatQty($powerplant['modules_qty']), $this->getExtraOption($object, 'jpsun_pv_module_qty'));
		$inverterqty = $this->firstNonEmpty($this->formatQty($powerplant['inverters_qty']), $this->getExtraOption($object, 'jpsun_inverter_qty'));
		$dcboxesqty = $this->firstNonEmpty($this->formatQty($powerplant['dc_boxes_qty']), $this->getExtraOption($object, 'jpsun_dc_boxes_qty'));
		$acboxesqty = $this->firstNonEmpty($this->formatQty($powerplant['ac_boxes_qty']), $this->getExtraOption($object, 'jpsun_ac_boxes_qty'));
		$installedpower = $this->firstNonEmpty($this->formatNumber($powerplant['installed_power'], 2), $this->formatNumber($this->getExtraOption($object, 'jpsun_installed_power_kwc'), 2));
		$pdl = $this->firstNonEmpty($powerplant['prm_pdl_number'], $this->getExtraOption($object, 'jpsun_pdl_number'));

		$this->writeAnnexValue($pdf, 52, $site, $outputlangs);
		$this->writeAnnexValue($pdf, 57.35, $installedpower, $outputlangs);
		$this->writeAnnexValue($pdf, 62.7, $modulelabel, $outputlangs);
		$this->writeAnnexValue($pdf, 68.05, $moduleqty, $outputlangs);
		$this->writeAnnexValue($pdf, 73.4, $inverterlabel, $outputlangs);
		$this->writeAnnexValue($pdf, 78.75, $inverterqty, $outputlangs);
		$this->writeAnnexValue($pdf, 84.1, $this->formatNumber($this->getExtraOption($object, 'jpsun_inverter_install_height_m'), 2), $outputlangs);
		$this->writeAnnexValue($pdf, 89.45, $dcboxesqty, $outputlangs);
		$this->writeAnnexValue($pdf, 94.8, $this->formatNumber($this->getExtraOption($object, 'jpsun_dc_box_install_height_m'), 2), $outputlangs);
		$this->writeAnnexValue($pdf, 100.15, $acboxesqty, $outputlangs);
		$this->writeAnnexValue($pdf, 105.5, $this->formatNumber($this->getExtraOption($object, 'jpsun_ac_box_install_height_m'), 2), $outputlangs);
		$this->writeAnnexValue($pdf, 110.85, $this->getExtraOption($object, 'jpsun_access_code'), $outputlangs);
		$this->writeAnnexValue($pdf, 116.2, $pdl, $outputlangs);

		$this->renderPowerPlantDetails($pdf, $powerplant, $outputlangs);
	}

	/**
	 * Render supplementary power plant fields.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param array<string,mixed> $powerplant Power plant data
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderPowerPlantDetails(&$pdf, $powerplant, $outputlangs)
	{
		$details = array();
		if (!empty($powerplant['commissioning_date'])) {
			$details[] = $outputlangs->trans('PowerPlantCommissioningDate').': '.dol_print_date($this->db->jdate($powerplant['commissioning_date']), 'day', false, $outputlangs, true);
		}
		if (!empty($powerplant['enedis_commissioning_date'])) {
			$details[] = $outputlangs->trans('PowerPlantEnedisCommissioningDate').': '.dol_print_date($this->db->jdate($powerplant['enedis_commissioning_date']), 'day', false, $outputlangs, true);
		}
		if (!empty($powerplant['connection_type'])) {
			$details[] = $outputlangs->trans('PowerPlantConnectionType').': '.$powerplant['connection_type'];
		}
		if (!empty($powerplant['connection_contract_power'])) {
			$details[] = $outputlangs->trans('PowerPlantConnectionContractPower').': '.$this->formatNumber($powerplant['connection_contract_power'], 2);
		}
		if (!empty($powerplant['connection_request_number'])) {
			$details[] = $outputlangs->trans('PowerPlantConnectionRequestNumber').': '.$powerplant['connection_request_number'];
		}
		if (!empty($powerplant['buyback_contract_number'])) {
			$details[] = $outputlangs->trans('PowerPlantBuybackContractNumber').': '.$powerplant['buyback_contract_number'];
		}
		if (!empty($powerplant['buyback_tariff'])) {
			$details[] = $outputlangs->trans('PowerPlantBuybackTariff').': '.$this->formatNumber($powerplant['buyback_tariff'], 6);
		}

		if (empty($details)) {
			return;
		}

		$pdf->SetFont('', '', 8);
		$pdf->writeHTMLCell(185, 45, 10, 125, $this->html(implode("\n", $details), $outputlangs), 0, 1);
	}

	/**
	 * Write a value in the Annex 1 table.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param float $y Y position
	 * @param string|int|float $value Value
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function writeAnnexValue(&$pdf, $y, $value, $outputlangs)
	{
		if ((string) $value === '') {
			return;
		}
		$pdf->writeHTMLCell(100, 4, 95, $y, $this->html((string) $value, $outputlangs), 0, 1);
	}

	/**
	 * Load external contract contacts used by the template.
	 *
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param array<string,mixed> $fallbackpowerplant First linked power plant
	 * @return array<string,array<string,string>>
	 */
	private function getContractContactData($object, $outputlangs, $fallbackpowerplant)
	{
		$contactdata = array(
			'SITEADDRESS' => array('address' => '', 'zip' => '', 'town' => ''),
			'SITEREPRESANT1' => array('fullname' => '', 'job' => '', 'phone' => '', 'email' => ''),
			'SITEREPRESANT2' => array('fullname' => '', 'job' => '', 'phone' => '', 'email' => ''),
		);

		$contactlist = $object->liste_contact(-1, 'external');
		foreach ($contactlist as $contact) {
			$contactcode = '';
			if (!empty($contact['code'])) {
				$contactcode = $contact['code'];
			} elseif (!empty($contact['typecode'])) {
				$contactcode = $contact['typecode'];
			}
			if ($contactcode === '' || !isset($contactdata[$contactcode])) {
				continue;
			}

			$contactstatic = new Contact($this->db);
			if (!empty($contact['id'])) {
				$contactstatic->fetch((int) $contact['id']);
			}

			if ($contactcode === 'SITEADDRESS') {
				$contactdata[$contactcode]['address'] = !empty($contactstatic->address) ? $contactstatic->address : (isset($contact['address']) ? $contact['address'] : '');
				$contactdata[$contactcode]['zip'] = !empty($contactstatic->zip) ? $contactstatic->zip : (isset($contact['zip']) ? $contact['zip'] : '');
				$contactdata[$contactcode]['town'] = !empty($contactstatic->town) ? $contactstatic->town : (isset($contact['town']) ? $contact['town'] : '');
				continue;
			}

			$fullname = $contactstatic->getFullName($outputlangs, 1);
			if ($fullname === '') {
				$fullname = trim((isset($contact['firstname']) ? $contact['firstname'] : '').' '.(isset($contact['lastname']) ? $contact['lastname'] : ''));
			}
			$contactdata[$contactcode]['fullname'] = $fullname;
			$contactdata[$contactcode]['job'] = !empty($contactstatic->poste) ? $contactstatic->poste : (isset($contact['poste']) ? $contact['poste'] : '');
			$contactdata[$contactcode]['phone'] = !empty($contactstatic->phone_pro) ? $contactstatic->phone_pro : (isset($contact['phone']) ? $contact['phone'] : '');
			$contactdata[$contactcode]['email'] = !empty($contactstatic->email) ? $contactstatic->email : (isset($contact['email']) ? $contact['email'] : '');
		}

		if ($contactdata['SITEADDRESS']['address'] === '' && !empty($fallbackpowerplant['address'])) {
			$contactdata['SITEADDRESS']['address'] = (string) $fallbackpowerplant['address'];
			$contactdata['SITEADDRESS']['zip'] = (string) $fallbackpowerplant['zip'];
			$contactdata['SITEADDRESS']['town'] = (string) $fallbackpowerplant['town'];
		}

		return $contactdata;
	}

	/**
	 * Return an object extrafield value.
	 *
	 * @param CommonObject $object Object
	 * @param string $key Extrafield key without options_
	 * @return string
	 */
	private function getExtraOption($object, $key)
	{
		$optionkey = 'options_'.$key;
		if (!empty($object->array_options[$optionkey])) {
			return (string) $object->array_options[$optionkey];
		}

		return '';
	}

	/**
	 * Return a product ref and label from id.
	 *
	 * @param string|int $productid Product id
	 * @return string
	 */
	private function getProductLabel($productid)
	{
		$productid = (int) $productid;
		if ($productid <= 0) {
			return '';
		}

		$product = new Product($this->db);
		if ($product->fetch($productid) <= 0) {
			return '';
		}

		$label = (string) $product->ref;
		if (!empty($product->label)) {
			$label .= ' - '.$product->label;
		}

		return $label;
	}

	/**
	 * Format a numeric value.
	 *
	 * @param string|int|float $value Value
	 * @param int $precision Precision
	 * @return string
	 */
	private function formatNumber($value, $precision)
	{
		if ($value === '' || $value === null || !is_numeric($value)) {
			return '';
		}

		return rtrim(rtrim(sprintf('%.'.$precision.'F', (float) $value), '0'), '.');
	}

	/**
	 * Format a quantity, preserving empty values.
	 *
	 * @param string|int|float $value Quantity
	 * @return string
	 */
	private function formatQty($value)
	{
		if ($value === '' || $value === null || !is_numeric($value) || (float) $value == 0.0) {
			return '';
		}

		return $this->formatNumber($value, 2);
	}

	/**
	 * Return the first non-empty value.
	 *
	 * @param mixed ...$values Values
	 * @return string
	 */
	private function firstNonEmpty(...$values)
	{
		foreach ($values as $value) {
			if ((string) $value !== '') {
				return (string) $value;
			}
		}

		return '';
	}

	/**
	 * Convert plain text to PDF-safe HTML.
	 *
	 * @param string $text Text
	 * @param Translate $outputlangs Output language
	 * @return string
	 */
	private function html($text, $outputlangs)
	{
		return dol_htmlentitiesbr($outputlangs->convToOutputCharset((string) $text));
	}

	/**
	 * Show footer of page.
	 *
	 * @param TCPDF $pdf PDF object
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param int $hidefreetext Hide free text
	 * @return int
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		$showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);
		return pdf_pagefoot($pdf, $outputlangs, 'CONTRACT_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext, $this->page_largeur, $this->watermark);
	}
}
