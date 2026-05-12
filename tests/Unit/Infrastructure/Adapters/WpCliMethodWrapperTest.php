<?php

declare(strict_types=1);

use Pollora\WpCli\Infrastructure\Adapters\WpCliMethodWrapper;

describe('WpCliMethodWrapper', function (): void {
    beforeEach(function (): void {
        $this->instance = new class
        {
            /**
             * Test command documentation.
             *
             * ## OPTIONS
             * <name>
             * : The name to greet.
             */
            public function greet(array $args, array $assocArgs): string
            {
                return 'Hello '.$args[0];
            }

            private function secretMethod(array $args): string
            {
                return 'secret: '.$args[0];
            }
        };
    });

    it('invokes the original method via __invoke', function (): void {
        $method = new ReflectionMethod($this->instance, 'greet');
        $wrapper = new WpCliMethodWrapper($this->instance, $method);

        $result = $wrapper(['World'], []);

        expect($result)->toBe('Hello World');
    });

    it('delegates __call to original method by name', function (): void {
        $method = new ReflectionMethod($this->instance, 'greet');
        $wrapper = new WpCliMethodWrapper($this->instance, $method);

        $result = $wrapper->greet(['World'], []);

        expect($result)->toBe('Hello World');
    });

    it('throws BadMethodCallException for unknown method via __call', function (): void {
        $method = new ReflectionMethod($this->instance, 'greet');
        $wrapper = new WpCliMethodWrapper($this->instance, $method);

        expect(fn () => $wrapper->unknownMethod())
            ->toThrow(BadMethodCallException::class, 'does not exist');
    });

    it('caches and returns the docblock', function (): void {
        $method = new ReflectionMethod($this->instance, 'greet');
        $wrapper = new WpCliMethodWrapper($this->instance, $method);

        $doc = $wrapper->getDocComment();

        expect($doc)->toBeString();
        expect($doc)->toContain('Test command documentation');
        expect($doc)->toContain('## OPTIONS');
    });

    it('returns false when method has no docblock', function (): void {
        $obj = new class
        {
            public function noDoc(array $args, array $assocArgs): void {}
        };

        $method = new ReflectionMethod($obj, 'noDoc');
        $wrapper = new WpCliMethodWrapper($obj, $method);

        expect($wrapper->getDocComment())->toBeFalse();
    });

    it('returns the original method reflection', function (): void {
        $method = new ReflectionMethod($this->instance, 'greet');
        $wrapper = new WpCliMethodWrapper($this->instance, $method);

        expect($wrapper->getOriginalMethod())->toBe($method);
    });

    it('returns the original instance', function (): void {
        $method = new ReflectionMethod($this->instance, 'greet');
        $wrapper = new WpCliMethodWrapper($this->instance, $method);

        expect($wrapper->getOriginalInstance())->toBe($this->instance);
    });

    it('can invoke private methods via reflection', function (): void {
        $method = new ReflectionMethod($this->instance, 'secretMethod');

        $wrapper = new WpCliMethodWrapper($this->instance, $method);

        $result = $wrapper(['data'], []);

        expect($result)->toBe('secret: data');
    });
});
