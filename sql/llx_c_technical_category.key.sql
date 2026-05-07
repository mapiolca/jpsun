ALTER TABLE llx_c_technical_category ADD UNIQUE INDEX uk_c_technical_category_entity_code (entity, code);
ALTER TABLE llx_c_technical_category ADD INDEX idx_c_technical_category_active (active);

