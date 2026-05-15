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
	 * Hook called before standard object actions.
	 *
	 * Used on proposal signature to attach the proposal to an existing project
	 * before Dolibarr closes/signs it, so the auto-project trigger does not
	 * create a duplicate.
	 *
	 * @param	array		$parameters		Hook metadata
	 * @param	CommonObject	$object		Current object
	 * @param	string		$action		Current action
	 * @param	HookManager	$hookmanager	Hook manager
	 * @return	int					<0 on error, 0 on success
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		$contexts = explode(':', $parameters['context']);
		if (!in_array('propalcard', $contexts, true)) {
			return 0;
		}
		if (!isModEnabled('project') || !getDolGlobalInt('JPSUN_AUTOPROJECT_ON_PROPAL_SIGNED')) {
			return 0;
		}
		if (empty($object) || empty($object->id) || empty($object->socid) || $this->propalAlreadyLinkedToProject($object)) {
			return 0;
		}
		if (GETPOST('confirm', 'alpha') !== 'yes' || GETPOST('cancel', 'alpha')) {
			return 0;
		}

		$isSigningAction = false;
		if ($action === 'confirm_closeas') {
			$isSigningAction = (GETPOSTINT('statut') === (int) $object::STATUS_SIGNED);
		} elseif ($action === 'confirm_validate' && getDolGlobalInt('PROPAL_SKIP_ACCEPT_REFUSE')) {
			$isSigningAction = true;
		}

		if (!$isSigningAction) {
			return 0;
		}

		$choice = GETPOST('jpsun_autoproject_guard_choice', 'aZ09');
		if ($choice !== 'existing') {
			return 0;
		}

		$langs->load('jpsun@jpsun');
		$projectId = GETPOSTINT('jpsun_autoproject_existing_project_id');
		$project = $this->fetchAutoProjectGuardProject($projectId, (int) $object->socid, $user);
		if (!is_object($project) || empty($project->id)) {
			$this->error = $langs->trans('JpsunAutoProjectGuardInvalidProject');
			$this->errors[] = $this->error;
			setEventMessages($this->error, null, 'errors');
			return -1;
		}

		$result = $this->linkPropalToExistingProject($object, $project, $user, $langs);
		if ($result < 0) {
			setEventMessages($this->error, $this->errors, 'errors');
			return -1;
		}

		setEventMessages($langs->trans('JpsunAutoProjectGuardProjectLinked', $object->ref, $project->ref), null, 'mesgs');
		return 0;
	}

	/**
	 * Hook called after Dolibarr built a confirmation modal.
	 *
	 * @param	array		$parameters		Hook metadata
	 * @param	CommonObject	$object		Current object
	 * @param	string		$action		Current action
	 * @param	HookManager	$hookmanager	Hook manager
	 * @return	int					0 to append, 1 to replace
	 */
	public function formConfirm($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $form, $langs, $user;

		$contexts = explode(':', $parameters['context']);
		if (!in_array('propalcard', $contexts, true)) {
			return 0;
		}
		if (!isModEnabled('project') || !getDolGlobalInt('JPSUN_AUTOPROJECT_ON_PROPAL_SIGNED')) {
			return 0;
		}
		if (empty($parameters['formConfirm']) || empty($object) || empty($object->id) || empty($object->socid) || $this->propalAlreadyLinkedToProject($object)) {
			return 0;
		}

		$projects = $this->getCustomerProjectsForAutoProjectGuard((int) $object->socid, $user);
		if (empty($projects)) {
			return 0;
		}
		if (!is_object($form)) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
			$form = new Form($db);
		}

		$langs->loadLangs(array('jpsun@jpsun', 'propal'));

		if ($action === 'closeas' && !getDolGlobalInt('PROPAL_SKIP_ACCEPT_REFUSE')) {
			$formquestion = array();
			$formquestion[] = array(
				'type' => 'select',
				'name' => 'statut',
				'label' => '<span class="fieldrequired">'.$langs->trans('CloseAs').'</span>',
				'values' => array(
					$object::STATUS_SIGNED => $object->LibStatut($object::STATUS_SIGNED),
					$object::STATUS_NOTSIGNED => $object->LibStatut($object::STATUS_NOTSIGNED)
				)
			);
			$formquestion[] = array('type' => 'text', 'name' => 'note_private', 'label' => $langs->trans('Note'), 'value' => '');

			$this->addDepositSuggestionFormQuestions($formquestion, $object);
			$formquestion[] = $this->buildAutoProjectGuardQuestion($projects);

			if (isModEnabled('notification')) {
				require_once DOL_DOCUMENT_ROOT.'/core/class/notify.class.php';
				$notify = new Notify($db);
				$formquestion[] = array('type' => 'onecolumn', 'value' => $notify->confirmMessage('PROPAL_CLOSE_SIGNED', $object->socid, $object));
			}
			$formquestion[] = array('type' => 'onecolumn', 'value' => $this->buildAutoProjectGuardScript((int) $object::STATUS_SIGNED, true));

			$this->resprints = $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('SetAcceptedRefused'), '', 'confirm_closeas', $formquestion, '', 1, 520, 700);
			return 1;
		}

		if ($action === 'validate' && getDolGlobalInt('PROPAL_SKIP_ACCEPT_REFUSE')) {
			$text = $this->buildValidateProposalConfirmText($object);
			$formquestion = array(
				$this->buildAutoProjectGuardQuestion($projects),
				array('type' => 'onecolumn', 'value' => $this->buildAutoProjectGuardScript((int) $object::STATUS_SIGNED, false))
			);
			$this->resprints = $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('ValidateProp'), $text, 'confirm_validate', $formquestion, 0, 1, 420, 700);
			return 1;
		}

		return 0;
	}

	/**
	 * Add the native Dolibarr deposit suggestion fields to the rebuilt close modal.
	 *
	 * @param	array		$formquestion	Form question array
	 * @param	CommonObject	$object		Proposal object
	 * @return	void
	 */
	private function addDepositSuggestionFormQuestions(&$formquestion, $object)
	{
		global $form, $user;

		if (!getDolGlobalInt('PROPOSAL_SUGGEST_DOWN_PAYMENT_INVOICE_CREATION')) {
			return;
		}

		$depositPercentFromPaymentTerms = getDictionaryValue('c_payment_term', 'deposit_percent', $object->cond_reglement_id);
		if (empty($depositPercentFromPaymentTerms) || !isModEnabled('invoice') || !$user->hasRight('facture', 'creer')) {
			return;
		}

		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

		$object->fetchObjectLinked();
		$eligibleForDepositGeneration = true;
		if (!empty($object->linkedObjects['facture'])) {
			foreach ($object->linkedObjects['facture'] as $invoice) {
				if ($invoice->type == Facture::TYPE_DEPOSIT) {
					$eligibleForDepositGeneration = false;
					break;
				}
			}
		}
		if ($eligibleForDepositGeneration && !empty($object->linkedObjects['commande'])) {
			foreach ($object->linkedObjects['commande'] as $order) {
				$order->fetchObjectLinked();
				if (!empty($order->linkedObjects['facture'])) {
					foreach ($order->linkedObjects['facture'] as $invoice) {
						if ($invoice->type == Facture::TYPE_DEPOSIT) {
							$eligibleForDepositGeneration = false;
							break 2;
						}
					}
				}
			}
		}
		if (!$eligibleForDepositGeneration) {
			return;
		}

		global $langs;
		$depositPercent = !empty($object->deposit_percent) ? $object->deposit_percent : $depositPercentFromPaymentTerms;
		$formquestion[] = array(
			'type' => 'checkbox',
			'tdclass' => 'showonlyifsigned',
			'name' => 'generate_deposit',
			'morecss' => 'margintoponly marginbottomonly',
			'label' => $form->textwithpicto($langs->trans('GenerateDeposit', $depositPercent), $langs->trans('DepositGenerationPermittedByThePaymentTermsSelected'))
		);
		$formquestion[] = array(
			'type' => 'date',
			'tdclass' => 'fieldrequired showonlyifgeneratedeposit',
			'name' => 'datef',
			'label' => $langs->trans('DateInvoice'),
			'value' => dol_now(),
			'datenow' => true
		);
		if (getDolGlobalString('INVOICE_POINTOFTAX_DATE')) {
			$formquestion[] = array(
				'type' => 'date',
				'tdclass' => 'fieldrequired showonlyifgeneratedeposit',
				'name' => 'date_pointoftax',
				'label' => $langs->trans('DatePointOfTax'),
				'value' => dol_now(),
				'datenow' => true
			);
		}
		$formquestion[] = array(
			'type' => 'other',
			'tdclass' => 'fieldrequired showonlyifgeneratedeposit',
			'name' => 'cond_reglement_id',
			'label' => $langs->trans('PaymentTerm'),
			'value' => $form->getSelectConditionsPaiements(0, 'cond_reglement_id', -1, 0, 1, 'minwidth200')
		);
		$formquestion[] = array(
			'type' => 'checkbox',
			'tdclass' => 'showonlyifgeneratedeposit',
			'name' => 'validate_generated_deposit',
			'morecss' => 'margintoponly marginbottomonly',
			'label' => $langs->trans('ValidateGeneratedDeposit')
		);
	}

	/**
	 * Build the auto-project guard row added to the signature modal.
	 *
	 * @param	array	$projects	Existing customer projects
	 * @return	array				Form question row
	 */
	private function buildAutoProjectGuardQuestion($projects)
	{
		global $langs;

		$html = '<div id="jpsun-autoproject-guard" class="jpsun-autoproject-guard">';
		$html .= '<div class="opacitymedium marginbottomonly">'.$langs->trans('JpsunAutoProjectGuardIntro').'</div>';
		$html .= '<div class="marginbottomonly"><label><input type="radio" name="jpsun_autoproject_guard_choice" value="existing" checked="checked"> '.$langs->trans('JpsunAutoProjectGuardUseExisting').'</label></div>';
		$html .= '<div class="marginleftonly marginbottomonly"><select class="flat minwidth300 maxwidth500" name="jpsun_autoproject_existing_project_id" id="jpsun_autoproject_existing_project_id">';
		foreach ($projects as $project) {
			$html .= '<option value="'.((int) $project['id']).'">'.dol_escape_htmltag($project['label']).'</option>';
		}
		$html .= '</select></div>';
		$html .= '<div><label><input type="radio" name="jpsun_autoproject_guard_choice" value="new"> '.$langs->trans('JpsunAutoProjectGuardCreateNew').'</label></div>';
		$html .= '</div>';

		return array(
			'type' => 'other',
			'name' => 'jpsun_autoproject_guard_choice,jpsun_autoproject_existing_project_id',
			'label' => $langs->trans('JpsunAutoProjectGuardLabel'),
			'value' => $html
		);
	}

	/**
	 * Build Javascript for the rebuilt confirmation modal.
	 *
	 * @param	int		$signedStatus		Proposal signed status
	 * @param	bool	$showOnlyWhenSigned	Hide guard while another close status is selected
	 * @return	string						HTML script
	 */
	private function buildAutoProjectGuardScript($signedStatus, $showOnlyWhenSigned)
	{
		return '<script nonce="'.getNonce().'">
			(function() {
				var signedStatus = "'.((int) $signedStatus).'";
				var guardDependsOnSigned = '.($showOnlyWhenSigned ? 'true' : 'false').';
				function closestRow(element) {
					return element && element.closest ? element.closest(".tagtr") : null;
				}
				function updateDepositRows(isSigned) {
					var generateDeposit = document.querySelector("[name=generate_deposit]");
					var showDepositOptions = !!(isSigned && generateDeposit && generateDeposit.checked);
					document.querySelectorAll(".showonlyifsigned").forEach(function(element) {
						var row = closestRow(element);
						if (row) row.style.display = isSigned ? "" : "none";
					});
					document.querySelectorAll(".showonlyifgeneratedeposit").forEach(function(element) {
						var row = closestRow(element);
						if (row) row.style.display = showDepositOptions ? "" : "none";
					});
				}
				function updateGuard() {
					var status = document.getElementById("statut");
					var isSigned = !status || String(status.value) === signedStatus;
					var shouldShowGuard = !guardDependsOnSigned || isSigned;
					var guard = document.getElementById("jpsun-autoproject-guard");
					if (guard) {
						var guardRow = closestRow(guard);
						if (guardRow) guardRow.style.display = shouldShowGuard ? "" : "none";
						var selectedChoice = guard.querySelector("input[name=jpsun_autoproject_guard_choice]:checked");
						var projectSelect = guard.querySelector("select[name=jpsun_autoproject_existing_project_id]");
						if (projectSelect) {
							projectSelect.disabled = !(shouldShowGuard && selectedChoice && selectedChoice.value === "existing");
						}
					}
					updateDepositRows(isSigned);
				}
				function initGuard() {
					var status = document.getElementById("statut");
					if (status) status.addEventListener("change", updateGuard);
					var generateDeposit = document.querySelector("[name=generate_deposit]");
					if (generateDeposit) generateDeposit.addEventListener("change", updateGuard);
					document.querySelectorAll("input[name=jpsun_autoproject_guard_choice]").forEach(function(input) {
						input.addEventListener("change", updateGuard);
					});
					updateGuard();
				}
				if (document.readyState === "loading") {
					document.addEventListener("DOMContentLoaded", initGuard);
				} else {
					initGuard();
				}
			})();
		</script>';
	}

	/**
	 * Build validation confirmation text for skip accept/refuse mode.
	 *
	 * @param	CommonObject	$object	Proposal object
	 * @return	string				HTML confirmation text
	 */
	private function buildValidateProposalConfirmText($object)
	{
		global $db, $langs;

		if (empty($object->thirdparty) && !empty($object->socid)) {
			$object->fetch_thirdparty();
		}

		$numref = $object->ref;
		$ref = substr((string) $object->ref, 1, 4);
		if ($ref === 'PROV' || $object->ref === '') {
			$nextRef = $object->getNextNumRef($object->thirdparty);
			if (!empty($nextRef)) {
				$numref = $nextRef;
			}
		}

		$text = $langs->trans('ConfirmValidateProp', $numref);
		if (isModEnabled('notification')) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/notify.class.php';
			$notify = new Notify($db);
			$text .= '<br>'.$notify->confirmMessage('PROPAL_VALIDATE', $object->socid, $object);
		}

		$nbMandated = 0;
		if (!empty($object->lines)) {
			foreach ($object->lines as $line) {
				$res = $line->fetch_product();
				if ($res > 0 && is_object($line->product) && method_exists($line->product, 'isService') && $line->product->isService() && $line->product->isMandatoryPeriod() && (empty($line->date_start) || empty($line->date_end))) {
					$nbMandated++;
					break;
				}
			}
		}
		if ($nbMandated > 0 && !getDolGlobalString('SERVICE_STRICT_MANDATORY_PERIOD')) {
			$text .= '<div><span class="clearboth nowraponall warning">'.img_warning().$langs->trans('mandatoryPeriodNeedTobeSetMsgValidate').'</span></div>';
		}

		return $text;
	}

	/**
	 * Check whether a proposal already has a project link.
	 *
	 * @param	CommonObject	$object	Proposal object
	 * @return	bool
	 */
	private function propalAlreadyLinkedToProject($object)
	{
		return (!empty($object->fk_projet) || !empty($object->fk_project));
	}

	/**
	 * Fetch existing projects for a proposal customer.
	 *
	 * @param	int		$socid	Thirdparty id
	 * @param	User	$user	Current user
	 * @return	array			Array of projects with id and label
	 */
	private function getCustomerProjectsForAutoProjectGuard($socid, $user)
	{
		global $db;

		$socid = (int) $socid;
		if ($socid <= 0) {
			return array();
		}

		dol_include_once('/projet/class/project.class.php');
		$projectStatic = new Project($db);
		$projectListFilter = '';
		if (!$user->hasRight('projet', 'all', 'lire')) {
			$projectsListId = $projectStatic->getProjectsAuthorizedForUser($user, 0, 1, 0);
			if (empty($projectsListId)) {
				return array();
			}
			$projectListFilter = " AND p.rowid IN (".$db->sanitize($projectsListId).")";
		}

		$sql = "SELECT p.rowid, p.ref, p.title";
		$sql .= " FROM ".MAIN_DB_PREFIX."projet AS p";
		$sql .= " WHERE p.fk_soc = ".$socid;
		$sql .= " AND p.entity IN (".getEntity('project').")";
		$sql .= $projectListFilter;
		$sql .= " ORDER BY p.rowid DESC";

		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' SQL error: '.$db->lasterror(), LOG_ERR);
			return array();
		}

		$projects = array();
		while ($obj = $db->fetch_object($resql)) {
			$label = $obj->ref;
			if (!empty($obj->title)) {
				$label .= ' - '.$obj->title;
			}
			$projects[] = array(
				'id' => (int) $obj->rowid,
				'label' => $label
			);
		}

		return $projects;
	}

	/**
	 * Fetch and validate a project selected in the guard modal.
	 *
	 * @param	int		$projectId	Project id
	 * @param	int		$socid		Expected thirdparty id
	 * @param	User	$user		Current user
	 * @return	Project|null
	 */
	private function fetchAutoProjectGuardProject($projectId, $socid, $user)
	{
		global $db;

		$projectId = (int) $projectId;
		if ($projectId <= 0) {
			return null;
		}

		$authorizedProjects = $this->getCustomerProjectsForAutoProjectGuard($socid, $user);
		$isAuthorized = false;
		foreach ($authorizedProjects as $projectData) {
			if ((int) $projectData['id'] === $projectId) {
				$isAuthorized = true;
				break;
			}
		}
		if (!$isAuthorized) {
			return null;
		}

		dol_include_once('/projet/class/project.class.php');
		$project = new Project($db);
		if ($project->fetch($projectId) <= 0) {
			return null;
		}
		if ((int) $project->socid !== (int) $socid) {
			return null;
		}

		return $project;
	}

	/**
	 * Link a proposal to an existing project.
	 *
	 * @param	CommonObject	$object		Proposal object
	 * @param	Project		$project	Project object
	 * @param	User		$user		Current user
	 * @param	Translate	$langs		Language object
	 * @return	int					1 if OK, <0 on error
	 */
	private function linkPropalToExistingProject(&$object, $project, $user, $langs)
	{
		$propalId = (int) $object->id;
		if ($propalId <= 0 || empty($project->id)) {
			$this->error = $langs->trans('JpsunAutoProjectGuardInvalidProject');
			$this->errors[] = $this->error;
			return -1;
		}

		$result = $project->update_element('propal', $propalId);
		if ($result < 0) {
			$this->error = $langs->trans('JpsunPropalSignedProjectLinkError', $object->ref, $propalId, $project->id);
			$this->errors[] = $this->error;
			dol_syslog(__METHOD__.' failed to set fk_projet on propal id='.$propalId.' project id='.$project->id.' : '.$project->error, LOG_ERR);
			return -1;
		}

		$object->fk_projet = $project->id;
		$object->fk_project = $project->id;

		$linkResult = $project->add_object_linked('propal', $propalId, $user);
		if ($linkResult < 0) {
			dol_syslog(__METHOD__.' add_object_linked warning for propal id='.$propalId.' project id='.$project->id.' : '.$project->error, LOG_WARNING);
		}

		return 1;
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
				$workloadSeconds = $this->parsePlannedWorkloadToSeconds($workloadRaw);
				if ($workloadSeconds === null) {
					$error++;
					$this->errors[] = $langs->trans('JpsunMassActionInvalidPlannedWorkloadValue', $taskId);
					continue;
				}
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
				$currentWorkload = $this->formatPlannedWorkloadToHourMinute((int) $taskObject->planned_workload);
				$tableHtml .= '<tr><td>'.dol_escape_htmltag($taskObject->label ? $taskObject->label : $langs->trans('Task').' #'.$taskId).'</td>';
				$tableHtml .= '<td class="right"><input type="text" class="flat right width75" maxlength="6" placeholder="00:00" id="'.dol_escape_htmltag($inputName).'" name="'.dol_escape_htmltag($inputName).'" value="'.dol_escape_htmltag($currentWorkload).'"></td></tr>';
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

	/**
	 * Convert planned workload input (hh:mm) to seconds.
	 *
	 * @param	string	$value	Raw input value
	 * @return	int|null	Seconds or null if value is invalid
	 */
	private function parsePlannedWorkloadToSeconds($value)
	{
		$value = trim((string) $value);
		if ($value === '') {
			return null;
		}
		if (!preg_match('/^([0-9]{1,4}):([0-5][0-9])$/', $value, $matches)) {
			return null;
		}

		$hours = (int) $matches[1];
		$minutes = (int) $matches[2];

		return ($hours * 3600) + ($minutes * 60);
	}

	/**
	 * Convert planned workload in seconds to hh:mm string.
	 *
	 * @param	int	$seconds	Planned workload in seconds
	 * @return	string			Formatted string
	 */
	private function formatPlannedWorkloadToHourMinute($seconds)
	{
		$seconds = max(0, (int) $seconds);
		$hours = (int) floor($seconds / 3600);
		$minutes = (int) floor(($seconds % 3600) / 60);

		return sprintf('%02d:%02d', $hours, $minutes);
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






