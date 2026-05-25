<?php

namespace App\Support;

use Illuminate\Support\Facades\Blade;

final class TestingBladeComponentRegistry
{
    public static function register(): void
    {
        Blade::component('testing.components.button', 'button');
        Blade::component('testing.components.badge', 'badge');
        Blade::component('testing.components.checkbox', 'checkbox');
        Blade::component('testing.components.avatar', 'avatar');
        Blade::component('testing.components.file', 'file');
        Blade::component('testing.components.icon', 'icon');
        Blade::component('testing.components.icon', 'svg');
        Blade::component('testing.components.input', 'input');
        Blade::component('testing.components.main', 'main');
        Blade::component('testing.components.mary-modal', 'mary-modal');
        Blade::component('testing.components.mary-modal', 'modal');
        Blade::component('testing.components.password', 'password');
        Blade::component('testing.components.select', 'select');
        Blade::component('testing.components.stat', 'stat');
        Blade::component('testing.components.table', 'table');
        Blade::component('testing.components.theme-toggle', 'theme-toggle');
        Blade::component('testing.components.toast', 'toast');
    }
}