<?php
/* Copyright (C) 2026		CONCORDE de Conseil				<contact@concorde.tn>
 * Copyright (C) 2026       Ali WERGHEMMI                   <ali.werghemmi@concorde.tn>
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

require_once dol_buildpath('/mfa/class/mfaattemptservice.class.php');
require_once dol_buildpath('/mfa/class/mfaservice.class.php');
require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';


/**
 * Authentication function for MFA module
 *
 * @param   string  $login      Login
 * @param   string  $password   Password
 * @param   int     $entity     Entity
 * @return  string              Login if OK, '' if KO, or '--bad-login-validity--' for MFA challenge
 */
function check_user_password_mfa($login, $password, $entity)
{
    global $db, $conf, $langs; // Add $langs to use for messages

    $maxAttempts = 5;
    $cooldown = 300;

    if (!is_object($db)) {
        return 0;
    }

    $challengeLogin = $login;
    $challengePassword = $password;
    $challengeEntity = (int) $entity;

    $postedLogin = GETPOST('username', 'alphanohtml');
    if (empty($postedLogin)) {
        $postedLogin = GETPOST('login', 'alphanohtml');
    }
    if (!empty($postedLogin)) {
        $challengeLogin = $postedLogin;
    }

    $postedPassword = GETPOST('password', 'password');
    if (!empty($postedPassword)) {
        $challengePassword = $postedPassword;
    }

    $postedEntity = GETPOSTINT('entity');
    if (!empty($postedEntity)) {
        $challengeEntity = $postedEntity;
    }

    if (empty($challengeLogin) && !empty($_SESSION['dol_mfa_challenge_login'])) {
        $challengeLogin = $_SESSION['dol_mfa_challenge_login'];
    }
    if (empty($challengeEntity) && !empty($_SESSION['dol_mfa_challenge_entity'])) {
        $challengeEntity = (int) $_SESSION['dol_mfa_challenge_entity'];
    }

    require_once DOL_DOCUMENT_ROOT . '/core/login/functions_dolibarr.php';

    // 1. Verify standard password (or check if already verified in this session)
    $password_already_verified = (!empty($_SESSION['dol_mfa_password_verified']) && $_SESSION['dol_mfa_challenge_login'] === $challengeLogin);

    if ($password_already_verified) {
        $response = $challengeLogin;
    } else {
        $response = check_user_password_dolibarr($challengeLogin, $challengePassword, $challengeEntity);
    }

    if ($response == '') {
        return '';
    }

    // If response is not empty, it means that login/password is correct.
    // We now check if MFA is enabled for this user and if yes, we check the code.

    require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
    $userstatic = new User($db);
    $result = $userstatic->fetch(0, $challengeLogin, '', 0, $challengeEntity);
    if ($result <= 0) {
        return '';
    }

    // 1. Check if MFA is enabled
    $mfaService = new MFAService($db);
    $attemptService = new MFAAttemptService($db);
    $mfa = $mfaService->getForUser($userstatic->id, $userstatic->entity);

    if ($mfa && $mfa->enabled) {
        $cooldownRemaining = $attemptService->getCooldownRemaining($userstatic->id, $userstatic->entity, MFAAttemptService::SCOPE_LOGIN);
        if ($cooldownRemaining > 0) {
            $langs->load("mfa@mfa");
            $_SESSION["dol_loginmesg"] = $langs->trans("MFATooManyLoginAttempts");
            $_SESSION["dol_mfa_challenge_user_id"] = $userstatic->id;
            $_SESSION["dol_mfa_challenge_login"] = $challengeLogin;
            $_SESSION["dol_mfa_challenge_entity"] = $userstatic->entity;
            $_SESSION["dol_mfa_password_verified"] = true;

            return '--bad-login-validity--';
        }

        $otpCode = GETPOST('mfa_code', 'alphanohtml');
        if (empty($otpCode)) {
            $langs->load("mfa@mfa"); // Load MFA language file
            $_SESSION["dol_loginmesg"] = $langs->trans("MFACodeRequired");
            $_SESSION["dol_mfa_challenge_user_id"] = $userstatic->id;
            $_SESSION["dol_mfa_challenge_login"] = $challengeLogin;
            $_SESSION["dol_mfa_challenge_entity"] = $userstatic->entity;
            $_SESSION["dol_mfa_password_verified"] = true; // Flag that password check passed

            return '--bad-login-validity--';
        }

        $plainSecret = dolDecrypt($mfa->secret);

        if (!$mfaService->verifyCode($plainSecret, $otpCode)) {
            $langs->load("mfa@mfa");
            $_SESSION["dol_loginmesg"] = $langs->trans("InvalidMFACode");
            $_SESSION["dol_mfa_challenge_user_id"] = $userstatic->id;
            $_SESSION["dol_mfa_challenge_login"] = $challengeLogin;
            $_SESSION["dol_mfa_challenge_entity"] = $userstatic->entity;
            $_SESSION["dol_mfa_password_verified"] = true;
            $attemptService->recordFailedAttempt(
                $userstatic->id,
                $userstatic->entity,
                MFAAttemptService::SCOPE_LOGIN,
                $maxAttempts,
                $cooldown,
                empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR']
            );

            return '--bad-login-validity--';
        }

        $attemptService->markSuccessfulAttempt($userstatic->id, $userstatic->entity, MFAAttemptService::SCOPE_LOGIN, empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR']);
        unset($_SESSION["dol_mfa_challenge_user_id"]);
        unset($_SESSION["dol_mfa_challenge_login"]);
        unset($_SESSION["dol_mfa_password_verified"]);
        unset($_SESSION["dol_mfa_challenge_entity"]);
    }

    return $response;
}
