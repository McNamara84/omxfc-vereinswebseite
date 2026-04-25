<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Livewire\RezensionForm;
use App\Livewire\RezensionIndex;
use App\Livewire\RezensionShow;
use App\Mail\NewReviewNotification;
use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Large;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

#[Large]
class RezensionLivewireTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    // ── Store / Create Tests ─────────────────────────────────────

    public function test_store_creates_review_and_notifies_author(): void
    {
        Mail::fake();

        $author = User::factory()->create(['notify_new_review' => true]);
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => $author->name,
        ]);

        $user = $this->actingMember();

        Livewire::actingAs($user)
            ->test(RezensionForm::class, ['book' => $book])
            ->set('title', 'Tolle Rezension')
            ->set('content', str_repeat('A', 140))
            ->call('save')
            ->assertRedirect(route('reviews.show', $book));

        $this->assertDatabaseHas('reviews', [
            'book_id' => $book->id,
            'title' => 'Tolle Rezension',
        ]);
        Mail::assertQueued(NewReviewNotification::class);
    }

    public function test_store_strips_heading_markers_before_validation(): void
    {
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);

        $user = $this->actingMember();

        Livewire::actingAs($user)
            ->test(RezensionForm::class, ['book' => $book])
            ->set('title', 'Tolle Rezension')
            ->set('content', '# '.str_repeat('A', 140))
            ->call('save')
            ->assertRedirect(route('reviews.show', $book));

        $this->assertDatabaseHas('reviews', [
            'book_id' => $book->id,
            'content' => str_repeat('A', 140),
        ]);
    }

    public function test_store_rejects_content_too_short_after_stripping_heading_markers(): void
    {
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);

        $user = $this->actingMember();

        Livewire::actingAs($user)
            ->test(RezensionForm::class, ['book' => $book])
            ->set('title', 'Tolle Rezension')
            ->set('content', '# '.str_repeat('A', 139))
            ->call('save')
            ->assertHasErrors('content');
    }

    // ── Index Tests ──────────────────────────────────────────────

    public function test_index_requires_valid_role(): void
    {
        $user = $this->actingMember('Gast');

        Livewire::actingAs($user)
            ->test(RezensionIndex::class)
            ->assertForbidden();
    }

    public function test_index_displays_total_review_count_per_cycle(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('maddrax.json', json_encode([
            ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'],
            ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1'],
        ]));

        $book1 = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        $book2 = Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);

        $teamId = Team::membersTeam()->id;
        Review::create([
            'team_id' => $teamId,
            'user_id' => User::factory()->create()->id,
            'book_id' => $book1->id,
            'title' => 'R1',
            'content' => str_repeat('A', 140),
        ]);
        Review::create([
            'team_id' => $teamId,
            'user_id' => User::factory()->create()->id,
            'book_id' => $book2->id,
            'title' => 'R2',
            'content' => str_repeat('B', 140),
        ]);

        $user = $this->actingMember();

        Livewire::actingAs($user)
            ->test(RezensionIndex::class)
            ->assertSee('(2 Rezensionen)');
    }

    public function test_index_shows_hardcover_books(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('maddrax.json', json_encode([
            ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'],
            ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1'],
        ]));

        $book = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        Book::create([
            'roman_number' => 2,
            'title' => 'Hardcover Beta',
            'author' => 'B',
            'type' => BookType::MaddraxHardcover,
        ]);

        $user = $this->actingMember();

        Livewire::actingAs($user)
            ->test(RezensionIndex::class)
            ->assertSee($book->title)
            ->assertSee('Hardcover Beta');
    }

    public function test_index_shows_mission_mars_books(): void
    {
        $path = storage_path('app/private/maddrax.json');
        $original = file_get_contents($path);

        try {
            file_put_contents($path, json_encode([
                ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Wandler'],
            ]));

            $book = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
            Book::create([
                'roman_number' => 2,
                'title' => 'Mission Mars Beta',
                'author' => 'B',
                'type' => BookType::MissionMars,
            ]);

            $user = $this->actingMember();

            Livewire::actingAs($user)
                ->test(RezensionIndex::class)
                ->assertSee($book->title)
                ->assertSee('Mission Mars Beta');
        } finally {
            file_put_contents($path, $original);
        }
    }

    public function test_index_shows_2012_books(): void
    {
        $path = storage_path('app/private/maddrax.json');
        $original = file_get_contents($path);

        try {
            file_put_contents($path, json_encode([
                ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Ursprung'],
            ]));

            $book = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
            Book::create([
                'roman_number' => 3,
                'title' => '2012 Roman',
                'author' => 'C',
                'type' => BookType::ZweiTausendZwölfDasJahrDerApokalypse,
            ]);

            $user = $this->actingMember();

            Livewire::actingAs($user)
                ->test(RezensionIndex::class)
                ->assertSee($book->title)
                ->assertSee('2012 Roman')
                ->assertSee('Mini-Serie 2012');
        } finally {
            file_put_contents($path, $original);
        }
    }

    public function test_index_shows_volk_der_tiefe_books(): void
    {
        $path = storage_path('app/private/maddrax.json');
        $original = file_get_contents($path);

        try {
            file_put_contents($path, json_encode([
                ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Wandler'],
            ]));

            $book = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
            Book::create([
                'roman_number' => 3,
                'title' => 'Volk Roman',
                'author' => 'C',
                'type' => BookType::DasVolkDerTiefe,
            ]);

            $user = $this->actingMember();

            Livewire::actingAs($user)
                ->test(RezensionIndex::class)
                ->assertSee($book->title)
                ->assertSee('Volk Roman');
        } finally {
            file_put_contents($path, $original);
        }
    }

    public function test_index_shows_abenteurer_books(): void
    {
        $path = storage_path('app/private/maddrax.json');
        $original = file_get_contents($path);

        try {
            file_put_contents($path, json_encode([
                ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Ursprung'],
            ]));

            $book = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
            Book::create([
                'roman_number' => 3,
                'title' => 'Abenteurer Roman',
                'author' => 'C',
                'type' => BookType::DieAbenteurer,
            ]);

            $user = $this->actingMember();

            Livewire::actingAs($user)
                ->test(RezensionIndex::class)
                ->assertSee($book->title)
                ->assertSee('Abenteurer Roman')
                ->assertSee('Die Abenteurer');
        } finally {
            file_put_contents($path, $original);
        }
    }

    // ── Show Tests ───────────────────────────────────────────────

    public function test_show_redirects_when_user_has_no_permission(): void
    {
        $user = $this->actingMember();

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $other = $this->createUserWithRole('Mitglied');
        Review::create([
            'team_id' => $other->currentTeam->id,
            'user_id' => $other->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('B', 140),
        ]);

        Livewire::actingAs($user)
            ->test(RezensionShow::class, ['book' => $book])
            ->assertRedirect(route('reviews.create', $book));
    }

    public function test_show_displays_review_for_author(): void
    {
        $user = $this->actingMember();

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('A', 140),
        ]);

        Livewire::actingAs($user)
            ->test(RezensionShow::class, ['book' => $book])
            ->assertSee('R')
            ->assertOk();
    }

    public function test_show_displays_update_information_when_review_was_edited(): void
    {
        $user = $this->actingMember();

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);

        Carbon::setTestNow(Carbon::create(2025, 7, 16, 17, 0));
        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('A', 140),
        ]);

        Carbon::setTestNow(Carbon::create(2025, 7, 17, 17, 30));
        $review->update(['content' => str_repeat('B', 140)]);
        Carbon::setTestNow();

        Livewire::actingAs($user)
            ->test(RezensionShow::class, ['book' => $book])
            ->assertSee('am 16.07.2025 17:00 Uhr')
            ->assertSee('geändert am 17.07.2025 um 17:30 Uhr');
    }

    public function test_show_does_not_display_update_information_when_review_not_edited(): void
    {
        $user = $this->actingMember();

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);

        Carbon::setTestNow(Carbon::create(2025, 7, 16, 17, 0));
        Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('A', 140),
        ]);
        Carbon::setTestNow();

        Livewire::actingAs($user)
            ->test(RezensionShow::class, ['book' => $book])
            ->assertSee('am 16.07.2025 17:00 Uhr')
            ->assertDontSee('geändert am');
    }

    // ── Edit / Update Tests ──────────────────────────────────────

    public function test_edit_shows_form_for_author(): void
    {
        $user = $this->actingMember();

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('C', 140),
        ]);

        Livewire::actingAs($user)
            ->test(RezensionForm::class, ['review' => $review])
            ->assertSet('title', 'R')
            ->assertOk();
    }

    public function test_admin_can_update_review(): void
    {
        $admin = $this->actingMember('Admin');

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $member = $this->actingMember();
        $review = Review::create([
            'team_id' => $admin->currentTeam->id,
            'user_id' => $member->id,
            'book_id' => $book->id,
            'title' => 'Old',
            'content' => str_repeat('D', 140),
        ]);

        Livewire::actingAs($admin)
            ->test(RezensionForm::class, ['review' => $review])
            ->set('title', 'New')
            ->set('content', str_repeat('E', 140))
            ->call('save')
            ->assertRedirect(route('reviews.show', $book));

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'title' => 'New',
        ]);
    }

    public function test_admin_can_delete_review(): void
    {
        $admin = $this->actingMember('Admin');

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $member = $this->actingMember();
        $review = Review::create([
            'team_id' => $admin->currentTeam->id,
            'user_id' => $member->id,
            'book_id' => $book->id,
            'title' => 'Old',
            'content' => str_repeat('D', 140),
        ]);

        Livewire::actingAs($admin)
            ->test(RezensionShow::class, ['book' => $book])
            ->call('deleteReview', $review->id)
            ->assertDispatched('toast', type: 'success');

        $this->assertSoftDeleted('reviews', ['id' => $review->id]);
    }

    public function test_update_validation_errors(): void
    {
        $admin = $this->actingMember('Admin');
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Test',
            'author' => 'Autor',
        ]);
        $review = Review::create([
            'team_id' => Team::membersTeam()->id,
            'user_id' => $admin->id,
            'book_id' => $book->id,
            'title' => 'Old',
            'content' => str_repeat('A', 140),
        ]);

        Livewire::actingAs($admin)
            ->test(RezensionForm::class, ['review' => $review])
            ->set('title', '')
            ->set('content', 'short')
            ->call('save')
            ->assertHasErrors(['title', 'content']);
    }

    public function test_create_redirects_when_review_exists(): void
    {
        $user = $this->actingMember();

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('B', 140),
        ]);

        Livewire::actingAs($user)
            ->test(RezensionForm::class, ['book' => $book])
            ->assertRedirect(route('reviews.show', $book));
    }

    public function test_store_fails_when_review_exists(): void
    {
        $user = $this->actingMember();

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('C', 140),
        ]);

        // Mount redirects when review exists
        Livewire::actingAs($user)
            ->test(RezensionForm::class, ['book' => $book])
            ->assertRedirect(route('reviews.show', $book));
    }

    public function test_update_forbidden_for_non_author(): void
    {
        $owner = $this->actingMember();
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $review = Review::create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'book_id' => $book->id,
            'title' => 'Old',
            'content' => str_repeat('E', 140),
        ]);

        $other = $this->actingMember();

        Livewire::actingAs($other)
            ->test(RezensionForm::class, ['review' => $review])
            ->assertForbidden();
    }

    public function test_update_strips_heading_markers_before_validation(): void
    {
        $user = $this->actingMember();

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('E', 140),
        ]);

        Livewire::actingAs($user)
            ->test(RezensionForm::class, ['review' => $review])
            ->set('content', '# '.str_repeat('A', 140))
            ->call('save')
            ->assertRedirect(route('reviews.show', $book));

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'content' => str_repeat('A', 140),
        ]);
    }

    public function test_update_rejects_content_too_short_after_stripping_heading_markers(): void
    {
        $user = $this->actingMember();

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('E', 140),
        ]);

        Livewire::actingAs($user)
            ->test(RezensionForm::class, ['review' => $review])
            ->set('content', '# '.str_repeat('A', 139))
            ->call('save')
            ->assertHasErrors('content');
    }

    public function test_destroy_forbidden_for_non_author(): void
    {
        $owner = $this->actingMember();
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $review = Review::create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'book_id' => $book->id,
            'title' => 'Old',
            'content' => str_repeat('E', 140),
        ]);

        $other = $this->actingMember();
        // Give other user their own review so they can view the show page
        Review::create([
            'team_id' => $other->currentTeam->id,
            'user_id' => $other->id,
            'book_id' => $book->id,
            'title' => 'Other',
            'content' => str_repeat('F', 140),
        ]);

        Livewire::actingAs($other)
            ->test(RezensionShow::class, ['book' => $book])
            ->call('deleteReview', $review->id)
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'deleted_at' => null]);
    }

    // ── Filter Tests ─────────────────────────────────────────────

    public function test_filter_by_roman_number(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('maddrax.json', json_encode([
            ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'],
            ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1'],
        ]));
        Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);

        $user = $this->actingMember();

        Livewire::actingAs($user)
            ->test(RezensionIndex::class)
            ->set('roman_number', 1)
            ->assertSee('Alpha')
            ->assertDontSee('Beta');
    }

    public function test_filter_by_review_status(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('maddrax.json', json_encode([
            ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'],
            ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1'],
        ]));
        $book1 = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        $book2 = Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);
        Review::create([
            'team_id' => Team::membersTeam()->id,
            'user_id' => User::factory()->create()->id,
            'book_id' => $book1->id,
            'title' => 'R',
            'content' => str_repeat('A', 140),
        ]);

        $user = $this->actingMember();

        Livewire::actingAs($user)
            ->test(RezensionIndex::class)
            ->set('review_status', 'without')
            ->assertSee('Beta')
            ->assertDontSee('Alpha');
    }

    public function test_filter_by_author(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('maddrax.json', json_encode([
            ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'],
            ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1'],
        ]));
        Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);

        $user = $this->actingMember();

        Livewire::actingAs($user)
            ->test(RezensionIndex::class)
            ->set('author', 'B')
            ->assertSee('Beta')
            ->assertDontSee('Alpha');
    }

    public function test_reset_filters(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('maddrax.json', json_encode([
            ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'],
            ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1'],
        ]));
        Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);

        $user = $this->actingMember();

        Livewire::actingAs($user)
            ->test(RezensionIndex::class)
            ->set('roman_number', 1)
            ->assertDontSee('Beta')
            ->call('resetFilters')
            ->assertSee('Alpha')
            ->assertSee('Beta');
    }

    // ── Cycle Ordering / Interleaving Tests ──────────────────────

    public function test_2012_is_rendered_between_ursprung_and_streiter_cycles(): void
    {
        $path = storage_path('app/private/maddrax.json');
        $original = file_get_contents($path);

        try {
            file_put_contents($path, json_encode([
                ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Ursprung'],
                ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Streiter'],
            ]));

            Book::create(['roman_number' => 1, 'title' => 'Ursprung Alpha', 'author' => 'A']);
            Book::create(['roman_number' => 2, 'title' => 'Streiter Beta', 'author' => 'B']);
            Book::create([
                'roman_number' => 10,
                'title' => '2012 Roman',
                'author' => 'C',
                'type' => BookType::ZweiTausendZwölfDasJahrDerApokalypse,
            ]);

            $user = $this->actingMember();

            $this->actingAs($user)
                ->get('/rezensionen')
                ->assertOk()
                ->assertSeeInOrder([
                    'Ursprung-Zyklus',
                    'Mini-Serie 2012',
                    'Streiter-Zyklus',
                ], false);
        } finally {
            file_put_contents($path, $original);
        }
    }

    public function test_abenteurer_is_rendered_between_ursprung_and_2012(): void
    {
        $path = storage_path('app/private/maddrax.json');
        $original = file_get_contents($path);

        try {
            file_put_contents($path, json_encode([
                ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Ursprung'],
                ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Streiter'],
            ]));

            Book::create(['roman_number' => 1, 'title' => 'Ursprung Alpha', 'author' => 'A']);
            Book::create(['roman_number' => 2, 'title' => 'Streiter Beta', 'author' => 'B']);
            Book::create([
                'roman_number' => 10,
                'title' => 'Abenteurer Roman',
                'author' => 'C',
                'type' => BookType::DieAbenteurer,
            ]);
            Book::create([
                'roman_number' => 11,
                'title' => '2012 Roman',
                'author' => 'D',
                'type' => BookType::ZweiTausendZwölfDasJahrDerApokalypse,
            ]);

            $user = $this->actingMember();

            $this->actingAs($user)
                ->get('/rezensionen')
                ->assertOk()
                ->assertSeeInOrder([
                    'Ursprung-Zyklus',
                    'Die Abenteurer',
                    'Mini-Serie 2012',
                    'Streiter-Zyklus',
                ], false);
        } finally {
            file_put_contents($path, $original);
        }
    }

    public function test_cycles_follow_release_order_with_spin_offs_and_hardcovers_at_end(): void
    {
        $path = storage_path('app/private/maddrax.json');
        $original = file_get_contents($path);

        try {
            file_put_contents($path, json_encode([
                ['nummer' => 650, 'titel' => 'Weltrat 1', 'zyklus' => 'Weltrat'],
                ['nummer' => 200, 'titel' => 'Afra 1', 'zyklus' => 'Afra'],
                ['nummer' => 175, 'titel' => 'Ausala 1', 'zyklus' => 'Ausala'],
                ['nummer' => 150, 'titel' => 'Mars 1', 'zyklus' => 'Mars'],
                ['nummer' => 125, 'titel' => 'Wandler 1', 'zyklus' => 'Wandler'],
                ['nummer' => 1, 'titel' => 'Euree 1', 'zyklus' => 'Euree'],
            ]));

            Book::create(['roman_number' => 650, 'title' => 'Weltrat Alpha', 'author' => 'W']);
            Book::create(['roman_number' => 200, 'title' => 'Afra Alpha', 'author' => 'A']);
            Book::create(['roman_number' => 175, 'title' => 'Ausala Alpha', 'author' => 'A']);
            Book::create(['roman_number' => 150, 'title' => 'Mars Alpha', 'author' => 'M']);
            Book::create(['roman_number' => 125, 'title' => 'Wandler Alpha', 'author' => 'W']);
            Book::create(['roman_number' => 1, 'title' => 'Euree Alpha', 'author' => 'E']);

            Book::create([
                'roman_number' => 5,
                'title' => 'Mission Mars 1',
                'author' => 'MM',
                'type' => BookType::MissionMars,
            ]);

            Book::create([
                'roman_number' => 6,
                'title' => 'Volk Roman 1',
                'author' => 'VDT',
                'type' => BookType::DasVolkDerTiefe,
            ]);

            Book::create([
                'roman_number' => 8,
                'title' => '2012 Roman',
                'author' => 'JR',
                'type' => BookType::ZweiTausendZwölfDasJahrDerApokalypse,
            ]);

            Book::create([
                'roman_number' => 2,
                'title' => 'Hardcover 1',
                'author' => 'HC',
                'type' => BookType::MaddraxHardcover,
            ]);

            $user = $this->actingMember();

            $this->actingAs($user)
                ->get('/rezensionen')
                ->assertOk()
                ->assertSeeInOrder([
                    'Weltrat-Zyklus',
                    'Afra-Zyklus',
                    'Das Volk der Tiefe',
                    'Ausala-Zyklus',
                    'Mars-Zyklus',
                    'Mission Mars-Heftromane',
                    'Wandler-Zyklus',
                    'Euree-Zyklus',
                    'Mini-Serie 2012',
                    'Maddrax-Hardcover',
                ], false);
        } finally {
            file_put_contents($path, $original);
        }
    }

    public function test_volk_der_tiefe_accordion_renders_as_daisyui_collapse(): void
    {
        $path = storage_path('app/private/maddrax.json');
        $original = file_get_contents($path);

        try {
            file_put_contents($path, json_encode([
                ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Wandler'],
            ]));

            Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
            Book::create([
                'roman_number' => 7,
                'title' => 'Volk Roman',
                'author' => 'C',
                'type' => BookType::DasVolkDerTiefe,
            ]);

            $user = $this->actingMember();

            $response = $this->actingAs($user)->get('/rezensionen');
            $response->assertOk();

            $html = $response->getContent();
            $this->assertMatchesRegularExpression(
                '/class="collapse collapse-arrow[^"]*"[^<]*<input[^>]*aria-label="Das Volk der Tiefe ein-\/ausklappen"/',
                $html,
                'Das Volk der Tiefe section should be rendered as a DaisyUI collapse with matching aria-label'
            );
        } finally {
            file_put_contents($path, $original);
        }
    }
}
