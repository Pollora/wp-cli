<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/*
 * ARCH-07: the harness preset (WordPress classes and functions, the tests), plus what is specific to this package.
 */
$configuration = require __DIR__.'/vendor/pollora/harness/presets/composer-dependency-analyser.php';

return $configuration
    // The discovery package brings spatie/php-structure-discoverer: its data classes are part of discovery's contract.
    ->ignoreErrorsOnPackage('spatie/php-structure-discoverer', [ErrorType::SHADOW_DEPENDENCY])
    // WP-CLI runs the commands and provides WP_CLI: it is required for the tests and suggested, and the adapter checks for it first.
    ->ignoreErrorsOnPackage('wp-cli/wp-cli', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
