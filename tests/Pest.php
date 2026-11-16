<?php

declare(strict_types=1);

use Brain\Monkey;

// PHPUnit's separate-process runner needs this constant to find the autoloader;
// the Pest binary does not define it.
if (! defined('PHPUNIT_COMPOSER_INSTALL')) {
    define('PHPUNIT_COMPOSER_INSTALL', dirname(__DIR__).'/vendor/autoload.php');
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| No framework to boot. WordPress functions are stubbed with Brain Monkey,
| and Mockery's expectations count as assertions.
|
*/

pest()->beforeEach(function (): void {
    Monkey\setUp();
})->afterEach(function (): void {
    if ($container = Mockery::getContainer()) {
        $this->addToAssertionCount($container->mockery_getExpectationCount());
    }

    Monkey\tearDown();
})->in('Unit');
