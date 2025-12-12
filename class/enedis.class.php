<?php

function show_actions_enedis_messaging($conf, $langs, $db, $filterobj, $objcon = null, $noprint = 0, $actioncode = '', $donetodo = 'done', $filters = array(), $sortfield = 'a.datep,a.id', $sortorder = 'DESC')
{
  global $user, $conf;
  global $form;
 
  global $param, $massactionbutton;
 
  require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
 
  // Check parameters
  if (!is_object($filterobj) && !is_object($objcon)) {
    dol_print_error(null, 'BadParameter');
  }
 
  $histo = array();
  '@phan-var-force array<int,array{type:string,tododone:string,id:string,datestart:int|string,dateend:int|string,note:string,message:string,percent:string,userid:string,login:string,userfirstname:string,userlastname:string,userphoto:string,msg_from?:string,contact_id?:string,socpeopleassigned?:int[],lastname?:string,firstname?:string,fk_element?:int,elementtype?:string,acode:string,alabel?:string,libelle?:string,apicto?:string}> $histo';
 
  $numaction = 0;
  $now = dol_now();
 
  $sortfield_list = explode(',', $sortfield);
  $sortfield_label_list = array('a.id' => 'id', 'a.datep' => 'dp', 'a.percent' => 'percent');
  $sortfield_new_list = array();
  foreach ($sortfield_list as $sortfield_value) {
    $sortfield_new_list[] = $sortfield_label_list[trim($sortfield_value)];
  }
  $sortfield_new = implode(',', $sortfield_new_list);
 
  $sql = null;
  $sql2 = null;
 
  if (isModEnabled('agenda')) {
    // Search histo on actioncomm
    if (is_object($objcon) && $objcon->id > 0) {
      $sql = "SELECT DISTINCT a.id, a.label as label,";
    } else {
      $sql = "SELECT a.id, a.label as label,";
    }
    $sql .= " a.datep as dp,";
    $sql .= " a.note as message,";
    $sql .= " a.datep2 as dp2,";
    $sql .= " a.percent as percent, 'action' as type,";
    $sql .= " a.fk_element, a.elementtype,";
    $sql .= " a.fk_contact,";
    $sql .= " a.email_from as msg_from,";
    $sql .= " c.code as acode, c.libelle as alabel, c.picto as apicto,";
    $sql .= " u.rowid as user_id, u.login as user_login, u.photo as user_photo, u.firstname as user_firstname, u.lastname as user_lastname";
    if (is_object($filterobj) && get_class($filterobj) == 'Societe') {
      $sql .= ", sp.lastname, sp.firstname";
    } elseif (is_object($filterobj) && get_class($filterobj) == 'Adherent') {
      $sql .= ", m.lastname, m.firstname";
    } elseif (is_object($filterobj) && in_array(get_class($filterobj), array('Commande', 'CommandeFournisseur', 'Product', 'Ticket', 'BOM', 'Contrat', 'Facture', 'FactureFournisseur'))) {
      $sql .= ", o.ref";
    }
    $sql .= " FROM ".MAIN_DB_PREFIX."actioncomm as a";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u on u.rowid = a.fk_user_action";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_actioncomm as c ON a.fk_action = c.id";
 
    $force_filter_contact = $filterobj instanceof User;
 
    if (is_object($objcon) && $objcon->id > 0) {
      $force_filter_contact = true;
      $sql .= " INNER JOIN ".MAIN_DB_PREFIX."actioncomm_resources as r ON a.id = r.fk_actioncomm";
      $sql .= " AND r.element_type = '".$db->escape($objcon->table_element)."' AND r.fk_element = ".((int) $objcon->id);
    }
 
    if ((is_object($filterobj) && get_class($filterobj) == 'Societe') || (is_object($filterobj) && get_class($filterobj) == 'Contact')) {
      $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON a.fk_contact = sp.rowid";
    } elseif (is_object($filterobj) && get_class($filterobj) == 'Dolresource') {
      $sql .= " INNER JOIN ".MAIN_DB_PREFIX."element_resources as er";
      $sql .= " ON er.resource_type = 'dolresource'";
      $sql .= " AND er.element_id = a.id";
      $sql .= " AND er.resource_id = ".((int) $filterobj->id);
    } elseif (is_object($filterobj) && get_class($filterobj) == 'Adherent') {
      $sql .= ", ".MAIN_DB_PREFIX."adherent as m";
    } elseif (is_object($filterobj) && get_class($filterobj) == 'CommandeFournisseur') {
      $sql .= ", ".MAIN_DB_PREFIX."commande_fournisseur as o";
    } elseif (is_object($filterobj) && get_class($filterobj) == 'Product') {
      $sql .= ", ".MAIN_DB_PREFIX."product as o";
    } elseif (is_object($filterobj) && get_class($filterobj) == 'Ticket') {
      $sql .= ", ".MAIN_DB_PREFIX."ticket as o";
    } elseif (is_object($filterobj) && get_class($filterobj) == 'BOM') {
      $sql .= ", ".MAIN_DB_PREFIX."bom_bom as o";
    } elseif (is_object($filterobj) && get_class($filterobj) == 'Contrat') {
      $sql .= ", ".MAIN_DB_PREFIX."contrat as o";
    } elseif (is_object($filterobj) && get_class($filterobj) == 'Facture') {
      $sql .= ", ".MAIN_DB_PREFIX."facture as o";
    } elseif (is_object($filterobj) && get_class($filterobj) == 'FactureFournisseur') {
      $sql .= ", ".MAIN_DB_PREFIX."facture_fourn as o";
    }
 
    $sql .= " WHERE a.entity IN (".getEntity('agenda').")";
    if (!$force_filter_contact) {
      if (is_object($filterobj) && in_array(get_class($filterobj), array('Societe', 'Client', 'Fournisseur')) && $filterobj->id) {
        $sql .= " AND a.fk_soc = ".((int) $filterobj->id);
      } elseif (is_object($filterobj) && get_class($filterobj) == 'Project' && $filterobj->id) {
        $sql .= " AND a.fk_project = ".((int) $filterobj->id);
      } elseif (is_object($filterobj) && get_class($filterobj) == 'Adherent') {
        $sql .= " AND a.fk_element = m.rowid AND a.elementtype = 'member'";
        if ($filterobj->id) {
          $sql .= " AND a.fk_element = ".((int) $filterobj->id);
        }
      } elseif (is_object($filterobj) && get_class($filterobj) == 'Commande') {
        $sql .= " AND a.fk_element = o.rowid AND a.elementtype = 'order'";
        if ($filterobj->id) {
          $sql .= " AND a.fk_element = ".((int) $filterobj->id);
        }
      } elseif (is_object($filterobj) && get_class($filterobj) == 'CommandeFournisseur') {
        $sql .= " AND a.fk_element = o.rowid AND a.elementtype = 'order_supplier'";
        if ($filterobj->id) {
          $sql .= " AND a.fk_element = ".((int) $filterobj->id);
        }
      } elseif (is_object($filterobj) && get_class($filterobj) == 'Product') {
        $sql .= " AND a.fk_element = o.rowid AND a.elementtype = 'product'";
        if ($filterobj->id) {
          $sql .= " AND a.fk_element = ".((int) $filterobj->id);
        }
      } elseif (is_object($filterobj) && get_class($filterobj) == 'Ticket') {
        $sql .= " AND a.fk_element = o.rowid AND a.elementtype = 'ticket'";
        if ($filterobj->id) {
          $sql .= " AND a.fk_element = ".((int) $filterobj->id);
        }
      } elseif (is_object($filterobj) && get_class($filterobj) == 'BOM') {
        $sql .= " AND a.fk_element = o.rowid AND a.elementtype = 'bom'";
        if ($filterobj->id) {
          $sql .= " AND a.fk_element = ".((int) $filterobj->id);
        }
      } elseif (is_object($filterobj) && get_class($filterobj) == 'Contrat') {
        $sql .= " AND a.fk_element = o.rowid AND a.elementtype = 'contract'";
        if ($filterobj->id) {
          $sql .= " AND a.fk_element = ".((int) $filterobj->id);
        }
      } elseif (is_object($filterobj) && get_class($filterobj) == 'Contact' && $filterobj->id) {
        $sql .= " AND a.fk_contact = sp.rowid";
        if ($filterobj->id) {
          $sql .= " AND a.fk_contact = ".((int) $filterobj->id);
        }
      } elseif (is_object($filterobj) && get_class($filterobj) == 'Facture') {
        $sql .= " AND a.fk_element = o.rowid";
        if ($filterobj->id) {
          $sql .= " AND a.fk_element = ".((int) $filterobj->id)." AND a.elementtype = 'invoice'";
        }
      } elseif (is_object($filterobj) && get_class($filterobj) == 'FactureFournisseur') {
        $sql .= " AND a.fk_element = o.rowid";
        if ($filterobj->id) {
          $sql .= " AND a.fk_element = ".((int) $filterobj->id)." AND a.elementtype = 'invoice_supplier'";
        }
      }
    } else {
      $sql .= " AND u.rowid = ". ((int) $filterobj->id);
    }
 
    // Condition on actioncode
    if (!empty($actioncode) && $actioncode != '-1') {
      if (!getDolGlobalString('AGENDA_USE_EVENT_TYPE')) {
        if ($actioncode == 'AC_NON_AUTO') {
          $sql .= " AND c.type != 'systemauto'";
        } elseif ($actioncode == 'AC_ALL_AUTO') {
          $sql .= " AND c.type = 'systemauto'";
        } else {
          if ($actioncode == 'AC_OTH') {
            $sql .= " AND c.type != 'systemauto'";
          } elseif ($actioncode == 'AC_OTH_AUTO') {
            $sql .= " AND c.type = 'systemauto'";
          }
        }
      } else {
        if ($actioncode == 'AC_NON_AUTO') {
          $sql .= " AND c.type != 'systemauto'";
        } elseif ($actioncode == 'AC_ALL_AUTO') {
          $sql .= " AND c.type = 'systemauto'";
        } else {
          $sql .= " AND c.code = '".$db->escape($actioncode)."'";
        }
      }
    }
    if ($donetodo == 'todo') {
      $sql .= " AND ((a.percent >= 0 AND a.percent < 100) OR (a.percent = -1 AND a.datep > '".$db->idate($now)."'))";
    } elseif ($donetodo == 'done') {
      $sql .= " AND (a.percent = 100 OR (a.percent = -1 AND a.datep <= '".$db->idate($now)."'))";
    }
    if (is_array($filters) && $filters['search_agenda_label']) {
      $sql .= natural_search('a.label', $filters['search_agenda_label']);
    }
    if (is_array($filters) && $filters['search_code']) {
      $sql .= natural_search('c.code', $filters['search_code']);
    }
  }
 
  // Add also event from emailings. TODO This should be replaced by an automatic event ? May be it's too much for very large emailing.
  if (isModEnabled('mailing') && !empty($objcon->email)
    && (empty($actioncode) || $actioncode == 'AC_OTH_AUTO' || $actioncode == 'AC_EMAILING')) {
    $langs->load("mails");
 
    $sql2 = "SELECT m.rowid as id, m.titre as label, mc.date_envoi as dp, mc.date_envoi as dp2, '100' as percent, 'mailing' as type";
    $sql2 .= ", null as fk_element, '' as elementtype, null as contact_id";
    $sql2 .= ", 'AC_EMAILING' as acode, '' as alabel, '' as apicto";
    $sql2 .= ", u.rowid as user_id, u.login as user_login, u.photo as user_photo, u.firstname as user_firstname, u.lastname as user_lastname"; // User that valid action
    if (is_object($filterobj) && get_class($filterobj) == 'Societe') {
      $sql2 .= ", '' as lastname, '' as firstname";
    } elseif (is_object($filterobj) && get_class($filterobj) == 'Adherent') {
      $sql2 .= ", '' as lastname, '' as firstname";
    } elseif (is_object($filterobj) && get_class($filterobj) == 'CommandeFournisseur') {
      $sql2 .= ", '' as ref";
    } elseif (is_object($filterobj) && get_class($filterobj) == 'Product') {
      $sql2 .= ", '' as ref";
    } elseif (is_object($filterobj) && get_class($filterobj) == 'Ticket') {
      $sql2 .= ", '' as ref";
    }
    $sql2 .= " FROM ".MAIN_DB_PREFIX."mailing as m, ".MAIN_DB_PREFIX."mailing_cibles as mc, ".MAIN_DB_PREFIX."user as u";
    $sql2 .= " WHERE mc.email = '".$db->escape($objcon->email)."'"; // Search is done on email.
    $sql2 .= " AND mc.statut = 1";
    $sql2 .= " AND u.rowid = m.fk_user_valid";
    $sql2 .= " AND mc.fk_mailing=m.rowid";
  }
 
  if ($sql || $sql2) {  // May not be defined if module Agenda is not enabled and mailing module disabled too
    if (!empty($sql) && !empty($sql2)) {
      $sql = $sql." UNION ".$sql2;
    } elseif (empty($sql) && !empty($sql2)) {
      $sql = $sql2;
    }
 
    //TODO Add navigation with this limits...
    $offset = 0;
    $limit = 1000;
 
    // Complete request and execute it with limit
    $sql .= $db->order($sortfield_new, $sortorder);
    if ($limit) {
      $sql .= $db->plimit($limit + 1, $offset);
    }
 
    dol_syslog("function.lib::show_actions_messaging", LOG_DEBUG);
    $resql = $db->query($sql);
    if ($resql) {
      $i = 0;
      $num = $db->num_rows($resql);
 
      $imaxinloop = ($limit ? min($num, $limit) : $num);
      while ($i < $imaxinloop) {
        $obj = $db->fetch_object($resql);
 
        if ($obj->type == 'action') {
          $contactaction = new ActionComm($db);
          $contactaction->id = $obj->id;
          $result = $contactaction->fetchResources();
          if ($result < 0) {
            dol_print_error($db);
            setEventMessage("actions.lib::show_actions_messaging Error fetch resource", 'errors');
          }
 
          //if ($donetodo == 'todo') $sql.= " AND ((a.percent >= 0 AND a.percent < 100) OR (a.percent = -1 AND a.datep > '".$db->idate($now)."'))";
          //elseif ($donetodo == 'done') $sql.= " AND (a.percent = 100 OR (a.percent = -1 AND a.datep <= '".$db->idate($now)."'))";
          $tododone = '';
          if (($obj->percent >= 0 and $obj->percent < 100) || ($obj->percent == -1 && $obj->dp > $now)) {
            $tododone = 'todo';
          }
 
          $histo[$numaction] = array(
            'type' => $obj->type,
            'tododone' => $tododone,
            'id' => $obj->id,
            'datestart' => $db->jdate($obj->dp),
            'dateend' => $db->jdate($obj->dp2),
            'note' => $obj->label,
            'message' => dol_htmlentitiesbr($obj->message),
            'percent' => $obj->percent,
 
            'userid' => $obj->user_id,
            'login' => $obj->user_login,
            'userfirstname' => $obj->user_firstname,
            'userlastname' => $obj->user_lastname,
            'userphoto' => $obj->user_photo,
            'msg_from' => $obj->msg_from,
 
            'contact_id' => $obj->fk_contact,
            'socpeopleassigned' => $contactaction->socpeopleassigned,
            'lastname' => (empty($obj->lastname) ? '' : $obj->lastname),
            'firstname' => (empty($obj->firstname) ? '' : $obj->firstname),
            'fk_element' => $obj->fk_element,
            'elementtype' => $obj->elementtype,
            // Type of event
            'acode' => $obj->acode,
            'alabel' => $obj->alabel,
            'libelle' => $obj->alabel, // deprecated
            'apicto' => $obj->apicto
          );
        } else {
          $histo[$numaction] = array(
            'type' => $obj->type,
            'tododone' => 'done',
            'id' => $obj->id,
            'datestart' => $db->jdate($obj->dp),
            'dateend' => $db->jdate($obj->dp2),
            'note' => $obj->label,
            'message' => dol_htmlentitiesbr($obj->message),
            'percent' => $obj->percent,
            'acode' => $obj->acode,
 
            'userid' => $obj->user_id,
            'login' => $obj->user_login,
            'userfirstname' => $obj->user_firstname,
            'userlastname' => $obj->user_lastname,
            'userphoto' => $obj->user_photo
          );
        }
 
        $numaction++;
        $i++;
      }
    } else {
      dol_print_error($db);
    }
  }
 
  // Set $out to show events
  $out = '';
 
  if (!isModEnabled('agenda')) {
    $langs->loadLangs(array("admin", "errors"));
    $out = info_admin($langs->trans("WarningModuleXDisabledSoYouMayMissEventHere", $langs->transnoentitiesnoconv("Module2400Name")), 0, 0, 'warning');
  }
 
  if (isModEnabled('agenda') || (isModEnabled('mailing') && !empty($objcon->email))) {
    $delay_warning = getDolGlobalInt('MAIN_DELAY_ACTIONS_TODO') * 24 * 60 * 60;
 
    require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
    include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
    require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
    require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
 
    $formactions = new FormActions($db);
 
    $actionstatic = new ActionComm($db);
    $userstatic = new User($db);
    $contactstatic = new Contact($db);
    $userGetNomUrlCache = array();
    $contactGetNomUrlCache = array();
 
    $out .= '<div class="filters-container" >';
    $out .= '<form name="listactionsfilter" class="listactionsfilter" action="'.$_SERVER["PHP_SELF"].'" method="POST">';
    $out .= '<input type="hidden" name="token" value="'.newToken().'">';
 
    if ($objcon && get_class($objcon) == 'Contact' &&
      (is_null($filterobj) || get_class($filterobj) == 'Societe')) {
      $out .= '<input type="hidden" name="id" value="'.$objcon->id.'" />';
    } else {
      $out .= '<input type="hidden" name="id" value="'.$filterobj->id.'" />';
    }
    if (($filterobj && get_class($filterobj) == 'Societe')) {
      $out .= '<input type="hidden" name="socid" value="'.$filterobj->id.'" />';
    } else {
      $out .= '<input type="hidden" name="userid" value="'.$filterobj->id.'" />';
    }
 
    $out .= "\n";
/**
    $out .= '<div class="div-table-responsive-no-min">';
    $out .= '<table class="noborder borderbottom centpercent">';

    $out .= '<tr class="liste_titre">';
 
    // Action column
    if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
      $out .= '<th class="liste_titre width50 middle">';
      $searchpicto = $form->showFilterAndCheckAddButtons($massactionbutton ? 1 : 0, 'checkforselect', 1);
      $out .= $searchpicto;
      $out .= '</th>';
    }
 
    // Date
    $out .= getTitleFieldOfList('Date', 0, $_SERVER["PHP_SELF"], 'a.datep', '', $param, '', $sortfield, $sortorder, 'nowraponall nopaddingleftimp ')."\n";
 
    $out .= '<th class="liste_titre hideonsmartphone"><strong class="hideonsmartphone">'.$langs->trans("Search").' : </strong></th>';
    if ($donetodo) {
      $out .= '<th class="liste_titre"></th>';
    }
    // Type of event
    $out .= '<th class="liste_titre">';
    $out .= '<span class="fas fa-square inline-block fawidth30 hideonsmartphone" style="color: #ddd;" title="'.$langs->trans("ActionType").'"></span>';
    $out .= $formactions->select_type_actions($actioncode, "actioncode", '', getDolGlobalString('AGENDA_USE_EVENT_TYPE') ? -1 : 1, 0, 0, 1, 'selecttype minwidth100', $langs->trans("Type"));
    $out .= '</th>';
    // Label
    $out .= '<th class="liste_titre maxwidth100onsmartphone">';
    $out .= '<input type="text" class="maxwidth100onsmartphone" name="search_agenda_label" value="'.$filters['search_agenda_label'].'" placeholder="'.$langs->trans("Label").'">';
    $out .= '</th>';
 
    // Action column
    if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
      $out .= '<th class="liste_titre width50 middle">';
      $searchpicto = $form->showFilterAndCheckAddButtons($massactionbutton ? 1 : 0, 'checkforselect', 1);
      $out .= $searchpicto;
      $out .= '</th>';
    }
 
    $out .= '</tr>';

    $out .= '</table>';
 
    $out .= '</form>';
 **/
    $out .= '</div>';
 
    $out .= "\n";
 
    $out .= '<ul class="timeline">';
 
    if ($donetodo) {
      $tmp = '';
      if ($filterobj instanceof Societe) {
        $tmp .= '<a href="'.DOL_URL_ROOT.'/comm/action/list.php?mode=show_list&socid='.$filterobj->id.'&status=done">';
      }
      if ($filterobj instanceof User) {
        $tmp .= '<a href="'.DOL_URL_ROOT.'/comm/action/list.php?mode=show_list&socid='.$filterobj->id.'&status=done">';
      }
      $tmp .= ($donetodo != 'done' ? $langs->trans("ActionsToDoShort") : '');
      $tmp .= ($donetodo != 'done' && $donetodo != 'todo' ? ' / ' : '');
      $tmp .= ($donetodo != 'todo' ? $langs->trans("ActionsDoneShort") : '');
      //$out.=$langs->trans("ActionsToDoShort").' / '.$langs->trans("ActionsDoneShort");
      if ($filterobj instanceof Societe) {
        $tmp .= '</a>';
      }
      if ($filterobj instanceof User) {
        $tmp .= '</a>';
      }
      $out .= getTitleFieldOfList($tmp);
    }
 
    require_once DOL_DOCUMENT_ROOT.'/comm/action/class/cactioncomm.class.php';
    $caction = new CActionComm($db);
    $arraylist = $caction->liste_array(1, 'code', '', (!getDolGlobalString('AGENDA_USE_EVENT_TYPE') ? 1 : 0), '', 1);
 
    $actualCycleDate = false;
 
    // Loop on each event to show it
    foreach ($histo as $key => $value) {
      $actionstatic->fetch($histo[$key]['id']); // TODO Do we need this, we already have a lot of data of line into $histo
 
      $actionstatic->type_picto = $histo[$key]['apicto'];
      $actionstatic->type_code = $histo[$key]['acode'];
 
      $labeltype = $actionstatic->type_code;
      if (!getDolGlobalString('AGENDA_USE_EVENT_TYPE') && empty($arraylist[$labeltype])) {
        $labeltype = 'AC_OTH';
      }
      if (!empty($actionstatic->code) && preg_match('/^TICKET_MSG/', $actionstatic->code)) {
        $labeltype = $langs->trans("Message");
      } else {
        if (!empty($arraylist[$labeltype])) {
          $labeltype = $arraylist[$labeltype];
        }
        if ($actionstatic->type_code == 'AC_OTH_AUTO' && ($actionstatic->type_code != $actionstatic->code) && $labeltype && !empty($arraylist[$actionstatic->code])) {
          $labeltype .= ' - '.$arraylist[$actionstatic->code]; // Use code in priority on type_code
        }
      }
 
      $url = DOL_URL_ROOT.'/comm/action/card.php?id='.$histo[$key]['id'];
 
      $tmpa = dol_getdate($histo[$key]['datestart'], false);
 
      if (isset($tmpa['year']) && isset($tmpa['yday']) && $actualCycleDate !== $tmpa['year'].'-'.$tmpa['yday']) {
        $actualCycleDate = $tmpa['year'].'-'.$tmpa['yday'];
        $out .= '<!-- timeline time label -->';
        $out .= '<li class="time-label">';
        $out .= '<span class="timeline-badge-date">';
        $out .= dol_print_date($histo[$key]['datestart'], 'daytext', 'tzuserrel', $langs);
        $out .= '</span>';
        $out .= '</li>';
        $out .= '<!-- /.timeline-label -->';
      }
 
 
      $out .= '<!-- timeline item -->'."\n";
      $out .= '<li class="timeline-code-'.(!empty($actionstatic->code) ? strtolower($actionstatic->code) : "none").'">';
 
      //$timelineicon = getTimelineIcon($actionstatic, $histo, $key);
      $typeicon = $actionstatic->getTypePicto('pictofixedwidth timeline-icon-not-applicble', $labeltype);
      //$out .= $timelineicon;
      //var_dump($timelineicon);
      $out .= $typeicon;
 
      $out .= '<div class="timeline-item">'."\n";
 
      $out .= '<span class="time timeline-header-action2">';
 
      if (isset($histo[$key]['type']) && $histo[$key]['type'] == 'mailing') {
        $out .= '<a class="paddingleft paddingright timeline-btn2 editfielda" href="'.DOL_URL_ROOT.'/comm/mailing/card.php?id='.$histo[$key]['id'].'">'.img_object($langs->trans("ShowEMailing"), "email").' ';
        $out .= $histo[$key]['id'];
        $out .= '</a> ';
      } else {
        $out .= $actionstatic->getNomUrl(1, -1, 'valignmiddle').' ';
      }
 
      if ($user->hasRight('agenda', 'allactions', 'create') ||
        (($actionstatic->authorid == $user->id || $actionstatic->userownerid == $user->id) && $user->hasRight('agenda', 'myactions', 'create'))) {
        $out .= '<a class="paddingleft paddingright timeline-btn2 editfielda" href="'.DOL_MAIN_URL_ROOT.'/comm/action/card.php?action=edit&token='.newToken().'&id='.$actionstatic->id.'&backtopage='.urlencode($_SERVER["PHP_SELF"].'?'.$param).'">';
        //$out .= '<i class="fa fa-pencil" title="'.$langs->trans("Modify").'" ></i>';
        $out .= img_picto($langs->trans("Modify"), 'edit', 'class="edita"');
        $out .= '</a>';
      }
 
      $out .= '</span>';
 
      // Date
      $out .= '<span class="time"><i class="fa fa-clock-o valignmiddle"></i> <span class="valignmiddle">';
      $out .= dol_print_date($histo[$key]['datestart'], 'dayhour', 'tzuserrel');
      if ($histo[$key]['dateend'] && $histo[$key]['dateend'] != $histo[$key]['datestart']) {
        $tmpa = dol_getdate($histo[$key]['datestart'], true);
        $tmpb = dol_getdate($histo[$key]['dateend'], true);
        if ($tmpa['mday'] == $tmpb['mday'] && $tmpa['mon'] == $tmpb['mon'] && $tmpa['year'] == $tmpb['year']) {
          $out .= '-'.dol_print_date($histo[$key]['dateend'], 'hour', 'tzuserrel');
        } else {
          $out .= '-'.dol_print_date($histo[$key]['dateend'], 'dayhour', 'tzuserrel');
        }
      }
      $late = 0;
      if ($histo[$key]['percent'] == 0 && $histo[$key]['datestart'] && $histo[$key]['datestart'] < ($now - $delay_warning)) {
        $late = 1;
      }
      if ($histo[$key]['percent'] == 0 && !$histo[$key]['datestart'] && $histo[$key]['dateend'] && $histo[$key]['datestart'] < ($now - $delay_warning)) {
        $late = 1;
      }
      if ($histo[$key]['percent'] > 0 && $histo[$key]['percent'] < 100 && $histo[$key]['dateend'] && $histo[$key]['dateend'] < ($now - $delay_warning)) {
        $late = 1;
      }
      if ($histo[$key]['percent'] > 0 && $histo[$key]['percent'] < 100 && !$histo[$key]['dateend'] && $histo[$key]['datestart'] && $histo[$key]['datestart'] < ($now - $delay_warning)) {
        $late = 1;
      }
      if ($late) {
        $out .= img_warning($langs->trans("Late")).' ';
      }
      $out .= "</span></span>\n";
 
      // Ref
      $out .= '<h3 class="timeline-header">';
 
      // Author of event
      $out .= '<div class="messaging-author inline-block tdoverflowmax150 valignmiddle marginrightonly">';
      if ($histo[$key]['userid'] > 0) {
        if (!isset($userGetNomUrlCache[$histo[$key]['userid']])) { // is in cache ?
          $userstatic->fetch($histo[$key]['userid']);
          $userGetNomUrlCache[$histo[$key]['userid']] = $userstatic->getNomUrl(-1, '', 0, 0, 16, 0, 'firstelselast', '');
        }
        $out .= $userGetNomUrlCache[$histo[$key]['userid']];
      } elseif (!empty($histo[$key]['msg_from']) && $actionstatic->code == 'TICKET_MSG') {
        if (!isset($contactGetNomUrlCache[$histo[$key]['msg_from']])) {
          if ($contactstatic->fetch(0, null, '', $histo[$key]['msg_from']) > 0) {
            $contactGetNomUrlCache[$histo[$key]['msg_from']] = $contactstatic->getNomUrl(-1, '', 16);
          } else {
            $contactGetNomUrlCache[$histo[$key]['msg_from']] = $histo[$key]['msg_from'];
          }
        }
        $out .= $contactGetNomUrlCache[$histo[$key]['msg_from']];
      }
      $out .= '</div>';
 
      // Title
      $out .= ' <div class="messaging-title inline-block">';
      //$out .= $actionstatic->getTypePicto();
      if (empty($conf->dol_optimize_smallscreen) && $actionstatic->type_code != 'AC_OTH_AUTO') {
        $out .= $labeltype.' - ';
      }
 
      $libelle = '';
      if (!empty($actionstatic->code) && preg_match('/^TICKET_MSG/', $actionstatic->code)) {
        $out .= $langs->trans('TicketNewMessage');
      } elseif (!empty($actionstatic->code) && preg_match('/^TICKET_MSG_PRIVATE/', $actionstatic->code)) {
        $out .= $langs->trans('TicketNewMessage').' <em>('.$langs->trans('Private').')</em>';
      } elseif (isset($histo[$key]['type'])) {
        if ($histo[$key]['type'] == 'action') {
          $transcode = $langs->transnoentitiesnoconv("Action".$histo[$key]['acode']);
          $libelle = ($transcode != "Action".$histo[$key]['acode'] ? $transcode : $histo[$key]['alabel']);
          $libelle = $histo[$key]['note'];
          $actionstatic->id = $histo[$key]['id'];
          $out .= dol_escape_htmltag(dol_trunc($libelle, 120));
        } elseif ($histo[$key]['type'] == 'mailing') {
          $out .= '<a href="'.DOL_URL_ROOT.'/comm/mailing/card.php?id='.$histo[$key]['id'].'">'.img_object($langs->trans("ShowEMailing"), "email").' ';
          $transcode = $langs->transnoentitiesnoconv("Action".$histo[$key]['acode']);
          $libelle = ($transcode != "Action".$histo[$key]['acode'] ? $transcode : 'Send mass mailing');
          $out .= dol_escape_htmltag(dol_trunc($libelle, 120));
        } else {
          $libelle .= $histo[$key]['note'];
          $out .= dol_escape_htmltag(dol_trunc($libelle, 120));
        }
      }
 
      if (isset($histo[$key]['elementtype']) && !empty($histo[$key]['fk_element'])) {
        if (isset($conf->cache['elementlinkcache'][$histo[$key]['elementtype']]) && isset($conf->cache['elementlinkcache'][$histo[$key]['elementtype']][$histo[$key]['fk_element']])) {
          $link = $conf->cache['elementlinkcache'][$histo[$key]['elementtype']][$histo[$key]['fk_element']];
        } else {
          if (!isset($conf->cache['elementlinkcache'][$histo[$key]['elementtype']])) {
            $conf->cache['elementlinkcache'][$histo[$key]['elementtype']] = array();
          }
          $link = dolGetElementUrl($histo[$key]['fk_element'], $histo[$key]['elementtype'], 1);
          $conf->cache['elementlinkcache'][$histo[$key]['elementtype']][$histo[$key]['fk_element']] = $link;
        }
        if ($link) {
          $out .= ' - '.$link;
        }
      }
 
      $out .= '</div>';
 
      $out .= '</h3>';
 
      // Message
      if (!empty($histo[$key]['message'] && $histo[$key]['message'] != $libelle)
        && $actionstatic->code != 'AC_TICKET_CREATE'
        && $actionstatic->code != 'AC_TICKET_MODIFY'
      ) {
        $out .= '<div class="timeline-body wordbreak small">';
        $truncateLines = getDolGlobalInt('MAIN_TRUNCATE_TIMELINE_MESSAGE', 3);
        $truncatedText = dolGetFirstLineOfText($histo[$key]['message'], $truncateLines);
        if ($truncateLines > 0 && strlen($histo[$key]['message']) > strlen($truncatedText)) {
          $out .= '<div class="readmore-block --closed" >';
          $out .= ' <div class="readmore-block__excerpt">';
          $out .=   dolPrintHTML($truncatedText);
          $out .= '   <br><a class="read-more-link" data-read-more-action="open" href="'.DOL_MAIN_URL_ROOT.'/comm/action/card.php?id='.$actionstatic->id.'&backtopage='.urlencode($_SERVER["PHP_SELF"].'?'.$param).'" >'.$langs->trans("ReadMore").' <span class="fa fa-chevron-right" aria-hidden="true"></span></a>';
          $out .= ' </div>';
          $out .= ' <div class="readmore-block__full-text" >';
          $out .=  dolPrintHTML($histo[$key]['message']);
          $out .= '   <a class="read-less-link" data-read-more-action="close" href="#" ><span class="fa fa-chevron-up" aria-hidden="true"></span> '.$langs->trans("ReadLess").'</a>';
          $out .= ' </div>';
          $out .= '</div>';
        } else {
          $out .= dolPrintHTML($histo[$key]['message']);
        }
 
        $out .= '</div>';
      }
 
      // Timeline footer
      $footer = '';
 
      // Contact for this action
      if (isset($histo[$key]['socpeopleassigned']) && is_array($histo[$key]['socpeopleassigned']) && count($histo[$key]['socpeopleassigned']) > 0) {
        $contactList = '';
        foreach ($histo[$key]['socpeopleassigned'] as $cid => $Tab) {
          if (empty($conf->cache['contact'][$histo[$key]['contact_id']])) {
            $contact = new Contact($db);
            $contact->fetch($cid);
            $conf->cache['contact'][$histo[$key]['contact_id']] = $contact;
          } else {
            $contact = $conf->cache['contact'][$histo[$key]['contact_id']];
          }
 
          if ($contact) {
            $contactList .= !empty($contactList) ? ', ' : '';
            $contactList .= $contact->getNomUrl(1);
            if (isset($histo[$key]['acode']) && $histo[$key]['acode'] == 'AC_TEL') {
              if (!empty($contact->phone_pro)) {
                $contactList .= '('.dol_print_phone($contact->phone_pro).')';
              }
            }
          }
        }
 
        $footer .= $langs->trans('ActionOnContact').' : '.$contactList;
      } elseif (empty($objcon->id) && isset($histo[$key]['contact_id']) && $histo[$key]['contact_id'] > 0) {
        if (empty($conf->cache['contact'][$histo[$key]['contact_id']])) {
          $contact = new Contact($db);
          $result = $contact->fetch($histo[$key]['contact_id']);
          $conf->cache['contact'][$histo[$key]['contact_id']] = $contact;
        } else {
          $contact = $conf->cache['contact'][$histo[$key]['contact_id']];
          $result = ($contact instanceof Contact) ? $contact->id : 0;
        }
 
        if ($result > 0) {
          $footer .= $contact->getNomUrl(1);
          if (isset($histo[$key]['acode']) && $histo[$key]['acode'] == 'AC_TEL') {
            if (!empty($contact->phone_pro)) {
              $footer .= '('.dol_print_phone($contact->phone_pro).')';
            }
          }
        }
      }
 
      $documents = getActionCommEcmList($actionstatic);
      if (!empty($documents)) {
        $footer .= '<div class="timeline-documents-container">';
        foreach ($documents as $doc) {
          $footer .= '<span id="document_'.$doc->id.'" class="timeline-documents" ';
          $footer .= ' data-id="'.$doc->id.'" ';
          $footer .= ' data-path="'.$doc->filepath.'"';
          $footer .= ' data-filename="'.dol_escape_htmltag($doc->filename).'" ';
          $footer .= '>';
 
          $filePath = DOL_DATA_ROOT.'/'.$doc->filepath.'/'.$doc->filename;
          $mime = dol_mimetype($filePath);
          $file = $actionstatic->id.'/'.$doc->filename;
          $thumb = $actionstatic->id.'/thumbs/'.substr($doc->filename, 0, strrpos($doc->filename, '.')).'_mini'.substr($doc->filename, strrpos($doc->filename, '.'));
          $doclink = dol_buildpath('document.php', 1).'?modulepart=actions&attachment=0&file='.urlencode($file).'&entity='.$conf->entity;
          $viewlink = dol_buildpath('viewimage.php', 1).'?modulepart=actions&file='.urlencode($thumb).'&entity='.$conf->entity;
 
          $mimeAttr = ' mime="'.$mime.'" ';
          $class = '';
          if (in_array($mime, array('image/png', 'image/jpeg', 'application/pdf'))) {
            $class .= ' documentpreview';
          }
 
          $footer .= '<a href="'.$doclink.'" class="btn-link '.$class.'" target="_blank" rel="noopener noreferrer" '.$mimeAttr.' >';
          $footer .= img_mime($filePath).' '.$doc->filename;
          $footer .= '</a>';
 
          $footer .= '</span>';
        }
        $footer .= '</div>';
      }
 
      if (!empty($footer)) {
        $out .= '<div class="timeline-footer">'.$footer.'</div>';
      }
 
      $out .= '</div>'."\n"; // end timeline-item
 
      $out .= '</li>';
      $out .= '<!-- END timeline item -->';
    }
 
    $out .= "</ul>\n";
 
    $out .= '<script>
        jQuery(document).ready(function () {
           $(document).on("click", "[data-read-more-action]", function(e){
             let readMoreBloc = $(this).closest(".readmore-block");
             if(readMoreBloc.length > 0){
              e.preventDefault();
              if($(this).attr("data-read-more-action") == "close"){
                readMoreBloc.addClass("--closed").removeClass("--open");
                 $("html, body").animate({
                  scrollTop: readMoreBloc.offset().top - 200
                }, 100);
              }else{
                readMoreBloc.addClass("--open").removeClass("--closed");
              }
             }
          });
        });
      </script>';
 
 
    if (empty($histo)) {
      $out .= '<span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span>';
    }
  }
 
  if ($noprint) {
    return $out;
  } else {
    print $out;
    return null;
  }
}