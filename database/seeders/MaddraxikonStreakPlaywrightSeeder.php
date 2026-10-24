<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonStreakBooking;
use App\Models\MaddraxikonStreakDay;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use App\Models\User;
use App\Services\Baxx\BaxxBookingWriter;
use App\Services\Maddraxikon\MaddraxikonStreakTracker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

class MaddraxikonStreakPlaywrightSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('Serien-Testkonten sind nur in testing erlaubt.');
        }
        $today = CarbonImmutable::now('Europe/Berlin')->startOfDay();
        $start = CarbonImmutable::parse('2026-01-01T00:00:00Z');
        $team = Team::membersTeam();
        MaddraxikonSyncState::query()->updateOrCreate(['wiki_key' => config('maddraxikon.wiki_key')], ['initial_watermark_at' => $start, 'watermark_at' => now(), 'last_succeeded_at' => now()]);
        foreach (['new', 'active', 'pending', 'corrected'] as $index => $state) {
            $user = User::query()->firstOrCreate(['email' => "streak-{$state}@example.com"], [
                'name' => 'Serienprüfung '.$state, 'vorname' => 'Serienprüfung', 'nachname' => $state, 'password' => Hash::make('password'),
                'strasse' => 'Teststraße', 'hausnummer' => '1', 'plz' => '12345', 'stadt' => 'Teststadt', 'land' => 'Deutschland', 'telefon' => '', 'mitgliedsbeitrag' => 12,
                'current_team_id' => $team->id, 'email_verified_at' => now(),
            ]);
            $team->users()->syncWithoutDetaching([$user->id => ['role' => Role::Mitglied->value]]);
            if ($state === 'new' || MaddraxikonStreakDay::query()->where('user_id', $user->id)->exists()) {
                continue;
            }
            $link = MaddraxikonAccountLink::factory()->create(['user_id' => $user->id, 'wiki_user_id' => 990001 + $index, 'wiki_username' => 'SerienTest'.$index, 'verified_at' => $today->subDays(10), 'streak_verified_at_epoch' => $today->subDays(10)->timestamp]);
            for ($length = 1; $length <= 4; $length++) {
                $day = MaddraxikonStreakDay::query()->create([
                    'wiki_key' => $link->wiki_key, 'user_id' => $user->id, 'activity_date' => $today->subDays(5 - $length)->toDateString(),
                    'status' => 'valid', 'valid_count' => 1, 'streak_length' => $length, 'target_points' => $length,
                    'settled_points' => $length, 'calculation_version' => 1, 'starts_at_epoch' => $start->timestamp,
                ]);
                $point = app(BaxxBookingWriter::class)->record($user->id, $team->id, $length);
                MaddraxikonStreakBooking::query()->create(['streak_day_id' => $day->id, 'user_id' => $user->id, 'team_id' => $team->id, 'user_point_id' => $point->id, 'points_delta' => $length, 'previous_points' => 0, 'target_points' => $length, 'calculation_version' => 1, 'reason' => 'daily_bonus', 'source_revision_ids' => []]);
                if ($state === 'corrected' && $length >= 2) {
                    $target = $length - 2;
                    $point = app(BaxxBookingWriter::class)->record($user->id, $team->id, -2);
                    MaddraxikonStreakBooking::query()->create(['streak_day_id' => $day->id, 'user_id' => $user->id, 'team_id' => $team->id, 'user_point_id' => $point->id, 'points_delta' => -2, 'previous_points' => $length, 'target_points' => $target, 'calculation_version' => 2, 'reason' => 'series_recalculated', 'source_revision_ids' => []]);
                    $day->update(['streak_length' => $target, 'target_points' => $target, 'settled_points' => $target, 'calculation_version' => 2, 'status' => $target ? 'valid' : 'inactive']);
                }
            }
            if ($state === 'pending') {
                $source = MaddraxikonContribution::factory()->create(['user_id' => $user->id, 'account_link_id' => $link->id, 'wiki_user_id' => $link->wiki_user_id, 'wiki_username' => $link->wiki_username, 'page_title' => 'Eine kleine Korrektur', 'occurred_at' => now()]);
                app(MaddraxikonStreakTracker::class)->record($source, $link);
            }
        }
    }
}
