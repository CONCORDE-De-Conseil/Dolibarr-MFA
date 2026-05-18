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
 * \file    mfa/class/mfaattemptservice.class.php
 * \ingroup mfa
 * \brief   Persistent storage and reporting for MFA failed attempts and lockouts.
 */

/**
 * Class MFAAttemptService
 *
 * Tracks MFA failed attempts for both login and setup scopes,
 * manages lock state, and provides history data for administrators.
 */
class MFAAttemptService
{
    /**
     * @var string MFA login verification scope.
     */
    const SCOPE_LOGIN = 'login';

    /**
     * @var string MFA enrollment/setup verification scope.
     */
    const SCOPE_SETUP = 'setup';

    /**
     * @var string Failure event type.
     */
    const EVENT_FAILURE = 'failure';

    /**
     * @var string Lock event type.
     */
    const EVENT_LOCK = 'lock';

    /**
     * @var string Reset event type.
     */
    const EVENT_RESET = 'reset';

    /**
     * @var string Success event type.
     */
    const EVENT_SUCCESS = 'success';

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
     * Return the remaining lock time for a user and scope.
     *
     * @param int    $userId User identifier.
     * @param int    $entity Entity identifier.
     * @param string $scope  MFA scope.
     * @return int           Remaining lock time in seconds.
     */
    public function getCooldownRemaining($userId, $entity, $scope)
    {
        $state = $this->fetchState($userId, $entity, $scope);
        if (empty($state) || empty($state->locked_until)) {
            return 0;
        }

        $remaining = $this->db->jdate($state->locked_until) - dol_now();
        if ($remaining <= 0) {
            $this->resetAttempts($userId, $entity, $scope, 0, false);
            return 0;
        }

        return max(0, (int) $remaining);
    }

    /**
     * Record a failed attempt and apply lockout if the threshold is reached.
     *
     * @param int    $userId       User identifier.
     * @param int    $entity       Entity identifier.
     * @param string $scope        MFA scope.
     * @param int    $maxAttempts  Maximum attempts before lock.
     * @param int    $cooldown     Lock duration in seconds.
     * @param string $ipAddress    Source IP address.
     * @return int                 Remaining lock time in seconds after recording the failure.
     */
    public function recordFailedAttempt($userId, $entity, $scope, $maxAttempts, $cooldown, $ipAddress = '')
    {
        $userId = (int) $userId;
        $entity = (int) $entity;
        $scope = $this->sanitizeScope($scope);
        $ipAddress = $this->sanitizeIpAddress($ipAddress);

        $state = $this->fetchState($userId, $entity, $scope);
        $attemptCount = empty($state) ? 0 : (int) $state->attempt_count;
        $lockedUntilTimestamp = 0;
        if (!empty($state) && !empty($state->locked_until)) {
            $lockedUntilTimestamp = (int) $this->db->jdate($state->locked_until);
        }

        if ($lockedUntilTimestamp > dol_now()) {
            $this->insertLog($userId, $entity, $scope, self::EVENT_FAILURE, $attemptCount, $lockedUntilTimestamp, $ipAddress, '', 0);
            return max(0, $lockedUntilTimestamp - dol_now());
        }

        if ($lockedUntilTimestamp > 0 && $lockedUntilTimestamp <= dol_now()) {
            $attemptCount = 0;
        }

        $attemptCount++;
        if ($attemptCount >= (int) $maxAttempts) {
            $lockedUntilTimestamp = dol_now() + (int) $cooldown;
            $this->saveState($userId, $entity, $scope, $attemptCount, $lockedUntilTimestamp, $ipAddress);
            $this->insertLog($userId, $entity, $scope, self::EVENT_LOCK, $attemptCount, $lockedUntilTimestamp, $ipAddress, '', 0);
            return max(0, $lockedUntilTimestamp - dol_now());
        }

        $this->saveState($userId, $entity, $scope, $attemptCount, 0, $ipAddress);
        $this->insertLog($userId, $entity, $scope, self::EVENT_FAILURE, $attemptCount, 0, $ipAddress, '', 0);
        return 0;
    }

    /**
     * Reset lock state for a user.
     *
     * @param int         $userId        User identifier.
     * @param int         $entity        Entity identifier.
     * @param string|null $scope         Specific scope or null for all scopes.
     * @param int         $actionUserId  Administrator who performed the reset.
     * @param bool        $logReset      True to add a reset event into history.
     * @return bool                      True on success.
     */
    public function resetAttempts($userId, $entity, $scope = null, $actionUserId = 0, $logReset = true)
    {
        $userId = (int) $userId;
        $entity = (int) $entity;
        $actionUserId = (int) $actionUserId;

        if ($scope !== null && $scope !== '') {
            $scope = $this->sanitizeScope($scope);
        }

        $states = $this->fetchStatesForReset($userId, $entity, $scope);
        if ($logReset) {
            foreach ($states as $state) {
                $this->insertLog(
                    $userId,
                    $entity,
                    $state->scope,
                    self::EVENT_RESET,
                    (int) $state->attempt_count,
                    0,
                    '',
                    'Manual reset',
                    $actionUserId
                );
            }
        }

        $sql = "DELETE FROM " . MAIN_DB_PREFIX . "mfa_attempt_state";
        $sql .= " WHERE fk_user = " . $userId . " AND entity = " . $entity;
        if (!empty($scope)) {
            $sql .= " AND scope = '" . $this->db->escape($scope) . "'";
        }

        return (bool) $this->db->query($sql);
    }

    /**
     * Record a successful MFA verification and clear state for the scope.
     *
     * @param int    $userId    User identifier.
     * @param int    $entity    Entity identifier.
     * @param string $scope     MFA scope.
     * @param string $ipAddress Source IP address.
     * @return void
     */
    public function markSuccessfulAttempt($userId, $entity, $scope, $ipAddress = '')
    {
        $userId = (int) $userId;
        $entity = (int) $entity;
        $scope = $this->sanitizeScope($scope);
        $ipAddress = $this->sanitizeIpAddress($ipAddress);

        $state = $this->fetchState($userId, $entity, $scope);
        if (!empty($state)) {
            $this->insertLog($userId, $entity, $scope, self::EVENT_SUCCESS, (int) $state->attempt_count, 0, $ipAddress, '', 0);
        }

        $this->resetAttempts($userId, $entity, $scope, 0, false);
    }

    /**
     * List current attempt states for the admin page.
     *
     * @param string $search     Search string against login or user name.
     * @param int    $onlyLocked 1 to restrict to currently locked users.
     * @param int    $limit      Max rows to return.
     * @return array<int,object> Result rows.
     */
    public function getAttemptStateList($search = '', $onlyLocked = 0, $limit = 100)
    {
        $sql = "SELECT s.rowid, s.fk_user, s.entity, s.scope, s.attempt_count, s.last_attempt, s.last_ip, s.locked_until,";
        $sql .= " u.login, u.firstname, u.lastname";
        $sql .= " FROM " . MAIN_DB_PREFIX . "mfa_attempt_state as s";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user as u ON u.rowid = s.fk_user";
        $sql .= " WHERE 1 = 1";

        if (!empty($search)) {
            $searchSql = $this->db->escape('%' . $search . '%');
            $sql .= " AND (u.login LIKE '" . $searchSql . "'";
            $sql .= " OR u.firstname LIKE '" . $searchSql . "'";
            $sql .= " OR u.lastname LIKE '" . $searchSql . "')";
        }

        if (!empty($onlyLocked)) {
            $sql .= " AND s.locked_until IS NOT NULL AND s.locked_until > '" . $this->db->idate(dol_now()) . "'";
        }

        $sql .= " ORDER BY";
        $sql .= " CASE WHEN s.locked_until IS NOT NULL AND s.locked_until > '" . $this->db->idate(dol_now()) . "' THEN 0 ELSE 1 END ASC,";
        $sql .= " s.last_attempt DESC";
        $sql .= " LIMIT " . ((int) $limit);

        $resql = $this->db->query($sql);
        $rows = array();
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    /**
     * List recent MFA attempt log events for the admin page.
     *
     * @param string $search Search string against login or user name.
     * @param int    $limit  Max rows to return.
     * @return array<int,object> Result rows.
     */
    public function getRecentLogs($search = '', $limit = 100)
    {
        $sql = "SELECT l.rowid, l.fk_user, l.entity, l.scope, l.event_type, l.attempt_count, l.locked_until, l.ip_address, l.note, l.fk_user_action, l.datec,";
        $sql .= " u.login, u.firstname, u.lastname,";
        $sql .= " ua.login as action_login";
        $sql .= " FROM " . MAIN_DB_PREFIX . "mfa_attempt_log as l";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user as u ON u.rowid = l.fk_user";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user as ua ON ua.rowid = l.fk_user_action";
        $sql .= " WHERE 1 = 1";

        if (!empty($search)) {
            $searchSql = $this->db->escape('%' . $search . '%');
            $sql .= " AND (u.login LIKE '" . $searchSql . "'";
            $sql .= " OR u.firstname LIKE '" . $searchSql . "'";
            $sql .= " OR u.lastname LIKE '" . $searchSql . "')";
        }

        $sql .= " ORDER BY l.datec DESC";
        $sql .= " LIMIT " . ((int) $limit);

        $resql = $this->db->query($sql);
        $rows = array();
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    /**
     * Load current state for a user and scope.
     *
     * @param int    $userId User identifier.
     * @param int    $entity Entity identifier.
     * @param string $scope  MFA scope.
     * @return object|null   Current state row or null.
     */
    private function fetchState($userId, $entity, $scope)
    {
        $sql = "SELECT rowid, fk_user, entity, scope, attempt_count, last_attempt, last_ip, locked_until";
        $sql .= " FROM " . MAIN_DB_PREFIX . "mfa_attempt_state";
        $sql .= " WHERE fk_user = " . ((int) $userId);
        $sql .= " AND entity = " . ((int) $entity);
        $sql .= " AND scope = '" . $this->db->escape($scope) . "'";

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            if ($obj) {
                return $obj;
            }
        }

        return null;
    }

    /**
     * Load states to be reset.
     *
     * @param int         $userId User identifier.
     * @param int         $entity Entity identifier.
     * @param string|null $scope  Optional scope filter.
     * @return array<int,object>  Matching states.
     */
    private function fetchStatesForReset($userId, $entity, $scope = null)
    {
        $sql = "SELECT rowid, fk_user, entity, scope, attempt_count, locked_until";
        $sql .= " FROM " . MAIN_DB_PREFIX . "mfa_attempt_state";
        $sql .= " WHERE fk_user = " . ((int) $userId);
        $sql .= " AND entity = " . ((int) $entity);
        if ($scope !== null && $scope !== '') {
            $sql .= " AND scope = '" . $this->db->escape($scope) . "'";
        }

        $rows = array();
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    /**
     * Insert or update current attempt state.
     *
     * @param int    $userId          User identifier.
     * @param int    $entity          Entity identifier.
     * @param string $scope           MFA scope.
     * @param int    $attemptCount    Current attempt count.
     * @param int    $lockedUntilTs   Lock expiration timestamp.
     * @param string $ipAddress       Source IP address.
     * @return void
     */
    private function saveState($userId, $entity, $scope, $attemptCount, $lockedUntilTs, $ipAddress)
    {
        $state = $this->fetchState($userId, $entity, $scope);
        $lockedUntilSql = $lockedUntilTs > 0 ? "'" . $this->db->idate($lockedUntilTs) . "'" : "NULL";
        $lastAttemptSql = "'" . $this->db->idate(dol_now()) . "'";

        if (!empty($state)) {
            $sql = "UPDATE " . MAIN_DB_PREFIX . "mfa_attempt_state SET";
            $sql .= " attempt_count = " . ((int) $attemptCount);
            $sql .= ", last_attempt = " . $lastAttemptSql;
            $sql .= ", last_ip = '" . $this->db->escape($ipAddress) . "'";
            $sql .= ", locked_until = " . $lockedUntilSql;
            $sql .= " WHERE rowid = " . ((int) $state->rowid);
            $this->db->query($sql);
            return;
        }

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "mfa_attempt_state (fk_user, entity, scope, attempt_count, last_attempt, last_ip, locked_until, datec)";
        $sql .= " VALUES (";
        $sql .= ((int) $userId) . ", ";
        $sql .= ((int) $entity) . ", ";
        $sql .= "'" . $this->db->escape($scope) . "', ";
        $sql .= ((int) $attemptCount) . ", ";
        $sql .= $lastAttemptSql . ", ";
        $sql .= "'" . $this->db->escape($ipAddress) . "', ";
        $sql .= $lockedUntilSql . ", ";
        $sql .= "'" . $this->db->idate(dol_now()) . "')";
        $this->db->query($sql);
    }

    /**
     * Insert an attempt log row.
     *
     * @param int    $userId         User identifier.
     * @param int    $entity         Entity identifier.
     * @param string $scope          MFA scope.
     * @param string $eventType      Event type.
     * @param int    $attemptCount   Attempt count at event time.
     * @param int    $lockedUntilTs  Lock expiration timestamp.
     * @param string $ipAddress      Source IP address.
     * @param string $note           Optional note.
     * @param int    $actionUserId   Administrator actor.
     * @return void
     */
    private function insertLog($userId, $entity, $scope, $eventType, $attemptCount, $lockedUntilTs, $ipAddress, $note, $actionUserId)
    {
        $lockedUntilSql = $lockedUntilTs > 0 ? "'" . $this->db->idate($lockedUntilTs) . "'" : "NULL";

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "mfa_attempt_log (fk_user, entity, scope, event_type, attempt_count, locked_until, ip_address, note, fk_user_action, datec)";
        $sql .= " VALUES (";
        $sql .= ((int) $userId) . ", ";
        $sql .= ((int) $entity) . ", ";
        $sql .= "'" . $this->db->escape($scope) . "', ";
        $sql .= "'" . $this->db->escape($eventType) . "', ";
        $sql .= ((int) $attemptCount) . ", ";
        $sql .= $lockedUntilSql . ", ";
        $sql .= "'" . $this->db->escape($ipAddress) . "', ";
        $sql .= "'" . $this->db->escape($note) . "', ";
        $sql .= ((int) $actionUserId) . ", ";
        $sql .= "'" . $this->db->idate(dol_now()) . "')";
        $this->db->query($sql);
    }

    /**
     * Normalize the MFA scope.
     *
     * @param string $scope MFA scope.
     * @return string       Sanitized scope.
     */
    private function sanitizeScope($scope)
    {
        return ($scope === self::SCOPE_SETUP) ? self::SCOPE_SETUP : self::SCOPE_LOGIN;
    }

    /**
     * Normalize an IP address for storage.
     *
     * @param string $ipAddress Input IP address.
     * @return string           Safe value to store.
     */
    private function sanitizeIpAddress($ipAddress)
    {
        $ipAddress = trim((string) $ipAddress);
        return substr($ipAddress, 0, 64);
    }
}
