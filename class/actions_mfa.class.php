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
 * MFA actions class to handle MFA management on user card and MFA verification on login page.
 */

require_once dol_buildpath('/mfa/class/mfaservice.class.php');
require_once dol_buildpath('/mfa/class/mfaattemptservice.class.php');
require_once dol_buildpath('/mfa/lib/mfa.lib.php');

/**
 * Class ActionsMFA
 */
class ActionsMFA
{

    /**
     * @var DoliDB Database handler.
     */
    public $db;

    private $maxAttempts;
    private $cooldown;

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
        global $conf;
        $this->db = $db;
        $this->maxAttempts = $conf->global->MFA_MAX_ATTEMPTS ?? 5;
        $this->cooldown = $conf->global->MFA_SETUP_COOLDOWN ?? 300;
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
        unset($_SESSION['dol_login']);  // Clear the login session
        unset($_SESSION['dol_loginmesg']);  // Clear any pending message

        if ($renewSessionId) {
            session_regenerate_id(true);
        }
    }

    /**
     * Store a temporary MFA setup session for the target user.
     *
     * @param int $userId User identifier
     * @param int $entity  Entity identifier
     * @return void
     */
    private function startMfaSetupSession($userId, $entity)
    {
        $_SESSION['dol_mfa_setup_user_id'] = (int) $userId;
        $_SESSION['dol_mfa_setup_entity'] = (int) $entity;
    }

    /**
     * Check whether the MFA setup form may be shown for the target user.
     *
     * @param int $userId User identifier
     * @param int $entity Entity identifier
     * @return bool
     */
    private function hasValidMfaSetupSession($userId, $entity)
    {
        return !empty($_SESSION['dol_mfa_setup_user_id'])
            && !empty($_SESSION['dol_mfa_setup_entity'])
            && (int) $_SESSION['dol_mfa_setup_user_id'] === (int) $userId
            && (int) $_SESSION['dol_mfa_setup_entity'] === (int) $entity;
    }

    /**
     * Clear the temporary MFA setup session.
     *
     * @return void
     */
    private function clearMfaSetupSession()
    {
        unset($_SESSION['dol_mfa_setup_user_id']);
        unset($_SESSION['dol_mfa_setup_entity']);
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

        $isSessionSetted = isset($_SESSION['dol_mfa_challenge_user_id']) && !empty($_SESSION['dol_mfa_challenge_user_id']) && $_SESSION['dol_mfa_challenge_user_id'] != null;

        if ($isSessionSetted  && (($requestedAction === 'login' && $confirm === 'no') || $mfaAbort === 1)) {
            $this->clearMfaChallengeSession(true);
            $_SESSION['dol_loginmesg'] = $langs->trans('MFASessionDestroyed');

            $urllogout = DOL_URL_ROOT . '/user/logout.php?token=' . newToken();
            // header('Location: ' . $urllogout);
            print '<script>window.location.href = "' . $urllogout . '";</script>';
            return -1;
        }

        if (empty($_SESSION['dol_mfa_challenge_user_id'])) {
            return 0;
        } else {

            $pendingLogin = (string) $_SESSION['dol_mfa_challenge_login'];
            $pendingLoginEscaped = dol_escape_htmltag($pendingLogin);

            // Link to externalized MFA CSS
            $cssUrl = dol_buildpath('/mfa/css/mfa.css.php', 1);
            print '<link rel="stylesheet" href="' . $cssUrl . '">';

            // Modern MFA HTML
            $html = <<<'HTML'
<div class="mfa-container">
    <div class="mfa-header">
        <div class="mfa-icon">🔐</div>
        <h2>Two-Factor Authentication</h2>
        <p>Additional security verification required</p>
    </div>

    <div class="mfa-content">
        <div class="mfa-user-info">
            <strong>Logged in as:</strong>
            <span>USER_LOGIN</span>
        </div>

        <form method="POST" action="PAGE_CONFIRM">
            <div class="mfa-form-group">
                <label for="mfa_code_modern">Enter 6-digit code</label>
                <input
                    type="text"
                    id="mfa_code_modern"
                    name="mfa_code"
                    maxlength="6"
                    placeholder="000000"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                    
                >
            </div>

            <div class="mfa-buttons">
                <button type="submit" name="action" value="login" class="mfa-btn-submit">
                    ✓ Verify Code
                </button>
                <button type="submit" name="mfaabort" value="1" class="mfa-btn-logout">
                    ✕ Logout
                </button>
            </div>

            <div class="mfa-help-text">
                <strong>Don't have access to your authenticator?</strong><br>
                Contact your administrator for assistance.
            </div>
        </form>
    </div>
</div>
HTML;

            // Replace placeholders
            $html = str_replace('USER_LOGIN', $pendingLoginEscaped, $html);

            print $html;
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
    public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
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

                $this->startMfaSetupSession($currentUser->id, $currentUser->entity);
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

            if ($mfa && $canManageMfa && $this->hasValidMfaSetupSession($currentUser->id, $currentUser->entity)) {
                require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';

                // 1. Try to decrypt
                $secret = dolDecrypt($mfa->secret);

                // 2. Validate if it's a real Base32 string (A-Z, 2-7, exactly 16 chars usually)
                if (!preg_match('/^[A-Z2-7]{16}$/', $secret)) {
                    // SECRET IS CORRUPT - Generate a fresh one immediately
                    $secret = $mfaService->generateSecret();

                    // Update the DB so we don't have this error again next time
                    $mfaService->createOrUpdateSecret($currentUser, $secret, 0);

                    print '<div class="warning">Previous secret was invalid. A new one has been generated.</div>';
                }

                // 3. Build URI with the CLEAN secret
                $uri = $mfaService->getProvisioningUri($currentUser->login, $secret);

                // 4. Generate QR
                require_once TCPDF_PATH . 'tcpdf_barcodes_2d.php';
                try {
                    // 1. Generate QR Code
                    $barcodeobj = new TCPDF2DBarcode($uri, 'QRCODE,L');
                    $imageData = (string)$barcodeobj->getBarcodePngData(5, 5);
                    $qrCodeBase64 = 'data:image/png;base64,' . base64_encode($imageData);

                    // --- ROW 1: QR CODE ---
                    print '<tr class="trextrafields">';
                    print '<td class="titlefield">' . $langs->trans("MFAQRCode") . '</td>';
                    print '<td>';
                    print '    <div style="background:#fff; padding:20px; display:inline-block; border:1px solid #ccc; border-radius: 4px;">';
                    print '        <img src="' . $qrCodeBase64 . '" alt="QR Code" />';
                    print '    </div>';
                    print '    <div class="opacitymedium">' . $langs->trans("ScanThisWithYourApp") . '</div>';
                    print '</td></tr>';

                    // --- ROW 2: TEXT SECRET ---
                    print '<tr class="trextrafields">';
                    print '<td class="titlefield">' . $langs->trans("MFASecretKey") . '</td>';
                    print '<td>';
                    print '    <code style="font-size:1.4em; letter-spacing:2px; background: #eee; padding: 2px 8px; border-radius: 3px;">' . $secret . '</code>';
                    print '    <div class="opacitymedium">' . $langs->trans("UseThisForManualEntry") . '</div>';
                    print '</td></tr>';

                    // --- ROW 3: VERIFICATION INPUT ---
                    print '<tr class="trextrafields">';
                    print '<td class="titlefield"><strong>' . $langs->trans("VerifyAndEnable") . '</strong></td>';
                    print '<td>';

                    // Start Form
                    print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '?id=' . $currentUser->id . '">';
                    print '<input type="hidden" name="action" value="enablemfa">';
                    print '<input type="hidden" name="token" value="' . newToken() . '">';

                    print '<div style="margin-top: 10px;">';
                    print '    <input type="text" name="mfa_verif" id="mfa_verif" maxlength="6" ';
                    print '           placeholder="000000" class="flat" ';
                    print '           style="width: 120px; text-align: center; font-size: 1.5em; font-weight: bold; height: 40px; margin-right: 10px;">';

                    print '    <input type="submit" class="button" value="' . $langs->trans("VerifyAndEnable") . '">';
                    print '</div>';

                    print '<div class="opacitymedium" style="margin-top: 5px;">' . $langs->trans("EnterSixDigitCodeFromApp") . '</div>';
                    print '</form>';

                    print '</td></tr>';
                } catch (Exception $e) {
                    print '<tr class="trextrafields"><td colspan="2"><div class="error">' . $e->getMessage() . '</div></td></tr>';
                }
            }

            print '<!-- End MFA Section -->';
        }

        return 0;
    }

    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user, $conf;
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
                    $this->clearMfaSetupSession();
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
                    $mfa = $mfaService->createOrUpdateSecret($currentUser, $secret, 1);
                    $attemptService->markSuccessfulAttempt($currentUser->id, $currentUser->entity, MFAAttemptService::SCOPE_SETUP, empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR']);
                    $this->clearMfaSetupSession();
                    setEventMessages($langs->trans("MFAEnabled"), null, 'mesgs');
                } else {
                    $attemptService->recordFailedAttempt(
                        $currentUser->id,
                        $currentUser->entity,
                        MFAAttemptService::SCOPE_SETUP,
                        $this->maxAttempts,
                        $this->cooldown,
                        empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR']
                    );
                    setEventMessages($langs->trans("InvalidCode"), null, 'errors');
                }
            }
        }

        return 0;
    }
}
