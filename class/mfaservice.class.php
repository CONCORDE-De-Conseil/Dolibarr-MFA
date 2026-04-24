<?php

class MFAService
{
    private $db;

    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    public function getForUser($user_id)
    {
        require_once dol_buildpath('/mfa/class/mfa.class.php');
        $mfa = new MFA($this->db);
        if ($mfa->fetch(0, $user_id) > 0) {
            return $mfa;
        }
        return null;
    }

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

    public function enableMFA(User $user)
    {
        $mfa = $this->getForUser($user->id);
        if ($mfa) {
            $mfa->enabled = 1;
            return $mfa->update($user);
        }
        return -1;
    }

    public function disableForUser(User $user)
    {
        $mfa = $this->getForUser($user->id, $user->entity);
        if ($mfa) {
            $mfa->enabled = 0;
            return $mfa->update($user);
        }
        return 0;
    }

    public function generateSecret()
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    public function getProvisioningUri($login, $secret)
    {
        $issuer = getDolGlobalString('MAIN_APPLICATION_TITLE', 'Dolibarr');

        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $login)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer);
    }

    public function verifyCode($secret, $code)
    {
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timeWindow = floor(time() / 30);

        for ($i = -1; $i <= 1; $i++) {
            if ($this->calculateCode($secret, $timeWindow + $i) === $code) {
                return true;
            }
        }

        return false;
    }

    private function calculateCode($secret, $timeWindow)
    {
        $key = $this->base32Decode($secret);

        // Proper 64-bit time
        $time = pack('N*', 0) . pack('N*', $timeWindow);

        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;

        $otp = (
            (ord($hash[$offset]) & 0x7f) << 24 |
            (ord($hash[$offset + 1]) & 0xff) << 16 |
            (ord($hash[$offset + 2]) & 0xff) << 8 |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad($otp, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode($base32)
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bufferSize = 0;
        $binary = '';

        foreach (str_split($base32) as $char) {
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
}
