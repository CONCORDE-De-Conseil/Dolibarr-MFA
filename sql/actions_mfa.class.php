<?php
/* Copyright (C) 2026 Your Name */

/**
 * Hooks for MFA module
 */
class ActionsMFA
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
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
        var_dump($parameters, $object, $action, $hookmanager); // Debug line to inspect parameters
        if ($hookmanager->contextarray[0] == 'usercard') {
            global $langs, $user;
            $langs->load("mfa@mfa");

            require_once dol_buildpath('/mfa/class/mfaservice.class.php');
            $mfaService = new MFAService($this->db);
            $mfa = $mfaService->getForUser($object->id, $object->entity);

            print '<!-- MFA Section -->';
            print '<tr class="trextrafields"><td class="titlefield">' . $langs->trans("MFAStatus") . '</td>';
            print '<td>';
            if ($mfa && $mfa->enabled) {
                print '<span class="badge badge-status status4">' . $langs->trans("Enabled") . '</span>';
                if ($user->admin || $user->id == $object->id) {
                    print ' <a class="butActionDelete" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=disablemfa&token=' . newToken() . '">' . $langs->trans("Disable") . '</a>';
                }
            } else {
                print '<span class="badge badge-status status5">' . $langs->trans("Disabled") . '</span>';
                if ($user->admin || $user->id == $object->id) {
                    print ' <a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=setupmfa">' . $langs->trans("SetupMFA") . '</a>';
                }
            }
            print '</td></tr>';

            if ($action == 'setupmfa') {
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
}
