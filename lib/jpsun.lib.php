<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
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
 *	\file		lib/jpsun.lib.php
 *	\ingroup	jpsun
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function jpsunAdminPrepareHead()
{
    global $langs, $conf, $object;

    $langs->load("jpsun@jpsun");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/jpsun/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("Setup");
    $head[$h][2] = 'setup';
    $h++;
/**
    $head[$h][0] = dol_buildpath("/jpsun/admin/about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;
**/
    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@metre:/metre/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@metre:/metre/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'jpsun');

    return $head;
}



function printForecastProfitBoard(Project &$object, &$listofreferent, $dates, $datee) {
	global $db, $langs, $user, $conf, $mysoc, $form, $hookmanager;

	$langs->load('jpsun@jpsun');

	$elementuser = new User($db);

	$balance_ht = 0;
	$balance_ttc = 0;

	print '<tr class="left forecast" style="display: none">';
	print '<th colspan="4" align="center">'.$langs->trans('jpsun_ForecastProfit').'</th>';
	print '</tr>';
	print '<tr class="liste_titre forecast" style="display: none">';
	print '<td align="left" width="200">';
	$tooltiponprofit = $langs->trans("ProfitIsCalculatedWith")."<br>\n";
	$tooltiponprofitplus = $tooltiponprofitminus = '';
	foreach ($listofreferent as $key => $value) {
		$name = $langs->trans($value['name']);
		$tooltip = $langs->trans($value['tooltip']);
		$qualified = $value['test'];
		$margin = $value['margin'] ?? null;
		if ($qualified && isset($margin)) {		// If this element must be included into profit calculation ($margin is 'minus' or 'add')
			if ($margin == 'add') {
				if ($value['tooltip'] != "") {
					$tooltiponprofitplus .= ' &gt; '.$name." (".$tooltip.") (+)<br>\n";
				} else {
					$tooltiponprofitplus .= ' &gt; '.$name." (+)<br>\n";
				}
			}
			if ($margin == 'minus') {
				if ($value['tooltip'] != "") {
					$tooltiponprofitminus .= ' &gt; '.$name." (".$tooltip.") (-)<br>\n";
				} else {
					$tooltiponprofitminus .= ' &gt; '.$name." (-)<br>\n";
				}
			}
		}
	}
	$tooltiponprofit .= $tooltiponprofitplus;
	$tooltiponprofit .= $tooltiponprofitminus;
	print $form->textwithpicto($langs->trans("Element"), $tooltiponprofit);
	print '</td>';
	print '<td align="right" width="100">'.$langs->trans("Number").'</td>';
	print '<td align="right" width="100">'.$langs->trans("AmountHT").'</td>';
	print '<td align="right" width="100">'.$langs->trans("AmountTTC").'</td>';
	print '</tr>';
    $total_revenue_ht = 0;
	foreach($listofreferent as $key => $value) {
		$name=$langs->trans($value['name']);
		$tooltip=$langs->trans($value['tooltip']);
		$classname=$value['class'];
		$tablename=$value['table'];
		$datefieldname=$value['datefieldname'];
		$qualified=$value['test'];
		$margin = $value['margin'] ?? 0;
        $project_field = $value['project_field'] ?? 'fk_projet';
		if($qualified && isset($margin)) {
			$element = new $classname($db);

			$elementarray = $object->get_element_list($key, $tablename, $datefieldname, $dates, $datee, $project_field);
			if($key == 'project_task' && empty($object->lines)) {
				$object->getLinesArray($user);
			}

			if($key == 'project_task' && ! empty($object->lines)) {
				$total_ht_by_line = $total_ttc_by_line = 0;
				$thm = getDolGlobalInt('JPSUN_PROJECT_FORECAST_DEFAULT_THM');
				$i = count($object->lines);

				foreach($object->lines as $l) {
					$parameters = array('task' => $l);
					$resHook = $hookmanager->executeHooks('getForecastTHM', $parameters, $object, $action);
					if(! empty($resHook)) $thm = $resHook;
					$total_ht_by_line += price2num(($l->planned_workload / 3600) * $thm, 'MT');
				}
				$total_ttc_by_line += $total_ht_by_line;    // No TVA for tasks

				if ($margin != "add") {
					$total_ht_by_line *= -1;
					$total_ttc_by_line *= -1;
				}

				$balance_ht += $total_ht_by_line;
				$balance_ttc += $total_ttc_by_line;

				print '<tr class="oddeven forecast" style="display: none">';
				// Module
				print '<td align="left">'.$name.'</td>';
				// Nb
				print '<td align="right">'.$i.'</td>';
				// Amount HT
				print '<td align="right">';
				print price($total_ht_by_line);
				print '</td>';
				// Amount TTC
				print '<td align="right">';
				print price($total_ttc_by_line);
				print '</td>';
				print '</tr>';
			}
			else if (is_array($elementarray) && count($elementarray)>0)
			{
				$total_ht = 0;
				$total_ttc = 0;

				$num=count($elementarray);
				$TLinkedOrder = array();
				for ($i = 0; $i < $num; $i++)
				{
					$tmp=explode('_', $elementarray[$i]);
					$idofelement=$tmp[0];
					$idofelementuser=$tmp[1] ?? 0;

					$element->fetch($idofelement);
					if ($idofelementuser) $elementuser->fetch($idofelementuser);

					// Define if record must be used for total or not
					$qualifiedfortotal=true;
					if ($key == 'invoice')
					{
						if (! empty($element->close_code) && $element->close_code == 'replaced') $qualifiedfortotal=false;	// Replacement invoice, do not include into total
					}
					if ($key == 'propal')
					{
						if ($element->statut == Propal::STATUS_NOTSIGNED) $qualifiedfortotal=false;	// Refused proposal must not be included in total
						else {
							$element->fetchObjectLinked($element->id, 'propal', null, 'commande');
							if(! empty($element->linkedObjects['commande'])) {
								foreach($element->linkedObjects['commande'] as $linkedOrder) {
									if(! isset($TLinkedOrder['HT'])) $TLinkedOrder['HT'] = $linkedOrder->total_ht;
									else $TLinkedOrder['HT'] += $linkedOrder->total_ht;

									if(! isset($TLinkedOrder['TTC'])) $TLinkedOrder['TTC'] = $linkedOrder->total_ttc;
									else $TLinkedOrder['TTC'] += $linkedOrder->total_ttc;

									if(! isset($TLinkedOrder['nbOrder'])) $TLinkedOrder['nbOrder'] = 1;
									else $TLinkedOrder['nbOrder']++;
								}
							}
						}
					}

					if ($tablename != 'expensereport_det' && method_exists($element, 'fetch_thirdparty')) $element->fetch_thirdparty();

					// Define $total_ht_by_line
					if ($tablename == 'don' || $tablename == 'chargesociales' || $tablename == 'payment_various' || $tablename == 'payment_salary') $total_ht_by_line=$element->amount;
					elseif ($tablename == 'fichinter') $total_ht_by_line=$element->getAmount();
					elseif ($tablename == 'stock_mouvement') $total_ht_by_line=$element->price*abs($element->qty);
					elseif ($tablename == 'projet_task')
					{
						$thm = getDolGlobalInt('JPSUN_PROJECT_FORECAST_DEFAULT_THM');
						$total_ht_by_line = price2num(($element->planned_workload / 3600) * $thm, 'MT');
					}
					else $total_ht_by_line=$element->total_ht;

					// Define $total_ttc_by_line
					if ($tablename == 'don' || $tablename == 'chargesociales' || $tablename == 'payment_various' || $tablename == 'payment_salary') $total_ttc_by_line=$element->amount;
					elseif ($tablename == 'fichinter') $total_ttc_by_line=$element->getAmount();
					elseif ($tablename == 'stock_mouvement') $total_ttc_by_line=$element->price*abs($element->qty);
					elseif ($tablename == 'projet_task')
					{
						$defaultvat = get_default_tva($mysoc, $mysoc);
						$total_ttc_by_line = price2num($total_ht_by_line * (1 + ($defaultvat / 100)), 'MT');
					}
					elseif ($tablename == 'supplier_proposal')
					{
						if ($element->statut == SupplierProposal::STATUS_NOTSIGNED) 
						{
							$qualifiedfortotal=false;	// Refused proposal must not be included in total
							$Qty_refused++;
						}
						if ($element->statut == SupplierProposal::STATUS_DRAFT)
						{
							$qualifiedfortotal=false;	// Refused proposal must not be included in total
							$Qty_refused++;
						} 
						else {
							$element->fetchObjectLinked($element->id, 'supplier_proposal', null, 'order_supplier');
							if(! empty($element->linkedObjects['order_supplier'])) {
								foreach($element->linkedObjects['order_supplier'] as $linkedOrder) {
									if(! isset($TLinkedOrder['HT'])) $TLinkedOrder['HT'] = $linkedOrder->total_ht;
									else $TLinkedOrder['HT'] += $linkedOrder->total_ht;

									if(! isset($TLinkedOrder['TTC'])) $TLinkedOrder['TTC'] = $linkedOrder->total_ttc;
									else $TLinkedOrder['TTC'] += $linkedOrder->total_ttc;

									if(! isset($TLinkedOrder['nbOrder'])) $TLinkedOrder['nbOrder'] = 1;
									else $TLinkedOrder['nbOrder']++;
								}
							}
							//$total_ttc_by_line = price2num($total_ht_by_line * (1 + ($defaultvat / 100)), 'MT');
							$total_ttc_by_line=$element->total_ttc;
						}
					}
					else $total_ttc_by_line=$element->total_ttc;



					// Change sign of $total_ht_by_line and $total_ttc_by_line for some cases
					if ($tablename == 'payment_various')
					{
						if ($element->sens == 1)
						{
							$total_ht_by_line = -$total_ht_by_line;
							$total_ttc_by_line = -$total_ttc_by_line;
						}
					}

					// Add total if we have to
					if ($qualifiedfortotal)
					{
						
						$total_ht = $total_ht + $total_ht_by_line;
						$total_ttc = $total_ttc + $total_ttc_by_line;
					}
				}

				$Qty_total = $i - $Qty_refused ;

				// Each element with at least one line is output
				$qualifiedforfinalprofit=true;
				if ($key == 'intervention' && !getDolGlobalInt('PROJECT_INCLUDE_INTERVENTION_AMOUNT_IN_PROFIT')) $qualifiedforfinalprofit=false;
				//var_dump($key);

				// Calculate margin
				if ($qualifiedforfinalprofit)
				{
					if ($margin == 'add') {
						$total_revenue_ht += $total_ht;
					}

					if ($margin != "add")
					{
						$total_ht = -$total_ht;
						$total_ttc = -$total_ttc;
					}

					$balance_ht += $total_ht;
					$balance_ttc += $total_ttc;
				}

				print '<tr class="oddeven forecast" style="display: none">';
				// Module
				if ($tablename == 'commande' || $tablename == 'commande_fournisseur') {
					// $form->textwithpicto($name, $tooltiponprofit)
					print '<td align="left">'.$name.'</td>';
				}
				else
				{
					print '<td align="left">'.$form->textwithpicto($name , $tooltip).'</td>';
				}
				// Nb
				print '<td align="right">'.$Qty_total.'</td>';
				// Amount HT
				print '<td align="right">';
				if (! $qualifiedforfinalprofit) print '<span class="opacitymedium">'.$form->textwithpicto($langs->trans("NA"), $langs->trans("AmountOfInteventionNotIncludedByDefault")).'</span>';
				else print price($total_ht);
				print '</td>';
				// Amount TTC
				print '<td align="right">';
				if (! $qualifiedforfinalprofit) print '<span class="opacitymedium">'.$form->textwithpicto($langs->trans("NA"), $langs->trans("AmountOfInteventionNotIncludedByDefault")).'</span>';
				else print price($total_ttc);
				print '</td>';
				print '</tr>';

				if($key == 'propal' && ! empty($TLinkedOrder)) {
					$balance_ht -= $TLinkedOrder['HT'];
					$balance_ttc -= $TLinkedOrder['TTC'];

					print '<tr class="oddeven forecast" style="display: none">';
					// Module
					print '<td align="left">'.$langs->trans('OrdersFormProposals').'</td>';
					// Nb
					print '<td align="right">'.$TLinkedOrder['nbOrder'].'</td>';
					// Amount HT
					print '<td align="right">';
					if (! $qualifiedforfinalprofit) print '<span class="opacitymedium">'.$form->textwithpicto($langs->trans("NA"), $langs->trans("AmountOfInteventionNotIncludedByDefault")).'</span>';
					else print '-'.price($TLinkedOrder['HT']);
					print '</td>';
					// Amount TTC
					print '<td align="right">';
					if (! $qualifiedforfinalprofit) print '<span class="opacitymedium">'.$form->textwithpicto($langs->trans("NA"), $langs->trans("AmountOfInteventionNotIncludedByDefault")).'</span>';
					else print '-'.price($TLinkedOrder['TTC']);
					print '</td>';
					print '</tr>';
				}
			}
		}
	}

	print '<tr class="liste_total forecast" style="display: none">';
	print '<td align="right" colspan="2" >'.$langs->trans("Profit").'</td>';
	print '<td align="right" >'.price(price2num($balance_ht, 'MT')).'</td>';
	print '<td align="right" >'.price(price2num($balance_ttc, 'MT')).'</td>';
	print '</tr>';

	if ($total_revenue_ht) {
		print '<tr class="liste_total forecast" style="display: none">';
		print '<td class="right" colspan="2">'.$langs->trans("Margin").'</td>';
		print '<td class="right">'.round(100 * $balance_ht / $total_revenue_ht, 1).'%</td>';
		print '<td class="right"></td>';
		print '</tr>';
	}

	print '<tr class="left forecast" style="display: none">';
	print '<th colspan="4" align="center">'.$langs->trans('jpsun_RealProfit').'</th>';
	print '</tr>';
	?>
		<script>
			$(document).ready(function (){
				let benefTitle = $("table.table-fiche-title")[0];
				$(benefTitle).next().prepend($('.forecast'));
				$('.forecast').show();
			})
		</script>
	<?php

}

/**
 * Display title
 * @param string $title
 */
function setup_print_title($title="Parameter", $width = 300)
{
    global $langs;
    print '<tr class="liste_titre">';
	print '<td td class="titlefield">'.$langs->trans($title) . '</td>';
    print '<td td class="titlefield" align="center" width="20">&nbsp;</td>';
    print '<td td class="titlefield" align="center">'.$langs->trans('Value').'</td>';
    print '</tr>';
}

/**
 * yes / no select
 * @param string $confkey
 * @param string $title
 * @param string $desc
 * @param $ajaxConstantOnOffInput will be send to ajax_constantonoff() input param
 *
 * exemple _print_on_off('CONSTNAME', 'ParamLabel' , 'ParamDesc');
 */
function setup_print_on_off($confkey, $title = false, $desc ='', $help = false, $width = 300, $forcereload = false, $ajaxConstantOnOffInput = array())
{
    global $var, $bc, $langs, $conf, $form;
    $var=!$var;

    print '<tr>';
    print '<td>';


	if(empty($help) && !empty($langs->tab_translate[$confkey . '_HELP'])){
		$help = $confkey . '_HELP';
	}

    if(!empty($help)){
        print $form->textwithtooltip( ($title?$title:$langs->trans($confkey)) , $langs->trans($help),2,1,img_help(1,''));
    }
    else {
        print $title?$title:$langs->trans($confkey);
    }

    if(!empty($desc))
    {
        print '<br><small>'.$langs->trans($desc).'</small>';
    }
    print '</td>';
    print '<td align="center" width="20">&nbsp;</td>';
    print '<td align="center" width="'.$width.'">';

    if($forcereload){
        $link = $_SERVER['PHP_SELF'].'?action=set_'.$confkey.'&token='. newToken() .'&'.$confkey.'='.intval((empty($conf->global->{$confkey})));
        $toggleClass = empty($conf->global->{$confkey})?'fa-toggle-off':'fa-toggle-on font-status4';
        print '<a href="'.$link.'" ><span class="fas '.$toggleClass.' marginleftonly" style=" color: #999;"></span></a>';
    }
    else{
        print ajax_constantonoff($confkey, $ajaxConstantOnOffInput);
    }
    print '</td></tr>';
}

/**
 * Auto print form part for setup
 * @param string $confkey
 * @param bool $title
 * @param string $desc
 * @param array $metas exemple use with color array('type'=>'color') or  with placeholder array('placeholder'=>'http://')
 * @param string $type = 'imput', 'textarea' or custom html
 * @param bool $help
 * @param int $width
 */
function setup_print_input_form_part($confkey, $title = false, $desc ='', $metas = array(), $type='input', $help = false, $width = 300)
{
    global $var, $bc, $langs, $conf, $db;
    $var=!$var;

	if(empty($help) && !empty($langs->tab_translate[$confkey . '_HELP'])){
		$help = $confkey . '_HELP';
	}

    $form=new Form($db);

    $defaultMetas = array(
        'name' => $confkey
    );

    if($type!='textarea'){
        $defaultMetas['type']   = 'text';
        $defaultMetas['value']  = isset($conf->global->{$confkey}) ? $conf->global->{$confkey} : '';
    }


    $metas = array_merge ($defaultMetas, $metas);
    $metascompil = '';
    foreach ($metas as $key => $values)
    {
        $metascompil .= ' '.$key.'="'.$values.'" ';
    }

    print '<tr>';
    print '<td>';

    if(!empty($help)){
        print $form->textwithtooltip( ($title?$title:$langs->trans($confkey)) , $langs->trans($help),2,1,img_help(1,''));
    }
    else {
        print $title?$title:$langs->trans($confkey);
    }

    if(!empty($desc))
    {
        print '<br><small>'.$langs->trans($desc).'</small>';
    }

    print '</td>';
    print '<td align="center" width="20">&nbsp;</td>';
    print '<td align="right" width="'.$width.'">';
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" '.($metas['type'] === 'file' ? 'enctype="multipart/form-data"' : '').'>';
    print '<input type="hidden" name="token" value="'. newToken() .'">';
    print '<input type="hidden" name="action" value="set_'.$confkey.'">';

		if($type=='textarea'){
			print '<textarea '.$metascompil.'  >'.dol_htmlentities($conf->global->{$confkey}).'</textarea>';
		}
		elseif($type=='input'){
			print '<input '.$metascompil.'  />';
		}
		else{
			// custom
			print $type;
		}

    print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
    print '</form>';
    print '</td></tr>';
}