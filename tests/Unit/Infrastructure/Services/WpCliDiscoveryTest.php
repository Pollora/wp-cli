<?php

declare(strict_types=1);

use Pollora\Attributes\WpCli;
use Pollora\Attributes\WpCli\Command;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Pollora\WpCli\Application\Services\WpCliService;
use Pollora\WpCli\Infrastructure\Services\WpCliDiscovery;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;

final class ReportBuilder
{
    public function build(string $month): string
    {
        return 'report '.$month;
    }
}

/**
 * Build a report.
 */
#[WpCli('report')]
final class BuildReportCommand
{
    public function __construct(private readonly ReportBuilder $reports) {}

    public function __invoke(array $arguments, array $options): void
    {
        echo $this->reports->build($arguments[0]);
    }
}

/**
 * Manage the reports.
 */
#[WpCli('reports')]
final class ReportsCommand
{
    public function __construct(private readonly ReportBuilder $reports) {}

    public function listAll(array $arguments, array $options): void
    {
        echo $this->reports->build('all');
    }

    #[Command('purge')]
    private function purgeOld(array $arguments, array $options): void
    {
        echo 'purged';
    }
}

/**
 * Run the discovery over a class and return what reached the WP-CLI service, by command name.
 *
 * @return array<string, mixed>
 */
function registeredHandlers(string $class): array
{
    $handlers = [];
    $service = Mockery::mock(WpCliService::class);
    $service->shouldReceive('register')->andReturnUsing(function (string $name, mixed $handler) use (&$handlers): void {
        $handlers[$name] = $handler;
    });

    $discovery = new WpCliDiscovery($service);
    $discovery->discover(new DiscoveryLocation('', __DIR__), DiscoveredClass::fromReflection(new ReflectionClass($class)), new ReflectionCache);
    $discovery->apply();

    return $handlers;
}

it('hands WP-CLI a single command built with its dependencies', function (): void {
    $handler = registeredHandlers(BuildReportCommand::class)['report'];

    expect($handler)->toBeInstanceOf(BuildReportCommand::class);

    ob_start();
    $handler(['2026-10'], []);
    expect(ob_get_clean())->toBe('report 2026-10');
});

it('hands WP-CLI a command suite built with its dependencies, for the subcommands it runs itself', function (): void {
    $handler = registeredHandlers(ReportsCommand::class)['reports'];

    expect($handler)->toBeInstanceOf(ReportsCommand::class);

    ob_start();
    $handler->listAll([], []);
    expect(ob_get_clean())->toBe('report all');
});

it('registers a non-public #[Command] method through a callable', function (): void {
    $handler = registeredHandlers(ReportsCommand::class)['reports purge'];

    ob_start();
    $handler([], []);
    expect(ob_get_clean())->toBe('purged');
});
