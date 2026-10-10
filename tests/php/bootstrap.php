<?php

error_reporting(-1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
date_default_timezone_set('UTC');

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.1.7');
}

$testsPhpDir = __DIR__;
$repoRoot = dirname(dirname($testsPhpDir));

$vendor = getenv('PS_SOCIALFOLLOW_VENDOR');
if (!$vendor || !is_dir($vendor)) {
    fwrite(STDERR, "PS_SOCIALFOLLOW_VENDOR is not set or missing\n");
    exit(1);
}

require $vendor . '/autoload.php';

if (!class_exists('PHPUnit_Framework_TestCase', false) && class_exists('PHPUnit\\Framework\\TestCase')) {
    class_alias('PHPUnit\\Framework\\TestCase', 'PHPUnit_Framework_TestCase');
}
if (!class_exists('PHPUnit_Framework_Assert', false) && class_exists('PHPUnit\\Framework\\Assert')) {
    class_alias('PHPUnit\\Framework\\Assert', 'PHPUnit_Framework_Assert');
}

require $testsPhpDir . '/Support/PrestaShopWidgetInterface.php';
require $testsPhpDir . '/Support/Fakes.php';
require $testsPhpDir . '/Support/ChildProcess.php';
require $testsPhpDir . '/ModuleTestCase.php';

require $repoRoot . '/ps_socialfollow.php';
require $testsPhpDir . '/Support/TestModule.php';
require $repoRoot . '/upgrade/upgrade-2.3.0.php';
