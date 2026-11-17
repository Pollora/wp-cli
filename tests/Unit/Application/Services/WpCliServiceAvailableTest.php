<?php

declare(strict_types=1);

use Pollora\WpCli\Application\Services\WpCliService;
use Pollora\WpCli\Domain\Contracts\WpCliAdapterInterface;
use Psr\Log\LoggerInterface;

it('logs the failure of a registration with the command name, then the reason', function (): void {
    $adapter = Mockery::mock(WpCliAdapterInterface::class);
    $adapter->shouldReceive('isAvailable')->andReturn(true);
    $adapter->shouldReceive('addCommand')->andThrow(new RuntimeException('handler is broken'));

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->once()->with('Failed to register WP CLI command demo run: handler is broken');

    (new WpCliService($adapter, $logger))->register('demo run', 'Handler');
});

it('keeps a command whose registration failed, and survives without a logger', function (): void {
    $adapter = Mockery::mock(WpCliAdapterInterface::class);
    $adapter->shouldReceive('isAvailable')->andReturn(true);
    $adapter->shouldReceive('addCommand')->andThrow(new RuntimeException('handler is broken'));

    $service = new WpCliService($adapter);
    $service->register('demo', 'Handler');

    expect($service->hasCommand('demo'))->toBeTrue();
});

it('registers a command with WP-CLI at once through the adapter, with its arguments', function (): void {
    $adapter = Mockery::mock(WpCliAdapterInterface::class);
    $adapter->shouldReceive('isAvailable')->andReturn(true);
    $adapter->shouldReceive('addCommand')->once()->with('demo', 'Handler', ['when' => 'after_wp_load']);

    (new WpCliService($adapter))->register('demo', 'Handler', 'Demo', 5, ['when' => 'after_wp_load']);
});

it('registers every stored command again when initialised, each with its own arguments', function (): void {
    $calls = [];
    $adapter = Mockery::mock(WpCliAdapterInterface::class);
    $adapter->shouldReceive('isAvailable')->andReturn(true);
    $adapter->shouldReceive('addCommand')->andReturnUsing(function (string $name, mixed $handler, array $args) use (&$calls): void {
        $calls[] = [$name, $handler, $args];
    });

    $service = new WpCliService($adapter);
    $service->register('one', 'One', '', 0, ['a' => 1]);
    $service->register('two', 'Two');
    $calls = [];

    $service->initializeCommands();

    expect($calls)->toBe([['one', 'One', ['a' => 1]], ['two', 'Two', []]]);
});

it('does not touch the adapter commands when WP-CLI is unavailable', function (): void {
    $adapter = Mockery::mock(WpCliAdapterInterface::class);
    $adapter->shouldReceive('isAvailable')->andReturn(false);
    $adapter->shouldNotReceive('addCommand');

    $service = new WpCliService($adapter);
    $service->register('one', 'One');
    $service->initializeCommands();

    expect($service->hasCommand('one'))->toBeTrue();
});
