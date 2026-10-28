<?php
/** Generate an ignored PHPStan configuration using real Dolibarr/TCPDF sources. */
$root = realpath($argv[1] ?? '');
$tcpdf = realpath($argv[2] ?? $root.'/includes/tecnickcom/tcpdf/tcpdf.php');
if (!$root || !is_file($root.'/core/modules/facture/doc/pdf_sponge.modules.php') || !$tcpdf) {
	throw new RuntimeException('Usage: php test/invoice-phpstan.php <Dolibarr htdocs> <tcpdf.php>');
}
$runtime = __DIR__.'/.runtime';
if (!is_dir($runtime)) { mkdir($runtime, 0777, true); }
$bootstrap = "<?php\ndefine('DOL_DOCUMENT_ROOT', ".var_export($root, true).");\ndefine('DOL_VERSION', getenv('DOLIBARR_TEST_VERSION') ?: '24.0.2');\n";
file_put_contents($runtime.'/phpstan-bootstrap.php', $bootstrap);
$config = "parameters:\n    level: 5\n    phpVersion: 80000\n    dynamicConstantNames:\n        - DOL_VERSION\n    tmpDir: cache\n    bootstrapFiles:\n        - phpstan-bootstrap.php\n    paths:\n";
foreach (array('core/modules/facture/doc/pdf_jpsuninvoice.modules.php', 'class/jpsuninvoicecompatibility.class.php') as $file) {
	$config .= '        - '.json_encode(realpath(__DIR__.'/../'.$file))."\n";
}
$config .= "    scanDirectories:\n        - ".json_encode($root)."\n    scanFiles:\n        - ".json_encode($tcpdf)."\n";
file_put_contents($runtime.'/phpstan-invoice.neon', $config);
echo "Generated test/.runtime/phpstan-invoice.neon (level 5, PHP 8.0).\n";
