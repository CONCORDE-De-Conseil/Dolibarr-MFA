<?php
/* Copyright (C) 2023		Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2026		CONCORDE de Conseil				<contact@concorde.tn>
 * Copyright (C) 2026		Ali WERGHEMMI				<ali.werghemmi@concorde.tn>
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
require_once dol_buildpath('/mfa/class/mfaservice.class.php');
require_once dol_buildpath('/mfa/class/mfaattemptservice.class.php');

/**
 * Class ActionsMFA
 */
class ActionsMFA extends CommonHookActions
{
    const MFA_SETUP_MAX_ATTEMPTS = 5;
    const MFA_SETUP_COOLDOWN = 300;

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
     * Check whether the authenticated user may manage the target user's MFA settings.
     *
     * @param User $actorUser  Authenticated user performing the action.
     * @param User $targetUser User whose MFA settings are targeted.
     * @return bool            True when access is allowed.
     */
    private function canManageMfaForUser(User $actorUser, User $targetUser)
    {
        return ($actorUser->admin || (int) $actorUser->id === (int) $targetUser->id);
    }

    /**
     * Validate the anti-CSRF token provided with MFA actions.
     *
     * @return bool True when the submitted token matches the current session token.
     */
    private function hasValidActionToken()
    {
        $token = (string) GETPOST('token', 'alphanohtml');
        if ($token === '' || !function_exists('currentToken')) {
            return false;
        }

        return hash_equals((string) currentToken(), $token);
    }

    /**
     * Clear MFA challenge markers from session.
     *
     * @param bool $renewSessionId True to renew PHP session id after cleanup
     * @return void
     */
    private function clearMfaChallengeSession($renewSessionId = false)
    {
        unset($_SESSION['dol_mfa_challenge_user_id']);
        unset($_SESSION['dol_mfa_challenge_login']);
        unset($_SESSION['dol_mfa_challenge_entity']);
        unset($_SESSION['dol_mfa_password_verified']);

        if ($renewSessionId) {
            session_regenerate_id(true);
        }
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

        $langs->load('mfa@mfa');

        $requestedAction = GETPOST('action', 'aZ09');
        $confirm = GETPOST('confirm', 'alpha');
        $mfaAbort = GETPOSTINT('mfaabort');

        if (!empty($_SESSION['dol_mfa_challenge_user_id']) && (($requestedAction === 'login' && $confirm === 'no') || $mfaAbort === 1)) {
            $this->clearMfaChallengeSession(true);
            $_SESSION['dol_loginmesg'] = $langs->trans('MFASessionDestroyed');
            return 0;
        }

        if (empty($_SESSION['dol_mfa_challenge_user_id'])) {
            return 0;
        }

        require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

        $form = new Form($this->db);
        $pendingLogin = (string) $_SESSION['dol_mfa_challenge_login'];
        $pendingLoginEscaped = dol_escape_htmltag($pendingLogin);
        $pendingEntity = (int) $_SESSION['dol_mfa_challenge_entity'];
        $pageConfirm = $_SERVER['PHP_SELF']
            . '?username=' . urlencode($pendingLogin)
            . '&entity=' . $pendingEntity
            . '&actionlogin=login'
            . '&loginfunction=loginfunction';
        $confirmMessage = $langs->trans('EnterMFACode') . '<br><span class="opacitymedium">' . $langs->trans('MFAContinueAs', $pendingLoginEscaped) . '</span>';

        $logoutUrl = $_SERVER['PHP_SELF'] . '?mfaabort=1&token=' . urlencode(newToken());
        print '<div class="warning">' . $langs->trans('MFAPendingChallenge') . ' ' . $langs->trans('MFAContinueAs', '<strong>' . $pendingLoginEscaped . '</strong>') . ' <a href="' . dol_escape_htmltag($logoutUrl) . '">' . $langs->trans('Logout') . '</a></div>';

        $formquestion = array(
            array(
                'name' => 'mfa_code',
                'label' => $langs->trans('OTPCode'),
                'type' => 'text',
                'value' => '',
                'size' => 6,
                'moreattr' => 'maxlength="6" inputmode="numeric" autocomplete="one-time-code" id="mfa_code"',
            ),
        );

        $formconfirm = $form->formconfirm(
            $pageConfirm,
            $langs->trans('MFAVerification'),
            $confirmMessage,
            'login',
            $formquestion,
            '',
            2,
            260
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
        if ($parameters['currentcontext'] == 'usercard' && $action != 'create') {
            global $langs, $user;
            print '<!-- Begin MFA Section -->';

            $langs->load("mfa@mfa");
            $mfaService = new MFAService($this->db);

            $id = GETPOST('id', 'int');
            $currentAction = GETPOST('action', 'alpha');
            $currentUser = new User($this->db);
            $currentUser->fetch($id);
            if (!$currentUser->id) {
                return 0;
            }

            $mfa = $mfaService->getForUser($currentUser->id, $currentUser->entity);
            $canManageMfa = $this->canManageMfaForUser($user, $currentUser);
            $hasValidActionToken = $this->hasValidActionToken();

            // Ensure a provisioning secret exists only after a valid authorized setup request.
            if ($currentAction === 'setupmfa' && $canManageMfa && $hasValidActionToken) {
                // If we are in setup mode, we only generate a new secret if the user doesn't already have an unconfirmed one
                if (!$mfa || empty($mfa->secret)) {
                    $secret = $mfaService->generateSecret();
                    $mfaService->createOrUpdateSecret($currentUser, $secret, 0);
                    // Reload MFA to get the newly created secret
                    $mfa = $mfaService->getForUser($currentUser->id, $currentUser->entity);
                } else {
                    $secret = dolDecrypt($mfa->secret);
                }
            }

            // 🔹 Status row
            print '<tr class="trextrafields"><td class="titlefield">' . $langs->trans("MFAStatus") . '</td><td>';

            if ($mfa && $mfa->enabled) {
                print '<span class="badge badge-status4 badge-status">' . $langs->trans("Enabled") . '</span>';

                if ($canManageMfa) {
                    print ' <a class="butActionDelete" href="' . $_SERVER["PHP_SELF"] . '?id=' . $currentUser->id . '&action=disablemfa&token=' . newToken() . '">' . $langs->trans("Disable") . '</a>';
                }
            } else {
                print '<span class="badge badge-status5 badge-status">' . $langs->trans("Disabled") . '</span>';

                if ($canManageMfa) {
                    print ' <a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $currentUser->id . '&action=setupmfa&token=' . newToken() . '">' . $langs->trans("SetupMFA") . '</a>';
                }
            }

            print '</td></tr>';
            // 🔹 Setup screen

            if ($currentAction === 'setupmfa' && $mfa && $canManageMfa && $hasValidActionToken) {

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

                print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '?id=' . $currentUser->id . '&action=enablemfa&token=' . newToken() . '">';

                print '<input type="text" name="mfa_verif" maxlength="6" placeholder="' . $langs->trans("EnterVerifyCode") . '" class="flat"> ';
                print '<input type="submit" class="button" value="' . $langs->trans("VerifyAndEnable") . '">';
                print '</form>';

                print '</td></tr>';
            }

            print '<!-- End MFA Section -->';
        }

        return 0;
    }

    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user;
        $langs->load("mfa@mfa");

        if ($parameters['currentcontext'] == 'usercard') {
            $id = GETPOST('id', 'int');
            $currentUser = new User($this->db);
            $currentUser->fetch($id);

            if (!$currentUser->id) {
                return 0;
            }

            if ($action == 'disablemfa') {
                $mfaService = new MFAService($this->db);
                $attemptService = new MFAAttemptService($this->db);
                if (!$this->canManageMfaForUser($user, $currentUser)) {
                    setEventMessages($langs->trans("ErrorForbidden"), null, 'errors');
                    return 0;
                }
                if (!$this->hasValidActionToken()) {
                    setEventMessages($langs->trans("ErrorBadToken"), null, 'errors');
                    return 0;
                }
                if ($user->admin || $user->id == $currentUser->id) {
                    $mfaService->disableForUser($currentUser);
                    $attemptService->resetAttempts($currentUser->id, $currentUser->entity, MFAAttemptService::SCOPE_SETUP, (int) $user->id);
                    setEventMessages($langs->trans("MFADisabled"), null, 'mesgs');
                }
            }

            if ($action == 'enablemfa') {
                $mfaService = new MFAService($this->db);
                $attemptService = new MFAAttemptService($this->db);
                if (!$this->canManageMfaForUser($user, $currentUser)) {
                    setEventMessages($langs->trans("ErrorForbidden"), null, 'errors');
                    return 0;
                }
                if (!$this->hasValidActionToken()) {
                    setEventMessages($langs->trans("ErrorBadToken"), null, 'errors');
                    return 0;
                }

                $cooldownRemaining = $attemptService->getCooldownRemaining($currentUser->id, $currentUser->entity, MFAAttemptService::SCOPE_SETUP);
                if ($cooldownRemaining > 0) {
                    setEventMessages($langs->trans("MFATooManySetupAttempts"), null, 'errors');
                    return 0;
                }

                $mfa = $mfaService->getForUser($currentUser->id, $currentUser->entity);
                if (!$mfa || empty($mfa->secret)) {
                    setEventMessages($langs->trans("MFASecretNotAvailable"), null, 'errors');
                    return 0;
                }

                $secret = dolDecrypt($mfa->secret);
                $code = GETPOST('mfa_verif', 'alphanohtml');
                if ($mfaService->verifyCode($secret, $code)) {
                    $mfaService->enableMFA($currentUser);
                    $attemptService->markSuccessfulAttempt($currentUser->id, $currentUser->entity, MFAAttemptService::SCOPE_SETUP, empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR']);
                    setEventMessages($langs->trans("MFAEnabled"), null, 'mesgs');
                } else {
                    $attemptService->recordFailedAttempt(
                        $currentUser->id,
                        $currentUser->entity,
                        MFAAttemptService::SCOPE_SETUP,
                        self::MFA_SETUP_MAX_ATTEMPTS,
                        self::MFA_SETUP_COOLDOWN,
                        empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR']
                    );
                    setEventMessages($langs->trans("InvalidCode"), null, 'errors');
                }
            }
        }

        return 0;
    }
}
