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
    global $db, $conf, $langs; // Add $langs to use for messages

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
    $mfa = $mfaService->getForUser($userstatic->id);

    if ($mfa && $mfa->enabled) {
        // var_dump('MFA is enabled for this user, checking for code...'); // Debug line to confirm MFA is enabled
        // die();
        $otpCode = GETPOST('mfa_code', 'alphanohtml');
        if (empty($otpCode)) {
            // MFA is enabled and code is missing. This is the point where we need to show the modal.
            // Set specific session variables to trigger the modal on the login page.
            $langs->load("mfa@mfa"); // Load MFA language file
            $_SESSION["dol_loginmesg"] = $langs->trans("MFACodeRequired");
            $_SESSION["dol_mfa_challenge_user_id"] = $userstatic->id; // Store user ID for the challenge
            $_SESSION["dol_mfa_challenge_login"] = $login; // Store login for the challenge
            $_SESSION["dol_mfa_challenge_password"] = $password; // Store password for re-submission
            $_SESSION["dol_mfa_challenge_entity"] = $entity; // Store entity for the challenge
            return ''; // Missing code
        }
        var_dump('MFA code provided, verifying...'); // Debug line to confirm code is provided
        die();
        require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';
        $plainSecret = dolDecrypt($mfa->secret);

        if (!$mfaService->verifyCode($plainSecret, $otpCode)) {
            $langs->load("mfa@mfa");
            $_SESSION["dol_loginmesg"] = $langs->trans("InvalidMFACode");
            $_SESSION["dol_mfa_challenge_user_id"] = $userstatic->id;
            $_SESSION["dol_mfa_challenge_login"] = $login;
            $_SESSION["dol_mfa_challenge_password"] = $password;
            $_SESSION["dol_mfa_challenge_entity"] = $entity;
            return -1; // Invalid code
        }
        // If MFA code is correct, clear challenge session variables
        unset($_SESSION["dol_mfa_challenge_user_id"]);
        unset($_SESSION["dol_mfa_challenge_login"]);
        unset($_SESSION["dol_mfa_challenge_password"]);
        unset($_SESSION["dol_mfa_challenge_entity"]);
    }
    var_dump('MFA not enabled or code verified successfully, proceeding with login...'); // Debug line to confirm MFA check passed
    var_dump($mfa);
    var_dump($userstatic->id);
    die();
    return $response;
}


function mfa_login_form_additional_fields()
{
    global $langs, $conf;

    $langs->load("mfa@mfa");

    // Check if we are in a login challenge context
    if (isset($_SESSION["dol_mfa_challenge_user_id"])) {
        // We are in MFA challenge context, show the code input field
        $form = new Form($db);


        $more = '<input type="text" name="mfa_code" maxlength="6" class="flat" placeholder="123456" autofocus>';

        $formconfirm = $form->formconfirm(
            $_SERVER["PHP_SELF"] . '?actionlogin=login' . '&mfa_challenge=1',
            $langs->trans("MFAVerification"),
            $langs->trans("EnterMFACode"),
            'confirm_mfa',
            array(),
            '',
            1,
            $more
        );
    }
}
