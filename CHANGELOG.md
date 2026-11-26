# Changelog

All notable changes to `pollora/wp-cli` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- PHPStan runs at level 10 (was 5), with no ignored error. The PHPDoc of `WpCliService` and of the adapter now says what they accept and return

### Fixed

- `WpCliAdapter::hasCommand()` answered `true` for any name while WP-CLI was running: the runner returns an error message, not `null`, for a command it does not know
- A handler array naming a private or protected method is refused with `InvalidArgumentException`, which `WpCliService` logs. WP-CLI received it, reported the callable as missing and stopped the whole `wp` run
- A command discovered without a reflection cache, which `discover()` makes optional, was never registered

## [0.1.3] - 2026-11-23

### Changed

- Accepts `pollora/discovery` ^0.3 (`^0.1 || ^0.2 || ^0.3 || ^1.0`)

## [0.1.2] - 2026-11-19

### Changed

- Accepts pollora/discovery 0.2 (`^0.1 || ^0.2 || ^1.0`)
- `WpCliService` now depends on the new `WpCliAdapterInterface` contract (implemented by `WpCliAdapter`) instead of the concrete adapter, so that the application layer no longer depends on the infrastructure layer. Passing a `WpCliAdapter` keeps working
- Registering a command outside WP-CLI throws the named `WpCliUnavailableException` exception instead of a bare `\RuntimeException`. It extends `\RuntimeException`, and the message keeps its `WP-CLI is not available` prefix, followed by how to avoid it

### Changed

## [0.1.1] - 2026-10-13

### Fixed

- Commands get their constructor dependencies: WP-CLI received the class name and built it with `new $class()`, so a single command (`__invoke()`) or a public method without `#[Command]` failed when the constructor required anything. WP-CLI now receives the instance the discovery built, which runs every command and subcommand
- A non-public method with `#[Command]` registers: `WpCliService::register()` accepted only a class name or an array, and refused the callable built for it with a `TypeError`

## [0.1.0] - 2026-10-13

A first version for the framework's next release to build on; 1.0.0 comes with that release.

### Added

- First release, extracted from `pollora/framework` (`src/WpCli`) with its history. Class names are unchanged: `Pollora\WpCli\*`
- Runs without Laravel's application: `WpCliDiscovery` builds `#[Command]` subcommands through the discovery's instance resolver, with their constructor dependencies. An architecture test keeps the package to the attributes, discovery, PSR logging and WP-CLI
