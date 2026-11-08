<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Support\PhpunitMetadata;

pest()->extend(TestCase::class)->use(MemberLayoutMigrationTestFixtures::class);

test('protected blade views use member layout', function (): void {
    foreach (self::PROTECTED_BLADE_VIEWS as $path) {
        $source = $this->source($path);
        expect($source)->toContain('<x-member-layout')->not->toContain('<x-app-layout');
    }
});

test('protected full page livewire components use member layout', function (): void {
    foreach (self::PROTECTED_LIVEWIRE_COMPONENTS as $path) {
        $source = $this->source($path);
        expect($source)->toContain('layouts.member')->not->toContain('layouts.admin');
    }
});

test('public and auth pages keep the public layout', function (): void {
    foreach (['resources/views/pages/home.blade.php', 'resources/views/pages/satzung.blade.php', 'resources/views/veranstaltungen/show.blade.php', 'resources/views/auth/login.blade.php'] as $path) {
        expect($this->source($path))->toContain('<x-app-layout');
    }
});

test('obsolete admin layout was removed', function (): void {
    $this->assertFileDoesNotExist($this->projectPath('resources/views/layouts/admin.blade.php'));
});

PhpunitMetadata::onFile(__FILE__, CoversNothing::class);
