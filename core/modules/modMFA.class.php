<?php
/* Copyright (C) 2026 CONCORDE de Conseil <contact@concorde.tn> */

/**
 *	\file       htdocs/custom/mfa/core/modules/modMFA.class.php
 *	\ingroup    mfa
 *	\brief      Module descriptor for MFA.
 */
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 *	Class to describe MFA module
 */
class modMFA extends DolibarrModules
{
    /**
     *   Constructor. Define the module description.
     *
     *   @param      DoliDB      $db      Database handler
     */
    public function __construct($db)
    {
        global $conf, $langs;

        $this->db = $db;
        $this->numero = 2026006; // Unique ID for the module
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->family = "hr";
        $this->description = "Multi-Factor Authentication for Dolibarr users using TOTP";
        $this->version = '1.1';
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->special = 0;
        $this->picto = 'mfa'; // We use email picto for now

        // Module parts (hooks, login backend, etc)
        $this->module_parts = array(
            // Set this to 1 if module has its own trigger directory (core/triggers)
            'triggers' => 1,
            // Set this to 1 if module has its own login method file (core/login)
            'login' => 1,
            // Set this to 1 if module has its own substitution function file (core/substitutions)
            'substitutions' => 0,
            // Set this to 1 if module has its own menus handler directory (core/menus)
            'menus' => 0,
            // Set this to 1 if module overwrite template dir (core/tpl)
            'tpl' => 1,
            // Set this to 1 if module has its own barcode directory (core/modules/barcode)
            'barcode' => 0,
            // Set this to 1 if module has its own models directory (core/modules/xxx)
            'models' => 1,
            // Set this to 1 if module has its own printing directory (core/modules/printing)
            'printing' => 0,
            // Set this to 1 if module has its own theme directory (theme)
            'theme' => 0,
            // Set this to relative path of css file if module has its own css file
            'css' => array(
                '/mfa/css/mfa.css.php',
            ),
            // Set this to relative path of js file if module must load a js on all pages
            'js' => array(
                //'/mfa/js/mfa.js.php',
            ),
            'hooks' => array(
                'data' => array(
                    'mainloginpage',
                    'login',
                    'usercard',
                ),
                //   'entity' => '0',
            ),
            // 'login' => array('mfa' => '/mfa/core/login/functions_mfa.php'),
            // Set this to 1 if features of module are opened to external users
            'moduleforexternal' => 0,
            // Set this to 1 if the module provides a website template into doctemplates/websites/website_template-mytemplate
            'websitetemplates' => 0
        );

        $this->dirs = array('/mfa');

        // Config page
        $this->config_page_url = array("setup.php@mfa");

        $this->depends = array();
        $this->requiredby = array();
        $this->phpmin = array(7, 0);
        $this->langfiles = array("mfa@mfa");

        $this->tabs = array();
        $this->dictionaries = array();
        $this->flags = array();
        $this->rights = array();


        // Main menu entries to add
        $this->menu = array();
        $r = 0;
        // Add here entries to declare new menus
        /* BEGIN MODULEBUILDER TOPMENU */
        $this->menu[$r++] = array(
            'fk_menu' => '', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
            'type' => 'top', // This is a Top menu entry
            'titre' => 'MFA',
            'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
            'mainmenu' => 'mfa',
            'leftmenu' => '',
            'url' => '/mfa/admin/setup.php',
            'langs' => 'mfa@mfa', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("mfa")', // Define condition to show or hide menu entry. Use 'isModEnabled("mfa")' if entry must be visible if module is enabled.
            'perms' => '$user->admin',
            'target' => '',
            'user' => 0, // 0=Menu for internal users, 1=external users, 2=both
        );
        /* END MODULEBUILDER TOPMENU */

        /* BEGIN MODULEBUILDER LEFTMENU MFA */
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=mfa',
            'type' => 'left',
            'titre' => 'Setup',
            'prefix' => img_picto('', 'lock', 'class="paddingright pictofixedwidth valignmiddle"'),
            'mainmenu' => 'mfa',
            'leftmenu' => 'mfa',
            'url' => '/mfa/admin/setup.php',
            'langs' => 'mfa@mfa',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("mfa")',
            'perms' => '$user->admin',
            'target' => '',
            'user' => 2,
            'object' => ''
        );

        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=mfa',
            'type' => 'left',
            'titre' => 'Attempts',
            'prefix' => img_picto('', 'lock', 'class="paddingright pictofixedwidth valignmiddle"'),
            'mainmenu' => 'mfa',
            'leftmenu' => 'mfa',
            'url' => '/mfa/admin/attempts.php',
            'langs' => 'mfa@mfa',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("mfa")',
            'perms' => '$user->admin',
            'target' => '',
            'user' => 2,
            'object' => ''
        );


        $this->rights_class = 'mfa';
    }

    /**
     *	Function called when module is enabled.
     *	The init function loads the sql file for table creation.
     *
     *	@param	string	$options	Options
     *	@return	int					1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        $result = $this->_load_tables('/mfa/sql/');
        if ($result < 0) {
            return 0;
        }

        return parent::init($options);
    }

    /**
     *	Function called when module is disabled.
     *
     *	@param	string	$options	Options
     *	@return	int					1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        return parent::remove($options);
    }
}
