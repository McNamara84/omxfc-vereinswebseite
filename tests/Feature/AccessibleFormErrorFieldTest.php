<?php

namespace Tests\Feature;

use App\View\Components\AccessibleFile;
use App\View\Components\AccessibleInput;
use App\View\Components\AccessiblePassword;
use App\View\Components\AccessibleSelect;
use App\View\Components\AccessibleTextarea;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessibleFormErrorFieldTest extends TestCase
{
    #[DataProvider('components')]
    public function test_error_fields_use_explicit_names_bindings_and_html_names_without_null(string $class): void
    {
        $component = (new $class)->withAttributes([]);
        $this->assertSame('', $component->errorFieldName());
        $component->withAttributes(['name' => 'email']);
        $this->assertSame('email', $component->errorFieldName());
        $component->withAttributes(['wire:model' => 'profile.email']);
        $this->assertSame('profile.email', $component->errorFieldName());
        $component->errorField = 'custom.email';
        $this->assertSame('custom.email', $component->errorFieldName());
    }

    public static function components(): iterable
    {
        yield 'input' => [AccessibleInput::class];
        yield 'password' => [AccessiblePassword::class];
        yield 'select' => [AccessibleSelect::class];
        yield 'textarea' => [AccessibleTextarea::class];
        yield 'file' => [AccessibleFile::class];
    }

    public function test_unnamed_controls_do_not_read_unrelated_validation_errors(): void
    {
        $this->withViewErrors(['other' => 'Unrelated validation error'])
            ->blade('<x-accessible-input label="Name" /><x-form-select label="Choice" />')
            ->assertDontSee('Unrelated validation error')
            ->assertSee('aria-invalid="false"', false);
    }

    public function test_html_name_associates_validation_feedback_with_the_actual_input(): void
    {
        $this->withViewErrors(['email' => 'Invalid email'])
            ->blade('<x-accessible-input id="email" name="email" label="Email" />')
            ->assertSee('Invalid email')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('email-error');
    }

    public function test_console_textarea_does_not_read_unrelated_validation_errors(): void
    {
        $this->withViewErrors(['other' => 'Unrelated validation error'])
            ->blade('<x-textarea label="Notes" />')
            ->assertDontSee('Unrelated validation error')
            ->assertSee('aria-invalid="false"', false);
    }
}
