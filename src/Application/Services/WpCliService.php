<?php

declare(strict_types=1);

namespace Pollora\WpCli\Application\Services;

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
     * @var array<string, array{class: string|array, description: string, priority: int}>
     */
    private array $registeredCommands = [];

    /**
     * Register a WP CLI command.
     *
     * @param string $name The command name
     * @param string|array $className The command class name or callable array
     * @param string $description The command description
     * @param int $priority The registration priority
     * @return void
     */
    public function register(string $name, string|array $className, string $description = '', int $priority = 0): void
    {
        $this->registeredCommands[$name] = [
            'class' => $className,
            'description' => $description,
            'priority' => $priority,
        ];

        // Register immediately if WP CLI is available
        $this->registerWithWpCli($name, $className, $description);
    }

    /**
     * Register a command with WP CLI if it's available.
     *
     * @param string $name The command name
     * @param string|array $className The command class name or callable array
     * @param string $description The command description
     * @return void
     */
    private function registerWithWpCli(string $name, string|array $className, string $description = ''): void
    {
        if (!(\defined('WP_CLI') && WP_CLI)) {
            return;
        }

        try {
            // If it's an array (callable), validate the class exists
            if (is_array($className)) {
                if (!class_exists($className[0])) {
                    error_log("WP CLI command class {$className[0]} does not exist");
                    return;
                }
            } else {
                // Validate that the class exists for string class names
                if (!class_exists($className)) {
                    error_log("WP CLI command class {$className} does not exist");
                    return;
                }
            }
            
            // Register with WP CLI
            WP_CLI::add_command($name, $className);

        } catch (\Throwable $e) {
            error_log("Failed to register WP CLI command {$name}: " . $e->getMessage());
        }
    }

    /**
     * Initialize all registered commands.
     * This method can be called to ensure all commands are properly initialized.
     *
     * @return void
     */
    public function initializeCommands(): void
    {
        if (!(\defined('WP_CLI') && WP_CLI)) {
            return;
        }

        foreach ($this->registeredCommands as $name => $commandData) {
            $this->registerWithWpCli($name, $commandData['class']);
        }
    }

    /**
     * Get all registered commands.
     *
     * @return array<string, array{class: string|array, description: string, priority: int}>
     */
    public function getRegisteredCommands(): array
    {
        return $this->registeredCommands;
    }

    /**
     * Check if a command is registered.
     *
     * @param string $name The command name
     * @return bool
     */
    public function hasCommand(string $name): bool
    {
        return isset($this->registeredCommands[$name]);
    }

    /**
     * Get command information.
     *
     * @param string $name The command name
     * @return array{class: string|array, description: string, priority: int}|null
     */
    public function getCommand(string $name): ?array
    {
        return $this->registeredCommands[$name] ?? null;
    }
}
