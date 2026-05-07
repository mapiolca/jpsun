ALTER TABLE llx_c_jpsun_consuel_case ADD UNIQUE INDEX uk_c_jpsun_consuel_case_entity_code (entity, code);
ALTER TABLE llx_c_jpsun_consuel_case ADD INDEX idx_c_jpsun_consuel_case_active (active);

