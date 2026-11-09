<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\AccessibleFile;
use App\View\Components\AccessibleInput;
use App\View\Components\AccessiblePassword;
use App\View\Components\AccessibleSelect;
use App\View\Components\AccessibleTextarea;
use Tests\TestCase;

pest()->extend(TestCase::class);
dataset('AccessibleFormErrorFieldTest.components', static function (): iterable {
    yield 'input' => [AccessibleInput::class];
    yield 'password' => [AccessiblePassword::class];
    yield 'select' => [AccessibleSelect::class];
    yield 'textarea' => [AccessibleTextarea::class];
    yield 'file' => [AccessibleFile::class];
});
test('error fields use explicit names bindings and html names without null', function (string $class): void {
    $component = (new $class)->withAttributes([]);
    expect($component->errorFieldName())->toBe('');
    $component->withAttributes(['name' => 'email']);
    expect($component->errorFieldName())->toBe('email');
    $component->withAttributes(['wire:model' => 'profile.email']);
    expect($component->errorFieldName())->toBe('profile.email');
    $component->errorField = 'custom.email';
    expect($component->errorFieldName())->toBe('custom.email');
})->with('AccessibleFormErrorFieldTest.components');
test('unnamed controls do not read unrelated validation errors', function (): void {
    $this->withViewErrors(['other' => 'Unrelated validation error'])->blade('<x-accessible-input label="Name" /><x-form-select label="Choice" />')->assertDontSee('Unrelated validation error')->assertSee('aria-invalid="false"', false);
});
test('html name associates validation feedback with the actual input', function (): void {
    $this->withViewErrors(['email' => 'Invalid email'])->blade('<x-accessible-input id="email" name="email" label="Email" />')->assertSee('Invalid email')->assertSee('aria-invalid="true"', false)->assertSee('email-error');
});
test('console textarea does not read unrelated validation errors', function (): void {
    $this->withViewErrors(['other' => 'Unrelated validation error'])->blade('<x-textarea label="Notes" />')->assertDontSee('Unrelated validation error')->assertSee('aria-invalid="false"', false);
});
