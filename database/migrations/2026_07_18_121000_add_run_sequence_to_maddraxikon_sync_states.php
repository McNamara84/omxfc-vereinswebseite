<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maddraxikon_sync_states', function (Blueprint $table): void {
            $table->unsignedBigInteger('run_sequence')
                ->default(0)
                ->after('last_started_at_epoch');
        });
    }

    public function down(): void
    {
        Schema::table('maddraxikon_sync_states', function (Blueprint $table): void {
            $table->dropColumn('run_sequence');
        });
    }
};
