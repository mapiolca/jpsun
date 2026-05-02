<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

if (!defined('NOLOGIN')) define('NOLOGIN', 1);
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);
if (!defined('NOIPCHECK')) define('NOIPCHECK', 1);
if (!defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', 1);

$entity = (int) ($_GET['entity'] ?? $_POST['entity'] ?? 1);
if ($entity > 0) {
	define('DOLENTITY', $entity);
}

// EN: Load Dolibarr environment from core or custom path
// FR: Charger l'environnement Dolibarr depuis le chemin core ou custom
if (!@include '../../main.inc.php') {
	if (!@include '../../../main.inc.php') {
		if (!@include '../../../../main.inc.php') {
			if (!@include '../../../../../main.inc.php') {
				die('Include of main fails');
			}
		}
	}
}
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

$langs->loadLangs(array('main', 'companies', 'projects'));

$form = new Form($db);
$extrafieldsSoc = new ExtraFields($db);
$extrafieldsProj = new ExtraFields($db);
$extrafieldsSoc->fetch_name_optionals_label('societe');
$extrafieldsProj->fetch_name_optionals_label('projet');

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$entityposted = GETPOSTINT('entity') ?: (defined('DOLENTITY') ? DOLENTITY : 1);

$fullname = trim(GETPOST('fullname', 'alphanohtml'));
$phone = trim(GETPOST('phone', 'alphanohtml'));
$email = trim(GETPOST('email', 'alpha'));
$town = trim(GETPOST('town', 'alphanohtml'));
$zip = trim(GETPOST('zip', 'alphanohtml'));
$description = trim(GETPOST('description', 'restricthtml'));
$rgpdConsent = GETPOSTINT('rgpd_consent');
$websiteUrl = trim(GETPOST('website_url', 'alphanohtml'));
$formTs = GETPOSTINT('form_ts');

$utmFields = array('utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term');
$utms = array();
foreach ($utmFields as $utmField) {
	$utms[$utmField] = trim(GETPOST($utmField, 'alphanohtml'));
}

$error = 0;
$publicError = '';
$successMessage = '';

if ($action === 'add') {
	$maxpostbyip = getDolGlobalInt('MAIN_SECURITY_MAX_POST_ON_PUBLIC_PAGES_BY_IP_ADDRESS', 20);
	if (!dolCheckLimitsByIP('projectpubliclead', $maxpostbyip)) {
		$error++;
		$publicError = $langs->trans('ErrorTryLater');
	}

	if (!$error && !empty($websiteUrl)) {
		dol_syslog(__METHOD__.': honeypot blocked on public lead form for IP '.getUserRemoteIP(), LOG_WARNING);
		$error++;
		$publicError = 'Une erreur est survenue. Merci de réessayer.';
	}

	if (!$error && (!empty($formTs) && ((dol_now() - $formTs) < 3))) {
		dol_syslog(__METHOD__.': timestamp blocked on public lead form for IP '.getUserRemoteIP(), LOG_WARNING);
		$error++;
		$publicError = 'Une erreur est survenue. Merci de réessayer.';
	}

	if (empty($fullname) || empty($phone) || empty($email) || empty($town) || empty($zip) || empty($rgpdConsent)) {
		$error++;
		$publicError = 'Merci de compléter les champs obligatoires.';
	}
	if (empty($error) && !isValidEmail($email)) {
		$error++;
		$publicError = 'L\'adresse e-mail n\'est pas valide.';
	}

	if (empty($description)) {
		$description = 'Demande de contact depuis le site Soleil Aquitain.';
	}

	$firstname = '';
	$lastname = $fullname;
	if (preg_match('/^([^\s]+)\s+(.+)$/u', $fullname, $matches)) {
		$firstname = trim($matches[1]);
		$lastname = trim($matches[2]);
	}

	if (!$error) {
		$db->begin();

		$thirdparty = new Societe($db);
		$socid = 0;
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE email = '".$db->escape($email)."'";
		if (isModEnabled('multicompany')) {
			$sql .= " AND entity IN (".getEntity('societe').")";
		}
		$sql .= ' ORDER BY rowid ASC';
		$resql = $db->query($sql);
		if ($resql && ($obj = $db->fetch_object($resql))) {
			$socid = (int) $obj->rowid;
			$thirdparty->fetch($socid);
			if (empty($thirdparty->phone) && !empty($phone)) $thirdparty->phone = $phone;
			if (empty($thirdparty->town) && !empty($town)) $thirdparty->town = $town;
			if (empty($thirdparty->zip) && !empty($zip)) $thirdparty->zip = $zip;
			if (empty($thirdparty->name) && !empty($fullname)) $thirdparty->name = $fullname;
			if (empty($thirdparty->firstname) && !empty($firstname)) $thirdparty->firstname = $firstname;
			if (empty($thirdparty->lastname) && !empty($lastname)) $thirdparty->lastname = $lastname;
			$thirdparty->update($socid, $user);
		} else {
			$thirdparty->name = $fullname;
			$thirdparty->firstname = $firstname;
			$thirdparty->lastname = $lastname;
			$thirdparty->email = $email;
			$thirdparty->phone = $phone;
			$thirdparty->zip = $zip;
			$thirdparty->town = $town;
			$thirdparty->client = Societe::PROSPECT;
			$thirdparty->code_client = 'auto';
			$thirdparty->code_fournisseur = 'auto';
			$thirdparty->array_options = $extrafieldsSoc->getOptionalsFromPost(null, '', 'societe');
			$socid = $thirdparty->create($user);
			if ($socid < 0) {
				$error++;
				dol_syslog(__METHOD__.': thirdparty create failed '.$thirdparty->error, LOG_ERR);
			}
		}

		if (!$error && $socid > 0) {
			$project = new Project($db);
			$project->socid = $socid;
			$project->status = Project::STATUS_DRAFT;
			$project->statut = Project::STATUS_DRAFT;
			$project->usage_opportunity = 1;
			$project->title = 'Demande de contact Soleil Aquitain - '.$fullname;
			$project->public = (getDolGlobalInt('PROJET_VISIBILITY') ? 1 : 0);
			$project->ip = getUserRemoteIP();
			$project->dateo = dol_now();
			$project->opp_status = getDolGlobalString('PROJECT_DEFAULT_OPPORTUNITY_STATUS_FOR_ONLINE_LEAD');
			$project->fk_opp_status = getDolGlobalInt('PROJECT_DEFAULT_OPPORTUNITY_STATUS_FOR_ONLINE_LEAD');
			$project->array_options = $extrafieldsProj->getOptionalsFromPost(null, '', 'projet');
			
			$desc = array();
			$desc[] = 'Nom complet: '.$fullname;
			$desc[] = 'Téléphone: '.$phone;
			$desc[] = 'Email: '.$email;
			$desc[] = 'Ville: '.$town;
			$desc[] = 'Code postal: '.$zip;
			$desc[] = 'Message / projet: '.$description;
			$desc[] = 'Consentement RGPD: '.($rgpdConsent ? 'Oui' : 'Non');
			foreach ($utms as $utmKey => $utmVal) {
				if ($utmVal !== '') $desc[] = $utmKey.': '.$utmVal;
			}
			$desc[] = 'IP: '.getUserRemoteIP();
			$desc[] = 'Date/heure de dépôt: '.dol_print_date(dol_now(), 'dayhourlog');
			$project->description = implode("\n", $desc);

			$resultProject = $project->create($user);
			if ($resultProject < 0) {
				$error++;
				dol_syslog(__METHOD__.': project create failed '.$project->error, LOG_ERR);
			}
		}

		if (!$error) {
			$db->commit();
			$redirect = $backtopage;
			if (empty($redirect)) {
				$redirect = getDolGlobalString('PROJECT_URL_REDIRECT_LEAD');
			}
			if (!empty($redirect)) {
				header('Location: '.$redirect);
				exit;
			}
			header('Location: '.$_SERVER['PHP_SELF'].'?action=added&entity='.$entityposted);
			exit;
		} else {
			$db->rollback();
			$publicError = 'Une erreur est survenue lors de l\'envoi de votre demande. Merci de réessayer.';
		}
	}
}

if ($action === 'added') {
	$successMessage = 'Votre demande a bien été envoyée. Nous vous répondrons sous 24h.';
}

$formTokenTs = dol_now();

$head = '<meta name="viewport" content="width=device-width,initial-scale=1">';
$head .= '<link rel="preconnect" href="https://soleilaquitain.fr" crossorigin>';
$head .= '<link rel="stylesheet" href="https://soleilaquitain.fr/contact/">';
$head .= '<script defer src="https://soleilaquitain.fr/wp-includes/js/jquery/jquery.min.js"></script>';
$head .= '<script defer src="https://soleilaquitain.fr/wp-content/plugins/elementor/assets/js/frontend.min.js"></script>';
$head .= '<style>';
$head .= 'body{margin:0;font-family:Arial,sans-serif;background:#f6f8fb;color:#1f2a37}.sa-wrap{max-width:1100px;margin:0 auto;padding:24px}.sa-grid{display:grid;grid-template-columns:1.1fr 1fr;gap:36px;align-items:start}.sa-card{background:#fff;border-radius:16px;box-shadow:0 10px 24px rgba(0,0,0,.08);padding:24px}.sa-title{font-size:46px;font-weight:800;margin:10px 0}.sa-sub{font-size:26px;font-weight:700;margin:0}.sa-sub2{font-size:22px;font-weight:500;margin:6px 0 16px}.sa-intro{line-height:1.5;color:#4b5563}.sa-list{margin:16px 0 0;padding:0;list-style:none}.sa-list li{margin:8px 0}.sa-badge{display:inline-block;background:#eff6ff;color:#1d4ed8;border-radius:999px;padding:6px 12px;margin:4px 8px 0 0;font-size:13px}.sa-form input,.sa-form textarea{width:100%;box-sizing:border-box;border:1px solid #dbe1ea;border-radius:10px;padding:12px;margin-top:6px}.sa-form label{display:block;font-weight:600;margin:12px 0 0}.sa-btn{margin-top:14px;background:#1d4ed8;color:#fff;border:none;border-radius:999px;padding:12px 18px;font-weight:700;cursor:pointer;width:100%}.sa-msg{border-radius:10px;padding:10px 12px;margin-bottom:10px}.sa-ok{background:#dcfce7;color:#166534}.sa-ko{background:#fee2e2;color:#991b1b}.sa-hid{display:none}@media (max-width:900px){.sa-grid{grid-template-columns:1fr}.sa-title{font-size:34px}}';
$head .= '</style>';

top_htmlhead('', 'CONTACT - Soleil Aquitain', 0, 0, '', '', $head);
echo '<body><div class="sa-wrap">';
echo '<div class="sa-grid">';
echo '<div>';
echo '<div class="sa-card">';
echo '<div><img src="'.dol_escape_htmltag(DOL_URL_ROOT.'/theme/common/logos/logo-180.png').'" alt="Soleil Aquitain" style="max-height:70px"></div>';
echo '<h1 class="sa-title">CONTACT</h1>';
echo '<p class="sa-sub">Demandez à être contacté</p>';
echo '<p class="sa-sub2">ou réservez un rendez-vous avec un expert</p>';
echo '<p class="sa-intro">Remplissez le formulaire ou contactez-nous directement. Nous vous répondons sous 24h avec une étude personnalisée.</p>';
echo '<ul class="sa-list"><li>📞 05 33 51 14 34</li><li>✉️ contact@soleilaquitain.fr</li><li>📍 4 rue Alfred Kastler, 33380 Mios</li><li>🕒 Lundi - Vendredi : 9h - 18h</li></ul>';
echo '<div style="margin-top:14px"><span class="sa-badge">RGE QualiPV</span><span class="sa-badge">Garantie décennale</span><span class="sa-badge">Assurance RC Pro</span></div>';
echo '</div></div>';
echo '<div class="sa-card sa-form">';
if (!empty($successMessage)) echo '<div class="sa-msg sa-ok">'.dol_escape_htmltag($successMessage).'</div>';
if (!empty($publicError)) echo '<div class="sa-msg sa-ko">'.dol_escape_htmltag($publicError).'</div>';
echo '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
echo '<input type="hidden" name="action" value="add">';
echo '<input type="hidden" name="entity" value="'.((int) $entityposted).'">';
echo '<input type="hidden" name="token" value="'.newToken().'">';
echo '<input type="hidden" name="form_ts" value="'.$formTokenTs.'">';
if (!empty($backtopage)) echo '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
foreach ($utms as $utmKey => $utmVal) {
	echo '<input type="hidden" name="'.$utmKey.'" value="'.dol_escape_htmltag($utmVal).'">';
}
echo '<input class="sa-hid" type="text" name="website_url" value="">';
echo '<label>Nom complet *<input type="text" name="fullname" required value="'.dol_escape_htmltag($fullname).'"></label>';
echo '<label>Téléphone *<input type="text" name="phone" required value="'.dol_escape_htmltag($phone).'"></label>';
echo '<label>E-mail *<input type="email" name="email" required value="'.dol_escape_htmltag($email).'"></label>';
echo '<label>Ville<input type="text" name="town" required value="'.dol_escape_htmltag($town).'"></label>';
echo '<label>Code postal<input type="text" name="zip" required value="'.dol_escape_htmltag($zip).'"></label>';
echo '<label>Votre projet (optionnel)<textarea name="description" rows="5">'.dol_escape_htmltag($description).'</textarea></label>';
echo '<label style="font-weight:400"><input type="checkbox" name="rgpd_consent" value="1" '.($rgpdConsent ? 'checked' : '').' required> En soumettant ce formulaire, vous acceptez d’être contacté par Soleil Aquitain. Vos données sont traitées conformément à notre politique de confidentialité.</label>';
echo '<button type="submit" class="sa-btn">Envoyer ma demande</button>';
echo '</form>';
echo '</div></div></div>';
printCommonFooter('public');
echo '</body></html>';
