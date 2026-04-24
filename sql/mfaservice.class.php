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
     * @return int            Result
     */
    public function enableForUser(User $user, $secret)
    {
        $mfa = $this->getForUser($user->id, $user->entity);
        if (!$mfa) {
            $mfa = new MFA($this->db);
            $mfa->fk_user = $user->id;
            $mfa->entity = $user->entity;
        }

        require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';
        $mfa->secret = dolEncrypt($secret);
        $mfa->enabled = 1;

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
}
