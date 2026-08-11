<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->unsignedBigInteger('maddraxikon_page_id')->nullable()->unique();
            $table->string('maddraxikon_page_title')->nullable()->index();
            $table->timestamp('maddraxikon_page_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->dropUnique(['maddraxikon_page_id']);
            $table->dropIndex(['maddraxikon_page_title']);
            $table->dropColumn([
                'maddraxikon_page_id',
                'maddraxikon_page_title',
                'maddraxikon_page_verified_at',
            ]);
        });
    }
};
