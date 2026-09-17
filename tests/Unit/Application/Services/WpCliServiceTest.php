<?php

declare(strict_types=1);

use Pollora\WpCli\Application\Services\WpCliService;
use Pollora\WpCli\Infrastructure\Adapters\WpCliAdapter;

describe('WpCliService', function (): void {
    beforeEach(function (): void {
        // WpCliAdapter is final, so we create a real instance
        // isAvailable() returns false since WP_CLI is not defined in tests
        $this->adapter = new WpCliAdapter;
        $this->service = new WpCliService($this->adapter);
    });

    describe('register', function (): void {
        it('stores command in registered commands', function (): void {
            $this->service->register('pollora test', 'TestCommand', 'A test command', 10);

            expect($this->service->hasCommand('pollora test'))->toBeTrue();
        });

        it('stores command metadata correctly', function (): void {
            $this->service->register('pollora test', 'TestCommand', 'A test command', 10, ['when' => 'before_wp_load']);

            $command = $this->service->getCommand('pollora test');

            expect($command['class'])->toBe('TestCommand');
            expect($command['description'])->toBe('A test command');
            expect($command['priority'])->toBe(10);
            expect($command['args'])->toBe(['when' => 'before_wp_load']);
        });

        it('accepts array handler', function (): void {
            $handler = ['SomeClass', 'someMethod'];

            $this->service->register('pollora array-cmd', $handler, 'Array handler');

            $command = $this->service->getCommand('pollora array-cmd');
            expect($command['class'])->toBe($handler);
        });

        it('stores default values when not provided', function (): void {
            $this->service->register('pollora minimal', 'MinimalCommand');

            $command = $this->service->getCommand('pollora minimal');

            expect($command['description'])->toBe('');
            expect($command['priority'])->toBe(0);
            expect($command['args'])->toBe([]);
        });
    });

    describe('hasCommand', function (): void {
        it('returns false for unregistered command', function (): void {
            expect($this->service->hasCommand('nonexistent'))->toBeFalse();
        });

        it('returns true for registered command', function (): void {
            $this->service->register('pollora exists', 'ExistsCommand');

            expect($this->service->hasCommand('pollora exists'))->toBeTrue();
        });
    });

    describe('getCommand', function (): void {
        it('returns null for unregistered command', function (): void {
            expect($this->service->getCommand('nonexistent'))->toBeNull();
        });
    });

    describe('getRegisteredCommands', function (): void {
        it('returns empty array initially', function (): void {
            expect($this->service->getRegisteredCommands())->toBe([]);
        });

        it('returns all registered commands', function (): void {
            $this->service->register('cmd1', 'Class1');
            $this->service->register('cmd2', 'Class2');

            $commands = $this->service->getRegisteredCommands();

            expect($commands)->toHaveCount(2);
            expect($commands)->toHaveKeys(['cmd1', 'cmd2']);
        });
    });

    describe('initializeCommands', function (): void {
        it('does not throw when WP CLI is unavailable', function (): void {
            $this->service->register('cmd1', 'Class1');

            // Should not throw — WP CLI is not available, so it silently skips
            $this->service->initializeCommands();

            expect($this->service->hasCommand('cmd1'))->toBeTrue();
        });
    });
});
