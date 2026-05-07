-- Copyright (C) 2026 JPSUN

CREATE TABLE llx_c_technical_category (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  code varchar(64) NOT NULL,
  label varchar(255) NOT NULL,
  description text,
  active tinyint DEFAULT 1 NOT NULL,
  position integer DEFAULT 0 NOT NULL
) ENGINE=innodb;

