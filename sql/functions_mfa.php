<?php
/* Copyright (C) 2026 Your Name */

/**
 * Authentication function for MFA module
 *
 * @param   string  $login      Login
 * @param   string  $password   Password
 * @param   int     $entity     Entity
 * @param   string  $action     Action
 * @return  int                 User ID if OK, <=0 if KO
 */
function checkLoginPassEntity($login, $password, $entity, $action = 'login')
{
    global $db, $conf;

    require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
    $userstatic = new User($db);
    $result = $userstatic->fetch(0, $login, '', 0, $entity);

    if ($result <= 0) {
        return 0;
    }

    // 1. Verify standard password
    $check = $userstatic->check_password($password);
    if (!$check) {
        return 0;
    }

    // 2. Check if MFA is enabled
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
