<?php

declare(strict_types=1);

namespace Pollora\WpCli\Domain\Contracts;

/**
 * Interface for WP CLI command implementations.
 *
 * This interface defines the contract for WP CLI commands in the framework.
 * Commands implementing this interface will be automatically discovered and
 * registered with WP CLI when the AsWordpressCommand attribute is present.
 */
interface WpCliCommandInterface
{
    /**
     * Execute the command with provided arguments and options.
     *
     * @param  array<string, mixed>  $arguments  The command arguments
     * @param  array<string, mixed>  $options  The command options/flags
     */
    public function __invoke(array $arguments, array $options): void;

    /**
     * Get the command priority for registration order.
     *
     * @return int The priority (higher numbers = higher priority)
     */
    public function getPriority(): int;

    /**
     * Initialize the command.
     * This method is called during the discovery and registration process.
     */
    public function initialize(): void;

    /**
     * Get the default command name from the AsWordpressCommand attribute.
     *
     * @return string|null The command name or null if not found
     */
    public static function getDefaultName(): ?string;
}
