<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maddraxikon_contributions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('evaluation_attempts')
                ->default(0)
                ->after('checked_at');
            $table->string('last_evaluation_error', 1000)
                ->nullable()
                ->after('evaluation_attempts');
            $table->timestamp('last_evaluation_error_at')
                ->nullable()
                ->after('last_evaluation_error');

            $table->index(
                ['status', 'last_evaluation_error_at'],
                'maddraxikon_contributions_evaluation_error_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('maddraxikon_contributions', function (Blueprint $table): void {
            $table->dropIndex('maddraxikon_contributions_evaluation_error_index');
            $table->dropColumn([
                'evaluation_attempts',
                'last_evaluation_error',
                'last_evaluation_error_at',
            ]);
        });
    }
};
