<?php

declare(strict_types=1);

use Pollora\WpCli\Infrastructure\Providers\WpCliServiceProvider;
use Pollora\WpCli\UI\Console\WpCliMakeCommand;

/**
 * The WpCli module becomes pollora/wp-cli, which runs without Laravel's
 * application. Its service provider and the pollora:make:wp-cli generator stay
 * in the framework; the rest uses the attributes, discovery, PSR logging and
 * WP-CLI.
 */
arch('the WpCli module stays free of the Laravel application')
    ->expect('Pollora\WpCli')
    ->toOnlyUse([
        'Pollora\WpCli',
        'Pollora\Attributes',
        'Pollora\Discovery',
        'Psr\Log',
        'Spatie\StructureDiscoverer',
    ])
    ->ignoring([WpCliServiceProvider::class, WpCliMakeCommand::class, 'WP_CLI', 'WP_CLI_Command']);
