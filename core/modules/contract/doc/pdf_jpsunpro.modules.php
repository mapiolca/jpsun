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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       core/modules/contract/doc/pdf_jpsunpro.modules.php
 *	\ingroup    jpsun
 *	\brief      Native JPSUN PRO contract PDF template.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/contract/modules_contract.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once dirname(__DIR__, 4).'/lib/jpsun_powerplantpv.lib.php';
require_once dirname(__DIR__, 4).'/lib/jpsun_pdf_attachments.lib.php';

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
	 * @var int Default PDF font size
	 */
	private $defaultFontSize = 9;

	/**
	 * @var float Content top Y position
	 */
	private $contentTop = 32;

	/**
	 * @var float Content bottom Y position
	 */
	private $contentBottom = 270;

	/**
	 * @var array<int,int>
	 */
	private $primaryColor = array(25, 58, 84);

	/**
	 * @var array<int,int>
	 */
	private $mutedColor = array(98, 107, 116);

	/**
	 * @var array<int,int>
	 */
	private $lightColor = array(243, 246, 248);

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
		$this->option_tva = 1; // Manage VAT totals.
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
		$this->defaultFontSize = pdf_getPDFFontSize($outputlangs);
		$this->contentTop = max(32, $this->marge_haute + 20);
		$this->contentBottom = $this->page_hauteur - $this->marge_basse - 12;

		$pdf->SetAutoPageBreak(false, 0);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs));
		$pdf->Open();
		$pdf->SetDrawColor(190, 198, 205);
		$pdf->SetLineWidth(0.15);
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetSubject($outputlangs->transnoentities('Contract'));
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
		$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref).' '.$outputlangs->transnoentities('Contract'));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		if (method_exists($pdf, 'AliasNbPages')) {
			$pdf->AliasNbPages();
		}

		$contactdata = $this->getContractContactData($object, $outputlangs, $dataset['powerplants'][0]);
		$this->renderNativeContract($pdf, $object, $dataset['powerplants'], $contactdata, $outputlangs);
		$nextannexnumber = $this->appendConfiguredPdfAnnexes($pdf, $object, $outputlangs, 3);
		$this->appendFichinterSpecimenAnnex($pdf, $object, $outputlangs, $nextannexnumber);

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
	 * Render all native contract pages.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param array<int,array<string,mixed>> $powerplants Linked power plants
	 * @param array<string,array<string,string>> $contactdata Contact data
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderNativeContract(&$pdf, $object, $powerplants, $contactdata, $outputlangs)
	{
		$this->addPage($pdf, $object, $outputlangs, 'Contrat de maintenance');
		$this->renderCover($pdf, $object, $powerplants, $contactdata, $outputlangs);

		$this->addPage($pdf, $object, $outputlangs, 'Conditions contractuelles');
		$this->renderPrestationScope($pdf, $object, $powerplants, $contactdata, $outputlangs);
		$this->renderFixedSections($pdf, $object, $outputlangs);
		$this->renderSignaturePage($pdf, $object, $contactdata, $outputlangs);

		$totalplants = count($powerplants);
		$plantindex = 1;
		foreach ($powerplants as $powerplant) {
			$this->addPage($pdf, $object, $outputlangs, 'Annexe 1');
			$this->renderPowerPlantAnnex($pdf, $object, $powerplant, $contactdata, $outputlangs, $plantindex, $totalplants);
			$plantindex++;
		}

		$this->addPage($pdf, $object, $outputlangs, 'Annexes');
		$this->renderServicePriceAnnex($pdf, $object, $outputlangs);
	}

	/**
	 * Add a page with native header and footer.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param string $title Page title
	 * @return void
	 */
	private function addPage(&$pdf, $object, $outputlangs, $title)
	{
		$pdf->AddPage();
		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);
		$this->renderHeader($pdf, $object, $outputlangs, $title);
		$this->_pagefoot($pdf, $object, $outputlangs);
		$pdf->SetXY($this->marge_gauche, $this->contentTop);
	}

	/**
	 * Render compact JPSUN PRO header.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param string $title Page title
	 * @return void
	 */
	private function renderHeader(&$pdf, $object, $outputlangs, $title)
	{
		global $conf;

		$default_font_size = $this->defaultFontSize;
		$pdf->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
		$pdf->SetFont('', 'B', $default_font_size + 2);

		$logoRendered = false;
		if (!getDolGlobalString('PDF_DISABLE_MYCOMPANY_LOGO') && !empty($this->emetteur->logo)) {
			$logodir = $conf->mycompany->dir_output;
			if (getMultidirOutput($object, 'mycompany')) {
				$logodir = getMultidirOutput($object, 'mycompany');
			}
			$logo = $logodir.'/logos/'.$this->emetteur->logo;
			if (!getDolGlobalString('MAIN_PDF_USE_LARGE_LOGO') && !empty($this->emetteur->logo_small)) {
				$smalllogo = $logodir.'/logos/thumbs/'.$this->emetteur->logo_small;
				if (is_readable($smalllogo)) {
					$logo = $smalllogo;
				}
			}
			if (is_readable($logo)) {
				$pdf->Image($logo, $this->marge_gauche, 10, 0, 13);
				$logoRendered = true;
			}
		}
		if (!$logoRendered) {
			$pdf->SetXY($this->marge_gauche, 11);
			$pdf->Cell(70, 6, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 0, 'L');
		}

		$pdf->SetFont('', 'B', $default_font_size + 1);
		$pdf->SetXY($this->page_largeur - $this->marge_droite - 95, 10);
		$pdf->Cell(95, 5, $outputlangs->convToOutputCharset($title), 0, 2, 'R');
		$pdf->SetFont('', '', $default_font_size - 1);
		$pdf->SetTextColor($this->mutedColor[0], $this->mutedColor[1], $this->mutedColor[2]);
		$pdf->Cell(95, 4, $outputlangs->transnoentities('Ref').' '.$outputlangs->convToOutputCharset($object->ref), 0, 2, 'R');
		if (!empty($object->date_contrat)) {
			$pdf->Cell(95, 4, $outputlangs->transnoentities('Date').' '.dol_print_date($object->date_contrat, 'day', false, $outputlangs, true), 0, 2, 'R');
		}
		if ($object->status == $object::STATUS_DRAFT) {
			$pdf->SetTextColor(150, 0, 0);
			$pdf->Cell(95, 4, $outputlangs->transnoentities('NotValidated'), 0, 2, 'R');
		}

		$pdf->SetDrawColor(211, 216, 221);
		$pdf->Line($this->marge_gauche, 28, $this->page_largeur - $this->marge_droite, 28);
		$pdf->SetTextColor(0, 0, 0);
	}

	/**
	 * Render the cover page.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param array<int,array<string,mixed>> $powerplants Linked power plants
	 * @param array<string,array<string,string>> $contactdata Contact data
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderCover(&$pdf, $object, $powerplants, $contactdata, $outputlangs)
	{
		$pdf->SetY(43);
		$pdf->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
		$pdf->SetFont('', 'B', $this->defaultFontSize + 8);
		$pdf->MultiCell($this->contentWidth(), 11, $outputlangs->convToOutputCharset('Contrat de maintenance et entretien annuel préventif'), 0, 'C');
		$pdf->SetFont('', '', $this->defaultFontSize + 1);
		$pdf->SetTextColor($this->mutedColor[0], $this->mutedColor[1], $this->mutedColor[2]);
		$pdf->MultiCell($this->contentWidth(), 6, $outputlangs->convToOutputCharset('Centrale photovoltaïque professionnelle'), 0, 'C');

		$pdf->Ln(8);
		$this->renderInfoCard($pdf, $outputlangs, 'Contrat', array(
			array('label' => $outputlangs->transnoentities('Ref'), 'value' => $object->ref),
			array('label' => $outputlangs->transnoentities('Date'), 'value' => !empty($object->date_contrat) ? dol_print_date($object->date_contrat, 'day', false, $outputlangs, true) : ''),
			array('label' => 'Nombre de centrales liées', 'value' => (string) count($powerplants)),
			array('label' => 'Premier site', 'value' => $this->firstNonEmpty($powerplants[0]['site_name'], $powerplants[0]['ref'])),
		), 88);

		$pdf->Ln(6);
		$left = array(
			array('label' => 'Le Client', 'value' => $this->formatThirdpartyBlock($object, $outputlangs)),
			array('label' => 'Interlocuteur site', 'value' => $this->formatPowerPlantContacts($powerplants[0])),
		);
		$right = array(
			array('label' => 'Le Prestataire', 'value' => $this->formatEmitterBlock($object, $outputlangs)),
			array('label' => 'Prestation', 'value' => "Maintenance préventive annuelle\nMaintenance curative selon demande\nGestion des alarmes si prévue au contrat"),
		);
		$this->renderTwoCards($pdf, $outputlangs, $left, $right);

		$pdf->Ln(6);
		$this->renderSectionTitle($pdf, $object, $outputlangs, 'Préambule');
		$this->renderParagraph($pdf, $object, $outputlangs, 'Le Client et le Prestataire sont individuellement dénommés une Partie et collectivement les Parties.');
		$this->renderParagraph($pdf, $object, $outputlangs, 'Le Client exploite la centrale photovoltaïque décrite en Annexe 1 et souhaite s’adjoindre les services d’une entreprise chargée d’en assurer la maintenance.');
		$this->renderParagraph($pdf, $object, $outputlangs, 'Le Prestataire est spécialisé dans la maintenance de centrales solaires et dispose de l’expérience et du savoir-faire nécessaires pour mener à bien ces activités.');
	}

	/**
	 * Render scope, price and site contact information.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param array<int,array<string,mixed>> $powerplants Linked power plants
	 * @param array<string,array<string,string>> $contactdata Contact data
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderPrestationScope(&$pdf, $object, $powerplants, $contactdata, $outputlangs)
	{
		$this->renderSectionTitle($pdf, $object, $outputlangs, 'Prestation');
		$this->renderParagraph($pdf, $object, $outputlangs, 'Le présent contrat fixe les modalités de maintenance préventive et, le cas échéant, de maintenance curative assurées par le Prestataire pour le compte du Client.');

		$scopeRows = array(
			array('Maintenance préventive', 'Contrôles électriques, relevés de tensions à vide, contrôles de résistances d’isolement, connexions CC/CA et vérification des protections.'),
			array('Nettoyage', 'Local, onduleurs, coffrets et panneaux photovoltaïques lorsque cette option est prévue.'),
			array('Inspection', 'Vérification visuelle des panneaux, des locaux techniques et des affichages réglementaires.'),
			array('Administratif', 'Rédaction d’un rapport de maintenance à l’issue de la visite annuelle.'),
			array('Maintenance curative', 'Intervention sur appel du Client selon les modalités et tarifs prévus au présent contrat.'),
		);
		$this->renderKeyValueTable($pdf, $object, $outputlangs, $scopeRows, array(50, $this->contentWidth() - 50));

		$this->renderSectionTitle($pdf, $object, $outputlangs, 'Montant de la prestation');
		$this->renderContractAmountTable($pdf, $object, $outputlangs);

		$this->renderSectionTitle($pdf, $object, $outputlangs, 'Site et interlocuteurs');
		$this->renderPowerPlantSiteTables($pdf, $object, $powerplants, $outputlangs);
	}

	/**
	 * Render one site/contact table per linked PowerPlantPV power plant.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param array<int,array<string,mixed>> $powerplants Linked power plants
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderPowerPlantSiteTables(&$pdf, $object, $powerplants, $outputlangs)
	{
		$index = 1;
		foreach ($powerplants as $powerplant) {
			$site = $this->firstNonEmpty($powerplant['site_name'], $powerplant['ref']);
			$this->renderSubTitle($pdf, $object, $outputlangs, 'Site '.$index.' : '.$site);

			$technical = isset($powerplant['technical_contact']) && is_array($powerplant['technical_contact']) ? $powerplant['technical_contact'] : array();
			$administrative = isset($powerplant['administrative_contact']) && is_array($powerplant['administrative_contact']) ? $powerplant['administrative_contact'] : array();
			$siteRows = array(
				array('Adresse du site', isset($powerplant['full_address']) ? (string) $powerplant['full_address'] : ''),
				array('Représentant 1 - Contact technique client', $this->formatContactLine($technical)),
				array('Représentant 2 - Contact administratif client', $this->formatContactLine($administrative)),
			);
			$this->renderKeyValueTable($pdf, $object, $outputlangs, $siteRows, array(66, $this->contentWidth() - 66));
			$index++;
		}
	}

	/**
	 * Render fixed contractual sections.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderFixedSections(&$pdf, $object, $outputlangs)
	{
		foreach ($this->getContractSections() as $section) {
			$this->renderSectionTitle($pdf, $object, $outputlangs, $section['title']);
			foreach ($section['paragraphs'] as $paragraph) {
				if (is_array($paragraph)) {
					$this->renderBulletList($pdf, $object, $outputlangs, $paragraph);
				} else {
					$this->renderParagraph($pdf, $object, $outputlangs, $paragraph);
				}
			}
		}
	}

	/**
	 * Render signatures on a dedicated page when needed.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param array<string,array<string,string>> $contactdata Contact data
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderSignaturePage(&$pdf, $object, $contactdata, $outputlangs)
	{
		$this->ensureSpace($pdf, $object, $outputlangs, 68, 'Signatures');
		$this->renderSectionTitle($pdf, $object, $outputlangs, 'Signatures');
		$this->renderParagraph($pdf, $object, $outputlangs, 'Fait en deux exemplaires originaux. Le Client reconnaît avoir pris connaissance du présent contrat et de ses annexes.');
		$pdf->Ln(4);

		$startY = $pdf->GetY();
		$w = ($this->contentWidth() - 8) / 2;
		$providername = $this->formatSignatureContactBlock($contactdata['SALESSIGNATORY'], $this->emetteur->name);
		$clientname = $this->formatSignatureContactBlock($contactdata['CLIENTSIGNATORY'], is_object($object->thirdparty) ? $object->thirdparty->name : '');
		$this->renderSignatureBox($pdf, $outputlangs, $this->marge_gauche, $startY, $w, 'Pour le Prestataire', $providername);
		$this->renderSignatureBox($pdf, $outputlangs, $this->marge_gauche + $w + 8, $startY, $w, 'Pour le Client', $clientname);
		$pdf->SetY($startY + 54);
	}

	/**
	 * Render one PowerPlantPV Annex 1.
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
		$title = 'Annexe 1 : Description centrale photovoltaïque';
		if ($totalplants > 1) {
			$title .= ' - '.$plantindex.'/'.$totalplants;
		}
		$this->renderMainTitle($pdf, $outputlangs, $title);

		$site = $this->firstNonEmpty($powerplant['site_name'], $this->getExtraOption($object, 'jpsun_site_name'));
		$address = isset($powerplant['full_address']) ? (string) $powerplant['full_address'] : '';
		$moduleqty = $this->formatQty($powerplant['modules_qty']);
		$inverterqty = $this->formatQty($powerplant['inverters_qty']);
		$dcboxesqty = $this->formatQty($powerplant['dc_boxes_qty']);
		$acboxesqty = $this->formatQty($powerplant['ac_boxes_qty']);
		$installedpower = $this->formatNumber($powerplant['installed_power'], 2);
		$pdl = isset($powerplant['prm_pdl_number']) ? (string) $powerplant['prm_pdl_number'] : '';

		$siteRows = array(
			array('Référence centrale', $powerplant['ref']),
			array('Nom du site', $site),
			array('Adresse', $address),
			array('N° PDL / PRM', $pdl),
			array('Puissance installée (kWc)', $installedpower),
			array('Date de mise en service', $this->formatDate($powerplant['commissioning_date'], $outputlangs)),
			array('Date MES ENEDIS', $this->formatDate($powerplant['enedis_commissioning_date'], $outputlangs)),
			array('Type de raccordement', $powerplant['connection_type']),
			array('Puissance de raccordement', $this->formatNumber($powerplant['connection_contract_power'], 2)),
			array('N° demande de raccordement', $powerplant['connection_request_number']),
			array('N° contrat d’achat', $powerplant['buyback_contract_number']),
			array('Tarif d’achat', $this->formatNumber($powerplant['buyback_tariff'], 6)),
		);
		$this->renderSectionTitle($pdf, $object, $outputlangs, 'Informations site');
		$this->renderKeyValueTable($pdf, $object, $outputlangs, $siteRows, array(58, $this->contentWidth() - 58));
		$this->renderPowerPlantImage($pdf, $object, $powerplant, $outputlangs);

		$this->renderSectionTitle($pdf, $object, $outputlangs, 'Matériel');
		$equipmentRows = array(
			array('Nombre de modules PV', $moduleqty),
			array('Nombre d’onduleurs', $inverterqty),
			array('Nombre coffrets DC', $dcboxesqty),
			array('Nombre coffrets AC', $acboxesqty),
		);
		$inverterheight = $this->formatNumber($this->firstNonEmpty($powerplant['inverter_install_height_m'] ?? '', $this->getExtraOption($object, 'jpsun_inverter_install_height_m')), 2);
		$dcboxheight = $this->formatNumber($this->firstNonEmpty($powerplant['dc_box_install_height_m'] ?? '', $this->getExtraOption($object, 'jpsun_dc_box_install_height_m')), 2);
		$acboxheight = $this->formatNumber($this->firstNonEmpty($powerplant['ac_box_install_height_m'] ?? '', $this->getExtraOption($object, 'jpsun_ac_box_install_height_m')), 2);
		if ($inverterheight !== '') {
			$equipmentRows[] = array('Hauteur installation onduleurs (m)', $inverterheight);
		}
		if ($dcboxheight !== '') {
			$equipmentRows[] = array('Hauteur installation coffret DC (m)', $dcboxheight);
		}
		if ($acboxheight !== '') {
			$equipmentRows[] = array('Hauteur installation coffret AC (m)', $acboxheight);
		}
		if ($this->getExtraOption($object, 'jpsun_access_code') !== '') {
			$equipmentRows[] = array('Codes d’accès', $this->getExtraOption($object, 'jpsun_access_code'));
		}
		$this->renderKeyValueTable($pdf, $object, $outputlangs, $equipmentRows, array(68, $this->contentWidth() - 68));

		if (!empty($powerplant['modules_rows'])) {
			$this->renderSubTitle($pdf, $object, $outputlangs, 'Modules PV');
			$this->renderSimpleTable($pdf, $object, $outputlangs, array('Modules PV', 'Puissance', 'Nombre'), $this->getModuleMaterialRows($powerplant['modules_rows']), array($this->contentWidth() - 66, 40, 26), array(2));
		}

		if (!empty($powerplant['inverters_rows'])) {
			$this->renderSubTitle($pdf, $object, $outputlangs, 'Onduleurs');
			$this->renderSimpleTable($pdf, $object, $outputlangs, array('Onduleurs', 'Puissance', 'Nombre'), $this->getInverterMaterialRows($powerplant['inverters_rows']), array($this->contentWidth() - 70, 44, 26), array(2));
		}

		if (!empty($powerplant['components'])) {
			$this->renderSectionTitle($pdf, $object, $outputlangs, 'Composants liés à la centrale');
			$componentRows = array();
			foreach ($powerplant['components'] as $component) {
				$componentRows[] = array(
					$this->firstNonEmpty($component['category_label'], $component['category_code']),
					$this->firstNonEmpty(trim((string) $component['product_ref'].' - '.(string) $component['product_label']), (string) $component['fk_product']),
					$this->formatQty($component['qty']),
					$component['serial_number'],
				);
			}
			$this->renderSimpleTable($pdf, $object, $outputlangs, array('Catégorie', 'Produit', 'Qté', 'N° série'), $componentRows, array(38, 88, 18, $this->contentWidth() - 144), array(2));
		}
	}

	/**
	 * Render the first image attached to a PowerPlantPV power plant.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param array<string,mixed> $powerplant Power plant data
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderPowerPlantImage(&$pdf, $object, $powerplant, $outputlangs)
	{
		$image = isset($powerplant['first_image']) ? (string) $powerplant['first_image'] : '';
		if ($image === '' || !is_readable($image)) {
			return;
		}

		$maxW = min(110, $this->contentWidth());
		$maxH = 58;
		$imgW = $maxW;
		$imgH = 45;
		$size = @getimagesize($image);
		if (is_array($size) && !empty($size[0]) && !empty($size[1])) {
			$ratio = min($maxW / (float) $size[0], $maxH / (float) $size[1]);
			$imgW = (float) $size[0] * $ratio;
			$imgH = (float) $size[1] * $ratio;
		}

		$blockH = $imgH + 14;
		$this->ensureSpace($pdf, $object, $outputlangs, $blockH + 4, 'Annexe 1');
		$this->renderSubTitle($pdf, $object, $outputlangs, 'Photo du site');

		$x = $this->marge_gauche;
		$y = $pdf->GetY();
		$this->drawRect($pdf, $x, $y, $imgW + 8, $imgH + 8, array(255, 255, 255), array(210, 216, 222));
		$pdf->Image($image, $x + 4, $y + 4, $imgW, $imgH);
		$pdf->SetY($y + $imgH + 10);
	}

	/**
	 * Return material table rows for PV modules.
	 *
	 * @param array<int,array<string,mixed>> $modules Component groups
	 * @return array<int,array<int,string>>
	 */
	private function getModuleMaterialRows($modules)
	{
		$rows = array();
		foreach ($modules as $module) {
			$rows[] = array(
				(string) $module['label'],
				$this->formatPowerWithUnit($module['pmax'], 'Wc', 2),
				$this->formatQty($module['qty']),
			);
		}

		return $rows;
	}

	/**
	 * Return material table rows for inverters.
	 *
	 * @param array<int,array<string,mixed>> $inverters Component groups
	 * @return array<int,array<int,string>>
	 */
	private function getInverterMaterialRows($inverters)
	{
		$rows = array();
		foreach ($inverters as $inverter) {
			$power = array();
			if (!empty($inverter['ac_nominal_power'])) {
				$power[] = 'Nom. '.$this->formatPowerWithUnit($inverter['ac_nominal_power'], 'kVA', 2);
			}
			if (!empty($inverter['ac_max_power'])) {
				$power[] = 'Max. '.$this->formatPowerWithUnit($inverter['ac_max_power'], 'kVA', 2);
			}
			$rows[] = array(
				(string) $inverter['label'],
				implode("\n", $power),
				$this->formatQty($inverter['qty']),
			);
		}

		return $rows;
	}

	/**
	 * Render Annex 2.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderServicePriceAnnex(&$pdf, $object, $outputlangs)
	{
		$this->renderMainTitle($pdf, $outputlangs, 'Annexe 2 : Prix des prestations hors forfait');
		$this->renderParagraph($pdf, $object, $outputlangs, 'Les tarifs horaires sont révisables chaque année. Le Client en sera informé le 15 du mois précédant la date anniversaire.');
		$this->renderParagraph($pdf, $object, $outputlangs, 'Ce tarif est applicable pour toute demande d’intervention de maintenance curative comme expliqué dans l’article 2.');
		$rows = array(
			array('Tarif horaire en jours et heures ouvrés (8h00 à 16h00)', '50.00 € HT / heure'),
			array('Forfait déplacement > 50 km et < 100 km aller-retour', '100 € HT'),
			array('Forfait déplacement > 100 km aller-retour', '200 € HT'),
			array('Forfait déplacement > 200 km aller-retour', 'Sur devis'),
			array('Nettoyage panneaux', 'Sur devis'),
		);
		$this->renderSimpleTable($pdf, $object, $outputlangs, array('Tarifs', 'Prix'), $rows, array($this->contentWidth() - 48, 48));
	}

	/**
	 * Append configured PDF attachments as numbered annexes.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param int $startnumber First annex number
	 * @return int Next annex number
	 */
	private function appendConfiguredPdfAnnexes(&$pdf, $object, $outputlangs, $startnumber)
	{
		$annexnumber = (int) $startnumber;
		foreach (jpsunPdfAttachmentFilesForTarget('contract', $object->entity) as $item) {
			$title = $outputlangs->transnoentities((string) $item['label']);
			if ($title === (string) $item['label']) {
				$title = (string) $item['label'];
			}

			$result = $this->appendPdfFileAnnex($pdf, $object, $outputlangs, $annexnumber, $title, (string) $item['path'], 'JpsunPdfAttachmentAppendError');
			if ($result > 0) {
				$annexnumber++;
			}
		}

		return $annexnumber;
	}

	/**
	 * Append the native intervention specimen as a numbered annex.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param int $annexnumber Annex number
	 * @return int Next annex number
	 */
	private function appendFichinterSpecimenAnnex(&$pdf, $object, $outputlangs, $annexnumber)
	{
		$errorcode = 0;
		$path = jpsunPdfFichinterSpecimenPath($this->db, $outputlangs, $errorcode, $object);
		if ($path === '') {
			return $annexnumber;
		}

		$title = 'Fiche d’intervention';
		$result = $this->appendPdfFileAnnex($pdf, $object, $outputlangs, $annexnumber, $title, $path, 'JpsunPdfAttachmentFichinterAppendError');
		if ($result > 0) {
			$annexnumber++;
		}

		return $annexnumber;
	}

	/**
	 * Append one external PDF as a numbered annex.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param int $annexnumber Annex number
	 * @param string $title Annex title
	 * @param string $path PDF path
	 * @param string $warningkey Warning translation key
	 * @return int Page count if OK, <0 if KO
	 */
	private function appendPdfFileAnnex(&$pdf, $object, $outputlangs, $annexnumber, $title, $path, $warningkey)
	{
		if (!$this->canAppendPdfFile($pdf, $path, $title)) {
			jpsunPdfAttachmentWarn($warningkey, $title);
			return -1;
		}

		$this->renderPdfAnnexReferencePage($pdf, $object, $outputlangs, $annexnumber, $title);
		$result = jpsunPdfAppendPdfFileAsPages($pdf, $path, $title);
		if ($result < 0) {
			jpsunPdfAttachmentWarn($warningkey, $title);
		}

		return $result;
	}

	/**
	 * Return whether a PDF can be imported without creating an empty annex page.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param string $path PDF path
	 * @param string $label Log label
	 * @return bool
	 */
	private function canAppendPdfFile(&$pdf, $path, $label)
	{
		if ($path === '' || !is_readable($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
			return false;
		}
		if (!method_exists($pdf, 'setSourceFile') || !method_exists($pdf, 'importPage')) {
			return false;
		}

		try {
			return ((int) $pdf->setSourceFile($path) > 0);
		} catch (Exception $e) {
			dol_syslog('JPSUN PDF attachment '.$label.' cannot be preflighted: '.$e->getMessage(), LOG_WARNING);
			return false;
		}
	}

	/**
	 * Render the reference page that introduces an appended PDF annex.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param int $annexnumber Annex number
	 * @param string $title Annex title
	 * @return void
	 */
	private function renderPdfAnnexReferencePage(&$pdf, $object, $outputlangs, $annexnumber, $title)
	{
		$this->addPage($pdf, $object, $outputlangs, 'Annexe '.$annexnumber);
		$this->renderMainTitle($pdf, $outputlangs, 'Annexe '.$annexnumber.' : '.$title);
		$this->renderParagraph($pdf, $object, $outputlangs, 'Le document PDF joint à cette annexe est intégré aux pages suivantes.');
	}

	/**
	 * Render placeholder annex headings for attached documents.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderPlaceholderAnnexes(&$pdf, $object, $outputlangs)
	{
		$this->ensureSpace($pdf, $object, $outputlangs, 50, 'Annexes');
		$this->renderSectionTitle($pdf, $object, $outputlangs, 'Annexe 3 : Certificat d’assurance');
		$this->renderParagraph($pdf, $object, $outputlangs, 'Le certificat d’assurance du Prestataire est joint au dossier contractuel lorsque le document est disponible.');
		$this->renderSectionTitle($pdf, $object, $outputlangs, 'Annexe 4 : Fiche d’intervention');
		$this->renderParagraph($pdf, $object, $outputlangs, 'La fiche d’intervention est établie et signée à chaque intervention réalisée sur site.');
	}

	/**
	 * Return fixed contract sections.
	 *
	 * @return array<int,array{title:string,paragraphs:array<int,string|array<int,string>>}>
	 */
	private function getContractSections()
	{
		return array(
			array(
				'title' => 'Article 1 : Définition de la maintenance préventive',
				'paragraphs' => array(
					'Le Prestataire s’engage à maintenir le matériel désigné en Annexe 1 en bon fonctionnement en effectuant les opérations d’entretien préventif appropriées au cours de la visite annuelle listées dans la Prestation.',
					'Au cours d’une intervention d’entretien préventif, tous les défauts seront identifiés et, le cas échéant, éliminés. Les pièces de rechange nécessaires à l’intervention sont à la seule charge du Client.',
					'Les frais de main-d’œuvre, déplacement et séjour sont à la charge du Prestataire pour l’intervention d’entretien préventif.',
					'L’entretien préventif comprend une visite annuelle. Le remplacement des éléments consommables pour un montant inférieur ou égal à 50 € HT est inclus.',
					'La date de visite est fixée d’un commun accord entre le Client et le Prestataire. Les visites sont effectuées pendant les jours ouvrables, de 8h00 à 16h00.',
					'Le Prestataire garantit que ses équipes disposent des habilitations nécessaires pour intervenir sur l’installation.',
					'A chaque intervention réalisée sur site, une fiche d’intervention devra être signée et datée par l’interlocuteur. Suite à la visite annuelle, un rapport d’intervention est fourni sous 15 jours.',
				),
			),
			array(
				'title' => 'Article 1.2 : Limites de la maintenance préventive',
				'paragraphs' => array(
					'Les modifications demandées par le Client ne rentrant pas dans le cadre du contrat feront l’objet d’un devis.',
					'Le Prestataire ne sera pas tenu d’assurer la remise en état du matériel au titre du présent contrat pour les dommages causés par ou du fait de :',
					array(
						'évènements d’origine externe : chute, choc, corps étrangers, fumées, liquide ou gaz ;',
						'vol, tentative de vol, vandalisme, maladresse, malveillance ou négligence ;',
						'inondation, incendie, explosion, foudre, tremblement de terre ou évènement naturel ;',
						'grève, émeute, attentat, guerre, action des pouvoirs publics ou cas assimilé ;',
						'déménagement, déplacement, démontage ou remontage de l’équipement ;',
						'non-respect des règles d’utilisation décrites dans la documentation ;',
						'dommages d’ordre esthétique, panne d’un équipement non couvert ou mauvaise prise de terre ;',
						'défaut constaté issu du gestionnaire réseau.',
					),
					'Dans les cas ci-dessus, les réparations, remises en état et autres prestations réalisables pourront être effectuées par le Prestataire à titre onéreux après accord préalable écrit.',
				),
			),
			array(
				'title' => 'Article 2 : Définition de la maintenance curative',
				'paragraphs' => array(
					'Le Prestataire s’engage, en cas d’incident survenant sur l’équipement et selon la nature de cet incident, à fournir par téléphone toute indication permettant la remise en service hors intervention d’ordre électrique.',
					'En cas de besoin, le Prestataire pourra expédier le matériel susceptible de permettre la remise en état de fonctionnement ou d’assurer une continuité de production.',
					'Si nécessaire, un technicien du service après-vente pourra intervenir chez le Client. Cette intervention donnera lieu à facturation selon les tarifs applicables en Annexe 2.',
					'Toute intervention demandée et acceptée en dehors des plages horaires contractuelles donnera lieu à une facturation complémentaire.',
					'L’intervention se fait sur appel du Client entre 8h00 et 16h00 pendant les jours ouvrables. Le délai garanti est de 48 heures ouvrées après réception de l’appel.',
					'Toute intervention nécessitant du matériel ou des consommables pour un montant strictement supérieur à 50 € HT nécessite l’édition d’un devis et sa validation par le Client.',
				),
			),
			array(
				'title' => 'Article 3 : Pièces, matériel de rechange et déchets',
				'paragraphs' => array(
					'Les pièces ou matériels défectueux remplacés dans le cadre du contrat deviennent propriété du Prestataire.',
					'Le Prestataire s’assure de la bonne gestion de ces déchets en les envoyant dans les circuits adéquats de traitement et recyclage.',
					'Les pièces ou matériels installés en remplacement deviennent la propriété du Client.',
				),
			),
			array(
				'title' => 'Article 4 : Gestion des alarmes',
				'paragraphs' => array(
					'Le Prestataire prend en charge la gestion des alarmes relatives à l’installation photovoltaïque objet du présent contrat lorsque cette prestation est prévue.',
					array(
						'réception et suivi des alarmes émises par le système de supervision ;',
						'analyse et diagnostic des anomalies ;',
						'information du Client sur les incidents et mesures correctives proposées ;',
						'mise en œuvre des interventions appropriées dans un délai maximum de 48 heures ouvrées suivant la réception de l’alarme.',
					),
					'Le Client s’engage à fournir les accès aux systèmes de supervision et aux outils de contrôle nécessaires. Tout défaut ou retard dans la transmission de ces accès dégage le Prestataire de toute responsabilité quant aux délais ou conséquences liées à la non-gestion des alarmes.',
				),
			),
			array(
				'title' => 'Article 5 : Responsabilités',
				'paragraphs' => array(
					'Les obligations du Prestataire sont limitées aux prestations définies au présent contrat. Le Prestataire ne peut être tenu responsable du rendement de l’installation.',
					'L’intervention des techniciens du Prestataire n’a pas pour effet de transférer à la société la garde et la complète responsabilité de l’exploitation des matériels du Client.',
					'Le Client renonce à demander toute indemnité supérieure à une année de redevance fondée sur tous dommages directs ou indirects découlant du présent contrat et de ses suites.',
					'Le Prestataire est libéré de toute responsabilité si l’exécution du contrat est retardée ou empêchée, en tout ou partie, en raison de conflits sociaux, cas fortuits ou force majeure.',
					'Tout manquement du Client dans l’exécution de ses obligations peut entraîner la résiliation immédiate et de plein droit du contrat, sous réserve de tous dommages et intérêts au profit du Prestataire.',
				),
			),
			array(
				'title' => 'Article 6 : Facturation',
				'paragraphs' => array(
					'La redevance s’entend hors taxes. Les taxes appliquées sont celles en vigueur au moment de la facturation.',
					'La redevance est payable par chèque ou virement en une seule fois, lors de la première intervention puis à chacune des interventions subséquentes.',
					'Les interventions, prestations et fournitures n’entrant pas dans le cadre du contrat font l’objet d’une facturation séparée.',
					'A défaut de paiement d’une cotisation dans les 30 jours de son échéance, le Prestataire peut suspendre la garantie et résilier le contrat.',
				),
			),
			array(
				'title' => 'Article 7 : Renouvellement du contrat et résiliation',
				'paragraphs' => array(
					'Le présent contrat est renouvelable par tacite reconduction.',
					'Il pourra être résilié par l’une quelconque des Parties au plus tard avant le 15 du mois précédant la date anniversaire, par lettre recommandée avec accusé de réception.',
					'En cas de cession du matériel à un tiers, liquidation de biens ou redressement judiciaire, le Prestataire se réserve le droit de résilier immédiatement le contrat.',
					'La revalorisation du contrat intervient à la date anniversaire selon la formule Pn = Po x Sn / So, où Po est le prix initial, So l’indice initial ICHTrev-TS et Sn l’indice de l’année n.',
					'Par défaut de communication écrite préalable ou simultanée à la facturation de la revalorisation, le prix initial s’applique.',
					'En cas de modification des conditions générales, le Client dispose d’un délai de 15 jours pour les refuser après information. Toute absence de réponse écrite est considérée comme un accord.',
				),
			),
			array(
				'title' => 'Article 8 : Contrat',
				'paragraphs' => array(
					'Le présent contrat annule et remplace tous les accords antérieurs entre les Parties ayant le même objet.',
					'Toute renonciation ou modification à l’une de ces dispositions ne peut prendre effet qu’après accord écrit entre les Parties.',
					'L’exécution du contrat est soumise au droit français. Tout litige relatif à son interprétation ou son exécution sera porté devant le Tribunal de Commerce du siège social du Prestataire, sauf accord amiable préalable.',
				),
			),
		);
	}

	/**
	 * Render the main title of a page.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Translate $outputlangs Output language
	 * @param string $title Title
	 * @return void
	 */
	private function renderMainTitle(&$pdf, $outputlangs, $title)
	{
		$pdf->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
		$pdf->SetFont('', 'B', $this->defaultFontSize + 5);
		$pdf->MultiCell($this->contentWidth(), 9, $outputlangs->convToOutputCharset($title), 0, 'L');
		$pdf->SetTextColor(0, 0, 0);
		$pdf->Ln(4);
	}

	/**
	 * Render a section title.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param string $title Title
	 * @return void
	 */
	private function renderSectionTitle(&$pdf, $object, $outputlangs, $title)
	{
		$this->ensureSpace($pdf, $object, $outputlangs, 14, $title);
		$pdf->Ln(2);
		$pdf->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
		$pdf->SetFont('', 'B', $this->defaultFontSize + 2);
		$pdf->MultiCell($this->contentWidth(), 6, $outputlangs->convToOutputCharset($title), 0, 'L');
		$pdf->SetDrawColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
		$pdf->Line($this->marge_gauche, $pdf->GetY(), $this->page_largeur - $this->marge_droite, $pdf->GetY());
		$pdf->Ln(3);
		$pdf->SetTextColor(0, 0, 0);
	}

	/**
	 * Render a compact subsection title.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param string $title Title
	 * @return void
	 */
	private function renderSubTitle(&$pdf, $object, $outputlangs, $title)
	{
		$this->ensureSpace($pdf, $object, $outputlangs, 10, $title);
		$pdf->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
		$pdf->SetFont('', 'B', $this->defaultFontSize);
		$pdf->MultiCell($this->contentWidth(), 5, $outputlangs->convToOutputCharset($title), 0, 'L');
		$pdf->SetTextColor(0, 0, 0);
	}

	/**
	 * Render a paragraph.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param string $text Paragraph
	 * @return void
	 */
	private function renderParagraph(&$pdf, $object, $outputlangs, $text)
	{
		$w = $this->contentWidth();
		$text = $outputlangs->convToOutputCharset($text);
		$height = max(5, $this->getTextHeight($pdf, $w, $text) + 1);
		$this->ensureSpace($pdf, $object, $outputlangs, $height + 2, 'Conditions contractuelles');
		$pdf->SetFont('', '', $this->defaultFontSize);
		$pdf->SetTextColor(35, 35, 35);
		$pdf->MultiCell($w, 5, $text, 0, 'L', false, 1);
		$pdf->Ln(1);
	}

	/**
	 * Render bullet list.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param array<int,string> $items Items
	 * @return void
	 */
	private function renderBulletList(&$pdf, $object, $outputlangs, $items)
	{
		$bulletW = 6;
		$textW = $this->contentWidth() - $bulletW;
		foreach ($items as $item) {
			$text = $outputlangs->convToOutputCharset($item);
			$height = max(5, $this->getTextHeight($pdf, $textW, $text) + 1);
			$this->ensureSpace($pdf, $object, $outputlangs, $height + 1, 'Conditions contractuelles');
			$x = $this->marge_gauche;
			$y = $pdf->GetY();
			$pdf->SetFont('', '', $this->defaultFontSize);
			$pdf->SetXY($x, $y);
			$pdf->Cell($bulletW, 5, '-', 0, 0, 'L');
			$pdf->SetXY($x + $bulletW, $y);
			$pdf->MultiCell($textW, 5, $text, 0, 'L', false, 1);
		}
		$pdf->Ln(1);
	}

	/**
	 * Render an information card.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Translate $outputlangs Output language
	 * @param string $title Title
	 * @param array<int,array{label:string,value:string}> $rows Rows
	 * @param float $height Height
	 * @return void
	 */
	private function renderInfoCard(&$pdf, $outputlangs, $title, $rows, $height)
	{
		$x = $this->marge_gauche;
		$y = $pdf->GetY();
		$w = $this->contentWidth();
		$this->drawRect($pdf, $x, $y, $w, $height, $this->lightColor, array(210, 216, 222));
		$pdf->SetXY($x + 4, $y + 4);
		$pdf->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
		$pdf->SetFont('', 'B', $this->defaultFontSize + 2);
		$pdf->Cell($w - 8, 6, $outputlangs->convToOutputCharset($title), 0, 2, 'L');
		$pdf->SetFont('', '', $this->defaultFontSize);
		$pdf->SetTextColor(0, 0, 0);
		foreach ($rows as $row) {
			$pdf->SetFont('', 'B', $this->defaultFontSize);
			$pdf->Cell(48, 5, $outputlangs->convToOutputCharset($row['label']), 0, 0, 'L');
			$pdf->SetFont('', '', $this->defaultFontSize);
			$pdf->MultiCell($w - 58, 5, $outputlangs->convToOutputCharset($row['value']), 0, 'L');
			$pdf->SetX($x + 4);
		}
		$pdf->SetY($y + $height);
	}

	/**
	 * Render two side-by-side cards.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Translate $outputlangs Output language
	 * @param array<int,array{label:string,value:string}> $left Left rows
	 * @param array<int,array{label:string,value:string}> $right Right rows
	 * @return void
	 */
	private function renderTwoCards(&$pdf, $outputlangs, $left, $right)
	{
		$gap = 6;
		$w = ($this->contentWidth() - $gap) / 2;
		$y = $pdf->GetY();
		$h = max(58, $this->getSmallCardHeight($pdf, $left, $w), $this->getSmallCardHeight($pdf, $right, $w));
		$this->renderSmallCard($pdf, $outputlangs, $this->marge_gauche, $y, $w, $h, $left);
		$this->renderSmallCard($pdf, $outputlangs, $this->marge_gauche + $w + $gap, $y, $w, $h, $right);
		$pdf->SetY($y + $h);
	}

	/**
	 * Measure a small card height from its rows.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param array<int,array{label:string,value:string}> $rows Rows
	 * @param float $w Width
	 * @return float
	 */
	private function getSmallCardHeight(&$pdf, $rows, $w)
	{
		$height = 6;
		foreach ($rows as $row) {
			$pdf->SetFont('', 'B', $this->defaultFontSize);
			$height += max(4, $this->getTextHeight($pdf, $w - 6, (string) $row['label']));
			$pdf->SetFont('', '', $this->defaultFontSize - 1);
			$height += max(4, $this->getTextHeight($pdf, $w - 6, (string) $row['value'])) + 1;
		}

		return $height + 3;
	}

	/**
	 * Render a small card.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Translate $outputlangs Output language
	 * @param float $x X
	 * @param float $y Y
	 * @param float $w Width
	 * @param float $h Height
	 * @param array<int,array{label:string,value:string}> $rows Rows
	 * @return void
	 */
	private function renderSmallCard(&$pdf, $outputlangs, $x, $y, $w, $h, $rows)
	{
		$this->drawRect($pdf, $x, $y, $w, $h, array(255, 255, 255), array(210, 216, 222));
		$innerX = $x + 3;
		$innerW = $w - 6;
		$pdf->SetXY($innerX, $y + 3);
		foreach ($rows as $row) {
			$pdf->SetFont('', 'B', $this->defaultFontSize);
			$pdf->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
			$pdf->SetX($innerX);
			$pdf->MultiCell($innerW, 4, $outputlangs->convToOutputCharset($row['label']), 0, 'L');
			$pdf->SetFont('', '', $this->defaultFontSize - 1);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetX($innerX);
			$pdf->MultiCell($innerW, 4, $outputlangs->convToOutputCharset($row['value']), 0, 'L');
			$pdf->Ln(1);
		}
	}

	/**
	 * Render a key-value table.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param array<int,array{0:string,1:string}> $rows Rows
	 * @param array{0:float,1:float} $widths Widths
	 * @return void
	 */
	private function renderKeyValueTable(&$pdf, $object, $outputlangs, $rows, $widths)
	{
		$index = 0;
		foreach ($rows as $row) {
			$label = $outputlangs->convToOutputCharset((string) $row[0]);
			$value = $outputlangs->convToOutputCharset((string) $row[1]);
			if (trim((string) $row[1]) === '') {
				$value = '-';
			}
			$height = max(7, $this->getTextHeight($pdf, $widths[1] - 4, $value) + 3);
			$this->ensureSpace($pdf, $object, $outputlangs, $height, 'Tableau');
			$fill = ($index % 2 === 0);
			$this->renderTableCell($pdf, $widths[0], $height, $label, true, $fill, 'L');
			$this->renderTableCell($pdf, $widths[1], $height, $value, false, $fill, 'L', true);
			$index++;
		}
		$pdf->Ln(2);
	}

	/**
	 * Render a simple table with optional repeated header.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param array<int,string> $headers Headers
	 * @param array<int,array<int,string>> $rows Rows
	 * @param array<int,float> $widths Column widths
	 * @param int[]|null $rightAlignedColumns Columns to right-align
	 * @return void
	 */
	private function renderSimpleTable(&$pdf, $object, $outputlangs, $headers, $rows, $widths, $rightAlignedColumns = null)
	{
		if ($rightAlignedColumns === null) {
			$rightAlignedColumns = array(count($headers) - 1);
		}
		$rightAlignedColumns = array_flip($rightAlignedColumns);

		$this->ensureSpace($pdf, $object, $outputlangs, 14, 'Tableau');
		$this->renderTableHeader($pdf, $outputlangs, $headers, $widths, array_keys($rightAlignedColumns));
		foreach ($rows as $row) {
			$height = 7;
			foreach ($row as $index => $value) {
				$height = max($height, $this->getTextHeight($pdf, $widths[$index] - 4, $outputlangs->convToOutputCharset((string) $value)) + 3);
			}
			if ($pdf->GetY() + $height > $this->contentBottom) {
				$this->addPage($pdf, $object, $outputlangs, 'Tableau');
				$this->renderTableHeader($pdf, $outputlangs, $headers, $widths, array_keys($rightAlignedColumns));
			}
			$last = count($row) - 1;
			foreach ($row as $index => $value) {
				$this->renderTableCell($pdf, $widths[$index], $height, $outputlangs->convToOutputCharset((string) $value), false, false, (isset($rightAlignedColumns[$index]) ? 'R' : 'L'), $index === $last);
			}
		}
		$pdf->Ln(2);
	}

	/**
	 * Render table header.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Translate $outputlangs Output language
	 * @param array<int,string> $headers Headers
	 * @param array<int,float> $widths Widths
	 * @param int[] $rightAlignedColumns Columns to right-align
	 * @return void
	 */
	private function renderTableHeader(&$pdf, $outputlangs, $headers, $widths, $rightAlignedColumns = array())
	{
		$height = 7;
		$rightAlignedColumns = array_flip($rightAlignedColumns);
		$last = count($headers) - 1;
		foreach ($headers as $index => $header) {
			$this->renderTableCell($pdf, $widths[$index], $height, $outputlangs->convToOutputCharset($header), true, true, (isset($rightAlignedColumns[$index]) ? 'R' : 'L'), $index === $last, true);
		}
	}

	/**
	 * Render a table cell.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param float $w Width
	 * @param float $h Height
	 * @param string $text Text
	 * @param bool $bold Bold
	 * @param bool $fill Fill
	 * @param string $align Align
	 * @param bool $ln Move to next line
	 * @param bool $darkHeader Dark header
	 * @return void
	 */
	private function renderTableCell(&$pdf, $w, $h, $text, $bold = false, $fill = false, $align = 'L', $ln = false, $darkHeader = false)
	{
		if ($darkHeader) {
			$pdf->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
			$pdf->SetTextColor(255, 255, 255);
		} elseif ($fill) {
			$pdf->SetFillColor($this->lightColor[0], $this->lightColor[1], $this->lightColor[2]);
			$pdf->SetTextColor(0, 0, 0);
		} else {
			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetTextColor(0, 0, 0);
		}
		$pdf->SetFont('', $bold ? 'B' : '', $this->defaultFontSize - 1);
		$pdf->MultiCell($w, $h, $text, 1, $align, $fill || $darkHeader, $ln ? 1 : 0, '', '', true, 0, false, true, $h, $darkHeader ? 'M' : 'T', false);
		$pdf->SetTextColor(0, 0, 0);
	}

	/**
	 * Render the contract amount table with HTML-capable line descriptions.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderContractAmountTable(&$pdf, $object, $outputlangs)
	{
		$headers = array('Désignation', 'Montant HT');
		$widths = array($this->contentWidth() - 42, 42);
		$rows = $this->getContractAmountRows($object, $outputlangs);

		$this->ensureSpace($pdf, $object, $outputlangs, 14, 'Tableau');
		$this->renderTableHeader($pdf, $outputlangs, $headers, $widths, array(1));

		foreach ($rows as $row) {
			$rowType = isset($row['type']) ? $row['type'] : 'line';
			$height = $this->getContractAmountRowHeight($pdf, $row, $widths);

			if ($pdf->GetY() + $height > $this->contentBottom) {
				$this->addPage($pdf, $object, $outputlangs, 'Tableau');
				$this->renderTableHeader($pdf, $outputlangs, $headers, $widths, array(1));
			}

			$x = $this->marge_gauche;
			$y = $pdf->GetY();
			$bold = ($rowType === 'total');

			if ($rowType === 'line') {
				$this->renderContractAmountHtmlCell($pdf, $x, $y, $widths[0], $height, isset($row['html']) ? $row['html'] : '', $outputlangs);
			} else {
				$pdf->SetXY($x, $y);
				$this->renderTableCell($pdf, $widths[0], $height, $outputlangs->convToOutputCharset((string) $row['label']), $bold, false, 'L', false);
			}

			$pdf->SetXY($x + $widths[0], $y);
			$this->renderTableCell($pdf, $widths[1], $height, $outputlangs->convToOutputCharset((string) $row['amount']), $bold, false, 'R', true);
		}

		$pdf->Ln(2);
	}

	/**
	 * Return height for one amount table row.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param array<string,string> $row Row data
	 * @param array<int,float> $widths Column widths
	 * @return float
	 */
	private function getContractAmountRowHeight(&$pdf, $row, $widths)
	{
		$height = 7;
		if ((isset($row['type']) ? $row['type'] : 'line') === 'line') {
			$height = max($height, $this->getHtmlCellHeight($pdf, isset($row['html']) ? $row['html'] : '', $widths[0] - 4, $this->marge_gauche + 2, $pdf->GetY() + 1.5, 4) + 3);
		} else {
			$height = max($height, $this->getTextHeight($pdf, $widths[0] - 4, isset($row['label']) ? (string) $row['label'] : '') + 3);
		}
		$height = max($height, $this->getTextHeight($pdf, $widths[1] - 4, isset($row['amount']) ? (string) $row['amount'] : '') + 3);

		return $height;
	}

	/**
	 * Render one HTML-enabled designation cell.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param float $x X
	 * @param float $y Y
	 * @param float $w Width
	 * @param float $h Height
	 * @param string $html Prepared HTML
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderContractAmountHtmlCell(&$pdf, $x, $y, $w, $h, $html, $outputlangs)
	{
		$pdf->SetFillColor(255, 255, 255);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetDrawColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
		$pdf->SetFont('', '', $this->defaultFontSize - 1);
		$pdf->Rect($x, $y, $w, $h, 'D');

		if (method_exists($pdf, 'writeHTMLCell')) {
			$pdf->writeHTMLCell($w - 4, $h - 3, $x + 2, $y + 1.5, $outputlangs->convToOutputCharset($html), 0, 0, false, true, 'L', true);
		} else {
			$text = $outputlangs->convToOutputCharset($this->normalizeTableText($html));
			$pdf->SetXY($x + 2, $y + 1.5);
			$pdf->MultiCell($w - 4, 4, $text, 0, 'L', false, 0, '', '', true, 0, false, true, $h - 3, 'T', false);
		}

		$pdf->SetTextColor(0, 0, 0);
	}

	/**
	 * Measure an HTML cell height with a plain-text fallback.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param string $html Prepared HTML
	 * @param float $w Width
	 * @param float $x X
	 * @param float $y Y
	 * @param float $lineHeight Fallback line height
	 * @return float
	 */
	private function getHtmlCellHeight(&$pdf, $html, $w, $x, $y, $lineHeight)
	{
		$html = trim((string) $html);
		if ($html === '' || $w <= 0) {
			return $lineHeight;
		}

		if (method_exists($pdf, 'writeHTMLCell') && method_exists($pdf, 'startTransaction') && method_exists($pdf, 'rollbackTransaction')) {
			$pageBefore = method_exists($pdf, 'getPage') ? $pdf->getPage() : 0;
			$xBefore = $pdf->GetX();
			$yBefore = $pdf->GetY();

			$pdf->startTransaction();
			$pdf->SetXY($x, $y);
			$pdf->writeHTMLCell($w, 0, $x, $y, $html, 0, 1, false, true, 'L', true);
			$pageAfter = method_exists($pdf, 'getPage') ? $pdf->getPage() : $pageBefore;
			$endY = $pdf->GetY();
			$pdf->rollbackTransaction(true);
			if ($pageBefore > 0 && method_exists($pdf, 'setPage')) {
				$pdf->setPage($pageBefore);
			}
			$pdf->SetXY($xBefore, $yBefore);

			if ($pageBefore > 0 && $pageAfter > $pageBefore) {
				return max($lineHeight, $this->contentBottom - $y);
			}

			return max($lineHeight, $endY - $y);
		}

		return max($lineHeight, $this->getTextHeight($pdf, $w, $this->normalizeTableText($html)));
	}

	/**
	 * Ensure enough vertical space.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @param float $needed Needed height
	 * @param string $title New page title
	 * @return void
	 */
	private function ensureSpace(&$pdf, $object, $outputlangs, $needed, $title)
	{
		if ($pdf->GetY() + $needed > $this->contentBottom) {
			$this->addPage($pdf, $object, $outputlangs, $title);
		}
	}

	/**
	 * Draw rectangle with optional rounded corners.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param float $x X
	 * @param float $y Y
	 * @param float $w Width
	 * @param float $h Height
	 * @param array<int,int> $fill Fill color
	 * @param array<int,int> $border Border color
	 * @return void
	 */
	private function drawRect(&$pdf, $x, $y, $w, $h, $fill, $border)
	{
		$pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
		$pdf->SetDrawColor($border[0], $border[1], $border[2]);
		if (method_exists($pdf, 'RoundedRect')) {
			$pdf->RoundedRect($x, $y, $w, $h, 2, '1111', 'DF');
		} else {
			$pdf->Rect($x, $y, $w, $h, 'DF');
		}
	}

	/**
	 * Render a signature box.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Translate $outputlangs Output language
	 * @param float $x X
	 * @param float $y Y
	 * @param float $w Width
	 * @param string $title Title
	 * @param string $name Name
	 * @return void
	 */
	private function renderSignatureBox(&$pdf, $outputlangs, $x, $y, $w, $title, $name)
	{
		$innerX = $x + 4;
		$innerW = $w - 8;
		$this->drawRect($pdf, $x, $y, $w, 50, array(255, 255, 255), array(180, 188, 195));
		$pdf->SetXY($innerX, $y + 4);
		$pdf->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
		$pdf->SetFont('', 'B', $this->defaultFontSize);
		$pdf->MultiCell($innerW, 5, $outputlangs->convToOutputCharset($title), 0, 'L');
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $this->defaultFontSize - 1);
		$pdf->SetX($innerX);
		$pdf->MultiCell($innerW, 4, $outputlangs->convToOutputCharset($name), 0, 'L');
		$pdf->SetXY($innerX, $y + 34);
		$pdf->MultiCell($innerW, 5, $outputlangs->convToOutputCharset('Lu et approuvé, date et signature'), 0, 'L');
	}

	/**
	 * Return rows for the contract amount table.
	 *
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @return array<int,array<string,string>>
	 */
	private function getContractAmountRows($object, $outputlangs)
	{
		global $conf;

		$rows = array();
		if (!empty($object->lines) && is_array($object->lines)) {
			foreach ($object->lines as $line) {
				$label = $this->getContractLineRawLabel($line);
				$amount = '';
				if (isset($line->total_ht) && is_numeric($line->total_ht)) {
					$amount = price($line->total_ht, 0, $outputlangs, 1, -1, -1, $conf->currency);
				}
				if ($label !== '') {
					$rows[] = array(
						'type' => 'line',
						'html' => $this->prepareContractLineDescriptionHtml($label),
						'amount' => $amount,
					);
				}
			}
		}

		if (empty($rows)) {
			$rows[] = array('type' => 'line', 'html' => $this->prepareContractLineDescriptionHtml('Contrat maintenance annuelle'), 'amount' => '');
			$rows[] = array('type' => 'line', 'html' => $this->prepareContractLineDescriptionHtml('Gestion des alarmes'), 'amount' => '');
		}
		if (isset($object->total_ht) && is_numeric($object->total_ht) && (float) $object->total_ht != 0.0) {
			$rows[] = array('type' => 'total', 'label' => 'Total HT', 'amount' => price($object->total_ht, 0, $outputlangs, 1, -1, -1, $conf->currency));
		}
		foreach ($this->getContractVatRows($object) as $vatRow) {
			$rate = isset($vatRow['rate']) ? (string) $vatRow['rate'] : '';
			$reverseChargeMarker = (strpos($rate, '*') !== false) ? '*' : '';
			$rateForDisplay = str_replace('*', '', $rate);
			$label = ($rateForDisplay === '') ? 'TVA' : 'TVA '.vatrate($rateForDisplay, true).$reverseChargeMarker;
			if (!empty($vatRow['vatcode'])) {
				$label .= ' ('.$vatRow['vatcode'].')';
			}
			$rows[] = array('type' => 'vat', 'label' => $label, 'amount' => price($vatRow['amount'], 0, $outputlangs, 1, -1, -1, $conf->currency));
		}
		if (isset($object->total_ttc) && is_numeric($object->total_ttc) && (float) $object->total_ttc != 0.0) {
			$rows[] = array('type' => 'total', 'label' => 'Total TTC', 'amount' => price($object->total_ttc, 0, $outputlangs, 1, -1, -1, $conf->currency));
		}

		return $rows;
	}

	/**
	 * Return a raw label/description for one contract line.
	 *
	 * @param CommonObjectLine $line Contract line
	 * @return string
	 */
	private function getContractLineRawLabel($line)
	{
		foreach (array('desc', 'libelle', 'label', 'ref') as $property) {
			if (!empty($line->{$property})) {
				return trim((string) $line->{$property});
			}
		}

		return '';
	}

	/**
	 * Return VAT totals grouped by rate and VAT code.
	 *
	 * @param Contrat $object Contract object
	 * @return array<int,array<string,mixed>>
	 */
	private function getContractVatRows($object)
	{
		$vatRows = array();
		if (!empty($object->lines) && is_array($object->lines)) {
			foreach ($object->lines as $line) {
				$vatAmount = 0;
				if (isset($line->total_tva) && is_numeric($line->total_tva)) {
					$vatAmount = (float) price2num($line->total_tva, 'MT');
				} elseif (isset($line->total_ht, $line->tva_tx) && is_numeric($line->total_ht) && is_numeric($line->tva_tx)) {
					$vatAmount = (float) price2num(((float) $line->total_ht * (float) $line->tva_tx) / 100, 'MT');
				}

				if (abs($vatAmount) < 0.00001) {
					continue;
				}

				$rate = isset($line->tva_tx) && is_numeric($line->tva_tx) ? (string) price2num($line->tva_tx, 'MU') : '';
				if (isset($line->info_bits) && (($line->info_bits & 0x01) == 0x01)) {
					$rate .= '*';
				}
				$vatcode = empty($line->vat_src_code) ? '' : (string) $line->vat_src_code;
				$key = $rate.'|'.$vatcode;
				if (!isset($vatRows[$key])) {
					$vatRows[$key] = array(
						'rate' => $rate,
						'vatcode' => $vatcode,
						'amount' => 0,
					);
				}
				$vatRows[$key]['amount'] += $vatAmount;
			}
		}

		if (empty($vatRows) && isset($object->total_tva) && is_numeric($object->total_tva) && abs((float) $object->total_tva) >= 0.00001) {
			$vatRows[] = array('rate' => '', 'vatcode' => '', 'amount' => (float) price2num($object->total_tva, 'MT'));
		}

		return array_values($vatRows);
	}

	/**
	 * Prepare a line description for TCPDF HTML rendering.
	 *
	 * @param string $text Raw description
	 * @return string
	 */
	private function prepareContractLineDescriptionHtml($text)
	{
		$text = (string) $text;
		$text = preg_replace('#<\s*(script|style)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $text);
		$html = dol_htmlentitiesbr($text, 1);
		$html = str_replace(array('&nbsp;', "\xc2\xa0"), ' ', $html);
		$html = preg_replace('/<\s*p[^>]*>\s*/i', '', $html);
		$html = preg_replace('/<\/\s*p\s*>/i', '<br>', $html);
		$html = preg_replace('/<\s*div[^>]*>\s*/i', '', $html);
		$html = preg_replace('/<\/\s*div\s*>/i', '<br>', $html);
		$html = preg_replace('/<\s*li[^>]*>\s*/i', '<br>&bull; ', $html);
		$html = preg_replace('/<\/\s*li\s*>/i', '', $html);
		$html = preg_replace('/<\s*\/?\s*(ul|ol)[^>]*>/i', '', $html);
		$html = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br><br>', $html);
		$html = preg_replace('/^(<br\s*\/?>\s*)+/i', '', $html);

		return trim((string) $html);
	}

	/**
	 * Normalize table text while preserving useful line breaks and bullets.
	 *
	 * @param string $text Raw text
	 * @return string
	 */
	private function normalizeTableText($text)
	{
		$text = (string) $text;
		$text = preg_replace('#<\s*(script|style)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $text);
		$text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text);
		$text = preg_replace('/<\s*\/?\s*(ul|ol)[^>]*>/i', "\n", $text);
		$text = preg_replace('/<\s*li[^>]*>/i', "\n- ", $text);
		$text = preg_replace('/<\/\s*(p|div|li|tr|h[1-6])\s*>/i', "\n", $text);
		$text = preg_replace('/<\s*(p|div|tr|h[1-6])[^>]*>/i', '', $text);
		$text = strip_tags($text);
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = str_replace("\xc2\xa0", ' ', $text);
		$text = str_replace(array("\r\n", "\r"), "\n", $text);
		$text = preg_replace('/[ \t]+/', ' ', $text);
		$text = preg_replace('/[ \t]*\n[ \t]*/', "\n", $text);
		$text = preg_replace('/^[ \t]*(?:[\x{2022}\x{00B7}\x{25CF}]|[-*])\s*/mu', '- ', $text);
		$text = preg_replace('/\n{3,}/', "\n\n", $text);

		return trim((string) $text);
	}

	/**
	 * Format thirdparty block.
	 *
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @return string
	 */
	private function formatThirdpartyBlock($object, $outputlangs)
	{
		if (!is_object($object->thirdparty)) {
			return '';
		}

		$lines = array($object->thirdparty->name);
		if (!empty($object->thirdparty->address)) {
			$lines[] = $object->thirdparty->address;
		}
		$city = trim((string) $object->thirdparty->zip.' '.(string) $object->thirdparty->town);
		if ($city !== '') {
			$lines[] = $city;
		}
		if (!empty($object->thirdparty->idprof1)) {
			$lines[] = 'SIREN : '.$object->thirdparty->idprof1;
		}

		return implode("\n", $lines);
	}

	/**
	 * Format emitter block.
	 *
	 * @param Contrat $object Contract object
	 * @param Translate $outputlangs Output language
	 * @return string
	 */
	private function formatEmitterBlock($object, $outputlangs)
	{
		$lines = array($this->emetteur->name);
		if (!empty($this->emetteur->address)) {
			$lines[] = $this->emetteur->address;
		}
		$city = trim((string) $this->emetteur->zip.' '.(string) $this->emetteur->town);
		if ($city !== '') {
			$lines[] = $city;
		}
		if (!empty($this->emetteur->idprof1)) {
			$lines[] = 'SIREN : '.$this->emetteur->idprof1;
		}

		return implode("\n", $lines);
	}

	/**
	 * Format site contacts.
	 *
	 * @param array<string,array<string,string>> $contactdata Contact data
	 * @return string
	 */
	private function formatSiteContacts($contactdata)
	{
		$lines = array();
		foreach (array('SITEREPRESANT1', 'SITEREPRESANT2') as $code) {
			$line = $this->formatContactLine($contactdata[$code]);
			if ($line !== '') {
				$lines[] = $line;
			}
		}

		return implode("\n", $lines);
	}

	/**
	 * Format technical and administrative contacts for one power plant.
	 *
	 * @param array<string,mixed> $powerplant Power plant data
	 * @return string
	 */
	private function formatPowerPlantContacts($powerplant)
	{
		$lines = array();
		$technical = isset($powerplant['technical_contact']) && is_array($powerplant['technical_contact']) ? $powerplant['technical_contact'] : array();
		$administrative = isset($powerplant['administrative_contact']) && is_array($powerplant['administrative_contact']) ? $powerplant['administrative_contact'] : array();

		$line = $this->formatContactLine($technical);
		if ($line !== '') {
			$lines[] = 'Technique : '.$line;
		}
		$line = $this->formatContactLine($administrative);
		if ($line !== '') {
			$lines[] = 'Administratif : '.$line;
		}

		return implode("\n", $lines);
	}

	/**
	 * Format one contact line.
	 *
	 * @param array<string,string> $contact Contact
	 * @return string
	 */
	private function formatContactLine($contact)
	{
		$parts = array();
		foreach (array('fullname', 'job', 'phone', 'email') as $key) {
			if (!empty($contact[$key])) {
				$parts[] = $contact[$key];
			}
		}

		return implode(' - ', $parts);
	}

	/**
	 * Format signature contact data on separate lines.
	 *
	 * @param array<string,string> $contact Contact
	 * @param string $fallback Fallback text
	 * @return string
	 */
	private function formatSignatureContactBlock($contact, $fallback = '')
	{
		$lines = array();
		foreach (array('fullname', 'job', 'phone', 'email') as $key) {
			if (!empty($contact[$key])) {
				$lines[] = (string) $contact[$key];
			}
		}

		if (empty($lines) && $fallback !== '') {
			$lines[] = $fallback;
		}

		return implode("\n", $lines);
	}

	/**
	 * Return normalized data for a contract contact list row.
	 *
	 * @param array<string,mixed> $contact Contact row from liste_contact
	 * @param string $source Contact source
	 * @param Translate $outputlangs Output language
	 * @return array<string,string>
	 */
	private function getListContactData($contact, $source, $outputlangs)
	{
		$data = array('fullname' => '', 'job' => '', 'phone' => '', 'email' => '');
		$id = !empty($contact['id']) ? (int) $contact['id'] : 0;

		if ($source === 'external' && $id > 0) {
			$contactstatic = new Contact($this->db);
			if ($contactstatic->fetch($id) > 0) {
				$data['fullname'] = $contactstatic->getFullName($outputlangs, 1);
				$data['job'] = (string) $contactstatic->poste;
				$data['phone'] = (string) $this->firstNonEmpty($contactstatic->phone_pro, $contactstatic->phone_mobile);
				$data['email'] = (string) $contactstatic->email;
			}
		} elseif ($source === 'internal' && $id > 0) {
			$userstatic = new User($this->db);
			if ($userstatic->fetch($id) > 0) {
				$data['fullname'] = $userstatic->getFullName($outputlangs);
				$data['job'] = !empty($userstatic->job) ? (string) $userstatic->job : '';
				$data['phone'] = (string) $this->firstNonEmpty($userstatic->office_phone, $userstatic->user_mobile);
				$data['email'] = (string) $userstatic->email;
			}
		}

		if ($data['fullname'] === '') {
			$data['fullname'] = trim((isset($contact['firstname']) ? $contact['firstname'] : '').' '.(isset($contact['lastname']) ? $contact['lastname'] : ''));
		}
		if ($data['job'] === '') {
			$data['job'] = isset($contact['poste']) ? (string) $contact['poste'] : '';
		}
		if ($data['phone'] === '') {
			$data['phone'] = (string) $this->firstNonEmpty($contact['phone'] ?? '', $contact['phone_pro'] ?? '', $contact['phone_mobile'] ?? '');
		}
		if ($data['email'] === '') {
			$data['email'] = isset($contact['email']) ? (string) $contact['email'] : '';
		}

		return $data;
	}

	/**
	 * Check if a contract contact row matches a signatory role.
	 *
	 * @param array<string,mixed> $contact Contact row from liste_contact
	 * @param string $role Role
	 * @return bool
	 */
	private function contractContactMatchesRole($contact, $role)
	{
		$text = jpsunPowerPlantPVNormalizeMatchText(implode(' ', array(
			$contact['code'] ?? '',
			$contact['typecode'] ?? '',
			$contact['label'] ?? '',
			$contact['libelle'] ?? '',
		)));

		if ($role === 'client_signatory') {
			return ((strpos($text, 'client') !== false || strpos($text, 'customer') !== false) && (strpos($text, 'signataire') !== false || strpos($text, 'signatory') !== false || strpos($text, 'sign') !== false));
		}
		if ($role === 'sales_signatory') {
			return ((strpos($text, 'commercial') !== false || strpos($text, 'sales') !== false) && (strpos($text, 'signataire') !== false || strpos($text, 'signatory') !== false || strpos($text, 'sign') !== false));
		}

		return false;
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
			'CLIENTSIGNATORY' => array('fullname' => '', 'job' => '', 'phone' => '', 'email' => ''),
			'SALESSIGNATORY' => array('fullname' => '', 'job' => '', 'phone' => '', 'email' => ''),
		);
		$firstExternalContact = array('fullname' => '', 'job' => '', 'phone' => '', 'email' => '');

		$contactlist = $object->liste_contact(-1, 'external');
		foreach ($contactlist as $contact) {
			if ($firstExternalContact['fullname'] === '') {
				$firstExternalContact = $this->getListContactData($contact, 'external', $outputlangs);
			}

			$contactcode = '';
			if (!empty($contact['code'])) {
				$contactcode = $contact['code'];
			} elseif (!empty($contact['typecode'])) {
				$contactcode = $contact['typecode'];
			}
			if ($contactcode === 'SALESSIGNATORY') {
				if ($contactdata['CLIENTSIGNATORY']['fullname'] === '') {
					$contactdata['CLIENTSIGNATORY'] = $this->getListContactData($contact, 'external', $outputlangs);
				}
				continue;
			}
			if ($contactcode !== '' && isset($contactdata[$contactcode])) {
				$contactstatic = new Contact($this->db);
				if (!empty($contact['id'])) {
					$contactstatic->fetch((int) $contact['id']);
				}

				if ($contactcode === 'SITEADDRESS') {
					$contactdata[$contactcode]['address'] = !empty($contactstatic->address) ? $contactstatic->address : (isset($contact['address']) ? $contact['address'] : '');
					$contactdata[$contactcode]['zip'] = !empty($contactstatic->zip) ? $contactstatic->zip : (isset($contact['zip']) ? $contact['zip'] : '');
					$contactdata[$contactcode]['town'] = !empty($contactstatic->town) ? $contactstatic->town : (isset($contact['town']) ? $contact['town'] : '');
				} else {
					$contactdata[$contactcode] = $this->getListContactData($contact, 'external', $outputlangs);
				}
			}

			if ($contactdata['CLIENTSIGNATORY']['fullname'] === '' && $this->contractContactMatchesRole($contact, 'client_signatory')) {
				$contactdata['CLIENTSIGNATORY'] = $this->getListContactData($contact, 'external', $outputlangs);
			}
		}
		if ($contactdata['CLIENTSIGNATORY']['fullname'] === '' && $firstExternalContact['fullname'] !== '') {
			$contactdata['CLIENTSIGNATORY'] = $firstExternalContact;
		}

		$internalcontactlist = $object->liste_contact(-1, 'internal');
		foreach ($internalcontactlist as $contact) {
			if ($contactdata['SALESSIGNATORY']['fullname'] === '' && $this->contractContactMatchesRole($contact, 'sales_signatory')) {
				$contactdata['SALESSIGNATORY'] = $this->getListContactData($contact, 'internal', $outputlangs);
			}
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
	 * Format a numeric power value with a unit.
	 *
	 * @param string|int|float $value Value
	 * @param string $unit Unit
	 * @param int $precision Precision
	 * @return string
	 */
	private function formatPowerWithUnit($value, $unit, $precision)
	{
		$formatted = $this->formatNumber($value, $precision);
		if ($formatted === '') {
			return '';
		}

		return $formatted.' '.$unit;
	}

	/**
	 * Format a date value.
	 *
	 * @param mixed $value Date value
	 * @param Translate $outputlangs Output language
	 * @return string
	 */
	private function formatDate($value, $outputlangs)
	{
		if (empty($value)) {
			return '';
		}
		if (is_numeric($value)) {
			return dol_print_date((int) $value, 'day', false, $outputlangs, true);
		}

		return dol_print_date($this->db->jdate($value), 'day', false, $outputlangs, true);
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
	 * Return available content width.
	 *
	 * @return float
	 */
	private function contentWidth()
	{
		return $this->page_largeur - $this->marge_gauche - $this->marge_droite;
	}

	/**
	 * Return text height.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param float $width Width
	 * @param string $text Text
	 * @return float
	 */
	private function getTextHeight(&$pdf, $width, $text)
	{
		if (method_exists($pdf, 'getStringHeight')) {
			return $pdf->getStringHeight($width, $text);
		}

		$charsperline = max(1, (int) floor($width / 2.2));
		return max(5, ceil(strlen($text) / $charsperline) * 5);
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
