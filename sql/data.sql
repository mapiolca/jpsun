-- ============================================================================
-- Copyright (C) 2025 Pierre Ardoin
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see <http://www.gnu.org/licenses/>.
--
-- ============================================================================

-- CONST

DELETE FROM llx_const WHERE name='AGENDA_USE_EVENT_TYPE' AND entity='__ENTITY__';
DELETE FROM llx_const WHERE name='MAIN_ADD_EVENT_ON_ELEMENT_CARD' AND entity='__ENTITY__';

INSERT INTO llx_const (name, entity, value, type, visible, note) VALUES ('AGENDA_USE_EVENT_TYPE', '__ENTITY__', 1, 'chaine', 1, 'Add a mandatory field Type when creating an event');
INSERT INTO llx_const (name, entity, value, type, visible, note) VALUES ('MAIN_ADD_EVENT_ON_ELEMENT_CARD', '__ENTITY__', 1, 'chaine', 1, 'Allow to create an event from a document (proposal, order, invoice)');

-- ENEDIS EVENT'S TYPES

DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_INFO';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_R_CLIENT';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_REL';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_SEND';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_R_ENEDIS';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_R_JPSUN';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_R_TIERS';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_COMP';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_TR';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_OK';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_AC';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_JPS_TRAV';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_ENE_TRAV';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_PAY';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_CONS_OK';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_MES_ASK';
DELETE FROM llx_c_actioncomm WHERE code='JPSUN_DDR_MES_OK';

INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('106', 'JPSUN_DDR_INFO', 'user', 'JPSUN_DDR_INFO', 'jpsun', '1', NULL, '9B59B6', 'fa-plug-circle-bolt', '100');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('103', 'JPSUN_DDR_R_CLIENT', 'user', 'JPSUN_DDR_R_CLIENT', 'jpsun', '1', NULL, 'BDC3C7  ', 'fa-plug-circle-exclamation', '101');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('105', 'JPSUN_DDR_REL', 'user', 'JPSUN_DDR_REL', 'jpsun', '1', NULL, 'F39C12', 'fa-plug-circle-bolt', '102');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('100', 'JPSUN_DDR_SEND', 'user', 'JPSUN_DDR_SEND', 'jpsun', '1', NULL, '3498DB', 'fa-plug', '103');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('101', 'JPSUN_DDR_R_ENEDIS', 'user', 'JPSUN_DDR_R_ENEDIS', 'jpsun', '1', NULL, 'C0392B', 'fa-plug-circle-xmark', '104');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('102', 'JPSUN_DDR_R_JPSUN', 'user', 'JPSUN_DDR_R_JPSUN', 'jpsun', '1', NULL, '3498DB', 'fa-plug-circle-bolt', '105');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('104', 'JPSUN_DDR_R_TIERS', 'user', 'JPSUN_DDR_R_TIERS', 'jpsun', '1', NULL, 'AAB7B8', 'fa-plug-circle-exclamation', '106');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('107', 'JPSUN_DDR_COMP', 'user', 'JPSUN_DDR_COMP', 'jpsun', '1', NULL, '27AE60', 'fa-plug-circle-check', '107');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('108', 'JPSUN_DDR_TR', 'user', 'JPSUN_DDR_TR', 'jpsun', '1', NULL, '27AE60', 'fa-paper-plane', '108');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('108', 'JPSUN_DDR_OK', 'user', 'JPSUN_DDR_OK', 'jpsun', '1', NULL, '27AE60', 'fa-handshake', '108');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('108', 'JPSUN_DDR_AC', 'user', 'JPSUN_DDR_AC', 'jpsun', '1', NULL, '27AE60', 'fa-file-invoice', '108');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('109', 'JPSUN_DDR_JPS_TRAV', 'user', 'JPSUN_DDR_JPS_TRAV', 'jpsun', '1', NULL, '27AE60', 'fa-hard-hat', '109');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('110', 'JPSUN_DDR_ENE_TRAV', 'user', 'JPSUN_DDR_ENE_TRAV', 'jpsun', '1', NULL, '27AE60', 'fa-hard-hat', '110');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('111', 'JPSUN_DDR_PAY', 'user', 'JPSUN_DDR_PAY', 'jpsun', '1', NULL, '27AE60', 'fa-receipt', '111');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('112', 'JPSUN_DDR_CONS_OK', 'user', 'JPSUN_DDR_CONS_OK', 'jpsun', '1', NULL, '27AE60', 'fa-check-double', '112');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('113', 'JPSUN_DDR_MES_ASK', 'user', 'JPSUN_DDR_MES_ASK', 'jpsun', '1', NULL, '27AE60', 'fa-plug', '113');
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES ('114', 'JPSUN_DDR_MES_OK', 'user', 'JPSUN_DDR_MES_OK', 'jpsun', '1', NULL, '27AE60', 'fa-solar-panel', '114');


-- TRADUCTIONS

---Enedis
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_SEND' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_R_ENEDIS' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_R_JPSUN' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_R_CLIENT' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_R_TIERS' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_REL' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_COMP' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_INFO' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_TR' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_OK' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_AC' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_JPS_TRAV' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_ENE_TRAV' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_PAY' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_CONS_OK' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_MES_ASK' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='JPSUN_DDR_MES_OK' AND entity='__ENTITY__';

DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_SEND' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_R_ENEDIS' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_R_JPSUN' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_R_CLIENT' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_R_TIERS' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_REL' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_COMP' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_INFO' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_TR' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_OK' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_AC' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_JPS_TRAV' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_ENE_TRAV' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_PAY' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_CONS_OK' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_MES_ASK' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ActionJPSUN_DDR_MES_OK' AND entity='__ENTITY__';

INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_SEND', 'DDR | JPSUN | Demande de raccordement');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_R_ENEDIS', 'DDR | ENEDIS | Dossier incomplet');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_R_JPSUN', 'DDR | JPSUN | Complément de dossier');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_R_CLIENT', 'DDR | Client | Retour d\'informations');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_R_TIERS', 'DDR | Autre | Réponse d\'un tiers');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_REL', 'DDR | JPSUN | Relance');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_COMP', 'DDR | T0 | Obtention de la complétude');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_INFO', 'DDR | JPSUN | Demande d\'informations');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_TR', 'DDR | ENEDIS | Proposition de raccordement transmise');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_OK', 'DDR | ENEDIS | Réception de l\'accord');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_AC', 'DDR | Client | Acompte réglé');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_JPS_TRAV', 'DDR | JPSUN | Travaux Réalisés');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_ENE_TRAV', 'DDR | ENEDIS | Travaux Réalisés');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_PAY', 'DDR | Client | Facture soldée');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_CONS_OK', 'DDR | JPSUN | Consuel mis à disposition');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_MES_ASK', 'DDR | JPSUN | Mise en service commandée');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'JPSUN_DDR_MES_OK', 'DDR | ENEDIS | Mise en service réalisée');

INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_SEND', 'DDR | JPSUN | Demande de raccordement');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_R_ENEDIS', 'DDR | ENEDIS | Dossier incomplet');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_R_JPSUN', 'DDR | JPSUN | Complément de dossier');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_R_CLIENT', 'DDR | Client | Retour d\'informations');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_R_TIERS', 'DDR | Autre | Réponse d\'un tiers');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_REL', 'DDR | JPSUN | Relance');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_COMP', 'DDR | T0 | Obtention de la complétude');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_INFO', 'DDR | JPSUN | Demande d\'informations');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_TR', 'DDR | ENEDIS | Proposition de raccordement transmise');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_OK', 'DDR | ENEDIS | Réception de l\'accord');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_AC', 'DDR | Client | Acompte réglé');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_JPS_TRAV', 'DDR | JPSUN | Travaux Réalisés');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_ENE_TRAV', 'DDR | ENEDIS | Travaux Réalisés');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_PAY', 'DDR | Client | Facture soldée');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_CONS_OK', 'DDR | JPSUN | Consuel mis à disposition');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_MES_ASK', 'DDR | JPSUN | Mise en service commandée');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ActionJPSUN_DDR_MES_OK', 'DDR | ENEDIS | Mise en service réalisée');

---Devis clients
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='Proposals' AND entity='__ENTITY__';
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'Proposals', 'Devis clients');

---Commandes clients
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='CustomersOrders' AND entity='__ENTITY__';
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'CustomersOrders', 'Commandes clients');

---Demande de prix fournisseurs
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='RefSupplierProposal' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='SupplierProposals' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='SupplierProposalsShort' AND entity='__ENTITY__';

INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'RefSupplierProposal', 'Réf. demande de prix');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'SupplierProposals', 'Devis fournisseurs');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'SupplierProposalsShort', 'Devis fournisseurs');


---Unités
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='unitENS' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='unitMPV' AND entity='__ENTITY__';

INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'unitENS', 'Ensemble');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'unitMPV', 'Modules Photovoltaïques');

---Divers
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='ExtrafieldMail' AND entity='__ENTITY__';
DELETE FROM llx_overwrite_trans WHERE lang='fr_FR' AND transkey='DeliveryDate' AND entity='__ENTITY__';

INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'ExtrafieldMail', 'Courriel');
INSERT IGNORE INTO `llx_overwrite_trans` (`rowid`, `entity`, `lang`, `transkey`, `transvalue`) VALUES (NULL, '__ENTITY__', 'fr_FR', 'DeliveryDate', 'Date de livraison');




