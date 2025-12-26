<?php

namespace Database\Seeders;

use App\Enums\PollStatus;
use App\Enums\PollVisibility;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Database\Seeder;

class PollPlaywrightPublicSeeder extends Seeder
{
    public function run(): void
    {
        // Keep other Playwright fixtures intact; only reset poll tables.
        PollVote::query()->delete();
        PollOption::query()->delete();
        Poll::query()->delete();

        $admin = User::query()->where('email', 'info@maddraxikon.com')->first();

        $poll = Poll::query()->create([
            'question' => 'Playwright: Öffentliche Umfrage?',
            'menu_label' => 'Playwright Umfrage',
            'visibility' => PollVisibility::Public,
            'status' => PollStatus::Active,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'activated_at' => now(),
            'created_by_user_id' => $admin?->id,
        ]);

        PollOption::query()->create([
            'poll_id' => $poll->id,
            'label' => 'Option A',
            'sort_order' => 0,
        ]);

        PollOption::query()->create([
            'poll_id' => $poll->id,
            'label' => 'Option B',
            'sort_order' => 1,
        ]);
    }
}
