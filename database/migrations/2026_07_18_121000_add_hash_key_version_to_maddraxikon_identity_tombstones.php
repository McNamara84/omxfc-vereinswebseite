<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'maddraxikon_identity_tombstones',
            function (Blueprint $table): void {
                $table->string('hash_key_version', 64)
                    ->nullable()
                    ->after('wiki_key')
                    ->index();
            }
        );

        DB::table('maddraxikon_identity_tombstones')
            ->whereNull('hash_key_version')
            ->update(['hash_key_version' => 'legacy-app-key']);
    }

    public function down(): void
    {
        Schema::table(
            'maddraxikon_identity_tombstones',
            function (Blueprint $table): void {
                $table->dropIndex(['hash_key_version']);
                $table->dropColumn('hash_key_version');
            }
        );
    }
};
