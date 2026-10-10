<?php

class ConfigurationFormTest extends ModuleTestCase
{
    public function testLegacyScalarUrlIsCopiedToEveryLanguageOnce()
    {
        Configuration::seedNonLocalized('BLOCKSOCIAL_FACEBOOK', 'https://facebook.example/old');
        $module = PsSocialfollowTestHarness::module();
        $values = $module->exposeGetConfigFieldsValues();

        $this->assertSame('https://facebook.example/old', $values['BLOCKSOCIAL_FACEBOOK'][1]);
        $this->assertSame('https://facebook.example/old', $values['BLOCKSOCIAL_FACEBOOK'][2]);

        $migration = $this->findUpdateCall('BLOCKSOCIAL_FACEBOOK');
        $this->assertNotNull($migration);
        $this->assertSame(
            array(1 => 'https://facebook.example/old', 2 => 'https://facebook.example/old'),
            $migration['values']
        );
    }

    public function testExistingLocalizedValuesAreNotRewritten()
    {
        Configuration::seedLocalized('BLOCKSOCIAL_FACEBOOK', array(
            1 => 'https://facebook.example/en',
            2 => 'https://facebook.example/fr',
        ));
        $before = count(Configuration::$updateCalls);
        $module = PsSocialfollowTestHarness::module();
        $values = $module->exposeGetConfigFieldsValues();
        $this->assertSame(count(Configuration::$updateCalls), $before);
        $this->assertSame('https://facebook.example/en', $values['BLOCKSOCIAL_FACEBOOK'][1]);
        $this->assertSame('https://facebook.example/fr', $values['BLOCKSOCIAL_FACEBOOK'][2]);
    }

    public function testGetContentRendersMultilingualFormWhenMultistoreIsOff()
    {
        Shop::setFeatureActive(false);
        PsSocialfollowTestHarness::setContextLanguageId(2);
        $module = PsSocialfollowTestHarness::module();
        $html = $module->getContent();
        $this->assertContains('HELPER_FORM:', $html);
        $payload = unserialize(substr($html, strlen('HELPER_FORM:')));
        $this->assertFalse($payload['show_toolbar']);
        $this->assertSame('submitModule', $payload['submit_action']);
        $this->assertSame(2, $payload['default_form_language']);

        $names = array();
        foreach ($payload['forms'][0]['form']['input'] as $input) {
            $names[] = $input['name'];
            $this->assertSame('text', $input['type']);
            $this->assertTrue($input['lang']);
        }
        $expected = array();
        foreach (Ps_Socialfollow::SOCIAL_NETWORKS as $social) {
            $expected[] = 'BLOCKSOCIAL_' . $social;
        }
        sort($expected);
        sort($names);
        $this->assertSame($expected, $names);
    }

    public function testGetContentRendersFormInSingleShopContext()
    {
        Shop::setFeatureActive(true);
        Shop::setContext(Shop::CONTEXT_SHOP);
        Configuration::seedLocalized('BLOCKSOCIAL_TWITTER', array(1 => 'https://twitter.example/a'));
        $module = PsSocialfollowTestHarness::module();
        $html = $module->getContent();
        $this->assertContains('HELPER_FORM:', $html);
        $payload = unserialize(substr($html, strlen('HELPER_FORM:')));
        $this->assertSame('https://twitter.example/a', $payload['fields_value']['BLOCKSOCIAL_TWITTER'][1]);
    }

    public function testSuccessfulSaveRedirectsAndClearsTheModuleTemplateCache()
    {
        PsSocialfollowTestHarness::fillValidUrlsForAllNetworks();
        $module = PsSocialfollowTestHarness::module();
        try {
            $module->getContent();
            $this->fail('Expected redirect');
        } catch (PrestashopRedirectException $e) {
            $this->assertContains('AdminModules', TestState::$lastRedirect);
            $this->assertContains('ps_socialfollow', TestState::$lastRedirect);
            $this->assertContains('"conf":4', TestState::$lastRedirect);
        }
        $this->assertCount(1, Module::$clearCacheCalls);
        $this->assertSame('module:ps_socialfollow/ps_socialfollow.tpl', Module::$clearCacheCalls[0]['template']);
        $this->assertGreaterThan(0, count(Configuration::$updateCalls));
    }

    public function testInvalidUrlIsReportedAndNothingIsStored()
    {
        Tools::setPost(array(
            'submitModule' => '1',
            'BLOCKSOCIAL_FACEBOOK_1' => 'notaurl',
            'BLOCKSOCIAL_FACEBOOK_2' => 'notaurl',
        ));
        $module = PsSocialfollowTestHarness::module();
        $html = $module->getContent();
        $this->assertContains('notaurl', $html);
        $this->assertContains('Invalid URL', $html);
        $this->assertNull(TestState::$lastRedirect);
        $this->assertSame(array(), Configuration::$updateCalls);
    }

    public function testTrimAndDefaultLanguageFillEmptyTranslations()
    {
        Tools::setPost(array(
            'submitModule' => '1',
            'BLOCKSOCIAL_FACEBOOK_1' => '  https://example.com/en  ',
            'BLOCKSOCIAL_FACEBOOK_2' => '   ',
        ));
        $module = PsSocialfollowTestHarness::module();
        try {
            $module->getContent();
        } catch (PrestashopRedirectException $e) {
        }
        $call = $this->findUpdateCall('BLOCKSOCIAL_FACEBOOK');
        $this->assertNotNull($call);
        $this->assertSame('https://example.com/en', $call['values'][1]);
        $this->assertSame('https://example.com/en', $call['values'][2]);
    }

    public function testDistinctLanguageUrlsAreStoredSeparately()
    {
        Tools::setPost(array(
            'submitModule' => '1',
            'BLOCKSOCIAL_FACEBOOK_1' => 'https://example.com/en',
            'BLOCKSOCIAL_FACEBOOK_2' => 'https://example.com/fr',
        ));
        $module = PsSocialfollowTestHarness::module();
        try {
            $module->getContent();
        } catch (PrestashopRedirectException $e) {
        }
        $call = $this->findUpdateCall('BLOCKSOCIAL_FACEBOOK');
        $this->assertSame('https://example.com/en', $call['values'][1]);
        $this->assertSame('https://example.com/fr', $call['values'][2]);
    }

    public function testOneInvalidNetworkLeavesPreviousValuesUntouched()
    {
        Configuration::seedLocalized('BLOCKSOCIAL_TWITTER', array(1 => 'https://twitter.example/saved'));
        $before = count(Configuration::$updateCalls);
        Tools::setPost(array(
            'submitModule' => '1',
            'BLOCKSOCIAL_FACEBOOK_1' => 'notaurl',
            'BLOCKSOCIAL_FACEBOOK_2' => 'notaurl',
        ));
        $module = PsSocialfollowTestHarness::module();
        $module->getContent();
        $this->assertSame($before, count(Configuration::$updateCalls));
    }

    public function testFtpAndJavascriptUrlsAreRejected()
    {
        Tools::setPost(array(
            'submitModule' => '1',
            'BLOCKSOCIAL_FACEBOOK_1' => 'ftp://example.com/file',
            'BLOCKSOCIAL_FACEBOOK_2' => 'javascript:alert(1)',
        ));
        $module = PsSocialfollowTestHarness::module();
        $html = $module->getContent();
        $this->assertContains('ftp://example.com/file', $html);
        $this->assertSame(array(), Configuration::$updateCalls);
    }

    public function testHttpsUrlWithPathAndQueryIsStoredUnchanged()
    {
        $url = 'https://example.com/path?q=1';
        Tools::setPost(array(
            'submitModule' => '1',
            'BLOCKSOCIAL_FACEBOOK_1' => $url,
            'BLOCKSOCIAL_FACEBOOK_2' => $url,
        ));
        $module = PsSocialfollowTestHarness::module();
        try {
            $module->getContent();
        } catch (PrestashopRedirectException $e) {
        }
        $call = $this->findUpdateCall('BLOCKSOCIAL_FACEBOOK');
        $this->assertSame($url, $call['values'][1]);
        $this->assertSame($url, $call['values'][2]);
    }

    private function findUpdateCall($key)
    {
        $found = null;
        foreach (Configuration::$updateCalls as $call) {
            if ($call['key'] === $key) {
                $found = $call;
            }
        }

        return $found;
    }
}
