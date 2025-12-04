<?php

declare(strict_types=1);

namespace Pollora\WpCli\Application\Services;

use Pollora\WpCli\Infrastructure\Adapters\WpCliAdapter;
use WP_CLI;

/**
 * WP CLI Service for managing WordPress CLI commands.
 *
 * This service handles the registration and management of WP CLI commands
 * in the Pollora framework. It provides methods to register commands
 * and manages their lifecycle within the WordPress CLI environment.
 */
class WpCliService
{
    /**
     * @var array<string, array{class: string|array, description: string, priority: int, args: array}>
     */
    private array $registeredCommands = [];

    public function __construct(
        private readonly WpCliAdapter $wpCliAdapter
    ) {}

    /**
     * Register a WP CLI command.
     *
     * @param  string  $name  The command name
     * @param  string|array  $className  The command class name or callable array
     * @param  string  $description  The command description
     * @param  int  $priority  The registration priority
     * @param  array  $args  Additional WP_CLI::add_command() arguments
     */
    public function register(string $name, string|array $className, string $description = '', int $priority = 0, array $args = []): void
    {
        $this->registeredCommands[$name] = [
            'class' => $className,
            'description' => $description,
            'priority' => $priority,
            'args' => $args,
        ];

        // Register immediately if WP CLI is available
        $this->registerWithWpCli($name, $className, $description, $args);
    }

    /**
     * Register a command with WP CLI through the adapter.
     *
     * @param  string  $name  The command name
     * @param  string|array  $className  The command class name or callable array
     * @param  string  $description  The command description
     * @param  array  $args  Additional WP_CLI::add_command() arguments
     */
    private function registerWithWpCli(string $name, string|array $className, string $description = '', array $args = []): void
    {
        if (! $this->wpCliAdapter->isAvailable()) {
            return;
        }

        try {
            // Delegate validation and registration to the adapter
            $this->wpCliAdapter->addCommand($name, $className, $args);
        } catch (\Throwable $e) {
            error_log("Failed to register WP CLI command {$name}: ".$e->getMessage());
        }
    }

    /**
     * Initialize all registered commands.
     * This method can be called to ensure all commands are properly initialized.
     */
    public function initializeCommands(): void
    {
        if (! $this->wpCliAdapter->isAvailable()) {
            return;
        }

        foreach ($this->registeredCommands as $name => $commandData) {
            $this->registerWithWpCli($name, $commandData['class'], $commandData['description'], $commandData['args']);
        }
    }

    /**
     * Get all registered commands.
     *
     * @return array<string, array{class: string|array, description: string, priority: int, args: array}>
     */
    public function getRegisteredCommands(): array
    {
        return $this->registeredCommands;
    }

    /**
     * Check if a command is registered.
     *
     * @param  string  $name  The command name
     */
    public function hasCommand(string $name): bool
    {
        return isset($this->registeredCommands[$name]);
    }

    /**
     * Get command information.
     *
     * @param  string  $name  The command name
     * @return array{class: string|array, description: string, priority: int, args: array}|null
     */
    public function getCommand(string $name): ?array
    {
        return $this->registeredCommands[$name] ?? null;
    }
}
