<?php

declare(strict_types=1);

namespace Pollora\WpCli\Domain\Models;

use Pollora\Attributes\WpCli;
use Pollora\WpCli\Domain\Contracts\WpCliCommandInterface;
use WP_CLI_Command;

/**
 * Abstract base class for WordPress CLI commands.
 *
 * This class provides the foundation for creating WP CLI commands in the
 * Pollora framework. It implements the WpCliCommandInterface and provides
 * automatic registration with WP CLI when decorated with the WpCli
 * attribute.
 *
 * Child classes should implement the __invoke method to define their command
 * behavior and use the WpCli attribute to specify command details.
 */
abstract class WordpressCommand extends WP_CLI_Command implements WpCliCommandInterface
{
    /**
     * Execute the command with provided arguments and options.
     * This method must be implemented by concrete command classes.
     *
     * @param  array<string, mixed>  $arguments  The command arguments
     * @param  array<string, mixed>  $options  The command options/flags
     */
    abstract public function __invoke(array $arguments, array $options): void;

    /**
     * Get the command priority for registration order.
     * Override this method to change the registration priority.
     *
     * @return int The priority (higher numbers = higher priority)
     */
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * Get command registration data.
     * Returns data needed for command registration without directly interacting with WP-CLI.
     * This respects the hexagonal architecture by keeping the domain layer pure.
     *
     * @return array{name: string|null, description: string|null, class: string}
     */
    public function getRegistrationData(): array
    {
        return [
            'name' => static::getDefaultName(),
            'description' => static::getDefaultDescription(),
            'class' => static::class,
        ];
    }

    /**
     * Get the default command name from the WpCli attribute.
     *
     * @return string|null The command name or null if not found
     */
    public static function getDefaultName(): ?string
    {
        $class = static::class;

        try {
            $reflectionClass = new \ReflectionClass($class);
            $attributes = $reflectionClass->getAttributes(WpCli::class);

            if ($attributes !== []) {
                /** @var WpCli $attribute */
                $attribute = $attributes[0]->newInstance();

                return $attribute->name;
            }
        } catch (\ReflectionException $e) {
            error_log("Failed to get command name for {$class}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Get the default command description from the WpCli attribute.
     *
     * @return string|null The command description or null if not found
     */
    public static function getDefaultDescription(): ?string
    {
        $class = static::class;

        try {
            $reflectionClass = new \ReflectionClass($class);
            $attributes = $reflectionClass->getAttributes(WpCli::class);

            if ($attributes !== []) {
                /** @var WpCli $attribute */
                $attribute = $attributes[0]->newInstance();

                return $attribute->description ?? null;
            }
        } catch (\ReflectionException $e) {
            error_log("Failed to get command description for {$class}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Get the command description from the WpCli attribute.
     * This method is called by WP CLI to get the command description.
     *
     * @return string The command description
     */
    public static function get_shortdesc(): string
    {
        return static::getDefaultDescription() ?? '';
    }
}
