-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
--
-- Dictionary of SOLEIL AQUITAIN contract geographical zones.

CREATE TABLE llx_c_jpsun_contract_zone (
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	code varchar(64) NOT NULL,
	label varchar(255) NOT NULL,
	position integer DEFAULT 0 NOT NULL,
	active smallint DEFAULT 1 NOT NULL
) ENGINE=innodb;
