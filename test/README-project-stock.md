# Affiche stock chantier — JPSUN 1.25.0

Modèle natif `jpsun_projectstock`, activable par entité depuis `/projet/admin/project.php`. Il est découvert grâce à la déclaration `models` existante ; aucun modèle courant ou défaut n’est remplacé. Le fichier `REF-stock.pdf` est généré par le bloc documentaire du projet, dans le répertoire de l’entité propriétaire résolu avec `getMultidirOutput()`. Les permissions de projet, son périmètre natif et l’accès au tiers parent sont contrôlés ; un utilisateur externe est refusé pour cette affiche interne.

Sources : tiers du projet (`socid`) → nom du client, `title` → chantier, `ref` → numéro d’affaire. Le titre et le nom restent vides si absents. Le document est une affiche de réservation, pas un état des quantités de stock : aucune ligne de stock ni valorisation n’est lue. L’affiche ne reprend pas les notes publiques/privées ou les extrafields.

La référence Word fournie est conservée hors dépôt. Ses mesures OOXML sont A4 paysage, marges latérales 20 mm, bandeau noir, texte jaune/blanc, client 115 points, chantier 55 points, référence 28 points. Le fond PNG embarqué est repris à l’identique dans `img/project_stock_background.png` ; les quatre pictogrammes sont remplacés par des dessins vectoriels afin de ne pas dépendre d’une police emoji Windows. Les textes longs réduisent progressivement leur corps dans leur zone réservée, sans page supplémentaire. Aucun pied ou numéro de page n’est ajouté à cette affiche.

Contrôles reproductibles : `php test/project_stock.php /chemin/htdocs /répertoire/temporaire [version]`. Le test utilise les bibliothèques PDF natives : trois affiches d’une page (référence, textes longs, données vides), sortie spécimen dans l’entité 2, refus d’un répertoire propriétaire absent et d’un droit manquant. Acteur et configuration sont simulés ; cela ne prouve pas les règles d’accès d’une instance complète. La matrice CI existante exécute ces contrôles sur les six révisions figées Dolibarr 20–25 alpha.

Validation locale du 2026-11-05 : PHP 8.4.22, sources Dolibarr 25.0.0-alpha `0ac96524bf68b063a44d6af8137e05bf11c227c8`. Test ci-dessus réussi, trois PDF d’une page rendus avec Poppler puis inspectés visuellement (référence, textes longs, données absentes). Syntaxe des quatre fichiers PHP concernés vérifiée. PHPStan niveau 5, cible PHP 8.0, bootstrap natif du core : modèle sans erreur, sans suppression ni baseline ajouté.

## Recette interactive du 2026-11-05

Navigateur intégré, develop Dolibarr 25.0.0-alpha / PHP 8.3.36, JPSUN 1.25.0. Branche servie temporairement par Git Version Control cPanel ; tête finale de code `f3ac823248b1e0531c9f9aa90d5d7ddb0d812ae1` confirmée dans cPanel. PowerPlantPV n’a pas été déployé ou modifié pendant cette recette.

| Scénario | Résultat observé |
|---|---|
| Découverte native | L’ancienne inclusion inconditionnelle de la classe `Deplacement`, absente du core déployé, provoquait une erreur fatale. Chargement et rubrique désormais conditionnels ; page complète des modèles vérifiée après correctif. |
| Activation par entité | Modèle initialement désactivé dans TEST 1 et TEST 2, défaut `baleine`. Activation temporaire indépendante, choix disponible dans le sélecteur natif. |
| Projet avec tiers | Projet fictif 28, `PJ2611-0012`, client TEST 1 : génération depuis la fiche et téléchargement réussis, PDF d’une page A4 paysage inspecté avec accents et titre sur deux lignes. |
| Projet sans tiers, titre long | Même brouillon fictif modifié : zone client vide, titre accentué long sur trois lignes, référence conservée, une page sans chevauchement. Régénération réussie. |
| Langue anglaise | Sélection native `en_US`, génération et téléchargement ; titres « SITE STOCK », « RESERVED MATERIAL — DO NOT TAKE », « Customer », « PROJECT NUMBER » vérifiés dans le rendu. |
| Onglet documentaire | Même fichier retrouvé dans l’onglet Fichiers joints, téléchargement natif disponible. |
| Aperçu natif | Modale ouverte, mais le lecteur PDF reste noir dans le navigateur intégré, y compris sur le spécimen. Cela ne valide pas l’affichage du lecteur ; fichiers téléchargés et rendus avec Poppler pour le contrôle visuel. |
| CSRF | Avant correction, `builddoc` en GET sans jeton générait le document avec le niveau CSRF du core installé. Contrôle explicite par `currentToken()` ajouté au modèle pour `builddoc` et `specimen`. Après déploiement, même requête refusée avec message de jeton expiré ; bouton natif avec jeton accepté. Aucun réglage de sécurité ni fichier core modifié. |
| Spécimen TEST 2 | Nom aligné sur la redirection native `SPECIMEN.pdf` ; clic sur l’aperçu, redirection et téléchargement réussis, une page A4 paysage. |
| Projet TEST 2 | Nouveau brouillon fictif 29, `PJ2611-0001`, sans tiers : génération et téléchargement réussis avec bandeau TEST 2 et lien documentaire `entity=2`, rendu inspecté. Cela ne prouve pas la génération d’un projet partagé depuis une autre entité. |
| Compte `utilisateur.test` | Projets signalés non autorisés ; fiche 28, téléchargement direct, réglages et génération directe avec jeton valide refusés. Aucun changement de droits. |
| Compte `3.utilisateur` | Fiche 28, génération et téléchargement autorisés ; réglages administrateur refusés. Ce compte dispose de droits de modification : il ne représente pas un profil en lecture seule. |

Les PDF des scénarios avec tiers, titre long et anglais ont été produits avant le dernier correctif de nom de spécimen, qui ne change pas leur rendu. Le spécimen TEST 2, le projet 29 et les essais des deux comptes ont été réalisés sur `f3ac823`. Les six couples CI et le build passent sur cette tête. Le test CLI charge aussi le modèle ENEDIS pour couvrir la régression de découverte.

Données fictives conservées : projets 28 et 29 en brouillon et leurs PDF ; événements Agenda natifs de création 3032 et 3033. Aucun email, validation de projet ou appel financier exécuté. Le module de zonage a signalé `NoGeocoderResult` puis `ObjectAddressNotFound` sur le projet fictif 28 ; création et édition ont néanmoins persisté, vérifiées sur la fiche et le PDF.

Restitution achevée le 2026-11-06 après reconnexion administrateur : modèle temporaire désactivé dans TEST 1, défaut `baleine` conservé, comme dans TEST 2 déjà restauré le 2026-11-05. Contexte TEST 1 rétabli. Retour par Git Version Control cPanel à la branche initiale `fix/jpsun-1.24.2-simple-accounting` / `850bd90f4b9263e637042f09a8f44dd60be3332b` ; SHA confirmé dans cPanel et version servie 1.24.2 confirmée dans À propos. Aucune donnée fictive supprimée, aucun droit modifié. Les correctifs 1.25.0 restent proposés dans la PR, sans fusion ni release.

Limites : LibreOffice embarqué absent, donc comparaison fondée sur les propriétés OOXML et le fond du Word, puis les PNG des PDF. Profil strictement en lecture seule/externe, projet partagé entre entités, refus d’un tiers hors périmètre et répertoire propriétaire indisponible non exécutés sur l’instance ; les refus simulés du test CLI ne les remplacent pas. Suppression documentaire non exécutée. Aucun ZIP installable ou validation Dolistore revendiqué.
