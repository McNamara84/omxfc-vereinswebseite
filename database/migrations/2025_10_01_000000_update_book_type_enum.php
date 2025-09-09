<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // No schema changes necessary; type is stored as a string.
    }

    public function down(): void
    {
        // No schema changes necessary for rollback.
    }
};
