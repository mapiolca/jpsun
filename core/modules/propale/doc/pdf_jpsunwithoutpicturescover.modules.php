<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later */
require_once __DIR__.'/pdf_jpsunwithoutpictures.modules.php';
require_once dirname(__DIR__, 4).'/class/jpsunproposalcover.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

/** Existing commercial layout with an additional A4 cover. */
class pdf_jpsunwithoutpicturescover extends pdf_jpsunwithoutpictures
{
	/** @var array<string,string> */
	private $coverConfig = array();
	/** @var string */
	private $coverImage = '';
	/** @var float|null */
	private $moduleCount;

	/** @param DoliDB $db Database */
	public function __construct($db)
	{
		parent::__construct($db);
		global $langs;
		$this->name = JpsunProposalCover::MODEL;
		$this->description = $langs->trans('JpsunCoverModel');
		$this->page_largeur = 210;
		$this->page_hauteur = 297;
		$this->format = array(210, 297);
	}

	/**
	 * @param Propal $object Proposal
	 * @param Translate|null $outputlangs Language
	 * @param string $srctemplatepath Template
	 * @param int $hidedetails Hide details
	 * @param int $hidedesc Hide description
	 * @param int $hideref Hide reference
	 * @return int
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $user, $langs, $conf;
		$outputlangs = is_object($outputlangs) ? $outputlangs : $langs;
		$outputlangs->load('jpsun@jpsun');
		if (!isModEnabled('jpsun') || !isModEnabled('propal') || !$user->hasRight('propal', 'lire') || !$user->hasRight('propal', 'creer') || getDolGlobalString('MAIN_USE_FPDF')) {
			$this->error = $outputlangs->trans('JpsunCoverUnavailable');
			return 0;
		}
		if ((int) $object->entity !== (int) $conf->entity && isModEnabled('multicompany') && !$user->hasRight('multicompany', 'propal', 'write')) {
			$this->error = $outputlangs->trans('JpsunCoverUnavailable');
			return 0;
		}
		$this->coverImage = '';
		if (empty($object->specimen)) {
			restrictedArea($user, 'propal', $object->id, 'propal', '', 'fk_soc', 'rowid', 0);
			if ($object->fetch_optionals() < 0) {
				$this->error = $outputlangs->trans('JpsunCoverReadError');
				return 0;
			}
			$cover = new JpsunProposalCover($this->db);
			if (!$cover->loadOwnerValues($object)) {
				$this->error = $outputlangs->trans($cover->error);
				return 0;
			}
			$image = $cover->directory($object) === false ? false : $cover->image($object);
			if ($image === false) {
				$this->error = $outputlangs->trans($cover->error);
				return 0;
			}
			$this->coverImage = $image;
		}
		$this->moduleCount = null;
		if (isModEnabled('powerplantpv')) {
			dol_include_once('/powerplantpv/lib/powerplantpv.lib.php');
			if (function_exists('powerplantpvCountProposalModules') && empty($object->specimen)) {
				$count = powerplantpvCountProposalModules($object, $user);
				if ($count['result'] < 0) {
					$this->error = $outputlangs->trans('JpsunCoverReadError');
					return 0;
				}
				$this->moduleCount = $count['quantity'];
			}
		}
		$this->coverConfig = array();
		$keys = array_merge(array_keys(JpsunProposalCover::COLORS), array('MAIN_INFO_SOCIETE_NOM', 'MAIN_INFO_SOCIETE_ADDRESS', 'MAIN_INFO_SOCIETE_ZIP', 'MAIN_INFO_SOCIETE_TOWN', 'MAIN_INFO_SOCIETE_WEB', 'MAIN_INFO_SOCIETE_LOGO'));
		// Native constant getters cannot report a SQL failure. Read this owner snapshot once,
		// preserving the authenticated entity and native encrypted-value handling.
		$sql = 'SELECT name, '.$this->db->sanitize($this->db->decrypt('value')).' AS value FROM '.MAIN_DB_PREFIX.'const WHERE entity = '.((int) $object->entity);
		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $outputlangs->trans('JpsunCoverReadError');
			return 0;
		}
		$this->coverConfig = array_fill_keys($keys, '');
		while (is_object($row = $this->db->fetch_object($res))) {
			if (in_array($row->name, $keys, true)) {
				$this->coverConfig[$row->name] = dolDecrypt($row->value);
			}
		}
		$this->db->free($res);
		return parent::write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);
	}

	/**
	 * @param TCPDF $pdf PDF
	 * @param Propal $object Proposal
	 * @param Translate $outputlangs Language
	 * @return void
	 */
	protected function printCover($pdf, $object, $outputlangs)
	{
		global $conf, $user;
		$pdf->AddPage();
		$pdf->SetAutoPageBreak(false);
		$pdf->SetFillColor(255, 255, 255);
		$pdf->Rect(0, 0, 210, 297, 'F');
		$color = function (string $key): array {
			$hex = $this->coverConfig[$key] ?? '';
			$hex = preg_match('/^#[0-9a-f]{6}$/iD', $hex) ? $hex : JpsunProposalCover::COLORS[$key];
			return array(hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
		};
		$ink = $color('JPSUN_COVER_TEXT');
		$accent = $color('JPSUN_COVER_ACCENT');
		$text = function (float $x, float $y, float $w, float $h, string $value, float $size, string $style = '', bool $blue = false) use ($pdf, $outputlangs, $ink, $accent): void {
			$pdf->SetTextColor(...($blue ? $accent : $ink));
			$pdf->SetFont(pdf_getPDFFont($outputlangs), $style, $size);
			$pdf->SetXY($x, $y);
			$pdf->MultiCell($w, $h, $outputlangs->convToOutputCharset($value), 0, 'L', false, 1, null, null, true, 0, false, true, $h, 'T', true);
		};
		$logo = $this->coverConfig['MAIN_INFO_SOCIETE_LOGO'];
		$logoRoot = $conf->mycompany->multidir_output[(int) $object->entity] ?? '';
		$logoPath = $logoRoot.'/logos/'.basename($logo);
		if ($logo !== '' && $logoRoot !== '' && is_file($logoPath) && !is_link($logoPath)) {
			$size = getimagesize($logoPath);
			if ($size) {
				$scale = min(53 / $size[0], 22 / $size[1]);
				$pdf->Image($logoPath, 15, 15, $size[0] * $scale, $size[1] * $scale);
			}
		} else {
			$text(15, 17, 65, 18, $this->coverConfig['MAIN_INFO_SOCIETE_NOM'], 22, 'B');
		}
		$text(105, 15, 90, 6, $outputlangs->transnoentities('JpsunCoverOffer'), 13, 'B');
		$text(105, 21, 90, 8, $outputlangs->transnoentities('JpsunCoverOfferDetails', dol_print_date($object->date, 'daytext', false, $outputlangs), $object->ref), 9);
		$text(15, 46.5, 180, 6, $outputlangs->transnoentities('JpsunCoverInstallation'), 10, 'B', true);
		$text(15, 55.5, 180, 21, (string) $object->ref_client, 24, 'B');
		$text(15, 78, 180, 8, (string) ($object->array_options['options_powerplantpv_consumption_type'] ?? ''), 11);
		$pdf->SetFillColor(...$color('JPSUN_COVER_BACKGROUND'));
		$pdf->Rect(15, 88.5, 180, 81.5, 'F');
		if ($this->coverImage !== '') {
			$size = getimagesize($this->coverImage);
			if ($size) {
				$p = JpsunProposalCover::placement($size[0], $size[1]);
				$pdf->StartTransform();
				$pdf->Rect(15, 88.5, 180, 81.5, 'CNZ');
				$pdf->Image($this->coverImage, $p['x'], $p['y'], $p['width'], $p['height']);
				$pdf->StopTransform();
			}
		}
		$metrics = array();
		foreach (array('powerplantpv_peak_power' => 'kWc', 'powerplantpv_storage_capacity' => 'kWh') as $key => $unit) {
			$value = $object->array_options['options_'.$key] ?? null;
			if (is_numeric($value) && (float) $value > 0) {
				$metrics[] = price($value, 0, $outputlangs, 1, -1).' '.$unit;
			}
		}
		if ($this->moduleCount !== null && $this->moduleCount > 0) {
			$metrics[] = $outputlangs->transnoentities('JpsunCoverModules', price($this->moduleCount, 0, $outputlangs, 1, -1, $this->moduleCount == floor($this->moduleCount) ? 0 : -1));
		}
		$text(15, 172, 180, 7, implode('  ·  ', $metrics), 12, 'B');
		$text(15, 179.5, 180, 8, (string) ($object->array_options['options_jpsun_cover_caption'] ?? ''), 8);
		$text(15, 190, 180, 5, $outputlangs->transnoentities('JpsunCoverPresentedTo'), 9, 'B', true);
		$text(15, 197, 180, 9, (string) $object->thirdparty->name, 17);
		$names = array();
		$contacts = $object->liste_contact(4, 'external', 0, 'CUSTOMER');
		if (is_array($contacts)) {
			foreach ($contacts as $contact) {
				$person = new Contact($this->db);
				if ($person->fetch((int) $contact['id']) > 0 && (int) $person->socid === (int) $object->socid
					&& restrictedArea($user, 'contact', $person, 'socpeople&societe', '', '', 'rowid', 0, 1, 'read')) {
					$names[] = $person->getFullName($outputlangs);
				}
			}
		}
		$text(15, 207, 180, 10, $names ? $outputlangs->transnoentities('JpsunCoverAttention', implode(', ', $names)) : '', 11);
		$text(15, 219, 180, 9, trim($object->thirdparty->address.' · '.$object->thirdparty->zip.' '.$object->thirdparty->town, ' ·'), 9);
		$text(15, 229, 180, 5, $outputlangs->transnoentities('JpsunCoverContact', $this->coverConfig['MAIN_INFO_SOCIETE_NOM']), 9, 'B', true);
		$agent = new User($this->db);
		$agentId = (int) ($object->status == $object::STATUS_DRAFT ? $object->user_author_id : $object->user_validation_id);
		if ($agentId > 0 && $agent->fetch($agentId) > 0) {
			$text(15, 235, 180, 7, $agent->getFullName($outputlangs), 13, 'B');
			$text(15, 243, 180, 9, implode('  ·  ', array_filter(array($agent->office_phone ?: $agent->user_mobile, $agent->email))), 10);
		}
		$text(15, 281, 82, 9, $this->coverConfig['MAIN_INFO_SOCIETE_NOM'].' | '.str_replace("\n", ', ', $this->coverConfig['MAIN_INFO_SOCIETE_ADDRESS']).' | '.$this->coverConfig['MAIN_INFO_SOCIETE_ZIP'].' '.$this->coverConfig['MAIN_INFO_SOCIETE_TOWN'], 7);
		$text(100, 281, 95, 9, $this->coverConfig['MAIN_INFO_SOCIETE_WEB'], 7);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetAutoPageBreak(true, 0);
	}
}
