# JPSUN - Notes de versions (ChangeLog)

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
