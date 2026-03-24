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
	public $massActionTaskScriptPrinted = false;


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
		$massaction = GETPOST('massaction', 'aZ09');
		$toselect = GETPOST('toselect', 'array:int');

		if (!$isCompatibleVersion || !in_array('tasklist', $contexts) && !in_array('projecttaskscard', $contexts)) {
			return 0;
		}

		if (!in_array($massaction, array('jpsun_cloturer_taches_projet', 'jpsun_modifier_avancement', 'jpsun_modifier_date_debut', 'jpsun_modifier_echeance'))) {
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

		$tasks = $this->getAllowedSelectedTasks($toselect, $user);
		if (empty($tasks)) {
			setEventMessages($langs->trans('NoRecordSelected'), null, 'warnings');
			return 0;
		}

		if ($massaction === 'jpsun_cloturer_taches_projet') {
			$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task";
			$sql .= " SET progress = 100, fk_statut = 3";
			$sql .= " WHERE rowid IN (".implode(',', array_map('intval', array_keys($tasks))).")";

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

		if ($massaction === 'jpsun_modifier_avancement') {
			$progressByTask = GETPOST('jpsun_progress', 'array');
			foreach ($tasks as $taskid => $taskdata) {
				if (!isset($progressByTask[$taskid]) || $progressByTask[$taskid] === '') {
					continue;
				}
				$progress = max(0, min(100, (int) $progressByTask[$taskid]));
				$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task SET progress = ".$progress." WHERE rowid = ".((int) $taskid);
				$resql = $db->query($sql);
				if ($resql) {
					$done++;
				}
			}
			setEventMessages($langs->trans('JpsunMassActionProjectTasksProgressUpdated', $done), null, 'mesgs');
			$action = 'list';
			return $error < 0 ? -1 : 0;
		}

		$globalDate = GETPOST('jpsun_global_date', 'alphanohtml');
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $globalDate)) {
			setEventMessages($langs->trans('JpsunMassActionInvalidDate'), null, 'errors');
			return 0;
		}

		$tmp = explode('-', $globalDate);
		$newBaseTs = dol_mktime(0, 0, 0, (int) $tmp[1], (int) $tmp[2], (int) $tmp[0], false, 'tzserver');
		$keepDuration = GETPOST('jpsun_keep_duration', 'array');

		foreach ($tasks as $taskid => $taskdata) {
			$dateo = (int) $taskdata['dateo'];
			$datee = (int) $taskdata['datee'];
			$duration = ($dateo > 0 && $datee > 0) ? ($datee - $dateo) : 0;
			$keep = !empty($keepDuration[$taskid]);
			$newStart = $dateo;
			$newEnd = $datee;

			if ($massaction === 'jpsun_modifier_date_debut') {
				$newStart = $newBaseTs;
				if ($keep && $duration > 0) {
					$newEnd = $newStart + $duration;
				}
			}

			if ($massaction === 'jpsun_modifier_echeance') {
				$newEnd = $newBaseTs;
				if ($keep && $duration > 0) {
					$newStart = $newEnd - $duration;
				}
			}

			$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task";
			$sql .= " SET dateo = ".($newStart > 0 ? "'".$db->idate($newStart)."'" : "NULL");
			$sql .= ", datee = ".($newEnd > 0 ? "'".$db->idate($newEnd)."'" : "NULL");
			$sql .= " WHERE rowid = ".((int) $taskid);
			$resql = $db->query($sql);
			if ($resql) {
				$done++;
			}
		}

		if ($massaction === 'jpsun_modifier_date_debut') {
			setEventMessages($langs->trans('JpsunMassActionProjectTasksStartDateUpdated', $done), null, 'mesgs');
		}
		if ($massaction === 'jpsun_modifier_echeance') {
			setEventMessages($langs->trans('JpsunMassActionProjectTasksEndDateUpdated', $done), null, 'mesgs');
		}
		$action = 'list';

		return $error < 0 ? -1 : 0;
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

		if ($isCompatibleVersion && (in_array('tasklist', $contexts) || in_array('projecttaskscard', $contexts))) {
			$label = $langs->trans('JpsunMassActionCloturerTachesProjet');
			$html = img_picto('', 'tick', '', false, 0, 0, '', 'pictofixedwidth').' '.$label;
			$this->resprints .= '<option value="jpsun_cloturer_taches_projet"'.($canCloseTasks ? '' : ' disabled="disabled"').' data-html="'.dol_escape_htmltag($html).'">'.$html.'</option>';

			$label = $langs->trans('JpsunMassActionModifierAvancement');
			$html = img_picto('', 'projecttask', '', false, 0, 0, '', 'pictofixedwidth').' '.$label;
			$this->resprints .= '<option value="jpsun_modifier_avancement"'.($canCloseTasks ? '' : ' disabled="disabled"').' data-html="'.dol_escape_htmltag($html).'">'.$html.'</option>';

			$label = $langs->trans('JpsunMassActionModifierDateDebut');
			$html = img_picto('', 'calendar', '', false, 0, 0, '', 'pictofixedwidth').' '.$label;
			$this->resprints .= '<option value="jpsun_modifier_date_debut"'.($canCloseTasks ? '' : ' disabled="disabled"').' data-html="'.dol_escape_htmltag($html).'">'.$html.'</option>';

			$label = $langs->trans('JpsunMassActionModifierEcheance');
			$html = img_picto('', 'calendar', '', false, 0, 0, '', 'pictofixedwidth').' '.$label;
			$this->resprints .= '<option value="jpsun_modifier_echeance"'.($canCloseTasks ? '' : ' disabled="disabled"').' data-html="'.dol_escape_htmltag($html).'">'.$html.'</option>';
		}

		return $error < 0 ? -1 : 0;
    }

	public function printFieldPreListTitle($parameters, &$object, &$action, $hookmanager)
	{
		return $this->renderMassActionTaskPopupScript($parameters);
	}

	public function printFieldListTitle($parameters, &$object, &$action, $hookmanager)
	{
		return $this->renderMassActionTaskPopupScript($parameters);
	}

	private function renderMassActionTaskPopupScript($parameters)
	{
		global $langs;

		if ($this->massActionTaskScriptPrinted) {
			return 0;
		}

		$contexts = explode(':', $parameters['context']);
		$isCompatibleVersion = (defined('DOL_VERSION') && version_compare(DOL_VERSION, '24.0', '<'));
		if (!$isCompatibleVersion || !in_array('tasklist', $contexts) && !in_array('projecttaskscard', $contexts)) {
			return 0;
		}

		$langs->load('jpsun@jpsun');
		$promptProgress = json_encode($langs->trans('JpsunMassActionPromptTaskProgress', '__ID__'));
		$promptStart = json_encode($langs->trans('JpsunMassActionPromptGlobalDateStart'));
		$promptEnd = json_encode($langs->trans('JpsunMassActionPromptGlobalDateEnd'));
		$confirmKeepStart = json_encode($langs->trans('JpsunMassActionPromptKeepDurationStart', '__ID__'));
		$confirmKeepEnd = json_encode($langs->trans('JpsunMassActionPromptKeepDurationEnd', '__ID__'));

		$this->resprints .= '<script>
document.addEventListener("DOMContentLoaded", function () {
	var massactionSelect = document.querySelector("select[name=\'massaction\']");
	if (!massactionSelect) return;
	var massForm = massactionSelect.closest("form");
	if (!massForm) return;

	function addHidden(name, value) {
		var input = document.createElement("input");
		input.type = "hidden";
		input.name = name;
		input.value = value;
		massForm.appendChild(input);
	}

	function clearPrevious(prefix) {
		var nodes = massForm.querySelectorAll("input[name^=\'"+prefix+"\']");
		nodes.forEach(function (node) { node.parentNode.removeChild(node); });
	}

	massForm.addEventListener("submit", function (event) {
		var action = massactionSelect.value;
		if (["jpsun_modifier_avancement", "jpsun_modifier_date_debut", "jpsun_modifier_echeance"].indexOf(action) === -1) return;

		var selected = [];
		massForm.querySelectorAll("input[name=\'toselect[]\']:checked").forEach(function (checkbox) {
			selected.push(checkbox.value);
		});
		if (!selected.length) return;

		clearPrevious("jpsun_progress");
		clearPrevious("jpsun_keep_duration");
		clearPrevious("jpsun_global_date");

		if (action === "jpsun_modifier_avancement") {
			for (var i = 0; i < selected.length; i++) {
				var taskid = selected[i];
				var label = '.$promptProgress.'.replace("__ID__", taskid);
				var value = window.prompt(label, "100");
				if (value === null) { event.preventDefault(); return false; }
				addHidden("jpsun_progress["+taskid+"]", value);
			}
			return;
		}

		var promptDate = (action === "jpsun_modifier_date_debut") ? '.$promptStart.' : '.$promptEnd.';
		var dateValue = window.prompt(promptDate, "");
		if (dateValue === null || !dateValue) { event.preventDefault(); return false; }
		addHidden("jpsun_global_date", dateValue);

		for (var j = 0; j < selected.length; j++) {
			var selectedTask = selected[j];
			var keepLabel = (action === "jpsun_modifier_date_debut" ? '.$confirmKeepStart.' : '.$confirmKeepEnd.').replace("__ID__", selectedTask);
			if (window.confirm(keepLabel)) {
				addHidden("jpsun_keep_duration["+selectedTask+"]", "1");
			}
		}
	});
});
</script>';

		$this->massActionTaskScriptPrinted = true;
		return 0;
	}

	private function getAllowedSelectedTasks($toselect, $user)
	{
		global $db;

		dol_include_once('/projet/class/project.class.php');
		$projectStatic = new Project($db);

		$toselect = array_unique(array_filter(array_map('intval', (array) $toselect)));
		if (empty($toselect)) {
			return array();
		}

		$sql = "SELECT t.rowid, t.dateo, t.datee";
		$sql .= " FROM ".MAIN_DB_PREFIX."projet_task as t";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet as p ON p.rowid = t.fk_projet";
		$sql .= " WHERE t.rowid IN (".implode(',', $toselect).")";
		$sql .= " AND p.entity IN (".getEntity('project').")";

		if (!$user->hasRight('projet', 'all', 'lire')) {
			$projectsListId = $projectStatic->getProjectsAuthorizedForUser($user, 0, 1, 0);
			$sql .= " AND p.rowid IN (".$db->sanitize($projectsListId ? $projectsListId : '0').")";
		}

		$resql = $db->query($sql);
		if (!$resql) {
			return array();
		}

		$tasks = array();
		while ($obj = $db->fetch_object($resql)) {
			$tasks[(int) $obj->rowid] = array(
				'dateo' => $db->jdate($obj->dateo),
				'datee' => $db->jdate($obj->datee),
			);
		}

		return $tasks;
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






