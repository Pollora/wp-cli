<?php

declare(strict_types=1);

namespace Pollora\WpCli\Infrastructure\Adapters;

use WP_CLI;

/**
 * Adapter for WP-CLI integration.
 * 
 * This adapter encapsulates all direct interactions with WP-CLI,
 * providing a clean interface that respects hexagonal architecture
 * by isolating external dependencies in the infrastructure layer.
 */
final class WpCliAdapter
{
    /**
     * Check if WP-CLI is available.
     */
    public function isAvailable(): bool
    {
        return \defined('WP_CLI') && WP_CLI;
    }

    /**
     * Register a command with WP-CLI.
     *
     * @param string $name The command name
     * @param string|array|object $handler The command handler
     * @param array<string, mixed> $args Additional arguments for WP-CLI
     * @throws \RuntimeException If WP-CLI is not available
     * @throws \InvalidArgumentException If the handler is invalid
     */
    public function addCommand(string $name, string|array|object $handler, array $args = []): void
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('WP-CLI is not available');
        }

        $this->validateHandler($handler);

        try {
            WP_CLI::add_command($name, $handler, $args);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to register WP-CLI command '{$name}': " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Validate that the handler is properly formatted.
     *
     * @param string|array|object $handler
     * @throws \InvalidArgumentException If the handler is invalid
     */
    private function validateHandler(string|array|object $handler): void
    {
        if (is_string($handler)) {
            if (!class_exists($handler)) {
                throw new \InvalidArgumentException("Command handler class '{$handler}' does not exist");
            }
        } elseif (is_array($handler)) {
            if (count($handler) !== 2) {
                throw new \InvalidArgumentException('Command handler array must contain exactly 2 elements [object, method]');
            }

            [$object, $method] = $handler;

            if (!is_object($object)) {
                throw new \InvalidArgumentException('First element of handler array must be an object');
            }

            if (!is_string($method)) {
                throw new \InvalidArgumentException('Second element of handler array must be a method name string');
            }

            if (!method_exists($object, $method)) {
                $class = get_class($object);
                throw new \InvalidArgumentException("Method '{$method}' does not exist on class '{$class}'");
            }
        } elseif (is_object($handler)) {
            if (!method_exists($handler, '__invoke')) {
                $class = get_class($handler);
                throw new \InvalidArgumentException("Object of class '{$class}' must be invokable (have __invoke method)");
            }
        }
    }

    /**
     * Check if a command is already registered with WP-CLI.
     *
     * @param string $name The command name
     * @return bool True if the command exists
     */
    public function hasCommand(string $name): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            $runner = WP_CLI::get_runner();
            return $runner->find_command_to_run(explode(' ', $name)) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get WP-CLI version information.
     *
     * @return string|null The WP-CLI version or null if not available
     */
    public function getVersion(): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            return WP_CLI_VERSION ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Log a message to WP-CLI output.
     *
     * @param string $message The message to log
     * @param string $level The log level (debug, log, warning, error, success)
     */
    public function log(string $message, string $level = 'log'): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        try {
            match ($level) {
                'debug' => WP_CLI::debug($message),
                'warning' => WP_CLI::warning($message),
                'error' => WP_CLI::error($message),
                'success' => WP_CLI::success($message),
                default => WP_CLI::log($message),
            };
        } catch (\Throwable) {
            // Silently fail for logging to avoid breaking command execution
        }
    }
}