<?php

declare(strict_types=1);

namespace Pollora\WpCli\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a command is registered outside a WP-CLI process.
 */
final class WpCliUnavailable extends RuntimeException
{
    public static function whenRegisteringACommand(): self
    {
        return new self('WP-CLI is not available. Register commands from code that runs under `wp`, or check the adapter with isAvailable() first.');
    }
}
