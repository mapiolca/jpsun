# JPSUN sur [DOLIBARR ERP CRM](https://dolibarr.org)

## Informations

- Numéro du module : 999000
- Dernière mise à jour : 03/11/2026
- Éditeur : [JPSUN](https://jpsun.fr)
- Thème : Eldy Menu
- Licence : GPLv3
- Disponible sur : Windows - MacOS - Linux
- 
### Version

- Version : 1.24.1
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

### Contrôle des acomptes clients avant Dolibarr 26 — version 1.24.0

Le rétroportage demandé de [Dolibarr/dolibarr#42407](https://github.com/Dolibarr/dolibarr/pull/42407) ajoute **Comptabilité > Régularisations > Contrôle des acomptes clients** par le hook natif `menuLeftMenuItems`, sans modification du core. Il nécessite les modules JPSUN, Comptabilité en partie double et Factures, un utilisateur interne et le droit natif de lecture des rapports comptables. Les restrictions commerciales s’appliquent avant pagination ; ce contrôle comptable reste dans l’entité courante, sans afficher les factures partagées d’une autre entité.

Le rapport reprend les encaissements, remboursements, avoirs et utilisations à la date d’arrêté, les factures consommatrices et les commandes directement liées accessibles. Les montants sont TTC dans la devise de la société. Le reliquat ne constitue ni un calcul de PCA ni un solde comptable, et aucune écriture n’est produite. Les liaisons et statuts actuels ne permettent pas de reconstituer les suppressions ou modifications historiques ni de prouver une livraison.

Le menu, l’accès direct et le service sont désactivés à partir de Dolibarr 26, préversions incluses. Si une instance antérieure expose déjà le contrôle natif, le hook conserve son entrée sans doublon. L’onglet Compatibilité précise les conditions d’accès. Après déploiement, actualiser les hooks persistés par le parcours natif de mise à jour du module, en conservant les réglages existants ; aucune activation ou réactivation automatique n’est effectuée.

Le pictogramme conserve `img/object_jpsun.png` et ajoute une copie identique `img/jpsun.png`, pour les deux conventions de résolution natives. L’ID historique `999000` et les droits existants sont conservés.

Les commandes, scénarios exécutés et limites figurent dans [les preuves de développement](test/README-customerdeposits.md).

Le contrôle est entièrement traduit en français et en anglais : ses 22 libellés couvrent les menus, colonnes, filtres, statuts, aides, totaux et conditions de compatibilité. Le catalogue du module est chargé par le mécanisme natif Dolibarr, sans dépendre de traductions françaises ajoutées au core.

### Retrait des installations historiques — version 1.24.1 en préparation

- Les 17 types d’événements ENEDIS `JPSUN_DDR_*` et leurs surcharges de traduction ne sont plus supprimés ni recréés lors de l’activation de JPSUN. Leurs données et réglages existants restent en place ; ProceduresPV porte le suivi des démarches de raccordement.
- L’onglet ENEDIS des projets est retiré ; son ancienne URL refuse aussi l’accès. Les événements historiques restent consultables dans l’Agenda natif, selon les droits habituels.
- Les dix extrafields de contrat `jpsun_site_name`, `jpsun_installed_power_kwc`, `jpsun_pv_module_product`, `jpsun_pv_module_qty`, `jpsun_inverter_product`, `jpsun_inverter_qty`, `jpsun_dc_boxes_qty`, `jpsun_ac_boxes_qty`, `jpsun_access_code` et `jpsun_pdl_number` ne sont plus créés ni mis à jour par JPSUN. Les définitions et valeurs existantes sont conservées pour les anciens modèles ; cette version ne les masque ni ne les supprime automatiquement.
- La récurrence, le jour de paiement, la distance, l’indice de valorisation et les trois hauteurs d’installation restent gérés par JPSUN. Les autres extrafields et types de contacts ne sont pas concernés par cette première étape.
- Après mise à jour, actualiser les déclarations persistées du module par le parcours natif afin de retirer l’onglet. Aucune purge ni migration des données historiques n’est prévue.

Vérification reproductible : `python test/legacysetup.py <racine htdocs Dolibarr>`. Les tests exécutent le descripteur avec des services d’installation simulés, le SQL du dictionnaire sur SQLite et l’ancienne URL dans les deux dispositions d’installation. Ils couvrent les entités 1 et 2, les activations répétées et les réglages personnalisés conservés ; ils ne remplacent pas une désactivation/réactivation sur une instance réelle avec MySQL/MariaDB.

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
