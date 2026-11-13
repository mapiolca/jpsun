# Avancement pondéré des situations — JPSUN 1.26.0

Référence : PR Dolibarr #43005 ouverte, lue au SHA `1f2010eef91651cfeba501074affad06fc72bb3f` le 2026-11-13. Rétroportage explicitement demandé, sans modification du core. Le changelog de JPSUN 1.26.0 est finalisé au 13 novembre 2026 pour la fusion de la PR #78 ; aucune release GitHub 1.26.0 n’est publiée par cette opération. La release stable 1.25.1 et son tag ont été vérifiés sur `03f8de703e0bb7ac320328bdb22a04b1b0383d42` avant la montée de version.

## Contrat

- Calcul PHP identique à la PR, avec `calcul_price_total()` et formatage `price()` suivant `MAIN_MAX_DECIMALS_TOT`. Aucune précision financière codée en dur.
- Le hook natif `addMoreActionsButtons`, contexte `invoicecard`, s'exécute après le contrôle natif `restrictedArea()`. Droit direct `$user->hasRight('facture', 'lire')`, entité accessible via `getEntity('invoice')`, client externe contrôlé. Toutes les factures doivent avoir le même client, propriétaire et cycle que la fiche autorisée. Réutilisation des objets et lignes déjà chargés ; aucune requête supplémentaire ni effet métier.
- JavaScript limité à `table.situationstable`. Validation complète avant mutation : un seul tableau, en-têtes attendus, six ou sept colonnes, cellules vides attendues, identifiants de facture uniques issus des liens natifs, correspondance complète avec le payload et totaux à colspan natif. Les pourcentages sont insérés comme texte ; seule l'aide provient du HTML natif `Form::textwithpicto()`.
- Réinitialisation idempotente ; aucune substitution du tableau, action Ajax, gestionnaire de clic ou CSS ajouté. Les totaux n'obtiennent aucun pourcentage agrégé artificiel.
- Indisponible hors Dolibarr 20–25, module Factures/JPSUN désactivé ou méthode native `Facture::getSituationProgress()` présente. Cette dernière détection évite de doubler le correctif après sa livraison dans le core. Une structure différente s'abstient même sans cette méthode.
- Actualiser les hooks persistés par l'administration native après déploiement. Ne pas réactiver automatiquement le module ni purger ses constantes.

## Vérifications exécutées le 2026-11-13

État local basé sur JPSUN `03f8de703e0bb7ac320328bdb22a04b1b0383d42`, branche de proposition 1.26.0. PHP CLI 8.4.22 sur Windows. Les fixtures exécutent le calcul et les classes natives, puis extraient et exécutent uniquement le bloc de tableau de la fiche native. Les accès DB, liens et paiements sont simulés. Le DOM est simulé sous Node ; ce n'est pas une recette dans un navigateur.

Commande reproductible, avec un checkout core complet et PHP/Node dans PATH :

```text
python test/situation_progress.py <racine htdocs> <version>
```

| Dolibarr | Révision des sources | Résultat local |
|---|---|---|
| 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` | 40 contrôles PHP et 24 scénarios DOM réussis |
| 21.0.0 | `fd970b582a4d8c5779a2958a4e9f4fce225cf085` | Idem |
| 22.0.0 | `49b9a6d19f3deb6d410c0e9b3310e95be4ea7710` | Idem |
| 23.0.0 | `57a1f05d490a7a80944a8232e9c613e6556d2704` | Idem |
| 24.0.0 | `769c7db907099643558e77d7002c109cfda919e5` | Idem |
| 25.0.0-alpha | `0ac96524bf68b063a44d6af8137e05bf11c227c8` | Idem ; préversion uniquement |

Couverture : pondération HT, quantités, remises, avancement nul, valeurs négatives, ligne non commerciale, facture vide, base nette nulle, exemple 86,66666667, deux modes de stockage ; droits absents, utilisateur externe d'un autre client, propriétaire/client/cycle incohérent, module désactivé et autre contexte ; Banque actif/inactif, situations précédente/courante/suivante, conservation des totaux, double initialisation, chargement initial différé, lien inconnu/dupliqué/externe, colonne existante et structure altérée. Le runner vérifie séparément la détection d'une méthode core présente et le seuil v26 avec une API simulée.

PHPStan niveau 5, cible PHP 8.0, configuration locale `.work/cover-phpstan.neon`, bootstrap core et `--memory-limit=1G` : aucune erreur dans la nouvelle classe et aucune nouvelle erreur dans le hook. Le fichier historique `actions_jpsun.class.php` conserve 44 diagnostics préexistants avec cette configuration (dont découverte de la classe de rétrocompatibilité) ; comparaison avec le fichier de HEAD, sans ignore ni baseline ajouté. Le lint et `git diff --check` sont également exécutés. La matrice GitHub PHP 8.0–8.4 / Dolibarr 20–25 et Composer réussissent sur le correctif fonctionnel `59f58409f30b114920aac942b1ab1662e43654d0` (runs `43429957248` et `43429957253`). La finalisation documentaire ultérieure doit aussi avoir sa propre CI verte avant fusion.

## Correction du premier échec CI

La première CI de la PR #78 sur `53579f066f9df1346069f086a71f734370ad0fcd` échoue dans les six jobs Dolibarr sur l'assertion de version de `test/legacysetup.py`, restée à 1.25.1. Cinq scénarios d'activation s'arrêtent sur cette assertion ; les étapes suivantes sont ignorées. Le job Composer réussit. L'échec a été reproduit localement avec le même résultat avant correction. L'assertion attend désormais 1.26.0 ; les contrôles de conservation des données, réglages et définitions sont conservés. Cette correction touche uniquement le test, sans changement du code métier ni des contrôles CI.

## Correction révélée par la recette réelle

Sur le core antérieur à la PR, `addMoreActionsButtons` ne restitue pas `HookManager::resPrint` dans la fiche facture. Le hook émet désormais directement ses balises script, conformément à ce point d’appel natif, en conservant le retour neutre. Un nouveau scénario avec le véritable `HookManager` reproduit l’absence de sortie avant correction puis vérifie la sortie et la remise à zéro après correction sur les sources v20 à v25. Les tests finaux comportent 40 contrôles PHP par version.

## Recette réelle du 13 novembre 2026

Navigateur intégré Codex, instance `https://develop.lesmetiersdubatiment.fr`, entité TEST 1, compte Codex, Dolibarr 25.0.0-alpha, PHP 8.3.36 et Multicompany actif (version non relevée). Déploiement du module par l'interface native cPanel Git Version Control, SHA fonctionnel `59f58409f30b114920aac942b1ab1662e43654d0` confirmé. Version 1.26.0 confirmée dans À propos. Actualisation des hooks JPSUN par l'action native de rechargement, sans désactiver les autres modules.

Le core initial sert `fix/situation-weighted-progress` au SHA `1f2010eef91651cfeba501074affad06fc72bb3f`, qui contient déjà le correctif natif : le module s'abstient effectivement (aucun script de rétroportage, une seule colonne native, disponibilité indiquée comme inactive). Pour tester le module, une branche de recette `fix/jpsun-78-recette-base` a été créée dans le fork core au parent exact `4fa75eae2421fcf9dd5a017ff316a4afab333794`, sans modification de fichiers core. Le changement temporaire de branche s'effectue uniquement dans cPanel Git Version Control. Cette branche de référence reste disponible ; aucun tag ou historique n'est réécrit.

Sur ce core antérieur, l'affichage initial vide a permis de reproduire le défaut de sortie du hook décrit ci-dessus. Après correction et actualisation native du dépôt JPSUN :

- Facture 110 / FA2026-030 : historique S1 à 13,33 %, S2 à 0,00 % et S3 à 86,67 %. Valeur témoin calculée sur les lignes affichées : `(2000 × 85 + 1000 × 90) / 3000 = 86,66666667`. Totaux inchangés : 3 000,00 HT et 3 600,00 TTC.
- Facture 5 / FA2510-0004 : situation précédente, courante et suivante affichées avec les mêmes pourcentages ; sept colonnes effectives dans chaque ligne, y compris les deux totaux et leurs colspans. L'aide native présente le texte français complet et accentué.
- Vue native d'impression de la facture 5 : mêmes valeurs, sept colonnes effectives et absence de duplication.
- Facture 50 / (PROV50), sans lignes : tiret pour la base nulle, totaux HT/TTC à zéro et rendu natif conservé.
- Rechargements de page : une seule colonne, aucun doublon. Aucun message d'erreur JavaScript observé sur la fiche témoin. Banque reste actif pendant la recette ; son état inactif est couvert par les tests de tableau, pas par une désactivation sur l'instance.

Le core a ensuite été restauré exactement sur `fix/situation-weighted-progress` / `1f2010eef91651cfeba501074affad06fc72bb3f` et vérifié dans cPanel : le script JPSUN n'est plus émis, la colonne native reste unique et les valeurs sont identiques. Aucun objet métier, ligne, facture, règlement, document ou email n'a été créé, modifié, validé ou envoyé pour cette recette. Le rechargement du module constitue une mise à jour de ses déclarations persistées ; l'identité exhaustive de toutes les constantes avant/après n'a pas été mesurée. Captures conservées localement hors du diff dans `.work/recette-1260/`.

## Limites restantes

La recette réelle ne couvre pas les droits alternatifs, les utilisateurs externes, les partages entre deux entités, Banque désactivé, JavaScript désactivé ou le changement du mode global des situations ; les contrôles correspondants de CI restent des simulations ciblées. Aucun essai d'installation ZIP ni validation Dolistore. Les domaines Agenda, Notifications, documents et migrations métier ne sont pas modifiés. Les tests d'autorisation n'équivalent pas à une recette exhaustive de tout le module.
