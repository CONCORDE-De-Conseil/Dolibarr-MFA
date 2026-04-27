--
-- Script run when an upgrade of Dolibarr is done. Whatever is the Dolibarr version.
--

CREATE TABLE IF NOT EXISTS llx_mfa_attempt_state (
  rowid           integer AUTO_INCREMENT PRIMARY KEY,
  fk_user         integer NOT NULL,
  entity          integer DEFAULT 1 NOT NULL,
  scope           varchar(16) NOT NULL,
  attempt_count   integer DEFAULT 0 NOT NULL,
  last_attempt    datetime,
  last_ip         varchar(64),
  locked_until    datetime,
  tms             timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  datec           datetime,
  UNIQUE KEY uk_mfa_attempt_state (fk_user, entity, scope),
  KEY idx_mfa_attempt_state_locked_until (locked_until)
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_mfa_attempt_log (
  rowid           integer AUTO_INCREMENT PRIMARY KEY,
  fk_user         integer NOT NULL,
  entity          integer DEFAULT 1 NOT NULL,
  scope           varchar(16) NOT NULL,
  event_type      varchar(16) NOT NULL,
  attempt_count   integer DEFAULT 0 NOT NULL,
  locked_until    datetime,
  ip_address      varchar(64),
  note            varchar(255),
  fk_user_action  integer DEFAULT 0 NOT NULL,
  datec           datetime,
  KEY idx_mfa_attempt_log_user (fk_user, entity, scope),
  KEY idx_mfa_attempt_log_datec (datec)
) ENGINE=innodb;
