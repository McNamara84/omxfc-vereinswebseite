<?php

namespace Tests\Feature;

use App\Enums\KassenbuchEntryType;
use App\Models\KassenbuchEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KassenbuchEntryModelTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(string $role = 'Kassenwart'): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);

        return $user;
    }

    public function test_kassenbuch_entry_can_be_created(): void
    {
        $user = $this->createMember();
        $team = Team::membersTeam();

        $entry = KassenbuchEntry::create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'buchungsdatum' => '2025-05-01',
            'betrag' => 10.50,
            'beschreibung' => 'Mitgliedsbeitrag',
            'typ' => KassenbuchEntryType::Einnahme->value,
        ]);

        $this->assertDatabaseHas('kassenbuch_entries', [
            'id' => $entry->id,
            'team_id' => $team->id,
            'created_by' => $user->id,
            'buchungsdatum' => '2025-05-01 00:00:00',
            'betrag' => 10.50,
            'beschreibung' => 'Mitgliedsbeitrag',
            'typ' => KassenbuchEntryType::Einnahme->value,
        ]);
    }

    public function test_mass_assignment_sets_attributes(): void
    {
        $user = $this->createMember();
        $team = Team::membersTeam();

        $entry = KassenbuchEntry::create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'buchungsdatum' => '2025-05-02',
            'betrag' => 5,
            'beschreibung' => 'Einkauf',
            'typ' => KassenbuchEntryType::Ausgabe->value,
            'id' => 999,
        ]);

        $entry->refresh();

        $this->assertNotEquals(999, $entry->id);
        $this->assertSame(KassenbuchEntryType::Ausgabe, $entry->typ);
        $this->assertEquals('Einkauf', $entry->beschreibung);
    }

    public function test_kassenbuch_entry_belongs_to_team(): void
    {
        $user = $this->createMember();
        $team = Team::membersTeam();

        $entry = KassenbuchEntry::create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'buchungsdatum' => now(),
            'betrag' => 12,
            'beschreibung' => 'Test',
            'typ' => KassenbuchEntryType::Einnahme->value,
        ]);

        $this->assertTrue($entry->team->is($team));
    }

    public function test_kassenbuch_entry_belongs_to_creator(): void
    {
        $user = $this->createMember();
        $team = Team::membersTeam();

        $entry = KassenbuchEntry::create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'buchungsdatum' => now(),
            'betrag' => 8,
            'beschreibung' => 'Test',
            'typ' => KassenbuchEntryType::Einnahme->value,
        ]);

        $this->assertTrue($entry->creator->is($user));
    }

    public function test_casts_transform_attributes(): void
    {
        $user = $this->createMember();
        $team = Team::membersTeam();

        $entry = KassenbuchEntry::create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'buchungsdatum' => '2025-06-01',
            'betrag' => '123.45',
            'beschreibung' => 'Cast Test',
            'typ' => KassenbuchEntryType::Einnahme->value,
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $entry->buchungsdatum);
        $this->assertSame('123.45', $entry->betrag);
    }
}
