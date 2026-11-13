<?php

declare(strict_types=1);

use Pollora\Attributes\WpCli;
use Pollora\Attributes\WpCli\AfterInvoke;
use Pollora\Attributes\WpCli\BeforeInvoke;
use Pollora\Attributes\WpCli\Command;
use Pollora\Attributes\WpCli\IsDeferred;
use Pollora\Attributes\WpCli\Synopsis;
use Pollora\Attributes\WpCli\When;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Pollora\WpCli\Application\Services\WpCliService;
use Pollora\WpCli\Infrastructure\Services\WpCliDiscovery;
use Psr\Log\LoggerInterface;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredInterface;

#[WpCli('behaviour-options')]
#[Synopsis('<file> [--dry-run]')]
#[When('after_wp_load')]
#[IsDeferred(false)]
#[BeforeInvoke('before_cb')]
#[AfterInvoke('after_cb')]
final class OptionsBehaviourCommandFixture
{
    public function __invoke(array $arguments, array $options): void {}
}

#[WpCli('behaviour-suite')]
final class SuiteBehaviourCommandFixture
{
    /**
     * Import the rows.
     *
     * Reads every row of the file
     * and stores it.
     *
     * ## OPTIONS
     * <file>
     * : never reaches the long description
     */
    #[Command('import')]
    #[Synopsis('<file>')]
    public function importRows(array $arguments, array $options): void {}

    /**
     * Only a short description.
     */
    #[Command]
    protected function purgeOld(array $arguments, array $options): void {}

    #[Command('bare')]
    public function bare(array $arguments, array $options): void {}
}

#[WpCli('')]
final class NamelessBehaviourCommandFixture
{
    public function __invoke(array $arguments, array $options): void {}
}

#[WpCli('private-ctor')]
final class UninstantiableBehaviourCommandFixture
{
    private function __construct() {}
}

#[WpCli('inert')]
final class InertBehaviourCommandFixture
{
    public function helper(): void {}
}

abstract class AbstractBehaviourCommandFixture {}

final class PlainBehaviourClassFixture {}

#[WpCli('needs-input')]
final class NeedsInputBehaviourCommandFixture
{
    public function __construct(private readonly int $required) {}

    public function __invoke(array $arguments, array $options): void {}
}

/**
 * @return array<string, array{handler: mixed, args: array}>
 */
function behaviourRegistrations(string $class, ?LoggerInterface $logger = null): array
{
    $registered = [];
    $service = Mockery::mock(WpCliService::class);
    $service->shouldReceive('register')->andReturnUsing(
        function (string $name, mixed $handler, string $description, int $priority, array $args) use (&$registered): void {
            $registered[$name] = ['handler' => $handler, 'args' => $args];
        }
    );

    $discovery = new WpCliDiscovery($service, $logger);
    $discovery->discover(new DiscoveryLocation('', __DIR__), DiscoveredClass::fromReflection(new ReflectionClass($class)), new ReflectionCache);
    $discovery->apply();

    return $registered;
}

it('identifies itself as the wp_cli_commands discovery', function (): void {
    expect((new WpCliDiscovery(Mockery::mock(WpCliService::class)))->getIdentifier())->toBe('wp_cli_commands');
});

it('hands WP-CLI the synopsis, hook, deferral and invoke callbacks declared on the class', function (): void {
    $registered = behaviourRegistrations(OptionsBehaviourCommandFixture::class);

    expect($registered)->toHaveKey('behaviour-options')
        ->and($registered['behaviour-options']['args'])->toBe([
            'before_invoke' => 'before_cb',
            'after_invoke' => 'after_cb',
            'synopsis' => '<file> [--dry-run]',
            'when' => 'after_wp_load',
            'is_deferred' => false,
        ]);
});

it('registers each #[Command] method as a subcommand under the class command', function (): void {
    $registered = behaviourRegistrations(SuiteBehaviourCommandFixture::class);

    expect(array_keys($registered))->toBe([
        'behaviour-suite',
        'behaviour-suite import',
        'behaviour-suite purge-old',
        'behaviour-suite bare',
    ]);
});

it('hands a public subcommand to WP-CLI as [instance, method]', function (): void {
    $handler = behaviourRegistrations(SuiteBehaviourCommandFixture::class)['behaviour-suite import']['handler'];

    expect($handler)->toBeArray()
        ->and($handler[0])->toBeInstanceOf(SuiteBehaviourCommandFixture::class)
        ->and($handler[1])->toBe('importRows');
});

it('hands a non-public subcommand to WP-CLI as a closure', function (): void {
    $handler = behaviourRegistrations(SuiteBehaviourCommandFixture::class)['behaviour-suite purge-old']['handler'];

    expect($handler)->toBeInstanceOf(Closure::class);
});

it('takes the help of a subcommand from its docblock: short description, then long description up to the first tag or section', function (): void {
    $args = behaviourRegistrations(SuiteBehaviourCommandFixture::class)['behaviour-suite import']['args'];

    expect($args['shortdesc'])->toBe('Import the rows.')
        ->and($args['longdesc'])->toBe("Reads every row of the file\nand stores it.")
        ->and($args['synopsis'])->toBe('<file>');
});

it('gives no long description to a subcommand whose docblock has only a short one', function (): void {
    $args = behaviourRegistrations(SuiteBehaviourCommandFixture::class)['behaviour-suite purge-old']['args'];

    expect($args['shortdesc'])->toBe('Only a short description.')
        ->and($args)->not->toHaveKey('longdesc');
});

it('gives no help to a subcommand without a docblock', function (): void {
    $args = behaviourRegistrations(SuiteBehaviourCommandFixture::class)['behaviour-suite bare']['args'];

    expect($args)->not->toHaveKey('shortdesc')->not->toHaveKey('longdesc');
});

it('registers a class with neither __invoke nor #[Command] method as one plain command', function (): void {
    $registered = behaviourRegistrations(InertBehaviourCommandFixture::class);

    expect(array_keys($registered))->toBe(['inert'])
        ->and($registered['inert']['handler'])->toBeInstanceOf(InertBehaviourCommandFixture::class);
});

it('logs a command whose name is empty and registers nothing', function (): void {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->once()->with(Mockery::on(
        fn (string $message): bool => str_contains($message, NamelessBehaviourCommandFixture::class) && str_contains($message, 'no command name')
    ));

    expect(behaviourRegistrations(NamelessBehaviourCommandFixture::class, $logger))->toBe([]);
});

it('stays silent about an empty command name when no logger is given', function (): void {
    expect(behaviourRegistrations(NamelessBehaviourCommandFixture::class))->toBe([]);
});

it('skips a command class it cannot instantiate', function (): void {
    expect(behaviourRegistrations(UninstantiableBehaviourCommandFixture::class))->toBe([]);
});

it('ignores abstract classes, classes without #[WpCli] and structures that are not classes', function (): void {
    expect(behaviourRegistrations(AbstractBehaviourCommandFixture::class))->toBe([])
        ->and(behaviourRegistrations(PlainBehaviourClassFixture::class))->toBe([]);

    $service = Mockery::mock(WpCliService::class);
    $service->shouldNotReceive('register');
    $discovery = new WpCliDiscovery($service);
    $discovery->discover(
        new DiscoveryLocation('', __DIR__),
        DiscoveredInterface::fromReflection(new ReflectionClass(Countable::class))
    );
    $discovery->apply();
});

it('logs a command it fails to build and goes on with the others', function (): void {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->once()->with(Mockery::on(
        fn (string $message): bool => str_contains($message, NeedsInputBehaviourCommandFixture::class)
    ));

    expect(behaviourRegistrations(NeedsInputBehaviourCommandFixture::class, $logger))->toBe([]);
});

it('logs a discovered item whose reflection cache is missing', function (): void {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->once();

    $discovery = new WpCliDiscovery(Mockery::mock(WpCliService::class), $logger);
    $discovery->discover(new DiscoveryLocation('', __DIR__), DiscoveredClass::fromReflection(new ReflectionClass(OptionsBehaviourCommandFixture::class)));
    $discovery->apply();
});
