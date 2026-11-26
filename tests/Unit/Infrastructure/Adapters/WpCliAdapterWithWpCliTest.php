<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Adapters;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Pollora\WpCli\Infrastructure\Adapters\WpCliAdapter;

/*
 * The WP_CLI constant cannot be removed from a process once defined, and the
 * other tests assert it is absent. Each case therefore runs in its own PHP
 * process (the one exception to Pest function tests: Pest cannot isolate a
 * function test), where WP_CLI and a recording stand-in for the WP_CLI class
 * are declared before the adapter is used. Nothing leaks to the main process.
 */
final class WpCliAdapterWithWpCliTest extends TestCase
{
    private function declareWpCli(string $extra = ''): void
    {
        define('WP_CLI', true);
        define('WP_CLI_VERSION', '2.12.0');

        eval('class WP_CLI {
            public static array $calls = [];
            public static ?object $runner = null;
            public static bool $throw = false;
            public static function add_command($name, $handler, $args = []) { self::$calls[] = ["add_command", $name, $handler, $args]; }
            public static function get_runner() { if (self::$throw) { throw new \RuntimeException("no runner"); } return self::$runner; }
            public static function debug($m) { self::$calls[] = ["debug", $m]; }
            public static function warning($m) { self::$calls[] = ["warning", $m]; }
            public static function error($m) { if (self::$throw) { throw new \RuntimeException("exit"); } self::$calls[] = ["error", $m]; }
            public static function success($m) { self::$calls[] = ["success", $m]; }
            public static function log($m) { self::$calls[] = ["log", $m]; }
        } '.$extra);
    }

    #[RunInSeparateProcess]
    public function test_it_is_available_and_reports_the_wp_cli_version(): void
    {
        $this->declareWpCli();
        $adapter = new WpCliAdapter;

        $this->assertTrue($adapter->isAvailable());
        $this->assertSame('2.12.0', $adapter->getVersion());
    }

    #[RunInSeparateProcess]
    public function test_it_registers_a_valid_command_with_wp_cli_with_its_arguments(): void
    {
        $this->declareWpCli();
        $handler = new class
        {
            public function __invoke(): void {}
        };

        (new WpCliAdapter)->addCommand('demo', $handler, ['shortdesc' => 'Demo']);

        $this->assertSame([['add_command', 'demo', $handler, ['shortdesc' => 'Demo']]], \WP_CLI::$calls);
    }

    #[RunInSeparateProcess]
    public function test_it_rejects_an_invalid_handler_before_calling_wp_cli(): void
    {
        $this->declareWpCli();

        try {
            (new WpCliAdapter)->addCommand('demo', 'Missing\\Handler');
            $this->fail('An invalid handler must be rejected');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('does not exist', $e->getMessage());
        }

        $this->assertSame([], \WP_CLI::$calls);
    }

    #[RunInSeparateProcess]
    public function test_it_finds_a_command_the_runner_resolves_and_none_otherwise(): void
    {
        $this->declareWpCli();
        $adapter = new WpCliAdapter;

        \WP_CLI::$runner = new class
        {
            public array $asked = [];

            public function find_command_to_run(array $parts): ?array
            {
                $this->asked[] = $parts;

                return $parts === ['cache', 'flush'] ? [new \stdClass] : null;
            }
        };

        $this->assertTrue($adapter->hasCommand('cache flush'));
        $this->assertFalse($adapter->hasCommand('nope'));
        $this->assertSame([['cache', 'flush'], ['nope']], \WP_CLI::$runner->asked);
    }

    /**
     * The runner answers an error message, not null, for a command it does not know.
     */
    #[RunInSeparateProcess]
    public function test_it_reads_an_error_message_from_the_runner_as_no_command(): void
    {
        $this->declareWpCli();
        \WP_CLI::$runner = new class
        {
            public function find_command_to_run(array $parts): array|string
            {
                return $parts === ['cache', 'flush']
                    ? [new \stdClass, [], $parts]
                    : "'nope' is not a registered wp command.";
            }
        };
        $adapter = new WpCliAdapter;

        $this->assertTrue($adapter->hasCommand('cache flush'));
        $this->assertFalse($adapter->hasCommand('nope'));
    }

    #[RunInSeparateProcess]
    public function test_it_reports_no_command_when_the_runner_is_not_ready(): void
    {
        $this->declareWpCli();
        \WP_CLI::$throw = true;

        $this->assertFalse((new WpCliAdapter)->hasCommand('cache flush'));
    }

    #[RunInSeparateProcess]
    public function test_it_routes_each_log_level_to_the_matching_wp_cli_method(): void
    {
        $this->declareWpCli();
        $adapter = new WpCliAdapter;

        $adapter->log('d', 'debug');
        $adapter->log('w', 'warning');
        $adapter->log('e', 'error');
        $adapter->log('s', 'success');
        $adapter->log('l');
        $adapter->log('x', 'unknown');

        $this->assertSame([
            ['debug', 'd'], ['warning', 'w'], ['error', 'e'], ['success', 's'], ['log', 'l'], ['log', 'x'],
        ], \WP_CLI::$calls);
    }

    #[RunInSeparateProcess]
    public function test_it_swallows_a_failure_of_wp_cli_while_logging(): void
    {
        $this->declareWpCli();
        \WP_CLI::$throw = true;

        (new WpCliAdapter)->log('boom', 'error');

        $this->assertSame([], \WP_CLI::$calls);
    }
}
