<?php

declare(strict_types=1);

arch('application entry points do not use debugging helpers')
    ->expect(['App\\Http\\Controllers', 'App\\Livewire', 'App\\Services'])
    ->not->toUse(['dd', 'ray']);

arch('controller classes keep the project naming convention')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toHaveSuffix('Controller');

arch('policies keep the project naming convention')
    ->expect('App\Policies')
    ->toBeClasses()
    ->toHaveSuffix('Policy');
