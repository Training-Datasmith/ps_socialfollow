<?php

class PrestashopRedirectException extends Exception
{
}

class Shop
{
    const CONTEXT_SHOP = 1;
    const CONTEXT_GROUP = 2;
    const CONTEXT_ALL = 4;

    private static $featureActive = false;
    private static $context = self::CONTEXT_SHOP;

    public static function reset()
    {
        self::$featureActive = false;
        self::$context = self::CONTEXT_SHOP;
    }

    public static function setFeatureActive($active)
    {
        self::$featureActive = (bool) $active;
    }

    public static function isFeatureActive()
    {
        return self::$featureActive;
    }

    public static function setContext($context)
    {
        self::$context = (int) $context;
    }

    public static function getContext()
    {
        return self::$context;
    }
}

class Language
{
    public static function getIDs()
    {
        return array(1, 2);
    }
}

class Configuration
{
    /** @var array<string, array<int, string>> */
    private static $values = array();

    /** @var array<string, bool> */
    private static $langKeys = array();

    /** @var array<int, array{key:string,values:mixed,html:bool}> */
    public static $updateCalls = array();

    /** @var array<string, bool> */
    private static $deleteResults = array();

    /** @var array<string> */
    public static $deletedKeys = array();

    public static function reset()
    {
        self::$values = array();
        self::$langKeys = array();
        self::$updateCalls = array();
        self::$deleteResults = array();
        self::$deletedKeys = array();
        self::$updateValueResults = array();
    }

    public static function seedNonLocalized($key, $value)
    {
        self::$values[$key] = array(0 => (string) $value);
        self::$langKeys[$key] = false;
    }

    public static function seedLocalized($key, array $perLang)
    {
        self::$values[$key] = array();
        foreach ($perLang as $lang => $value) {
            self::$values[$key][(int) $lang] = (string) $value;
        }
        self::$langKeys[$key] = true;
    }

    public static function setDeleteResult($key, $result)
    {
        self::$deleteResults[$key] = (bool) $result;
    }

    public static function isLangKey($key)
    {
        return isset(self::$langKeys[$key]) && self::$langKeys[$key];
    }

    public static function get($key, $idLang = null, $idShopGroup = null, $idShop = null, $default = false)
    {
        if (!isset(self::$values[$key])) {
            return $default;
        }

        if (self::isLangKey($key)) {
            $lang = (int) $idLang;
            if (isset(self::$values[$key][$lang])) {
                return self::$values[$key][$lang];
            }

            return $default;
        }

        if ($idLang !== null && (int) $idLang > 0 && isset(self::$values[$key][(int) $idLang])) {
            return self::$values[$key][(int) $idLang];
        }

        if (isset(self::$values[$key][0]) && self::$values[$key][0] !== '') {
            return self::$values[$key][0];
        }

        $hasLocalized = false;
        foreach (self::$values[$key] as $storedLang => $storedValue) {
            if ((int) $storedLang > 0 && $storedValue !== '') {
                $hasLocalized = true;
                break;
            }
        }
        if ($hasLocalized) {
            return $default;
        }

        if (isset(self::$values[$key][0])) {
            return self::$values[$key][0];
        }

        return $default;
    }

    /** @var array<string, bool> */
    public static $updateValueResults = array();

    public static function updateValue($key, $values, $html = false, $idShopGroup = null, $idShop = null)
    {
        if (isset(self::$updateValueResults[$key]) && self::$updateValueResults[$key] === false) {
            return false;
        }

        self::$updateCalls[] = array(
            'key' => $key,
            'values' => $values,
            'html' => (bool) $html,
        );

        if (!is_array($values)) {
            $values = array($values);
        }

        $hasLangIds = false;
        foreach (array_keys($values) as $langKey) {
            if ((int) $langKey > 0) {
                $hasLangIds = true;
                break;
            }
        }

        if (!isset(self::$values[$key])) {
            self::$values[$key] = array();
        }

        foreach ($values as $lang => $value) {
            self::$values[$key][(int) $lang] = (string) $value;
        }

        if ($hasLangIds && !self::isLangKey($key)) {
            // Legacy classification unchanged for the remainder of the request.
        }

        return true;
    }

    public static function deleteByName($key)
    {
        self::$deletedKeys[] = $key;
        if (isset(self::$deleteResults[$key]) && self::$deleteResults[$key] === false) {
            return false;
        }
        unset(self::$values[$key], self::$langKeys[$key]);

        return true;
    }
}

class Tools
{
    /** @var array<string, string> */
    private static $post = array();

    public static function reset()
    {
        self::$post = array();
    }

    public static function setPost(array $post)
    {
        self::$post = $post;
    }

    public static function isSubmit($key)
    {
        return array_key_exists($key, self::$post);
    }

    public static function getValue($key, $default = '')
    {
        if (array_key_exists($key, self::$post)) {
            return self::$post[$key];
        }

        return $default;
    }

    public static function redirectAdmin($url)
    {
        TestState::$lastRedirect = $url;
        throw new PrestashopRedirectException($url);
    }
}

class TestState
{
    /** @var string|null */
    public static $lastRedirect;

    public static function reset()
    {
        self::$lastRedirect = null;
    }
}

class TestLink
{
    public function getAdminLink($controller, $withToken = true, array $params = array(), array $extra = array())
    {
        return $controller . '|' . ($withToken ? 'true' : 'false') . '|'
            . json_encode($params) . '|' . json_encode($extra);
    }
}

class TestAdminController
{
    public function getLanguages()
    {
        return array(
            array('id_lang' => 1, 'name' => 'English'),
            array('id_lang' => 2, 'name' => 'French'),
        );
    }

    public function registerStylesheet($id, $path)
    {
        TestFrontController::$stylesheets[] = array('id' => $id, 'path' => $path);
    }
}

class TestFrontController
{
    /** @var array<int, array{id:string,path:string}> */
    public static $stylesheets = array();

    public static function reset()
    {
        self::$stylesheets = array();
    }
}

class TestContext
{
    /** @var TestContext|null */
    private static $instance;

    /** @var TestLink */
    public $link;

    /** @var object */
    public $controller;

    /** @var object */
    public $language;

    public function __construct()
    {
        $this->link = new TestLink();
        $this->controller = new TestAdminController();
        $this->language = new stdClass();
        $this->language->id = '1';
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset()
    {
        self::$instance = null;
    }
}

class TestSmarty
{
    /** @var array<string, mixed>|null */
    public static $lastAssign;

    public static function reset()
    {
        self::$lastAssign = null;
    }
}

class HelperForm
{
    public $show_toolbar = true;
    public $table;
    public $submit_action;
    public $currentIndex;
    public $tpl_vars = array();
    public $languages = array();
    public $default_form_language;

    public function generateForm(array $forms)
    {
        $payload = array(
            'forms' => $forms,
            'default_form_language' => $this->default_form_language,
            'languages' => $this->languages,
            'fields_value' => isset($this->tpl_vars['fields_value']) ? $this->tpl_vars['fields_value'] : array(),
            'show_toolbar' => $this->show_toolbar,
            'submit_action' => $this->submit_action,
        );

        return 'HELPER_FORM:' . serialize($payload);
    }
}

class Module
{
    public $name;
    public $tab;
    public $author;
    public $version;
    public $bootstrap;
    public $displayName;
    public $description;
    public $ps_versions_compliancy;
    public $table = 'module';
    public $smarty;

    /** @var TestContext */
    public $context;

    private static $parentInstallResult = true;
    private static $parentUninstallResult = true;

    /** @var array<int, string> */
    public static $registeredHooks = array();

    /** @var array<int, bool> */
    public static $registerHookResults = array();

    /** @var bool */
    public static $isCached = false;

    /** @var array<string, mixed>|null */
    public static $assignPayload;

    /** @var array<int, array{template:string,cache_id:?string,compile_id:?string}> */
    public static $clearCacheCalls = array();

    public static $clearCacheReturn = 1;

    public static function reset()
    {
        self::$parentInstallResult = true;
        self::$parentUninstallResult = true;
        self::$registeredHooks = array();
        self::$registerHookResults = array();
        self::$isCached = false;
        self::$assignPayload = null;
        self::$clearCacheCalls = array();
        self::$clearCacheReturn = 1;
        TestSmarty::reset();
        TestFrontController::reset();
    }

    public static function setParentInstallResult($result)
    {
        self::$parentInstallResult = (bool) $result;
    }

    public static function setParentUninstallResult($result)
    {
        self::$parentUninstallResult = (bool) $result;
    }

    public function __construct()
    {
        $this->context = TestContext::getInstance();
        $this->smarty = new TestSmartyBridge();
    }

    public function install()
    {
        return self::$parentInstallResult;
    }

    public function uninstall()
    {
        return self::$parentUninstallResult;
    }

    public function registerHook($hook)
    {
        $index = count(self::$registeredHooks);
        self::$registeredHooks[] = $hook;
        if (isset(self::$registerHookResults[$index])) {
            return (bool) self::$registerHookResults[$index];
        }

        return true;
    }

    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        return htmlspecialchars($id, ENT_NOQUOTES);
    }

    public function displayError($error)
    {
        return is_array($error) ? implode('|', $error) : (string) $error;
    }

    protected function getCacheId($name = null)
    {
        return 'cache:' . ($name !== null ? $name : $this->name);
    }

    public function isCached($template, $cache_id = null, $compile_id = null)
    {
        return self::$isCached;
    }

    public function fetch($templatePath, $cache_id = null, $compile_id = null)
    {
        return 'FETCH:' . $templatePath . '|' . $cache_id;
    }

    protected function _clearCache($template, $cache_id = null, $compile_id = null)
    {
        self::$clearCacheCalls[] = array(
            'template' => $template,
            'cache_id' => $cache_id,
            'compile_id' => $compile_id,
        );

        return self::$clearCacheReturn;
    }
}

class TestSmartyBridge
{
    public function assign($vars)
    {
        Module::$assignPayload = $vars;
    }
}

class PsSocialfollowTestHarness
{
    public static function resetAll()
    {
        Configuration::reset();
        Tools::reset();
        Shop::reset();
        TestState::reset();
        TestContext::reset();
        Module::reset();
        Configuration::seedNonLocalized('PS_LANG_DEFAULT', '1');
    }

    /**
     * @return TestPs_Socialfollow
     */
    public static function module()
    {
        return new TestPs_Socialfollow();
    }

    public static function repoRoot()
    {
        return dirname(dirname(dirname(__DIR__)));
    }

    public static function setContextLanguageId($id)
    {
        $ctx = TestContext::getInstance();
        $ctx->language->id = (string) $id;
    }

    public static function fillValidUrlsForAllNetworks()
    {
        $post = array('submitModule' => '1');
        foreach (Ps_Socialfollow::SOCIAL_NETWORKS as $social) {
            foreach (Language::getIDs() as $idLang) {
                $post["BLOCKSOCIAL_{$social}_{$idLang}"] = 'https://example.com/' . strtolower($social);
            }
        }
        Tools::setPost($post);
    }
}
