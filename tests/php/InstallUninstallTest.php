<?php

class InstallUninstallTest extends ModuleTestCase
{
    public function testInstallWritesEmptyScalarForEveryNetworkAndRegistersHooks()
    {
        $module = PsSocialfollowTestHarness::module();
        $this->assertTrue($module->install());

        $expectedKeys = array();
        foreach (Ps_Socialfollow::SOCIAL_NETWORKS as $social) {
            $expectedKeys[] = 'BLOCKSOCIAL_' . $social;
        }
        $writtenKeys = array();
        foreach (Configuration::$updateCalls as $call) {
            $writtenKeys[] = $call['key'];
            $this->assertSame('', is_array($call['values']) ? $call['values'][0] : $call['values']);
        }
        sort($expectedKeys);
        sort($writtenKeys);
        $this->assertSame($expectedKeys, $writtenKeys);

        $hooks = Module::$registeredHooks;
        sort($hooks);
        $this->assertSame(array('actionFrontControllerSetMedia', 'displayFooter'), $hooks);
    }

    public function testInstallStopsWhenParentInstallFails()
    {
        Module::setParentInstallResult(false);
        $module = PsSocialfollowTestHarness::module();
        $this->assertFalse($module->install());
        $this->assertSame(array(), Configuration::$updateCalls);
        $this->assertSame(array(), Module::$registeredHooks);
    }

    public function testInstallStopsWhenAConfigurationWriteFails()
    {
        Configuration::$updateValueResults['BLOCKSOCIAL_DISCORD'] = false;
        $module = PsSocialfollowTestHarness::module();
        $this->assertFalse($module->install());
        $this->assertNotContains('displayFooter', Module::$registeredHooks);
        $this->assertGreaterThan(0, count(Configuration::$updateCalls));
    }

    public function testInstallStopsWhenTheFirstHookFails()
    {
        Module::$registerHookResults[0] = false;
        $module = PsSocialfollowTestHarness::module();
        $this->assertFalse($module->install());
        $this->assertSame(array('actionFrontControllerSetMedia'), Module::$registeredHooks);
    }

    public function testUninstallDeletesEveryNetworkThenParent()
    {
        $module = PsSocialfollowTestHarness::module();
        $this->assertTrue($module->uninstall());

        $expected = array();
        foreach (Ps_Socialfollow::SOCIAL_NETWORKS as $social) {
            $expected[] = 'BLOCKSOCIAL_' . $social;
        }
        sort($expected);
        $deleted = Configuration::$deletedKeys;
        sort($deleted);
        $this->assertSame($expected, $deleted);
    }

    public function testUninstallStopsWhenTheFirstDeleteFails()
    {
        Configuration::setDeleteResult('BLOCKSOCIAL_FACEBOOK', false);
        $module = PsSocialfollowTestHarness::module();
        $this->assertFalse($module->uninstall());
        $this->assertSame(array('BLOCKSOCIAL_FACEBOOK'), Configuration::$deletedKeys);
    }

    public function testUpgrade230RegistersMediaHookAndSeedsDiscord()
    {
        $module = PsSocialfollowTestHarness::module();
        $this->assertTrue(upgrade_module_2_3_0($module));
        $this->assertSame(array('actionFrontControllerSetMedia'), Module::$registeredHooks);
        $foundDiscord = false;
        foreach (Configuration::$updateCalls as $call) {
            if ($call['key'] === 'BLOCKSOCIAL_DISCORD') {
                $foundDiscord = true;
                $this->assertSame('', is_array($call['values']) ? $call['values'][0] : $call['values']);
            }
        }
        $this->assertTrue($foundDiscord);
    }

    public function testUpgrade230StopsWhenTheHookCannotBeRegistered()
    {
        Module::$registerHookResults[0] = false;
        $module = PsSocialfollowTestHarness::module();
        $this->assertFalse(upgrade_module_2_3_0($module));
        $this->assertSame(array(), Configuration::$updateCalls);
    }

    public function testUpgrade230StopsWhenDiscordCannotBeSeeded()
    {
        Configuration::$updateValueResults['BLOCKSOCIAL_DISCORD'] = false;
        $module = PsSocialfollowTestHarness::module();
        $this->assertFalse(upgrade_module_2_3_0($module));
        $this->assertSame(array('actionFrontControllerSetMedia'), Module::$registeredHooks);
    }
}
