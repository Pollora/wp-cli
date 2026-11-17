<?php

declare(strict_types=1);

use Pollora\WpCli\Infrastructure\Adapters\WpCliAdapter;

/*
 * A recording stand-in for the WP_CLI class, declared WITHOUT the WP_CLI
 * constant (which cannot be undone in a process): the adapter must not reach
 * it while WP-CLI is unavailable. Same shape as the one the separate-process
 * tests declare.
 */
if (! class_exists('WP_CLI', false)) {
    class WP_CLI
    {
        public static array $calls = [];

        public static ?object $runner = null;

        public static bool $throw = false;

        public static function add_command($name, $handler, $args = []): void
        {
            self::$calls[] = ['add_command', $name, $handler, $args];
        }

        public static function get_runner(): ?object
        {
            if (self::$throw) {
                throw new RuntimeException('no runner');
            }

            return self::$runner;
        }

        public static function debug($m): void
        {
            self::$calls[] = ['debug', $m];
        }

        public static function warning($m): void
        {
            self::$calls[] = ['warning', $m];
        }

        public static function error($m): void
        {
            self::$calls[] = ['error', $m];
        }

        public static function success($m): void
        {
            self::$calls[] = ['success', $m];
        }

        public static function log($m): void
        {
            self::$calls[] = ['log', $m];
        }
    }
}

beforeEach(function (): void {
    WP_CLI::$calls = [];
    WP_CLI::$throw = false;
    WP_CLI::$runner = new class
    {
        public array $asked = [];

        public function find_command_to_run(array $parts): array
        {
            $this->asked[] = $parts;

            return [new stdClass];
        }
    };
});

it('never asks the WP-CLI runner whether a command exists while WP-CLI is unavailable', function (): void {
    expect((new WpCliAdapter)->hasCommand('cache flush'))->toBeFalse()
        ->and(WP_CLI::$runner->asked)->toBe([]);
});

it('never registers a command with WP-CLI while it is unavailable', function (): void {
    expect(fn () => (new WpCliAdapter)->addCommand('demo', new class
    {
        public function __invoke(): void {}
    }))->toThrow(RuntimeException::class, 'WP-CLI is not available')
        ->and(WP_CLI::$calls)->toBe([]);
});

it('never logs through WP-CLI while it is unavailable', function (string $level): void {
    (new WpCliAdapter)->log('hello', $level);

    expect(WP_CLI::$calls)->toBe([]);
})->with(['debug', 'warning', 'error', 'success', 'log']);

it('reports no version while WP-CLI is unavailable', function (): void {
    expect((new WpCliAdapter)->getVersion())->toBeNull();
});

describe('handler validation of an object', function (): void {
    beforeEach(function (): void {
        $this->validate = new ReflectionMethod(WpCliAdapter::class, 'validateHandler');
        $this->message = 'must be invokable or have public methods to run as subcommands';
    });

    it('rejects an object whose only public method is static', function (): void {
        $handler = new class
        {
            public static function helper(): void {}
        };

        expect(fn () => $this->validate->invoke(new WpCliAdapter, $handler))
            ->toThrow(InvalidArgumentException::class, $this->message);
    });

    it('rejects an object whose only public method is its constructor', function (): void {
        $handler = new class
        {
            public function __construct() {}
        };

        expect(fn () => $this->validate->invoke(new WpCliAdapter, $handler))
            ->toThrow(InvalidArgumentException::class, $this->message);
    });

    it('rejects an object with only private, protected, static and constructor methods', function (): void {
        $handler = new class
        {
            public function __construct() {}

            public static function helper(): void {}

            protected function hidden(): void {}

            private function secret(): void {}
        };

        expect(fn () => $this->validate->invoke(new WpCliAdapter, $handler))
            ->toThrow(InvalidArgumentException::class, $this->message);
    });

    it('accepts an object with one runnable public method next to a static one and a constructor', function (): void {
        $handler = new class
        {
            public function __construct() {}

            public static function helper(): void {}

            public function run(): void {}
        };

        $this->validate->invoke(new WpCliAdapter, $handler);

        expect(true)->toBeTrue();
    });

    it('names the rejected class in the message', function (): void {
        $handler = new class
        {
            public static function helper(): void {}
        };

        expect(fn () => $this->validate->invoke(new WpCliAdapter, $handler))
            ->toThrow(InvalidArgumentException::class, sprintf("Object of class '%s' %s", $handler::class, $this->message));
    });
});
