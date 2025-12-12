<?php
/* Copyright (C) 2025 Pierre Ardoin        <dev.dolibarr@lemsetiersdubatiment.fr>
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
 * \file       htdocs/core/modules/propale/mod_propale_saphir.php
 * \ingroup    propale
 * \brief      File that contains the numbering module rules Saphir
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/propale/modules_propale.php';


/**
 * Class of file that contains the numbering module rules Saphir
 */
class mod_propale_JPSUN extends ModeleNumRefPropales
{
	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr'; // 'development', 'experimental', 'dolibarr'

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string Nom du modele
	 * @deprecated
	 * @see $name
	 */
	public $nom = 'JPSUN';

	/**
	 * @var string model name
	 */
	public $name = 'JPSUN';


	/**
	 *  Return description of module
	 *
	 *	@param	Translate	$langs      Lang object to use for output
	 *  @return string      			Descriptive text
	 */
	public function info($langs)
	{
		global $conf, $langs, $db, $user;

		$langs->load("bills");
		$langs->load("jpsun@jpsun");

		$form = new Form($db);

		$texte = $langs->trans('JPSUNNumRefModelDesc')."<br>\n";
		$texte .= '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
		$texte .= '<input type="hidden" name="token" value="'.newToken().'">';
		$texte .= '<input type="hidden" name="action" value="updateMask">';
		//$texte .= '<input type="hidden" name="maskconstpropal" value="PROPALE_JPSUN_MASK">';
		$texte .= '<table class="nobordernopadding" width="100%">';

		$tooltip = $langs->trans("GenericMaskCodes", $langs->transnoentities("Proposal"), $langs->transnoentities("Proposal"));
		$tooltip .= $langs->trans("GenericMaskCodes2");
		$tooltip .= $langs->trans("GenericMaskCodes3");
		$tooltip .= $langs->trans("GenericMaskCodes4a", $langs->transnoentities("Proposal"), $langs->transnoentities("Proposal"));
		$tooltip .= $langs->trans("GenericMaskCodes5");
		$tooltip .= '<br>'.$langs->trans("GenericMaskCodes5b");
	

		// Parametrage du prefix
		//$texte .= '<tr><td>'.$langs->trans("Mask").':</td>';
		//$mask = !getDolGlobalString('PROPALE_JPSUN_MASK') ? '' : $conf->global->PROPALE_JPSUN_MASK;
		//$texte .= '<td class="right">'.$form->textwithpicto('<input type="text" class="flat minwidth175" name="maskpropal" value="'.$mask.'">', $tooltip, 1, 1).'</td>';

		//$texte .= '<td class="left" rowspan="2">&nbsp; <input type="submit" class="button button-edit reposition smallpaddingimp" name="Button"value="'.$langs->trans("Modify").'"></td>';

		$texte .= '</tr>';

		$texte .= '</table>';
		$texte .= '</form>';

		return $texte;
	}

	/**
	 *  Return an example of numbering
	 *
	 *  @return     string      Example
	 */
	public function getExample()
	{
		global $conf, $langs, $mysoc, $user;

		$old_code_client = $mysoc->code_client;
		$old_code_type = $mysoc->typent_code;
		$mysoc->code_client = 'CCCCCCCCCC';
		$mysoc->typent_code = 'TTTTTTTTTT';
		$numExample = $this->getNextValue($mysoc, '');
		$mysoc->code_client = $old_code_client;
		$mysoc->typent_code = $old_code_type;

		if (!$numExample) {
			$numExample = 'NotConfigured';
		}
		return $numExample;
	}

	/**
	 *  Return next value
	 *
	 *  @param	Societe			$objsoc     Object third party
	 * 	@param	Propal			$propal		Object commercial proposal
	 *  @return string|int      			Value if OK, 0 if KO
	 */
	public function getNextValue($objsoc, $propal)
	{
		global $db, $conf, $user;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

		// On defini critere recherche compteur
		//$mask = !getDolGlobalString('PROPALE_JPSUN_MASK') ? '' : $conf->global->PROPALE_JPSUN_MASK;
/**
		if (!$mask) {
			$this->error = 'NotConfigured';
			return 0;
		}
**/
		// Get entities
		$entity = getEntity('proposalnumber', 1, $propal);

		$date = empty($propal->date) ? dol_now() : $propal->date;
		
		//$mask1 = '{yyyy}{000@1}';
			
	    //$numFinal = get_next_value($db, $mask1, 'propal', 'ref', '', $objsoc, $date, 'next', '', null, $entity);
		
		$mask = '{yyyy}'.$user->array_options['options_jpsun_user_monogramme'].'{000@1}';
    
        $numFinal = get_next_value($db, $mask, 'propal', 'ref', '', $objsoc, $date, 'next', $objuser, null, $entity);
		
        //$numFinal .= ' '.substr($user->lastname, 0, 1);
		return  $numFinal;
	}
}
