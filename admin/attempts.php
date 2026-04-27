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

/**
 * \file    mfa/admin/attempts.php
 * \ingroup mfa
 * \brief   Admin page to review and reset MFA failed attempt history.
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"]) . "/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once '../lib/mfa.lib.php';
require_once '../class/mfaattemptservice.class.php';

$langs->loadLangs(array("admin", "mfa@mfa"));

if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$searchUser = trim(GETPOST('search_user', 'alphanohtml'));
$showLockedOnly = GETPOSTINT('show_locked_only');

$attemptService = new MFAAttemptService($db);

if ($action === 'resetattempts') {
    $token = GETPOST('token', 'alphanohtml');
    $fkUser = GETPOSTINT('fk_user');
    $entity = GETPOSTINT('entity');
    $scope = GETPOST('scope', 'alpha');

    if ($token === '' || !hash_equals((string) currentToken(), (string) $token)) {
        setEventMessages($langs->trans("ErrorBadToken"), null, 'errors');
    } elseif ($fkUser > 0 && $entity >= 0) {
        $attemptService->resetAttempts($fkUser, $entity, $scope, (int) $user->id);
        setEventMessages($langs->trans("MFAAttemptsResetDone"), null, 'mesgs');
    }
}

$title = $langs->trans("MFAAttemptsPage");
$help_url = '';

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-mfa page-admin_attempts');

$head = mfaAdminPrepareHead();
print dol_get_fiche_head($head, 'attempts', $title, -1, 'mfa@mfa');

print '<form method="GET" action="' . $_SERVER["PHP_SELF"] . '">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans("Search") . '</td>';
print '<td><input type="text" class="minwidth200" name="search_user" value="' . dol_escape_htmltag($searchUser) . '"></td>';
print '<td><label><input type="checkbox" name="show_locked_only" value="1"' . ($showLockedOnly ? ' checked' : '') . '> ' . $langs->trans("MFAOnlyLockedUsers") . '</label></td>';
print '<td class="right"><input type="submit" class="button" value="' . $langs->trans("Search") . '"></td>';
print '</tr>';
print '</table>';
print '</div>';
print '</form>';

$stateRows = $attemptService->getAttemptStateList($searchUser, $showLockedOnly, 50);
$logRows = $attemptService->getRecentLogs($searchUser, 50);

print '<div class="fichecenter">';
print '<div class="fichethirdleft">';
print '<div class="ficheaddleft">';
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>' . $langs->trans("User") . '</th>';
print '<th>' . $langs->trans("MFAAttemptScope") . '</th>';
print '<th class="center">' . $langs->trans("MFAAttemptCount") . '</th>';
print '<th>' . $langs->trans("MFALastAttempt") . '</th>';
print '<th>' . $langs->trans("MFALockedUntil") . '</th>';
print '<th>' . $langs->trans("MFALastIP") . '</th>';
print '<th class="right">' . $langs->trans("Action") . '</th>';
print '</tr>';

if (empty($stateRows)) {
    print '<tr><td colspan="7" class="opacitymedium center">' . $langs->trans("MFANoAttemptData") . '</td></tr>';
} else {
    foreach ($stateRows as $row) {
        $fullName = trim($row->firstname . ' ' . $row->lastname);
        if ($fullName === '') {
            $fullName = $row->login;
        } else {
            $fullName .= ' (' . $row->login . ')';
        }

        $isLocked = (!empty($row->locked_until) && $db->jdate($row->locked_until) > dol_now());

        print '<tr class="oddeven">';
        print '<td>' . dol_escape_htmltag($fullName) . '</td>';
        print '<td>' . $langs->trans($row->scope === MFAAttemptService::SCOPE_SETUP ? "MFAAttemptSetup" : "MFAAttemptLogin") . '</td>';
        print '<td class="center">' . ((int) $row->attempt_count) . '</td>';
        print '<td>' . (!empty($row->last_attempt) ? dol_print_date($db->jdate($row->last_attempt), 'dayhour') : '') . '</td>';
        print '<td>';
        if ($isLocked) {
            print '<span class="badge badge-status8 badge-status">' . dol_print_date($db->jdate($row->locked_until), 'dayhour') . '</span>';
        } else {
            print '<span class="opacitymedium">' . $langs->trans("No") . '</span>';
        }
        print '</td>';
        print '<td>' . dol_escape_htmltag((string) $row->last_ip) . '</td>';
        print '<td class="right">';
        print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '" style="display:inline-block">';
        print '<input type="hidden" name="token" value="' . currentToken() . '">';
        print '<input type="hidden" name="action" value="resetattempts">';
        print '<input type="hidden" name="fk_user" value="' . ((int) $row->fk_user) . '">';
        print '<input type="hidden" name="entity" value="' . ((int) $row->entity) . '">';
        print '<input type="hidden" name="scope" value="' . dol_escape_htmltag($row->scope) . '">';
        print '<input type="hidden" name="search_user" value="' . dol_escape_htmltag($searchUser) . '">';
        if ($showLockedOnly) {
            print '<input type="hidden" name="show_locked_only" value="1">';
        }
        print '<input type="submit" class="button button-edit" value="' . $langs->trans("MFAResetAttempts") . '">';
        print '</form>';
        print '</td>';
        print '</tr>';
    }
}

print '</table>';
print '</div>';
print '</div>';
print '</div>';

print '<div class="fichetwothirdright">';
print '<div class="ficheaddleft">';
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>' . $langs->trans("Date") . '</th>';
print '<th>' . $langs->trans("User") . '</th>';
print '<th>' . $langs->trans("MFAAttemptScope") . '</th>';
print '<th>' . $langs->trans("MFAAttemptEvent") . '</th>';
print '<th class="center">' . $langs->trans("MFAAttemptCount") . '</th>';
print '<th>' . $langs->trans("MFALastIP") . '</th>';
print '</tr>';

if (empty($logRows)) {
    print '<tr><td colspan="7" class="opacitymedium center">' . $langs->trans("MFANoAttemptData") . '</td></tr>';
} else {
    foreach ($logRows as $row) {
        $fullName = trim($row->firstname . ' ' . $row->lastname);
        if ($fullName === '') {
            $fullName = $row->login;
        } else {
            $fullName .= ' (' . $row->login . ')';
        }

        $eventLabel = "MFAAttemptFailure";
        if ($row->event_type === MFAAttemptService::EVENT_LOCK) {
            $eventLabel = "MFAAttemptLock";
        } elseif ($row->event_type === MFAAttemptService::EVENT_RESET) {
            $eventLabel = "MFAAttemptReset";
        } elseif ($row->event_type === MFAAttemptService::EVENT_SUCCESS) {
            $eventLabel = "MFAAttemptSuccess";
        }

        print '<tr class="oddeven">';
        print '<td>' . dol_print_date($db->jdate($row->datec), 'dayhour') . '</td>';
        print '<td>' . dol_escape_htmltag($fullName) . '</td>';
        print '<td>' . $langs->trans($row->scope === MFAAttemptService::SCOPE_SETUP ? "MFAAttemptSetup" : "MFAAttemptLogin") . '</td>';
        print '<td>' . $langs->trans($eventLabel) . '</td>';
        print '<td class="center">' . ((int) $row->attempt_count) . '</td>';
        print '<td>' . dol_escape_htmltag((string) $row->ip_address) . '</td>';
        print '</tr>';
    }
}

print '</table>';
print '</div>';
print '</div>';
print '</div>';
print '</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
