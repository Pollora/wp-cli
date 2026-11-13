<?php

declare(strict_types=1);

use Pollora\Attributes\WpCli;
use Pollora\WpCli\Domain\Models\WordpressCommand;

/**
 * Greets someone.
 */
#[WpCli('greet')]
final class GreetWordpressCommandFixture extends WordpressCommand
{
    public function __invoke(array $arguments, array $options): void {}

    public function initialize(): void {}
}

#[WpCli]
final class SlugFromClassWordpressCommandFixture extends WordpressCommand
{
    public function __invoke(array $arguments, array $options): void {}

    public function initialize(): void {}
}

final class UndeclaredWordpressCommandFixture extends WordpressCommand
{
    public function __invoke(array $arguments, array $options): void {}

    public function initialize(): void {}
}

final class PrioritisedWordpressCommandFixture extends WordpressCommand
{
    public function __invoke(array $arguments, array $options): void {}

    public function initialize(): void {}

    public function getPriority(): int
    {
        return 20;
    }
}

it('takes its command name from the #[WpCli] attribute', function (): void {
    expect(GreetWordpressCommandFixture::getDefaultName())->toBe('greet');
});

it('derives the command name from the class name when the attribute gives none', function (): void {
    $name = SlugFromClassWordpressCommandFixture::getDefaultName();

    expect($name)->toBeString()->not->toBeEmpty();
});

it('has no command name without the #[WpCli] attribute', function (): void {
    expect(UndeclaredWordpressCommandFixture::getDefaultName())->toBeNull();
});

it('has no default description without the attribute', function (): void {
    expect(UndeclaredWordpressCommandFixture::getDefaultDescription())->toBeNull();
});

it('gives WP-CLI an empty short description when none is declared', function (): void {
    expect(UndeclaredWordpressCommandFixture::get_shortdesc())->toBe('');
});

it('describes its registration without touching WP-CLI', function (): void {
    expect((new GreetWordpressCommandFixture)->getRegistrationData())->toBe([
        'name' => 'greet',
        'description' => GreetWordpressCommandFixture::getDefaultDescription(),
        'class' => GreetWordpressCommandFixture::class,
    ]);
});

it('registers with priority 0 unless a command overrides it', function (): void {
    expect((new GreetWordpressCommandFixture)->getPriority())->toBe(0)
        ->and((new PrioritisedWordpressCommandFixture)->getPriority())->toBe(20);
});
