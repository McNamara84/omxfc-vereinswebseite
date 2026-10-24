<?php

use App\Services\Maddraxikon\MaddraxikonStreakSettings;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(MaddraxikonStreakSettings::class)->activate();
    }

    public function down(): void
    {
        // Preserve the rollout boundary and audit history across rollbacks.
    }
};
