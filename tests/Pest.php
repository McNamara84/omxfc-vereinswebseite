<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Browser');

pest()->browser()->timeout(30000);

// TIA stays opt-in locally and consumes the shared main-branch baseline.
// TIA commands deliberately run without explicit test paths so Pest 5 can use the graph.
pest()->tia()
    ->baselined()
    ->filtered();
