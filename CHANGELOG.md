# Changelog

All notable changes to `pollora/wp-cli` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

A first version for the framework's next release to build on; 1.0.0 comes with that release.

### Added

- First release, extracted from `pollora/framework` (`src/WpCli`) with its history. Class names are unchanged: `Pollora\WpCli\*`
- Runs without Laravel's application: `WpCliDiscovery` builds `#[Command]` subcommands through the discovery's instance resolver, with their constructor dependencies. An architecture test keeps the package to the attributes, discovery, PSR logging and WP-CLI
