# JPSUN sur [DOLIBARR ERP CRM](https://dolibarr.org)

## Informations

- Numéro du module : 999000
- Dernière mise à jour : 24/08/2026
- Éditeur : [JPSUN](https://jpsun.fr)
- Thème : Eldy Menu
- Licence : GPLv3
- Disponible sur : Windows - MacOS - Linux
- 
### Version

- Version : 1.22.2
- PHP : 8.0+
- Compatibilité : Dolibarr 20+

## Liens

- Support & Assistance : [Forum dolibarr.fr](https://dolibarr.fr) / Par mail à p.ardoin@jpsun.fr
- Documentation : [Wiki JPSUN](https://wiki.dolibarr.org/index.php/)

## Fonctionnalités

- Ajout de fonctionnalités diverses pour JPSUN.
- Rapport comptable des projets non soldés, avec agrégation des commandes, factures, achats, frais, temps et expéditions valorisées selon le réglage natif du module Marges.
- Ajout du modèle PDF fiche produit JPSUN pour les produits du catalogue.
- Intégration du modèle contrat JPSUN PRO avec le module Centrale PV.
- Intégration du modèle contrat SOLEIL AQUITAIN avec centrales PV liées, annexes natives, mentions légales configurables et zone issue des catégories de contrat `lmdbzoning`.

### Rapport des projets non soldés

Le menu **Comptabilité > Projets non soldés** présente les projets ouverts dont le total HT des commandes valides diffère du total HT des factures clients émises. L'accès nécessite l'un des droits natifs de consultation des rapports comptables, en comptabilité simplifiée ou en partie double. Les restrictions natives de visibilité des projets et des entités restent appliquées aux données affichées.

Les expéditions sont recalculées à partir des mouvements réels de stock et du coût courant fourni par le module Marges (meilleur prix fournisseur, PMP ou prix de revient). Les sources optionnelles désactivées sont signalées comme non disponibles et les coûts ou taux horaires manquants sont valorisés à zéro avec une alerte. La liste filtrée complète peut être exportée en CSV.

## Traductions

- Français
- Anglais
- Allemand
- Espagnol
- Italien

## Installation

- Depuis le menu "Déployer/Installer un module externe" de Dolibarr :
- Glisser l'archive ZIP 'module_easycrm-X.Y.Z' et cliquer sur "ENVOYER FICHIER"
- Glisser l'archive ZIP 'module_saturne-X.Y.Z' et cliquer sur "ENVOYER FICHIER"
- Activer le module dans la liste des Modules/Applications installés
