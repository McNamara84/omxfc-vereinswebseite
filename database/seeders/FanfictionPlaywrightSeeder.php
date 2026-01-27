<?php

namespace Database\Seeders;

use App\Enums\FanfictionStatus;
use App\Models\Fanfiction;
use App\Models\FanfictionComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class FanfictionPlaywrightSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $team = Team::membersTeam();

        if (! $team) {
            $this->command->warn('Team "Mitglieder" not found. Run TodoPlaywrightSeeder first.');

            return;
        }

        $admin = User::firstWhere('email', 'info@maddraxikon.com');
        $member = User::firstWhere('email', 'playwright-member@example.com');

        if (! $admin) {
            $this->command->warn('Admin user not found. Run TodoPlaywrightSeeder first.');

            return;
        }

        // Published fanfiction by member
        $fanfiction1Content = <<<'CONTENT'
## Kapitel 1: Der Aufbruch

Matt stand am Rand der alten Straße und blickte hinaus über das verwüstete Land. Die Sonne brannte unbarmherzig auf seinen Nacken, während er sich fragte, ob diese Reise überhaupt Sinn machte.

> "Wir müssen weiter", sagte Aruula und legte ihre Hand auf seine Schulter.

Sie hatten bereits drei Tage Fußmarsch hinter sich, und Doredo lag noch immer weit entfernt. Die Mutanten in dieser Gegend waren besonders aggressiv, und jede Nacht mussten sie Wache halten.

### Die erste Begegnung

Es geschah am vierten Tag. Ein Schatten bewegte sich zwischen den Ruinen, zu schnell für einen Menschen. Matt zog seine Waffe.

*"Zeig dich!"*, rief er in die Stille.

Das Wesen, das aus dem Dunkel trat, war weder Mensch noch Mutant. Es war etwas, das Matt noch nie gesehen hatte - und das ihn bis ins Mark erschütterte.
CONTENT;

        $fanfiction1 = Fanfiction::create([
            'team_id' => $team->id,
            'user_id' => $member?->id,
            'created_by' => $admin->id,
            'title' => 'Die Reise nach Doredo',
            'author_name' => $member?->name ?? 'Playwright Member',
            'content' => $fanfiction1Content,
            'status' => FanfictionStatus::Published,
            'published_at' => now()->subDays(5),
        ]);

        // Add comments to the published fanfiction
        FanfictionComment::create([
            'fanfiction_id' => $fanfiction1->id,
            'user_id' => $admin->id,
            'content' => 'Spannende Geschichte! Ich bin gespannt, wie es weitergeht.',
        ]);

        if ($member) {
            FanfictionComment::create([
                'fanfiction_id' => $fanfiction1->id,
                'user_id' => $member->id,
                'content' => 'Danke für das positive Feedback! Teil 2 kommt bald.',
            ]);
        }

        // Second published fanfiction by external author
        $fanfiction2Content = <<<'CONTENT'
Die Nacht war dunkel über dem Kratersee. Keine Sterne erhellten den Himmel, nur das schwache Glimmen der Phosphoreszenz am Ufer warf gespenstische Lichter auf die Wasseroberfläche.

Lira hatte schon viele seltsame Dinge gesehen in ihrem Leben als Späherin, aber das, was sich dort unten im Wasser bewegte, ließ ihr das Blut in den Adern gefrieren.

Es war groß. Sehr groß. Und es kam näher.

---

*Diese Geschichte ist eine Hommage an die klassischen MADDRAX-Abenteuer am Kratersee.*
CONTENT;

        Fanfiction::create([
            'team_id' => $team->id,
            'user_id' => null,
            'created_by' => $admin->id,
            'title' => 'Schatten über dem Kratersee',
            'author_name' => 'Max T. Hardwet',
            'content' => $fanfiction2Content,
            'status' => FanfictionStatus::Published,
            'published_at' => now()->subDays(2),
        ]);

        // Draft fanfiction (not visible to regular members)
        Fanfiction::create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'created_by' => $admin->id,
            'title' => 'Entwurf: Die dunkle Prophezeiung',
            'author_name' => $admin->name,
            'content' => 'Dies ist ein Entwurf, der noch nicht veröffentlicht wurde. Er sollte nur für Vorstand sichtbar sein.',
            'status' => FanfictionStatus::Draft,
            'published_at' => null,
        ]);

        $this->command->info('FanfictionPlaywrightSeeder: Created 3 fanfictions (2 published, 1 draft) with comments.');
    }
}
