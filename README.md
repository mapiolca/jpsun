# JPSUN sur [DOLIBARR ERP CRM](https://dolibarr.org)

## Informations

- Numéro du module : 999000
- Dernière mise à jour : 28/10/2026
- Éditeur : [JPSUN](https://jpsun.fr)
- Thème : Eldy Menu
- Licence : GPLv3
- Disponible sur : Windows - MacOS - Linux
- 
### Version

- Version : 1.23.0
- PHP : 8.0+
- Compatibilité : Dolibarr 20+

## Liens

- Support & Assistance : [Forum dolibarr.fr](https://dolibarr.fr) / Par mail à p.ardoin@jpsun.fr
- Documentation : [Wiki JPSUN](https://wiki.dolibarr.org/index.php/)

## Fonctionnalités

- Ajout de fonctionnalités diverses pour JPSUN.
- Actions de masse JPSUN sur les tâches disponibles jusqu’à Dolibarr 24 inclus ; désactivées à partir de Dolibarr 25.
- Rapport comptable des projets non soldés, avec agrégation des commandes, factures, achats, frais, temps et expéditions valorisées selon le réglage natif du module Marges.
- Ajout du modèle PDF fiche produit JPSUN pour les produits du catalogue.
- Le modèle PDF de stock utilise le PMP de l'entité active lorsque le partage des produits et le PMP par entité sont activés ; les valorisations incomplètes sont signalées.
- Intégration du modèle contrat JPSUN PRO avec le module Centrale PV.
- Intégration du modèle contrat SOLEIL AQUITAIN avec centrales PV liées, annexes natives, mentions légales configurables et zone issue des catégories de contrat `lmdbzoning`.

### Rapport des projets non soldés

Le menu **Comptabilité > Projets non soldés** présente les projets ouverts dont le total HT des commandes valides diffère du total HT des factures clients émises. L'accès nécessite l'un des droits natifs de consultation des rapports comptables, en comptabilité simplifiée ou en partie double. Les restrictions natives de visibilité des projets et des entités restent appliquées aux données affichées.

Les expéditions sont recalculées à partir des mouvements réels de stock et du coût courant fourni par le module Marges (meilleur prix fournisseur, PMP ou prix de revient). Les sources optionnelles désactivées sont signalées comme non disponibles et les coûts ou taux horaires manquants sont valorisés à zéro avec une alerte. La liste filtrée complète peut être exportée en CSV.

### Modèle de facture JPSUN — version 1.23.0

Le nouveau modèle de facture `jpsuninvoice`, dérivé du modèle natif **Sponge**, sépare les **Acomptes déduits** de la **Remise totale**, dans cet ordre et en montants HT. Les acomptes sont identifiés par les liaisons natives des lignes vers leurs factures d’acompte ; un avoir ou une ligne négative ordinaire n’est pas reclassé en acompte. Les totaux HT, TVA, TTC et le solde restent calculés et imprimés par Dolibarr. Les acomptes affectés comme règlements restent dans le bloc natif des paiements.

Ce modèle nécessite **Dolibarr 24 ou plus**, qui fournit les contrôles d’affichage des remises de Sponge ; il reste désactivé sous Dolibarr 20 à 23. Les factures de situation et les avoirs conservent le récapitulatif natif. Activer le modèle dans **Configuration > Modules > Factures > Modèles de document**, puis le sélectionner sur la facture. Les réglages JPSUN > Factures donnent accès à cet écran et l’onglet Compatibilité indique sa disponibilité.

Les limites et validations de la version `1.23.0` sont consignées dans [les preuves de développement](test/README-invoice.md).

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
