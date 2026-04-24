<?php
/* Copyright (C) 2026 Your Name */

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
    require_once dol_buildpath('/mfa/class/mfaservice.class.php');
    $mfaService = new MFAService($db);
    $mfa = $mfaService->getForUser($userstatic->id, $challengeEntity);

    if ($mfa && $mfa->enabled) {

        $otpCode = GETPOST('mfa_code', 'alphanohtml');
        if (empty($otpCode)) {
            $langs->load("mfa@mfa"); // Load MFA language file
            $_SESSION["dol_loginmesg"] = $langs->trans("MFACodeRequired");
            $_SESSION["dol_mfa_challenge_user_id"] = $userstatic->id;
            $_SESSION["dol_mfa_challenge_login"] = $challengeLogin;
            $_SESSION["dol_mfa_challenge_entity"] = $challengeEntity;
            $_SESSION["dol_mfa_password_verified"] = true; // Flag that password check passed

            return '--bad-login-validity--';
        }

        require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';
        $plainSecret = dolDecrypt($mfa->secret);

        if (!$mfaService->verifyCode($plainSecret, $otpCode)) {
            $langs->load("mfa@mfa");
            $_SESSION["dol_loginmesg"] = $langs->trans("InvalidMFACode");
            $_SESSION["dol_mfa_challenge_user_id"] = $userstatic->id;
            $_SESSION["dol_mfa_challenge_login"] = $challengeLogin;
            $_SESSION["dol_mfa_challenge_entity"] = $challengeEntity;
            $_SESSION["dol_mfa_password_verified"] = true;

            return '--bad-login-validity--';
        }

        unset($_SESSION["dol_mfa_challenge_user_id"]);
        unset($_SESSION["dol_mfa_challenge_login"]);
        unset($_SESSION["dol_mfa_password_verified"]);
        unset($_SESSION["dol_mfa_challenge_entity"]);
    }

    return $response;
}
