<?php

abstract class ModuleTestCase extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        PsSocialfollowTestHarness::resetAll();
    }

    protected function tearDown()
    {
        PsSocialfollowTestHarness::resetAll();
    }
}
