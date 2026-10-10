<?php

class DirectoryGuardTest extends ModuleTestCase
{
    public function testRootIndexExitsWithoutOutput()
    {
        $result = PsSocialfollowChildProcess::includeGuardFile(
            PsSocialfollowTestHarness::repoRoot() . '/index.php'
        );
        PsSocialfollowChildProcess::assertCleanGuardExit($result);
    }

    public function testTranslationsIndexExitsWithoutOutput()
    {
        $result = PsSocialfollowChildProcess::includeGuardFile(
            PsSocialfollowTestHarness::repoRoot() . '/translations/index.php'
        );
        PsSocialfollowChildProcess::assertCleanGuardExit($result);
    }

    public function testUpgradeIndexExitsWithoutOutput()
    {
        $result = PsSocialfollowChildProcess::includeGuardFile(
            PsSocialfollowTestHarness::repoRoot() . '/upgrade/index.php'
        );
        PsSocialfollowChildProcess::assertCleanGuardExit($result);
    }

    public function testTestsIndexExitsWithoutOutput()
    {
        $result = PsSocialfollowChildProcess::includeGuardFile(
            PsSocialfollowTestHarness::repoRoot() . '/tests/index.php'
        );
        PsSocialfollowChildProcess::assertCleanGuardExit($result);
    }

    public function testPhpstanIndexExitsWithoutOutput()
    {
        $result = PsSocialfollowChildProcess::includeGuardFile(
            PsSocialfollowTestHarness::repoRoot() . '/tests/php/phpstan/index.php'
        );
        PsSocialfollowChildProcess::assertCleanGuardExit($result);
    }

    public function testAssetsIconIndexExitsWithoutOutput()
    {
        $result = PsSocialfollowChildProcess::includeGuardFile(
            PsSocialfollowTestHarness::repoRoot() . '/assets/icon/index.php'
        );
        PsSocialfollowChildProcess::assertCleanGuardExit($result);
    }

    public function testTestsPhpIndexExitsWithoutOutput()
    {
        $result = PsSocialfollowChildProcess::includeGuardFile(
            __DIR__ . '/index.php'
        );
        PsSocialfollowChildProcess::assertCleanGuardExit($result);
    }

    public function testModuleFileExitsWhenVersionConstantMissing()
    {
        $path = PsSocialfollowTestHarness::repoRoot() . '/ps_socialfollow.php';
        $result = PsSocialfollowChildProcess::run(
            'include ' . var_export($path, true) . '; echo "AFTER_INCLUDE";'
        );
        PsSocialfollowChildProcess::assertCleanGuardExit($result);
        $this->assertNotContains('AFTER_INCLUDE', $result['stdout'] . $result['stderr']);
    }

    public function testUpgradeScriptExitsWhenVersionConstantMissing()
    {
        $path = PsSocialfollowTestHarness::repoRoot() . '/upgrade/upgrade-2.3.0.php';
        $result = PsSocialfollowChildProcess::run(
            'include ' . var_export($path, true) . '; echo "AFTER_INCLUDE";'
        );
        PsSocialfollowChildProcess::assertCleanGuardExit($result);
        $this->assertNotContains('AFTER_INCLUDE', $result['stdout'] . $result['stderr']);
    }
}
