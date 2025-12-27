<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\FantreffenVipAuthors;
use App\Models\FantreffenVipAuthor;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FantreffenVipAuthorsTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserWithRole(Role $role): User
    {
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $user = User::factory()->create(['current_team_id' => $team->id]);

        $user->teams()->attach($team->id, [
            'role' => $role->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    /** @test */
    public function test_vip_authors_page_is_accessible_for_admin(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $this->actingAs($admin);

        $response = $this->get('/admin/fantreffen-2026/vip-autoren');

        $response->assertStatus(200);
        $response->assertSeeLivewire('fantreffen-vip-authors');
    }

    /** @test */
    public function test_vip_authors_page_is_accessible_for_vorstand(): void
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $this->actingAs($vorstand);

        $response = $this->get('/admin/fantreffen-2026/vip-autoren');

        $response->assertStatus(200);
    }

    /** @test */
    public function test_vip_authors_page_is_accessible_for_kassenwart(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $this->actingAs($kassenwart);

        $response = $this->get('/admin/fantreffen-2026/vip-autoren');

        $response->assertStatus(200);
    }

    /** @test */
    public function test_vip_authors_page_is_not_accessible_for_regular_member(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $this->actingAs($member);

        $response = $this->get('/admin/fantreffen-2026/vip-autoren');

        $response->assertStatus(403);
    }

    /** @test */
    public function test_vip_authors_page_is_not_accessible_for_guests(): void
    {
        $response = $this->get('/admin/fantreffen-2026/vip-autoren');

        $response->assertRedirect('/login');
    }

    /** @test */
    public function test_admin_can_create_vip_author(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        Livewire::actingAs($admin)
            ->test(FantreffenVipAuthors::class)
            ->call('openForm')
            ->set('name', 'Oliver Fröhlich')
            ->set('pseudonym', 'Ian Rolf Hill')
            ->set('is_active', true)
            ->set('sort_order', 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('fantreffen_vip_authors', [
            'name' => 'Oliver Fröhlich',
            'pseudonym' => 'Ian Rolf Hill',
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    /** @test */
    public function test_admin_can_create_vip_author_with_tentative_status(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        Livewire::actingAs($admin)
            ->test(FantreffenVipAuthors::class)
            ->call('openForm')
            ->set('name', 'Vorbehalt Autor')
            ->set('pseudonym', '')
            ->set('is_active', true)
            ->set('is_tentative', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('fantreffen_vip_authors', [
            'name' => 'Vorbehalt Autor',
            'is_active' => true,
            'is_tentative' => true,
        ]);
    }

    /** @test */
    public function test_admin_can_create_vip_author_without_pseudonym(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        Livewire::actingAs($admin)
            ->test(FantreffenVipAuthors::class)
            ->call('openForm')
            ->set('name', 'Jo Zybell')
            ->set('pseudonym', '')
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('fantreffen_vip_authors', [
            'name' => 'Jo Zybell',
            'pseudonym' => null,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_admin_can_edit_vip_author(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $author = FantreffenVipAuthor::create([
            'name' => 'Original Name',
            'pseudonym' => 'Original Pseudo',
            'is_active' => true,
            'is_tentative' => false,
            'sort_order' => 0,
        ]);

        Livewire::actingAs($admin)
            ->test(FantreffenVipAuthors::class)
            ->call('edit', $author->id)
            ->set('name', 'Updated Name')
            ->set('pseudonym', 'Updated Pseudo')
            ->set('is_tentative', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('fantreffen_vip_authors', [
            'id' => $author->id,
            'name' => 'Updated Name',
            'pseudonym' => 'Updated Pseudo',
            'is_tentative' => true,
        ]);
    }

    /** @test */
    public function test_admin_can_delete_vip_author(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        // Create three authors with consecutive sort_order values
        $author1 = FantreffenVipAuthor::create(['name' => 'First', 'is_active' => true, 'sort_order' => 0]);
        $author2 = FantreffenVipAuthor::create(['name' => 'To Delete', 'is_active' => true, 'sort_order' => 1]);
        $author3 = FantreffenVipAuthor::create(['name' => 'Third', 'is_active' => true, 'sort_order' => 2]);

        // Delete the middle author
        Livewire::actingAs($admin)
            ->test(FantreffenVipAuthors::class)
            ->call('delete', $author2->id);

        // Verify author was deleted
        $this->assertDatabaseMissing('fantreffen_vip_authors', [
            'id' => $author2->id,
        ]);

        // Verify sort_order was recompacted (remaining authors should have consecutive values starting from 0)
        $this->assertEquals(0, $author1->fresh()->sort_order);
        $this->assertEquals(1, $author3->fresh()->sort_order);
    }

    /** @test */
    public function test_admin_can_toggle_active_status(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $author = FantreffenVipAuthor::create([
            'name' => 'Test Author',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        Livewire::actingAs($admin)
            ->test(FantreffenVipAuthors::class)
            ->call('toggleActive', $author->id);

        $this->assertDatabaseHas('fantreffen_vip_authors', [
            'id' => $author->id,
            'is_active' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(FantreffenVipAuthors::class)
            ->call('toggleActive', $author->id);

        $this->assertDatabaseHas('fantreffen_vip_authors', [
            'id' => $author->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_move_up_swaps_positions_correctly(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $author1 = FantreffenVipAuthor::create(['name' => 'First', 'sort_order' => 0, 'is_active' => true]);
        $author2 = FantreffenVipAuthor::create(['name' => 'Second', 'sort_order' => 1, 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(FantreffenVipAuthors::class)
            ->call('moveUp', $author2->id);

        $this->assertEquals(0, $author2->fresh()->sort_order);
        $this->assertEquals(1, $author1->fresh()->sort_order);
    }

    /** @test */
    public function test_move_down_swaps_positions_correctly(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $author1 = FantreffenVipAuthor::create(['name' => 'First', 'sort_order' => 0, 'is_active' => true]);
        $author2 = FantreffenVipAuthor::create(['name' => 'Second', 'sort_order' => 1, 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(FantreffenVipAuthors::class)
            ->call('moveDown', $author1->id);

        $this->assertEquals(1, $author1->fresh()->sort_order);
        $this->assertEquals(0, $author2->fresh()->sort_order);
    }

    /** @test */
    public function test_move_up_at_top_does_nothing(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $author = FantreffenVipAuthor::create(['name' => 'First', 'sort_order' => 0, 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(FantreffenVipAuthors::class)
            ->call('moveUp', $author->id);

        $this->assertEquals(0, $author->fresh()->sort_order);
    }

    /** @test */
    public function test_move_down_at_bottom_does_nothing(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $author = FantreffenVipAuthor::create(['name' => 'Only', 'sort_order' => 0, 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(FantreffenVipAuthors::class)
            ->call('moveDown', $author->id);

        $this->assertEquals(0, $author->fresh()->sort_order);
    }

    /** @test */
    public function test_name_is_required(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        Livewire::actingAs($admin)
            ->test(FantreffenVipAuthors::class)
            ->call('openForm')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    /** @test */
    public function test_vip_authors_displayed_on_public_page(): void
    {
        FantreffenVipAuthor::create([
            'name' => 'Oliver Fröhlich',
            'pseudonym' => 'Ian Rolf Hill',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->get('/maddrax-fantreffen-2026');

        $response->assertStatus(200);
        $response->assertSee('VIP-Autoren bestätigt!');
        $response->assertSee('Oliver Fröhlich');
        $response->assertSee('Ian Rolf Hill');
    }

    /** @test */
    public function test_tentative_vip_authors_are_marked_on_public_page_and_disclaimer_is_in_program_section(): void
    {
        FantreffenVipAuthor::create([
            'name' => 'Vorbehalt Autor',
            'pseudonym' => null,
            'is_active' => true,
            'is_tentative' => true,
            'sort_order' => 0,
        ]);

        $response = $this->get('/maddrax-fantreffen-2026');

        $response->assertStatus(200);
        $response->assertSee('Vorbehalt Autor');
        $response->assertSee('(unter Vorbehalt)');

        $disclaimer = 'Einige Autor:innen haben ihre Teilnahme bereits zugesagt, andere sind noch angefragt oder haben nur vorläufig zugesagt. Bitte beachtet, dass sich die Gästeliste kurzfristig ändern kann';
        $response->assertSee($disclaimer);

        $content = $response->getContent();
        $programmPos = strpos($content, 'Programm');
        $disclaimerPos = strpos($content, $disclaimer);

        $this->assertNotFalse($programmPos);
        $this->assertNotFalse($disclaimerPos);
        $this->assertGreaterThan($programmPos, $disclaimerPos);
    }

    /** @test */
    public function test_inactive_vip_authors_not_displayed_on_public_page(): void
    {
        FantreffenVipAuthor::create([
            'name' => 'Hidden Author',
            'is_active' => false,
            'sort_order' => 0,
        ]);

        $response = $this->get('/maddrax-fantreffen-2026');

        $response->assertStatus(200);
        $response->assertDontSee('VIP-Autoren bestätigt!');
        $response->assertDontSee('Hidden Author');
    }

    /** @test */
    public function test_display_name_includes_pseudonym_when_set(): void
    {
        $author = FantreffenVipAuthor::create([
            'name' => 'Oliver Fröhlich',
            'pseudonym' => 'Ian Rolf Hill',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->assertEquals('Oliver Fröhlich („Ian Rolf Hill")', $author->display_name);
    }

    /** @test */
    public function test_display_name_shows_only_name_without_pseudonym(): void
    {
        $author = FantreffenVipAuthor::create([
            'name' => 'Jo Zybell',
            'pseudonym' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->assertEquals('Jo Zybell', $author->display_name);
    }
}
