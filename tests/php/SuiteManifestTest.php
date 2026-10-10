<?php

class SuiteManifestTest extends ModuleTestCase
{
    public function testTestFilesMatchExpectedManifest()
    {
        $expected = require __DIR__ . '/expected-suite.php';
        $files = glob(__DIR__ . '/*Test.php');
        $this->assertNotFalse($files);

        $names = array();
        foreach ($files as $path) {
            $names[] = basename($path);
        }
        sort($names);

        $this->assertSame($expected['files'], $names);
        $this->assertSame($expected['testCount'], $this->countTestMethods());
    }

    private function countTestMethods()
    {
        $count = 0;
        foreach (glob(__DIR__ . '/*Test.php') as $path) {
            $class = basename($path, '.php');
            if (!class_exists($class)) {
                require $path;
            }
            $ref = new ReflectionClass($class);
            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (strpos($method->getName(), 'test') === 0) {
                    ++$count;
                }
            }
        }

        return $count;
    }
}
