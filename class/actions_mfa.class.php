<?php
/* Copyright (C) 2023		Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2026		Alice Adminson				<laurent@destailleur.fr>
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    mfa/class/actions_mfa.class.php
 * \ingroup mfa
 * \brief   Example hook overload.
 *
 * TODO: Write detailed description here.
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonhookactions.class.php';

/**
 * Class ActionsMFA
 */
class ActionsMFA extends CommonHookActions
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;

    /**
     * @var string Error code (or message)
     */
    public $error = '';

    /**
     * @var string[] Errors
     */
    public $errors = array();


    /**
     * @var mixed[] Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public $results = array();

    /**
     * @var ?string String displayed by executeHook() immediately after return
     */
    public $resprints;

    /**
     * @var int		Priority of hook (50 is used if value is not defined)
     */
    public $priority;


    /**
     * Constructor
     *
     *  @param	DoliDB	$db      Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }


    /**
     * Execute action
     *
     * @param	array<string,mixed>	$parameters	Array of parameters
     * @param	CommonObject		$object		The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param	string				$action		'add', 'update', 'view'
     * @return	int								Return integer <0 if KO,
     *                           				=0 if OK but we want to process standard actions too,
     *											>0 if OK and we want to replace standard actions.
     */
    public function getNomUrl($parameters, &$object, &$action)
    {
        global $db, $langs, $conf, $user;
        $this->resprints = '';
        return 0;
    }

    /**
     * Inject MFA field into login form
     *
     * @param array  $parameters Hook parameters
     * @param object $object     Object
     * @param string $action     Action
     * @param object $hookmanager Hook manager
     * @return int               Return 0
     */
    public function mainloginpage($parameters, &$object, &$action, $hookmanager)
    {
        if ($parameters['attribute'] == 'login_extra_fields') {
            global $langs;
            $langs->load("mfa@mfa");
            print '<div class="login_main_field">';
            print '<label for="mfa_code">' . $langs->trans("MFACode") . ' (' . $langs->trans("IfEnabled") . ')</label>';
            print '<input type="text" name="mfa_code" id="mfa_code" class="flat" maxlength="6" autocomplete="one-time-code" placeholder="123456">';
            print '</div>';
        }
        return 0;
    }

    /**
     * Add MFA management on user card
     *
     * @param array  $parameters Hook parameters
     * @param object $object     Object (User)
     * @param string $action     Action
     * @param object $hookmanager Hook manager
     * @return int               Return 0
     */
    public function showOutputExtraField($parameters, &$object, &$action, $hookmanager)
    {
        // var_dump($parameters, $object, $action, $hookmanager); // Debug line to inspect parameters
        if (in_array('usercard', $hookmanager->contextarray)) {
            global $langs, $user;
            $langs->load("mfa@mfa");

            require_once dol_buildpath('/mfa/class/mfaservice.class.php');
            $mfaService = new MFAService($this->db);
            $mfa = $mfaService->getForUser($object->id, $object->entity);

            $id = GETPOST('id', 'int');
            $action = GETPOST('action', 'alpha');

            print '<!-- MFA Section -->';
            print '<tr class="trextrafields"><td class="titlefield">' . $langs->trans("MFAStatus") . '</td>';
            print '<td>';
            if ($mfa && $mfa->enabled) {
                print '<span class="badge badge-status status4">' . $langs->trans("Enabled") . '</span>';
                if ($user->admin || $user->id == $id) {
                    print ' <a class="butActionDelete" href="' . $_SERVER["PHP_SELF"] . '?id=' . $id . '&action=disablemfa&token=' . newToken() . '">' . $langs->trans("Disable") . '</a>';
                }
            } else {
                print '<span class="badge badge-status status5">' . $langs->trans("Disabled") . '</span>';
                if ($user->admin || $user->id == $id) {
                    print ' <a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $id . '&action=setupmfa&token=' . newToken() . '">' . $langs->trans("SetupMFA") . '</a>';
                }
            }
            print '</td></tr>';
            var_dump($action);
            if ($action == 'setupmfa') {

                var_dump('FFFFF');
                $secret = $mfaService->generateSecret();
                $uri = $mfaService->getProvisioningUri($object->login, $secret);

                print '<tr class="trextrafields"><td class="titlefield">' . $langs->trans("MFASecret") . '</td><td>';
                print '<code>' . $secret . '</code>';
                print '<div class="opacitymedium small">' . $langs->trans("ScanThisUri") . ': <br>' . $uri . '</div>';
                print '<form action="' . $_SERVER["PHP_SELF"] . '" method="POST">';
                print '<input type="hidden" name="id" value="' . $object->id . '">';
                print '<input type="hidden" name="action" value="enablemfa">';
                print '<input type="hidden" name="token" value="' . newToken() . '">';
                print '<input type="hidden" name="mfa_secret" value="' . $secret . '">';
                print '<input type="text" name="mfa_verif" placeholder="' . $langs->trans("EnterVerifyCode") . '" class="flat" maxlength="6"> ';
                print '<input type="submit" class="button" value="' . $langs->trans("VerifyAndEnable") . '">';
                print '</form>';
                print '</td></tr>';
            }
        }
        return 0;
    }

    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        if ($action == 'enablemfa') {
            require_once dol_buildpath('/mfa/class/mfaservice.class.php');
            $mfaService = new MFAService($this->db);
            $secret = GETPOST('mfa_secret', 'alphanohtml');
            $code = GETPOST('mfa_verif', 'alphanohtml');
            if ($mfaService->verifyCode($secret, $code)) {
                $mfaService->enableForUser($object, $secret);
                setEventMessages("MFA Enabled", null);
            } else {
                setEventMessages("Invalid Code", null, 'errors');
            }
        }
    }


    /**
     * Overload the doMassActions function : replacing the parent's function with the one below
     *
     * @param	array<string,mixed>	$parameters		Hook metadata (context, etc...)
     * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param	?string				$action			Current action (if set). Generally create or edit or null
     * @param	HookManager			$hookmanager	Hook manager propagated to allow calling another hook
     * @return	int									Return integer < 0 on error, 0 on success, 1 to replace standard code
     */
    public function doMassActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $error = 0; // Error counter

        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {        // do something only for the context 'somecontext1' or 'somecontext2'
            // @phan-suppress-next-line PhanPluginEmptyStatementForeachLoop
            foreach ($parameters['toselect'] as $objectid) {
                // Do action on each object id
            }

            if (!$error) {
                $this->results = array('myreturn' => 999);
                $this->resprints = 'A text to show';
                return 0; // or return 1 to replace standard code
            } else {
                $this->errors[] = 'Error message';
                return -1;
            }
        }

        return 0;
    }


    /**
     * Overload the addMoreMassActions function : replacing the parent's function with the one below
     *
     * @param	array<string,mixed>	$parameters     Hook metadata (context, etc...)
     * @param	CommonObject		$object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param	?string				$action			Current action (if set). Generally create or edit or null
     * @param	HookManager			$hookmanager	Hook manager propagated to allow calling another hook
     * @return	int									Return integer < 0 on error, 0 on success, 1 to replace standard code
     */
    public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;

        $error = 0; // Error counter
        $disabled = 1;

        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {        // do something only for the context 'somecontext1' or 'somecontext2'
            $this->resprints = '<option value="0"' . ($disabled ? ' disabled="disabled"' : '') . '>' . $langs->trans("MFAMassAction") . '</option>';
        }

        if (!$error) {
            return 0; // or return 1 to replace standard code
        } else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }



    /**
     * Execute action before PDF (document) creation
     *
     * @param	array<string,mixed>	$parameters	Array of parameters
     * @param	CommonObject		$object		Object output on PDF
     * @param	string				$action		'add', 'update', 'view'
     * @return	int								Return integer <0 if KO,
     *											=0 if OK but we want to process standard actions too,
     *											>0 if OK and we want to replace standard actions.
     */
    public function beforePDFCreation($parameters, &$object, &$action)
    {
        global $conf, $user, $langs;
        global $hookmanager;

        $outputlangs = $langs;

        $ret = 0;
        $deltemp = array();
        dol_syslog(get_class($this) . '::executeHooks action=' . $action);

        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        // @phan-suppress-next-line PhanPluginEmptyStatementIf
        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {
            // do something only for the context 'somecontext1' or 'somecontext2'
        }

        return $ret;
    }

    /**
     * Execute action after PDF (document) creation
     *
     * @param	array<string,mixed>	$parameters	Array of parameters
     * @param	CommonDocGenerator	$pdfhandler	PDF builder handler
     * @param	string				$action		'add', 'update', 'view'
     * @return	int								Return integer <0 if KO,
     * 											=0 if OK but we want to process standard actions too,
     *											>0 if OK and we want to replace standard actions.
     */
    public function afterPDFCreation($parameters, &$pdfhandler, &$action)
    {
        global $conf, $user, $langs;
        global $hookmanager;

        $outputlangs = $langs;

        $ret = 0;
        $deltemp = array();
        dol_syslog(get_class($this) . '::executeHooks action=' . $action);

        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        // @phan-suppress-next-line PhanPluginEmptyStatementIf
        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {
            // do something only for the context 'somecontext1' or 'somecontext2'
        }

        return $ret;
    }



    /**
     * Overload the loadDataForCustomReports function : returns data to complete the customreport tool
     *
     * @param	array<string,mixed>	$parameters		Hook metadata (context, etc...)
     * @param	?string				$action 		Current action (if set). Generally create or edit or null
     * @param	HookManager			$hookmanager    Hook manager propagated to allow calling another hook
     * @return	int									Return integer < 0 on error, 0 on success, 1 to replace standard code
     */
    public function loadDataForCustomReports($parameters, &$action, $hookmanager)
    {
        global $langs;

        $langs->load("mfa@mfa");

        $this->results = array();

        $head = array();
        $h = 0;

        if ($parameters['tabfamily'] == 'mfa') {
            $head[$h][0] = dol_buildpath('/module/index.php', 1);
            $head[$h][1] = $langs->trans("Home");
            $head[$h][2] = 'home';
            $h++;

            $this->results['title'] = $langs->trans("MFA");
            $this->results['picto'] = 'mfa@mfa';
        }

        $head[$h][0] = 'customreports.php?objecttype=' . $parameters['objecttype'] . (empty($parameters['tabfamily']) ? '' : '&tabfamily=' . $parameters['tabfamily']);
        $head[$h][1] = $langs->trans("CustomReports");
        $head[$h][2] = 'customreports';

        $this->results['head'] = $head;

        $arrayoftypes = array();
        //$arrayoftypes['mfa_myobject'] = array('label' => 'MyObject', 'picto'=>'myobject@mfa', 'ObjectClassName' => 'MyObject', 'enabled' => isModEnabled('mfa'), 'ClassPath' => "/mfa/class/myobject.class.php", 'langs'=>'mfa@mfa')

        $this->results['arrayoftype'] = $arrayoftypes;

        return 0;
    }



    /**
     * Overload the restrictedArea function : check permission on an object
     *
     * @param	array<string,mixed>	$parameters		Hook metadata (context, etc...)
     * @param   CommonObject    	$object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param	string				$action			Current action (if set). Generally create or edit or null
     * @param	HookManager			$hookmanager	Hook manager propagated to allow calling another hook
     * @return	int									Return integer <0 if KO,
     *												=0 if OK but we want to process standard actions too,
     *												>0 if OK and we want to replace standard actions.
     */
    public function restrictedArea($parameters, $object, &$action, $hookmanager)
    {
        global $user;

        if ($parameters['features'] == 'myobject') {
            if ($user->hasRight('mfa', 'myobject', 'read')) {
                $this->results['result'] = 1;
                return 1;
            } else {
                $this->results['result'] = 0;
                return 1;
            }
        }

        return 0;
    }

    /**
     * Execute action completeTabsHead
     *
     * @param	array<string,mixed>	$parameters		Array of parameters
     * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param	string				$action			'add', 'update', 'view'
     * @param	HookManager			$hookmanager	Hookmanager
     * @return	int									Return integer <0 if KO,
     *												=0 if OK but we want to process standard actions too,
     *												>0 if OK and we want to replace standard actions.
     */
    public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $conf, $user;

        if (!isset($parameters['object']->element)) {
            return 0;
        }
        if ($parameters['mode'] == 'remove') {
            // used to make some tabs removed
            return 0;
        } elseif ($parameters['mode'] == 'add') {
            $langs->load('mfa@mfa');
            // used when we want to add some tabs
            $counter = count($parameters['head']);
            $element = $parameters['object']->element;
            $id = $parameters['object']->id;
            // verifier le type d'onglet comme member_stats où ça ne doit pas apparaitre
            // if (in_array($element, ['societe', 'member', 'contrat', 'fichinter', 'project', 'propal', 'commande', 'facture', 'order_supplier', 'invoice_supplier'])) {
            if (in_array($element, ['context1', 'context2'])) {
                $datacount = 0;

                $parameters['head'][$counter][0] = dol_buildpath('/mfa/mfa_tab.php', 1) . '?id=' . $id . '&amp;module=' . $element;
                $parameters['head'][$counter][1] = $langs->trans('MFATab');
                if ($datacount > 0) {
                    $parameters['head'][$counter][1] .= '<span class="badge marginleftonlyshort">' . $datacount . '</span>';
                }
                $parameters['head'][$counter][2] = 'mfaemails';
                $counter++;
            }
            if ($counter > 0 && (int) DOL_VERSION < 14) {  // @phpstan-ignore-line
                $this->results = $parameters['head'];
                // return 1 to replace standard code
                return 1;
            } else {
                // From V14 onwards, $parameters['head'] is modifiable by reference
                return 0;
            }
        } else {
            // Bad value for $parameters['mode']
            return -1;
        }
    }


    /**
     * Overload the showLinkToObjectBlock function : add or replace array of object linkable
     *
     * @param	array<string,mixed>	$parameters		Hook metadata (context, etc...)
     * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param	?string				$action			Current action (if set). Generally create or edit or null
     * @param	HookManager			$hookmanager	Hook manager propagated to allow calling another hook
     * @return	int									Return integer < 0 on error, 0 on success, 1 to replace standard code
     */
    public function showLinkToObjectBlock($parameters, &$object, &$action, $hookmanager)
    {
        $myobject = new MyObject($object->db);
        $this->results = array('myobject@mfa' => array(
            'enabled' => isModEnabled('mfa'),
            'perms' => 1,
            'label' => 'LinkToMyObject',
            'sql' => "SELECT t.rowid, t.ref, t.ref as 'name' FROM " . $this->db->prefix() . $myobject->table_element . " as t "
        ),);

        return 1;
    }
    /* Add other hook methods here... */
}
