<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->string('cycle')->nullable()->after('author')->index();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->dropIndex(['cycle']);
            $table->dropColumn('cycle');
        });
    }
};
