# JPSUN - Notes de versions (ChangeLog)

## 1.20.1 (05/06/2026)
- Ajustement du modèle `Contrat SOLEIL AQUITAIN` : cadre contrat réduit, libellé de forme juridique du prestataire, signatures sur la page Bon pour accord et médiateur issu d'un tiers fournisseur.
- Remplacement des mentions imprimées en dur `SOLEIL AQUITAIN` par le nom de l'entité émettrice dans le modèle de contrat.
- Amélioration de la présentation du médiateur de la consommation dans les conditions générales du modèle `Contrat SOLEIL AQUITAIN`.
- Ajout du nom alternatif du tiers médiateur dans le bloc médiateur du modèle `Contrat SOLEIL AQUITAIN`.
- Calcul des totaux HT, TVA et TTC du modèle `Contrat SOLEIL AQUITAIN` à partir des lignes du contrat.

## 1.20 (03/06/2026)
- Ajout du modèle PDF contrat `Contrat SOLEIL AQUITAIN`, généré nativement par Dolibarr avec annexes centrales PV, formulaire de rétractation, mentions légales configurables et signature en ligne dédiée.
- Ajustement des réglages `Contrat SOLEIL AQUITAIN` : mode de paiement via le dictionnaire Mode de règlements, délai de validité en jours avec repli sur le paramètre natif des devis clients, et restauration des mentions d'assurances RC Pro et décennale.
- Ajout des réglages `Récurrence par défaut` et `Jour de paiement par défaut`, avec extrafields contrat éditables par le commercial et reprise dans le modèle `Contrat SOLEIL AQUITAIN`.
- Remplacement du dictionnaire JPSUN de zones géographiques et de l'extrafield contrat `Zone géographique` par les catégories de contrat fournies par `lmdbzoning` pour le modèle `Contrat SOLEIL AQUITAIN`.
- Intégration optionnelle avec le module Centrale PV / PowerPlantPV.
- Masquage de l'extrafield produit `jpsun_module_pv_pc` lorsque PowerPlantPV est actif, tout en conservant les données historiques.
- Suppression des widgets historiques de puissance crête (kWc), de l'extrafield historique de puissance crête sur devis, commandes et factures, ainsi que du recalcul JPSUN associé.
- Ajout du modèle PDF contrat `JPSUN PRO`, basé sur le contrat PRO V4, avec génération d'une Annexe 1 par centrale PV liée au contrat.
- Récupération des données centrales PV, composants, tiers et contacts via les mécanismes Dolibarr natifs, avec contrôle des droits et filtrage par entité.
- Séparation des réglages JPSUN en onglets admin dédiés.
- Ajout d'annexes PDF configurables par entité, jointes aux devis et contrats JPSUN selon ordre et activation.
- Le contrat `JPSUN PRO` ajoute le spécimen du modèle natif Interventions configuré en annexe PDF.
- Amélioration du rendu des descriptions HTML des lignes et ajout du détail de TVA par taux dans les totaux du contrat `JPSUN PRO`.
- Correction du positionnement des blocs Client / Prestataire en première page du contrat `JPSUN PRO`.
- Correction de la section Signature du contrat `JPSUN PRO` pour placer le contact tiers dans la case Pour le Client.
- Les PDF joints au contrat `JPSUN PRO` sont maintenant introduits comme annexes numérotées après l'Annexe 2.
- Le spécimen d'intervention joint au contrat `JPSUN PRO` force le watermark SPECIMEN et reprend le tiers du contrat.
- La première page du contrat `JPSUN PRO` affiche le signataire du contrat client uniquement si ce contact est déclaré.
- Ajout de l'annexe PDF configurable `Certifications`, placée après la Garantie Décennale dans les réglages et l'ordre par défaut.
- Le contrat `JPSUN PRO` affiche les consignes d'accès des centrales PV liées et masque les extrafields site historiques du contrat lorsque PowerPlantPV est actif.
- La génération du contrat `JPSUN PRO` produit désormais un PDF contrat principal et un PDF séparé pour les annexes.
- La signature en ligne du contrat `JPSUN PRO` est placée dans la case Pour le Client de la section Signatures.

## 1.19 (27/05/2026)
- Ajout du modele PDF fiche produit JPSUN pour les produits du catalogue Dolibarr.
- Generation d'une fiche one-page avec image principale, vignettes secondaires, description, notes, caracteristiques, extrafields imprimables et categories liees.
- Selection de l'image principale alignee sur la regle du modele de devis Cyan/JPSUN.

## 1.18 (25/05/2026)
- Le garde-fou de signature des devis ne propose plus que les projets du tiers du devis dans la selection de projet existant.
- Le reglage de benefice previsionnel projet `JPSUN_PROJECT_SHOW_FORECAST_PROFIT_BOARD` est masque sauf si la constante cachee `SHOW_DEPRECATED_FEATURES` est activee.
- Le delai de livraison Dolibarr `AV_NOW` est traite comme immediat : il vaut 0 jour et cree une plage projet ouvree a partir de la date de signature.
- La date de livraison du devis est prioritaire sur le delai de livraison et les plages projet tiennent compte des heures d'ouverture ou des jours non ouvres Dolibarr lorsque disponibles.
- Mise a jour de la version du module et du README en 1.18.

## 1.17 (15/05/2026)
- Ajout d'un garde-fou a la creation automatique de projet a la signature d'un devis : apres confirmation de la signature, si un projet eligible existe et que le devis n'est pas deja lie a un projet, l'utilisateur choisit entre rattacher le devis a un projet existant ou creer un nouveau projet.
- Le delai de livraison du devis est desormais obligatoire a la signature lorsque la creation automatique de projet est activee, et les dates du projet cree sont calculees autour de la date de livraison estimee.
- Ajout du reglage `JPSUN_AUTOPROJECT_DELIVERY_WINDOW_WORKDAYS` pour definir le nombre de jours ouvres de la plage projet autour de la date de livraison calculee.
- Correction de la suppression des anciens extrafields projet pour ignorer les colonnes deja absentes.

## 1.16.1 (13/05/2026)
- Remplacement des extrafields projet `project_address`, `project_zip` et `project_town` par le contact externe projet `PROJECTADD` (`jpsunWorkSiteAddress`, position 1).
- Les etiquettes de classeur projet `jpsun_projectlabels` utilisent maintenant l'adresse, le code postal et la ville de ce contact.
- Suppression automatique des anciens extrafields projet lors de l'initialisation du module.

## 1.16 (12/05/2026)
- Ajout d'un modèle PDF d'étiquettes de classeur pour les projets.
- Génération d'une page A4 unique contenant les quatre formats d'étiquettes côte à côte.
- Ajout des formats 5 x 15,8 cm, 2,5 x 15,8 cm, 2,8 x 18,5 cm et 5,8 x 18,5 cm.
- Affichage du logo société, de la référence projet, du libellé projet et du nom du tiers sur chaque étiquette.

## 1.15 (29/04/2026)
- Ajout des widgets puissance crête (annuel, mensuel, hebdomadaire) et du widget camembert CA par catégorie facture.
- Ajout/mise à jour idempotente de l'ancien extrafield historique de puissance crête sur devis, factures et commandes.
- Améliorations d'affichage des graphs (unités, légendes, hauteurs harmonisées).
- Ajout d'un bouton dans les réglages du module pour forcer le recalcul de l'ancien extrafield historique de puissance crête des devis, commandes et factures à partir des lignes de produits de type module photovoltaïque et de l'extrafield produit `jpsun_module_pv_pc`.

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
