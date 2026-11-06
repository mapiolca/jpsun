# Traductions des modèles de document — JPSUN 1.25.1

Les 19 modèles PDF livrés possèdent un nom et une description dans les cinq catalogues `fr_FR`, `en_US`, `de_DE`, `es_ES` et `it_IT`. Les modèles chargent le catalogue JPSUN avant de lire leurs métadonnées. Les clés manquantes de synthèse projet, de raccordement ENEDIS, de commande client et du devis sans image sont complétées ; la clé du devis avec images n’est plus définie deux fois pour porter deux descriptions opposées. Les descriptions de la couverture, de l’affiche et des étiquettes sont distinctes de leurs noms.

Le registre `JpsunDocumentModelTranslations` utilise le couple type documentaire/identifiant technique, car plusieurs objets possèdent un modèle `jpsun`. Le hook natif `getListOfModels` ne traduit que les entrées JPSUN déjà retournées par le core : mêmes clés, mêmes activations, même ordre, aucun ajout de modèle désactivé ni écriture dans `document_model`. Les modèles d’autres modules restent inchangés. Les libellés d’affichage des modèles JPSUN sont résolus dans la langue courante ; leur libellé historique en base reste conservé.

Contrat natif : hook observé dans `htdocs/core/lib/functions2.lib.php` à partir de Dolibarr 23, commit d’introduction `bb294eef951` (PR core #34626). Les révisions figées de la CI conservent ce contrat en 23, 24 et 25 alpha ; les sources 20, 21 et 22 ne l’exposent pas. Le prédicat partagé avec la page Compatibilité exige explicitement Dolibarr 23 et JPSUN actif. Sur 20–22, les réglages utilisent les métadonnées traduites, tandis que les sélecteurs conservent le libellé natif enregistré à l’activation ; aucun remplacement du core n’est ajouté.

## Vérifications reproductibles

`python test/document_model_translations.py /chemin/htdocs VERSION`

Le contrôle vérifie la présence unique des 40 clés de métadonnées/compatibilité dans chaque catalogue, instancie les 19 modèles avec le chargeur de traductions natif dans cinq langues, puis appelle le sélecteur natif et le HookManager. Chaque modèle est chargé dans un processus indépendant pour éviter la collision historique des classes `pdf_jpsun`. La base et l’acteur sont simulés : pas une instance complète, pas un essai de génération ni de permissions métier. Les écritures ne font pas partie de ce traitement.

Exécution locale le 2026-11-06 sous PHP 8.4.22 : découverte complète réussie avec les sources Dolibarr 25.0.0-alpha `0ac96524bf68b063a44d6af8137e05bf11c227c8`. Sélecteur natif réussi sur les six révisions 20–25 figées dans `.github/workflows/customerdeposits.yml` ; les copies locales 20–24 sont partielles et ne permettent pas leur découverte complète. La CI utilise les checkouts complets pour ce contrôle.

Syntaxe vérifiée sur les PHP modifiés, huit tests d’installation/conservation réussis, rendu existant de l’affiche et ses refus inchangés réussis. L’essai local d’installation requiert une exécution hors bac à sable pour ses fichiers temporaires Windows ; aucune règle de sécurité Dolibarr modifiée.

PHPStan niveau 5, cible PHP 8.0, bootstrap natif et limite mémoire CLI de 1 Go : nouveau registre sans diagnostic ; classe de hooks avec les mêmes 44 diagnostics préexistants que `origin/main` (dont des PHPDoc historiques mal formés). Comparaison des identifiants/messages, en neutralisant seulement les offsets de texte des erreurs de parsing dus aux fins de ligne. Aucun nouvel ignore ni baseline ajouté ; aucun diagnostic sur le nouveau hook. Les changements de métadonnées des modèles sont couverts par la syntaxe et leur instanciation réelle, sans réaudit PHPStan intégral des anciens générateurs.

Pas de déploiement 1.25.1 ni de recette interactive sur develop dans ce travail : develop sert encore 1.25.0. Aucun changement de configuration, d’entité, de droits, de données métier ou de modèle par défaut. La release 1.25.0 publiée et son tag sur `2c498d57c1f66c44c0282f0b6c840c485a8c7403` ont été vérifiés avant l’incrément. Aucun ZIP installable ni validation Dolistore revendiqué.

Intégration PowerPlantPV 1.6.1 : la couverture traduit les clés des quatre types de raccordement via le résolveur déjà présent en 1.6.0. Le test de couverture utilise sa source publiée figée ; douze cas supplémentaires vérifient les quatre types, la valeur vide et un ancien texte en français/anglais, avec capture des textes transmis au rendu. La génération native existante reste exécutée. Aucun changement du dictionnaire ni écriture métier pendant ce rendu.
