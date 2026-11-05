# Validation de la couverture des propositions

État de développement de JPSUN 1.25.0, 2026-11-05. La référence fournie est un JPG, sans PDF original.

## Contrôles reproductibles

- `php test/proposal_cover.php /chemin/dolibarr/htdocs /répertoire/temporaire [version]` : répertoire propriétaire d’un devis partagé, refus des chemins et configurations absentes, activation du modèle, concurrence de légende, erreurs transactionnelles simulées, JPEG/PNG reconstitués et huit orientations EXIF avec vérification des pixels et retrait des métadonnées. Requiert GD, EXIF, Fileinfo et mbstring.
- Le même test génère une couverture A4 et les deux documents complets avec 60 lignes : modèle standard puis modèle avec couverture, en utilisant les classes et les helpers TCPDF natifs. Les objets métier sont des spécimens et les réponses de base sont simulées ; aucune instance ni données réelles ne sont modifiées. La base native de ce test est identifiée dans la matrice CI.
- Exécution locale : core Dolibarr `0ac96524bf68b063a44d6af8137e05bf11c227c8` (25 alpha), PHP 8.4. Les deux générations produisent respectivement 4 et 5 pages. Après extraction PDF, les textes des quatre pages commerciales sont identiques, sauf leur pagination native. La couverture est sans numéro visible ; les pages commerciales comptent la couverture dans la pagination totale.
- Rendu PNG de la couverture contrôlé avec Poppler : proportions, bandes latérales, accents, titres et pied de page. Les mesures positives sont visibles ; le stockage à zéro est absent et le nombre entier de modules n’a pas de décimales.
- Syntaxe PHP ; PHPStan niveau 5 / cible PHP 8.0 sur les nouveaux composants, avec bootstrap du core et sans ignore ni baseline ajouté.
- CI : réutilisation de la matrice existante des six révisions figées Dolibarr 20 à 25 alpha. Un job configuré n’est pas encore un résultat exécuté ; consulter le SHA de la PR.

## Vérifications réelles restant nécessaires

Recette dans le navigateur intégré après déploiement explicite du SHA de la PR : activation native du modèle, modale et lien sans JavaScript, envoi/remplacement/retrait, double ouverture, conservation de l’ancienne image après échec, champs longs, contacts multiples et privés, auteur/valideur, couleurs par entité et conservation après réactivation. Vérifier les refus sans token, sans droits et hors périmètre commercial, puis le même parcours avec deux entités et un devis partagé.

Les tests ne prouvent pas une transaction MySQL/MariaDB réelle, un partage Multicompany installé, ni l’envoi HTTP des fichiers et la protection CSRF de l’instance. Aucun déploiement distant, ZIP installable ou acceptation Dolistore n’est revendiqué. Les règles de packaging officielles ont été consultées le 2026-11-05 (wiki oldid 65313). Les points d’entrée ajoutés essaient les deux dispositions d’installation et les tests sont réservés à la CLI.

## PowerPlantPV

La PR 1.6.0 fournit `powerplantpv_consumption_type` sur devis, commandes et factures. Le même nom technique permet la reprise native lors des conversions ; il n’ajoute pas de synchronisation rétroactive entre documents existants. Les choix présents ne sont pas modifiés. `powerplantpvCountProposalModules` effectue une lecture SQL seule, selon la classification MODULE et les contrôles natifs d’accès.
