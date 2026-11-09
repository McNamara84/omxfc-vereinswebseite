<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Romantausch;

use App\Services\Romantausch\BookPhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait BookPhotoServiceTestFixtures
{
    use RefreshDatabase;

    private BookPhotoService $service;
}
