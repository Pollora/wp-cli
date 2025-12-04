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
use Pollora\Discovery\Domain\Services\HasInstancePool;
use Pollora\Discovery\Domain\Services\IsDiscovery;
use Pollora\WpCli\Application\Services\WpCliService;
use Pollora\WpCli\Infrastructure\Adapters\WpCliMethodWrapper;
use ReflectionClass;
use ReflectionMethod;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;

/**
 * WP CLI Command Discovery Service
 *
 * Discovers classes decorated with WpCli attributes and registers them
 * as WordPress CLI commands.
 */
final class WpCliDiscovery implements DiscoveryInterface
{
    use HasInstancePool, IsDiscovery;

    /**
     * @var array<class-string, object>
     */
    private array $commandInstances = [];

    public function __construct(
        private readonly WpCliService $wpCliService
    ) {}

    /**
     * {@inheritDoc}
     */
    public function discover(DiscoveryLocationInterface $location, DiscoveredStructure $structure): void
    {
        if (! $structure instanceof DiscoveredClass || $structure->isAbstract) {
            return;
        }

        foreach ($structure->attributes as $attribute) {
            if ($attribute->class === WpCli::class) {
                $this->getItems()->add($location, [
                    'class' => $structure->namespace.'\\'.$structure->name,
                ]);

                return;
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $discoveredItem) {
            try {
                $this->processWpCliCommand($discoveredItem['class']);
            } catch (\Throwable $e) {
                error_log("Failed to register WP CLI command from class {$discoveredItem['class']}: ".$e->getMessage());
            }
        }
    }

    /**
     * Process a WP CLI command class for registration.
     */
    private function processWpCliCommand(string $className): void
    {
        $reflectionClass = new ReflectionClass($className);

        if (! $reflectionClass->isInstantiable()) {
            return;
        }

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

        // On garde quand même l'instance dispo pour les subcommands
        $instance = $this->getCommandInstance($className);

        if ($this->hasSubcommands($reflectionClass)) {
            $this->processSubcommands($reflectionClass, $className, $commandName, $instance);
        } else {
            // IMPORTANT : on repasse au class-string, pas à l'instance
            $this->registerCommand(
                $commandName,
                $className,
                $this->collectWpCliArguments($reflectionClass)
            );
        }
    }

    /**
     * Retourne une instance unique de la classe de commande.
     *
     * @param  class-string  $className
     */
    private function getCommandInstance(string $className): object
    {
        // Use instance pool if available, otherwise fallback to local cache
        return $this->getInstanceFromPool($className, function () use ($className) {
            if (! isset($this->commandInstances[$className])) {
                // On laisse le container gérer la construction
                $this->commandInstances[$className] = app($className);
            }

            return $this->commandInstances[$className];
        });
    }

    /**
     * Check if the class has subcommands (no __invoke and has #[Command] methods).
     */
    private function hasSubcommands(ReflectionClass $reflectionClass): bool
    {
        if ($reflectionClass->hasMethod('__invoke')) {
            return false;
        }

        foreach ($reflectionClass->getMethods() as $method) {
            if ($method->getAttributes(Command::class) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process and register subcommands for a class.
     */
    private function processSubcommands(
        ReflectionClass $reflectionClass,
        string $className,
        string $baseCommandName,
        object $instance
    ): void {
        // Base command => class-string, comme avant
        $this->registerCommand(
            $baseCommandName,
            $className,
            $this->collectWpCliArguments($reflectionClass)
        );

        // Subcommands => [instance, méthode]
        foreach ($reflectionClass->getMethods() as $method) {
            $commandAttributes = $method->getAttributes(Command::class);
            if ($commandAttributes === []) {
                continue;
            }

            /** @var Command $commandAttribute */
            $commandAttribute = $commandAttributes[0]->newInstance();
            $subcommandName = $commandAttribute->getSubcommandName($method->getName());
            $fullCommandName = "{$baseCommandName} {$subcommandName}";

            $handler = $this->createCallable($instance, $method);

            $this->registerCommand(
                $fullCommandName,
                $handler,
                $this->collectWpCliArguments($method)
            );
        }
    }

    /**
     * Register a command through the WP CLI service only.
     * This ensures single responsibility and avoids duplication.
     *
     * @param  string|array|object  $handler
     * @param  array<string,mixed>  $args
     */
    private function registerCommand(string $commandName, string|array $handler, array $args = []): void
    {
        // Delegate to the application service which handles WP-CLI registration
        $this->wpCliService->register($commandName, $handler, '', 0, $args);
    }

    /**
     * Create a callable for a method (handles private/protected methods).
     */
    private function createCallable(object $instance, ReflectionMethod $method): array
    {
        if ($method->isPublic()) {
            // Cas simple : WP-CLI peut appeler directement [instance, 'methodName']
            return [$instance, $method->getName()];
        }

        // Pour les méthodes non publiques, on doit préserver la documentation
        // On retourne directement l'instance et le nom de la méthode, mais on rend la méthode accessible
        $method->setAccessible(true);

        // Créer un wrapper qui se comporte comme la méthode originale
        $wrapper = new WpCliMethodWrapper($instance, $method);

        // Retourner un callable qui préserve l'accès à la documentation
        return [$wrapper, '__invoke'];
    }

    /**
     * Collect WP CLI arguments from reflection attributes.
     */
    private function collectWpCliArguments(ReflectionClass|ReflectionMethod $reflection): array
    {
        $args = [];

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
                $args[$wpCliKey] = $attributes[0]->newInstance()->$property;
            }
        }

        // Si c'est une méthode, extraire la documentation du docblock
        if ($reflection instanceof ReflectionMethod) {
            $docComment = $reflection->getDocComment();
            if ($docComment) {
                // Extraire la description courte et longue du docblock
                $description = $this->extractMethodDescription($docComment);
                if (! empty($description['short'])) {
                    $args['shortdesc'] = $description['short'];
                }
                if (! empty($description['long'])) {
                    $args['longdesc'] = $description['long'];
                }
            }
        }

        return $args;
    }

    /**
     * Extract description from method docblock for WP-CLI help.
     */
    private function extractMethodDescription(string $docComment): array
    {
        // Remove /** and */ and leading asterisks
        $cleaned = preg_replace('/^\/\*\*|\*\/$/', '', $docComment);
        $lines = explode("\n", $cleaned);

        $description = ['short' => '', 'long' => ''];
        $inLongDesc = false;
        $longDescLines = [];

        foreach ($lines as $line) {
            $line = trim(ltrim($line, ' *'));

            // Skip empty lines at the beginning
            if (empty($line) && empty($description['short']) && empty($longDescLines)) {
                continue;
            }

            // Stop at @tags
            if (str_starts_with($line, '@') || str_starts_with($line, '##')) {
                break;
            }

            // First non-empty line is the short description
            if (empty($description['short']) && ! empty($line)) {
                $description['short'] = $line;

                continue;
            }

            // After short description, collect long description
            if (! empty($description['short'])) {
                $inLongDesc = true;
                if (! empty($line) || ! empty($longDescLines)) {
                    $longDescLines[] = $line;
                }
            }
        }

        if (! empty($longDescLines)) {
            $description['long'] = trim(implode("\n", $longDescLines));
        }

        return $description;
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifier(): string
    {
        return 'wp_cli_commands';
    }
}
