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

- Version : 1.25.1
- PHP : 8.0+
- Compatibilité : Dolibarr 20+

## Liens

- Support & Assistance : [Forum dolibarr.fr](https://dolibarr.fr) / Par mail à p.ardoin@jpsun.fr
- Documentation : [Wiki JPSUN](https://wiki.dolibarr.org/index.php/)

## Fonctionnalités

- Ajout de fonctionnalités diverses pour JPSUN.
- Modèle projet « Affiche stock chantier » (`jpsun_projectstock`) : affiche A4 paysage jaune/noir basée sur la référence Word, avec le nom du tiers lié, le titre du projet et sa référence native. Activation dans les réglages natifs **Projets > Modèles de document**, puis génération dans les fichiers joints du projet. Aucun extrafield ajouté ; les données absentes laissent leur zone vide. Les pictogrammes sont redessinés en vectoriel pour éviter les glyphes emoji manquants sur le serveur.
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

Le contrôle est entièrement traduit en français et en anglais : ses libellés couvrent les menus, colonnes, filtres, statuts, aides, totaux et conditions de compatibilité. Le catalogue du module est chargé par le mécanisme natif Dolibarr, sans dépendre de traductions françaises ajoutées au core.

### Retrait des installations historiques — version 1.24.1

- Les 17 types d’événements ENEDIS `JPSUN_DDR_*` et leurs surcharges de traduction ne sont plus supprimés ni recréés lors de l’activation de JPSUN. Leurs données et réglages existants restent en place ; ProceduresPV porte le suivi des démarches de raccordement.
- L’onglet ENEDIS des projets est retiré ; son ancienne URL refuse aussi l’accès. Les événements historiques restent consultables dans l’Agenda natif, selon les droits habituels.
- Les dix extrafields de contrat `jpsun_site_name`, `jpsun_installed_power_kwc`, `jpsun_pv_module_product`, `jpsun_pv_module_qty`, `jpsun_inverter_product`, `jpsun_inverter_qty`, `jpsun_dc_boxes_qty`, `jpsun_ac_boxes_qty`, `jpsun_access_code` et `jpsun_pdl_number` ne sont plus créés ni mis à jour par JPSUN.
- Les six extrafields projet `jpsun_project_pc`, `jpsun_project_n_ddr`, `jpsun_project_t0`, `jpsun_project_prix_rachat`, `jpsun_project_type_racc`, `jpsun_project_mes_date`, ainsi que l’extrafield produit `jpsun_marque`, ne sont plus créés par JPSUN.
- Le script interne [migrate_1_24_1.php](scripts/migrate_1_24_1.php), appelé lors de l’activation, copie `jpsun_marque` vers `product_photovoltaic_brand` (libellé `ProductPhotovoltaicBrand`) uniquement lorsque la cible est NULL, vide ou composée d’espaces et que la source est renseignée. Il traite exclusivement les produits appartenant à l’entité courante, sans écraser une marque cible existante ni modifier la source.
- Ce script masque les 17 champs historiques concernés : activation, visibilité sur les fiches/listes et impression désactivées. Les définitions et valeurs restent conservées. Les définitions globales (entité 0) et celles de l’entité courante sont traitées ; les définitions propres aux autres entités restent inchangées.
- La migration s’exécute uniquement sous **JPSUN 1.24.1**. Un marqueur `JPSUN_MIGRATION_1241_DONE` par entité empêche son rejeu après succès et reste conservé après désactivation. Une marque trop longue pour la cible (128 caractères par défaut dans PowerPlantPV), une cible absente ou incompatible laisse les données conservées et masquées, avec un avertissement et sans marqueur de succès. Corriger le prérequis et réactiver JPSUN **en restant en 1.24.1** pour terminer ; aucune valeur n’est tronquée.
- La copie, le masquage et le marqueur de succès sont transactionnels. La récurrence, le jour de paiement, la distance, l’indice de valorisation et les trois hauteurs d’installation restent gérés par JPSUN. Les autres champs produit/fournisseur et les types de contacts ne sont pas concernés.
- Après mise à jour, actualiser les déclarations persistées du module par le parcours natif afin de retirer l’onglet et d’exécuter la migration dans chaque entité concernée. Aucune purge des données historiques n’est prévue.

Vérifications reproductibles : `python test/legacysetup.py <racine htdocs Dolibarr>` et `python test/migration1241.py <racine htdocs Dolibarr>`. Les tests exécutent le descripteur et les helpers de constantes natifs avec des services d’installation et transactions simulés, le SQL sur SQLite et l’ancienne URL dans les deux dispositions d’installation. Ils couvrent les entités 1 et 2, les réactivations, les cibles vides/renseignées, les marques trop longues, le seuil exact 1.24.1, le masquage et les rollbacks après erreur. Ils ne remplacent pas une désactivation/réactivation sur une instance réelle avec MySQL/MariaDB.

### Version 1.24.2

- JPSUN ne crée et ne met plus à jour l’extrafield produit `jpsun_module_pv_pc`, couvert par PowerPlantPV. Les définitions, paramètres personnalisés et valeurs existants restent conservés ; aucune colonne n’est supprimée.
- JPSUN ne supprime et ne recrée plus le type de contact projet externe `PROJECTADD`, fourni par `lmdbjobsite`. Les identifiants, libellés, états, positions et liaisons existants restent inchangés. Aucun de ces modules n’est activé automatiquement.
- Le contrôle des acomptes est disponible sous **Comptabilité simple → Rapports → Contrôle des acomptes clients**, avec le droit natif `compta → resultat → lire`. L’accès par URL directe et le service appliquent le même contrôle ; un droit de comptabilité en partie double ne remplace pas ce droit lorsque seul le module simple est actif.
- Le rapport reste en lecture seule : date d’arrêté, filtres, pagination, colonnes, montants et liens vers les pièces accessibles. Aucune écriture, régularisation, affectation de compte ou opération réservée à la comptabilité en partie double n’est ajoutée. En Comptabilité simple, la date proposée est la date courante, sans consultation de la table des exercices de la partie double.
- Toutes les colonnes du rapport sont filtrables : références et tiers, périodes inclusives pour la date de facture et du dernier paiement, comparaisons natives sur les six montants (par exemple `>=100`), références des factures utilisées et commandes liées, et statut affiché incluant les incohérences. Les filtres sont combinés en SQL avant comptage et pagination, conservés lors de la navigation et remis à zéro par le bouton natif. Les filtres de commandes restent soumis au droit de lecture des commandes.
- La prise en charge existante de la comptabilité en partie double, ses droits, les traductions françaises/anglaises et la désactivation dès Dolibarr 26 (préversions incluses) restent conservées.
- Cette version n’exécute pas la migration des marques de la 1.24.1. Terminer les migrations en attente **sous 1.24.1 avant de passer en 1.24.2**. Après mise à jour, vérifier le rapport avec un utilisateur disposant du droit natif correspondant.

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

### Modèles de document — 1.25.1

Les noms et descriptions des 19 modèles sont traduits en français, anglais, allemand, espagnol et italien dans les réglages natifs. Les catalogues sont chargés dès la découverte des modèles. Les identifiants techniques, activations et modèles par défaut restent conservés.

Sous Dolibarr 23 et plus, les sélecteurs natifs affichent les noms JPSUN dans la langue courante, même pour les modèles déjà activés, via le hook natif `getListOfModels` dans le contexte `formfile` déjà déclaré. Aucune ligne `document_model` n’est réécrite. Sous Dolibarr 20–22, ce hook n’existe pas : le sélecteur conserve le libellé enregistré à l’activation ; les réglages des modèles restent traduits. Cette limite apparaît dans l’onglet **Compatibilité**.

Contrôle reproductible : `python test/document_model_translations.py /chemin/htdocs 25.0.0-alpha` (remplacer par la version des sources). Les constructeurs et le sélecteur utilisent les classes natives avec un transport SQL simulé ; aucune génération ni écriture métier.

### Couverture des offres — 1.25.0

Activer **JPSUN sans image avec couverture** dans les modèles natifs des propositions commerciales (`Configuration → Modules → Propositions commerciales`). Le modèle **JPSUN sans image** reste disponible. Actualiser JPSUN par le parcours natif pour ajouter les deux extrafields techniques masqués, sans changer les droits ou réglages existants.

La couverture reprend la référence client comme titre, la date et la référence du devis, son tiers et son adresse, les contacts externes actifs de suivi accessibles, puis l’auteur du brouillon ou le valideur du devis validé. Logo et coordonnées proviennent de l’entité propriétaire, sans changement de session. Les couleurs sont réglables par entité dans les paramètres JPSUN, onglet Devis.

**Joindre une Illustration**, dans le bloc natif des fichiers joints, ouvre une modale avec image et légende/crédit ; le lien fonctionne aussi sans JavaScript. JPEG/PNG uniquement, dans la limite native d’upload et de 10 Mo / 24 mégapixels. GD, EXIF et Fileinfo sont requis pour nettoyer les images. Le fichier reconstitué à partir des pixels est stocké sous un nom aléatoire dans `jpsun-cover/` du répertoire documentaire du devis propriétaire. Aucun nom ni chemin fourni par le navigateur n’est conservé. Une suppression retire seulement cette illustration et sa légende ; une modification concurrente est refusée. L’enregistrement ne génère pas de PDF.

L’image conserve sa hauteur et ses proportions : centrage, recadrage des côtés trop larges et bandes bleu clair dans la zone non couverte. Sans image, le cadre reste bleu clair.

Puissance (`powerplantpv_peak_power`, kWc) et stockage (`powerplantpv_storage_capacity`, kWh) sont lus sans recalcul et affichés seulement si positifs. PowerPlantPV **1.6.0** fournit le texte `powerplantpv_consumption_type` (« Type de consommation », également sur commandes et factures) et le comptage des modules classés MODULE. Avec une ancienne version ou sans PowerPlantPV, les informations facultatives absentes sont omises, sans valeur inventée. Une erreur de lecture bloque la génération plutôt que produire une offre trompeuse.

La pièce de référence était une image JPG, sans PDF original : la composition est reproduite mais l’identité exacte des polices ne peut être attestée. Voir `test/README-proposal-cover.md` pour les preuves et limites de validation. Aucun déploiement, fusion ou acceptation Dolistore n’est implicite.
