-- Copyright (C) 2026 JPSUN

CREATE TABLE llx_jpsun_fichinter_fr37_string (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  fk_fr37 integer NOT NULL,
  string_no integer NOT NULL,
  voltage double(24,8) DEFAULT NULL,
  pv_count integer DEFAULT NULL,
  position integer DEFAULT 0 NOT NULL
) ENGINE=innodb;

