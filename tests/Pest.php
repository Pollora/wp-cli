<?php

declare(strict_types=1);

use Brain\Monkey;

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
