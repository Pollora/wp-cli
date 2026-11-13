<?php

declare(strict_types=1);

use Pollora\WpCli\Application\Services\WpCliService;
use Pollora\WpCli\Domain\Contracts\WpCliAdapterInterface;
use Psr\Log\LoggerInterface;

it('hands a command to the adapter as soon as WP-CLI is available', function (): void {
    $adapter = Mockery::mock(WpCliAdapterInterface::class);
    $adapter->shouldReceive('isAvailable')->andReturn(true);
    $adapter->shouldReceive('addCommand')->once()->with('pollora sync', 'SyncCommand', ['when' => 'after_wp_load']);

    (new WpCliService($adapter))->register('pollora sync', 'SyncCommand', 'Sync', 0, ['when' => 'after_wp_load']);
});

it('does not touch the adapter registration while WP-CLI is unavailable', function (): void {
    $adapter = Mockery::mock(WpCliAdapterInterface::class);
    $adapter->shouldReceive('isAvailable')->andReturn(false);
    $adapter->shouldNotReceive('addCommand');

    $service = new WpCliService($adapter);
    $service->register('pollora sync', 'SyncCommand');
    $service->initializeCommands();

    expect($service->hasCommand('pollora sync'))->toBeTrue();
});

it('logs a command the adapter refuses and keeps it registered', function (): void {
    $adapter = Mockery::mock(WpCliAdapterInterface::class);
    $adapter->shouldReceive('isAvailable')->andReturn(true);
    $adapter->shouldReceive('addCommand')->andThrow(new InvalidArgumentException('bad handler'));

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->once()->with(Mockery::on(
        fn (string $message): bool => str_contains($message, 'pollora broken') && str_contains($message, 'bad handler')
    ));

    $service = new WpCliService($adapter, $logger);
    $service->register('pollora broken', 'Missing');

    expect($service->hasCommand('pollora broken'))->toBeTrue();
});

it('swallows a refused command when no logger is given', function (): void {
    $adapter = Mockery::mock(WpCliAdapterInterface::class);
    $adapter->shouldReceive('isAvailable')->andReturn(true);
    $adapter->shouldReceive('addCommand')->andThrow(new RuntimeException('boom'));

    $service = new WpCliService($adapter);
    $service->register('pollora broken', 'Missing');

    expect($service->getCommand('pollora broken'))->not->toBeNull();
});

it('registers every stored command again with its arguments when initialised', function (): void {
    $adapter = Mockery::mock(WpCliAdapterInterface::class);
    $adapter->shouldReceive('isAvailable')->andReturn(false, false, true);
    $adapter->shouldReceive('addCommand')->once()->with('one', 'One', ['synopsis' => 'x']);
    $adapter->shouldReceive('addCommand')->once()->with('two', 'Two', []);

    $service = new WpCliService($adapter);
    $service->register('one', 'One', '', 0, ['synopsis' => 'x']);
    $service->register('two', 'Two');
    $service->initializeCommands();
});
