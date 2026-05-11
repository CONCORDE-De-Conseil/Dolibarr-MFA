<?php
/* Copyright (C) 2026       CONCORDE de Conseil             <contact@concorde.tn>
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
 * \file    mfa/qrcode.php
 * \ingroup mfa
 * \brief   Generate QR code for MFA
 */

define('NOCSRFCHECK', '1');
define('NOTOKENRENEWAL', '1');
define('NOREQUIREMENU', '1');
define('NOREQUIREHTML', '1');
define('NOREQUIREAJAX', '1');

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"]) . "/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
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
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
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

// No need to accept the full provisioning URI via GET (it may trigger WAF).
// We generate provisioning URI server-side from stored secret using provided user id.

// Generate QR Code using TCPDF which is bundled in Dolibarr Core
require_once DOL_DOCUMENT_ROOT . '/core/modules/barcode/doc/tcpdfbarcode.modules.php';
require_once dol_buildpath('/mfa/class/mfaservice.class.php');


$id = GETPOST('id', 'int');
$currentUser = new User($db);
$currentUser->fetch($id);

if (!$currentUser->id) {
    print 'Error: User not found';
    exit;
}

$mfaService = new MFAService($db);
$mfa = $mfaService->getForUser($currentUser->id, $currentUser->entity);
if (!$mfa || empty($mfa->secret)) {
    print 'Error: MFA secret not found';
    exit;
}

$secret = dolDecrypt($mfa->secret);
$uri = $mfaService->getProvisioningUri($currentUser->login, $secret);

require_once DOL_DOCUMENT_ROOT . '/core/modules/barcode/doc/phpbarcode.modules.php';

$encoding = 'QRCODE';

// Load barcode class
$module = new modTcpdfbarcode($db);
if ($module->encodingIsSupported($encoding)) {
    print $module->buildBarCode($uri, $encoding);
} else {
    http_response_code(500);
    print 'Error: Barcode encoding not supported';
}
