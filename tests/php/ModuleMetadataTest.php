<?php

class ModuleMetadataTest extends ModuleTestCase
{
    public function testConstructorPublishesModuleIdentity()
    {
        $module = PsSocialfollowTestHarness::module();
        $this->assertSame('ps_socialfollow', $module->name);
        $this->assertSame('advertising_marketing', $module->tab);
        $this->assertSame('PrestaShop', $module->author);
        $this->assertSame('2.3.3', $module->version);
        $this->assertTrue($module->bootstrap);
        $this->assertSame('Social media follow links', $module->displayName);
        $this->assertContains('Facebook', $module->description);
        $this->assertSame('8.1.0', $module->ps_versions_compliancy['min']);
        $this->assertSame('8.1.7', $module->ps_versions_compliancy['max']);

        $ref = new ReflectionClass('Ps_Socialfollow');
        $prop = $ref->getProperty('templateFile');
        $prop->setAccessible(true);
        $template = $prop->getValue($module);
        $this->assertSame('module:ps_socialfollow/ps_socialfollow.tpl', $template);
        $this->assertFileExists(PsSocialfollowTestHarness::repoRoot() . '/ps_socialfollow.tpl');
    }

    public function testSocialNetworkConstantIsTheTenNetworkList()
    {
        $this->assertSame(
            array(
                'FACEBOOK',
                'TWITTER',
                'RSS',
                'YOUTUBE',
                'PINTEREST',
                'VIMEO',
                'INSTAGRAM',
                'LINKEDIN',
                'TIKTOK',
                'DISCORD',
            ),
            Ps_Socialfollow::SOCIAL_NETWORKS
        );
    }

    public function testWidgetMethodsMatchThePrestaShopInterface()
    {
        $ref = new ReflectionClass('Ps_Socialfollow');
        $this->assertTrue($ref->implementsInterface('PrestaShop\\PrestaShop\\Core\\Module\\WidgetInterface'));

        $render = $ref->getMethod('renderWidget');
        $this->assertTrue($render->getParameters()[1]->isArray());

        $vars = $ref->getMethod('getWidgetVariables');
        $this->assertTrue($vars->getParameters()[1]->isArray());
    }

    public function testConfigXmlMatchesModuleVersionAndName()
    {
        $xml = simplexml_load_file(PsSocialfollowTestHarness::repoRoot() . '/config.xml');
        $this->assertNotFalse($xml);
        $this->assertSame('ps_socialfollow', (string) $xml->name);
        $this->assertSame('2.3.3', (string) $xml->version);
        $this->assertSame('Social media follow links', (string) $xml->displayName);
        $this->assertSame('PrestaShop', (string) $xml->author);
        $this->assertSame('1', (string) $xml->is_configurable);
        $this->assertSame('1', (string) $xml->need_instance);
    }

    public function testTemplateBindsSocialLinkFields()
    {
        $tpl = file_get_contents(PsSocialfollowTestHarness::repoRoot() . '/ps_socialfollow.tpl');
        $this->assertContains('ps_social_follow', $tpl);
        $this->assertContains('$social_links', $tpl);
        $this->assertContains('$social_link.class', $tpl);
        $this->assertContains('$social_link.url', $tpl);
        $this->assertContains('$social_link.label', $tpl);
        $this->assertContains('Modules.Socialfollow.Shop', $tpl);
    }

    public function testDiscordStylesheetAndIconExist()
    {
        $css = file_get_contents(PsSocialfollowTestHarness::repoRoot() . '/views/css/ps_socialfollow.css');
        $this->assertContains('.ps-socialfollow-discord', $css);
        $this->assertContains('discord.svg', $css);
        $svgPath = PsSocialfollowTestHarness::repoRoot() . '/assets/icon/discord.svg';
        $this->assertFileExists($svgPath);
        $svg = file_get_contents($svgPath);
        $this->assertNotSame('', trim($svg));
        $this->assertContains('<svg', $svg);
    }
}
