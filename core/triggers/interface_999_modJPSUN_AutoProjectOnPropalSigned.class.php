<?php
/* Copyright (C) 2025	Pierre Ardoin		<developpeur@lesmetiersdubatiment.fr>
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
 *	\file		core/triggers/interface_999_modJPSUN_AutoProjectOnPropalSigned.class.php
 *	\ingroup	jpsun
 *	\brief		Trigger file for JPSUN
 */

dol_include_once('/core/triggers/dolibarrtriggers.class.php');

/**
 *	\class		InterfaceJpsunAutoProjectOnPropalSigned
 *	\brief		Class of trigger for JPSUN
 */
class InterfaceJpsunAutoProjectOnPropalSigned extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs;

		$this->db = $db;

		$langs->loadLangs(array('jpsun@jpsun'));

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'jpsun';
		$this->description = $langs->trans('JpsunTriggerAutoProjectOnPropalSignedDesc');
		$this->version = 'dolibarr';
		$this->picto = 'jpsun@jpsun';
	}

	/**
	 * Function called when a Dolibarr business event is done.
	 *
	 * @param string     $action Trigger action code
	 * @param Object     $object Object
	 * @param User       $user   Object user
	 * @param Translate  $langs  Object langs
	 * @param Conf       $conf   Object conf
	 * @return int                Return integer <0 if KO, 0 if no action is done, >0 if OK
	 */
	public function runTrigger($action, $object, $user, $langs, $conf)
	{
		// EN: Ensure we only handle proposal signature event
		// FR: S'assurer que l'on traite uniquement la signature du devis
		if ($action !== 'PROPAL_CLOSE_SIGNED') {
			return 0;
		}

		// EN: Check expected object type
		// FR: Vérifier le type d'objet attendu
		if (!($object instanceof Propal)) {
			return 0;
		}

		// EN: Stop if project module is disabled
		// FR: Stopper si le module projet est désactivé
		if (!isModEnabled('project')) {
			return 0;
		}

		$langs->loadLangs(array('jpsun@jpsun'));

		// EN: Avoid duplicates when a project is already linked
		// FR: Éviter les doublons lorsqu'un projet est déjà lié
		$object->fetchObjectLinked($object->id, $object->element, null, 'project', 'OR', 0, 'sourcetype', 0);
		if (!empty($object->linkedObjectsIds['project'])) {
			dol_syslog($langs->trans('JpsunPropalSignedProjectAlreadyLinked', $object->ref, $object->id), LOG_INFO);
			return 0;
		}

		// EN: Load required classes
		// FR: Charger les classes nécessaires
		dol_include_once('/projet/class/project.class.php');
		dol_include_once('/core/class/extrafields.class.php');

		// EN: Ensure thirdparty data is available for naming and numbering
		// FR: S'assurer que les données du tiers sont disponibles pour le nommage et la numérotation
		if (empty($object->thirdparty) && !empty($object->socid)) {
			$object->fetch_thirdparty();
		}

		$project = new Project($this->db);
		$project->socid = $object->socid;
		$project->title = !empty($object->title) ? $object->title : trim($object->ref.' - '.(empty($object->thirdparty) ? '' : $object->thirdparty->name));
		if (empty($project->title)) {
			$project->title = $object->ref;
		}
		$project->description = $object->note_public;
		$project->status = Project::STATUS_VALIDATED;
		$project->statut = Project::STATUS_VALIDATED;

		// EN: Generate reference using the project numbering module
		// FR: Générer la référence via le module de numérotation des projets
		$defaultref = getDolGlobalString('PROJECT_ADDON', 'mod_project_simple');
		$filefound = '';
		if (!empty($conf->modules_parts['models'])) {
			foreach ($conf->modules_parts['models'] as $reldir) {
				$file = dol_buildpath($reldir.'project/'.$defaultref.'.php', 0);
				if (file_exists($file)) {
					$filefound = $file;
					break;
				}
			}
		}
		if (empty($filefound)) {
			$filefound = DOL_DOCUMENT_ROOT.'/core/modules/project/'.$defaultref.'.php';
		}
		if (!file_exists($filefound)) {
			dol_syslog($langs->trans('JpsunPropalSignedProjectNoRef', $object->ref, $object->id), LOG_ERR);
			return -1;
		}
		dol_include_once($filefound);
		$classname = $defaultref;
		$modProject = new $classname($this->db);
		$project->ref = $modProject->getNextValue($object->thirdparty, $project);
		if (empty($project->ref)) {
			dol_syslog($langs->trans('JpsunPropalSignedProjectNoRef', $object->ref, $object->id), LOG_ERR);
			return -1;
		}

		// EN: Copy extrafields with matching codes
		// FR: Copier les extrachamps avec les mêmes codes
		$extrafields = new ExtraFields($this->db);
		$extrafields->fetch_name_optionals_label($project->table_element);
		$object->fetch_optionals();
		$project->array_options = array();
		$copiedExtraFields = 0;
		if (!empty($object->array_options) && !empty($extrafields->attributes[$project->table_element]['label'])) {
			foreach ($object->array_options as $key => $value) {
				if (strpos($key, 'options_') !== 0) {
					continue;
				}
				$extraCode = substr($key, 8);
				if (array_key_exists($extraCode, $extrafields->attributes[$project->table_element]['label'])) {
					$project->array_options[$key] = $value;
					$copiedExtraFields++;
				}
			}
		}

		$res = $project->create($user);
		if ($res <= 0) {
			$this->error = $project->error;
			$this->errors = $project->errors;
			dol_syslog($langs->trans('JpsunPropalSignedProjectCreateError', $object->ref, $object->id, $this->error), LOG_ERR);
			return -1;
		}

		$project->add_object_linked('propal', $object->id, $user);

		$linkedOrders = 0;
		$object->fetchObjectLinked($object->id, $object->element, null, 'commande', 'OR', 0, 'sourcetype', 0);
		if (!empty($object->linkedObjectsIds['commande'])) {
			foreach ($object->linkedObjectsIds['commande'] as $idcommande) {
				$project->add_object_linked('commande', $idcommande, $user);
				$linkedOrders++;
			}
		}

		dol_syslog($langs->trans('JpsunPropalSignedProjectCreated', $object->ref, $object->id, $project->id, $copiedExtraFields, $linkedOrders), LOG_INFO);

		return 1;
	}
}
