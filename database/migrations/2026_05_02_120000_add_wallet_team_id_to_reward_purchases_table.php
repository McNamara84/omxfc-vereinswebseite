<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reward_purchases', function (Blueprint $table) {
            $table->foreignId('wallet_team_id')
                ->nullable()
                ->after('reward_id')
                ->constrained('teams')
                ->nullOnDelete();
        });

        $membersTeamId = DB::table('teams')
            ->where('name', 'Mitglieder')
            ->value('id');

        if (! $membersTeamId) {
            return;
        }

        $singleWalletUserIds = DB::table('team_user')
            ->join('teams', 'teams.id', '=', 'team_user.team_id')
            ->where('teams.personal_team', false)
            ->groupBy('team_user.user_id')
            ->havingRaw('COUNT(*) = 1')
            ->havingRaw('MIN(team_user.team_id) = ?', [$membersTeamId])
            ->pluck('team_user.user_id');

        if ($singleWalletUserIds->isEmpty()) {
            return;
        }

        DB::table('reward_purchases')
            ->whereNull('wallet_team_id')
            ->whereIn('user_id', $singleWalletUserIds)
            ->update([
                'wallet_team_id' => $membersTeamId,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reward_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wallet_team_id');
        });
    }
};