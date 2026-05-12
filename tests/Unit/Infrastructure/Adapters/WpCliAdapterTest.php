<?php

declare(strict_types=1);

use Pollora\WpCli\Infrastructure\Adapters\WpCliAdapter;

describe('WpCliAdapter', function (): void {
    beforeEach(function (): void {
        $this->adapter = new WpCliAdapter;
    });

    describe('isAvailable', function (): void {
        it('returns false when WP_CLI is not defined', function (): void {
            // WP_CLI is not defined in test environment
            expect($this->adapter->isAvailable())->toBeFalse();
        });
    });

    describe('addCommand', function (): void {
        it('throws RuntimeException when WP_CLI is not available', function (): void {
            expect(fn () => $this->adapter->addCommand('test', 'SomeClass'))
                ->toThrow(RuntimeException::class, 'WP-CLI is not available');
        });
    });

    describe('hasCommand', function (): void {
        it('returns false when WP_CLI is not available', function (): void {
            expect($this->adapter->hasCommand('test'))->toBeFalse();
        });
    });

    describe('getVersion', function (): void {
        it('returns null when WP_CLI is not available', function (): void {
            expect($this->adapter->getVersion())->toBeNull();
        });
    });

    describe('log', function (): void {
        it('does nothing when WP_CLI is not available', function (): void {
            // Should not throw
            $this->adapter->log('test message', 'debug');
            $this->adapter->log('test message', 'warning');
            $this->adapter->log('test message', 'error');
            $this->adapter->log('test message', 'success');
            $this->adapter->log('test message');

            expect(true)->toBeTrue(); // Assert no exception
        });
    });
});

describe('WpCliAdapter validation', function (): void {
    /*
     * Since validateHandler is private, we test it indirectly through addCommand.
     * When WP_CLI is unavailable, RuntimeException is thrown before validation.
     * We use Reflection to test validation logic directly.
     */
    beforeEach(function (): void {
        $this->adapter = new WpCliAdapter;
        $this->validate = new ReflectionMethod(WpCliAdapter::class, 'validateHandler');
    });

    it('accepts a valid class string', function (): void {
        // stdClass exists
        $this->validate->invoke($this->adapter, stdClass::class);

        expect(true)->toBeTrue();
    });

    it('rejects a non-existent class string', function (): void {
        expect(fn () => $this->validate->invoke($this->adapter, 'NonExistentClass'))
            ->toThrow(InvalidArgumentException::class, 'does not exist');
    });

    it('accepts a valid [object, method] array', function (): void {
        $handler = [new class
        {
            public function handle(): void {}
        }, 'handle'];

        $this->validate->invoke($this->adapter, $handler);

        expect(true)->toBeTrue();
    });

    it('rejects array with wrong count', function (): void {
        expect(fn () => $this->validate->invoke($this->adapter, ['only-one']))
            ->toThrow(InvalidArgumentException::class, 'exactly 2 elements');
    });

    it('rejects array where first element is not an object', function (): void {
        expect(fn () => $this->validate->invoke($this->adapter, ['string', 'method']))
            ->toThrow(InvalidArgumentException::class, 'must be an object');
    });

    it('rejects array where second element is not a string', function (): void {
        expect(fn () => $this->validate->invoke($this->adapter, [new stdClass, 42]))
            ->toThrow(InvalidArgumentException::class, 'must be a method name string');
    });

    it('rejects array where method does not exist', function (): void {
        expect(fn () => $this->validate->invoke($this->adapter, [new stdClass, 'nonExistent']))
            ->toThrow(InvalidArgumentException::class, 'does not exist on class');
    });

    it('accepts an invokable object', function (): void {
        $handler = new class
        {
            public function __invoke(): void {}
        };

        $this->validate->invoke($this->adapter, $handler);

        expect(true)->toBeTrue();
    });

    it('rejects a non-invokable object', function (): void {
        expect(fn () => $this->validate->invoke($this->adapter, new stdClass))
            ->toThrow(InvalidArgumentException::class, 'must be invokable');
    });
});
