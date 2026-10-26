# Changelog

All notable changes to `pollora/wp-cli` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Registering a command outside WP-CLI throws the named `WpCliUnavailable` exception instead of a bare `\RuntimeException`. It extends `\RuntimeException`, and the message keeps its `WP-CLI is not available` prefix, followed by how to avoid it

## [0.1.1] - 2026-10-13

### Fixed

- Commands get their constructor dependencies: WP-CLI received the class name and built it with `new $class()`, so a single command (`__invoke()`) or a public method without `#[Command]` failed when the constructor required anything. WP-CLI now receives the instance the discovery built, which runs every command and subcommand
- A non-public method with `#[Command]` registers: `WpCliService::register()` accepted only a class name or an array, and refused the callable built for it with a `TypeError`

## [0.1.0] - 2026-10-13

A first version for the framework's next release to build on; 1.0.0 comes with that release.

### Added

- First release, extracted from `pollora/framework` (`src/WpCli`) with its history. Class names are unchanged: `Pollora\WpCli\*`
- Runs without Laravel's application: `WpCliDiscovery` builds `#[Command]` subcommands through the discovery's instance resolver, with their constructor dependencies. An architecture test keeps the package to the attributes, discovery, PSR logging and WP-CLI
