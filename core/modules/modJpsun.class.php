<?php
/* Copyright (C) 2024-2025	Pierre Ardoin		<mapoiolca@me.com>

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
 * 		\defgroup   modJpsun    Module Jpsun
 *      \file       htdocs/core/modules/modJpsun.class.php
 *      \ingroup    modJpsun
 *      \brief      Description and activation file for module modJpsun
 */
include_once(DOL_DOCUMENT_ROOT ."/core/modules/DolibarrModules.class.php");

/**
 * 		\class      modJpsun
 *      \brief      Description and activation class for module modJpsun
 */
class modJpsun extends DolibarrModules
{
	/**
	 *   \brief      Constructor. Define names, constants, directories, boxes, permissions
	 *   \param      DB      Database handler
	 */
	function __construct($db)
	{
        global $langs, $conf;

        $this->db = $db;
		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = '999000';
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'jpsun';

		// Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
		// It is used to group modules in module setup page
		$this->family = "JPSUN";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = 'JPSUN';
		// Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
		$this->description = "Module999999Desc";
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = '1.6.6';
		// Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_JPSUN';
		// Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
		$this->special = 3;
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		$this->picto = 'jpsun@jpsun';

		$this->editor_name = 'Pierre ARDOIN pour JPSUN';
		$this->editor_url = 'https://lesmetiersdubatiment.fr';

		// Defined all module parts (triggers, login, substitutions, menus, css, etc...)
		// for default path (eg: /mymodule/core/xxxxx) (0=disable, 1=enable)
		// for specific path of parts (eg: /mymodule/core/modules/barcode)
		// for specific css file (eg: /mymodule/css/mymodule.css.php)
		//$this->module_parts = array(
		//                        	'triggers' => 0,                                 	// Set this to 1 if module has its own trigger directory (core/triggers)
		//							'login' => 0,                                    	// Set this to 1 if module has its own login method directory (core/login)
		//							'substitutions' => 0,                            	// Set this to 1 if module has its own substitution function file (core/substitutions)
		//							'menus' => 0,                                    	// Set this to 1 if module has its own menus handler directory (core/menus)
		//							'theme' => 0,                                    	// Set this to 1 if module has its own theme directory (core/theme)
		//                        	'tpl' => 0,                                      	// Set this to 1 if module overwrite template dir (core/tpl)
		//							'barcode' => 0,                                  	// Set this to 1 if module has its own barcode directory (core/modules/barcode)
		//							'models' => 0,                                   	// Set this to 1 if module has its own models directory (core/modules/xxx)
		//							'css' => array('/mymodule/css/mymodule.css.php'),	// Set this to relative path of css file if module has its own css file
	 	//							'js' => array('/mymodule/js/mymodule.js'),          // Set this to relative path of js file if module must load a js on all pages
		//							'hooks' => array('hookcontext1','hookcontext2')  	// Set here all hooks context managed by module
		//							'workflow' => array('WORKFLOW_MODULE1_YOURACTIONTYPE_MODULE2'=>array('enabled'=>'! empty($conf->module1->enabled) && ! empty($conf->module2->enabled)', 'picto'=>'yourpicto@mymodule')) // Set here all workflow context managed by module
		//                        );
		$this->module_parts = array(
			//'css' => array(''),
			'models' => 1,
			'hooks' => array('projectOverview', 'toprightmenu','data' => array('ajaxonlinesign'), 'entity' => '0',),
			'picto'=>'object_jpsun@jpsun'
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/mymodule/temp");
		$this->dirs = array("/jpsun/temp");
		$r=0;

		// Config pages. Put here list of php page names stored in admmin directory used to setup module.
		$this->config_page_url = array('setup.php@jpsun');

		// Dependencies
		$this->depends = array('modProjet', 'modAgenda');		// List of modules id that must be enabled if this module is enabled
		$this->conflictwith = array();
		$this->phpmin = array(8,0);					// Minimum version of PHP required by module
		$this->need_dolibarr_version = array(20,0);	// Minimum version of Dolibarr required by module
		$this->langfiles = array("jpsun@jpsun", "enedis@jpsun",);

		// Constants
		$this->const = array();

		// To add a new tab identified by code
		$this->tabs = array();
		$this->tabs[] = array(

			'data'=>'project:+projet_enedis:projet_enedis:jpsun@jpsun:$user->rights->jpsun->Enedis->read:/jpsun/tabs/projet_enedis.php?id=__ID__',
			

		); 
		

        // Dictionnaries
        $this->dictionnaries = array();

        // Boxes
		// Add here list of php file(s) stored in includes/boxes that contains class to show a box.
        $this->boxes = array();			// List of boxes

        // Permissions provided by this module
		$this->rights = array();
		$r = 0;
		// Add here entries to declare new permissions
		/* BEGIN MODULEBUILDER PERMISSIONS */
		$o = 1;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', ($o * 10) + 1);
		$this->rights[$r][1] = 'ReadEnedisTab';
		$this->rights[$r][4] = 'Enedis';
		$this->rights[$r][5] = 'read';
		$r++;

		/*
		
		$this->rights[$r][0] = $this->numero . sprintf("%02d", ($o * 10) + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read objects of Vierge'; // Permission label
		$this->rights[$r][4] = 'myobject';
		$this->rights[$r][5] = 'read'; // In php code, permission will be checked by test if ($user->hasRight('vierge', 'myobject', 'read'))
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", ($o * 10) + 2); // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update objects of Vierge'; // Permission label
		$this->rights[$r][4] = 'myobject';
		$this->rights[$r][5] = 'write'; // In php code, permission will be checked by test if ($user->hasRight('vierge', 'myobject', 'write'))
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", ($o * 10) + 3); // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete objects of Vierge'; // Permission label
		$this->rights[$r][4] = 'myobject';
		$this->rights[$r][5] = 'delete'; // In php code, permission will be checked by test if ($user->hasRight('vierge', 'myobject', 'delete'))
		$r++;
		*/
		/* END MODULEBUILDER PERMISSIONS */


		// Main menu entries to add
		$this->menu = array();
		$r = 0;
		// Add here entries to declare new menus
		/* BEGIN MODULEBUILDER TOPMENU */
		/*
		$this->menu[$r++] = array(
			'fk_menu' => '', // Will be stored into mainmenu + leftmenu. Use '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'top', // This is a Top menu entry
			'titre' => 'ModuleJpsunName',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'Jpsun',
			'leftmenu' => '',
			'url' => '/jpsun/jpsunindex.php',
			'langs' => 'jpsun@jpsun', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("jpsun")', // Define condition to show or hide menu entry. Use 'isModEnabled("jpsun")' if entry must be visible if module is enabled.
			'perms' => '1', // Use 'perms'=>'$user->hasRight("jpsun", "myobject", "read")' if you want your menu with a permission rules
			'target' => '',
			'user' => 2, // 0=Menu for internal users, 1=external users, 2=both
		);
		*/
		/* END MODULEBUILDER TOPMENU */

		/* BEGIN MODULEBUILDER LEFTMENU PLANNING */
		
		/* END MODULEBUILDER LEFTMENU PLANNING */

		/* BEGIN MODULEBUILDER LEFTMENU MYOBJECT */
		
		$this->menu[$r++]=array(
			'fk_menu' => 'fk_mainmenu=project,fk_leftmenu=projects',      // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                          // This is a Left menu entry
			'titre' => 'Planning',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle paddingright"'),
			'mainmenu' => 'project',
			'leftmenu' => 'projects',
			'url' => '/projet/ganttview.php',
			'langs' => 'project.lang',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("jpsun")', // Define condition to show or hide menu entry. Use 'isModEnabled("jpsun")' if entry must be visible if module is enabled.
			'perms' => '$user->hasRight("project", "read")',
			'target' => '',
			'user' => 2,				                // 0=Menu for internal users, 1=external users, 2=both
			'object' => 'Project'
		);
		/*
		$this->menu[$r++]=array(
			'fk_menu' => 'fk_mainmenu=jpsun,fk_leftmenu=myobject',	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',			                // This is a Left menu entry
			'titre' => 'New_MyObject',
			'mainmenu' => 'jpsun',
			'leftmenu' => 'jpsun_myobject_new',
			'url' => '/jpsun/myobject_card.php?action=create',
			'langs' => 'jpsun@jpsun',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("jpsun")', // Define condition to show or hide menu entry. Use 'isModEnabled("jpsun")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '$user->hasRight("jpsun", "myobject", "write")'
			'target' => '',
			'user' => 2,				                // 0=Menu for internal users, 1=external users, 2=both
			'object' => 'MyObject'
		);
		$this->menu[$r++]=array(
			'fk_menu' => 'fk_mainmenu=jpsun,fk_leftmenu=myobject',	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',			                // This is a Left menu entry
			'titre' => 'List_MyObject',
			'mainmenu' => 'jpsun',
			'leftmenu' => 'vierge_myobject_list',
			'url' => '/jpsun/myobject_list.php',
			'langs' => 'jpsun@jpsun',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("jpsun")', // Define condition to show or hide menu entry. Use 'isModEnabled("jpsun")' if entry must be visible if module is enabled.
			'perms' => '$user->hasRight("jpsun", "myobject", "read")'
			'target' => '',
			'user' => 2,				                // 0=Menu for internal users, 1=external users, 2=both
			'object' => 'MyObject'
		);
		*/
		/* END MODULEBUILDER LEFTMENU MYOBJECT */
			
	}

	/**
	 *		Function called when module is enabled.
	 *		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *		It also creates data directories.
	 *      @return     int             1 if OK, 0 if KO
	 */
	function init($options = '')
	{
	   global $db, $conf, $langs;

		$sql = array();

		$result = $this->load_tables('jpsun/sql/');

		define('INC_FROM_DOLIBARR', true);

		dol_include_once('/core/class/extrafields.class.php');
		$ext = new ExtraFields($db);
		
		//Proposition Fournisseurs 
		
		    $ext->addExtraField('jpsun_libelle', 'jpsun_libelle', 'varchar', 1, '50', 'supplier_proposal', 0, 0, '', '', 1, '', '-1', 'jpsun_libelle_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled', '',2 );
		    
		    $ext->addExtraField('jpsun_date_de_livraison_souhaitee', 'jpsun_date_de_livraison_souhaitee', 'date', 2, '', 'supplier_proposal', 0, 0, '', '', 1, '', '-1', 'jpsun_date_de_livraison_souhaitee_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled', '', '');

		    $ext->addExtraField('jpsun_link', 'jpsun_link', 'url', 3, '', 'supplier_proposal', 0, 0, '', '', 1, '', '-1', 'jpsun_link_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled', '', 2);
		    
		    $ext->addExtraField('jpsun_n_devis_fourn', 'jpsun_n_devis_fourn', 'varchar', 4, '255', 'supplier_proposal', 0, 0, '', '', 1, '', '-1', 'jpsun_n_devis_fourn_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled', '',2 );
		
		    $ext->addExtraField('jpsun_date_devis_fourn', 'jpsun_date_devis_fourn', 'date', 5, '', 'supplier_proposal', 0, 0, '', '', 1, '', '-1', 'jpsun_date_devis_fourn_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled', '', 2);
		
		//Commandes Fournisseurs
		
		    $ext->addExtraField('jpsun_libelle', 'jpsun_libelle', 'varchar', 1, '50', 'commande_fournisseur', 0, 0, '', '', 1, '', '-1', 'jpsun_libelle_help', '', 0, 'jpsun@jpsun', '$conf->jpsun->enabled', '',2 );
		    
		    $ext->addExtraField('jpsun_date_de_livraison_souhaitee', 'jpsun_date_de_livraison_souhaitee', 'date', 2, '', 'commande_fournisseur', 0, 0, '', '', 1, '', '-1', 'jpsun_date_de_livraison_souhaitee_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled', '', '');
		
		    $ext->addExtraField('jpsun_link', 'jpsun_link', 'url', 3, '', 'commande_fournisseur', 0, 0, '', '', 1, '', '-1', 'jpsun_link_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled', '', 2);
		    
		    $ext->addExtraField('jpsun_n_devis_fourn', 'jpsun_n_devis_fourn', 'varchar', 4, '255', 'commande_fournisseur', 0, 0, '', '', 1, '', '-1', 'jpsun_n_devis_fourn_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled', '', 2);
		
		    $ext->addExtraField('jpsun_date_devis_fourn', 'jpsun_date_devis_fourn', 'date', 5, '', 'commande_fournisseur', 0, 0, '', '', 1, '', '-1', 'jpsun_date_devis_fourn_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled', '', 2);

		//Produits
		
		    $ext->addExtraField('jpsun_marque', 'jpsun_marque', 'varchar', 100, '255', 'product', 0, 0, '', '', 1, '', '-1', 'jpsun_marque_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled');
		    $ext->addExtraField('jpsun_module_pv_pc', 'jpsun_module_pv_pc', 'int', 1, '4', 'product', 0, 0, '', '', 1, '', '($object->finished == 2 ? -1:0)', 'jpsun_module_pv_pc_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled');
		    $ext->addExtraField('jpsun_productdet', 'jpsun_ProductDet', 'html', 1, '2000', 'product', 0, 0, '', '', 1, '', '-1', 'jpsun_ProductDet_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled');
		    
		//Projets 
		    
		    $ext->addExtraField('jpsun_project_pc', 'jpsun_project_pc', 'varchar', 98, '255', 'projet', 0, 0, '', '', 1, '', '-1', 'jpsun_project_pc_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled');
		    $ext->addExtraField('jpsun_project_n_ddr', 'jpsun_project_n_ddr', 'varchar', 99, '18', 'projet', 0, 0, '', '', 1, '', '-4', 'jpsun_project_n_ddr_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled');
		    $ext->addExtraField('jpsun_project_t0', 'jpsun_project_t0', 'date', 100, '', 'projet', 0, 0, '', '', 1, '', '-4', 'jpsun_project_t0_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled');
		    $ext->addExtraField('jpsun_project_prix_rachat', 'jpsun_project_prix_rachat', 'price', 101, '', 'projet', 0, 0, '', '', 1, '', '-4', 'jpsun_project_prix_rachat_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled');
		    $ext->addExtraField('jpsun_project_type_racc', 'jpsun_project_type_racc', 'select', 102, '', 'projet', 0, 0, '', 'a:1:{s:7:"options";a:3:{i:1;s:26:"jpsun_project_type_racc_vt";i:2;s:26:"jpsun_project_type_racc_as";i:3;s:26:"jpsun_project_type_racc_at";}}', 1, '', '-1', 'jpsun_project_pc_help', '',  $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled');
		    $ext->addExtraField('jpsun_project_mes_date', 'jpsun_project_mes_date', 'date', 103, '', 'projet', 0, 0, '', '', 1, '', '-4', 'jpsun_project_mes_date_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled');
		    
	    //Utilisateurs
	    
	        $ext->addExtraField('jpsun_user_monogramme', 'jpsun_user_monogramme', 'varchar', 100, '2', 'user', 0, 0, '', '', 1, '', '-4', 'jpsun_user_monogramme_help', '', $conf->entity, 'jpsun@jpsun', '$conf->jpsun->enabled');

		// EN: Load contract extrafields and ensure idempotent setup.
		// FR: Charger les extrafields contrats et assurer une installation idempotente.
		$ext->fetch_name_optionals_label('contrat');
		$fields = array(
			'jpsun_site_name' => array(
				'label' => 'JpsunContractSiteName',
				'type' => 'varchar',
				'pos' => 100,
				'size' => 50,
				'elementtype' => 'contrat',
				'unique' => 0,
				'required' => 1,
				'default_value' => '',
				'param' => '',
				'alwayseditable' => 1,
				'perms' => '',
				'list' => 1,
				'help' => '',
				'computed' => '',
				'entity' => 0,
				'langfile' => 'jpsun@jpsun',
				'enabled' => '$conf->jpsun->enabled',
				'totalizable' => 0,
				'printable' => 0,
			),
			'jpsun_distance_company_km' => array(
				'label' => 'JpsunContractDistanceFromCompanyKm',
				'type' => 'double',
				'pos' => 101,
				'size' => '24,8',
				'elementtype' => 'contrat',
				'unique' => 0,
				'required' => 1,
				'default_value' => '',
				'param' => '',
				'alwayseditable' => 1,
				'perms' => '',
				'list' => -1,
				'help' => '',
				'computed' => '',
				'entity' => 0,
				'langfile' => 'jpsun@jpsun',
				'enabled' => '$conf->jpsun->enabled',
				'totalizable' => 0,
				'printable' => 0,
			),
			'jpsun_installed_power_kwc' => array(
				'label' => 'JpsunContractInstalledPowerKwc',
				'type' => 'double',
				'pos' => 102,
				'size' => '24,8',
				'elementtype' => 'contrat',
				'unique' => 0,
				'required' => 1,
				'default_value' => '',
				'param' => '',
				'alwayseditable' => 1,
				'perms' => '',
				'list' => -1,
				'help' => '',
				'computed' => '',
				'entity' => 0,
				'langfile' => 'jpsun@jpsun',
				'enabled' => '$conf->jpsun->enabled',
				'totalizable' => 0,
				'printable' => 0,
			),
			'jpsun_revaluation_index_sn' => array(
				'label' => 'JpsunContractRevaluationIndexSn',
				'type' => 'double',
				'pos' => 103,
				'size' => '24,8',
				'elementtype' => 'contrat',
				'unique' => 0,
				'required' => 1,
				'default_value' => '',
				'param' => '',
				'alwayseditable' => 1,
				'perms' => '',
				'list' => -1,
				'help' => '',
				'computed' => '',
				'entity' => 0,
				'langfile' => 'jpsun@jpsun',
				'enabled' => '$conf->jpsun->enabled',
				'totalizable' => 0,
				'printable' => 0,
			),
			'jpsun_pv_module_product' => array(
				'label' => 'JpsunContractPvModules',
				'type' => 'sellist',
				'pos' => 104,
				'size' => '',
				'elementtype' => 'contrat',
				'unique' => 0,
				'required' => 1,
				'default_value' => '',
				'param' => 'a:1:{s:7:"options";a:1:{s:33:"product:ref:rowid::(finished:=:3)";N;}}',
				'alwayseditable' => 1,
				'perms' => '',
				'list' => -1,
				'help' => '',
				'computed' => '',
				'entity' => 0,
				'langfile' => 'jpsun@jpsun',
				'enabled' => '$conf->jpsun->enabled',
				'totalizable' => 0,
				'printable' => 0,
			),
			'jpsun_pv_module_qty' => array(
				'label' => 'JpsunContractPvModulesQty',
				'type' => 'int',
				'pos' => 105,
				'size' => 10,
				'elementtype' => 'contrat',
				'unique' => 0,
				'required' => 1,
				'default_value' => '',
				'param' => '',
				'alwayseditable' => 1,
				'perms' => '',
				'list' => -1,
				'help' => '',
				'computed' => '',
				'entity' => 0,
				'langfile' => 'jpsun@jpsun',
				'enabled' => '$conf->jpsun->enabled',
				'totalizable' => 0,
				'printable' => 0,
			),
			'jpsun_inverter_product' => array(
				'label' => 'JpsunContractInverters',
				'type' => 'sellist',
				'pos' => 106,
				'size' => '',
				'elementtype' => 'contrat',
				'unique' => 0,
				'required' => 1,
				'default_value' => '',
				'param' => 'a:1:{s:7:"options";a:1:{s:34:"product:ref|label:rowid::(finished:=:4)";N;}}',
				'alwayseditable' => 1,
				'perms' => '',
				'list' => -1,
				'help' => '',
				'computed' => '',
				'entity' => 0,
				'langfile' => 'jpsun@jpsun',
				'enabled' => '$conf->jpsun->enabled',
				'totalizable' => 0,
				'printable' => 0,
			),
			'jpsun_inverter_qty' => array(
				'label' => 'JpsunContractInvertersQty',
				'type' => 'int',
				'pos' => 107,
				'size' => 10,
				'elementtype' => 'contrat',
				'unique' => 0,
				'required' => 1,
				'default_value' => '',
				'param' => '',
				'alwayseditable' => 1,
				'perms' => '',
				'list' => -1,
				'help' => '',
				'computed' => '',
				'entity' => 0,
				'langfile' => 'jpsun@jpsun',
				'enabled' => '$conf->jpsun->enabled',
				'totalizable' => 0,
				'printable' => 0,
			),
			'jpsun_inverter_install_height_m' => array(
				'label' => 'JpsunContractInvertersInstallHeightM',
				'type' => 'double',
				'pos' => 108,
				'size' => '24,8',
				'elementtype' => 'contrat',
				'unique' => 0,
				'required' => 1,
				'default_value' => '',
				'param' => '',
				'alwayseditable' => 1,
				'perms' => '',
				'list' => -1,
				'help' => '',
				'computed' => '',
				'entity' => 0,
				'langfile' => 'jpsun@jpsun',
				'enabled' => '$conf->jpsun->enabled',
				'totalizable' => 0,
				'printable' => 0,
			),
			'jpsun_dc_boxes_qty' => array(
				'label' => 'JpsunContractDcBoxesQty',
				'type' => 'int',
				'pos' => 109,
				'size' => 10,
				'elementtype' => 'contrat',
				'unique' => 0,
				'required' => 1,
				'default_value' => '',
				'param' => '',
				'alwayseditable' => 1,
				'perms' => '',
				'list' => -1,
				'help' => '',
				'computed' => '',
				'entity' => 0,
				'langfile' => 'jpsun@jpsun',
				'enabled' => '$conf->jpsun->enabled',
				'totalizable' => 0,
				'printable' => 0,
			),
			'jpsun_dc_box_install_height_m' => array(
				'label' => 'JpsunContractDcBoxInstallHeightM',
				'type' => 'double',
				'pos' => 110,
				'size' => '24,8',
				'elementtype' => 'contrat',
				'unique' => 0,
				'required' => 1,
				'default_value' => '',
				'param' => '',
				'alwayseditable' => 1,
				'perms' => '',
				'list' => -1,
				'help' => '',
				'computed' => '',
				'entity' => 0,
				'langfile' => 'jpsun@jpsun',
				'enabled' => '$conf->jpsun->enabled',
				'totalizable' => 0,
				'printable' => 0,
			),
			'jpsun_ac_boxes_qty' => array(
				'label' => 'JpsunContractAcBoxesQty',
				'type' => 'int',
				'pos' => 111,
				'size' => 10,
				'elementtype' => 'contrat',
				'unique' => 0,
				'required' => 1,
				'default_value' => '',
				'param' => '',
				'alwayseditable' => 1,
				'perms' => '',
				'list' => -1,
				'help' => '',
				'computed' => '',
				'entity' => 0,
				'langfile' => 'jpsun@jpsun',
				'enabled' => '$conf->jpsun->enabled',
				'totalizable' => 0,
				'printable' => 0,
			),
			'jpsun_ac_box_install_height_m' => array(
				'label' => 'JpsunContractAcBoxInstallHeightM',
				'type' => 'double',
				'pos' => 112,
				'size' => '24,8',
				'elementtype' => 'contrat',
				'unique' => 0,
				'required' => 1,
				'default_value' => '',
				'param' => '',
				'alwayseditable' => 1,
				'perms' => '',
				'list' => -1,
				'help' => '',
				'computed' => '',
				'entity' => 0,
				'langfile' => 'jpsun@jpsun',
				'enabled' => '$conf->jpsun->enabled',
				'totalizable' => 0,
				'printable' => 0,
			),
			'jpsun_access_code' => array(
				'label' => 'JpsunContractAccessCode',
				'type' => 'varchar',
				'pos' => 113,
				'size' => 255,
				'elementtype' => 'contrat',
				'unique' => 0,
				'required' => 1,
				'default_value' => '',
				'param' => '',
				'alwayseditable' => 1,
				'perms' => '',
				'list' => -1,
				'help' => '',
				'computed' => '',
				'entity' => 0,
				'langfile' => 'jpsun@jpsun',
				'enabled' => '$conf->jpsun->enabled',
				'totalizable' => 0,
				'printable' => 0,
			),
			'jpsun_pdl_number' => array(
				'label' => 'JpsunContractPdlNumber',
				'type' => 'varchar',
				'pos' => 114,
				'size' => 14,
				'elementtype' => 'contrat',
				'unique' => 0,
				'required' => 1,
				'default_value' => '',
				'param' => '',
				'alwayseditable' => 1,
				'perms' => '',
				'list' => -1,
				'help' => '',
				'computed' => '',
				'entity' => 0,
				'langfile' => 'jpsun@jpsun',
				'enabled' => '$conf->jpsun->enabled',
				'totalizable' => 0,
				'printable' => 0,
			),
		);

		// EN: Create or update extrafields to keep setup idempotent.
		// FR: Créer ou mettre à jour les extrafields pour garantir l'idempotence.
		foreach ($fields as $attrname => $field) {
			if (empty($ext->attributes['contrat']['label'][$attrname])) {
				$ext->addExtraField(
					$attrname,
					$field['label'],
					$field['type'],
					$field['pos'],
					$field['size'],
					$field['elementtype'],
					$field['unique'],
					$field['required'],
					$field['default_value'],
					$field['param'],
					$field['alwayseditable'],
					$field['perms'],
					$field['list'],
					$field['help'],
					$field['computed'],
					$field['entity'],
					$field['langfile'],
					$field['enabled'],
					$field['totalizable'],
					$field['printable']
				);
			} else {
				$ext->update(
					$attrname,
					$field['label'],
					$field['type'],
					$field['pos'],
					$field['size'],
					$field['elementtype'],
					$field['unique'],
					$field['required'],
					$field['default_value'],
					$field['param'],
					$field['alwayseditable'],
					$field['perms'],
					$field['list'],
					$field['help'],
					$field['computed'],
					$field['entity'],
					$field['langfile'],
					$field['enabled'],
					$field['totalizable'],
					$field['printable']
				);
			}
		}

		//$ext->addExtraField($attrname, 02 $label, 03 $type, 04 $pos, 05 $size, 06 $element, 07 $unique, 08 $required, 09 $default_value, 10 $param, 11 $alwayseditable, 12 $perms, 13 $list, 14 $help, 15 $computed, 16 $entity, 17 $langfile, 18 $enabled, 19 $sommable, 20 $PDF)
		return $this->_init($sql);
	}

	/**
	 *		Function called when module is disabled.
	 *      Remove from database constants, boxes and permissions from Dolibarr database.
	 *		Data directories are not deleted.
	 *      @return     int             1 if OK, 0 if KO
	 */
	function remove($options = '')
	{
		//$sql = array();

		return $this->_remove($sql);
	}


	/**
	 *		\brief		Create tables, keys and data required by module
	 * 					Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
	 * 					and create data commands must be stored in directory /mymodule/sql/
	 *					This function is called by this->init.
	 * 		\return		int		<=0 if KO, >0 if OK
	 */

	function load_tables()
	{
		return $this->_load_tables('/jpsun/sql/');
	}
}

?>

