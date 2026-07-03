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
 * \file       lib/jpsun_sepa_mandate_pdf.lib.php
 * \ingroup    jpsun
 * \brief      Shared renderer for JPSUN SEPA mandate PDF pages.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/companybankaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

/**
 * Draw a JPSUN SEPA mandate on the current PDF page.
 */
class JpsunSepaMandatePdfRenderer
{
	/**
	 * Render the mandate on the current page.
	 *
	 * @param TCPDF $pdf PDF document
	 * @param DoliDB $db Database handler
	 * @param CompanyBankAccount $bankaccount Thirdparty bank account, possibly empty for a blank mandate
	 * @param Societe|null $thirdparty Thirdparty linked to the mandate
	 * @param Translate $outputlangs Output language
	 * @param Societe $creditor Creditor company
	 * @param array<string,float|int|string|bool> $options Rendering options
	 * @return float Final Y position
	 */
	public static function render(&$pdf, $db, $bankaccount, $thirdparty, $outputlangs, $creditor, $options = array())
	{
		$outputlangs->loadLangs(array('main', 'dict', 'bank', 'withdrawals', 'companies', 'jpsun@jpsun'));

		$pageWidth = !empty($options['page_largeur']) ? (float) $options['page_largeur'] : 210.0;
		$pageHeight = !empty($options['page_hauteur']) ? (float) $options['page_hauteur'] : 297.0;
		$left = isset($options['marge_gauche']) ? (float) $options['marge_gauche'] : 10.0;
		$right = isset($options['marge_droite']) ? (float) $options['marge_droite'] : 10.0;
		$top = isset($options['top']) ? (float) $options['top'] : 24.0;
		$bottom = isset($options['bottom']) ? (float) $options['bottom'] : ($pageHeight - 18.0);
		$cornerRadius = isset($options['corner_radius']) ? (float) $options['corner_radius'] : 1.5;
		$width = $pageWidth - $left - $right;
		$fontSize = pdf_getPDFFontSize($outputlangs);
		$primaryColor = array(32, 95, 78);
		$mutedColor = array(91, 103, 112);
		$lightColor = array(239, 247, 244);

		$thirdparty = self::normalizeThirdparty($db, $bankaccount, $thirdparty);
		$frstrecur = self::getFrstRecur($bankaccount, $options);
		$creditorIdentifier = self::getCreditorIdentifier($db);
		$creditorName = self::getObjectProperty($creditor, 'name');
		$creditorAddress = (is_object($creditor) && method_exists($creditor, 'getFullAddress')) ? (string) $creditor->getFullAddress(1) : '';
		$rum = self::getObjectProperty($bankaccount, 'rum');
		$dateRum = self::getDateLabel($bankaccount, 'date_rum', $outputlangs);
		$debtorName = self::getDebtorName($bankaccount, $thirdparty);
		$debtorIdentifier = self::getDebtorIdentifier($thirdparty, $outputlangs);
		$debtorAddress = self::getDebtorAddress($bankaccount, $thirdparty);
		$iban = self::getObjectProperty($bankaccount, 'iban');
		$bic = self::getObjectProperty($bankaccount, 'bic');

		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetDrawColor(158, 171, 166);
		$pdf->SetLineWidth(0.15);

		$y = $top;
		$pdf->SetFont('', 'B', $fontSize + 3);
		$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
		$pdf->SetXY($left, $y);
		$pdf->MultiCell($width, 7, self::out($outputlangs, $outputlangs->transnoentitiesnoconv('JpsunSepaMandateTitle')), 0, 'L');
		$y = $pdf->GetY() + 2;

		$columnGap = 6.0;
		$columnWidth = ($width - $columnGap) / 2;
		$boxTop = $y;
		$boxHeight = 52.0;
		self::drawBox($pdf, $left, $boxTop, $columnWidth, $boxHeight, $cornerRadius, $lightColor);
		self::drawBox($pdf, $left + $columnWidth + $columnGap, $boxTop, $columnWidth, $boxHeight, $cornerRadius, $lightColor);
		self::drawSectionTitle($pdf, $outputlangs, $left + 3, $boxTop + 3, $columnWidth - 6, $outputlangs->transnoentitiesnoconv('JpsunSepaMandateIdentifiers'), $fontSize, $primaryColor);
		self::drawSectionTitle($pdf, $outputlangs, $left + $columnWidth + $columnGap + 3, $boxTop + 3, $columnWidth - 6, $outputlangs->transnoentitiesnoconv('JpsunSepaCreditorBlock'), $fontSize, $primaryColor);

		$rowY = $boxTop + 12;
		self::drawLabelValue($pdf, $outputlangs, $left + 3, $rowY, $columnWidth - 6, $outputlangs->transnoentitiesnoconv('RUMLong').' ('.$outputlangs->transnoentitiesnoconv('RUM').')', self::valueOrBlank($rum, 38), $fontSize);
		self::drawLabelValue($pdf, $outputlangs, $left + 3, $rowY + 14, $columnWidth - 6, $outputlangs->transnoentitiesnoconv('JpsunSepaMandateDate'), self::valueOrBlank($dateRum, 28), $fontSize);
		self::drawLabelValue($pdf, $outputlangs, $left + 3, $rowY + 29, $columnWidth - 6, $outputlangs->transnoentitiesnoconv('CreditorIdentifier').' ('.$outputlangs->transnoentitiesnoconv('ICS').')', self::valueOrBlank($creditorIdentifier, 32), $fontSize);

		$creditorX = $left + $columnWidth + $columnGap + 3;
		self::drawLabelValue($pdf, $outputlangs, $creditorX, $rowY, $columnWidth - 6, $outputlangs->transnoentitiesnoconv('CreditorName'), self::valueOrBlank($creditorName, 32), $fontSize);
		self::drawLabelValue($pdf, $outputlangs, $creditorX, $rowY + 14, $columnWidth - 6, $outputlangs->transnoentitiesnoconv('Address'), self::valueOrBlank($creditorAddress, 44), $fontSize - 1, 27);
		$y = $boxTop + $boxHeight + 6;

		$legalText = $outputlangs->transnoentitiesnoconv('SEPALegalText', $creditorName, $creditorName);
		self::drawBox($pdf, $left, $y, $width, 30.0, $cornerRadius, array(255, 255, 255));
		$pdf->SetFont('', '', $fontSize - 2);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetXY($left + 3, $y + 3);
		$pdf->MultiCell($width - 6, 3.7, self::out($outputlangs, $legalText), 0, 'L');
		$y += 36;

		self::drawBox($pdf, $left, $y, $width, 66.0, $cornerRadius, $lightColor);
		self::drawSectionTitle($pdf, $outputlangs, $left + 3, $y + 3, $width - 6, $outputlangs->transnoentitiesnoconv('JpsunSepaDebtorBlock'), $fontSize, $primaryColor);
		$rowY = $y + 12;
		self::drawLabelValue($pdf, $outputlangs, $left + 3, $rowY, $width - 6, $outputlangs->transnoentitiesnoconv('SEPAFormYourName'), self::valueOrBlank($debtorName, 50), $fontSize);
		self::drawLabelValue($pdf, $outputlangs, $left + 3, $rowY + 13, $width - 6, $outputlangs->transnoentitiesnoconv('JpsunSepaDebtorIdentifier'), self::valueOrBlank($debtorIdentifier, 50), $fontSize);
		self::drawLabelValue($pdf, $outputlangs, $left + 3, $rowY + 26, $width - 6, $outputlangs->transnoentitiesnoconv('Address'), self::valueOrBlank($debtorAddress, 72), $fontSize - 1, 12);
		self::drawLabelValue($pdf, $outputlangs, $left + 3, $rowY + 41, ($width / 2) - 5, $outputlangs->transnoentitiesnoconv('SEPAFormYourBAN'), self::valueOrBlank($iban, 38), $fontSize);
		self::drawLabelValue($pdf, $outputlangs, $left + ($width / 2) + 2, $rowY + 41, ($width / 2) - 5, $outputlangs->transnoentitiesnoconv('SEPAFormYourBIC'), self::valueOrBlank($bic, 24), $fontSize);
		$y += 72;

		self::drawBox($pdf, $left, $y, $width, 24.0, $cornerRadius, array(255, 255, 255));
		self::drawSectionTitle($pdf, $outputlangs, $left + 3, $y + 3, $width - 6, $outputlangs->transnoentitiesnoconv('JpsunSepaPaymentType'), $fontSize, $primaryColor);
		self::drawCheckbox($pdf, $outputlangs, $left + 6, $y + 13, $outputlangs->transnoentitiesnoconv('ModeRECUR'), $frstrecur === 'RCUR', $fontSize);
		self::drawCheckbox($pdf, $outputlangs, $left + 76, $y + 13, $outputlangs->transnoentitiesnoconv('ModeFRST'), $frstrecur === 'FRST', $fontSize);
		if ($frstrecur === '') {
			$pdf->SetFont('', 'I', $fontSize - 2);
			$pdf->SetTextColor($mutedColor[0], $mutedColor[1], $mutedColor[2]);
			$pdf->SetXY($left + 142, $y + 13);
			$pdf->MultiCell($width - 145, 4, self::out($outputlangs, $outputlangs->transnoentitiesnoconv('PleaseCheckOne')), 0, 'L');
		}
		$y += 30;

		$signatureHeight = max(30.0, $bottom - $y - 2.0);
		self::drawBox($pdf, $left, $y, $width, $signatureHeight, $cornerRadius, $lightColor);
		self::drawSectionTitle($pdf, $outputlangs, $left + 3, $y + 3, $width - 6, $outputlangs->transnoentitiesnoconv('JpsunSepaSignatureArea'), $fontSize, $primaryColor);
		self::drawLabelValue($pdf, $outputlangs, $left + 6, $y + 13, ($width / 2) - 10, $outputlangs->transnoentitiesnoconv('JpsunSepaSignaturePlaceDate'), self::valueOrBlank('', 38), $fontSize);
		self::drawLabelValue($pdf, $outputlangs, $left + ($width / 2) + 2, $y + 13, ($width / 2) - 8, $outputlangs->transnoentitiesnoconv('JpsunSepaSignatureLabel'), self::valueOrBlank('', 38), $fontSize);

		return $y + $signatureHeight;
	}

	/**
	 * Fetch the mandate thirdparty when it was not already loaded.
	 *
	 * @param DoliDB $db Database handler
	 * @param CompanyBankAccount $bankaccount Bank account
	 * @param Societe|null $thirdparty Thirdparty candidate
	 * @return Societe|null
	 */
	private static function normalizeThirdparty($db, $bankaccount, $thirdparty)
	{
		if (is_object($thirdparty) && !empty($thirdparty->id)) {
			return $thirdparty;
		}

		$socid = !empty($bankaccount->socid) ? (int) $bankaccount->socid : 0;
		if ($socid <= 0) {
			return is_object($thirdparty) ? $thirdparty : null;
		}

		$loadedThirdparty = new Societe($db);
		$result = $loadedThirdparty->fetch($socid);
		if ($result > 0) {
			return $loadedThirdparty;
		}

		return is_object($thirdparty) ? $thirdparty : null;
	}

	/**
	 * Return creditor ICS.
	 *
	 * @param DoliDB $db Database handler
	 * @return string
	 */
	private static function getCreditorIdentifier($db)
	{
		$ics = '';
		$idbankfordirectdebit = getDolGlobalInt('PRELEVEMENT_ID_BANKACCOUNT');
		if ($idbankfordirectdebit > 0) {
			$account = new Account($db);
			$result = $account->fetch($idbankfordirectdebit);
			if ($result > 0 && !empty($account->ics)) {
				$ics = (string) $account->ics;
			}
		}
		if ($ics === '' && getDolGlobalString('PRELEVEMENT_ICS') !== '') {
			$ics = getDolGlobalString('PRELEVEMENT_ICS');
		}

		return $ics;
	}

	/**
	 * Return mandate payment type.
	 *
	 * @param CompanyBankAccount $bankaccount Bank account
	 * @param array<string,float|int|string|bool> $options Rendering options
	 * @return string
	 */
	private static function getFrstRecur($bankaccount, $options)
	{
		if (!empty($options['force_frstrecur'])) {
			$forced = strtoupper((string) $options['force_frstrecur']);
			return in_array($forced, array('FRST', 'RCUR'), true) ? $forced : '';
		}

		$value = strtoupper(self::getObjectProperty($bankaccount, 'frstrecur'));
		if (in_array($value, array('FRST', 'RCUR'), true)) {
			return $value;
		}

		if (!empty($options['default_frstrecur'])) {
			$default = strtoupper((string) $options['default_frstrecur']);
			return in_array($default, array('FRST', 'RCUR'), true) ? $default : '';
		}

		return '';
	}

	/**
	 * Return a safe object property as string.
	 *
	 * @param object|null $object Object
	 * @param string $property Property name
	 * @return string
	 */
	private static function getObjectProperty($object, $property)
	{
		if (!is_object($object) || !isset($object->{$property})) {
			return '';
		}

		return trim((string) $object->{$property});
	}

	/**
	 * Return a printable date label.
	 *
	 * @param object|null $object Object
	 * @param string $property Property name
	 * @param Translate $outputlangs Output language
	 * @return string
	 */
	private static function getDateLabel($object, $property, $outputlangs)
	{
		if (!is_object($object) || empty($object->{$property})) {
			return '';
		}

		return dol_print_date((int) $object->{$property}, 'day', false, $outputlangs, true);
	}

	/**
	 * Return debtor display name.
	 *
	 * @param CompanyBankAccount $bankaccount Bank account
	 * @param Societe|null $thirdparty Thirdparty
	 * @return string
	 */
	private static function getDebtorName($bankaccount, $thirdparty)
	{
		$thirdpartyName = self::getObjectProperty($thirdparty, 'name');
		$ownerName = self::getObjectProperty($bankaccount, 'owner_name');
		if ($thirdpartyName !== '' && $ownerName !== '' && $ownerName !== $thirdpartyName) {
			return $thirdpartyName.' ('.$ownerName.')';
		}
		if ($thirdpartyName !== '') {
			return $thirdpartyName;
		}

		return $ownerName;
	}

	/**
	 * Return debtor identifier.
	 *
	 * @param Societe|null $thirdparty Thirdparty
	 * @param Translate $outputlangs Output language
	 * @return string
	 */
	private static function getDebtorIdentifier($thirdparty, $outputlangs)
	{
		if (!is_object($thirdparty) || empty($thirdparty->idprof1)) {
			return '';
		}

		$countryCode = !empty($thirdparty->country_code) ? (string) $thirdparty->country_code : '';
		$label = $outputlangs->transnoentitiesnoconv('ProfId1'.$countryCode);
		if ($label === 'ProfId1'.$countryCode) {
			$label = $outputlangs->transnoentitiesnoconv('ProfId1');
		}

		return $label.' : '.(string) $thirdparty->idprof1;
	}

	/**
	 * Return debtor address.
	 *
	 * @param CompanyBankAccount $bankaccount Bank account
	 * @param Societe|null $thirdparty Thirdparty
	 * @return string
	 */
	private static function getDebtorAddress($bankaccount, $thirdparty)
	{
		$ownerAddress = self::getObjectProperty($bankaccount, 'owner_address');
		if ($ownerAddress !== '') {
			return $ownerAddress;
		}
		if (is_object($thirdparty) && method_exists($thirdparty, 'getFullAddress')) {
			return trim((string) $thirdparty->getFullAddress(1));
		}

		return '';
	}

	/**
	 * Return value or a blank line.
	 *
	 * @param string $value Value
	 * @param int $length Blank line length
	 * @return string
	 */
	private static function valueOrBlank($value, $length)
	{
		$value = trim($value);
		if ($value !== '') {
			return $value;
		}

		return str_repeat('_', max(12, $length));
	}

	/**
	 * Draw a section box.
	 *
	 * @param TCPDF $pdf PDF document
	 * @param float $x X
	 * @param float $y Y
	 * @param float $w Width
	 * @param float $h Height
	 * @param float $radius Corner radius
	 * @param array<int,int> $fillColor Fill color
	 * @return void
	 */
	private static function drawBox(&$pdf, $x, $y, $w, $h, $radius, $fillColor)
	{
		$pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
		if (method_exists($pdf, 'RoundedRect')) {
			$pdf->RoundedRect($x, $y, $w, $h, $radius, '1234', 'DF');
			return;
		}

		$pdf->Rect($x, $y, $w, $h, 'DF');
	}

	/**
	 * Draw a section title.
	 *
	 * @param TCPDF $pdf PDF document
	 * @param Translate $outputlangs Output language
	 * @param float $x X
	 * @param float $y Y
	 * @param float $w Width
	 * @param string $title Title
	 * @param int $fontSize Base font size
	 * @param array<int,int> $color RGB color
	 * @return void
	 */
	private static function drawSectionTitle(&$pdf, $outputlangs, $x, $y, $w, $title, $fontSize, $color)
	{
		$pdf->SetTextColor($color[0], $color[1], $color[2]);
		$pdf->SetFont('', 'B', $fontSize);
		$pdf->SetXY($x, $y);
		$pdf->MultiCell($w, 5, self::out($outputlangs, $title), 0, 'L');
		$pdf->SetTextColor(0, 0, 0);
	}

	/**
	 * Draw a label/value pair.
	 *
	 * @param TCPDF $pdf PDF document
	 * @param Translate $outputlangs Output language
	 * @param float $x X
	 * @param float $y Y
	 * @param float $w Width
	 * @param string $label Label
	 * @param string $value Value
	 * @param int $fontSize Base font size
	 * @param float $valueHeight Value height
	 * @return void
	 */
	private static function drawLabelValue(&$pdf, $outputlangs, $x, $y, $w, $label, $value, $fontSize, $valueHeight = 5.0)
	{
		$pdf->SetTextColor(91, 103, 112);
		$pdf->SetFont('', 'B', $fontSize - 2);
		$pdf->SetXY($x, $y);
		$pdf->MultiCell($w, 3.2, self::out($outputlangs, $label), 0, 'L');
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $fontSize - 1);
		$pdf->SetXY($x, $y + 3.5);
		$pdf->MultiCell($w, $valueHeight, self::out($outputlangs, $value), 0, 'L');
	}

	/**
	 * Draw a checkbox with label.
	 *
	 * @param TCPDF $pdf PDF document
	 * @param Translate $outputlangs Output language
	 * @param float $x X
	 * @param float $y Y
	 * @param string $label Label
	 * @param bool $checked Checked flag
	 * @param int $fontSize Base font size
	 * @return void
	 */
	private static function drawCheckbox(&$pdf, $outputlangs, $x, $y, $label, $checked, $fontSize)
	{
		$pdf->SetDrawColor(80, 95, 91);
		$pdf->SetFillColor(255, 255, 255);
		if (method_exists($pdf, 'RoundedRect')) {
			$pdf->RoundedRect($x, $y, 5, 5, 0.8, '1234', 'D');
		} else {
			$pdf->Rect($x, $y, 5, 5, 'D');
		}
		if ($checked) {
			$pdf->SetFont('', 'B', $fontSize);
			$pdf->SetTextColor(32, 95, 78);
			$pdf->SetXY($x + 1.1, $y - 0.3);
			$pdf->MultiCell(4, 4, 'X', 0, 'L');
		}
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $fontSize - 1);
		$pdf->SetXY($x + 7, $y + 0.2);
		$pdf->MultiCell(62, 4, self::out($outputlangs, $label), 0, 'L');
	}

	/**
	 * Convert a text to PDF output charset.
	 *
	 * @param Translate $outputlangs Output language
	 * @param string $text Text
	 * @return string
	 */
	private static function out($outputlangs, $text)
	{
		return $outputlangs->convToOutputCharset($text);
	}
}
