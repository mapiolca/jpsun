<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) <year>  <name of author>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    htdocs/modulebuilder/template/class/actions_mymodule.class.php
 * \ingroup mymodule
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

/**
 * Class ActionsMyModule
 */
require_once __DIR__ . '/../backport/v19/core/class/commonhookactions.class.php';
class ActionsJpsun extends jpsun\RetroCompatCommonHookActions
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;
    /**
     * @var string Error
     */
    public $error = '';
    /**
     * @var array Errors
     */
    public $errors = array();


    /**
     * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public $results = array();

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints;

	/**
	 * @var array list of elements linked to a project
	 * used for projet/element.php customisation
	 */
	public $listofreferent;

	public $forecastProfitedPrinted = false;


    /**
     * Constructor
     *
     *  @param		DoliDB		$db      Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param   array()         $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function menuDropdownQuickaddItems($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $error = 0;

        //print_r($parameters);
        //echo "action: " . $action;
        //print_r($object);

        
        $this->results = array();
        $this->results[0] = array(
            "url"        => "/comm/action/card.php?action=create&mainmenu=agenda&leftmenu=agenda",
            "title"      => "AddEvent@agenda",               // ⚠️ format MODULE@FICHIER_LANG
            "name"       => "Event@agenda",               // idem
            "picto"      => "object_agenda",              // icône CSS
            "activation" => isModEnabled('agenda'),       // booléen pour affichage
            "position"   => 100                           // ordre
        );


        return 0;

    }


    public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $error = 0; // Error counter

        $contexts = explode(':',$parameters['context']);

        if (in_array('invoicecard',$contexts)) { // do something only for the context 'somecontext1' or 'somecontext2'
            
/**         if( $object->type == Facture::TYPE_SITUATION && (float) DOL_VERSION < 8.0){
            // pour les factures de situations on selectionne le modèle crabe_btp par défaut
            ?>
            <script type="text/javascript">
            $(document).ready(function(){
                if($('#model option[val=crabe_btp]').length > 0)
                {
                    $('#model option').each(function(){
                        if($(this).val() == 'crabe_btp') {
                            $(this).attr('selected',true);
                        } else {
                            $(this).attr('selected',false);
                        }
                    });
                }
            });
            </script>
<?php
            }
**/
        }

    }

    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param   array()         $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function doMassActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs, $db;

		$error = 0;
		$done = 0;
		$contexts = explode(':', $parameters['context']);
		$isCompatibleVersion = (defined('DOL_VERSION') && version_compare(DOL_VERSION, '24.0', '<'));
		$requestedAction = GETPOST('action', 'aZ09');
		$massactionFromHook = (isset($parameters['massaction']) && $parameters['massaction'] !== '' ? $parameters['massaction'] : '');
		$massactionFromRequest = GETPOST('massaction', 'aZ09');
		$toselect = GETPOST('toselect', 'array:int');
		if (empty($toselect)) {
			$toselectCsv = GETPOST('toselect', 'alphanohtml');
			if ($toselectCsv !== '') {
				$toselect = array_map('intval', explode(',', $toselectCsv));
			}
		}
		$allowedMassActions = array(
			'jpsun_cloturer_taches_projet',
			'jpsun_modifier_avancement_taches_projet',
			'jpsun_modifier_date_debut_taches_projet',
			'jpsun_modifier_echeance_taches_projet',
			'jpsun_modifier_charge_travail_prevue_taches_projet'
		);
		$massaction = '';
		if (in_array($requestedAction, $allowedMassActions, true)) {
			$massaction = $requestedAction;
		} elseif (in_array($massactionFromHook, $allowedMassActions, true)) {
			$massaction = $massactionFromHook;
		} elseif (in_array($massactionFromRequest, $allowedMassActions, true)) {
			$massaction = $massactionFromRequest;
		}

		if (!$isCompatibleVersion || (!in_array('tasklist', $contexts) && !in_array('projecttasklist', $contexts) && !in_array('projecttaskscard', $contexts))) {
			return 0;
		}

		if (!in_array($massaction, $allowedMassActions, true)) {
			return 0;
		}
		$langs->load('jpsun@jpsun');

		if (!$user->hasRight('projet', 'creer')) {
			setEventMessages($langs->trans('ErrorNotEnoughPermissions'), null, 'errors');
			return 0;
		}

		$toselect = array_unique(array_filter(array_map('intval', (array) $toselect)));
		if (empty($toselect)) {
			setEventMessages($langs->trans('NoRecordSelected'), null, 'warnings');
			return 0;
		}

		$tasksById = $this->getAuthorizedProjectTasks($toselect, $user, true);
		if (empty($tasksById)) {
			setEventMessages($langs->trans('NoRecordSelected'), null, 'warnings');
			return 0;
		}

		if ($massaction === 'jpsun_cloturer_taches_projet') {
			$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task AS t";
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet AS p ON p.rowid = t.fk_projet";
			$sql .= " SET t.progress = 100, t.fk_statut = 3";
			$sql .= " WHERE t.rowid IN (".implode(',', array_keys($tasksById)).")";
			$sql .= " AND p.entity IN (".getEntity('project').")";

			$resql = $db->query($sql);
			if (!$resql) {
				setEventMessages($db->lasterror(), null, 'errors');
				return 0;
			}

			$done = (int) $db->affected_rows($resql);
			setEventMessages($langs->trans('JpsunMassActionProjectTasksClosed', $done), null, 'mesgs');
			$action = 'list';
			return $error < 0 ? -1 : 0;
		}

		if ($massaction === 'jpsun_modifier_avancement_taches_projet') {
			$this->errors = array();
			foreach ($tasksById as $taskId => $taskObject) {
				$progressRaw = GETPOST('jpsun_task_progress_'.$taskId, 'alphanohtml');
				if ($progressRaw === '' || !is_numeric($progressRaw)) {
					$error++;
					$this->errors[] = $langs->trans('JpsunMassActionInvalidProgressValue', $taskId);
					continue;
				}
				$progress = max(0, min(100, (int) $progressRaw));
				$taskStatus = ($progress >= 100 ? 3 : 1);
				$updateSql = "UPDATE ".MAIN_DB_PREFIX."projet_task";
				$updateSql .= " SET progress = ".((int) $progress).", fk_statut = ".((int) $taskStatus);
				$updateSql .= " WHERE rowid = ".((int) $taskId);
				$updateRes = $db->query($updateSql);
				if (!$updateRes) {
					$error++;
					$this->errors[] = $db->lasterror();
				} else {
					$done++;
				}
			}

			if ($error) {
				setEventMessages('', $this->errors, 'errors');
			}
			if ($done > 0) {
				setEventMessages($langs->trans('JpsunMassActionProjectTasksProgressUpdated', $done), null, 'mesgs');
			} elseif (!$error) {
				setEventMessages($langs->trans('NoRecordSelected'), null, 'warnings');
			}
			$action = 'list';
			return $error < 0 ? -1 : 0;
		}

		if ($massaction === 'jpsun_modifier_charge_travail_prevue_taches_projet') {
			$this->errors = array();
			foreach ($tasksById as $taskId => $taskObject) {
				$workloadRaw = GETPOST('jpsun_task_planned_workload_'.$taskId, 'alphanohtml');
				if ($workloadRaw === '' || !is_numeric($workloadRaw)) {
					$error++;
					$this->errors[] = $langs->trans('JpsunMassActionInvalidPlannedWorkloadValue', $taskId);
					continue;
				}
				$workloadHours = price2num($workloadRaw, 'MS');
				if ($workloadHours < 0) {
					$error++;
					$this->errors[] = $langs->trans('JpsunMassActionInvalidPlannedWorkloadValue', $taskId);
					continue;
				}
				$workloadSeconds = (int) round($workloadHours * 3600);
				$updateSql = "UPDATE ".MAIN_DB_PREFIX."projet_task";
				$updateSql .= " SET planned_workload = ".((int) $workloadSeconds);
				$updateSql .= " WHERE rowid = ".((int) $taskId);
				$updateRes = $db->query($updateSql);
				if (!$updateRes) {
					$error++;
					$this->errors[] = $db->lasterror();
				} else {
					$done++;
				}
			}

			if ($error) {
				setEventMessages('', $this->errors, 'errors');
			}
			if ($done > 0) {
				setEventMessages($langs->trans('JpsunMassActionProjectTasksPlannedWorkloadUpdated', $done), null, 'mesgs');
			} elseif (!$error) {
				setEventMessages($langs->trans('NoRecordSelected'), null, 'warnings');
			}
			$action = 'list';
			return $error < 0 ? -1 : 0;
		}

		$globalTimestamp = 0;
		$globalDateDay = GETPOSTINT('jpsun_global_dateday');
		$globalDateMonth = GETPOSTINT('jpsun_global_datemonth');
		$globalDateYear = GETPOSTINT('jpsun_global_dateyear');
		if ($globalDateDay > 0 && $globalDateMonth > 0 && $globalDateYear > 0) {
			$globalTimestamp = dol_mktime(0, 0, 0, $globalDateMonth, $globalDateDay, $globalDateYear);
		}

		foreach ($tasksById as $taskId => $taskObject) {
			$setValues = array();
			$keepDuration = (GETPOSTINT('jpsun_keep_duration_'.$taskId) ? true : false);
			$taskTimestamp = 0;
			$taskDatetimeRaw = GETPOST('jpsun_task_datetime_'.$taskId, 'alphanohtml');
			if (!empty($taskDatetimeRaw)) {
				$taskTimestamp = (int) strtotime(str_replace('T', ' ', $taskDatetimeRaw));
			}
			if ($taskTimestamp <= 0 && $globalTimestamp > 0) {
				$taskTimestamp = $globalTimestamp;
			}
			if ($taskTimestamp <= 0) {
				$error++;
				$this->errors[] = $langs->trans('JpsunMassActionInvalidDateValue', $taskId);
				continue;
			}
			$oldStartTimestamp = !empty($taskObject->dateo) ? (int) $db->jdate($taskObject->dateo) : 0;
			$oldEndTimestamp = !empty($taskObject->datee) ? (int) $db->jdate($taskObject->datee) : 0;
			$durationSeconds = ($oldStartTimestamp > 0 && $oldEndTimestamp > 0 ? ($oldEndTimestamp - $oldStartTimestamp) : null);

			if ($massaction === 'jpsun_modifier_date_debut_taches_projet') {
				$setValues[] = "dateo = '".$db->idate($taskTimestamp)."'";
				if ($keepDuration && $durationSeconds !== null) {
					$setValues[] = "datee = '".$db->idate($taskTimestamp + $durationSeconds)."'";
				}
			} elseif ($massaction === 'jpsun_modifier_echeance_taches_projet') {
				$setValues[] = "datee = '".$db->idate($taskTimestamp)."'";
				if ($keepDuration && $durationSeconds !== null) {
					$setValues[] = "dateo = '".$db->idate($taskTimestamp - $durationSeconds)."'";
				}
			}

			if (empty($setValues)) {
				continue;
			}

			$updateSql = "UPDATE ".MAIN_DB_PREFIX."projet_task";
			$updateSql .= " SET ".implode(', ', $setValues);
			$updateSql .= " WHERE rowid = ".((int) $taskId);
			$updateRes = $db->query($updateSql);
			if (!$updateRes) {
				$error++;
				$this->errors[] = $db->lasterror();
			} else {
				$done++;
			}
		}

		if ($error) {
			setEventMessages($langs->trans('Error'), $this->errors, 'errors');
		}
		if ($done > 0) {
			if ($massaction === 'jpsun_modifier_date_debut_taches_projet') {
				setEventMessages($langs->trans('JpsunMassActionProjectTasksStartDateUpdated', $done), null, 'mesgs');
			} else {
				setEventMessages($langs->trans('JpsunMassActionProjectTasksDeadlineUpdated', $done), null, 'mesgs');
			}
		} elseif (!$error) {
			setEventMessages($langs->trans('NoRecordSelected'), null, 'warnings');
		}
		$action = 'list';

		return $error < 0 ? -1 : 0;
    }

	/**
	 * Overloading the doPreMassActions function: generate native Dolibarr pre-massaction popup.
	 *
	 * @param	array		$parameters		Hook metadata
	 * @param	CommonObject	$object		Current object
	 * @param	string		$action		Current action
	 * @param	HookManager	$hookmanager	Hook manager
	 * @return	int					0 on success, 1 if replacing standard code
	 */
	public function doPreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $form, $langs, $user;

		$contexts = explode(':', $parameters['context']);
		$isCompatibleVersion = (defined('DOL_VERSION') && version_compare(DOL_VERSION, '24.0', '<'));
		$massaction = (!empty($parameters['massaction']) ? $parameters['massaction'] : GETPOST('massaction', 'aZ09'));
		$toselect = GETPOST('toselect', 'array:int');
		if (empty($toselect) && !empty($parameters['toselect']) && is_array($parameters['toselect'])) {
			$toselect = array_map('intval', $parameters['toselect']);
		}
		$allowedPreMassActions = array(
			'prejpsun_modifier_avancement_taches_projet' => 'jpsun_modifier_avancement_taches_projet',
			'prejpsun_modifier_date_debut_taches_projet' => 'jpsun_modifier_date_debut_taches_projet',
			'prejpsun_modifier_echeance_taches_projet' => 'jpsun_modifier_echeance_taches_projet',
			'prejpsun_modifier_charge_travail_prevue_taches_projet' => 'jpsun_modifier_charge_travail_prevue_taches_projet'
		);

		if (!$isCompatibleVersion || (!in_array('tasklist', $contexts) && !in_array('projecttasklist', $contexts) && !in_array('projecttaskscard', $contexts))) {
			return 0;
		}
		if (!isset($allowedPreMassActions[$massaction])) {
			return 0;
		}
		if (!is_object($form)) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
			$form = new Form($db);
		}

		$langs->load('jpsun@jpsun');
		if (!$user->hasRight('projet', 'creer')) {
			setEventMessages($langs->trans('ErrorNotEnoughPermissions'), null, 'errors');
			return 0;
		}

		$toselect = array_unique(array_filter(array_map('intval', (array) $toselect)));
		$tasksById = $this->getAuthorizedProjectTasks($toselect, $user, true);
		if (empty($tasksById)) {
			setEventMessages($langs->trans('NoRecordSelected'), null, 'warnings');
			return 0;
		}

		$formquestion = array();
		$finalAction = $allowedPreMassActions[$massaction];
		$modalheight = 300;
		$modalwidth = 700;
		$modalWrapperId = '';
		$extraBodyHeight = 35;
		$contextpage = GETPOST('contextpage', 'aZ09');
		$formquestion[] = array('type' => 'hidden', 'name' => 'massaction', 'value' => $finalAction);
		if (!empty($contextpage)) {
			$formquestion[] = array('type' => 'hidden', 'name' => 'contextpage', 'value' => $contextpage);
		}
		$formquestion[] = array('type' => 'hidden', 'name' => 'toselect', 'value' => implode(',', array_keys($tasksById)));

		if ($finalAction === 'jpsun_modifier_avancement_taches_projet') {
			$progressInputNames = array();
			$progressRowCount = count($tasksById) + 2;
			$progressBodyHeight = min(600, max(220, 48 + ($progressRowCount * 32) + $extraBodyHeight));
			$modalheight = min(760, $progressBodyHeight + 170);
			$modalWrapperId = 'jpsun_massaction_progress_wrapper';
			$tableHtml = '<div id="'.$modalWrapperId.'" data-row-count="'.$progressRowCount.'" data-body-height="'.$progressBodyHeight.'"><table class="noborder centpercent">';
			$tableHtml .= '<tr class="liste_titre"><th>'.$langs->trans('Task').'</th><th class="right">'.$langs->trans('Progress').'</th></tr>';
			foreach ($tasksById as $taskId => $taskObject) {
				$inputName = 'jpsun_task_progress_'.$taskId;
				$progressInputNames[] = $inputName;
				$tableHtml .= '<tr><td>'.dol_escape_htmltag($taskObject->label ? $taskObject->label : $langs->trans('Task').' #'.$taskId).'</td>';
				$tableHtml .= '<td class="right"><input type="text" class="flat right width75" maxlength="6" id="'.dol_escape_htmltag($inputName).'" name="'.dol_escape_htmltag($inputName).'" value="'.((string) min(100, max(0, (int) $taskObject->progress))).'"> %</td></tr>';
			}
			$tableHtml .= '</table></div>';
			$formquestion[] = array(
				'type' => 'other',
				'name' => implode(',', $progressInputNames),
				'label' => '',
				'value' => $tableHtml
			);
			$title = $langs->trans('JpsunMassActionModifierAvancementTachesProjet');
		} elseif ($finalAction === 'jpsun_modifier_charge_travail_prevue_taches_projet') {
			$workloadInputNames = array();
			$workloadRowCount = count($tasksById) + 2;
			$workloadBodyHeight = min(600, max(220, 48 + ($workloadRowCount * 32) + $extraBodyHeight));
			$modalheight = min(760, $workloadBodyHeight + 170);
			$modalWrapperId = 'jpsun_massaction_workload_wrapper';
			$tableHtml = '<div id="'.$modalWrapperId.'" data-row-count="'.$workloadRowCount.'" data-body-height="'.$workloadBodyHeight.'"><table class="noborder centpercent">';
			$tableHtml .= '<tr class="liste_titre"><th>'.$langs->trans('Task').'</th><th class="right">'.$langs->trans('PlannedWorkload').'</th></tr>';
			foreach ($tasksById as $taskId => $taskObject) {
				$inputName = 'jpsun_task_planned_workload_'.$taskId;
				$workloadInputNames[] = $inputName;
				$currentWorkloadHours = (!empty($taskObject->planned_workload) ? price2num(((float) $taskObject->planned_workload / 3600), 'MS') : 0);
				$tableHtml .= '<tr><td>'.dol_escape_htmltag($taskObject->label ? $taskObject->label : $langs->trans('Task').' #'.$taskId).'</td>';
				$tableHtml .= '<td class="right"><input type="text" class="flat right width75" maxlength="12" id="'.dol_escape_htmltag($inputName).'" name="'.dol_escape_htmltag($inputName).'" value="'.dol_escape_htmltag((string) $currentWorkloadHours).'"> '.$langs->trans('HourShort').'</td></tr>';
			}
			$tableHtml .= '</table></div>';
			$formquestion[] = array(
				'type' => 'other',
				'name' => implode(',', $workloadInputNames),
				'label' => '',
				'value' => $tableHtml
			);
			$title = $langs->trans('JpsunMassActionModifierChargeTravailPrevueTachesProjet');
		} else {
			$dateInputNames = array();
			$keepDurationNames = array();
			$dateRowCount = count($tasksById) + 2;
			$dateBodyHeight = min(600, max(220, 48 + ($dateRowCount * 32) + $extraBodyHeight));
			$modalheight = min(760, $dateBodyHeight + 170);
			$modalWrapperId = 'jpsun_massaction_date_wrapper';
			$dateTableHtml = '<div id="'.$modalWrapperId.'" data-row-count="'.$dateRowCount.'" data-body-height="'.$dateBodyHeight.'"><table class="noborder centpercent">';
			$dateTableHtml .= '<tr class="liste_titre"><th>'.$langs->trans('Task').'</th><th class="center">'.$langs->trans('DateHour').'</th><th class="center">'.$langs->trans('JpsunMassActionKeepDuration').'</th></tr>';
			foreach ($tasksById as $taskId => $taskObject) {
				$datetimeName = 'jpsun_task_datetime_'.$taskId;
				$keepDurationName = 'jpsun_keep_duration_'.$taskId;
				$dateInputNames[] = $datetimeName;
				$keepDurationNames[] = $keepDurationName;
				$currentTimestamp = 0;
				if ($finalAction === 'jpsun_modifier_date_debut_taches_projet') {
					$currentTimestamp = (!empty($taskObject->dateo) ? (int) $db->jdate($taskObject->dateo) : 0);
				} else {
					$currentTimestamp = (!empty($taskObject->datee) ? (int) $db->jdate($taskObject->datee) : 0);
				}
				if ($currentTimestamp <= 0) {
					$currentTimestamp = dol_now();
				}
				$datetimeValue = dol_print_date($currentTimestamp, '%Y-%m-%dT%H:%M');
				$dateTableHtml .= '<tr><td>'.dol_escape_htmltag($taskObject->label ? $taskObject->label : $langs->trans('Task').' #'.$taskId).'</td>';
				$dateTableHtml .= '<td class="center"><input type="datetime-local" class="flat" id="'.dol_escape_htmltag($datetimeName).'" name="'.dol_escape_htmltag($datetimeName).'" value="'.dol_escape_htmltag($datetimeValue).'"></td>';
				$dateTableHtml .= '<td class="center"><input type="checkbox" class="flat" id="'.dol_escape_htmltag($keepDurationName).'" name="'.dol_escape_htmltag($keepDurationName).'" value="1"></td></tr>';
			}
			$dateTableHtml .= '</table></div>';
			$formquestion[] = array(
				'type' => 'other',
				'name' => implode(',', array_merge($dateInputNames, $keepDurationNames)),
				'label' => '',
				'value' => $dateTableHtml
			);
			$title = ($finalAction === 'jpsun_modifier_date_debut_taches_projet' ? $langs->trans('JpsunMassActionModifierDateDebutTachesProjet') : $langs->trans('JpsunMassActionModifierEcheanceTachesProjet'));
		}

		$pageUrl = $_SERVER['PHP_SELF'];
		$projectId = GETPOSTINT('id');
		if ($projectId > 0) {
			$pageUrl .= (strpos($pageUrl, '?') === false ? '?' : '&').'id='.$projectId;
		}
		$this->resprints = $form->formconfirm($pageUrl, $title, $langs->trans('JpsunMassActionPopupDescription'), $finalAction, $formquestion, '', 1, $modalheight, $modalwidth, 0, 'Validate', 'Cancel');
		if (!empty($modalWrapperId)) {
			$this->resprints .= '<script nonce="'.getNonce().'">
				(function() {
					var wrapperId = "'.dol_escape_js($modalWrapperId).'";
					function jpsunAdjustProgressDialogSize() {
						var wrapper = document.getElementById(wrapperId);
						if (!wrapper) return;
						var content = wrapper.closest(".ui-dialog-content");
						if (!content) return;
						var dialog = content.closest(".ui-dialog");
						if (!dialog) return;
							var rowCount = parseInt(wrapper.getAttribute("data-row-count"), 10) || 1;
							var expectedBodyHeight = parseInt(wrapper.getAttribute("data-body-height"), 10) || Math.max(220, 48 + (rowCount * 32) + 35);
						content.style.height = "auto";
						dialog.style.height = "auto";
						var nonContentHeight = Math.max(120, dialog.offsetHeight - content.offsetHeight);
						var requestedModalHeight = expectedBodyHeight + nonContentHeight;
						var maxModalHeight = Math.max(240, window.innerHeight - 100);
						var finalModalHeight = Math.min(requestedModalHeight, maxModalHeight);
						var finalBodyHeight = Math.max(140, finalModalHeight - nonContentHeight);
						content.style.height = finalBodyHeight + "px";
						content.style.maxHeight = finalBodyHeight + "px";
						content.style.overflowY = "auto";
						dialog.style.height = finalModalHeight + "px";
						dialog.style.maxHeight = finalModalHeight + "px";
						var viewportTop = window.pageYOffset || document.documentElement.scrollTop || 0;
						dialog.style.top = (viewportTop + Math.max(50, Math.floor((window.innerHeight - finalModalHeight) / 2))) + "px";
						if (window.jQuery) {
							var jqContent = window.jQuery(content);
							if (jqContent && jqContent.dialog) {
								jqContent.dialog("option", "position", { my: "center", at: "center", of: window });
							}
						}
					}
					jpsunAdjustProgressDialogSize();
					window.addEventListener("resize", jpsunAdjustProgressDialogSize);
					setTimeout(jpsunAdjustProgressDialogSize, 0);
				})();
			</script>';
		}
		return 1;
	}


    /**
     * Overloading the addMoreMassActions function : replacing the parent's function with the one below
     *
     * @param   array()         $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

		$error = 0;
		$contexts = explode(':', $parameters['context']);
		$isCompatibleVersion = (defined('DOL_VERSION') && version_compare(DOL_VERSION, '24.0', '<'));
		$canCloseTasks = $user->hasRight('projet', 'creer');
		$langs->load('jpsun@jpsun');

		if ($isCompatibleVersion && (in_array('tasklist', $contexts) || in_array('projecttasklist', $contexts) || in_array('projecttaskscard', $contexts))) {
			$label = $langs->trans('JpsunMassActionCloturerTachesProjet');
			$html = img_picto('', 'tick', '', false, 0, 0, '', 'pictofixedwidth').' '.$label;
			$this->resprints .= '<option value="jpsun_cloturer_taches_projet"'.($canCloseTasks ? '' : ' disabled="disabled"').' data-html="'.dol_escape_htmltag($html).'">'.$html.'</option>';
			$progressLabel = $langs->trans('JpsunMassActionModifierAvancementTachesProjet');
			$progressHtml = img_picto('', 'projecttask', '', false, 0, 0, '', 'pictofixedwidth').' '.$progressLabel;
			$this->resprints .= '<option value="prejpsun_modifier_avancement_taches_projet"'.($canCloseTasks ? '' : ' disabled="disabled"').' data-html="'.dol_escape_htmltag($progressHtml).'">'.$progressHtml.'</option>';
			$plannedWorkloadLabel = $langs->trans('JpsunMassActionModifierChargeTravailPrevueTachesProjet');
			$plannedWorkloadHtml = img_picto('', 'cron', '', false, 0, 0, '', 'pictofixedwidth').' '.$plannedWorkloadLabel;
			$this->resprints .= '<option value="prejpsun_modifier_charge_travail_prevue_taches_projet"'.($canCloseTasks ? '' : ' disabled="disabled"').' data-html="'.dol_escape_htmltag($plannedWorkloadHtml).'">'.$plannedWorkloadHtml.'</option>';
			$startDateLabel = $langs->trans('JpsunMassActionModifierDateDebutTachesProjet');
			$startDateHtml = img_picto('', 'calendar', '', false, 0, 0, '', 'pictofixedwidth').' '.$startDateLabel;
			$this->resprints .= '<option value="prejpsun_modifier_date_debut_taches_projet"'.($canCloseTasks ? '' : ' disabled="disabled"').' data-html="'.dol_escape_htmltag($startDateHtml).'">'.$startDateHtml.'</option>';
			$deadlineLabel = $langs->trans('JpsunMassActionModifierEcheanceTachesProjet');
			$deadlineHtml = img_picto('', 'calendar', '', false, 0, 0, '', 'pictofixedwidth').' '.$deadlineLabel;
			$this->resprints .= '<option value="prejpsun_modifier_echeance_taches_projet"'.($canCloseTasks ? '' : ' disabled="disabled"').' data-html="'.dol_escape_htmltag($deadlineHtml).'">'.$deadlineHtml.'</option>';
		}

		return $error < 0 ? -1 : 0;
    }

	/**
	 * Fetch authorized tasks for selected ids with project entity/user permission filters.
	 *
	 * @param	array	$toselect	List of selected task ids
	 * @param	User	$user		Current user
	 * @param	bool	$loadProgress	Load progress field when true
	 * @return	array				Array indexed by task id
	 */
	private function getAuthorizedProjectTasks($toselect, $user, $loadProgress = false)
	{
		global $db;

		$toselect = array_unique(array_filter(array_map('intval', (array) $toselect)));
		if (empty($toselect)) {
			return array();
		}

		dol_include_once('/projet/class/project.class.php');
		$projectStatic = new Project($db);
		$projectListFilter = '';
		if (!$user->hasRight('projet', 'all', 'lire')) {
			$projectsListId = $projectStatic->getProjectsAuthorizedForUser($user, 0, 1, 0);
			$projectListFilter = " AND p.rowid IN (".$db->sanitize($projectsListId ? $projectsListId : '0').")";
		}

		$sql = "SELECT t.rowid, t.label, t.dateo, t.datee, t.planned_workload";
		if ($loadProgress) {
			$sql .= ", t.progress";
		}
		$sql .= " FROM ".MAIN_DB_PREFIX."projet_task AS t";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet AS p ON p.rowid = t.fk_projet";
		$sql .= " WHERE t.rowid IN (".implode(',', $toselect).")";
		$sql .= " AND p.entity IN (".getEntity('project').")";
		$sql .= $projectListFilter;

		$resql = $db->query($sql);
		if (!$resql) {
			return array();
		}

		$tasksById = array();
		while ($obj = $db->fetch_object($resql)) {
			$tasksById[(int) $obj->rowid] = $obj;
		}

		return $tasksById;
	}

	public function completeListOfReferent($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		if (getDolGlobalInt('JPSUN_PROJECT_SHOW_FORECAST_PROFIT_BOARD')) $this->listofreferent = $parameters['listofreferent'];
	}

	public function printOverviewProfit($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

//		print 'lol';
		dol_include_once('../custom/jpsun/lib/jpsun.lib.php');

		if (getDolGlobalInt('JPSUN_PROJECT_SHOW_FORECAST_PROFIT_BOARD') && ! $this->forecastProfitedPrinted)
		{
			$this->listofreferent['propal']['margin'] = 'add';
			//$this->listofreferent['propal']['name'] = 'jpsun_Proposals';
            $this->listofreferent['propal']['tooltip'] = 'jpsun_ProposalsExcludingRefusedTooltip';
			$this->listofreferent['order']['margin'] = 'add';
            //$this->listofreferent['proposal_supplier']['name'] = 'SupplierProposalsExcludingRefused';
            $this->listofreferent['proposal_supplier']['tooltip'] = 'jpsun_SupplierProposalsExcludingRefusedTooltip';
            $this->listofreferent['proposal_supplier']['margin'] = 'minus';
			$this->listofreferent['order_supplier']['margin'] = 'minus';
			unset($this->listofreferent['invoice']['margin'], $this->listofreferent['invoice_supplier']['margin']);

			printForecastProfitBoard($object, $this->listofreferent, $parameters['dates'], $parameters['datee']);
			$this->forecastProfitedPrinted = true;
		}

		return 0;
	}

	/**
	 * Hook called by core/ajax/onlineSign.php
	 */
	public function AddSignature($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;
	
		$mode = GETPOST('mode', 'aZ09');
		if ($mode !== 'contract') return 0;
	
		$sourcefile     = $parameters['sourcefile'] ?? '';
		$newpdffilename = $parameters['newpdffilename'] ?? '';
		if (empty($sourcefile) || empty($newpdffilename)) return 0;
	
		if (!preg_match('/_signed-(\d{14})\.pdf$/', $newpdffilename, $m)) return 0;
		$date = $m[1];
	
		$upload_dir = dirname($sourcefile).'/';
		$sigpath = $upload_dir.'signatures/'.$date.'_signature.png';
	
		if (!dol_is_file($sourcefile) || !dol_is_file($sigpath)) return 0;
	
		$online_sign_name = GETPOST('onlinesignname', 'alphanohtml');
	
		$pdf = pdf_getInstance();
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($langs));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) $pdf->SetCompression(false);
	
		$pagecount = $pdf->setSourceFile($sourcefile);
	
		$targetPage = 8;
	
		// Coordonnées de ta box (tabSignature)
		$x = 66;
		$y = 150;
		$w = 70;
		$h = $w / 4;
	
		for ($i = 1; $i <= $pagecount; $i++) {
			$tpl = $pdf->importPage($i);
			$s = $pdf->getTemplatesize($tpl);
	
			// IMPORTANT: on force le format exact de la page importée
			$pdf->AddPage(($s['h'] > $s['w'] ? 'P' : 'L'), array($s['w'], $s['h']));
			$pdf->useTemplate($tpl);
	
			if ($i == $targetPage) {
				$pdf->Image($sigpath, $x, $y + 2, $w, $h);
	
				$pdf->SetFont(pdf_getPDFFont($langs), '', pdf_getPDFFontSize($langs) - 1);
				$pdf->SetTextColor(80, 80, 80);
				$pdf->SetXY($x, $y + 2 + $h + 1);
				$pdf->MultiCell($w, 4, $langs->trans("Signature").' : '.dol_print_date(dol_now(), "day", false, $langs, true).' - '.$online_sign_name, 0, 'L');
			}
		}
	
		$pdf->Output($newpdffilename, 'F');
	
		$object->indexFile($newpdffilename, 1);
	
		// CRUCIAL: si tu ne renvoies pas 1, Dolibarr refait son fallback page 15
		return 1;
	}

}






