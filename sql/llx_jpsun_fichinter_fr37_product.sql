-- Copyright (C) 2026 JPSUN

CREATE TABLE llx_jpsun_fichinter_fr37_product (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  fk_fr37 integer NOT NULL,
  role varchar(32) NOT NULL,
  fk_product integer NOT NULL
) ENGINE=innodb;

