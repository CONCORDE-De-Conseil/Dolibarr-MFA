<?php
/* Copyright (C) 2026		CONCORDE de Conseil		<contact@concorde.tn>
 * Copyright (C) 2026       Ali WERGHEMMI           <ali.werghemmi@concorde.tn>
 * Copyright (C) 2025       Frédéric France         <frederic.france@free.fr>
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
 * \file    mfa/lib/mfa.lib.php
 * \ingroup mfa
 * \brief   Library files with common functions for MFA
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */
function mfaAdminPrepareHead()
{
    global $langs, $conf;

    // global $db;
    // $extrafields = new ExtraFields($db);
    // $extrafields->fetch_name_optionals_label('myobject');

    $langs->load("mfa@mfa");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/mfa/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = dol_buildpath("/mfa/admin/attempts.php", 1);
    $head[$h][1] = $langs->trans("MFAAttempts");
    $head[$h][2] = 'attempts';
    $h++;

    /*
	$head[$h][0] = dolBuildUrl(dol_buildpath("/mfa/admin/myobject_extrafields.php", 1));
	$head[$h][1] = $langs->trans("ExtraFields");
	$nbExtrafields = (isset($extrafields->attributes['myobject']['label']) && is_countable($extrafields->attributes['myobject']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafields';
	$h++;

	$head[$h][0] = dolBuildUrl(dol_buildpath("/mfa/admin/myobjectline_extrafields.php", 1));
	$head[$h][1] = $langs->trans("ExtraFieldsLines");
	$nbExtrafields = (isset($extrafields->attributes['myobjectline']['label']) && is_countable($extrafields->attributes['myobjectline']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafieldsline';
	$h++;
	*/

    $head[$h][0] = dol_buildpath("/mfa/admin/about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@mfa:/mfa/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@mfa:/mfa/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, null, $head, $h, 'mfa@mfa');

    complete_head_from_modules($conf, $langs, null, $head, $h, 'mfa@mfa', 'remove');

    return $head;
}




if (!function_exists('dolGetRandomBytes')) {
    /**
     * Return a string of random bytes (hexa string) with length = $length for cryptographic purposes.
     *
     * @param 	int			$length		Length of random string
     * @return	string					Random string
     */
    function dolGetRandomBytes($length)
    {
        if (function_exists('random_bytes')) {    // Available with PHP 7 only.
            return bin2hex(random_bytes((int) floor($length / 2)));    // the bin2hex will double the number of bytes so we take length / 2
        }

        return bin2hex(openssl_random_pseudo_bytes((int) floor($length / 2)));        // the bin2hex will double the number of bytes so we take length / 2. May be very slow on Windows.
    }
}


if (!function_exists('dolEncrypt')) {

    define('MAIN_SECURITY_REVERSIBLE_ALGO', 'AES-256-CTR');

    /**
     *	Encode a string with a symmetric encryption. Used to encrypt sensitive data into database.
     *  Note: If a backup is restored onto another instance with a different $conf->file->instance_unique_id, then decoded value will differ.
     *  This function is called for example by dol_set_const() when saving a sensible data into database, like into configuration table llx_const, or societe_rib, ...
     *
     *	@param   string		$chain		String to encode
     *	@param   string		$key		If '', we use $conf->file->instance_unique_id (so $dolibarr_main_instance_unique_id in conf.php)
     *  @param	 string		$ciphering	Default ciphering algorithm
     *  @param	 string		$forceseed	To force the seed
     *	@return  string					encoded string, with format 'dolcrypt:CIPHERING:seed:cryptedpass'
     *  @since v17
     *  @see dolDecrypt(), dol_hash()
     */
    function dolEncrypt($chain, $key = '', $ciphering = '', $forceseed = '')
    {
        global $conf;
        global $dolibarr_disable_dolcrypt_for_debug;

        if ($chain === '' || is_null($chain)) {
            return '';
        }

        $reg = array();
        if (preg_match('/^dolcrypt:([^:]+):(.+)$/', $chain, $reg)) {
            // The $chain is already a encrypted string
            return $chain;
        }

        if (empty($key)) {
            if (!empty($conf->file->dolcrypt_key)) {
                // If dolcrypt_key is defined, we used it in priority. Note: this param was never been set for the moment.
                $key = $conf->file->dolcrypt_key;
            } else {
                // We fall back on the instance_unique_id (coming from $dolibarr_main_instance_unique_id, for backward compatibility).
                $key = $conf->file->instance_unique_id;
            }
        }
        if (empty($ciphering)) {
            $ciphering = constant('MAIN_SECURITY_REVERSIBLE_ALGO');
        }

        $newchain = $chain;

        if (function_exists('openssl_encrypt') && empty($dolibarr_disable_dolcrypt_for_debug)) {
            if (empty($key)) {
                return $chain;
            }

            $ivlen = 16;
            if (function_exists('openssl_cipher_iv_length')) {
                $ivlen = openssl_cipher_iv_length($ciphering);
            }
            if ($ivlen === false || $ivlen < 1 || $ivlen > 32) {
                $ivlen = 16;
            }
            if (empty($forceseed)) {
                $ivseed = dolGetRandomBytes($ivlen);
            } else {
                $ivseed = dol_substr(md5($forceseed), 0, $ivlen, 'ascii', 1);
            }

            $newchain = openssl_encrypt($chain, $ciphering, $key, 0, $ivseed);
            return 'dolcrypt:' . $ciphering . ':' . $ivseed . ':' . $newchain;
        } else {
            return $chain;
        }
    }
}

if (!function_exists('dolDecrypt')) {
    /**
     *	Decode a string with a symmetric encryption. Used to decrypt sensitive data saved into database.
     *  Note: If a backup is restored onto another instance with a different $conf->file->instance_unique_id, then decoded value will differ.
     *
     *	@param   string		$chain		string to decode
     *	@param   string		$key		If '', we use $conf->file->dolcrypt_key else $conf->file->instance_unique_id
     *	@return  string					encoded string
     *  @since v17
     *  @see dolEncrypt(), dol_hash()
     */
    function dolDecrypt($chain, $key = '')
    {
        global $conf;

        if ($chain === '' || is_null($chain)) {
            return '';
        }

        if (empty($key)) {
            if (!empty($conf->file->dolcrypt_key)) {
                // If dolcrypt_key is defined, we used it in priority. Note: this param was never been set for the moment.
                $key = $conf->file->dolcrypt_key;
            } else {
                // We fall back on the instance_unique_id (coming from $dolibarr_main_instance_unique_id, for backward compatibility).
                $key = !empty($conf->file->instance_unique_id) ? $conf->file->instance_unique_id : "";
            }
        }

        $reg = array();

        // Old method (no more used, kept for compatibility)
        if (preg_match('/^crypted:(.+)$/', $chain, $reg)) {
            return dol_decode($reg[1]);
        }

        // New method
        if (preg_match('/^dolcrypt:([^:]+):(.+)$/', $chain, $reg)) {
            // Do not enable this log, except during debug
            //dol_syslog("We try to decrypt the chain: ".$chain, LOG_DEBUG);

            $ciphering = $reg[1];
            if (function_exists('openssl_decrypt')) {
                if (empty($key)) {
                    dol_syslog("Error dolDecrypt decrypt key is empty", LOG_WARNING);
                    return $chain;
                }
                $tmpexplode = explode(':', $reg[2]);
                if (!empty($tmpexplode[1]) && is_string($tmpexplode[0])) {
                    $newchain = openssl_decrypt($tmpexplode[1], $ciphering, $key, 0, $tmpexplode[0]);
                } else {
                    $newchain = openssl_decrypt((string) $tmpexplode[0], $ciphering, $key, 0, '');
                }
                // Test validity of decryption
                if (!ascii_check($newchain)) {
                    dol_syslog("Error dolDecrypt failed: The key dolibarr_main_dolcrypt or dolibarr_main_instance_unique_id, found in conf.php file, is the the one used to encrypt this encrypted string", LOG_ERR);
                    return $chain;
                }
            } else {
                dol_syslog("Error dolDecrypt openssl_decrypt is not available", LOG_ERR);
                return $chain;
            }
            return $newchain;
        } else {
            return $chain;
        }
    }
}
