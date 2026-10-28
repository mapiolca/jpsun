# Modèle de facture JPSUN — preuves de développement

Évolution de JPSUN `1.23.0`, datée du 2026-10-28. Les validations ci-dessous concernent le modèle de facture ajouté dans cette version.

## Périmètre

Le modèle `jpsuninvoice` hérite de Sponge. Seul le récapitulatif des déductions est personnalisé : total net avant remise, **Acomptes déduits**, **Remise totale**, puis totaux natifs. Les montants de ce récapitulatif sont HT et dans la devise de la facture. Une remise commerciale n'est pas déplacée dans les acomptes. Le montant d'acompte est celui de la ligne effectivement déduite, pas le total de la facture source ou le total de ses paiements.

La relation `fk_remise_except` est chargée avec `DiscountAbsolute::fetch()` : son filtrage natif d'entité est conservé, le tiers et la ligne consommatrice sont vérifiés, puis le type de facture source est comparé à `Facture::TYPE_DEPOSIT`. Aucun classement ne repose sur le texte visible de la ligne. Avoirs, lignes négatives ordinaires et acomptes affectés comme règlements gardent leur traitement natif. Les factures de situation et les factures d'avoir conservent le récapitulatif Sponge.

Les points d'entrée de génération fiche, action de masse et API passent par le même `write_file()`. Leurs permissions et protections natives restent applicables. Aucun endpoint, paiement, écriture métier, trigger, cron, schéma SQL, migration ou modèle de numérotation n'est ajouté. Le modèle vérifie la présence du répertoire documentaire propriétaire avant de déléguer au générateur natif ; aucun repli vers l'entité de consultation n'est ajouté.

## Contrats natifs examinés

Lecture locale des tags 20.0.0, 21.0.0, 22.0.0, 23.0.0 et 24.0.0 : `Sponge::drawTotalTable()` fournit le récapitulatif ; les contrôles `showAmountBeforeDiscount` et `showDiscountAmount` sont utilisés par Sponge à partir de la révision 24 examinée. Le modèle JPSUN dépend de ces contrôles et est donc désactivé sous v20–v23, sans recopier le générateur core.

Version de l'instance déclarée par l'utilisateur : **24.0.2**. Les tests ciblés ont été exécutés sous **PHP 8.4.22**, avec les sources exactes du tag **24.0.2**, commit **f5ff00ea4a8910eb999caadb6e3a93e5cf68a531**, exportées dans le répertoire ignoré `.runtime/24.0.2`. Contrats concernés :

- `core/modules/facture/doc/pdf_sponge.modules.php` : génération, colonnes, réservation de hauteur et `drawTotalTable()` ;
- `core/lib/pdf.lib.php` : `pdfGetLineTotalDiscountAmount()`, TVA, règlements et solde ;
- `core/class/discount.class.php` : chargement de la déduction, type source et entités ;
- `core/lib/functions.lib.php` : `price2num()`, `price()`, `getEntity()` et `getMultidirOutput()` ;
- `admin/invoice.php` : découverte des modèles externes et exclusion de `version = 'disabled'`.

## Vérifications exécutées

```text
php test/invoice.php test/.runtime/24.0.2/htdocs 24.0.2
php test/invoice-phpstan.php test/.runtime/24.0.2/htdocs <chemin/tcpdf.php>
php <chemin/phpstan.phar> analyse -c test/.runtime/phpstan-invoice.neon --no-progress --memory-limit=1G
```

Le test charge les classes et fonctions core réelles. Les réponses de base de données et l'objet facture sont simulés ; aucun accès à la base d'une instance. Il reproduit d'abord l'affichage natif de l'acompte dans « Remise totale », puis vérifie le modèle corrigé sur les mêmes données :

| Ligne | Montant HT / total |
|---|---:|
| Total net avant remise | 3 625,00 |
| Acomptes déduits | 1 192,50 |
| Remise totale | 0,00 |
| Total HT | 2 432,50 |
| TVA 20 % | 486,50 |
| Total TTC | 2 919,00 |

Scénarios : acompte seul, remise commerciale supplémentaire, acomptes multiples, avoir et ligne négative non reclassés, montants en devise, ordre des lignes, montants de facture inchangés, restauration des options natives, réservation de la hauteur, absence d'acompte, erreur SQL, déduction absente, tiers ou ligne incohérents, répertoire propriétaire manquant et module désactivé. Le refus des versions 20 à 23 est aussi testé en simulant uniquement la constante de version sur ce core 24.0.2 ; ce n'est pas un essai sur ces instances.

PHPStan : **niveau 5**, syntaxe cible **PHP 8.0**, classes et fonctions natives 24.0.2 et TCPDF réels ; aucun baseline ni suppression d'erreur ajouté. Les nouveaux fichiers métier sont analysés ; les pages et la bibliothèque admin existante sont contrôlées par lint et revue ciblée.

Un aperçu du récapitulatif est générable avec TCPDF réel :

```text
php test/invoice.php test/.runtime/24.0.2/htdocs 24.0.2 <chemin/tcpdf.php>
pdftoppm -f 1 -singlefile -png test/.runtime/invoice-summary.pdf test/.runtime/invoice-summary
```

Cet aperçu a été rendu et inspecté visuellement : libellés français traduits, ordre correct, colonnes alignées et aucun chevauchement dans le récapitulatif. Il s'agit d'un document synthétique de contrôle, pas d'une facture émise ni d'une preuve de génération complète sur l'instance.

## Vérifications restant à effectuer

- Sur l'instance 24.0.2 : découverte/activation/sélection native du modèle, génération complète, aperçu et téléchargement, avec les droits, entités et partages réels ; aucun déploiement n'a été effectué.
- Factures multipages, texte libre long/HTML, pieds société et hooks, bilingue, formats de page, taxes multiples, QR facture et pièces jointes ; la réservation supplémentaire est prévue mais ces parcours complets ne sont pas validés.
- Actions de masse et API réelles, utilisateur externe et périmètre commercial ; seuls les contrats communs ont été relus.
- PHP 8.0 réel et Dolibarr 25 : non exécutés. La cible générale du module reste v20–v25 ; elle ne constitue pas une certification de ce nouveau modèle sur toutes les versions.

Le descripteur existant déclare déjà `models = 1` : aucune réactivation automatique du module ni modification de son identité, de ses droits ou de ses paramètres n'est nécessaire pour ajouter le fichier du modèle. Son activation reste un choix de l'administrateur dans les réglages natifs des factures. Cette évolution et l'action administrative nécessaire sont documentées dans le changelog `1.23.0`.
