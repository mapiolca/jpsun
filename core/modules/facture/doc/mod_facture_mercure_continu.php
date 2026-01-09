<?php
/* Copyright (C) 2003-2007	Rodolphe Quiedeville		<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011	Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2007	Regis Houssin				<regis.houssin@inodbox.com>
 * Copyright (C) 2008		Raphael Bertrand (Resultic)	<raphael.bertrand@resultic.fr>
 * Copyright (C) 2013		Juanjo Menent				<jmenent@2byte.es>
 * Copyright (C) 2022		Anthony Berton				<anthony.berton@bb2a.fr>
 * Copyright (C) 2024       Frédéric France             <frederic.france@free.fr>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024		Nick Fragoulis
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
 * or see https://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/facture/mod_facture_mercure.php
 *	\ingroup    invoice
 *	\brief      File containing class for numbering module Mercure
 */
require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';


/**
 *	Class of numbering module Mercure for invoices
 */
class mod_facture_mercure_continu extends ModeleNumRefFactures
{
	/**
	 * @var string Sub-module name
	 */
	public $name = 'MercureContinu';

	/**
	 * @var int		Position
	 */
	public $position = 51;

	/**
	 * Dolibarr version of the loaded document
	 * @var string Version, possible values are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'''|'development'|'dolibarr'|'experimental'
	 */
	public $version = 'dolibarr'; // 'development', 'experimental', 'dolibarr'

	/**
	 * @var string Error message
	 */
	public $error = '';


	/**
	 *  Returns the description of the numbering model
	 *
	 *	@param	Translate	$langs      Lang object to use for output
	 *  @return string      			Descriptive text
	 */
	public function info($langs)
	{
		global $db, $langs;

		$langs->load("bills");

		$form = new Form($db);

		$texte = $langs->trans('GenericNumRefModelDesc')."<br>\n";
		$texte .= '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
		$texte .= '<input type="hidden" name="token" value="'.newToken().'">';
		$texte .= '<input type="hidden" name="action" value="updateMask">';
		$texte .= '<input type="hidden" name="maskconstinvoice" value="FACTURE_MERCURE_MASK_INVOICE">';
		$texte .= '<input type="hidden" name="maskconstreplacement" value="FACTURE_MERCURE_MASK_REPLACEMENT">';
		$texte .= '<input type="hidden" name="maskconstcredit" value="FACTURE_MERCURE_MASK_CREDIT">';
		$texte .= '<input type="hidden" name="maskconstdeposit" value="FACTURE_MERCURE_MASK_DEPOSIT">';
		$texte .= '<input type="hidden" name="page_y" value="">';

		$texte .= '<table class="nobordernopadding centpercent">';

		$tooltip = $langs->trans("GenericMaskCodes", $langs->transnoentities("Invoice"), $langs->transnoentities("Invoice"));
		$tooltip .= $langs->trans("GenericMaskCodes1");
		$tooltip .= '<br>';
		$tooltip .= $langs->trans("GenericMaskCodes2");
		$tooltip .= '<br>';
		$tooltip .= $langs->trans("GenericMaskCodes3");
		$tooltip .= '<br>';
		$tooltip .= $langs->trans("GenericMaskCodes4a", $langs->transnoentities("Invoice"), $langs->transnoentities("Invoice"));
		$tooltip .= $langs->trans("GenericMaskCodes5");
		$tooltip .= '<br>'.$langs->trans("GenericMaskCodes5b");

		// Setting the prefix
		$texte .= '<tr><td><span class="opacitymedium">'.$langs->trans("Mask").' ('.$langs->trans("InvoiceStandard").'):</span></td>';
		$texte .= '<td class="right">'.$form->textwithpicto('<input type="text" class="flat minwidth175" name="maskinvoice" value="'.getDolGlobalString("FACTURE_MERCURE_MASK_INVOICE").'">', $tooltip, 1, 'help', '', 0, 3, 'tooltipstandardmercure').'</td>';

		$texte .= '<td class="left" rowspan="3">&nbsp; <input type="submit" class="button button-edit reposition smallpaddingimp" name="Button" value="'.$langs->trans("Save").'"></td>';

		$texte .= '</tr>';

		// Prefix setting of credit note
		$texte .= '<tr><td><span class="opacitymedium">'.$langs->trans("Mask").' ('.$langs->trans("InvoiceAvoir").'):</span></td>';
		$texte .= '<td class="right">'.$form->textwithpicto('<input type="text" class="flat minwidth175" name="maskcredit" value="'.getDolGlobalString("FACTURE_MERCURE_MASK_CREDIT").'">', $tooltip, 1, 'help', '', 0, 3, 'tooltipcreditnotemercure').'</td>';
		$texte .= '</tr>';

		// Prefix setting of replacement invoices
		if (!getDolGlobalString('INVOICE_DISABLE_REPLACEMENT')) {
			$texte .= '<tr><td><span class="opacitymedium">'.$langs->trans("Mask").' ('.$langs->trans("InvoiceReplacement").'):</span></td>';
			$texte .= '<td class="right">'.$form->textwithpicto('<input type="text" class="flat minwidth175" name="maskreplacement" value="'.getDolGlobalString("FACTURE_MERCURE_MASK_REPLACEMENT").'">', $tooltip, 1, 'help', '', 0, 3, 'tooltipreplacementmercure').'</td>';
			$texte .= '</tr>';
		}

		// Prefix setting of deposit
		if (!getDolGlobalString('INVOICE_DISABLE_DEPOSIT')) {
			$texte .= '<tr><td><span class="opacitymedium">'.$langs->trans("Mask").' ('.$langs->trans("InvoiceDeposit").'):</span></td>';
			$texte .= '<td class="right">'.$form->textwithpicto('<input type="text" class="flat minwidth175" name="maskdeposit" value="'.getDolGlobalString("FACTURE_MERCURE_MASK_DEPOSIT").'">', $tooltip, 1, 'help', '', 0, 3, 'tooltipdownpaymentmercure').'</td>';
			$texte .= '</tr>';
		}

		$texte .= '</table>';
		$texte .= '</form>';

		return $texte;
	}

	/**
	 *  Return an example of number value
	 *
	 *  @return     string      Example
	 */
	public function getExample()
	{
		global $mysoc;

		$old_code_client = $mysoc->code_client;
		$old_code_type = $mysoc->typent_code;
		$mysoc->code_client = 'CCCCCCCCCC';
		$mysoc->typent_code = 'TTTTTTTTTT';
		$numExample = $this->getNextValue($mysoc, null);
		$mysoc->code_client = $old_code_client;
		$mysoc->typent_code = $old_code_type;

		if (!$numExample) {
			$numExample = 'NotConfigured';
		}
		return $numExample;
	}

	/**
	 * Return next value not used or last value used
	 *
	 * @param	Societe		$objsoc		Object third party
	 * @param   ?Facture	$invoice	Object invoice
	 * @param   string		$mode		'next' for next value or 'last' for last value
	 * @return  string|int<-1,0>		Value if OK, <=0 if KO
	 */
	public function getNextValue($objsoc, $invoice, $mode = 'next')
	{
		global $db;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

		// Determine mask for current invoice type (for prefix/date formatting)
		$maskCurrent = '';
		if (is_object($invoice) && $invoice->type == 1) {
			$maskCurrent = getDolGlobalString('FACTURE_MERCURE_MASK_REPLACEMENT', getDolGlobalString('FACTURE_MERCURE_MASK_INVOICE'));
		} elseif (is_object($invoice) && $invoice->type == 2) {
			$maskCurrent = getDolGlobalString('FACTURE_MERCURE_MASK_CREDIT');
		} elseif (is_object($invoice) && $invoice->type == 3 && !getDolGlobalString('INVOICE_DISABLE_DEPOSIT')) {
			$maskCurrent = getDolGlobalString('FACTURE_MERCURE_MASK_DEPOSIT');
		} else {
			$maskCurrent = getDolGlobalString('FACTURE_MERCURE_MASK_INVOICE');
		}

		if (empty($maskCurrent)) {
			$this->error = 'NotConfigured';
			return 0;
		}

		// Masks to include in the global sequence (continuity across types)
		$masksToCheck = array();

		$maskInvoice = getDolGlobalString('FACTURE_MERCURE_MASK_INVOICE');
		if (!empty($maskInvoice)) $masksToCheck[] = $maskInvoice;

		$maskReplacement = getDolGlobalString('FACTURE_MERCURE_MASK_REPLACEMENT', $maskInvoice);
		if (!empty($maskReplacement)) $masksToCheck[] = $maskReplacement;

		$maskCredit = getDolGlobalString('FACTURE_MERCURE_MASK_CREDIT');
		if (!empty($maskCredit)) $masksToCheck[] = $maskCredit;

		if (!getDolGlobalString('INVOICE_DISABLE_DEPOSIT')) {
			$maskDeposit = getDolGlobalString('FACTURE_MERCURE_MASK_DEPOSIT');
			if (!empty($maskDeposit)) $masksToCheck[] = $maskDeposit;
		}

		$where = '';

		// Get entities (multicompany / shared numbering)
		$entity = getEntity('invoicenumber', 1, $invoice);

		$refDate = (empty($invoice) ? dol_now() : $invoice->date);

		// Compute the global last counter across all masks (same year/period logic as masks)
		$maxCounter = 0;
		$lastRefOfMax = '';
		foreach ($masksToCheck as $maskToCheck) {
			$last = get_next_value($db, $maskToCheck, 'facture', 'ref', $where, $objsoc, $refDate, 'last', false, null, $entity);
			if (!is_string($last) || empty($last)) continue;

			$counter = $this->extractCounterFromRef($last, $maskToCheck);
			if ($counter > $maxCounter) {
				$maxCounter = $counter;
				$lastRefOfMax = $last;
			}
		}

		if ($mode === 'last') {
			return $lastRefOfMax;
		}

		$nextCounter = $maxCounter + 1;

		// Get a reference generated by Dolibarr for the current mask (to ensure correct prefix/date parts),
		// then replace the counter with the global one.
		$candidate = get_next_value($db, $maskCurrent, 'facture', 'ref', $where, $objsoc, $refDate, 'next', false, null, $entity);
		if (!is_string($candidate) || empty($candidate)) {
			$this->error = $candidate;
			return 0;
		}

		// Replace counter inside candidate
		$numFinal = $this->replaceCounterInRef($candidate, $maskCurrent, $nextCounter);

		// Ensure uniqueness (very rare collisions, but possible under concurrency)
		$loopguard = 0;
		while ($this->refExists($db, $numFinal, $entity)) {
			$nextCounter++;
			$numFinal = $this->replaceCounterInRef($candidate, $maskCurrent, $nextCounter);

			$loopguard++;
			if ($loopguard > 100) {
				$this->error = 'FailedToFindFreeRef';
				return 0;
			}
		}

		if (!preg_match('/([0-9])+/', $numFinal)) {
			$this->error = $numFinal;
		}

		return $numFinal;
	}

	/**
	 * Extract numeric counter from a ref using a mask. Fallback to last digit block if no match.
	 *
	 * @param string $ref
	 * @param string $mask
	 * @return int
	 */
	private function extractCounterFromRef($ref, $mask)
	{
		$counterLen = 0;
		$regex = $this->buildRegexFromMask($mask, $counterLen);

		if (!empty($regex) && preg_match($regex, $ref, $m) && !empty($m['counter'])) {
			return (int) $m['counter'];
		}

		// Fallback: last block of digits
		if (preg_match('/(\d+)(?!.*\d)/', $ref, $m2)) {
			return (int) $m2[1];
		}

		return 0;
	}

	/**
	 * Replace counter in a ref using the mask to locate it.
	 *
	 * @param string $ref
	 * @param string $mask
	 * @param int    $newCounter
	 * @return string
	 */
	private function replaceCounterInRef($ref, $mask, $newCounter)
	{
		$counterLen = 0;
		$regex = $this->buildRegexFromMask($mask, $counterLen);

		$newCounterStr = (string) $newCounter;
		if ($counterLen > 0 && dol_strlen($newCounterStr) < $counterLen) {
			$newCounterStr = str_pad($newCounterStr, $counterLen, '0', STR_PAD_LEFT);
		}

		if (!empty($regex)) {
			$matches = array();
			if (preg_match($regex, $ref, $matches, PREG_OFFSET_CAPTURE) && isset($matches['counter'][0], $matches['counter'][1])) {
				$offset = $matches['counter'][1];
				$len = dol_strlen($matches['counter'][0]);

				return substr($ref, 0, $offset).$newCounterStr.substr($ref, $offset + $len);
			}
		}

		// Fallback: replace last block of digits
		if (preg_match('/(\d+)(?!.*\d)/', $ref, $m2, PREG_OFFSET_CAPTURE)) {
			$offset = $m2[1][1];
			$len = dol_strlen($m2[1][0]);

			return substr($ref, 0, $offset).$newCounterStr.substr($ref, $offset + $len);
		}

		// If we can't find a counter at all, return original ref (and let validation fail)
		return $ref;
	}

	/**
	 * Build a regex from mask with a named group 'counter' for the {000...} token.
	 *
	 * @param string $mask
	 * @param int    $counterLen (output)
	 * @return string
	 */
	private function buildRegexFromMask($mask, &$counterLen)
	{
		$counterLen = 0;
		if (empty($mask)) return '';

		$regex = '#^';
		$len = dol_strlen($mask);

		for ($i = 0; $i < $len; $i++) {
			$c = $mask[$i];

			if ($c === '{') {
				$end = strpos($mask, '}', $i);
				if ($end === false) {
					$regex .= preg_quote($c, '#');
					continue;
				}

				$token = substr($mask, $i + 1, $end - $i - 1);

				if (preg_match('/^0+$/', $token)) {
					$counterLen = dol_strlen($token);
					$regex .= '(?P<counter>\d{'.$counterLen.'})';
				} elseif ($token === 'yyyy') {
					$regex .= '\d{4}';
				} elseif ($token === 'yy') {
					$regex .= '\d{2}';
				} elseif ($token === 'mm') {
					$regex .= '\d{2}';
				} elseif ($token === 'dd') {
					$regex .= '\d{2}';
				} else {
					// Unknown token: accept any non-greedy chars
					$regex .= '.*?';
				}

				$i = $end;
				continue;
			}

			$regex .= preg_quote($c, '#');
		}

		$regex .= '$#';
		return $regex;
	}

	/**
	 * Check if a reference already exists in llx_facture for the target entities.
	 *
	 * @param DoliDB $db
	 * @param string $ref
	 * @param mixed  $entityList
	 * @return bool
	 */
	private function refExists($db, $ref, $entityList)
	{
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture";
		$sql .= " WHERE ref = '".$db->escape($ref)."'";

		$clean = preg_replace('/[^0-9,]/', '', (string) $entityList);
		if ($clean !== '') {
			$sql .= " AND entity IN (".$clean.")";
		}

		$resql = $db->query($sql);
		if ($resql) {
			return ($db->num_rows($resql) > 0);
		}

		return false;
	}


	/**
	 * Return next free value
	 *
	 * @param	Societe			$objsoc     	Object third party
	 * @param	Facture			$objforref		Object for number to search
	 * @param   string			$mode       	'next' for next value or 'last' for last value
	 * @return  string|int      				Next free value, 0 if KO
	 * @deprecated see getNextValue
	 */
	public function getNumRef($objsoc, $objforref, $mode = 'next')
	{
		return $this->getNextValue($objsoc, $objforref, $mode);
	}
}
