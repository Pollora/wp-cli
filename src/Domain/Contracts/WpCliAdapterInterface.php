<?php

declare(strict_types=1);

namespace Pollora\WpCli\Domain\Contracts;

/**
 * Port to WP-CLI: what the application needs to register its commands.
 */
interface WpCliAdapterInterface
{
    /**
     * Check if WP-CLI is available.
     */
    public function isAvailable(): bool;

    /**
     * Register a command with WP-CLI.
     *
     * @param  string  $name  The command name
     * @param  string|array|object  $handler  The command handler
     * @param  array<string, mixed>  $args  Additional arguments for WP-CLI
     *
     * @throws \RuntimeException If WP-CLI is not available
     * @throws \InvalidArgumentException If the handler is invalid
     */
    public function addCommand(string $name, string|array|object $handler, array $args = []): void;
}
