<p align="center">
  <a href="https://pollora.dev">
    <img src="https://raw.githubusercontent.com/Pollora/.github/main/brand/banners/wp-cli.png" width="100%" alt="Pollora WP-CLI: WP-CLI commands declared with PHP attributes">
  </a>
</p>

<p align="center">
  <a href="https://packagist.org/packages/pollora/wp-cli"><img src="https://img.shields.io/packagist/v/pollora/wp-cli" alt="Latest version"></a>
  <a href="https://packagist.org/packages/pollora/wp-cli"><img src="https://img.shields.io/packagist/dt/pollora/wp-cli" alt="Total downloads"></a>
  <a href="https://github.com/Pollora/wp-cli/actions/workflows/tests.yml"><img src="https://github.com/Pollora/wp-cli/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/Pollora/wp-cli" alt="License"></a>
</p>

WP-CLI commands written as classes: `#[WpCli]` on a class registers it, its public methods become subcommands, and the command and subcommand names come from the class and method names unless you give them. Synopsis, `when`, before and after callbacks are attributes too; no `WP_CLI::add_command()` call to write.

> Part of [Pollora](https://pollora.dev), the Laravel framework for WordPress, where it is installed and wired for you, with a `pollora:make:wp-cli` generator: see [WP-CLI Commands](https://pollora.dev/advanced/wp-cli/). It runs without Laravel too, in a WordPress plugin, a Bedrock site or any PHP project.

## Installation

```bash
composer require pollora/wp-cli
```

Requires PHP 8.3+. It builds on [`pollora/attributes`](https://github.com/Pollora/attributes) and [`pollora/discovery`](https://github.com/Pollora/discovery). WP-CLI itself provides the `WP_CLI` classes when it runs.

## Declaring commands

```php
use Pollora\Attributes\WpCli;
use Pollora\Attributes\WpCli\Command;
use Pollora\Attributes\WpCli\Synopsis;

/**
 * Manage the reports.
 */
#[WpCli]
final class ReportsCommand
{
    public function __construct(private readonly ReportBuilder $reports) {}

    /**
     * Build a report.
     */
    #[Command('build')]
    #[Synopsis('<month> [--format=<format>]')]
    public function build(array $arguments, array $options): void
    {
        WP_CLI::success($this->reports->build($arguments[0], $options['format'] ?? 'csv'));
    }

    #[Command('rm')]
    public function deleteReport(array $arguments, array $options): void {}
}
```

```bash
wp reports build 2026-10 --format=json
wp reports rm 2026-09
```

- The command name is the class name in kebab case, without its `Command` suffix: `ReportsCommand` → `reports`. `#[WpCli('reports')]` sets it. A class with `__invoke()` is a single command.
- A method with `#[Command]` is a subcommand registered by this package: `#[Command('rm')]` names it, its `#[Synopsis]`, `#[When]`, `#[BeforeInvoke]`, `#[AfterInvoke]` and `#[IsDeferred]` become the matching `WP_CLI::add_command()` arguments, and it is called on an instance built with its constructor dependencies.
- The class is also handed to WP-CLI by name, so its other public methods are subcommands too, named after them (`listItems()` → `list-items`). WP-CLI runs those itself: it builds the class without arguments and reads their help from the docblock.

## Wiring it outside Pollora

Run the discovery engine with `WpCliDiscovery` when WP-CLI loads your plugin:

```php
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\DiscoveryEngine;
use Pollora\WpCli\Application\Services\WpCliService;
use Pollora\WpCli\Infrastructure\Adapters\WpCliAdapter;
use Pollora\WpCli\Infrastructure\Services\WpCliDiscovery;

if (defined('WP_CLI') && WP_CLI) {
    (new DiscoveryEngine)
        ->addLocation(new DiscoveryLocation('MyPlugin\\', __DIR__.'/src'))
        ->addDiscovery('wp_cli_commands', new WpCliDiscovery(new WpCliService(new WpCliAdapter)))
        ->run();
}
```

Commands are built by the engine's instance resolver: by reflection by default (constructor parameters typed with a class are built in turn), by your container if you give the engine a `ContainerInstanceResolver` (PSR-11). `WpCliService` and `WpCliDiscovery` take a PSR-3 logger.

## Testing

```bash
composer test   # unit tests, PHPStan, Pint
```

## License

MIT. See [LICENSE](LICENSE).
