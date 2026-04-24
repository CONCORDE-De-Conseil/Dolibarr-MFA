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
        $this->version = '1.0';
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->special = 0;
        $this->picto = 'email'; // We use email picto for now

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
                // '/mfa/css/mfa.css.php',
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
                    'globalcard'
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
