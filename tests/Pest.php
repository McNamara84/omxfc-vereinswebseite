<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Browser');

pest()->browser()->timeout(30000);

// TIA stays opt-in locally. The shared main-branch baseline is available now;
// filtered mode is ready once the native Pest scope no longer needs file paths.
pest()->tia()
    ->baselined()
    ->filtered();
