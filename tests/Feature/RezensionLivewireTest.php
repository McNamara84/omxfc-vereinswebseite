<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Livewire\RezensionForm;
use App\Livewire\RezensionIndex;
use App\Livewire\RezensionShow;
use App\Mail\NewReviewNotification;
use App\Models\Book;
use App\Models\Review;
use App\Models\ReviewBaxxSpecialOffer;
use App\Models\ReviewComment;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Large;
use Tests\Concerns\CreatesUserWithRole;
use Tests\Support\PhpunitMetadata;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('store creates review and notifies author', function (): void {
    Mail::fake();
    $author = User::factory()->create(['notify_new_review' => true]);
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => $author->name]);
    $user = $this->actingMember();
    Livewire::actingAs($user)->test(RezensionForm::class, ['book' => $book])->set('title', 'Tolle Rezension')->set('content', str_repeat('A', 140))->call('save')->assertRedirect(route('reviews.show', $book));
    $this->assertDatabaseHas('reviews', ['book_id' => $book->id, 'title' => 'Tolle Rezension']);
    Mail::assertQueued(NewReviewNotification::class);
});

test('store strips heading markers before validation', function (): void {
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $user = $this->actingMember();
    Livewire::actingAs($user)->test(RezensionForm::class, ['book' => $book])->set('title', 'Tolle Rezension')->set('content', '# '.str_repeat('A', 140))->call('save')->assertRedirect(route('reviews.show', $book));
    $this->assertDatabaseHas('reviews', ['book_id' => $book->id, 'content' => str_repeat('A', 140)]);
});

test('store rejects content too short after stripping heading markers', function (): void {
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $user = $this->actingMember();
    Livewire::actingAs($user)->test(RezensionForm::class, ['book' => $book])->set('title', 'Tolle Rezension')->set('content', '# '.str_repeat('A', 139))->call('save')->assertHasErrors('content');
});

test('index requires valid role', function (): void {
    $user = $this->actingMember('Gast');
    Livewire::actingAs($user)->test(RezensionIndex::class)->assertForbidden();
});

test('index displays total review count per cycle', function (): void {
    Storage::fake('private');
    Storage::disk('private')->put('maddrax.json', json_encode([['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'], ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1']]));
    $book1 = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
    $book2 = Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);
    $teamId = Team::membersTeam()->id;
    Review::create(['team_id' => $teamId, 'user_id' => User::factory()->create()->id, 'book_id' => $book1->id, 'title' => 'R1', 'content' => str_repeat('A', 140)]);
    Review::create(['team_id' => $teamId, 'user_id' => User::factory()->create()->id, 'book_id' => $book2->id, 'title' => 'R2', 'content' => str_repeat('B', 140)]);
    $user = $this->actingMember();
    Livewire::actingAs($user)->test(RezensionIndex::class)->assertSee('(2 Rezensionen)');
});

test('index prefers persisted cycle over stale json mapping', function (): void {
    Storage::fake('private');
    Storage::disk('private')->put('maddrax.json', json_encode([['nummer' => 695, 'titel' => 'Roman', 'zyklus' => 'Veraltet']]));
    Book::create(['roman_number' => 695, 'title' => 'Roman', 'author' => 'Autor', 'cycle' => 'Weltrat']);
    $user = $this->actingMember();
    Livewire::actingAs($user)->test(RezensionIndex::class)->assertSee('Weltrat-Zyklus')->assertDontSee('Veraltet-Zyklus');
});

test('index marks missing cycle as data error instead of unknown cycle', function (): void {
    Storage::fake('private');
    Storage::disk('private')->put('maddrax.json', '[]');
    Book::create(['roman_number' => 695, 'title' => 'Roman', 'author' => 'Autor', 'cycle' => null]);
    $user = $this->actingMember();
    Livewire::actingAs($user)->test(RezensionIndex::class)->assertSee('Datenfehler-Zyklus')->assertDontSee('Unbekannt-Zyklus');
});

test('index shows dynamic review reward text', function (): void {
    $user = $this->actingMember();
    Livewire::actingAs($user)->test(RezensionIndex::class)->assertSee('1 Baxx pro 10 Rezensionen');
});

test('index shows prominent special offer banner', function (): void {
    $user = $this->actingMember();
    ReviewBaxxSpecialOffer::create(['points' => 2, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    Livewire::actingAs($user)->test(RezensionIndex::class)->assertSee('Sonderaktion')->assertSee('2 Baxx pro Rezension');
});

test('index shows hardcover books', function (): void {
    Storage::fake('private');
    Storage::disk('private')->put('maddrax.json', json_encode([['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'], ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1']]));
    $book = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
    Book::create(['roman_number' => 2, 'title' => 'Hardcover Beta', 'author' => 'B', 'type' => BookType::MaddraxHardcover]);
    $user = $this->actingMember();
    Livewire::actingAs($user)->test(RezensionIndex::class)->assertSee($book->title)->assertSee('Hardcover Beta');
});

test('index shows mission mars books', function (): void {
    $path = storage_path('app/private/maddrax.json');
    $original = file_get_contents($path);
    try {
        file_put_contents($path, json_encode([['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Wandler']]));
        $book = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        Book::create(['roman_number' => 2, 'title' => 'Mission Mars Beta', 'author' => 'B', 'type' => BookType::MissionMars]);
        $user = $this->actingMember();
        Livewire::actingAs($user)->test(RezensionIndex::class)->assertSee($book->title)->assertSee('Mission Mars Beta');
    } finally {
        file_put_contents($path, $original);
    }
});

test('index shows 2012 books', function (): void {
    $path = storage_path('app/private/maddrax.json');
    $original = file_get_contents($path);
    try {
        file_put_contents($path, json_encode([['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Ursprung']]));
        $book = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        Book::create(['roman_number' => 3, 'title' => '2012 Roman', 'author' => 'C', 'type' => BookType::ZweiTausendZwölfDasJahrDerApokalypse]);
        $user = $this->actingMember();
        Livewire::actingAs($user)->test(RezensionIndex::class)->assertSee($book->title)->assertSee('2012 Roman')->assertSee('Mini-Serie 2012');
    } finally {
        file_put_contents($path, $original);
    }
});

test('index shows volk der tiefe books', function (): void {
    $path = storage_path('app/private/maddrax.json');
    $original = file_get_contents($path);
    try {
        file_put_contents($path, json_encode([['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Wandler']]));
        $book = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        Book::create(['roman_number' => 3, 'title' => 'Volk Roman', 'author' => 'C', 'type' => BookType::DasVolkDerTiefe]);
        $user = $this->actingMember();
        Livewire::actingAs($user)->test(RezensionIndex::class)->assertSee($book->title)->assertSee('Volk Roman');
    } finally {
        file_put_contents($path, $original);
    }
});

test('index shows abenteurer books', function (): void {
    $path = storage_path('app/private/maddrax.json');
    $original = file_get_contents($path);
    try {
        file_put_contents($path, json_encode([['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Ursprung']]));
        $book = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        Book::create(['roman_number' => 3, 'title' => 'Abenteurer Roman', 'author' => 'C', 'type' => BookType::DieAbenteurer]);
        $user = $this->actingMember();
        Livewire::actingAs($user)->test(RezensionIndex::class)->assertSee($book->title)->assertSee('Abenteurer Roman')->assertSee('Die Abenteurer');
    } finally {
        file_put_contents($path, $original);
    }
});

test('show redirects when user has no permission', function (): void {
    $user = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $other = $this->createUserWithRole('Mitglied');
    Review::create(['team_id' => $other->currentTeam->id, 'user_id' => $other->id, 'book_id' => $book->id, 'title' => 'R', 'content' => str_repeat('B', 140)]);
    Livewire::actingAs($user)->test(RezensionShow::class, ['book' => $book])->assertRedirect(route('reviews.create', $book));
});

test('show displays review for ehrenmitglied without own review', function (): void {
    $user = $this->actingMember('Ehrenmitglied');
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $other = $this->createUserWithRole('Mitglied');
    Review::create(['team_id' => $other->currentTeam->id, 'user_id' => $other->id, 'book_id' => $book->id, 'title' => 'R', 'content' => str_repeat('B', 140)]);
    Livewire::actingAs($user)->test(RezensionShow::class, ['book' => $book])->assertSee('R')->assertOk();
});

test('show redirects kassenwart without own review', function (): void {
    $user = $this->actingMember('Kassenwart');
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $other = $this->createUserWithRole('Mitglied');
    Review::create(['team_id' => $other->currentTeam->id, 'user_id' => $other->id, 'book_id' => $book->id, 'title' => 'R', 'content' => str_repeat('B', 140)]);
    Livewire::actingAs($user)->test(RezensionShow::class, ['book' => $book])->assertRedirect(route('reviews.create', $book));
});

test('show displays review for vorstand without own review', function (): void {
    $user = $this->actingMember('Vorstand');
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $other = $this->createUserWithRole('Mitglied');
    Review::create(['team_id' => $other->currentTeam->id, 'user_id' => $other->id, 'book_id' => $book->id, 'title' => 'R', 'content' => str_repeat('B', 140)]);
    Livewire::actingAs($user)->test(RezensionShow::class, ['book' => $book])->assertSee('R')->assertOk();
});

test('show displays review for admin without own review', function (): void {
    $user = $this->actingMember('Admin');
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $other = $this->createUserWithRole('Mitglied');
    Review::create(['team_id' => $other->currentTeam->id, 'user_id' => $other->id, 'book_id' => $book->id, 'title' => 'R', 'content' => str_repeat('B', 140)]);
    Livewire::actingAs($user)->test(RezensionShow::class, ['book' => $book])->assertSee('R')->assertOk();
});

test('show displays review for author', function (): void {
    $user = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    Review::create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'R', 'content' => str_repeat('A', 140)]);
    Livewire::actingAs($user)->test(RezensionShow::class, ['book' => $book])->assertSee('R')->assertSee($user->name)->assertOk();
});

test('show uses nicknames for review comments and replies', function (): void {
    $reviewer = $this->actingMember();
    $reviewer->update(['name' => 'Rita Review', 'alias' => 'ReziRita']);
    $commenter = User::factory()->create(['name' => 'Karl Kommentar', 'alias' => 'Kommentator']);
    $replier = User::factory()->create(['name' => 'Anna Antwort', 'alias' => 'AntwortAss']);
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman mit Nicknames', 'author' => 'Author']);
    $review = Review::create(['team_id' => $reviewer->currentTeam->id, 'user_id' => $reviewer->id, 'book_id' => $book->id, 'title' => 'Nickname-Rezension', 'content' => str_repeat('A', 140)]);
    $comment = ReviewComment::create(['review_id' => $review->id, 'user_id' => $commenter->id, 'content' => 'Kommentar mit Nickname']);
    ReviewComment::create(['review_id' => $review->id, 'user_id' => $replier->id, 'parent_id' => $comment->id, 'content' => 'Antwort mit Nickname']);
    Livewire::actingAs($reviewer)->test(RezensionShow::class, ['book' => $book])->assertSee('ReziRita')->assertSee('Kommentator')->assertSee('AntwortAss')->assertSee('Antwort auf Kommentator');
});

test('show displays update information when review was edited', function (): void {
    $user = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    Carbon::setTestNow(Carbon::create(2025, 7, 16, 17, 0));
    $review = Review::create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'R', 'content' => str_repeat('A', 140)]);
    Carbon::setTestNow(Carbon::create(2025, 7, 17, 17, 30));
    $review->update(['content' => str_repeat('B', 140)]);
    Carbon::setTestNow();
    Livewire::actingAs($user)->test(RezensionShow::class, ['book' => $book])->assertSee('am 16.07.2025 17:00 Uhr')->assertSee('geändert am 17.07.2025 um 17:30 Uhr');
});

test('show does not display update information when review not edited', function (): void {
    $user = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    Carbon::setTestNow(Carbon::create(2025, 7, 16, 17, 0));
    Review::create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'R', 'content' => str_repeat('A', 140)]);
    Carbon::setTestNow();
    Livewire::actingAs($user)->test(RezensionShow::class, ['book' => $book])->assertSee('am 16.07.2025 17:00 Uhr')->assertDontSee('geändert am');
});

test('edit shows form for author', function (): void {
    $user = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $review = Review::create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'R', 'content' => str_repeat('C', 140)]);
    Livewire::actingAs($user)->test(RezensionForm::class, ['review' => $review])->assertSet('title', 'R')->assertOk();
});

test('admin can update review', function (): void {
    $admin = $this->actingMember('Admin');
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $member = $this->actingMember();
    $review = Review::create(['team_id' => $admin->currentTeam->id, 'user_id' => $member->id, 'book_id' => $book->id, 'title' => 'Old', 'content' => str_repeat('D', 140)]);
    Livewire::actingAs($admin)->test(RezensionForm::class, ['review' => $review])->set('title', 'New')->set('content', str_repeat('E', 140))->call('save')->assertRedirect(route('reviews.show', $book));
    $this->assertDatabaseHas('reviews', ['id' => $review->id, 'title' => 'New']);
});

test('admin can delete review', function (): void {
    $admin = $this->actingMember('Admin');
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $member = $this->actingMember();
    $review = Review::create(['team_id' => $admin->currentTeam->id, 'user_id' => $member->id, 'book_id' => $book->id, 'title' => 'Old', 'content' => str_repeat('D', 140)]);
    Livewire::actingAs($admin)->test(RezensionShow::class, ['book' => $book])->call('deleteReview', $review->id)->assertDispatched('toast', type: 'success');
    $this->assertSoftDeleted('reviews', ['id' => $review->id]);
});

test('update validation errors', function (): void {
    $admin = $this->actingMember('Admin');
    $book = Book::create(['roman_number' => 1, 'title' => 'Test', 'author' => 'Autor']);
    $review = Review::create(['team_id' => Team::membersTeam()->id, 'user_id' => $admin->id, 'book_id' => $book->id, 'title' => 'Old', 'content' => str_repeat('A', 140)]);
    Livewire::actingAs($admin)->test(RezensionForm::class, ['review' => $review])->set('title', '')->set('content', 'short')->call('save')->assertHasErrors(['title', 'content']);
});

test('create redirects when review exists', function (): void {
    $user = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    Review::create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'R', 'content' => str_repeat('B', 140)]);
    Livewire::actingAs($user)->test(RezensionForm::class, ['book' => $book])->assertRedirect(route('reviews.show', $book));
});

test('store fails when review exists', function (): void {
    $user = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    Review::create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'R', 'content' => str_repeat('C', 140)]);
    // Mount redirects when review exists
    Livewire::actingAs($user)->test(RezensionForm::class, ['book' => $book])->assertRedirect(route('reviews.show', $book));
});

test('update forbidden for non author', function (): void {
    $owner = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $review = Review::create(['team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'book_id' => $book->id, 'title' => 'Old', 'content' => str_repeat('E', 140)]);
    $other = $this->actingMember();
    Livewire::actingAs($other)->test(RezensionForm::class, ['review' => $review])->assertForbidden();
});

test('update strips heading markers before validation', function (): void {
    $user = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $review = Review::create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'R', 'content' => str_repeat('E', 140)]);
    Livewire::actingAs($user)->test(RezensionForm::class, ['review' => $review])->set('content', '# '.str_repeat('A', 140))->call('save')->assertRedirect(route('reviews.show', $book));
    $this->assertDatabaseHas('reviews', ['id' => $review->id, 'content' => str_repeat('A', 140)]);
});

test('update rejects content too short after stripping heading markers', function (): void {
    $user = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $review = Review::create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'R', 'content' => str_repeat('E', 140)]);
    Livewire::actingAs($user)->test(RezensionForm::class, ['review' => $review])->set('content', '# '.str_repeat('A', 139))->call('save')->assertHasErrors('content');
});

test('destroy forbidden for non author', function (): void {
    $owner = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $review = Review::create(['team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'book_id' => $book->id, 'title' => 'Old', 'content' => str_repeat('E', 140)]);
    $other = $this->actingMember();
    // Give other user their own review so they can view the show page
    Review::create(['team_id' => $other->currentTeam->id, 'user_id' => $other->id, 'book_id' => $book->id, 'title' => 'Other', 'content' => str_repeat('F', 140)]);
    Livewire::actingAs($other)->test(RezensionShow::class, ['book' => $book])->call('deleteReview', $review->id)->assertForbidden();
    $this->assertDatabaseHas('reviews', ['id' => $review->id, 'deleted_at' => null]);
});

test('filter by roman number', function (): void {
    Storage::fake('private');
    Storage::disk('private')->put('maddrax.json', json_encode([['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'], ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1']]));
    Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
    Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);
    $user = $this->actingMember();
    Livewire::actingAs($user)->test(RezensionIndex::class)->set('roman_number', 1)->assertSee('Alpha')->assertDontSee('Beta');
});

test('filter by review status', function (): void {
    Storage::fake('private');
    Storage::disk('private')->put('maddrax.json', json_encode([['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'], ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1']]));
    $book1 = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
    $book2 = Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);
    Review::create(['team_id' => Team::membersTeam()->id, 'user_id' => User::factory()->create()->id, 'book_id' => $book1->id, 'title' => 'R', 'content' => str_repeat('A', 140)]);
    $user = $this->actingMember();
    Livewire::actingAs($user)->test(RezensionIndex::class)->set('review_status', 'without')->assertSee('Beta')->assertDontSee('Alpha');
});

test('filter by author', function (): void {
    Storage::fake('private');
    Storage::disk('private')->put('maddrax.json', json_encode([['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'], ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1']]));
    Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
    Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);
    $user = $this->actingMember();
    Livewire::actingAs($user)->test(RezensionIndex::class)->set('author', 'B')->assertSee('Beta')->assertDontSee('Alpha');
});

test('reset filters', function (): void {
    Storage::fake('private');
    Storage::disk('private')->put('maddrax.json', json_encode([['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'], ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1']]));
    Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
    Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);
    $user = $this->actingMember();
    Livewire::actingAs($user)->test(RezensionIndex::class)->set('roman_number', 1)->assertDontSee('Beta')->call('resetFilters')->assertSee('Alpha')->assertSee('Beta');
});

test('2012 is rendered between ursprung and streiter cycles', function (): void {
    $path = storage_path('app/private/maddrax.json');
    $original = file_get_contents($path);
    try {
        file_put_contents($path, json_encode([['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Ursprung'], ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Streiter']]));
        Book::create(['roman_number' => 1, 'title' => 'Ursprung Alpha', 'author' => 'A']);
        Book::create(['roman_number' => 2, 'title' => 'Streiter Beta', 'author' => 'B']);
        Book::create(['roman_number' => 10, 'title' => '2012 Roman', 'author' => 'C', 'type' => BookType::ZweiTausendZwölfDasJahrDerApokalypse]);
        $user = $this->actingMember();
        $this->actingAs($user)->get('/rezensionen')->assertOk()->assertSeeInOrder(['Ursprung-Zyklus', 'Mini-Serie 2012', 'Streiter-Zyklus'], false);
    } finally {
        file_put_contents($path, $original);
    }
});

test('abenteurer is rendered between ursprung and 2012', function (): void {
    $path = storage_path('app/private/maddrax.json');
    $original = file_get_contents($path);
    try {
        file_put_contents($path, json_encode([['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Ursprung'], ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Streiter']]));
        Book::create(['roman_number' => 1, 'title' => 'Ursprung Alpha', 'author' => 'A']);
        Book::create(['roman_number' => 2, 'title' => 'Streiter Beta', 'author' => 'B']);
        Book::create(['roman_number' => 10, 'title' => 'Abenteurer Roman', 'author' => 'C', 'type' => BookType::DieAbenteurer]);
        Book::create(['roman_number' => 11, 'title' => '2012 Roman', 'author' => 'D', 'type' => BookType::ZweiTausendZwölfDasJahrDerApokalypse]);
        $user = $this->actingMember();
        $this->actingAs($user)->get('/rezensionen')->assertOk()->assertSeeInOrder(['Ursprung-Zyklus', 'Die Abenteurer', 'Mini-Serie 2012', 'Streiter-Zyklus'], false);
    } finally {
        file_put_contents($path, $original);
    }
});

test('cycles follow release order with spin offs and hardcovers at end', function (): void {
    $path = storage_path('app/private/maddrax.json');
    $original = file_get_contents($path);
    try {
        file_put_contents($path, json_encode([['nummer' => 650, 'titel' => 'Weltrat 1', 'zyklus' => 'Weltrat'], ['nummer' => 200, 'titel' => 'Afra 1', 'zyklus' => 'Afra'], ['nummer' => 175, 'titel' => 'Ausala 1', 'zyklus' => 'Ausala'], ['nummer' => 150, 'titel' => 'Mars 1', 'zyklus' => 'Mars'], ['nummer' => 125, 'titel' => 'Wandler 1', 'zyklus' => 'Wandler'], ['nummer' => 1, 'titel' => 'Euree 1', 'zyklus' => 'Euree']]));
        Book::create(['roman_number' => 650, 'title' => 'Weltrat Alpha', 'author' => 'W']);
        Book::create(['roman_number' => 200, 'title' => 'Afra Alpha', 'author' => 'A']);
        Book::create(['roman_number' => 175, 'title' => 'Ausala Alpha', 'author' => 'A']);
        Book::create(['roman_number' => 150, 'title' => 'Mars Alpha', 'author' => 'M']);
        Book::create(['roman_number' => 125, 'title' => 'Wandler Alpha', 'author' => 'W']);
        Book::create(['roman_number' => 1, 'title' => 'Euree Alpha', 'author' => 'E']);
        Book::create(['roman_number' => 5, 'title' => 'Mission Mars 1', 'author' => 'MM', 'type' => BookType::MissionMars]);
        Book::create(['roman_number' => 6, 'title' => 'Volk Roman 1', 'author' => 'VDT', 'type' => BookType::DasVolkDerTiefe]);
        Book::create(['roman_number' => 8, 'title' => '2012 Roman', 'author' => 'JR', 'type' => BookType::ZweiTausendZwölfDasJahrDerApokalypse]);
        Book::create(['roman_number' => 2, 'title' => 'Hardcover 1', 'author' => 'HC', 'type' => BookType::MaddraxHardcover]);
        $user = $this->actingMember();
        $this->actingAs($user)->get('/rezensionen')->assertOk()->assertSeeInOrder(['Weltrat-Zyklus', 'Afra-Zyklus', 'Das Volk der Tiefe', 'Ausala-Zyklus', 'Mars-Zyklus', 'Mission Mars-Heftromane', 'Wandler-Zyklus', 'Euree-Zyklus', 'Mini-Serie 2012', 'Maddrax-Hardcover'], false);
    } finally {
        file_put_contents($path, $original);
    }
});

test('volk der tiefe accordion renders as daisyui collapse', function (): void {
    $path = storage_path('app/private/maddrax.json');
    $original = file_get_contents($path);
    try {
        file_put_contents($path, json_encode([['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Wandler']]));
        Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        Book::create(['roman_number' => 7, 'title' => 'Volk Roman', 'author' => 'C', 'type' => BookType::DasVolkDerTiefe]);
        $user = $this->actingMember();
        $response = $this->actingAs($user)->get('/rezensionen');
        $response->assertOk();
        $html = $response->getContent();
        expect($html)->toMatch('/class="collapse collapse-arrow[^"]*"[^<]*<input[^>]*aria-label="Das Volk der Tiefe ein-\/ausklappen"/');
    } finally {
        file_put_contents($path, $original);
    }
});

test('reviews index shows explicit review write action for books without review', function (): void {
    Book::create(['roman_number' => 1, 'title' => 'Roman ohne Rezension', 'author' => 'Autor A']);
    $user = $this->actingMember();
    $response = $this->actingAs($user)->get('/rezensionen');
    $response->assertOk();
    $response->assertSee('Rezension schreiben');
    $response->assertSee('aria-label="Rezension schreiben"', false);
});

test('reviews index marks reviewed books as vorhanden', function (): void {
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman mit Rezension', 'author' => 'Autor B']);
    $user = $this->actingMember();
    Review::create(['team_id' => Team::membersTeam()->id, 'book_id' => $book->id, 'user_id' => $user->id, 'title' => 'Schon bewertet', 'content' => str_repeat('A', 140)]);
    $response = $this->actingAs($user)->get('/rezensionen');
    $response->assertOk();
    $response->assertSee('Roman mit Rezension');
    $response->assertSee('Vorhanden');
});

PhpunitMetadata::onFile(__FILE__, Large::class);
