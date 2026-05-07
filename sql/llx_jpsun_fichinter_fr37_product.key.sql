ALTER TABLE llx_jpsun_fichinter_fr37_product ADD UNIQUE INDEX uk_jpsun_fichinter_fr37_product (fk_fr37, role, fk_product);
ALTER TABLE llx_jpsun_fichinter_fr37_product ADD INDEX idx_jpsun_fichinter_fr37_product_role (role);
ALTER TABLE llx_jpsun_fichinter_fr37_product ADD CONSTRAINT fk_jpsun_fichinter_fr37_product_fr37 FOREIGN KEY (fk_fr37) REFERENCES llx_jpsun_fichinter_fr37(rowid);
ALTER TABLE llx_jpsun_fichinter_fr37_product ADD CONSTRAINT fk_jpsun_fichinter_fr37_product_product FOREIGN KEY (fk_product) REFERENCES llx_product(rowid);

