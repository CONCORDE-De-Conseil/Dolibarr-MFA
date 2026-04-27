<?php
/* Copyright (C) 2026 CONCORDE de Conseil <contact@concorde.tn>
 * Copyright (C) 2026 Ali WERGHEMMI <ali.werghemmi@concorde.tn>
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
 * \file    mfa/class/mfaservice.class.php
 * \ingroup mfa
 * \brief   Service class for MFA secret management and TOTP verification.
 */

require_once dol_buildpath('/mfa/lib/mfa.lib.php');

/**
 * Class MFAService
 *
 * Provides helpers to create, update and validate MFA configuration
 * for Dolibarr users using TOTP secrets.
 */
class MFAService
{
    /**
     * @var DoliDB Database handler.
     */
    private $db;

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Load MFA settings for a user.
     *
     * @param int $user_id User identifier.
     * @return MFA|null    MFA object if found, null otherwise.
     */
    public function getForUser($user_id, $entity = null)
    {
        require_once dol_buildpath('/mfa/class/mfa.class.php');
        $mfa = new MFA($this->db);
        if ($entity !== null) {
            $mfa->entity = (int) $entity;
        }
        if ($mfa->fetch(0, $user_id) > 0) {
            return $mfa;
        }
        return null;
    }

    /**
     * Create or update the encrypted secret associated with a user.
     *
     * @param User   $user    User whose MFA record must be updated.
     * @param string $secret  Plain TOTP secret to encrypt before storage.
     * @param int    $enabled Whether MFA should be enabled after save.
     * @return int            Created row id on insert, 1 on update, negative value on error.
     */
    public function createOrUpdateSecret(User $user, $secret, $enabled = 0)
    {
        $mfa = $this->getForUser($user->id);
        require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';
        if (!$mfa) {
            $mfa = new MFA($this->db);
            $mfa->fk_user = $user->id;
            $mfa->entity = $user->entity;
        }


        $mfa->secret = dolEncrypt($secret);
        $mfa->enabled = $enabled;

        if ($mfa->id) {
            return $mfa->update($user);
        } else {
            return $mfa->create($user);
        }
    }

    /**
     * Enable MFA for a user when a record already exists.
     *
     * @param User $user User to update.
     * @return int       1 on success, negative value if update fails, -1 if no record exists.
     */
    public function enableMFA(User $user)
    {
        $mfa = $this->getForUser($user->id);
        if ($mfa) {
            $mfa->enabled = 1;
            return $mfa->update($user);
        }
        return -1;
    }

    /**
     * Disable MFA for a user when a record already exists.
     *
     * @param User $user User to update.
     * @return int       1 on success, negative value if update fails, 0 if no record exists.
     */
    public function disableForUser(User $user)
    {
        $mfa = $this->getForUser($user->id, $user->entity);
        if ($mfa) {
            $mfa->enabled = 0;
            return $mfa->update($user);
        }
        return 0;
    }

    /**
     * Generate a random 16-character Base32 secret for TOTP provisioning.
     *
     * @return string Generated secret.
     */
    public function generateSecret()
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Build the otpauth provisioning URI used by authenticator applications.
     *
     * @param string $login  User login displayed in authenticator apps.
     * @param string $secret Base32 TOTP secret.
     * @return string        Provisioning URI.
     */
    public function getProvisioningUri($login, $secret)
    {
        $issuer = getDolGlobalString('MAIN_APPLICATION_TITLE', 'Dolibarr');

        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $login)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer);
    }

    /**
     * Verify a submitted TOTP code against the current time window.
     *
     * Accepts the current 30-second time step and one adjacent step on each side
     * to tolerate slight clock drift between the server and authenticator device.
     *
     * @param string $secret Base32 TOTP secret.
     * @param string $code   Six-digit code entered by the user.
     * @return bool          True when the code is valid, false otherwise.
     */
    public function verifyCode($secret, $code)
    {
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $decodedSecret = $this->base32Decode($secret);
        if ($decodedSecret === false || $decodedSecret === '') {
            return false;
        }

        $timeWindow = floor(time() / 30);

        for ($i = -1; $i <= 1; $i++) {
            if ($this->calculateCode($decodedSecret, $timeWindow + $i) === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compute the TOTP code for a given 30-second time window.
     *
     * @param string $binarySecret Raw decoded TOTP secret.
     * @param int    $timeWindow Time window index.
     * @return string            Six-digit TOTP code.
     */
    private function calculateCode($binarySecret, $timeWindow)
    {
        // Proper 64-bit time
        $time = pack('N*', 0) . pack('N*', $timeWindow);

        $hash = hash_hmac('sha1', $time, $binarySecret, true);
        $offset = ord($hash[19]) & 0xf;

        $otp = (
            (ord($hash[$offset]) & 0x7f) << 24 |
            (ord($hash[$offset + 1]) & 0xff) << 16 |
            (ord($hash[$offset + 2]) & 0xff) << 8 |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad($otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decode a Base32-encoded secret into raw binary form.
     *
     * @param string $base32 Base32 encoded string.
     * @return string|false  Raw binary secret, or false if the input is malformed.
     */
    private function base32Decode($base32)
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bufferSize = 0;
        $binary = '';

        foreach (str_split(strtoupper((string) $base32)) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                return false;
            }

            $buffer = ($buffer << 5) | $pos;
            $bufferSize += 5;

            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $binary .= chr(($buffer >> $bufferSize) & 0xff);
            }
        }

        if ($bufferSize > 0 && (($buffer & ((1 << $bufferSize) - 1)) !== 0)) {
            return false;
        }

        return $binary;
    }
}
