<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Kassenstand;
use App\Models\Team;

class KassenstandModelTest extends TestCase
{
    use RefreshDatabase;

    private function getTeam(): Team
    {
        return Team::membersTeam();
    }

    public function test_kassenstand_can_be_created(): void
    {
        $team = $this->getTeam();

        $kassenstand = Kassenstand::create([
            'team_id' => $team->id,
            'betrag' => 10.50,
            'letzte_aktualisierung' => '2025-01-01',
        ]);

        $this->assertDatabaseHas('kassenstand', [
            'id' => $kassenstand->id,
            'team_id' => $team->id,
            'betrag' => 10.50,
            'letzte_aktualisierung' => '2025-01-01 00:00:00',
        ]);
    }

    public function test_kassenstand_belongs_to_team(): void
    {
        $team = $this->getTeam();

        $kassenstand = Kassenstand::create([
            'team_id' => $team->id,
            'betrag' => 5.00,
            'letzte_aktualisierung' => now(),
        ]);

        $this->assertTrue($kassenstand->team->is($team));
    }

    public function test_attributes_are_cast_correctly(): void
    {
        $team = $this->getTeam();

        $kassenstand = Kassenstand::create([
            'team_id' => $team->id,
            'betrag' => '4.55',
            'letzte_aktualisierung' => '2025-02-15',
        ]);

        $kassenstand->refresh();

        $this->assertSame('4.55', $kassenstand->betrag);
        $this->assertEquals('2025-02-15', $kassenstand->letzte_aktualisierung->format('Y-m-d'));
    }

    public function test_kassenstand_can_be_updated(): void
    {
        $team = $this->getTeam();

        $kassenstand = Kassenstand::create([
            'team_id' => $team->id,
            'betrag' => 0,
            'letzte_aktualisierung' => now(),
        ]);

        $kassenstand->update([
            'betrag' => 20.25,
            'letzte_aktualisierung' => '2025-02-01',
        ]);
        $kassenstand->refresh();

        $this->assertSame('20.25', $kassenstand->betrag);
        $this->assertEquals('2025-02-01', $kassenstand->letzte_aktualisierung->format('Y-m-d'));
    }
}
