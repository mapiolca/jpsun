# JPSUN - Notes de versions (ChangeLog)

## 1.16 (12/05/2026)
- Ajout d'un modèle PDF d'étiquettes de classeur pour les projets.
- Génération d'une page A4 unique contenant les quatre formats d'étiquettes côte à côte.
- Ajout des formats 5 x 15,8 cm, 2,5 x 15,8 cm, 2,8 x 18,5 cm et 5,8 x 18,5 cm.
- Affichage du logo société, de la référence projet, du libellé projet et du nom du tiers sur chaque étiquette.

## 1.15 (29/04/2026)
- Ajout des widgets puissance crête (annuel, mensuel, hebdomadaire) et du widget camembert CA par catégorie facture.
- Ajout/mise à jour idempotente de l'extrafield `jpsun_pc_install` sur devis, factures et commandes.
- Améliorations d'affichage des graphs (unités, légendes, hauteurs harmonisées).
- Ajout d'un bouton dans les réglages du module pour forcer le recalcul de la puissance crête (`jpsun_pc_install`) des devis, commandes et factures à partir des lignes de produits de type module photovoltaïque et de l'extrafield produit `jpsun_module_pv_pc`.

## 1.14 (01/04/2026)
- Ajout de la massaction "Modifier la Charge de travail prévue" (Dolibarr `< 24.0`) avec popup de saisie `hh:mm` par tâche, conversion en secondes dans `planned_workload`, contrôles de droits/sécurité et traductions `fr_FR`, `en_US`, `de_DE`, `es_ES`, `it_IT`.

## 1.13 (24/03/2026)
- Ajout d'une action de masse "Clôturer les tâches des projets" sur `/projet/tasks/list.php` et `/projet/tasks.php` (Dolibarr `< 24.0`) qui met les tâches sélectionnées à `progress = 100` et `fk_statut = 3`, avec contrôle des droits (`projet->creer`), périmètre de sécurité (entité + projets autorisés) et traductions `fr_FR`, `en_US`, `de_DE`, `es_ES`, `it_IT`.
- Ajout de trois nouvelles actions de masse sur les tâches projet : "Modifier l'Avancement", "Modifier la Date début" et "Modifier l'Échéance", avec popup natif de pré-action pour saisir les valeurs, prise en charge des contextes `/projet/tasks/list.php` et `/projet/tasks.php` (Dolibarr `< 24.0`), contrôles de droits et filtrage des tâches autorisées.

## 1.12 (23/03/2026)
- Ajout de deux réglages workflow liés à la clôture des projets :
	- `JPSUN_PROJECT_CLOSE_SET_TASK_END_DATE` pour renseigner la date de fin des tâches liées sans date de fin.
	- `JPSUN_PROJECT_CLOSE_FORCE_PROJECT_END_DATE` pour forcer la date de fin du projet avec la date de clôture.
- Ajout du réglage workflow `JPSUN_PROJECT_CLOSE_COMPLETE_TASKS` pour clôturer automatiquement les tâches d'un projet à sa clôture.
- Quand ce réglage est actif, les tâches du projet sont mises à `100%` et au statut `3` (`fk_statut = 3`, Clôturée).
- Ajout du traitement du trigger `PROJECT_CLOSE` pour appliquer ces comportements selon les réglages activés.
- Correction de compatibilité Dolibarr 21+ sur le modèle PDF de synthèse projet (`write_file`) en alignant la signature de méthode avec `ModelePDFProjects`.
- Ajout des traductions manquantes pour :
	- `JPSUN_PROJECT_CLOSE_SET_TASK_END_DATE`
	- `JPSUN_PROJECT_CLOSE_FORCE_PROJECT_END_DATE`
	- `JPSUN_PROJECTSYNTHESIS_SHOW_PROPOSAL`
	- `JPSUN_PROJECTSYNTHESIS_SHOW_ORDER`
	- `JPSUN_PROJECTSYNTHESIS_SHOW_FICHINTER`
	- `JPSUN_PROJECTSYNTHESIS_SHOW_STOCKTRANSFER`

## 1.11 (13/03/2026)
- Ajout de l'extrafield facture "Taux de TVA" (`jpsun_taux_tva`) configuré pour être disponible en liste et masqué par défaut.
- Mise à jour automatique de la valeur de l'extrafield à chaque ajout, modification ou suppression de ligne de facture via les triggers `LINEBILL_INSERT`, `LINEBILL_UPDATE` et `LINEBILL_DELETE`.
- Calcul de la valeur selon les lignes de facture : un taux unique (`20%`, `5.5%`, etc.) ou la valeur "Multiples" quand plusieurs taux de TVA sont présents.
- Ajout des traductions associées (`JpsunTauxTvaFacture`, `JpsunTauxTvaFactureHelp`, `JpsunMultiples`) en `fr_FR`, `en_US`, `de_DE`, `es_ES` et `it_IT`.

## 1.10 (16/02/2026)
- Ajout d'un nouveau modèle de PDF pour la génération de rapport de stock
- Actualisation des modèles de devis pour la nouvelle gestion des CGV dans Dolibarr v22

## 1.9 (03/02/2026)
- Ajout d'un TRIGGER pour actualiser automatiquement la puissance crête totale des devis en fonction de la puissance et de la quantité des modules qu'il contient.

## 1.8 (18/01/2026)

- Création automatique d'un projet validé à la signature d'un devis, avec liens aux objets et copie des extrachamps.
- Ajout d'un réglage pour activer la création automatique de projet et mise à jour du libellé projet avec la référence client.

## 1.7 (11/01/2026)

- Ajout des extrafields contrats JPSUN pour les contrats.
- Ajout du modèle de contrat particulier v3.
- Ajout des traductions manquantes.
- Ajout des types de contact "Adresse du Site" , "Représentant du site 1" et Représentant du site 2".

## 1.6.6

- Ajout des extrafields contrats JPSUN pour les contrats. (09/01/2026)
- Ajout de l'extrafiel "Détail Produit" dans les fiches produit. (17/09/2025)
- L'extrafield "Puissance crête" n'est désormais affiché que lorsque la nature du produit est "2 - Modules Photovoltaïque". (15/09/2025)
- Ajout du support de la constante "TICKET_SHOW_MESSAGES_ON_CARD" dans les réglages.(11/09/2025)
- Ajout du support de la constante "TICKET_ADD_AUTHOR_AS_CONTACT" = 2 dans les réglages.(11/09/2025)
- Correction de la fonction réglage "INVOICE_USE_SITUATION" pour une mise à valeur 2.(11/09/2025)
- Intégration des fonctions MAIN_SEARCH_PRODUCT_BY_FOURN_REF + MAIN_DISABLE_TRUNC  + MAIN_ALL_TO_UPPER dans les réglages + ajout des traductions correspondantes.
- Ajout de la gestion des CGV dans les modèles de pdf des propositions commerciales clients. (01/09/2025)
- Ajout de la gestion de la constante cachée "PRODUIT_PDF_MERGE_PROPAL" dans les réglages du module. (30/08/2025)
- Intégration de la fonction "PDF_SHOW_PROJECT_TITLE" dans les réglages + ajout des traductions correspondantes. (23/07/2025)
- Suppression de la colonne TVA dans le modèle de commande fournisseur jpsun. (23/07/2025)
- AJoute une limite à deux décimale dans les modèles de devis (10/11/2025)
- Ajoute un modèle de contrat (18/11/2025)

## 1.6.5 (10/07/2025)

- Ajout d'un extrafield "Monogramme" pour les utilisateurs et prise en charge dans les modèles de devis JPSUN (16/06/2025)
- Intégration de l'option cachée "MAIN_CAN_EDIT_SUPPLIER_ON_SUPPLIER_ORDER" dans les réglages du module (03/06/2025)
- Modification des permissions pour compatibilité avec ModuleBuidler (23/04/2025)
- Ajout d'un menu à gauche pour afficher le planning Gantt de tous les projets (23/04/2025)

## 1.6.4 (17/04/2025)

- Ajout d'un bouton de création rapide d'évènement de le menu haut (+)

## 1.6.2 (07/04/2025)

- Ajout de nouvaux types d'évènement pour les DDR ENEDIS

## 1.6.0 (20/03/2025)

- Ajout d'un modèle personnalisé pour les devis client
- Ajout d'une page réglage du module pour pouvoir gérer différents paramètres (fonctions cachées natives ou particulières au module)
- Ajout de traductions nécessaires à un emeilleure interprétation du logiciel pour l'activité de JPSUN.

## 1.5.0 (07/03/2025)

- Ajout du calcul du bénéfice prévisionnel dans la vue d'ensemble des projets.

## 1.4.0 (06/03/2025)

- Ajout de plusieurs extrafields pour les projets
- Ajout d'un modèle de numérotation pour les devis client.

## 1.3.0 (05/03/2025)

- Ajout d'un onglet permettant la visualisation de l'état d'une demande de raccordement ENEDIS dans les projets.

## 1.2.1 (26/02/2025)

- Corrections et prise en compte de certaines amélioration du modèle de pdf des commandes fournisseurs par défaut "Cornas" pour le modèle propriétaire "JPSUN".

## 1.2.0 (25/02/2025)

- Ajout d'un Extrafields "Puissance crête" pour les produits.

## 1.1.0 (14/02/2025)

- Ajout d'un Extrafields "Libellé" pour les demande de prix et commandes fournisseurs.
- Réorganisation dans l'ordre des extrafields.
- Corrections d'une erreur de date dans le ChangeLog


## 1.0.0 (31/01/2025)

- Création du Modulde JPSUN.
- Ajout d'un modèle de Commandes fournisseur.
- Ajout d'un modèle de Demande de prix fournisseur.
- Ajout des Extrafields pour les produits / commandes fournisseurs / demande de prix.
