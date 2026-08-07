<?php

use App\Enums\MaddraxikonAccountLinkStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $releasedWithoutActiveLink = DB::table('users')
            ->where('contact_release_maddraxikon', true)
            ->whereNotExists(function (Builder $query): void {
                $query
                    ->selectRaw('1')
                    ->from('maddraxikon_account_links')
                    ->whereColumn('maddraxikon_account_links.user_id', 'users.id')
                    ->where('maddraxikon_account_links.status', MaddraxikonAccountLinkStatus::Active->value)
                    ->whereNull('maddraxikon_account_links.disconnected_at');
            });

        $releasedWithoutActiveLink->update([
            'contact_release_maddraxikon' => false,
            'contact_released_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('maddraxikon_username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Removed legacy values are intentionally not recoverable.
            $table->string('maddraxikon_username')->nullable();
        });
    }
};
