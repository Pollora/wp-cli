<?php

declare(strict_types=1);

namespace Pollora\WpCli\Domain\Contracts;

/**
 * The registry of the WP-CLI commands: it keeps what is registered and hands it to WP-CLI when it runs.
 */
interface WpCliServiceInterface
{
    /**
     * Register a WP CLI command.
     *
     * @param  string  $name  The command name
     * @param  string|array<mixed>|object  $className  The command class name, instance or callable
     * @param  string  $description  The command description
     * @param  int  $priority  The registration priority
     * @param  array<string, mixed>  $args  Additional WP_CLI::add_command() arguments
     */
    public function register(string $name, string|array|object $className, string $description = '', int $priority = 0, array $args = []): void;

    /**
     * Hand every registered command to WP-CLI, when it is running.
     */
    public function initializeCommands(): void;

    /**
     * Get all registered commands.
     *
     * @return array<string, array{class: string|array<mixed>|object, description: string, priority: int, args: array<string, mixed>}>
     */
    public function getRegisteredCommands(): array;

    /**
     * Check if a command is registered.
     *
     * @param  string  $name  The command name
     */
    public function hasCommand(string $name): bool;

    /**
     * Get command information.
     *
     * @param  string  $name  The command name
     * @return array{class: string|array<mixed>|object, description: string, priority: int, args: array<string, mixed>}|null
     */
    public function getCommand(string $name): ?array;
}
