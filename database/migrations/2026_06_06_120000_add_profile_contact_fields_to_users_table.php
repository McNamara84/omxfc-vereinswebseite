<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('alias')->nullable();
            $table->json('author_aliases')->nullable();
            $table->boolean('contact_release_email')->default(false);
            $table->boolean('contact_release_phone')->default(false);
            $table->boolean('contact_release_maddraxikon')->default(false);
            $table->boolean('contact_release_nextcloud')->default(false);
            $table->string('maddraxikon_username')->nullable();
            $table->string('nextcloud_username')->nullable();
            $table->timestamp('contact_released_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'alias',
                'author_aliases',
                'contact_release_email',
                'contact_release_phone',
                'contact_release_maddraxikon',
                'contact_release_nextcloud',
                'maddraxikon_username',
                'nextcloud_username',
                'contact_released_at',
            ]);
        });
    }
};
