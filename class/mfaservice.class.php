<?php
/* Copyright (C) 2024 Your Name */

/**
 * Class MFAService
 *
 * Service class for MFA logic (TOTP management).
 */
class MFAService
{
    /**
     * @var DoliDB Database handler
     */
    private $db;

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
     * Get MFA settings for a user
     *
     * @param  int $user_id User id
     * @param  int $entity  Entity id
     * @return MFA|null     MFA object or null
     */
    public function getForUser($user_id, $entity = 1)
    {
        require_once dol_buildpath('/mfa/class/mfa.class.php');
        $mfa = new MFA($this->db);
        $mfa->entity = $entity;
        if ($mfa->fetch(0, $user_id) > 0) {
            return $mfa;
        }
        return null;
    }

    /**
     * Enable or update MFA for a user
     *
     * @param  User   $user   The user for whom to enable MFA
     * @param  string $secret The secret to save (plaintext, will be encrypted)
     * @param  int    $enabled Set enabled status
     * @return int            Result
     */
    public function enableForUser(User $user, $secret, $enabled = 1)
    {
        $mfa = $this->getForUser($user->id, $user->entity);
        if (!$mfa) {
            $mfa = new MFA($this->db);
            $mfa->fk_user = $user->id;
            $mfa->entity = $user->entity;
        }

        require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';
        $mfa->secret = dolEncrypt($secret);
        $mfa->enabled = $enabled;

        if ($mfa->id) {
            return $mfa->update($user);
        } else {
            return $mfa->create($user);
        }
    }

    /**
     * Disable MFA for a user
     *
     * @param  User   $user   The user
     * @return int            Result
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
     * Generate a new TOTP secret (Base32)
     *
     * @return string
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
     * Check if a TOTP code is valid
     *
     * @param string $secret The plaintext secret (Base32)
     * @param string $code   The 6-digit code
     * @return bool
     */
    public function verifyCode($secret, $code)
    {
        if (empty($code) || strlen($code) != 6) {
            return false;
        }

        $timeWindow = floor(time() / 30);

        // Check current window and +/- 1 window for drift
        for ($i = -1; $i <= 1; $i++) {
            if ($this->calculateCode($secret, $timeWindow + $i) === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate TOTP code (Standard RFC 6238)
     *
     * @param string $secret     Base32 secret
     * @param int    $timeWindow Window count
     * @return string
     */
    private function calculateCode($secret, $timeWindow)
    {
        $key = $this->base32Decode($secret);
        $time = pack('N', $timeWindow);
        $time = str_pad($time, 8, chr(0), STR_PAD_LEFT);

        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $otp = (
            (ord($hash[$offset + 0]) & 0x7f) << 24 |
            (ord($hash[$offset + 1]) & 0xff) << 16 |
            (ord($hash[$offset + 2]) & 0xff) << 8 |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decodes a base32 string into binary.
     *
     * @param string $base32 String to decode
     * @return string Binary data
     */
    private function base32Decode($base32)
    {
        $base32 = strtoupper($base32);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bufferSize = 0;
        $binary = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];
            $pos = strpos($alphabet, $char);
            if ($pos === false) continue;

            $buffer = ($buffer << 5) | $pos;
            $bufferSize += 5;

            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $binary .= chr(($buffer >> $bufferSize) & 0xff);
            }
        }
        return $binary;
    }

    public function getProvisioningUri($login, $secret)
    {
        $issuer = getDolGlobalString('MAIN_APPLICATION_TITLE', 'Dolibarr');
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($login) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);
    }
}
