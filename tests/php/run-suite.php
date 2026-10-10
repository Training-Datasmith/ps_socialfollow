<?php

error_reporting(-1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

/**
 * Orchestrates ps_socialfollow PHPUnit runs in digest-pinned PHP containers.
 *
 * Usage (from repo root):
 *   php tests/php/run-suite.php
 */

$repoRoot = dirname(dirname(__DIR__));
chdir($repoRoot);

$rootComposerJsonHash = hash_file('sha256', $repoRoot . '/composer.json');
$rootComposerLockHash = hash_file('sha256', $repoRoot . '/composer.lock');

$runs = array(
    array(
        'label' => 'PHP 5.6.40',
        'image' => 'php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143',
        'phpunit' => '4.8.36',
        'phpunitSha256' => 'ab8bc3017a64cf75a2112f5e089a1d5b0c4cc2116556d60c1af5e3f584e85c5e',
        'expectedVersion' => '5.6.40',
    ),
    array(
        'label' => 'PHP 7.2.34',
        'image' => 'php@sha256:42ffbc0798e4449bbd1e14fc4dcb87774aa1ad1900a09ef6a965bc0880aa2161',
        'phpunit' => '6.5.14',
        'phpunitSha256' => '3ac5d3234ed2b2dd9aaaa1b33e5c275ce5d7d8f2e07ec7fa930f5036894cc689',
        'expectedVersion' => '7.2.34',
        'composerUrl' => 'https://getcomposer.org/download/2.2.25/composer.phar',
        'composerSha256' => '8b3f41253363f0645402d1951d6e7f02adedeef29c16de6074763e463e25c23f',
    ),
);

$dockerBin = '';
foreach (array('/tmp/docker/docker', 'sudo /tmp/docker/docker', 'docker', 'sudo docker') as $candidate) {
    $out = array();
    exec($candidate . ' version >/dev/null 2>&1', $out, $code);
    if ($code === 0) {
        $dockerBin = $candidate;
        break;
    }
}

$results = array();
$failed = false;

if ($dockerBin === '') {
    fwrite(STDERR, "Docker is not available; cannot run digest-pinned suite.\n");
    writeArtifact(array(), $repoRoot, 1);
    exit(1);
}

$sharedVendorDir = sys_get_temp_dir() . '/psf-shared-vendor-' . substr(hash('sha256', file_get_contents($repoRoot . '/tests/php/composer.json')), 0, 16);
if (!is_dir($sharedVendorDir . '/composer')) {
    if (is_dir($sharedVendorDir)) {
        exec('rm -rf ' . escapeshellarg($sharedVendorDir));
    }
    mkdir($sharedVendorDir, 0700, true);
    $composerRun = $runs[1];
    $toolDir = prepareVerifiedTooling($composerRun);
    $buildCmd = $dockerBin
        . ' run --rm'
        . ' -v ' . escapeshellarg($repoRoot) . ':/app'
        . ' -v ' . escapeshellarg($toolDir) . ':/tools:ro'
        . ' -v ' . escapeshellarg($sharedVendorDir) . ':/vendor-out'
        . ' -e PS_SOCIALFOLLOW_COMPOSER_PATH=/tools/composer.phar'
        . ' -e PS_SOCIALFOLLOW_VENDOR_OUT=/vendor-out'
        . ' php@sha256:42ffbc0798e4449bbd1e14fc4dcb87774aa1ad1900a09ef6a965bc0880aa2161'
        . ' php -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 /app/tests/php/build-vendor.php';
    exec($buildCmd . ' 2>&1', $buildOut, $buildExit);
    if ($buildExit !== 0 || strpos(implode("\n", $buildOut), 'VENDOR_OK') === false) {
        fwrite(STDERR, "Shared vendor build failed:\n" . implode("\n", $buildOut) . "\n");
        writeArtifact(array(), $repoRoot, 1);
        exit(1);
    }
}

foreach ($runs as $run) {
    $toolDir = prepareVerifiedTooling($run);
    $inner = buildInnerRunnerScript($run);
    $innerPath = $repoRoot . '/tests/php/.run-inner-temp.php';
    file_put_contents($innerPath, $inner);

    $cmd = $dockerBin
        . ' run --rm'
        . ' -v ' . escapeshellarg($repoRoot) . ':/app'
        . ' -v ' . escapeshellarg($toolDir) . ':/tools:ro'
        . ' -e PS_SOCIALFOLLOW_PHPUNIT_PATH=/tools/phpunit.phar'
        . ' -v ' . escapeshellarg($sharedVendorDir) . ':/vendor:ro'
        . ' -e PS_SOCIALFOLLOW_VENDOR=/vendor'
        . ' -w /app '
        . escapeshellarg($run['image'])
        . ' php -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 -d date.timezone=UTC '
        . ' /app/tests/php/.run-inner-temp.php';

    $output = array();
    $exit = 0;
    exec($cmd . ' 2>&1', $output, $exit);
    $text = implode("\n", $output);

    $parsed = parseRunnerOutput($text);
    $parsed['label'] = $run['label'];
    $parsed['exitCode'] = $exit;
    $parsed['raw'] = $text;
    $results[] = $parsed;

    if ($exit !== 0 || !$parsed['ok']) {
        $failed = true;
    }

    @unlink($innerPath);
}

$postJsonHash = hash_file('sha256', $repoRoot . '/composer.json');
$postLockHash = hash_file('sha256', $repoRoot . '/composer.lock');
if ($postJsonHash !== $rootComposerJsonHash || $postLockHash !== $rootComposerLockHash) {
    fwrite(STDERR, "Root composer.json or composer.lock was modified\n");
    exit(1);
}

writeArtifact($results, $repoRoot, $failed ? 1 : 0);

exit($failed ? 1 : 0);

function prepareVerifiedTooling(array $run)
{
    $toolDir = sys_get_temp_dir() . '/psf-suite-tools-' . md5($run['label'] . microtime(true));
    mkdir($toolDir, 0700, true);

    if (!empty($run['composerUrl']) && !empty($run['composerSha256'])) {
        $composerPhar = $toolDir . '/composer.phar';
        downloadVerifiedFile($run['composerUrl'], $composerPhar, $run['composerSha256']);
    }

    $phpunitPhar = $toolDir . '/phpunit.phar';
    $phpunitAsc = $toolDir . '/phpunit.phar.asc';
    $phpunitUrl = 'https://phar.phpunit.de/phpunit-' . $run['phpunit'] . '.phar';
    downloadVerifiedFile($phpunitUrl, $phpunitPhar, $run['phpunitSha256']);
    downloadVerifiedFile($phpunitUrl . '.asc', $phpunitAsc, null);
    verifyPhpunitPharSignature($phpunitPhar, $phpunitAsc);

    return $toolDir;
}

function downloadVerifiedFile($url, $dest, $expectedSha256)
{
    $ctx = stream_context_create(array('http' => array('follow_location' => 1)));
    $data = file_get_contents($url, false, $ctx);
    if ($data === false) {
        throw new RuntimeException('Download failed: ' . $url);
    }
    file_put_contents($dest, $data);
    if ($expectedSha256 !== null && hash_file('sha256', $dest) !== $expectedSha256) {
        throw new RuntimeException('SHA-256 mismatch for ' . $url);
    }
}

function verifyPhpunitPharSignature($phar, $asc)
{
    $gpgHome = sys_get_temp_dir() . '/psf-gpg-host-' . getmypid() . '-' . mt_rand();
    mkdir($gpgHome, 0700, true);
    putenv('GNUPGHOME=' . $gpgHome);

    $keyFile = $gpgHome . '/key.asc';
    $keyData = file_get_contents('https://sebastian-bergmann.de/gpg.asc');
    if ($keyData === false) {
        throw new RuntimeException('Could not download PHPUnit signing key');
    }
    file_put_contents($keyFile, $keyData);

    exec('gpg --batch --import ' . escapeshellarg($keyFile) . ' 2>&1', $importOut, $importCode);
    if ($importCode !== 0) {
        throw new RuntimeException('GPG import failed: ' . implode("\n", $importOut));
    }

    $cmd = 'gpg --batch --status-fd 1 --verify ' . escapeshellarg($asc) . ' ' . escapeshellarg($phar) . ' 2>/dev/null';
    exec($cmd, $status, $verifyCode);
    if ($verifyCode !== 0) {
        throw new RuntimeException('GPG verify failed');
    }
    $valid = false;
    foreach ($status as $line) {
        if (strpos($line, '[GNUPG:] VALIDSIG') !== false
            && strpos($line, 'D8406D0D82947747293778314AA394086372C20A') !== false) {
            $valid = true;
        }
    }
    if (!$valid) {
        throw new RuntimeException('PHPUnit PHAR VALIDSIG fingerprint mismatch');
    }
}

function buildInnerRunnerScript(array $run)
{
    $phpunit = $run['phpunit'];
    $sha = $run['phpunitSha256'];
    $expectedVersion = $run['expectedVersion'];

    $script = <<<'PHP'
<?php
error_reporting(-1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
date_default_timezone_set('UTC');

$repoRoot = '/app';
$testsPhp = $repoRoot . '/tests/php';
$expected = require $testsPhp . '/expected-suite.php';
$expectedTestCount = (int) $expected['testCount'];

$version = PHP_VERSION;
if (strpos($version, 'PHP_VERSION') !== false) {
    // placeholder guard
}
if ($version !== 'EXPECTED_VERSION' && strpos($version, substr('EXPECTED_VERSION', 0, 3)) === false) {
    // allow patch match below
}
$expectedFull = 'EXPECTED_VERSION';
if ($version !== $expectedFull) {
    fwrite(STDERR, "Unexpected PHP version: {$version}, expected {$expectedFull}\n");
    exit(2);
}

putenv('PHP_BINARY=php');

$toolDir = sys_get_temp_dir() . '/psf-tools-' . getmypid();
mkdir($toolDir, 0700, true);

$phpunitPhar = getenv('PS_SOCIALFOLLOW_PHPUNIT_PATH');
$vendorDir = getenv('PS_SOCIALFOLLOW_VENDOR');
if (!$phpunitPhar || !is_readable($phpunitPhar) || !$vendorDir || !is_readable($vendorDir . '/autoload.php')) {
    fwrite(STDERR, "Pre-verified tooling or vendor mount missing\n");
    exit(3);
}

putenv('PS_SOCIALFOLLOW_VENDOR=' . $vendorDir);

$passes = array('sorted', 'shuffled');
$allOk = true;
$summary = array(
    'tests' => $expectedTestCount,
    'assertions' => 0,
    'failures' => 0,
    'errors' => 0,
    'skipped' => 0,
    'incomplete' => 0,
);

foreach ($passes as $pass) {
    $xmlPath = $toolDir . '/phpunit-' . $pass . '.xml';
    if ($pass === 'sorted') {
        $files = $expected['files'];
        $ordered = array();
        foreach ($files as $file) {
            $ordered[] = $testsPhp . '/' . $file;
        }
    } else {
        $files = $expected['files'];
        srand(20261010);
        shuffle($files);
        $ordered = array();
        foreach ($files as $file) {
            $ordered[] = $testsPhp . '/' . $file;
        }
    }
    writePhpunitXml($xmlPath, $ordered);

    $logOut = $toolDir . '/phpunit-' . $pass . '.log';
    $cmd = 'php -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 '
        . escapeshellarg($phpunitPhar) . ' --configuration ' . escapeshellarg($xmlPath)
        . ' 2>&1';
    $lines = array();
    exec($cmd, $lines, $exitCode);
    $log = implode("\n", $lines);
    file_put_contents($logOut, $log);
    if (preg_match('/\nWarning:/', $log) || preg_match('/^Warning:/m', $log)) {
        fwrite(STDERR, "PHPUnit warning output detected in {$pass} pass\n");
        $allOk = false;
    }
    if (preg_match('/PHPUnit\\\\Framework\\\\Warning:/', $log)) {
        $allOk = false;
    }
    if (preg_match('/(?:Notice|Warning|Deprecated|Strict Standards|Parse error|Fatal error):/i', $log)) {
        fwrite(STDERR, "PHP diagnostic in {$pass} pass log\n");
        $allOk = false;
    }
    if (!preg_match('/OK \\(' . $expectedTestCount . ' tests/', $log)) {
        fwrite(STDERR, "Missing clean OK summary for {$pass} pass\n");
        $allOk = false;
    }
    if (preg_match('/OK \\(' . $expectedTestCount . ' tests, (\\d+) assertions\\)/', $log, $m)) {
        $summary['assertions'] = max($summary['assertions'], (int) $m[1]);
    }
    if (preg_match('/Failures: (\\d+)/', $log, $m)) {
        $summary['failures'] += (int) $m[1];
    }
    if (preg_match('/Errors: (\\d+)/', $log, $m)) {
        $summary['errors'] += (int) $m[1];
    }
    if (preg_match('/Skipped: (\\d+)/', $log, $m)) {
        $summary['skipped'] += (int) $m[1];
    }
    if (preg_match('/Incomplete: (\\d+)/', $log, $m)) {
        $summary['incomplete'] += (int) $m[1];
    }
    if ($exitCode !== 0) {
        $allOk = false;
    }
    echo "PASS {$pass} exit={$exitCode}\n";
    echo $log . "\n";
}

echo "RUNNER_SUMMARY " . json_encode($summary) . " ok=" . ($allOk ? '1' : '0') . " php={$version}\n";
exit($allOk ? 0 : 1);

function downloadFile($url, $dest)
{
    $ctx = stream_context_create(array('http' => array('follow_location' => 1)));
    $data = file_get_contents($url, false, $ctx);
    if ($data === false) {
        fwrite(STDERR, "Download failed: {$url}\n");
        exit(10);
    }
    file_put_contents($dest, $data);
}

function writePhpunitXml($path, array $absoluteFiles)
{
    $filesXml = '';
    foreach ($absoluteFiles as $file) {
        $filesXml .= '            <file>' . htmlspecialchars($file, ENT_XML1) . '</file>' . "\n";
    }
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<phpunit bootstrap="/app/tests/php/bootstrap.php" colors="true"'
        . ' beStrictAboutTestsThatDoNotTestAnything="true"'
        . ' beStrictAboutOutputDuringTests="true"'
        . ' beStrictAboutChangesToGlobalState="true"'
        . ' beStrictAboutTodoAnnotatedTests="true">' . "\n"
        . '    <testsuites>' . "\n"
        . '        <testsuite name="module-tests">' . "\n"
        . $filesXml
        . '        </testsuite>' . "\n"
        . '    </testsuites>' . "\n"
        . '</phpunit>' . "\n";
    file_put_contents($path, $xml);
}
PHP;

    return str_replace(
        array('EXPECTED_VERSION', 'PHPUNIT_VERSION', 'PHPUNIT_SHA256'),
        array(
            $expectedVersion,
            $phpunit,
            $sha,
        ),
        $script
    );
}

function parseRunnerOutput($text)
{
    $summary = array(
        'ok' => false,
        'tests' => null,
        'assertions' => null,
        'failures' => null,
        'errors' => null,
        'skipped' => null,
        'incomplete' => null,
        'php' => null,
    );
    if (preg_match('/RUNNER_SUMMARY (\{.*\}) ok=(\d) php=([^\s]+)/', $text, $m)) {
        $data = json_decode($m[1], true);
        $summary['ok'] = $m[2] === '1';
        $summary['tests'] = $data['tests'];
        $summary['assertions'] = $data['assertions'];
        $summary['failures'] = $data['failures'];
        $summary['errors'] = $data['errors'];
        $summary['skipped'] = $data['skipped'];
        $summary['incomplete'] = $data['incomplete'];
        $summary['php'] = $m[3];
    }

    return $summary;
}

function writeArtifact(array $results, $repoRoot, $exitCode)
{
    $artifactDir = '/opt/cursor/artifacts';
    if (!is_dir($artifactDir)) {
        mkdir($artifactDir, 0755, true);
    }
    $branch = trim(shell_exec('cd ' . escapeshellarg($repoRoot) . ' && git rev-parse --abbrev-ref HEAD 2>/dev/null') ?: 'unknown');
    $sha = trim(shell_exec('cd ' . escapeshellarg($repoRoot) . ' && git rev-parse HEAD 2>/dev/null') ?: 'unknown');
    $date = gmdate('Y-m-d');

    $md = "# ps_socialfollow suite results\n\n";
    $md .= "- Repo: Training-Datasmith/ps_socialfollow\n";
    $md .= "- Date: {$date}\n";
    $md .= "- Branch: {$branch}\n";
    $md .= "- Commit: {$sha}\n";
    $md .= "- Command: `php tests/php/run-suite.php` (digest-pinned Docker images)\n\n";

    foreach ($results as $r) {
        $md .= "## {$r['label']}\n\n";
        $md .= "- PHP version: " . ($r['php'] ?: 'n/a') . "\n";
        $md .= "- Tests: " . ($r['tests'] !== null ? $r['tests'] : 'n/a') . "\n";
        $md .= "- Assertions: " . ($r['assertions'] !== null ? $r['assertions'] : 'n/a') . "\n";
        $md .= "- Failures: " . ($r['failures'] !== null ? $r['failures'] : 'n/a') . "\n";
        $md .= "- Errors: " . ($r['errors'] !== null ? $r['errors'] : 'n/a') . "\n";
        $md .= "- Skipped: " . ($r['skipped'] !== null ? $r['skipped'] : 'n/a') . "\n";
        $md .= "- Incomplete: " . ($r['incomplete'] !== null ? $r['incomplete'] : 'n/a') . "\n";
        $md .= "- Docker exit: {$r['exitCode']}\n";
        $md .= "- OK: " . ($r['ok'] ? 'yes' : 'no') . "\n\n";
        if (!empty($r['raw'])) {
            $md .= "<details><summary>Log excerpt</summary>\n\n```\n" . substr($r['raw'], -8000) . "\n```\n</details>\n\n";
        }
    }

    file_put_contents($artifactDir . '/suite-results.md', $md);
}
