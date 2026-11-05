# Validation de la couverture des propositions

État de développement de JPSUN 1.25.0, 2026-11-05. La référence fournie est un JPG, sans PDF original.

## Contrôles reproductibles

- `php test/proposal_cover.php /chemin/dolibarr/htdocs /répertoire/temporaire [version]` : répertoire propriétaire d’un devis partagé, refus des chemins et configurations absentes, activation du modèle, concurrence de légende, erreurs transactionnelles simulées, JPEG/PNG reconstitués et huit orientations EXIF avec vérification des pixels et retrait des métadonnées. Requiert GD, EXIF, Fileinfo et mbstring.
- Le même test génère une couverture A4 et les deux documents complets avec 60 lignes : modèle standard puis modèle avec couverture, en utilisant les classes et les helpers TCPDF natifs. Les objets métier sont des spécimens et les réponses de base sont simulées ; aucune instance ni données réelles ne sont modifiées. La base native de ce test est identifiée dans la matrice CI.
- Exécution locale : core Dolibarr `0ac96524bf68b063a44d6af8137e05bf11c227c8` (25 alpha), PHP 8.4. Les deux générations produisent respectivement 4 et 5 pages. Après extraction PDF, les textes des quatre pages commerciales sont identiques, sauf leur pagination native. La couverture est sans numéro visible ; les pages commerciales comptent la couverture dans la pagination totale.
- Rendu PNG de la couverture contrôlé avec Poppler : proportions, bandes latérales, accents, titres et pied de page. Les mesures positives sont visibles ; le stockage à zéro est absent et le nombre entier de modules n’a pas de décimales.
- Syntaxe PHP ; PHPStan niveau 5 / cible PHP 8.0 sur les nouveaux composants, avec bootstrap du core et sans ignore ni baseline ajouté.
- CI : réutilisation de la matrice existante des six révisions figées Dolibarr 20 à 25 alpha. Un job configuré n’est pas encore un résultat exécuté ; consulter le SHA de la PR.

## Recette dans le navigateur intégré — 2026-11-05

Develop, Dolibarr 25.0.0-alpha / PHP 8.3.36, compte Codex, entité TEST 1. Checkout via Git Version Control cPanel : JPSUN `8cae584480fb7ce2bdc6a2a631507ec0d60d1ce8`, PowerPlantPV `7f55869d6faf2143592d19adfad82f4917772c53`. Versions servies et nouveaux contrôles vérifiés dans les écrans natifs.

- Activation du modèle sans changer le défaut `lmdbpropalpv_withpictures`, rechargement natif des deux modules, conservation du modèle actif et d’une couleur personnalisée ; couleur initiale rétablie ensuite.
- Devis fictif 53 : référence client longue et accentuée, type de consommation enregistré, couverture sans illustration et mentions techniques nulles omises. Les deux champs techniques étaient exposés ; correction de leur visibilité publiée, migration rejouable puis absence des champs confirmée sur l’instance.
- Modale ouverte plusieurs fois, aperçu, crédit accentué, envoi PNG, remplacement JPEG orienté, retrait et page de repli après erreur. Enregistrement sans génération automatique ; génération explicite réussie. JPEG falsifié refusé avec conservation de l’image et du crédit précédents.
- Image nettoyée téléchargée par le navigateur : PNG 40 × 80, aucune entrée EXIF, orientation portrait conservée. Images synthétiques portrait, paysage et très large générées en PDF puis rendues avec Poppler : bandes latérales et recadrage central conformes.
- Deux lignes du même module fictif 450 Wc (2 + 3) et une batterie fictive : extrafields 2,25 kWc / 10 kWh, couverture « 5 modules ». Deux pages avec couverture contre une pour l’ancien modèle ; texte commercial identique après retrait de la seule pagination.
- Action de retrait sans token refusée par la protection CSRF native. Accès direct à la couverture depuis TEST 2 refusé ; ce refus seul ne valide ni un devis partagé ni l’accès autorisé dans cette entité. Retour à TEST 1.
- Brouillons conservés : devis 53, commande 72, facture 117. Aucun document commercial validé, signé ou envoyé. Le module de zonage a affiché `GeocoderHttpError` à la création du devis et de la facture ; les brouillons ont néanmoins été créés et relus.

Les captures et PDF de preuve restent locaux, hors livraison. Les modifications temporaires de couleur et d’activation du modèle ont été restaurées ; le modèle par défaut n’a pas changé. Les extrafields créés et les données fictives ne sont pas purgés. Les checkouts de develop sont restaurés au code antérieur après les essais ; voir les informations de restitution dans le compte rendu.

Restitution confirmée par cPanel et les pages À propos : JPSUN 1.24.2, branche `fix/jpsun-1.24.2-simple-accounting`, commit `850bd90f4b9263e637042f09a8f44dd60be3332b` ; PowerPlantPV 1.5.0, commit `f067f003d16963cbe09a535eabea549c467a5c1e`. L’ancienne branche PowerPlantPV ayant disparu du distant, la branche `fix/recette-restore-develop-20261105` pointe sur ce même commit et sert désormais ce checkout. Aucun merge ni nouvelle release.

## Vérifications réelles restant nécessaires

Exécuter les scénarios restants dans le navigateur intégré sur le SHA de PR effectivement servi. Le test de la page de repli ne prouve pas un parcours complet avec JavaScript désactivé.

Restent à exécuter : profils en lecture seule et externe, contacts multiples/privés, comparaison auteur/valideur, devis partagé autorisé avec répertoire propriétaire indisponible, conversions commerciales, annexes configurées et pieds de page longs dans l’instance. La matrice CI v20–v25 alpha et les tests locaux ne remplacent pas ces scénarios. Aucun ZIP installable ou acceptation Dolistore n’est revendiqué. Les règles de packaging officielles ont été consultées le 2026-11-05 (wiki oldid 65313). Les points d’entrée ajoutés essaient les deux dispositions d’installation et les tests sont réservés à la CLI.

## PowerPlantPV

La PR 1.6.0 fournit `powerplantpv_consumption_type` sur devis, commandes et factures. Le même nom technique permet la reprise native lors des conversions ; il n’ajoute pas de synchronisation rétroactive entre documents existants. Les choix présents ne sont pas modifiés. `powerplantpvCountProposalModules` effectue une lecture SQL seule, selon la classification MODULE et les contrôles natifs d’accès.
