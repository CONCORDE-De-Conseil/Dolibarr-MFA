<?php
/* Copyright (C) 2024 Your Name */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

/**
 * Class MFA
 *
 * Object model for MFA user settings.
 */
class MFA extends CommonObject
{
    /**
     * @var string ID to identify managed object
     */
    public $element = 'mfa';

    /**
     * @var string Name of table without prefix where object is stored
     */
    public $table_element = 'mfa_user_totp';

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var int User ID
     */
    public $fk_user;

    /**
     * @var int Entity
     */
    public $entity;

    /**
     * @var int Enabled flag
     */
    public $enabled;

    /**
     * @var string Encrypted TOTP secret
     */
    public $secret;

    /**
     * @var int Creation date
     */
    public $datec;

    /**
     * @var int last modification date
     */
    public $tms;

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
     * Create object into database
     *
     * @param  User	$user		User that creates
     * @param  int	$notrigger	0=launch triggers after, 1=disable triggers
     * @return int				Return integer <0 if KO, Id of created object if OK
     */
    public function create(User $user, $notrigger = 0)
    {
        $this->datec = dol_now();
        if (empty($this->entity)) {
            $this->entity = $user->entity;
        }

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . $this->table_element . " (fk_user, entity, enabled, secret, datec)";
        $sql .= " VALUES (" . ((int) $this->fk_user) . ", " . ((int) $this->entity) . ", " . ((int) $this->enabled) . ", ";
        $sql .= " '" . $this->db->escape($this->secret) . "', '" . $this->db->idate($this->datec) . "')";

        dol_syslog(get_class($this) . "::create", LOG_DEBUG);
        if ($this->db->query($sql)) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . $this->table_element);
            return $this->id;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Load object from database
     *
     * @param  int $id      Object id
     * @param  int $user_id Filter by user id
     * @return int          Return integer <0 if KO, 0 if not found, >0 if OK
     */
    public function fetch($id, $user_id = 0)
    {
        $sql = "SELECT rowid, fk_user, entity, enabled, secret, datec, tms";
        $sql .= " FROM " . MAIN_DB_PREFIX . $this->table_element;
        if ($id) {
            $sql .= " WHERE rowid = " . ((int) $id);
        } elseif ($user_id) {
            $sql .= " WHERE fk_user = " . ((int) $user_id) . " AND entity = " . ((int) $this->entity);
        } else {
            return -1;
        }

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            if ($obj) {
                $this->id      = (int) $obj->rowid;
                $this->fk_user = (int) $obj->fk_user;
                $this->entity  = (int) $obj->entity;
                $this->enabled = (int) $obj->enabled;
                $this->secret  = $obj->secret;
                $this->datec   = $this->db->jdate($obj->datec);
                $this->tms     = $this->db->jdate($obj->tms);
                return 1;
            }
            return 0;
        }
        $this->error = $this->db->lasterror();
        return -1;
    }

    /**
     * Update object into database
     *
     * @param  User	$user		User that modifies
     * @param  int	$notrigger	0=launch triggers after, 1=disable triggers
     * @return int				Return integer <0 if KO, >0 if OK
     */
    public function update(User $user, $notrigger = 0)
    {
        $sql = "UPDATE " . MAIN_DB_PREFIX . $this->table_element . " SET";
        $sql .= " enabled = " . ((int) $this->enabled);
        $sql .= ", secret = '" . $this->db->escape($this->secret) . "'";
        $sql .= " WHERE rowid = " . ((int) $this->id);

        dol_syslog(get_class($this) . "::update", LOG_DEBUG);
        if ($this->db->query($sql)) {
            return 1;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }
}
