<?php

declare(strict_types=1);

namespace Pollora\WpCli\Infrastructure\Services;

use Pollora\Attributes\WpCli;
use Pollora\Attributes\WpCli\AfterInvoke;
use Pollora\Attributes\WpCli\BeforeInvoke;
use Pollora\Attributes\WpCli\Command;
use Pollora\Attributes\WpCli\IsDeferred;
use Pollora\Attributes\WpCli\Synopsis;
use Pollora\Attributes\WpCli\When;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Services\IsDiscovery;
use Pollora\WpCli\Application\Services\WpCliService;
use ReflectionClass;
use ReflectionMethod;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;

/**
 * WP CLI Command Discovery Service
 *
 * Discovers classes decorated with WpCli attributes and registers them
 * as WordPress CLI commands. This discovery class scans for classes that have
 * the #[WpCli] attribute and processes both class-level and method-level commands.
 *
 * The discovery process:
 * 1. Finds classes with the WpCli attribute
 * 2. Validates that classes are instantiable
 * 3. Collects them for registration
 * 4. Registers them directly with WP CLI during the apply phase
 * 5. Processes method-level #[Command] attributes for subcommands
 */
final class WpCliDiscovery implements DiscoveryInterface
{
    use IsDiscovery;

    /**
     * Create a new WP CLI discovery service.
     *
     * @param WpCliService $wpCliService The WP CLI service for command management
     */
    public function __construct(
        private readonly WpCliService $wpCliService
    ) {}

    /**
     * {@inheritDoc}
     *
     * Discovers classes with WpCli attributes and collects them for registration.
     * Only processes classes that have the WpCli attribute and are instantiable.
     */
    public function discover(DiscoveryLocationInterface $location, DiscoveredStructure $structure): void
    {
        // Only process classes
        if (! $structure instanceof DiscoveredClass) {
            return;
        }

        // Check if class has WpCli attribute
        $commandAttribute = null;

        foreach ($structure->attributes as $attribute) {
            if ($attribute->class === WpCli::class) {
                $commandAttribute = $attribute;
                break;
            }
        }

        if ($commandAttribute === null) {
            return;
        }

        // Skip abstract classes
        if ($structure->isAbstract) {
            return;
        }

        // Collect the class for registration
        $this->getItems()->add($location, [
            'class' => $structure->namespace . '\\' . $structure->name,
            'attribute' => $commandAttribute,
            'structure' => $structure,
        ]);
    }

    /**
     * {@inheritDoc}
     *
     * Applies discovered WP CLI command classes by processing their attributes and
     * registering them with WordPress CLI. This includes validating command classes
     * and initializing them for WP CLI registration.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $discoveredItem) {
            [
                'class' => $className,
                'attribute' => $commandAttribute,
                'structure' => $structure
            ] = $discoveredItem;

            try {
                $this->processWpCliCommand($className);
            } catch (\Throwable $e) {
                error_log("Failed to register WP CLI command from class {$className}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process a WP CLI command class for registration.
     *
     * This method handles the command registration process by:
     * 1. Validating the command class
     * 2. Processing the WpCli attribute
     * 3. Checking for method-level Command attributes (subcommands)
     * 4. Registering it directly with WP CLI
     *
     * @param string $className The fully qualified class name
     */
    private function processWpCliCommand(string $className): void
    {
        try {
            $reflectionClass = new ReflectionClass($className);

            // Validate that the class is instantiable
            if (!$reflectionClass->isInstantiable()) {
                return;
            }

            // Get the WpCli attribute
            $attributes = $reflectionClass->getAttributes(WpCli::class);
            if ($attributes === []) {
                return;
            }

            /** @var WpCli $attribute */
            $attribute = $attributes[0]->newInstance();
            $commandName = $attribute->getCommandName($className);

            if (empty($commandName)) {
                error_log("WP CLI command {$className} has no command name defined");
                return;
            }

            // Check if this class has method-level Command attributes (subcommands)
            $hasSubcommands = $this->hasMethodLevelCommands($reflectionClass);

            if ($hasSubcommands) {
                // Register subcommands
                $this->processSubcommands($reflectionClass, $className, $commandName, $attribute);
            } else {
                // Register as single command
                $this->registerSingleCommand($className, $commandName, $attribute);
            }

        } catch (\ReflectionException $e) {
            error_log("Failed to process WP CLI command for class {$className}: " . $e->getMessage());
        }
    }

    /**
     * Check if the class has method-level Command attributes.
     *
     * @param ReflectionClass $reflectionClass The reflection class
     * @return bool True if the class has subcommands
     */
    private function hasMethodLevelCommands(ReflectionClass $reflectionClass): bool
    {
        foreach ($reflectionClass->getMethods() as $method) {
            $commandAttributes = $method->getAttributes(Command::class);
            if ($commandAttributes !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Register a single command (class with __invoke method).
     *
     * @param string $className The class name
     * @param string $commandName The command name
     * @param WpCli $attribute The WpCli attribute
     */
    private function registerSingleCommand(string $className, string $commandName, WpCli $attribute): void
    {
        // Register directly with WP CLI if available
        if (\defined('WP_CLI') && WP_CLI) {
            try {
                $reflectionClass = new ReflectionClass($className);
                $args = $this->collectClassArguments($reflectionClass);
                \WP_CLI::add_command($commandName, $className, $args);
            } catch (\ReflectionException $e) {
                error_log("Failed to collect class arguments for {$className}: " . $e->getMessage());
                \WP_CLI::add_command($commandName, $className);
            }
        }

        // Also register with our service for tracking
        $args = [];
        try {
            $reflectionClass = new ReflectionClass($className);
            $args = $this->collectClassArguments($reflectionClass);
        } catch (\ReflectionException $e) {
            error_log("Failed to collect class arguments for {$className}: " . $e->getMessage());
        }
        
        $this->wpCliService->register(
            $commandName,
            $className,
            '',
            0, // Default priority
            $args
        );
    }

    /**
     * Process and register subcommands for a class.
     *
     * @param ReflectionClass $reflectionClass The reflection class
     * @param string $className The class name
     * @param string $baseCommandName The base command name
     * @param WpCli $attribute The WpCli attribute
     */
    private function processSubcommands(ReflectionClass $reflectionClass, string $className, string $baseCommandName, WpCli $attribute): void
    {
        // First register the base class command with class-level arguments
        if (\defined('WP_CLI') && WP_CLI) {
            try {
                $args = $this->collectClassArguments($reflectionClass);
                \WP_CLI::add_command($baseCommandName, $className, $args);
            } catch (\ReflectionException $e) {
                error_log("Failed to collect class arguments for {$className}: " . $e->getMessage());
                \WP_CLI::add_command($baseCommandName, $className);
            }
        }

        // Also register the base command with our service for tracking
        $args = [];
        try {
            $args = $this->collectClassArguments($reflectionClass);
        } catch (\ReflectionException $e) {
            error_log("Failed to collect class arguments for {$className}: " . $e->getMessage());
        }
        
        $this->wpCliService->register(
            $baseCommandName,
            $className,
            '',
            0, // Default priority
            $args
        );

        // Then register methods with Command attributes as individual subcommands
        foreach ($reflectionClass->getMethods() as $method) {
            $commandAttributes = $method->getAttributes(Command::class);

            if ($commandAttributes === []) {
                continue;
            }

            /** @var Command $commandAttribute */
            $commandAttribute = $commandAttributes[0]->newInstance();
            $subcommandName = $commandAttribute->getSubcommandName($method->getName());
            $fullCommandName = "{$baseCommandName} {$subcommandName}";

            // Create a callable array for the subcommand
            // For private/protected methods, we need to use a wrapper with invade
            if ($method->isPrivate() || $method->isProtected()) {
                $callable = $this->createInvadeWrapper($className, $method->getName());
            } else {
                $callable = [$className, $method->getName()];
            }

            // Register subcommand with WP CLI using method-level arguments
            if (\defined('WP_CLI') && WP_CLI) {
                try {
                    $args = $this->collectMethodArguments($method);
                    \WP_CLI::add_command($fullCommandName, $callable, $args);
                } catch (\ReflectionException $e) {
                    error_log("Failed to collect method arguments for {$className}::{$method->getName()}: " . $e->getMessage());
                    \WP_CLI::add_command($fullCommandName, $callable);
                }
            }

            // Also register with our service for tracking
            $methodArgs = [];
            try {
                $methodArgs = $this->collectMethodArguments($method);
            } catch (\ReflectionException $e) {
                error_log("Failed to collect method arguments for {$className}::{$method->getName()}: " . $e->getMessage());
            }
            
            $this->wpCliService->register(
                $fullCommandName,
                $callable,
                '',
                0, // Default priority
                $methodArgs
            );
        }
    }

    /**
     * Collect WP CLI arguments from class attributes.
     *
     * @param ReflectionClass $reflectionClass The reflection class
     * @return array The collected arguments for WP_CLI::add_command()
     */
    private function collectClassArguments(ReflectionClass $reflectionClass): array
    {
        return $this->collectWpCliArguments($reflectionClass);
    }

    /**
     * Collect WP CLI arguments from method attributes.
     *
     * @param ReflectionMethod $reflectionMethod The reflection method
     * @return array The collected arguments for WP_CLI::add_command()
     */
    private function collectMethodArguments(ReflectionMethod $reflectionMethod): array
    {
        return $this->collectWpCliArguments($reflectionMethod);
    }

    /**
     * Create an invade wrapper for calling private/protected methods.
     *
     * @param string $className The class name
     * @param string $methodName The method name
     * @return array A callable array that uses invade
     */
    private function createInvadeWrapper(string $className, string $methodName): array
    {
        return [new class($className, $methodName) {
            private string $className;
            private string $methodName;

            public function __construct(string $className, string $methodName)
            {
                $this->className = $className;
                $this->methodName = $methodName;
            }

            public function __invoke(array $args, array $assocArgs): mixed
            {
                $instance = app($this->className);
                $invadedInstance = invade($instance);
                return $invadedInstance->{$this->methodName}($args, $assocArgs);
            }
        }, '__invoke'];
    }

    /**
     * Collect WP CLI arguments from reflection attributes.
     *
     * @param ReflectionClass|ReflectionMethod $reflection The reflection object (class or method)
     * @return array The collected arguments for WP_CLI::add_command()
     */
    private function collectWpCliArguments(ReflectionClass|ReflectionMethod $reflection): array
    {
        $args = [];

        // Define attribute mappings: [AttributeClass => [property, wp_cli_key]]
        $attributeMap = [
            BeforeInvoke::class => ['callback', 'before_invoke'],
            AfterInvoke::class => ['callback', 'after_invoke'],
            Synopsis::class => ['synopsis', 'synopsis'],
            When::class => ['hook', 'when'],
            IsDeferred::class => ['deferred', 'is_deferred'],
        ];

        foreach ($attributeMap as $attributeClass => [$property, $wpCliKey]) {
            $attributes = $reflection->getAttributes($attributeClass);
            if ($attributes !== []) {
                $attribute = $attributes[0]->newInstance();
                $args[$wpCliKey] = $attribute->$property;
            }
        }

        return $args;
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifier(): string
    {
        return 'wp_cli_commands';
    }
}
