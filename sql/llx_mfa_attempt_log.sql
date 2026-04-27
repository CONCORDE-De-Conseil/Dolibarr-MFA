CREATE TABLE llx_mfa_attempt_log (
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
