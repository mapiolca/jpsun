ALTER TABLE llx_jpsun_fichinter_fr37 ADD UNIQUE INDEX uk_jpsun_fichinter_fr37_fichinter (fk_fichinter);
ALTER TABLE llx_jpsun_fichinter_fr37 ADD INDEX idx_jpsun_fichinter_fr37_entity (entity);
ALTER TABLE llx_jpsun_fichinter_fr37 ADD CONSTRAINT fk_jpsun_fichinter_fr37_fichinter FOREIGN KEY (fk_fichinter) REFERENCES llx_fichinter(rowid);

