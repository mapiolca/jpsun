# Avancement pondéré des situations — JPSUN 1.26.0

Référence : PR Dolibarr #43005 ouverte, lue au SHA `1f2010eef91651cfeba501074affad06fc72bb3f` le 2026-11-13. Rétroportage explicitement demandé, sans modification du core. La branche propose JPSUN 1.26.0 avec une entrée de changelog explicitement en préparation ; aucune release 1.26.0 n’est publiée. La release stable 1.25.1 et son tag ont été vérifiés sur `03f8de703e0bb7ac320328bdb22a04b1b0383d42` avant la montée de version.

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
| 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` | 38 contrôles PHP et 24 scénarios DOM réussis |
| 21.0.0 | `fd970b582a4d8c5779a2958a4e9f4fce225cf085` | Idem |
| 22.0.0 | `49b9a6d19f3deb6d410c0e9b3310e95be4ea7710` | Idem |
| 23.0.0 | `57a1f05d490a7a80944a8232e9c613e6556d2704` | Idem |
| 24.0.0 | `769c7db907099643558e77d7002c109cfda919e5` | Idem |
| 25.0.0-alpha | `0ac96524bf68b063a44d6af8137e05bf11c227c8` | Idem ; préversion uniquement |

Couverture : pondération HT, quantités, remises, avancement nul, valeurs négatives, ligne non commerciale, facture vide, base nette nulle, exemple 86,66666667, deux modes de stockage ; droits absents, utilisateur externe d'un autre client, propriétaire/client/cycle incohérent, module désactivé et autre contexte ; Banque actif/inactif, situations précédente/courante/suivante, conservation des totaux, double initialisation, chargement initial différé, lien inconnu/dupliqué/externe, colonne existante et structure altérée. Le runner vérifie séparément la détection d'une méthode core présente et le seuil v26 avec une API simulée.

PHPStan niveau 5, cible PHP 8.0, configuration locale `.work/cover-phpstan.neon`, bootstrap core et `--memory-limit=1G` : aucune erreur dans la nouvelle classe et aucune nouvelle erreur dans le hook. Le fichier historique `actions_jpsun.class.php` conserve 44 diagnostics préexistants avec cette configuration (dont découverte de la classe de rétrocompatibilité) ; comparaison avec le fichier de HEAD, sans ignore ni baseline ajouté. Le lint et `git diff --check` sont également exécutés. La CI existante reçoit le runner sur sa matrice PHP 8.0–8.4 / Dolibarr 20–25 ; elle n'a pas été exécutée sur GitHub pour cet état local.

## Correction du premier échec CI

La première CI de la PR #78 sur `53579f066f9df1346069f086a71f734370ad0fcd` échoue dans les six jobs Dolibarr sur l'assertion de version de `test/legacysetup.py`, restée à 1.25.1. Cinq scénarios d'activation s'arrêtent sur cette assertion ; les étapes suivantes sont ignorées. Le job Composer réussit. L'échec a été reproduit localement avec le même résultat avant correction. L'assertion attend désormais 1.26.0 ; les contrôles de conservation des données, réglages et définitions sont conservés. Cette correction touche uniquement le test, sans changement du code métier ni des contrôles CI.

## Limites et recette à effectuer

Pas de déploiement, d'activation, d'écriture métier, d'essai MySQL/MariaDB ou Multicompany réel. Pas de recette visuelle dans le navigateur intégré ni preuve du commit servi. Pas de ZIP préparé, validé ou installé. Les domaines Agenda, Notifications, documents et migrations métier ne sont pas modifiés.

Après déploiement autorisé : confirmer le commit servi, puis vérifier les situations précédente/courante/suivante, les deux modes, Banque actif/inactif, le thème et l'aide native, impression navigateur, JavaScript désactivé, droits de lecture et refus, utilisateur externe et partage entre deux entités. Vérifier que les paramètres persistés du module sont conservés après mise à jour et que le core corrigé reprend seul l'affichage.
