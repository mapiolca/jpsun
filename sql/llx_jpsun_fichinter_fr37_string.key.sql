ALTER TABLE llx_jpsun_fichinter_fr37_string ADD INDEX idx_jpsun_fichinter_fr37_string_fr37 (fk_fr37);
ALTER TABLE llx_jpsun_fichinter_fr37_string ADD CONSTRAINT fk_jpsun_fichinter_fr37_string_fr37 FOREIGN KEY (fk_fr37) REFERENCES llx_jpsun_fichinter_fr37(rowid);

