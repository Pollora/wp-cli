<?php

declare(strict_types=1);

use Pollora\WpCli\Domain\Exceptions\WpCliUnavailable;

it('says WP-CLI is missing and what to do about it', function (): void {
    $exception = WpCliUnavailable::whenRegisteringACommand();

    expect($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception->getMessage())->toStartWith('WP-CLI is not available')
        ->and($exception->getMessage())->toContain('isAvailable()');
});
