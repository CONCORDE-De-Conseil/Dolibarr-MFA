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
    public function getLoginPageExtraContent($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;

        if (empty($_SESSION['dol_mfa_challenge_user_id'])) {
            return 0;
        }

        require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

        $form = new Form($this->db);

        $formquestion = array(
            array(
                'name' => 'mfa_code',
                'label' => $langs->trans('OTPCode'),
                'type' => 'text',
                'value' => '',
                'size' => 6,
                'moreattr' => 'maxlength="6" inputmode="numeric" autocomplete="one-time-code" id="mfa_code"',
            ),
            array(
                'name' => 'username',
                'type' => 'hidden',
                'value' => $_SESSION['dol_mfa_challenge_login'],
            ),
            array(
                'name' => 'password',
                'type' => 'hidden',
                'value' => $_SESSION['dol_mfa_challenge_password'],
            ),
            array(
                'name' => 'entity',
                'type' => 'hidden',
                'value' => (string) ((int) $_SESSION['dol_mfa_challenge_entity']),
            ),
            array(
                'name' => 'actionlogin',
                'type' => 'hidden',
                'value' => 'login',
            ),
            array(
                'name' => 'loginfunction',
                'type' => 'hidden',
                'value' => 'loginfunction',
            ),
        );

        $formconfirm = $form->formconfirm(
            $_SERVER['PHP_SELF'],
            $langs->trans('MFAVerification'),
            $langs->trans('EnterMFACode'),
            'login',
            $formquestion,
            '',
            1,
            220
        );

        print $formconfirm;

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
        if (!in_array('usercard', $hookmanager->contextarray)) {
            return 0;
        }

        global $langs, $user;

        $langs->load("mfa@mfa");
        $mfaService = new MFAService($this->db);

        $id = GETPOST('id', 'int');
        $currentAction = GETPOST('action', 'alpha');
        $currentUser = new User($this->db);
        $currentUser->fetch($id);

        $mfa = $mfaService->getForUser($object->id, $object->entity);

        // 🔐 Handle disable MFA
        if ($currentAction === 'disablemfa') {
            if ($user->admin || $user->id == $object->id) {
                $mfaService->disableForUser($currentUser);
                setEventMessages($langs->trans("MFADisabled"), null, 'mesgs');
            }
        }

        // 🔐 Ensure secret exists (ONLY when needed)
        if ($currentAction === 'setupmfa') {
            if (!$mfa || empty($mfa->secret)) {
                $secret = $mfaService->generateSecret();
                $mfaService->createOrUpdateSecret($currentUser, $secret, 0);
            } else {
                $secret = dolDecrypt($mfa->secret);
            }
        }


        // Reload MFA after potential update
        $mfa = $mfaService->getForUser($currentUser->id, $currentUser->entity);

        print '<!-- MFA Section -->';

        // 🔹 Status row
        print '<tr class="trextrafields"><td class="titlefield">' . $langs->trans("MFAStatus") . '</td><td>';

        if ($mfa && $mfa->enabled) {
            print '<span class="badge badge-status status4">' . $langs->trans("Enabled") . '</span>';

            if ($user->admin || $user->id == $currentUser->id) {
                print ' <a class="butActionDelete" href="' . $_SERVER["PHP_SELF"] . '?id=' . $currentUser->id . '&action=disablemfa&token=' . newToken() . '">' . $langs->trans("Disable") . '</a>';
            }
        } else {
            print '<span class="badge badge-status status5">' . $langs->trans("Disabled") . '</span>';

            if ($user->admin || $user->id == $currentUser->id) {
                print ' <a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $currentUser->id . '&action=setupmfa&token=' . newToken() . '">' . $langs->trans("SetupMFA") . '</a>';
            }
        }

        print '</td></tr>';
        // 🔹 Setup screen
        if ($currentAction === 'setupmfa' && $mfa) {

            $secret = dolDecrypt($mfa->secret);
            $uri = $mfaService->getProvisioningUri($currentUser->login, $secret);

            $qrurl = DOL_URL_ROOT . '/viewimage.php?modulepart=barcode&generator=tcpdfbarcode&encoding=QRCODE&code=' . urlencode($uri);

            print '<tr class="trextrafields">';
            print '<td class="titlefield">' . $langs->trans("MFAQRCode") . '</td>';
            print '<td><img src="' . $qrurl . '" alt="QR Code"></td>';
            print '</tr>';

            print '<tr class="trextrafields">';
            print '<td class="titlefield">' . $langs->trans("MFASecret") . '</td>';
            print '<td>';

            print '<code>' . dol_escape_htmltag($secret) . '</code><br>';
            print '<div class="opacitymedium small">' . dol_escape_htmltag($uri) . '</div>';

            if (empty($mfa->enabled)) {
                print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '?id=' . $currentUser->id . '&action=enablemfa&token=' . newToken() . '">';

                print '<input type="text" name="mfa_verif" maxlength="6" placeholder="' . $langs->trans("EnterVerifyCode") . '" class="flat"> ';
                print '<input type="submit" class="button" value="' . $langs->trans("VerifyAndEnable") . '">';
                print '</form>';
            }

            print '</td></tr>';
        }

        return 0;
    }

    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        if ($action == 'enablemfa') {

            require_once dol_buildpath('/mfa/class/mfaservice.class.php');
            $mfaService = new MFAService($this->db);
            $id = GETPOST('id', 'int');
            $user = new User($this->db);
            $user->fetch($id);
            $mfa = $mfaService->getForUser($user->id, $user->entity);
            $secret = dolDecrypt($mfa->secret);
            $code = GETPOST('mfa_verif', 'alphanohtml');
            if ($mfaService->verifyCode($secret, $code)) {
                $mfaService->enableMFA($user);
                setEventMessages("MFA Enabled", null);
            } else {
                setEventMessages("Invalid Code", null, 'errors');
            }
        }
    }
}
