<?php

declare(strict_types=1);

/**
 * pollora/wp-cli runs without Laravel's application: it uses the attributes,
 * discovery, PSR logging and WP-CLI, nothing else. The Laravel service provider
 * and the pollora:make:wp-cli generator live in the framework.
 */
arch('wp-cli stays free of the Laravel application')
    ->expect('Pollora\WpCli')
    ->toOnlyUse([
        'Pollora\WpCli',
        'Pollora\Attributes',
        'Pollora\Discovery',
        'Psr\Log',
        'Spatie\StructureDiscoverer',
    ])
    ->ignoring(['WP_CLI', 'WP_CLI_Command']);
