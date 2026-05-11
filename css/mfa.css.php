<?php
/*
 * MFA CSS served as PHP so it can be versioned or provide dynamic values later.
 */
header('Content-Type: text/css; charset=UTF-8');

/* Safe to output static CSS below */
?>
/* MFA modern card styles */
.mfa-container {
max-width: 620px;
width: min(620px, calc(100% - 32px));
margin: 30px auto;
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
border-radius: 16px;
box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
overflow: hidden;
animation: mfa-slideIn 0.4s ease-out;
}

@keyframes mfa-slideIn {
from { opacity: 0; transform: translateY(20px); }
to { opacity: 1; transform: translateY(0); }
}

.mfa-header {
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
color: white;
padding: 30px;
text-align: center;
border-bottom: 1px solid rgba(255,255,255,0.1);
}

.mfa-header h2 { margin: 0 0 8px 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px; }
.mfa-header p { margin: 0; font-size: 14px; opacity: 0.9; font-weight: 500; }

.mfa-icon { display:inline-block; width:48px; height:48px; background:rgba(255,255,255,0.2); border-radius:12px; display:flex; align-items:center; justify-content:center; margin-bottom:16px; font-size:24px; }

.mfa-content { background: white; padding: 30px; }

.mfa-user-info { background: #f8f9ff; border-left: 4px solid #667eea; padding: 16px; border-radius: 8px; margin-bottom: 24px; }
.mfa-user-info strong { display:block; color:#333; margin-bottom:4px; font-size:14px; }
.mfa-user-info span { color:#667eea; font-size:16px; font-weight:600; }

.mfa-form-group { margin-bottom:20px; }
.mfa-form-group label { display:block; margin-bottom:10px; color:#333; font-weight:600; font-size:14px; }

.mfa-form-group input[type="text"] {
width:100%; padding:12px 16px; font-size:18px; border:2px solid #e0e0e0; border-radius:10px; letter-spacing:8px; text-align:center; font-weight:600; transition:all 0.3s ease; font-family: 'Courier New', monospace; box-sizing:border-box;
}
.mfa-form-group input[type="text"]:focus { outline:none; border-color:#667eea; box-shadow:0 0 0 4px rgba(102,126,234,0.1); background:#f8f9ff; }
.mfa-form-group input::placeholder { letter-spacing:2px; opacity:0.5; }

.mfa-buttons { display:flex; gap:12px; margin-top:24px; }
.mfa-btn-submit { flex:1; padding:12px 24px; background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:white; border:none; border-radius:10px; font-size:16px; font-weight:600; cursor:pointer; transition:all 0.3s ease; box-shadow:0 4px 15px rgba(102,126,234,0.3); }
.mfa-btn-submit:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(102,126,234,0.4); }
.mfa-btn-submit:active { transform:translateY(0); }
.mfa-btn-logout { flex:1; padding:12px 24px; background:#f0f0f0; color:#333; border:2px solid #e0e0e0; border-radius:10px; font-size:16px; font-weight:600; cursor:pointer; transition:all 0.3s ease; text-decoration:none; display:flex; align-items:center; justify-content:center; }
.mfa-btn-logout:hover { background:#e8e8e8; border-color:#d0d0d0; }

.mfa-help-text { margin-top:16px; padding-top:16px; border-top:1px solid #f0f0f0; color:#666; font-size:13px; line-height:1.5; }
.mfa-help-text strong { color:#333; }

/* Small devices adjustments */
@media (max-width:480px) {
.mfa-container { width: calc(100% - 24px); margin: 18px auto; border-radius:12px; }
.mfa-header { padding:20px; }
.mfa-content { padding:20px; }
}
<?php
/* Copyright (C) 2026 Ali WERGHEMMI <ali.werghemmi@concorde.tn>
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
 * \file    mfa/css/mfa.css.php
 * \ingroup mfa
 * \brief   CSS file for module MFA.
 */

//if (!defined('NOREQUIREUSER')) define('NOREQUIREUSER','1');	// Not disabled because need to load personalized language
//if (!defined('NOREQUIREDB'))   define('NOREQUIREDB','1');	// Not disabled. Language code is found on url.
if (!defined('NOREQUIRESOC')) {
    define('NOREQUIRESOC', '1');
}
//if (!defined('NOREQUIRETRAN')) define('NOREQUIRETRAN','1');	// Not disabled because need to do translations
//if (!defined('NOCSRFCHECK'))   define('NOCSRFCHECK', 1);		// Should be disable only for special situation
if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
    define('NOLOGIN', 1); // File must be accessed by logon page so without login
}
//if (! defined('NOREQUIREMENU'))   define('NOREQUIREMENU',1);  // We need top menu content
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

session_cache_limiter('public');
// false or '' = keep cache instruction added by server
// 'public'  = remove cache instruction added by server
// and if no cache-control added later, a default cache delay (10800) will be added by PHP.

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
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
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/../main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1)) . "/../main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';

// Load user to have $user->conf loaded (not done by default here because of NOLOGIN constant defined) and load permission if we need to use them in CSS
/*if (empty($user->id) && !empty($_SESSION['dol_login'])) {
	$user->fetch('',$_SESSION['dol_login']);
	$user->getrights();
}*/


// Define css type
header('Content-type: text/css');
// Important: Following code is to cache this file to avoid page request by browser at each Dolibarr page access.
// You can use CTRL+F5 to refresh your browser cache.
if (empty($dolibarr_nocache)) {
    header('Cache-Control: max-age=10800, public, must-revalidate');
} else {
    header('Cache-Control: no-cache');
}

?>

div.mainmenu.mfa::before {
content: "\f249";
}
div.mainmenu.mfa {
background-image: none;
}

.myclasscss {
/* ... */
}
