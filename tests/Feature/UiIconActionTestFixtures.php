<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Application as ArtisanApplication;
use Illuminate\Contracts\Console\Kernel;

trait UiIconActionTestFixtures
{
    public function createApplication()
    {
        ArtisanApplication::forgetBootstrappers();
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
