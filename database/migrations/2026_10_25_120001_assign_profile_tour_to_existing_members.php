<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $teamId = DB::table('teams')->where('name', 'Mitglieder')->orderBy('id')->value('id');
        if ($teamId === null) {
            return;
        }

        DB::table('users')->whereIn('id', DB::table('team_user')->select('user_id')
            ->where('team_id', $teamId)->whereIn('role', ['Mitwirkender', 'Mitglied', 'Ehrenmitglied', 'Kassenwart', 'Vorstand', 'Admin']))
            ->select('id')->orderBy('id')->chunkById(200, function ($users) {
                DB::transaction(function () use ($users) {
                    foreach ($users as $user) {
                        DB::table('tour_assignments')->insertOrIgnore([
                            'user_id' => $user->id, 'tour_key' => 'profilpflege', 'tour_version' => 3,
                            'status' => 'pending', 'assigned_via' => 'system',
                            'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                            'metadata' => json_encode(['campaign' => 'profilpflege_seriendaten']),
                        ]);
                        $old = DB::table('tour_assignments')->where('user_id', $user->id)
                            ->where('tour_key', 'profilpflege')->where('tour_version', '<', 3)
                            ->whereIn('status', ['pending', 'in_progress'])->get();
                        foreach ($old as $assignment) {
                            $metadata = json_decode($assignment->metadata ?? '{}', true) ?? [];
                            $metadata['superseded_by_version'] = 3;
                            DB::table('tour_assignments')->where('id', $assignment->id)->update([
                                'status' => 'completed', 'completed_at' => $assignment->completed_at ?? now(),
                                'dismissed_at' => null, 'next_prompt_at' => null,
                                'metadata' => json_encode($metadata), 'updated_at' => now(),
                            ]);
                        }
                    }
                });
            });
    }

    public function down(): void
    {
        // Preserve member progress and grant history on code rollback.
    }
};
