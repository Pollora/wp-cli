<?php

declare(strict_types=1);

use Pollora\WpCli\Application\Services\WpCliService;
use Pollora\WpCli\Domain\Contracts\WpCliServiceInterface;
use Pollora\WpCli\Infrastructure\Services\WpCliDiscovery;

it('is final and takes its substitutes through WpCliServiceInterface', function (): void {
    $service = new ReflectionClass(WpCliService::class);
    $parameter = (new ReflectionMethod(WpCliDiscovery::class, '__construct'))->getParameters()[0];

    expect($service->isFinal())->toBeTrue()
        ->and($service->implementsInterface(WpCliServiceInterface::class))->toBeTrue()
        ->and((string) $parameter->getType())->toBe(WpCliServiceInterface::class);
});

it('declares in the contract every public method of the service', function (): void {
    $names = static fn (string $class): array => array_values(array_filter(
        array_map(static fn (ReflectionMethod $method): string => $method->getName(), (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC)),
        static fn (string $name): bool => $name !== '__construct',
    ));

    expect($names(WpCliServiceInterface::class))->toEqualCanonicalizing($names(WpCliService::class));
});
