<?php
/* Copyright (C) 2026 Your Name */

/**
 * Authentication function for MFA module
 *
 * @param   string  $login      Login
 * @param   string  $password   Password
 * @param   int     $entity     Entity
 * @return  int                 User ID if OK, <=0 if KO
 */
function check_user_password_mfa($login, $password, $entity)
{
    global $db, $conf;

    require_once DOL_DOCUMENT_ROOT . '/core/login/functions_dolibarr.php';

    $response = check_user_password_dolibarr($login, $password, $entity);

    if ($response == '') {
        return 0;
    }

    // if response is not empty, it means that login/password is correct, we now check if MFA is enabled for this user and if yes, we check the code

    require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
    $userstatic = new User($db);
    $result = $userstatic->fetch(0, $login, '', 0, $entity);



    // 1. Check if MFA is enabled
    require_once dol_buildpath('/mfa/class/mfaservice.class.php');
    $mfaService = new MFAService($db);
    $mfa = $mfaService->getForUser($userstatic->id, $entity);

    if ($mfa && $mfa->enabled) {
        $otpCode = GETPOST('mfa_code', 'alphanohtml');
        if (empty($otpCode)) {
            return -1; // Missing code
        }

        require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';
        $plainSecret = dolDecrypt($mfa->secret);

        if (!$mfaService->verifyCode($plainSecret, $otpCode)) {
            return -1; // Invalid code
        }
    }

    return $userstatic->id;
}
