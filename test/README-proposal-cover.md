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

### Complément de recette — 2026-11-05

Essais dans le navigateur intégré, même core/PHP, JPSUN `62b2ad61b49e1b1c5fddaa89cf330ab045474476` et PowerPlantPV `a526de15073829985d674c02b29d8b2c8c31418b`, avec connexion réelle aux comptes existants et sans modification de leurs droits :

- Deux contacts fictifs de suivi ajoutés au devis 53 : Élodie RECETTE COUVERTURE ALPHA et François RECETTE COUVERTURE BÊTA. Ajout dans l’ordre inverse ; couverture dans l’ordre natif ALPHA, BÊTA, accents conservés.
- `utilisateur.test` peut consulter, générer et télécharger le PDF. Sans droit de lecture des contacts, leur mention est omise et le PDF reste générable. L’accès direct à la facture 117 est refusé. La commande de validation est inactive dans l’interface ; une tentative directe avec confirmation est refusée par le contrôle de marge, sans validation du devis. Ce dernier refus ne prouve pas à lui seul le contrôle natif du droit de validation.
- Brouillon : interlocuteur « Codex OPEN AI », même lorsque le PDF est généré par le commercial. Après validation par `3.utilisateur` : « 3 Utilisateur », nouvelle référence PR2611-0012 et date native, deux contacts et cinq modules conservés.
- Pour respecter les règles de marge existantes, les trois lignes fictives ont reçu un prix unitaire de vente de 200 et un coût de 100 (quantités 2, 3 et 1), soit 1 200 HT / 1 440 TTC. Aucune règle de marge n’a été changée.
- Conversion par les formulaires core : devis 53 validé → commande 73 CO2611-0016 validée → facture 118 conservée en brouillon. Type de consommation identique, 2,25 kWc, 10 kWh et trois lignes conservés. La création de commande utilise le formulaire natif avec origine `propal` ; le devis reste ouvert. La proposition d’acceptation annonçant un e-mail automatique a été annulée. Les confirmations des deux validations annonçaient l’absence de notification. Aucun e-mail ni paiement n’a été exécuté.
- Partage individuel temporaire du devis 53 depuis TEST 1 vers TEST 2 : fiche accessible. Sur cette révision, l’éditeur ne retrouve pas les données de couverture si les définitions d’extrafields diffèrent entre entités ; correction publiée dans `801b30ed3b0b2dc1cdf66728453d445f54bc53a6`, puis servie via cPanel. En-tête TEST 2 et aperçu/crédit propriétaire confirmés sur la page protégée ; image téléchargée. Une première sauvegarde est refusée, puis modification effective et sauvegarde sans changement réussissent. La légende est relue et rétablie dans TEST 1. Le PDF généré contient bien les données propriétaire et est téléchargé. Toutefois, le bandeau revient à TEST 1 après des redirections natives ; le contexte exact de la génération après redirection n’a pas été relevé. **La génération avec maintien du contexte TEST 2 reste non validée.** Le téléchargement du PDF provisoire depuis TEST 2 avait répondu « Le fichier n’existe pas » ; le PDF validé est ensuite téléchargé. Ces constats ne suffisent pas à attribuer le défaut au core, à Multicompany ou au module.

Contrôles locaux du correctif partagé : lecture SQL SQLite réelle (12 assertions), tests ciblés de couverture et refus du droit Multicompany d’écriture sur un devis partagé, syntaxe PHP et PHPStan niveau 5 / cible PHP 8.0 sans erreur sur le service, la page et le modèle. La CI de `801b30e` est verte : six révisions Dolibarr 20–25 alpha et build. Ces contrôles ne remplacent pas la recette distante du correctif.

Restitution effectuée : modèle de couverture inactif dans les deux entités, partage individuel du devis vide, lien de partage des propositions de TEST 2 vide, granularité des propositions désactivée et partage automatique global toujours désactivé. Comparaison du formulaire TEST 2 : 187 champs/contrôles identiques à l’état initial. Les réglages globaux de partage déjà actifs et les modèles par défaut n’ont pas été changés. cPanel confirme les deux SHA initiaux ci-dessus ; les pages À propos servent à nouveau JPSUN 1.24.2 et PowerPlantPV 1.5.0. Les contacts et documents fictifs sont conservés, sans purge. Le devis 53 et la commande 73 restent validés pour conserver la trace du scénario, la facture 118 reste en brouillon. Aucun merge ni release.

Exécuter les scénarios restants dans le navigateur intégré sur le SHA de PR effectivement servi. Le test de la page de repli ne prouve pas un parcours complet avec JavaScript désactivé.

Restent à exécuter : profils strictement en lecture seule et externe, contacts privés, devis partagé sur le correctif publié et répertoire propriétaire indisponible, annexes configurées et pieds de page longs dans l’instance. La matrice CI v20–v25 alpha et les tests locaux ne remplacent pas ces scénarios. Aucun ZIP installable ou acceptation Dolistore n’est revendiqué. Les règles de packaging officielles ont été consultées le 2026-11-05 (wiki oldid 65313). Les points d’entrée ajoutés essaient les deux dispositions d’installation et les tests sont réservés à la CLI.

## PowerPlantPV

La PR 1.6.0 fournit `powerplantpv_consumption_type` sur devis, commandes et factures. Le même nom technique permet la reprise native lors des conversions ; il n’ajoute pas de synchronisation rétroactive entre documents existants. Les choix présents ne sont pas modifiés. `powerplantpvCountProposalModules` effectue une lecture SQL seule, selon la classification MODULE et les contrôles natifs d’accès.
