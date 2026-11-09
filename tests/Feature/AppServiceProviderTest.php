<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use App\View\Components\AccessibleFile;
use App\View\Components\AccessibleInput;
use App\View\Components\AccessiblePassword;
use App\View\Components\AccessibleSelect;
use App\View\Components\AccessibleTextarea;
use App\View\Components\Alert;
use App\View\Components\NavigationDropdown;
use App\View\Components\NavigationMain;
use App\View\Components\NavigationMenuSeparator;
use App\View\Components\NavigationMenuSub;
use App\View\Components\NavigationThemeToggle;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use Mary\View\Components\Button as MaryButton;
use Mary\View\Components\Dropdown;
use Mary\View\Components\Icon;
use Mary\View\Components\ListItem;
use Mary\View\Components\Main as MaryMain;
use Mary\View\Components\Menu as MaryMenu;
use Mary\View\Components\MenuItem;
use Mary\View\Components\MenuSeparator;
use Mary\View\Components\Nav as MaryNav;
use Mary\View\Components\ThemeToggle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(AppServiceProviderTestFixtures::class);

test('command starting creates fresh console and scheduler execution context', function (): void {
    $previousExecutionId = null;
    foreach (['schedule:run' => 'scheduler', 'queue:work' => 'console'] as $command => $type) {
        event(new CommandStarting($command, new ArrayInput([]), new NullOutput));
        $executionId = Context::get('execution_id');
        expect($executionId)->not->toBeEmpty()->not->toBe($previousExecutionId)
            ->and(Context::get('correlation_id'))->toBe($executionId)
            ->and(Context::get('execution_type'))->toBe($type)
            ->and(Context::get('execution_name'))->toBe($command);
        $previousExecutionId = $executionId;
    }
});

test('guest layout component preserves page metadata and content', function (): void {
    $this->withoutVite();
    $html = Blade::render('<x-guest-layout title="Gastseite" description="Beschreibung der Gastseite">Öffentlicher Inhalt</x-guest-layout>');
    expect($html)->toContain('<title>Gastseite</title>')
        ->toContain('content="Beschreibung der Gastseite"')
        ->toContain('Öffentlicher Inhalt')
        ->toContain('property="og:image"');
});

test('password reset does not change existing verified timestamp', function (): void {
    // Create a verified user with a specific timestamp
    $timestamp = now()->subDay();
    $user = User::factory()->create(['email_verified_at' => $timestamp]);
    // Dispatching a password reset should not change the timestamp
    event(new PasswordReset($user));
    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->email_verified_at->getTimestamp())->toEqual($timestamp->getTimestamp());
});

test('social image defaults to logo when no image provided', function (): void {
    $this->withoutVite();
    $view = view('layouts.guest', ['slot' => '']);
    $view->render();
    $socialImage = $view->getData()['socialImage'];
    expect($socialImage)->toContain('omxfc-logo')
        ->toEndWith('.png');
});

test('social image uses asset for relative path', function (): void {
    $this->withoutVite();
    $view = view('layouts.guest', ['slot' => '', 'image' => 'images/custom.png']);
    $view->render();
    expect($view->getData()['socialImage'])->toBe(asset('images/custom.png'));
});

test('social image uses provided absolute url', function (): void {
    $this->withoutVite();
    $url = 'https://example.com/social.png';
    $view = view('layouts.guest', ['slot' => '', 'image' => $url]);
    $view->render();
    expect($view->getData()['socialImage'])->toBe($url);
});

test('app layout skips vite assets for minimal test layout', function (): void {
    Config::set('app.testing_minimal_layout', true);
    expect(config('app.testing_skip_vite_assets'))->toBeTrue();
    $html = view('layouts.app', ['slot' => 'Testinhalt'])->render();
    expect($html)->toContain('Testinhalt')->not->toContain('resources/css/app.css')->not->toContain('resources/js/app.js');
});

test('app layout keeps vite assets without minimal test layout', function (): void {
    $this->withTemporaryViteManifest(function (): void {
        $html = view('layouts.app', ['slot' => 'Testinhalt'])->render();
        expect($html)->toContain('/'.self::TEST_VITE_BUILD_DIRECTORY.'/'.self::TEST_VITE_ASSET)
            ->toContain('/'.self::TEST_VITE_BUILD_DIRECTORY.'/'.self::TEST_VITE_SCRIPT_ASSET);
    });
});

test('member layout skips vite assets for minimal test layout', function (): void {
    Config::set('app.testing_minimal_layout', true);
    $html = view('layouts.member', ['slot' => 'Interner Testinhalt'])->render();
    expect($html)->toContain('Interner Testinhalt')
        ->toContain('noindex, nofollow')->not->toContain('resources/css/app.css')->not->toContain('resources/js/app.js');
});

test('navigation shell uses explicit maryui component aliases', function (): void {
    $aliases = app('blade.compiler')->getClassComponentAliases();
    expect($aliases['mary-button'] ?? null)->toBe(MaryButton::class)
        ->and($aliases['mary-dropdown'] ?? null)->toBe(NavigationDropdown::class)
        ->and($aliases['mary-icon'] ?? null)->toBe(Icon::class)
        ->and($aliases['mary-list-item'] ?? null)->toBe(ListItem::class)
        ->and($aliases['mary-main'] ?? null)->toBe(NavigationMain::class)
        ->and($aliases['mary-menu'] ?? null)->toBe(MaryMenu::class)
        ->and($aliases['mary-menu-item'] ?? null)->toBe(MenuItem::class)
        ->and($aliases['mary-menu-separator'] ?? null)->toBe(NavigationMenuSeparator::class)
        ->and($aliases['mary-menu-sub'] ?? null)->toBe(NavigationMenuSub::class)
        ->and($aliases['mary-nav'] ?? null)->toBe(MaryNav::class)
        ->and($aliases['mary-theme-toggle'] ?? null)->toBe(NavigationThemeToggle::class)
        ->and(is_subclass_of(NavigationDropdown::class, Dropdown::class))->toBeTrue()
        ->and(is_subclass_of(NavigationMain::class, MaryMain::class))->toBeTrue()
        ->and(is_subclass_of(NavigationMenuSeparator::class, MenuSeparator::class))->toBeTrue()
        ->and(is_subclass_of(NavigationThemeToggle::class, ThemeToggle::class))->toBeTrue()
        ->and(view()->exists('layouts.admin'))->toBeFalse();
});

test('rebooting the provider preserves console field doubles and real prefixed components', function (): void {
    expect($this->app->runningInConsole())->toBeTrue()
        ->and($this->app->runningUnitTests())->toBeTrue();
    (new AppServiceProvider($this->app))->boot();
    $aliases = app('blade.compiler')->getClassComponentAliases();
    foreach (['input', 'password', 'select', 'file'] as $alias) {
        expect($aliases[$alias] ?? null)->toBe('testing.components.'.$alias);
    }
    foreach (['input' => AccessibleInput::class, 'password' => AccessiblePassword::class, 'select' => AccessibleSelect::class, 'textarea' => AccessibleTextarea::class, 'file' => AccessibleFile::class] as $alias => $component) {
        expect($aliases['mary-'.$alias] ?? null)->toBe($component);
    }
});

test('navigation main removes the invalid drawer overlay aria label', function (): void {
    $template = (new NavigationMain)->render();
    expect($template)->toBeString()
        ->toContain('class="drawer-overlay"')
        ->toContain('aria-label="Seitennavigation öffnen oder schließen"')->not->toContain('aria-label="close sidebar"');
});

test('navigation menu sub does not rely on the global str alias', function (): void {
    $template = (new NavigationMenuSub)->render();
    expect($template)->toBeString()
        ->toContain('\Illuminate\Support\Str::contains')->not->toContain('$submenuActive = Str::contains');
});

test('navigation theme toggle guards its inline storage bootstrap', function (): void {
    $template = (new NavigationThemeToggle)->render();
    expect($template)->toBeString()
        ->toContain('try {')
        ->toContain('if (root && storedTheme !== null)')
        ->toContain('if (root && storedClass !== null)')
        ->toContain('} catch {}')
        ->toContain('aria-label="Farbschema umschalten"')->not->toContain('setAttribute("data-theme", localStorage.getItem');
});

test('navigation adapters keep valid list and status semantics', function (): void {
    $dropdown = (new NavigationDropdown)->render();
    $separator = (new NavigationMenuSeparator)->render();
    $alert = (new Alert(dismissible: true))->render();
    expect($dropdown)->toBeString()
        ->toContain('{{ $slot }}')->not->toContain('<div wire:key="dropdown-slot-')
        ->and($separator)->toBeString()
        ->toContain('<li role="separator"')
        ->and($alert)->toBeString()
        ->toContain("'role' => 'status'")
        ->toContain('aria-label="Schließen"');
});

test('testing environment ignores standard vite hot file', function (): void {
    $hotPath = public_path('hot');
    $originalHotExists = is_file($hotPath);
    $originalHotContents = $originalHotExists ? file_get_contents($hotPath) : null;
    $this->withTemporaryViteManifest(function () use ($hotPath, $originalHotExists, $originalHotContents): void {
        file_put_contents($hotPath, 'http://127.0.0.1:5999');
        try {
            $html = Blade::render("@vite(['resources/css/app.css'])");
            expect($html)->not->toContain('http://127.0.0.1:5999')
                ->toContain('/'.self::TEST_VITE_BUILD_DIRECTORY.'/'.self::TEST_VITE_ASSET);
        } finally {
            if ($originalHotExists) {
                file_put_contents($hotPath, $originalHotContents ?: '');
            } elseif (is_file($hotPath)) {
                unlink($hotPath);
            }
        }
    });
});

test('playwright docker environment uses dedicated vite hot file', function (): void {
    $hotPath = public_path('playwright.hot');
    $originalHotExists = is_file($hotPath);
    $originalHotContents = $originalHotExists ? file_get_contents($hotPath) : null;
    $originalPlaywrightUseDocker = getenv('PLAYWRIGHT_USE_DOCKER');
    file_put_contents($hotPath, 'http://127.0.0.1:5173');
    putenv('PLAYWRIGHT_USE_DOCKER=1');
    $_ENV['PLAYWRIGHT_USE_DOCKER'] = '1';
    $_SERVER['PLAYWRIGHT_USE_DOCKER'] = '1';
    try {
        $this->refreshApplication();
        $html = Blade::render("@vite(['resources/css/app.css'])");
        expect($html)->toContain('http://127.0.0.1:5173')
            ->toContain('resources/css/app.css');
    } finally {
        if ($originalPlaywrightUseDocker === false) {
            putenv('PLAYWRIGHT_USE_DOCKER');
            unset($_ENV['PLAYWRIGHT_USE_DOCKER'], $_SERVER['PLAYWRIGHT_USE_DOCKER']);
        } else {
            putenv("PLAYWRIGHT_USE_DOCKER={$originalPlaywrightUseDocker}");
            $_ENV['PLAYWRIGHT_USE_DOCKER'] = $originalPlaywrightUseDocker;
            $_SERVER['PLAYWRIGHT_USE_DOCKER'] = $originalPlaywrightUseDocker;
        }
        if ($originalHotExists) {
            file_put_contents($hotPath, $originalHotContents ?: '');
        } elseif (is_file($hotPath)) {
            unlink($hotPath);
        }
        $this->refreshApplication();
    }
});
