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


$head = <<<'HTML'
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1">
<title>Contact - Soleil Aquitain</title>
<meta name="description" content="Remplissez le formulaire ou contactez-nous directement. Nous vous répondons sous 24h avec une étude personnalisée.">
<meta name="robots" content="index, follow, max-snippet:-1, max-video-preview:-1, max-image-preview:large">
<link rel="canonical" href="https://soleilaquitain.fr/contact/">
<meta property="og:locale" content="fr_FR">
<meta property="og:type" content="article">
<meta property="og:title" content="Contact - Soleil Aquitain">
<meta property="og:description" content="Remplissez le formulaire ou contactez-nous directement. Nous vous répondons sous 24h avec une étude personnalisée.">
<meta property="og:url" content="https://soleilaquitain.fr/contact/">
<meta property="og:site_name" content="Soleil Aquitain">
<meta property="og:updated_time" content="2026-03-22T20:34:37+00:00">
<meta property="og:image" content="https://soleilaquitain.fr/wp-content/uploads/2026/01/Contact-400_380.jpg">
<meta property="article:published_time" content="2026-01-08T11:11:09+00:00">
<meta property="article:modified_time" content="2026-03-22T20:34:37+00:00">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Contact - Soleil Aquitain">
<meta name="twitter:description" content="Remplissez le formulaire ou contactez-nous directement. Nous vous répondons sous 24h avec une étude personnalisée.">
<meta name="twitter:image" content="https://soleilaquitain.fr/wp-content/uploads/2026/01/Contact-400_380.jpg">
<link rel="alternate" type="application/rss+xml" title="Soleil Aquitain » Flux" href="https://soleilaquitain.fr/feed/">
<link rel="alternate" type="application/rss+xml" title="Soleil Aquitain » Flux des commentaires" href="https://soleilaquitain.fr/comments/feed/">
<link rel="alternate" title="oEmbed (JSON)" type="application/json+oembed" href="https://soleilaquitain.fr/wp-json/oembed/1.0/embed?url=https%3A%2F%2Fsoleilaquitain.fr%2Fcontact%2F">
<link rel="alternate" title="oEmbed (XML)" type="text/xml+oembed" href="https://soleilaquitain.fr/wp-json/oembed/1.0/embed?url=https%3A%2F%2Fsoleilaquitain.fr%2Fcontact%2F&amp;format=xml">
<link rel="stylesheet" id="ht_ctc_main_css-css" href="https://soleilaquitain.fr/wp-content/plugins/click-to-chat-for-whatsapp/new/inc/assets/css/main.css?ver=4.39" media="all">
<link rel="stylesheet" id="cookie-notice-front-css" href="https://soleilaquitain.fr/wp-content/plugins/cookie-notice/css/front.min.css?ver=2.5.16" media="all">
<link rel="stylesheet" id="wpa-css-css" href="https://soleilaquitain.fr/wp-content/plugins/honeypot/includes/css/wpa.css?ver=2.3.04" media="all">
<link rel="stylesheet" id="kadence-global-css" href="https://soleilaquitain.fr/wp-content/themes/kadence/assets/css/global.min.css?ver=1.4.5" media="all">
<link rel="stylesheet" id="kadence-header-css" href="https://soleilaquitain.fr/wp-content/themes/kadence/assets/css/header.min.css?ver=1.4.5" media="all">
<link rel="stylesheet" id="kadence-content-css" href="https://soleilaquitain.fr/wp-content/themes/kadence/assets/css/content.min.css?ver=1.4.5" media="all">
<link rel="stylesheet" id="kadence-footer-css" href="https://soleilaquitain.fr/wp-content/themes/kadence/assets/css/footer.min.css?ver=1.4.5" media="all">
<link rel="stylesheet" id="elementor-frontend-css" href="https://soleilaquitain.fr/wp-content/plugins/elementor/assets/css/frontend.min.css?ver=4.0.1" media="all">
<link rel="stylesheet" id="widget-heading-css" href="https://soleilaquitain.fr/wp-content/plugins/elementor/assets/css/widget-heading.min.css?ver=4.0.1" media="all">
<link rel="stylesheet" id="widget-icon-list-css" href="https://soleilaquitain.fr/wp-content/plugins/elementor/assets/css/widget-icon-list.min.css?ver=4.0.1" media="all">
<link rel="stylesheet" id="elementor-post-9-css" href="https://soleilaquitain.fr/wp-content/uploads/elementor/css/post-9.css?ver=1777551887" media="all">
<link rel="stylesheet" id="widget-spacer-css" href="https://soleilaquitain.fr/wp-content/plugins/elementor/assets/css/widget-spacer.min.css?ver=4.0.1" media="all">
<link rel="stylesheet" id="widget-form-css" href="https://soleilaquitain.fr/wp-content/plugins/elementor-pro/assets/css/widget-form.min.css?ver=4.0.1" media="all">
<link rel="stylesheet" id="elementor-post-11-css" href="https://soleilaquitain.fr/wp-content/uploads/elementor/css/post-11.css?ver=1777553729" media="all">
<link rel="stylesheet" id="elementor-post-1452-css" href="https://soleilaquitain.fr/wp-content/uploads/elementor/css/post-1452.css?ver=1777551888" media="all">
<link rel="stylesheet" id="kadence-rankmath-css" href="https://soleilaquitain.fr/wp-content/themes/kadence/assets/css/rankmath.min.css?ver=1.4.5" media="all">
<link rel="stylesheet" id="elementor-gf-poppins-css" href="https://fonts.googleapis.com/css?family=Poppins:100,100italic,200,200italic,300,300italic,400,400italic,500,500italic,600,600italic,700,700italic,800,800italic,900,900italic&amp;display=swap" media="all">
<link rel="stylesheet" id="kadence-fonts-gfonts-css" href="https://fonts.googleapis.com/css?family=Roboto:regular&amp;display=swap" media="all">
<script src="https://soleilaquitain.fr/wp-includes/js/jquery/jquery.min.js?ver=3.7.1" id="jquery-core-js"></script>
<script src="https://soleilaquitain.fr/wp-includes/js/jquery/jquery-migrate.min.js?ver=3.4.1" id="jquery-migrate-js"></script>
<script src="https://soleilaquitain.fr/wp-content/plugins/cookie-notice/js/front.min.js?ver=2.5.16" id="cookie-notice-front-js"></script>
<script src="https://soleilaquitain.fr/wp-includes/js/wp-emoji-release.min.js?ver=6.9.4" defer=""></script>
<style id="wp-img-auto-sizes-contain-inline-css">img:is([sizes=auto i],[sizes^="auto," i]){contain-intrinsic-size:3000px 1500px}</style>
<style id="sa-local-fallback">body{margin:0;font-family:Arial,sans-serif;background:#f6f8fb;color:#1f2a37}.sa-wrap{max-width:1100px;margin:0 auto;padding:24px}.sa-grid{display:grid;grid-template-columns:1.1fr 1fr;gap:36px;align-items:start}.sa-card{background:#fff;border-radius:16px;box-shadow:0 10px 24px rgba(0,0,0,.08);padding:24px}.sa-title{font-size:46px;font-weight:800;margin:10px 0}.sa-sub{font-size:26px;font-weight:700;margin:0}.sa-sub2{font-size:22px;font-weight:500;margin:6px 0 16px}.sa-intro{line-height:1.5;color:#4b5563}.sa-list{margin:16px 0 0;padding:0;list-style:none}.sa-list li{margin:8px 0}.sa-badge{display:inline-block;background:#eff6ff;color:#1d4ed8;border-radius:999px;padding:6px 12px;margin:4px 8px 0 0;font-size:13px}.sa-form input,.sa-form textarea{width:100%;box-sizing:border-box;border:1px solid #dbe1ea;border-radius:10px;padding:12px;margin-top:6px}.sa-form label{display:block;font-weight:600;margin:12px 0 0}.sa-btn{margin-top:14px;background:#1d4ed8;color:#fff;border:none;border-radius:999px;padding:12px 18px;font-weight:700;cursor:pointer;width:100%}.sa-msg{border-radius:10px;padding:10px 12px;margin-bottom:10px}.sa-ok{background:#dcfce7;color:#166534}.sa-ko{background:#fee2e2;color:#991b1b}.sa-hid{display:none}@media (max-width:900px){.sa-grid{grid-template-columns:1fr}.sa-title{font-size:34px}}</style>
HTML;

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
$saBodyEnd = <<<'HTML'
<script id="ht_ctc_app_js-js-extra">
var ht_ctc_chat_var = {"number":"33645454299","pre_filled":"Hello !","dis_m":"show","dis_d":"show","css":"cursor: pointer; z-index: 99999999;","pos_d":"position: fixed; bottom: 15px; right: 15px;","pos_m":"position: fixed; bottom: 15px; right: 15px;","side_d":"right","side_m":"right","schedule":"no","se":"150","ani":"no-animation","page_id":"11","url_target_d":"_blank","ga":"yes","gtm":"1","fb":"yes","webhook_format":"json","g_init":"default","g_an_event_name":"click to chat","gtm_event_name":"Click to Chat","pixel_event_name":"Click to Chat by HoliThemes"};
var ht_ctc_variables = {"g_an_event_name":"click to chat","gtm_event_name":"Click to Chat","pixel_event_type":"trackCustom","pixel_event_name":"Click to Chat by HoliThemes"};
</script>
<script src="https://soleilaquitain.fr/wp-content/plugins/click-to-chat-for-whatsapp/new/inc/assets/js/app.js?ver=4.39" id="ht_ctc_app_js-js" defer data-wp-strategy="defer"></script>
<script src="https://soleilaquitain.fr/wp-content/plugins/honeypot/includes/js/wpa.js?ver=2.3.04" id="wpascript-js"></script>
<script id="wpascript-js-after">wpa_field_info = {"wpa_field_name":"xtboij4894","wpa_field_value":788214,"wpa_add_test":"no"}</script>
<script id="kadence-navigation-js-extra">var kadenceConfig = {"screenReader":{"expand":"Menu enfant","expandOf":"Menu enfant de","collapse":"Menu enfant","collapseOf":"Menu enfant de"},"breakPoints":{"desktop":"1024","tablet":768},"scrollOffset":"0"};</script>
<script src="https://soleilaquitain.fr/wp-content/themes/kadence/assets/js/navigation.min.js?ver=1.4.5" id="kadence-navigation-js" async></script>
<script src="https://soleilaquitain.fr/wp-content/plugins/elementor/assets/js/webpack.runtime.min.js?ver=4.0.1" id="elementor-webpack-runtime-js"></script>
<script src="https://soleilaquitain.fr/wp-content/plugins/elementor/assets/js/frontend-modules.min.js?ver=4.0.1" id="elementor-frontend-modules-js"></script>
<script src="https://soleilaquitain.fr/wp-includes/js/jquery/ui/core.min.js?ver=1.13.3" id="jquery-ui-core-js"></script>
<script src="https://soleilaquitain.fr/wp-content/plugins/elementor/assets/js/frontend.min.js?ver=4.0.1" id="elementor-frontend-js"></script>
<script src="https://soleilaquitain.fr/wp-content/plugins/elementor-pro/assets/js/webpack-pro.runtime.min.js?ver=4.0.1" id="elementor-pro-webpack-runtime-js"></script>
<script src="https://soleilaquitain.fr/wp-includes/js/dist/hooks.min.js?ver=dd5603f07f9220ed27f1" id="wp-hooks-js"></script>
<script src="https://soleilaquitain.fr/wp-includes/js/dist/i18n.min.js?ver=c26c3dc7bed366793375" id="wp-i18n-js"></script>
<script src="https://soleilaquitain.fr/wp-content/plugins/elementor-pro/assets/js/frontend.min.js?ver=4.0.1" id="elementor-pro-frontend-js"></script>
<script src="https://soleilaquitain.fr/wp-content/plugins/elementor-pro/assets/js/elements-handlers.min.js?ver=4.0.1" id="pro-elements-handlers-js"></script>
<div id="search-drawer" aria-modal="true" role="dialog" aria-label="Rechercher" class="popup-drawer popup-drawer-layout-fullwidth" data-drawer-target-string="#search-drawer"></div>
<div id="cookie-notice" role="dialog" class="cookie-notice-hidden cookie-revoke-hidden cn-position-bottom" aria-label="Cookie Notice" style="background-color: rgba(168,196,192,0.8);"><div class="cookie-notice-container" style="color: #fff"><span id="cn-notice-text" class="cn-text-container">Nous utilisons des cookies pour vous garantir la meilleure expérience sur notre site web. Si vous continuez à utiliser ce site, nous supposerons que vous en êtes satisfait.</span><span id="cn-notice-buttons" class="cn-buttons-container"><button id="cn-accept-cookie" data-cookie-set="accept" class="cn-set-cookie cn-button" aria-label="OK" style="background-color: #020000">OK</button></span><button type="button" id="cn-close-notice" data-cookie-set="accept" class="cn-close-icon" aria-label="Non"></button></div></div>
HTML;
echo $saBodyEnd;
printCommonFooter('public');
echo '</body></html>';
