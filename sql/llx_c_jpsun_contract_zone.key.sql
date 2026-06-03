-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
--
-- Indexes for SOLEIL AQUITAIN contract geographical zones dictionary.

ALTER TABLE llx_c_jpsun_contract_zone ADD UNIQUE INDEX uk_c_jpsun_contract_zone_code (code);
ALTER TABLE llx_c_jpsun_contract_zone ADD INDEX idx_c_jpsun_contract_zone_active (active);
ALTER TABLE llx_c_jpsun_contract_zone ADD INDEX idx_c_jpsun_contract_zone_position (position);
