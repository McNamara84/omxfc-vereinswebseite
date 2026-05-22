<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Browser');

pest()->browser()->timeout(15000);