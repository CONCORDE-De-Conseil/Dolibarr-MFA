CREATE TABLE llx_mfa_user_totp (
  rowid           integer AUTO_INCREMENT PRIMARY KEY,
  fk_user         integer NOT NULL,
  entity          integer DEFAULT 1 NOT NULL,
  enabled         integer DEFAULT 0 NOT NULL,
  secret          text,
  tms             timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  datec           datetime
) ENGINE=innodb;

ALTER TABLE llx_mfa_user_totp ADD UNIQUE INDEX uk_mfa_user_totp (fk_user, entity);
