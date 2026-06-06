<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Enums\Role;
use App\Enums\TodoStatus;
use App\Livewire\RezensionForm;
use App\Livewire\RomantauschOfferForm;
use App\Livewire\RomantauschRequestForm;
use App\Livewire\TodoIndex;
use App\Models\Activity;
use App\Models\AdminMessage;
use App\Models\Book;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Fanfiction;
use App\Models\FanfictionComment;
use App\Models\FantreffenAnmeldung;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\RewardService;
use App\Services\Romantausch\SwapMatchingService;
use App\Support\PreviewText;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
            'type' => BookType::MaddraxDieDunkleZukunftDerErde,
        ]);
    }

    private function actingMember(Role $role = Role::Mitglied): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    public function test_activity_created_for_new_review(): void
    {
        $book = Book::first();

        $user = $this->actingMember();

        Livewire::actingAs($user)
            ->test(RezensionForm::class, ['book' => $book])
            ->set('title', 'Tolle Rezension')
            ->set('content', str_repeat('A', 140))
            ->call('save');

        $review = Review::first();
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'subject_type' => Review::class,
            'subject_id' => $review->id,
        ]);
    }

    public function test_activity_created_for_new_book_offer(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        Livewire::test(RomantauschOfferForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_number', 1)
            ->set('condition', 'Z0')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $offer = BookOffer::first();
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'subject_type' => BookOffer::class,
            'subject_id' => $offer->id,
        ]);
    }

    public function test_activity_created_for_new_book_request(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        Livewire::test(RomantauschRequestForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_number', 1)
            ->set('condition', 'Z0')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $requestModel = BookRequest::first();
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'subject_type' => BookRequest::class,
            'subject_id' => $requestModel->id,
        ]);
    }

    public function test_activity_created_for_new_review_comment(): void
    {
        $book = Book::first();

        $user = $this->actingMember();
        $this->actingAs($user);

        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'Meine Rezension',
            'content' => str_repeat('A', 140),
        ]);

        $response = $this->post(route('reviews.comments.store', $review), [
            'content' => 'Tolles Buch!',
        ]);

        $response->assertRedirect();
        $comment = ReviewComment::first();
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'subject_type' => ReviewComment::class,
            'subject_id' => $comment->id,
        ]);

        $dashboard = $this->get('/dashboard');
        $dashboard->assertOk();
        $dashboard->assertSeeTextInOrder(['Kommentar zu', 'Meine Rezension', 'von', $user->name]);
        $dashboard->assertSee('<a href="'.route('reviews.show', $review->book_id).'" wire:navigate class="text-info hover:underline">Meine Rezension</a>', false);
        $dashboard->assertSee('<a href="'.route('profile.view', $user->id).'" wire:navigate', false);
        $dashboard->assertSeeText('Tolles Buch!');
    }

    public function test_dashboard_shows_review_preview_excerpt(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $reviewContent = 'Dies ist eine ausführliche Rezension über Maddrax, die den Auftakt zusammenfasst und Lust auf mehr macht.';
        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => Book::first()->id,
            'title' => 'Ein moderner Start',
            'content' => $reviewContent,
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => Review::class,
            'subject_id' => $review->id,
        ]);

        $response = $this->get('/dashboard');
        $response->assertOk();
        $response->assertSeeText('Neue Rezension: '.$review->title);
        $response->assertSeeText(PreviewText::make($reviewContent, 160));
    }

    public function test_dashboard_limits_comment_preview_excerpt(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => Book::first()->id,
            'title' => 'Kommentar-Test',
            'content' => 'Kurze Review',
        ]);

        $longComment = str_repeat('Eine sehr ausführliche Meinung mit vielen Details. ', 6);
        $comment = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'content' => $longComment,
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => ReviewComment::class,
            'subject_id' => $comment->id,
        ]);

        $response = $this->get('/dashboard');
        $response->assertOk();
        $expectedPreview = PreviewText::make($longComment, 140);
        $response->assertSeeTextInOrder(['Kommentar zu', $review->title, 'von', $user->name]);
        $response->assertSeeText($expectedPreview);
    }

    public function test_dashboard_handles_review_comment_with_missing_user(): void
    {
        $viewer = $this->actingMember();
        $this->actingAs($viewer);

        $commentAuthor = User::factory()->create([
            'current_team_id' => $viewer->currentTeam->id,
            'name' => 'Verwaister Kommentarautor',
        ]);

        $review = Review::create([
            'team_id' => $viewer->currentTeam->id,
            'user_id' => $viewer->id,
            'book_id' => Book::first()->id,
            'title' => 'Kommentar ohne Autorprofil',
            'content' => 'Die Rezension bleibt bestehen.',
        ]);

        $comment = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $commentAuthor->id,
            'content' => 'Der Autor des Kommentars wurde entfernt.',
        ]);

        Activity::create([
            'user_id' => null,
            'subject_type' => ReviewComment::class,
            'subject_id' => $comment->id,
        ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Kommentar zu');
        $response->assertSeeText($review->title);
        $response->assertSeeText('Unbekannter Nutzer');
        $response->assertSeeText('Der Autor des Kommentars wurde entfernt.');
        $response->assertDontSeeText($commentAuthor->name);
    }

    public function test_dashboard_hides_empty_review_preview_excerpt(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $reviewContent = '<p>    </p>';
        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => Book::first()->id,
            'title' => 'Leerer Inhalt',
            'content' => $reviewContent,
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => Review::class,
            'subject_id' => $review->id,
        ]);

        $response = $this->get('/dashboard');
        $response->assertOk();
        $response->assertSeeText('Neue Rezension: '.$review->title);
        $response->assertDontSeeText('Auszug aus der Rezension');
        $response->assertDontSee('„');
    }

    public function test_dashboard_hides_empty_comment_preview_excerpt(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => Book::first()->id,
            'title' => 'Kommentar-Test',
            'content' => 'Kurze Review',
        ]);

        $comment = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'content' => '<div>   </div>',
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => ReviewComment::class,
            'subject_id' => $comment->id,
        ]);

        $response = $this->get('/dashboard');
        $response->assertOk();
        $response->assertSeeTextInOrder(['Kommentar zu', $review->title, 'von', $user->name]);
        $response->assertDontSeeText('Auszug aus dem Kommentar');
        $response->assertDontSee('„');
    }

    public function test_activity_created_and_displayed_for_reward_unlock(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => null,
            'points' => 12,
        ]);

        $reward = Reward::factory()->create([
            'title' => 'Mitgliederkarte',
            'slug' => 'activity-feed-mitgliederkarte',
            'cost_baxx' => 5,
        ]);

        $purchase = app(RewardService::class)->purchaseReward($user, $reward);

        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'subject_type' => RewardPurchase::class,
            'subject_id' => $purchase->id,
            'action' => 'reward_unlocked',
        ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText($user->name);
        $response->assertSeeText('Mitgliederkarte');
        $response->assertSeeText('freigeschaltet');
    }

    public function test_dashboard_shows_published_fanfiction_activity(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Orbit im Sturm',
            'content' => 'Die Crew erreicht eine verlassene Station und entdeckt dort ein seltsames Signal, das immer stärker wird.',
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => Fanfiction::class,
            'subject_id' => $fanfiction->id,
            'action' => 'published',
        ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Neue Fanfiction: Orbit im Sturm');
        $response->assertSeeText(PreviewText::make($fanfiction->content, 160));
    }

    public function test_dashboard_shows_teaser_preview_for_locked_fanfiction_activity(): void
    {
        $viewer = $this->actingMember();
        $author = $this->actingMember();
        $this->actingAs($viewer);

        $reward = Reward::factory()->create([
            'title' => 'Fanfiction-Freischaltung',
        ]);

        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $viewer->currentTeam->id,
            'user_id' => $author->id,
            'created_by' => $author->id,
            'reward_id' => $reward->id,
            'title' => 'Geheimsignal',
            'content' => 'Die Crew entdeckt [den verborgenen Sender](https://example.com/spoiler) und entschlüsselt das Signal. '.str_repeat('Weitere Hinweise tauchen auf. ', 12),
        ]);

        Activity::create([
            'user_id' => $author->id,
            'subject_type' => Fanfiction::class,
            'subject_id' => $fanfiction->id,
            'action' => 'published',
        ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Neue Fanfiction: Geheimsignal');
        $response->assertSeeText((string) PreviewText::make($fanfiction->teaser, 160));
        $response->assertDontSeeText((string) PreviewText::make($fanfiction->content, 160));
    }

    public function test_dashboard_handles_deleted_fanfiction_activity(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Verlorenes Logbuch',
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => Fanfiction::class,
            'subject_id' => $fanfiction->id,
            'action' => 'published',
        ]);

        $fanfiction->delete();

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Gelöschter Eintrag');
        $response->assertSeeText('nicht mehr verfügbar');
    }

    public function test_dashboard_shows_fanfiction_comment_activity(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Signal aus der Tiefe',
        ]);

        $comment = FanfictionComment::factory()->create([
            'fanfiction_id' => $fanfiction->id,
            'user_id' => $user->id,
            'content' => 'Spannender Einstieg mit starkem Cliffhanger am Ende.',
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => FanfictionComment::class,
            'subject_id' => $comment->id,
            'action' => 'created',
        ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeTextInOrder(['Kommentar zu', $fanfiction->title, 'von', $user->name]);
        $response->assertSeeText(PreviewText::make($comment->content, 140));
    }

    public function test_dashboard_shows_bundle_activity_as_package(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'Z1',
            'bundle_id' => 'paket-001',
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => BookOffer::class,
            'subject_id' => $offer->id,
            'action' => 'bundle_created',
        ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Neues Romantausch-Paket');
        $response->assertSeeText('Roman1');
        $response->assertSeeText('Mehrere Heftangebote wurden als Paket für die Börse eingestellt.');
        $response->assertDontSeeText('Neues Angebot: Roman1');
    }

    public function test_activity_created_and_displayed_for_completed_swap(): void
    {
        $offerUser = $this->actingMember();
        $requestUser = $this->actingMember();
        $this->actingAs($offerUser);

        $offer = BookOffer::create([
            'user_id' => $offerUser->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 21,
            'book_title' => 'Maddrax 21',
            'condition' => 'neu',
            'completed' => false,
        ]);

        $request = BookRequest::create([
            'user_id' => $requestUser->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 21,
            'book_title' => 'Maddrax 21',
            'condition' => 'neu',
            'completed' => false,
        ]);

        $swap = BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);

        $service = app(SwapMatchingService::class);
        $service->confirmSwap($swap->fresh(['offer.user', 'request.user']), $offerUser);
        $service->confirmSwap($swap->fresh(['offer.user', 'request.user']), $requestUser);

        $this->assertDatabaseHas('activities', [
            'user_id' => null,
            'subject_type' => BookSwap::class,
            'subject_id' => $swap->id,
            'action' => 'swap_completed',
        ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Tausch erfolgreich abgeschlossen');
        $response->assertSeeText($offerUser->name);
        $response->assertSeeText($requestUser->name);
        $response->assertSeeText('haben ihren Romantausch bestätigt.');
        $response->assertSeeText('Maddrax 21');
        $response->assertDontSeeText('Unbekannter Nutzer');
    }

    public function test_dashboard_shows_completed_challenge_with_baxx_value(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $category = TodoCategory::create(['name' => 'Baxx', 'slug' => 'baxx']);
        $todo = Todo::create([
            'team_id' => $user->currentTeam->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'title' => 'Archiv pflegen',
            'points' => 7,
            'category_id' => $category->id,
            'status' => TodoStatus::Verified->value,
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => Todo::class,
            'subject_id' => $todo->id,
            'action' => 'completed',
        ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Archiv pflegen');
        $response->assertSeeText('7 Baxx');
        $response->assertSeeText('erfolgreich abgeschlossen');
    }

    public function test_dashboard_shows_baxx_milestone_activity(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => null,
            'points' => 100,
        ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText($user->name);
        $response->assertSeeText('100 Baxx erreicht');
        $response->assertSeeText('Meilenstein');
    }

    public function test_dashboard_displays_recent_activities(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => Book::create(['roman_number' => 2, 'title' => 'B', 'author' => 'A', 'type' => BookType::MaddraxDieDunkleZukunftDerErde])->id,
            'title' => 'R',
            'content' => str_repeat('A', 140),
        ]);
        Activity::create([
            'user_id' => $user->id,
            'subject_type' => Review::class,
            'subject_id' => $review->id,
            'created_at' => now()->subMinutes(2),
        ]);
        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);
        Activity::create([
            'user_id' => $user->id,
            'subject_type' => BookOffer::class,
            'subject_id' => $offer->id,
            'created_at' => now()->subMinute(),
        ]);
        $requestModel = BookRequest::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);
        Activity::create([
            'user_id' => $user->id,
            'subject_type' => BookRequest::class,
            'subject_id' => $requestModel->id,
        ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $this->assertCount(3, $response->viewData('activities'));
    }

    public function test_dashboard_displays_fantreffen_registration_activity_without_profile_link(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $anmeldung = FantreffenAnmeldung::create([
            'user_id' => $user->id,
            'vorname' => 'Alex',
            'nachname' => 'Muster',
            'email' => 'alex@example.com',
            'payment_status' => 'free',
            'payment_amount' => 0,
            'tshirt_bestellt' => false,
            'ist_mitglied' => true,
            'zahlungseingang' => false,
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => FantreffenAnmeldung::class,
            'subject_id' => $anmeldung->id,
            'action' => 'fantreffen_registered',
            'created_at' => now(),
        ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Alex hat sich zum Fantreffen in Coellen angemeldet');
        $response->assertDontSee('<a href="'.route('profile.view', $user->id).'"', false);
    }

    public function test_dashboard_handles_guest_fantreffen_registration_activity_without_user()
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $anmeldung = FantreffenAnmeldung::create([
            'user_id' => null,
            'vorname' => 'Jamie',
            'nachname' => 'Guest',
            'email' => 'jamie@example.com',
            'payment_status' => 'pending',
            'payment_amount' => 5,
            'tshirt_bestellt' => false,
            'ist_mitglied' => false,
            'zahlungseingang' => false,
        ]);

        Activity::create([
            'user_id' => null,
            'subject_type' => FantreffenAnmeldung::class,
            'subject_id' => $anmeldung->id,
            'action' => 'fantreffen_registered',
            'created_at' => now(),
        ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Jamie hat sich zum Fantreffen in Coellen angemeldet');
        $response->assertDontSee('Unbekannter Nutzer', false);
    }

    public function test_activity_user_id_migration_down_removes_null_user_records(): void
    {
        $member = $this->actingMember();
        $this->actingAs($member);

        $anmeldung = FantreffenAnmeldung::create([
            'user_id' => null,
            'vorname' => 'Casey',
            'nachname' => 'Guest',
            'email' => 'casey@example.com',
            'payment_status' => 'pending',
            'payment_amount' => 5,
            'tshirt_bestellt' => false,
            'ist_mitglied' => false,
            'zahlungseingang' => false,
        ]);

        $orphanedActivity = Activity::create([
            'user_id' => null,
            'subject_type' => FantreffenAnmeldung::class,
            'subject_id' => $anmeldung->id,
            'action' => 'fantreffen_registered',
        ]);

        $migration = require base_path('database/migrations/2025_12_02_000001_make_activities_user_id_nullable.php');

        $migration->down();

        $this->assertDatabaseMissing('activities', ['id' => $orphanedActivity->id]);

        try {
            Activity::create([
                'user_id' => null,
                'subject_type' => FantreffenAnmeldung::class,
                'subject_id' => $anmeldung->id,
                'action' => 'fantreffen_registered',
            ]);
            $this->fail('Activity creation with null user_id should fail after down migration.');
        } catch (QueryException $exception) {
            $this->assertMatchesRegularExpression('/NOT NULL|cannot be null/i', $exception->getMessage());
        } finally {
            $migration->up();
        }
    }

    public function test_activity_created_when_challenge_is_accepted(): void
    {
        $user = $this->actingMember();

        $category = TodoCategory::create(['name' => 'Test', 'slug' => 'test']);
        $todo = Todo::create([
            'team_id' => $user->currentTeam->id,
            'created_by' => $user->id,
            'title' => 'Challenge',
            'points' => 5,
            'category_id' => $category->id,
            'status' => TodoStatus::Open->value,
        ]);

        Livewire::actingAs($user)
            ->test(TodoIndex::class)
            ->call('assign', $todo->id);

        $todo->refresh();
        $this->assertSame(TodoStatus::Assigned, $todo->status);

        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'subject_type' => Todo::class,
            'subject_id' => $todo->id,
            'action' => 'accepted',
        ]);
    }

    public function test_activity_created_when_challenge_is_verified(): void
    {
        $assignee = $this->actingMember();
        $admin = $this->actingMember(Role::Admin);
        $category = TodoCategory::create(['name' => 'Test2', 'slug' => 'test2']);
        $todo = Todo::create([
            'team_id' => $assignee->currentTeam->id,
            'created_by' => $admin->id,
            'assigned_to' => $assignee->id,
            'title' => 'Challenge',
            'points' => 5,
            'category_id' => $category->id,
            'status' => TodoStatus::Completed->value,
            'completed_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(TodoIndex::class)
            ->call('verify', $todo->id);

        $todo->refresh();
        $this->assertSame(TodoStatus::Verified, $todo->status);

        $this->assertDatabaseHas('activities', [
            'user_id' => $assignee->id,
            'subject_type' => Todo::class,
            'subject_id' => $todo->id,
            'action' => 'completed',
        ]);
    }

    public function test_activity_created_when_member_application_is_approved(): void
    {
        Mail::fake();
        $admin = $this->actingMember(Role::Admin);
        $team = $admin->currentTeam;
        $anwaerter = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($anwaerter, ['role' => Role::Anwaerter->value]);

        $this->actingAs($admin)->post(route('anwaerter.approve', $anwaerter));

        $this->assertDatabaseHas('activities', [
            'user_id' => $admin->id,
            'subject_type' => User::class,
            'subject_id' => $anwaerter->id,
            'action' => 'member_approved',
        ]);

        $dashboard = $this->get('/dashboard');
        $dashboard->assertSeeText('Wir begrüßen unser neues Mitglied '.$anwaerter->name);
    }

    public function test_dashboard_shows_fallback_when_activity_subject_missing(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $category = TodoCategory::create(['name' => 'Fallback', 'slug' => 'fallback']);
        $todo = Todo::create([
            'team_id' => $user->currentTeam->id,
            'created_by' => $user->id,
            'title' => 'Vergängliche Challenge',
            'points' => 3,
            'category_id' => $category->id,
            'status' => TodoStatus::Open->value,
        ]);

        Livewire::actingAs($user)
            ->test(TodoIndex::class)
            ->call('assign', $todo->id);

        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'subject_type' => Todo::class,
            'subject_id' => $todo->id,
            'action' => 'accepted',
        ]);

        $todo->delete();

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Gelöschter Eintrag – nicht mehr verfügbar');
    }

    public function test_dashboard_shows_fallback_when_review_subject_soft_deleted(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $book = Book::first();

        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'Kurzlebige Rezension',
            'content' => str_repeat('C', 160),
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => Review::class,
            'subject_id' => $review->id,
        ]);

        $review->delete();

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Gelöschter Eintrag – nicht mehr verfügbar');
    }

    public function test_dashboard_handles_review_comment_with_deleted_review(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $book = Book::first();

        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'Verwaiste Rezension',
            'content' => str_repeat('B', 150),
        ]);

        $comment = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'content' => 'Schade, dass sie weg ist.',
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => ReviewComment::class,
            'subject_id' => $comment->id,
        ]);

        $review->delete();

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Kommentar – Bezug nicht mehr verfügbar');
    }

    public function test_dashboard_handles_missing_admin_message_subject_without_delete_form(): void
    {
        $admin = $this->actingMember(Role::Admin);
        $this->actingAs($admin);

        $message = AdminMessage::create([
            'user_id' => $admin->id,
            'message' => 'Bitte beachtet die Regeln.',
        ]);

        Activity::create([
            'user_id' => $admin->id,
            'subject_type' => AdminMessage::class,
            'subject_id' => $message->id,
        ]);

        $message->delete();

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Gelöschter Eintrag – nicht mehr verfügbar');
        $response->assertDontSee('Nachricht löschen?');
    }

    public function test_dashboard_handles_missing_member_subject(): void
    {
        $admin = $this->actingMember(Role::Admin);
        $this->actingAs($admin);

        $newMember = User::factory()->create(['current_team_id' => $admin->currentTeam->id]);

        Activity::create([
            'user_id' => $admin->id,
            'subject_type' => User::class,
            'subject_id' => $newMember->id,
            'action' => 'member_approved',
        ]);

        $newMember->delete();

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Gelöschter Eintrag – nicht mehr verfügbar');
        $response->assertDontSee('Wir begrüßen unser neues Mitglied');
    }
}
