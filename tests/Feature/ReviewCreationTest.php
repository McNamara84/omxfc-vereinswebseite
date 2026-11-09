<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Livewire\RezensionForm;
use App\Models\BaxxEarningRule;
use App\Models\Book;
use App\Models\Review;
use App\Models\ReviewBaxxSpecialOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('member can store review', function (): void {
    Mail::fake();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author One']);
    $user = $this->actingMember();
    $this->actingAs($user);
    Livewire::test(RezensionForm::class, ['book' => $book])->set('title', 'Tolles Buch')->set('content', str_repeat('A', 150))->call('save')->assertRedirect(route('reviews.show', $book));
    $this->assertDatabaseHas('reviews', ['book_id' => $book->id, 'user_id' => $user->id, 'title' => 'Tolles Buch']);
    Mail::assertNothingSent();
});

test('member can store review with supported markdown content', function (): void {
    Mail::fake();
    $book = Book::create(['roman_number' => 13, 'title' => 'Markdown Roman', 'author' => 'Author Markdown']);
    $user = $this->actingMember();
    $this->actingAs($user);
    $content = <<<'MD'
    **Fettes Intro** und *kursiver Einschub*.

    - Erster Punkt
    - Zweiter Punkt

    1. Anfang
    2. Fortsetzung

    [Mehr dazu](https://example.com/reviews)

    Zum Abschluss folgt noch ausreichend Fliesstext, damit die Mindestlaenge fuer Rezensionen sicher erreicht wird.
    MD;
    Livewire::test(RezensionForm::class, ['book' => $book])->set('title', 'Markdown Rezension')->set('content', $content)->call('save')->assertRedirect(route('reviews.show', $book));
    $this->assertDatabaseHas('reviews', ['book_id' => $book->id, 'user_id' => $user->id, 'title' => 'Markdown Rezension', 'content' => $content]);
});

test('member can store hardcover review', function (): void {
    Mail::fake();
    $book = Book::create(['roman_number' => 1, 'title' => 'Hardcover1', 'author' => 'Author One', 'type' => BookType::MaddraxHardcover]);
    $user = $this->actingMember();
    $this->actingAs($user);
    Livewire::test(RezensionForm::class, ['book' => $book])->set('title', 'Tolles Hardcover')->set('content', str_repeat('A', 150))->call('save')->assertRedirect(route('reviews.show', $book));
    $this->assertDatabaseHas('reviews', ['book_id' => $book->id, 'user_id' => $user->id, 'title' => 'Tolles Hardcover']);
    Mail::assertNothingSent();
});

test('point awarded on every tenth review', function (): void {
    Mail::fake();
    $user = $this->actingMember();
    $this->actingAs($user);
    // create nine existing reviews for the user
    for ($i = 1; $i <= 9; $i++) {
        $book = Book::create(['roman_number' => $i, 'title' => 'Roman'.$i, 'author' => 'Author']);
        Review::create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'Review'.$i, 'content' => str_repeat('A', 150)]);
    }
    $newBook = Book::create(['roman_number' => 10, 'title' => 'Roman10', 'author' => 'Author']);
    Livewire::test(RezensionForm::class, ['book' => $newBook])->set('title', 'Tolles Buch')->set('content', str_repeat('A', 150))->call('save');
    $this->assertDatabaseCount('user_points', 1);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'points' => 1]);
});

test('active special offer awards points using special offer rule', function (): void {
    Mail::fake();
    ReviewBaxxSpecialOffer::create(['points' => 2, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $user = $this->actingMember();
    $this->actingAs($user);
    $book = Book::create(['roman_number' => 11, 'title' => 'Roman11', 'author' => 'Author']);
    Livewire::test(RezensionForm::class, ['book' => $book])->set('title', 'Aktionsreview')->set('content', str_repeat('A', 150))->call('save');
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'points' => 2]);
});

test('expired special offer falls back to updated base rule', function (): void {
    Mail::fake();
    $rule = BaxxEarningRule::where('action_key', 'rezension')->firstOrFail();
    $rule->update(['points' => 3, 'every_count' => 1]);
    ReviewBaxxSpecialOffer::create(['points' => 2, 'every_count' => 1, 'ends_at' => now()->subMinute(), 'is_active' => true]);
    $user = $this->actingMember();
    $this->actingAs($user);
    $book = Book::create(['roman_number' => 12, 'title' => 'Roman12', 'author' => 'Author']);
    Livewire::test(RezensionForm::class, ['book' => $book])->set('title', 'Fallbackreview')->set('content', str_repeat('A', 150))->call('save');
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'points' => 3]);
    $this->assertDatabaseMissing('user_points', ['user_id' => $user->id, 'points' => 2]);
});

test('non member cannot store review', function (): void {
    $book = Book::create(['roman_number' => 2, 'title' => 'Roman2', 'author' => 'Author Two']);
    $user = User::factory()->create();
    $this->actingAs($user);
    Livewire::test(RezensionForm::class, ['book' => $book])->assertStatus(403);
});

test('create review form has accessible labels and structure', function (): void {
    $book = Book::create(['roman_number' => 99, 'title' => 'Acc Test', 'author' => 'Author']);
    $user = $this->actingMember();
    $this->actingAs($user);
    Livewire::test(RezensionForm::class, ['book' => $book])->assertSee('<fieldset', false)->assertSee('fieldset-legend', false)->assertSee('Rezensionstitel', false)->assertSee('Rezensionstext', false)->assertSee('Mindestens 140 Zeichen.', false)->assertSee('id="review-content-hint"', false)->assertSee('aria-describedby="review-markdown-help review-content-hint review-content-error"', false)->assertSee('data-testid="review-markdown-toolbar"', false)->assertSee('data-markdown-action="bold"', false)->assertSee('data-markdown-action="italic"', false)->assertSee('data-markdown-action="bullet-list"', false)->assertSee('data-markdown-action="numbered-list"', false)->assertSee('data-markdown-action="link"', false);
});

test('review creation validation errors', function (): void {
    $book = Book::create(['roman_number' => 100, 'title' => 'Val Test', 'author' => 'Author']);
    $user = $this->actingMember();
    $this->actingAs($user);
    Livewire::test(RezensionForm::class, ['book' => $book])->set('title', '')->set('content', '')->call('save')->assertHasErrors(['title', 'content']);
});
