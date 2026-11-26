<?php

declare(strict_types=1);

use Pollora\Attributes\WpCli;
use Pollora\Attributes\WpCli\Command;
use Pollora\Discovery\Domain\Contracts\ReflectionCacheInterface;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Pollora\WpCli\Application\Services\WpCliService;
use Pollora\WpCli\Infrastructure\Services\WpCliDiscovery;
use Psr\Log\LoggerInterface;
use Spatie\StructureDiscoverer\Data\DiscoveredAttribute;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredInterface;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;

#[WpCli('details-abstract')]
abstract class AbstractDetailsCommandFixture {}

#[WpCli('details-interface')]
interface InterfaceDetailsCommandFixture {}

#[WpCli('details-plain')]
final class PlainDetailsCommandFixture
{
    public function __invoke(array $arguments, array $options): void {}
}

/**
 * A class docblock that is not the help of a command.
 */
#[WpCli('details-documented')]
final class DocumentedDetailsCommandFixture
{
    public function __invoke(array $arguments, array $options): void {}
}

#[WpCli('details-hybrid')]
final class HybridDetailsCommandFixture
{
    public function __invoke(array $arguments, array $options): void {}

    #[Command('extra')]
    public function extra(array $arguments, array $options): void {}
}

#[WpCli('details-private')]
final class PrivateDetailsCommandFixture
{
    private function __construct() {}
}

#[WpCli('details-needs')]
final class NeedsDetailsCommandFixture
{
    public function __construct(private readonly int $required) {}
}

/**
 * @return array{0: WpCliDiscovery, 1: ArrayObject<int, array{name: string, handler: mixed, description: string, priority: int, args: array}>}
 */
function detailsDiscovery(?LoggerInterface $logger = null): array
{
    $calls = new ArrayObject;
    $service = Mockery::mock(WpCliService::class);
    $service->shouldReceive('register')->andReturnUsing(
        function (string $name, mixed $handler, string $description, int $priority, array $args) use ($calls): void {
            $calls[] = compact('name', 'handler', 'description', 'priority', 'args');
        }
    );

    return [new WpCliDiscovery($service, $logger), $calls];
}

function detailsDiscover(WpCliDiscovery $discovery, DiscoveredStructure $structure): void
{
    $discovery->discover(new DiscoveryLocation('', __DIR__), $structure, new ReflectionCache);
}

function detailsDescription(string $docComment): array
{
    $method = new ReflectionMethod(WpCliDiscovery::class, 'extractMethodDescription');

    return $method->invoke(new WpCliDiscovery(Mockery::mock(WpCliService::class)), $docComment);
}

it('keeps the class name, namespace included, of a discovered command', function (): void {
    [$discovery] = detailsDiscovery();
    detailsDiscover($discovery, DiscoveredClass::fromReflection(new ReflectionClass(PlainDetailsCommandFixture::class)));

    expect(array_column($discovery->getItems()->all(), 'class'))->toBe(['\\'.PlainDetailsCommandFixture::class]);
});

it('joins the namespace and the name of a discovered class with one backslash', function (): void {
    [$discovery] = detailsDiscovery();
    detailsDiscover($discovery, new DiscoveredClass(
        name: 'Thing',
        file: '',
        namespace: 'Some\\Space',
        isFinal: true,
        isAbstract: false,
        isReadonly: false,
        extends: null,
        implements: [],
        attributes: [new DiscoveredAttribute(WpCli::class)],
    ));

    expect(array_column($discovery->getItems()->all(), 'class'))->toBe(['Some\\Space\\Thing']);
});

it('does not even keep an abstract class carrying #[WpCli]', function (): void {
    [$discovery] = detailsDiscovery();
    detailsDiscover($discovery, DiscoveredClass::fromReflection(new ReflectionClass(AbstractDetailsCommandFixture::class)));

    expect($discovery->getItems()->all())->toBe([]);
});

it('does not keep an interface carrying #[WpCli]', function (): void {
    [$discovery] = detailsDiscovery();
    detailsDiscover($discovery, DiscoveredInterface::fromReflection(new ReflectionClass(InterfaceDetailsCommandFixture::class)));

    expect($discovery->getItems()->all())->toBe([]);
});

it('registers a command with an empty description and priority 0', function (): void {
    [$discovery, $recorded] = detailsDiscovery();
    detailsDiscover($discovery, DiscoveredClass::fromReflection(new ReflectionClass(PlainDetailsCommandFixture::class)));
    $discovery->apply();
    $calls = $recorded->getArrayCopy();

    expect($calls)->toHaveCount(1)
        ->and($calls[0]['name'])->toBe('details-plain')
        ->and($calls[0]['description'])->toBe('')
        ->and($calls[0]['priority'])->toBe(0);
});

it('registers a command discovered without a reflection cache, which the contract makes optional', function (): void {
    [$discovery, $recorded] = detailsDiscovery();
    $discovery->discover(new DiscoveryLocation('', __DIR__), DiscoveredClass::fromReflection(new ReflectionClass(PlainDetailsCommandFixture::class)));
    $discovery->apply();

    expect(array_column($recorded->getArrayCopy(), 'name'))->toBe(['details-plain']);
});

it('reflects a command through the reflection cache when it was given one', function (): void {
    $cache = Mockery::mock(ReflectionCacheInterface::class);
    $cache->shouldReceive('getClassReflection')->once()->andReturn(new ReflectionClass(PlainDetailsCommandFixture::class));

    [$discovery] = detailsDiscovery();
    $discovery->discover(new DiscoveryLocation('', __DIR__), DiscoveredClass::fromReflection(new ReflectionClass(PlainDetailsCommandFixture::class)), $cache);
    $discovery->apply();
});

it('takes no help from the docblock of the command class itself', function (): void {
    [$discovery, $recorded] = detailsDiscovery();
    detailsDiscover($discovery, DiscoveredClass::fromReflection(new ReflectionClass(DocumentedDetailsCommandFixture::class)));
    $discovery->apply();
    $calls = $recorded->getArrayCopy();

    expect($calls[0]['args'])->toBe([]);
});

it('registers a class with __invoke as one command even when a method carries #[Command]', function (): void {
    [$discovery, $recorded] = detailsDiscovery();
    detailsDiscover($discovery, DiscoveredClass::fromReflection(new ReflectionClass(HybridDetailsCommandFixture::class)));
    $discovery->apply();
    $calls = $recorded->getArrayCopy();

    expect(array_column($calls, 'name'))->toBe(['details-hybrid']);
});

it('builds one instance of a command class for all its registrations', function (): void {
    [$discovery, $recorded] = detailsDiscovery();
    detailsDiscover($discovery, DiscoveredClass::fromReflection(new ReflectionClass(PlainDetailsCommandFixture::class)));
    $discovery->apply();
    $discovery->apply();
    $calls = $recorded->getArrayCopy();

    expect($calls)->toHaveCount(2)
        ->and($calls[1]['handler'])->toBe($calls[0]['handler']);
});

it('logs a failed registration with the class, then the reason', function (): void {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->once()->with('Failed to register WP CLI command from class \\'.PlainDetailsCommandFixture::class.': service is down');

    $service = Mockery::mock(WpCliService::class);
    $service->shouldReceive('register')->andThrow(new RuntimeException('service is down'));

    $discovery = new WpCliDiscovery($service, $logger);
    detailsDiscover($discovery, DiscoveredClass::fromReflection(new ReflectionClass(PlainDetailsCommandFixture::class)));
    $discovery->apply();
});

it('survives a failed registration when no logger is given', function (): void {
    $service = Mockery::mock(WpCliService::class);
    $service->shouldReceive('register')->andThrow(new RuntimeException('service is down'));

    $discovery = new WpCliDiscovery($service);
    detailsDiscover($discovery, DiscoveredClass::fromReflection(new ReflectionClass(PlainDetailsCommandFixture::class)));
    $discovery->apply();

    expect($discovery->getItems()->all())->not->toBe([]);
});

it('does not log a class it skips for being uninstantiable', function (): void {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldNotReceive('error');

    [$discovery, $recorded] = detailsDiscovery($logger);
    detailsDiscover($discovery, DiscoveredClass::fromReflection(new ReflectionClass(PrivateDetailsCommandFixture::class)));
    $discovery->apply();
    $calls = $recorded->getArrayCopy();

    expect($calls)->toBe([]);
});

it('treats a command without a name as skipped, never failing, when no logger is given', function (): void {
    $discovery = new WpCliDiscovery(Mockery::mock(WpCliService::class));
    $process = new ReflectionMethod(WpCliDiscovery::class, 'processWpCliCommand');

    $nameless = new #[WpCli('')] class {};

    expect($process->invoke($discovery, $nameless::class, new ReflectionCache))->toBeNull();
});

describe('help extracted from a method docblock', function (): void {
    it('takes the first line as the short description and the rest as the long one', function (): void {
        expect(detailsDescription("/**\n * Short.\n *\n * Long A.\n *\n * Long B.\n */"))
            ->toBe(['short' => 'Short.', 'long' => "Long A.\n\nLong B."]);
    });

    it('skips blank and zero lines before the short description', function (): void {
        expect(detailsDescription("/**\n *\n * 0\n *\n * Short.\n */"))
            ->toBe(['short' => 'Short.', 'long' => '']);
    });

    it('trims each line of trailing blanks, tabs and carriage returns', function (): void {
        expect(detailsDescription("/**\r\n * Short.  \r\n *\r\n * Long.\t\r\n */"))
            ->toBe(['short' => 'Short.', 'long' => 'Long.']);
    });

    it('stops at the first @tag or ## section, also after a long description', function (string $stop): void {
        expect(detailsDescription("/**\n * Short.\n * Long.\n * $stop\n * After.\n */"))
            ->toBe(['short' => 'Short.', 'long' => 'Long.']);
    })->with(['@return void', '## OPTIONS']);

    it('does not stop at a line that merely ends with @', function (): void {
        expect(detailsDescription("/**\n * Short.\n * Write to us @\n * After.\n */"))
            ->toBe(['short' => 'Short.', 'long' => "Write to us @\nAfter."]);
    });

    it('stops before the short description when a tag comes first', function (): void {
        expect(detailsDescription("/**\n * @return void\n */"))
            ->toBe(['short' => '', 'long' => '']);
    });

    it('ignores a zero line that opens the long description but keeps one inside it', function (): void {
        expect(detailsDescription("/**\n * Short.\n * 0\n * More.\n */"))->toBe(['short' => 'Short.', 'long' => 'More.'])
            ->and(detailsDescription("/**\n * Short.\n * More.\n * 0\n * End.\n */"))->toBe(['short' => 'Short.', 'long' => "More.\n0\nEnd."]);
    });
});
