<?php

class WidgetTest extends ModuleTestCase
{
    public function testWidgetVariablesAreEmptyWhenNothingIsConfigured()
    {
        $module = PsSocialfollowTestHarness::module();
        $vars = $module->getWidgetVariables('displayFooter', array());
        $this->assertInternalType('array', $vars);
        $this->assertArrayHasKey('social_links', $vars);
        $this->assertCount(0, $vars['social_links']);
    }

    public function testWidgetVariablesExposeAllConfiguredNetworksForCurrentLanguage()
    {
        foreach (Ps_Socialfollow::SOCIAL_NETWORKS as $social) {
            $key = 'BLOCKSOCIAL_' . $social;
            Configuration::seedLocalized($key, array(
                1 => 'https://example.com/' . strtolower($social),
                2 => 'https://example.com/' . strtolower($social) . '-fr',
            ));
        }
        $module = PsSocialfollowTestHarness::module();
        $vars = $module->getWidgetVariables(null, array());
        $expectedSlugs = array(
            'facebook', 'twitter', 'rss', 'youtube', 'pinterest', 'vimeo', 'instagram', 'linkedin', 'tiktok', 'discord',
        );
        $actualSlugs = array_keys($vars['social_links']);
        sort($expectedSlugs);
        sort($actualSlugs);
        $this->assertSame($expectedSlugs, $actualSlugs);
        foreach ($vars['social_links'] as $slug => $link) {
            $this->assertSame('https://example.com/' . $slug, $link['url']);
            $this->assertNotEmpty($link['label']);
            if ($slug === 'discord') {
                $this->assertSame('ps-socialfollow-discord', $link['class']);
            } else {
                $this->assertSame($slug, $link['class']);
            }
        }
    }

    public function testWidgetVariablesFollowTheContextLanguage()
    {
        Configuration::seedLocalized('BLOCKSOCIAL_TWITTER', array(
            1 => 'https://twitter.example/en',
            2 => 'https://twitter.example/fr',
        ));
        PsSocialfollowTestHarness::setContextLanguageId(2);
        $module = PsSocialfollowTestHarness::module();
        $vars = $module->getWidgetVariables(null, array());
        $this->assertSame('https://twitter.example/fr', $vars['social_links']['twitter']['url']);
    }

    public function testWidgetVariablesOmitEmptyLinksAndKeepTheOthers()
    {
        Configuration::seedLocalized('BLOCKSOCIAL_FACEBOOK', array(1 => ''));
        Configuration::seedLocalized('BLOCKSOCIAL_TWITTER', array(1 => 'https://twitter.example/a'));
        $module = PsSocialfollowTestHarness::module();
        $vars = $module->getWidgetVariables(null, array());
        $this->assertArrayNotHasKey('facebook', $vars['social_links']);
        $this->assertArrayHasKey('twitter', $vars['social_links']);
    }

    public function testWidgetVariablesIgnoreHookArguments()
    {
        Configuration::seedLocalized('BLOCKSOCIAL_FACEBOOK', array(1 => 'https://facebook.example/real'));
        $module = PsSocialfollowTestHarness::module();
        $vars = $module->getWidgetVariables('displayFooter', array('social_links' => array('evil' => true)));
        $this->assertSame('https://facebook.example/real', $vars['social_links']['facebook']['url']);
    }

    public function testRenderWidgetAssignsVariablesWhenTheTemplateIsNotCached()
    {
        Configuration::seedLocalized('BLOCKSOCIAL_FACEBOOK', array(1 => 'https://facebook.example/a'));
        Module::$isCached = false;
        $module = PsSocialfollowTestHarness::module();
        $html = $module->renderWidget('displayFooter', array());
        $this->assertInternalType('array', Module::$assignPayload);
        $this->assertArrayHasKey('social_links', Module::$assignPayload);
        $this->assertContains('module:ps_socialfollow/ps_socialfollow.tpl', $html);
        $this->assertContains('cache:ps_socialfollow', $html);
    }

    public function testRenderWidgetSkipsAssignWhenTheTemplateIsCached()
    {
        Module::$isCached = true;
        $module = PsSocialfollowTestHarness::module();
        $html = $module->renderWidget(null, array());
        $this->assertNull(Module::$assignPayload);
        $this->assertContains('module:ps_socialfollow/ps_socialfollow.tpl', $html);
        $this->assertContains('cache:ps_socialfollow', $html);
    }

    public function testFrontControllerHookRegistersTheModuleStylesheet()
    {
        $module = PsSocialfollowTestHarness::module();
        $module->hookActionFrontControllerSetMedia();
        $this->assertCount(1, TestFrontController::$stylesheets);
        $this->assertSame('ps_socialfollow_icons', TestFrontController::$stylesheets[0]['id']);
        $this->assertSame('/modules/ps_socialfollow/views/css/ps_socialfollow.css', TestFrontController::$stylesheets[0]['path']);
    }
}
