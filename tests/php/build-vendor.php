<?php

error_reporting(-1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

$composerPhar = getenv('PS_SOCIALFOLLOW_COMPOSER_PATH');
$outDir = getenv('PS_SOCIALFOLLOW_VENDOR_OUT');
$testsPhp = '/app/tests/php';

if (!$composerPhar || !is_readable($composerPhar) || !$outDir) {
    fwrite(STDERR, "build-vendor: missing composer phar or output dir\n");
    exit(1);
}

if (!is_dir($outDir)) {
    mkdir($outDir, 0700, true);
}

$work = sys_get_temp_dir() . '/psf-composer-work-' . getmypid();
mkdir($work, 0700, true);
copy($testsPhp . '/composer.json', $work . '/composer.json');

passthru('printf "deb http://archive.debian.org/debian buster main\\n" > /etc/apt/sources.list');
passthru('DEBIAN_FRONTEND=noninteractive apt-get -o Acquire::Check-Valid-Until=false update');
passthru('DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends unzip git zlib1g-dev');
if (!extension_loaded('zip')) {
    passthru('docker-php-ext-install zip');
}

$cmd = 'cd ' . escapeshellarg($work) . ' && COMPOSER_VENDOR_DIR=' . escapeshellarg($outDir)
    . ' php -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 '
    . escapeshellarg($composerPhar)
    . ' install --no-interaction --no-progress --prefer-dist --no-ansi 2>&1';

passthru($cmd, $exit);
if ($exit !== 0) {
    exit($exit);
}

$expected = array(
    'symfony/validator' => '3.4.47',
    'symfony/translation' => '3.4.47',
    'symfony/polyfill-ctype' => '1.19.0',
    'symfony/polyfill-mbstring' => '1.19.0',
);

$installed = json_decode(file_get_contents($outDir . '/composer/installed.json'), true);
if (!is_array($installed)) {
    fwrite(STDERR, "installed.json missing\n");
    exit(2);
}

$packages = isset($installed['packages']) ? $installed['packages'] : $installed;
$map = array();
foreach ($packages as $pkg) {
    $map[$pkg['name']] = isset($pkg['version']) ? ltrim($pkg['version'], 'v') : '';
}
foreach ($expected as $name => $version) {
    if (!isset($map[$name]) || $map[$name] !== $version) {
        fwrite(STDERR, "Expected {$name} {$version}, got " . (isset($map[$name]) ? $map[$name] : 'missing') . "\n");
        exit(3);
    }
}

echo "VENDOR_OK\n";
