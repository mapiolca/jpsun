<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/** Generate an ignored PHPStan configuration using a complete native Dolibarr tree. */
if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit;
}
$root = realpath($argv[1] ?? '');
if (!$root || !is_file($root.'/main.inc.php') || !is_file($root.'/user/class/user.class.php')) {
	throw new RuntimeException('Usage: php test/customerdeposits-phpstan.php <complete Dolibarr htdocs>');
}
$runtime = __DIR__.'/.runtime';
if (!is_dir($runtime) && !mkdir($runtime, 0777, true)) {
	throw new RuntimeException('Cannot create test runtime');
}
$bootstrap = "<?php\ndefine('DOL_DOCUMENT_ROOT', ".var_export($root, true).");\ndefine('DOL_VERSION', getenv('DOLIBARR_TEST_VERSION') ?: '24.0.0');\n";
file_put_contents($runtime.'/phpstan-deposits-bootstrap.php', $bootstrap);
$config = "parameters:\n    level: 5\n    phpVersion: 80000\n    dynamicConstantNames:\n        - DOL_VERSION\n    tmpDir: cache-deposits\n    bootstrapFiles:\n        - phpstan-deposits-bootstrap.php\n    paths:\n";
foreach (array('customerdeposits.php', 'class/jpsuncustomerdepositreport.class.php', 'class/jpsuncustomerdepositcompatibility.class.php', 'admin/compatibility.php') as $file) {
	$config .= '        - '.json_encode(realpath(__DIR__.'/../'.$file))."\n";
}
$config .= "    scanDirectories:\n        - ".json_encode($root)."\n    scanFiles:\n";
foreach (array('lib/jpsun.lib.php', 'class/jpsuninvoicecompatibility.class.php', 'backport/v19/core/class/commonhookactions.class.php', 'lib/jpsun_delivery.lib.php', 'lib/jpsun_pdf_signature.lib.php') as $file) {
	$config .= '        - '.json_encode(realpath(__DIR__.'/../'.$file))."\n";
}
file_put_contents($runtime.'/phpstan-deposits.neon', $config);
echo "Generated test/.runtime/phpstan-deposits.neon (level 5, PHP 8.0).\n";
