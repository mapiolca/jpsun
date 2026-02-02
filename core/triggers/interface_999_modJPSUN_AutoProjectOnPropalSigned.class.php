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
 *	\class		InterfaceAutoProjectOnPropalSigned
 *	\brief		Class of trigger for JPSUN
 */
class InterfaceAutoProjectOnPropalSigned extends DolibarrTriggers
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
	    if ($action == 'PROPAL_MODIFY' || $action == 'LINEPROPAL_INSERT' || $action == 'LINEPROPAL_MODIFY' || $action == 'LINEPROPAL_DELETE') {
            if ($object->element == 'propal') {
                global $db;
            
                // Somme des lignes( extrafield_produit * qty ) / 1000
                $sql = "SELECT SUM(pd.qty * COALESCE(pe.jpsun_module_pv_pc, 0)) AS s
                        FROM ".MAIN_DB_PREFIX."propaldet pd
                        LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields pe
                               ON pe.fk_object = pd.fk_product
                        WHERE pd.fk_propal = ".$object->rowid;
            
                $resql = $db->query($sql);
                if ($resql) {
                    $obj = $db->fetch_object($resql);
                    $sum = ((float) ($obj->s ?? 0)) / 1000;
            
                    dol_include_once('/comm/propal/class/propal.class.php');
                    $propal = new Propal($db);
                    if ($propal->fetch($object->rowid) > 0) {
                        $propal->fetch_optionals(); // important
                        $propal->array_options['options_jpsun_pc_install'] = $sum;
                        $propal->insertExtraFields();
                    }
                } else {
                    dol_syslog(__METHOD__." SQL error: ".$db->lasterror(), LOG_ERR);
                }
            }
            
            if ($object->element === 'propaldet') {
                global $db;
            
                $fk_propal = (int) ($object->fk_propal ?? 0);
                if ($fk_propal <= 0) return 0;
            
                // Somme des lignes( extrafield_produit * qty ) / 1000
                $sql = "SELECT SUM(pd.qty * COALESCE(pe.jpsun_module_pv_pc, 0)) AS s
                        FROM ".MAIN_DB_PREFIX."propaldet pd
                        LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields pe
                               ON pe.fk_object = pd.fk_product
                        WHERE pd.fk_propal = ".$fk_propal;
            
                $resql = $db->query($sql);
                if ($resql) {
                    $obj = $db->fetch_object($resql);
                    $sum = ((float) ($obj->s ?? 0)) / 1000;
            
                    dol_include_once('/comm/propal/class/propal.class.php');
                    $propal = new Propal($db);
                    if ($propal->fetch($fk_propal) > 0) {
                        $propal->fetch_optionals(); // important
                        $propal->array_options['options_jpsun_pc_install'] = $sum;
                        $propal->insertExtraFields();
                    }
                } else {
                    dol_syslog(__METHOD__." SQL error: ".$db->lasterror(), LOG_ERR);
                }
            }
        }
            
		// EN: Ensure we only handle proposal signature event
		// FR: S'assurer que l'on traite uniquement la signature du devis
		if ($action == 'PROPAL_CLOSE_SIGNED') {
		    
		    // EN: Check expected object type // EN: Stop if proposal not already has a native project link // EN: Stop if project module is enabled
    		// FR: Vérifier le type d'objet attendu // FR: Stopper si le devis n'a pas déjà un projet natif // FR: Stopper si le module projet est sactivé
    		if (($object instanceof Propal) && empty($object->fk_projet) && (int) $object->fk_projet > 0 && isModEnabled('project') && getDolGlobalInt('JPSUN_AUTOPROJECT_ON_PROPAL_SIGNED')) {
    		
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
        		// EN: Build title with thirdparty name and customer reference
        		// FR: Construire le libellé avec le nom du client et la référence client
        		$titleParts = array();
        		if (!empty($object->thirdparty) && !empty($object->thirdparty->name)) {
        			$titleParts[] = $object->thirdparty->name;
        		}
        		if (!empty($object->ref_client)) {
        			$titleParts[] = $object->ref_client;
        		}
        		$project->title = implode(' - ', $titleParts);
        		if (empty($project->title)) {
        			$project->title = !empty($object->title) ? $object->title : $object->ref;
        		}
        		$project->description = $object->note_public;
        		$project->status = Project::STATUS_VALIDATED;
        		$project->statut = Project::STATUS_VALIDATED;
        
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
        
        		// EN: Build project reference using numbering module
        		// FR: Générer la référence projet via le module de numérotation
        		$obj = getDolGlobalString('PROJECT_ADDON', 'mod_project_simple');
        		$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
        		$defaultref = '';
        		$filefound = '';
        		foreach ($dirmodels as $reldir) {
        			$file = dol_buildpath($reldir.'core/modules/project/'.$obj.'.php', 0);
        			if (file_exists($file)) {
        				$filefound = $file;
        				dol_include_once($reldir.'core/modules/project/'.$obj.'.php');
        				$modProject = new $obj();
        				'@phan-var-force ModeleNumRefProjects $modProject';
        				$project->date_c = dol_now();
        				$defaultref = $modProject->getNextValue(is_object($object->thirdparty) ? $object->thirdparty : null, $project);
        				break;
        			}
        		}
        		dol_syslog('JPSUN AutoProject: numbering model='.$obj.' file='.$filefound.' ref='.$defaultref, LOG_DEBUG);
        		if (empty($defaultref) || (is_numeric($defaultref) && $defaultref <= 0)) {
        			// EN: Fallback to a unique provisional ref to avoid failure
        			// FR: Repli sur une référence provisoire unique pour éviter l'échec
        			$project->ref = '(PROV-'.$object->id.')';
        			dol_syslog('JPSUN AutoProject: numbering failed, fallback ref='.$project->ref, LOG_WARNING);
        		} else {
        			$project->ref = $defaultref;
        		}
        		dol_syslog(
        			'JPSUN AutoProject: creating project from propal id='.$object->id
        			.' using PROJECT_ADDON='.getDolGlobalString('PROJECT_ADDON')
        			.' ref='.$project->ref.' title='.$project->title.' socid='.$project->socid.' entity='.$project->entity,
        			LOG_DEBUG
        		);
        		$res = $project->create($user);
        		if ($res <= 0) {
        			$this->error = $project->error;
        			$this->errors = $project->errors;
        			dol_syslog('JPSUN AutoProject: project->create failed: '.$project->error.' ref='.$project->ref.' title='.$project->title.' socid='.$project->socid, LOG_ERR);
        			dol_syslog($langs->trans('JpsunPropalSignedProjectCreateError', $object->ref, $object->id, $this->error), LOG_ERR);
        			return -1;
        		}
        
        		$resLink = $project->update_element('propal', $object->id);
        		if ($resLink < 0) {
        			$this->error = $langs->trans('JpsunPropalSignedProjectLinkError', $object->ref, $object->id, $project->id);
        			$this->errors[] = $this->error;
        			dol_syslog('JPSUN AutoProject: failed to set fk_projet on propal id='.$object->id.' project id='.$project->id.' : '.$project->error.' '.$this->db->lasterror(), LOG_ERR);
        			return -1;
        		}
        		if ($resLink > 0) {
        			$object->fk_projet = $project->id;
        		}
        		dol_syslog('JPSUN AutoProject: linked propal id='.$object->id.' to project id='.$project->id.' (fk_projet updated)', LOG_INFO);
        
        		$project->add_object_linked('propal', $object->id, $user);
        
        		$linkedOrders = 0;
        		$object->fetchObjectLinked($object->id, $object->element, null, 'commande', 'OR', 0, 'sourcetype', 0);
        		$linkedOrderIds = array();
        		if (!empty($object->linkedObjectsIds['commande'])) {
        			$linkedOrderIds = $object->linkedObjectsIds['commande'];
        		}
        
        		// EN: Link auto-created order when workflow is enabled
        		// FR: Lier la commande auto-créée si le workflow est activé
        		if (getDolGlobalInt('WORKFLOW_PROPAL_AUTOCREATE_ORDER') && !empty($linkedOrderIds)) {
        			foreach ($linkedOrderIds as $idcommande) {
        				$resOrderLink = $project->update_element('commande', $idcommande);
        				if ($resOrderLink < 0) {
        					$this->error = $langs->trans('JpsunPropalSignedProjectOrderLinkError', $object->ref, $object->id, $idcommande);
        					$this->errors[] = $this->error;
        					dol_syslog('JPSUN AutoProject: failed to set fk_projet on order id='.$idcommande.' project id='.$project->id.' : '.$project->error.' '.$this->db->lasterror(), LOG_ERR);
        					return -1;
        				}
        			}
        			dol_syslog('JPSUN AutoProject: linked orders to project id='.$project->id.' via workflow', LOG_INFO);
        		}
        
        		$project->add_object_linked('propal', $object->id, $user);
        
        		if (!empty($linkedOrderIds)) {
        			foreach ($linkedOrderIds as $idcommande) {
        				$project->add_object_linked('commande', $idcommande, $user);
        				$linkedOrders++;
        			}
        		}
        
        		dol_syslog($langs->trans('JpsunPropalSignedProjectCreated', $object->ref, $object->id, $project->id, $project->ref), LOG_INFO);
        		dol_syslog($langs->trans('JpsunPropalSignedProjectCreatedDetails', $copiedExtraFields, $linkedOrders), LOG_INFO);
        		// EN: Notify user about project creation
        		// FR: Notifier l'utilisateur de la création du projet
        		setEventMessage($langs->trans('JpsunPropalSignedProjectCreated', $object->ref, $object->id, $project->id, $project->ref));
    
    			return 0;
    		}
		}

		return 1;
	}
}
