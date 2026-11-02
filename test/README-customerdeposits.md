# Contrôle des acomptes clients — proposition de développement

État vérifié le 2026-11-02. Cette proposition ne change pas la version publiée `1.23.0` et n’ajoute pas d’entrée de release au changelog.

## Source et intégration

Rétroportage explicitement demandé de [Dolibarr/dolibarr#42407](https://github.com/Dolibarr/dolibarr/pull/42407), tête examinée `d2a7333fb5b79360913fcc018ab746b69f7f9d0f` : classe de rapport, page, libellés anglais et calculs TTC. Le module utilise une classe préfixée JPSUN et ses propres traductions françaises et anglaises. Aucun fichier core n’est remplacé.

Le hook `menuLeftMenuItems`, disponible dans les sources v20 à v25 examinées, insère les deux entrées sous le menu comptable existant. Il utilise la classe native `Menu`, préserve les entrées existantes et s’abstient si le contrôle est déjà présent. Le contexte `leftblock` est déclaré uniquement avant v26. Le gestionnaire cumule les contextes : le hook doit aussi fonctionner lorsque `toprightmenu` est initialisé avant `leftblock`. Les droits sont contrôlés directement avec `hasRight()`, sans élévation administrateur, dans le hook, la page et le service. Le service détermine lui-même les restrictions commerciales et la permission de lecture des commandes à partir de l’acteur reçu.

`JpsunCustomerDepositCompatibility::isAvailable()` centralise versions et dépendances. `version_compare(DOL_VERSION, '26.0', '<')` exclut aussi `26.0.0-alpha`, contrairement à une comparaison au seuil `26.0.0`. L’onglet Compatibilité affiche le même prédicat avec un badge natif. Le rapport reste en lecture seule ; aucun trigger, Agenda, notification, document, cron, modèle de numérotation ou schéma SQL n’est ajouté.

## Commandes et couverture

Avec un arbre Dolibarr complet accessible localement :

```text
python test/customerdeposits.py <Dolibarr htdocs>
php test/customerdeposits-phpstan.php <Dolibarr htdocs>
php <phpstan.phar> analyse -c test/.runtime/phpstan-deposits.neon --no-progress --memory-limit=1G
```

Les tests exécutent les SELECT de production dans une base SQLite en mémoire par un transport PHP/Python. Les classes et fonctions natives sont chargées depuis les sources indiquées, mais l’acteur, les réponses de base et le contexte d’installation sont simulés. Les fonctions MySQL `GREATEST` et `LEAST` sont adaptées pour SQLite. Ce contrôle ne remplace pas une recette MySQL/MariaDB ou Multicompany réelle.

Les 16 tests couvrent :

- acomptes non payés exclus, encaissements partiels et futurs, utilisations datées, validations tardives et brouillons ;
- acomptes entièrement utilisés conservés dans Tous et dans le filtre sans reliquat, remboursements et avoirs séparés, incohérences signalées ;
- isolation des entités et restrictions commerciales avant calcul des compteurs et pagination, liaisons étrangères exclues ;
- refus sans requête pour droit absent, administrateur sans droit, utilisateur externe, dépendance absente et v26/v27 ; commandes omises sans droit ou sans module ;
- filtres, quotes, tri, pagination, doublons de commerciaux et de liaisons, échecs des quatre requêtes sans résultat partiel, précision monétaire native ;
- découverte du descripteur, conservation de l’ID historique `999000`, retrait de `leftblock` en v26, cycle réel du HookManager avec plusieurs contextes et appels successifs ;
- résolution native des deux chemins d’image et identité binaire de `jpsun.png` avec l’original `object_jpsun.png`.

Exécutions locales : 16 tests réussis sur chacun des six arbres ci-dessous, tous sous **PHP 8.4.22**, soit 96 tests. Les schémas SQL touchés et les signatures natives utilisées ont également été lus dans ces révisions.

| Sources | Commit | Résultat local |
|---|---|---|
| Dolibarr 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` | 16 réussis |
| Dolibarr 21.0.0 | `fd970b582a4d8c5779a2958a4e9f4fce225cf085` | 16 réussis |
| Dolibarr 22.0.0 | `49b9a6d19f3deb6d410c0e9b3310e95be4ea7710` | 16 réussis |
| Dolibarr 23.0.0 | `57a1f05d490a7a80944a8232e9c613e6556d2704` | 16 réussis |
| Dolibarr 24.0.0 | `769c7db907099643558e77d7002c109cfda919e5` | 16 réussis |
| Dolibarr 25.0.0-alpha | `0ac96524bf68b063a44d6af8137e05bf11c227c8` | 16 réussis ; préversion |

La CI ajoutée fixe ces mêmes commits et prévoit respectivement PHP 8.0, 8.1, 8.2, 8.2, 8.3 et 8.4. Sa déclaration n’est pas une preuve d’exécution : consulter les résultats du SHA de la PR.

PHPStan : niveau 5, `phpVersion: 80000`, sources natives de la révision v25 alpha ci-dessus, aucune suppression ni baseline ajouté. La page, le service, le prédicat et les réglages sont analysés par la configuration générée. La classe de hooks historique fait en complément l’objet d’une comparaison des diagnostics avant/après ; les erreurs préexistantes ne sont pas présentées comme une analyse entièrement verte du module.

L’inclusion de l’environnement a été vérifiée séparément en copiant la page réelle sous `htdocs/jpsun` puis `htdocs/custom/jpsun` avec un `main.inc.php` simulé ; les deux dispositions atteignent ce bootstrap. En son absence, la page s’arrête avec `Include of main fails`. Il ne s’agit pas d’un test de connexion à une instance.

## Action administrative et recette restante

Après déploiement, actualiser la déclaration des hooks par le parcours natif de mise à jour du module ; conserver les réglages, modèles, constantes et partages. Aucune désactivation/réactivation n’a été effectuée par les tests. L’ID historique n’a pas été renuméroté et sa réservation globale n’a pas été réauditée.

À vérifier sur une instance servant ce code : menu après actualisation des hooks, accès direct autorisé/refusé, deux entités Multicompany, comptes internes limités et externe, datepicker, Select2, pagination 20/50/100 avec son formulaire parent, absence de champ caché `limit`, sélection et persistance des colonnes, position des commandes de filtre à gauche/droite, icône dans la liste des modules et les réglages, traductions françaises/anglaises. Aucun navigateur distant, déploiement, test d’installation de ZIP ou soumission Dolistore n’est revendiqué.

Les règles officielles Dolistore ont été consultées le 2026-11-02, révision wiki `65313`. La PR ne livre pas de ZIP. Le rapport est un contrôle des encaissements TTC, pas un calcul de PCA, une preuve de livraison ou un solde comptable. Les modifications, suppressions et déliaisons historiques restent impossibles à reconstruire depuis les seuls documents actuels.
