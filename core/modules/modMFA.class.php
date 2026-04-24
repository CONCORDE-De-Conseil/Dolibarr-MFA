<?php
/* Copyright (C) 2026 CONCORDE de Conseil <contact@concorde.tn> */

/**
 *	\file       htdocs/custom/mfa/core/modules/modMFA.class.php
 *	\ingroup    mfa
 *	\brief      Module descriptor for MFA.
 */
require_once DOL_DOCUMENT_ROOT . '/core/modules/modules.class.php';

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
            'hooks' => array('mainloginpage', 'login', 'usercard', 'globalcard'),
            'login' => array('mfa' => '/mfa/core/login/functions_mfa.php'),
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
