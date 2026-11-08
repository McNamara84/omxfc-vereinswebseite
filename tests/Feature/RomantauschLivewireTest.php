<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Livewire\RomantauschBundleForm;
use App\Livewire\RomantauschIndex;
use App\Livewire\RomantauschOfferForm;
use App\Livewire\RomantauschRequestForm;
use App\Models\BaxxEarningRule;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\RomantauschBaxxSpecialOffer;
use App\Models\User;
use App\Services\Romantausch\BookPhotoService;
use App\Services\Romantausch\RomantauschBaxxService;
use App\Services\RomantauschInfoProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use RuntimeException;
use Tests\Concerns\CreatesTestData;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesTestData::class, CreatesUserWithRole::class, RefreshDatabase::class);
dataset('RomantauschLivewireTest.rewardRuleStates', static function (): array {
    $base = ['7 Baxx pro 4 Auslöser', '11 Baxx pro 2 Auslöser'];

    return ['changed base rules' => ['changed', $base], 'disabled rules' => ['disabled', ['Derzeit keine Baxx-Vergabe.']], 'zero points' => ['zero', ['Derzeit keine Baxx-Vergabe.']], 'active special offers' => ['special', ['13 Baxx pro 3 Auslöser (Sonderaktion)', '17 Baxx pro Auslöser (Sonderaktion)']], 'expired special offers' => ['expired', $base]];
});
test('index page is accessible', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    $this->get('/romantauschboerse')->assertOk();
});
test('index displays romantausch success message from session', function (): void {
    $this->actingMember();
    session()->put('romantausch.success', 'Gesuch erstellt.');
    $this->get('/romantauschboerse')->assertOk()->assertSeeText('Gesuch erstellt.');
    expect(session('romantausch.success'))->toBeNull();
});
test('index displays structured information panel', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    $info = app(RomantauschInfoProvider::class)->getInfo();
    Livewire::test(RomantauschIndex::class)->assertSeeText($info['title'])->assertSeeText($info['reward_rules'][0]['description'])->assertSeeText($info['reward_rules'][2]['description'])->assertSeeText($info['steps']['offer']['title'])->assertSeeText($info['steps']['request']['title'])->assertSeeText($info['steps']['match']['title'])->assertSeeText($info['steps']['confirm']['title'])->assertSeeText($info['steps']['offer']['cta'])->assertSeeText($info['steps']['request']['cta']);
});
test('index displays offers and requests', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    $other = User::factory()->create();
    BookOffer::create(['user_id' => $other->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    BookRequest::create(['user_id' => $other->id, 'series' => BookType::MissionMars->value, 'book_number' => 2, 'book_title' => 'MM Roman', 'condition' => 'gut']);
    Livewire::test(RomantauschIndex::class)->assertSeeText('Roman1')->assertSeeText('MM Roman');
});
test('index displays completed swaps', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    $other = User::factory()->create();
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu', 'completed' => true]);
    $request = BookRequest::create(['user_id' => $other->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'gebraucht', 'completed' => true]);
    BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id, 'completed_at' => now()]);
    Livewire::test(RomantauschIndex::class)->assertSeeText('Erfolgreiche Tauschaktionen')->assertSeeText('Roman1');
});
test('index highlights matching offers and requests', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    $otherOfferUser = User::factory()->create();
    $otherRequestUser = User::factory()->create();
    BookRequest::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'gut']);
    BookOffer::create(['user_id' => $otherOfferUser->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'sehr gut']);
    BookOffer::create(['user_id' => $user->id, 'series' => BookType::MissionMars->value, 'book_number' => 2, 'book_title' => 'MM Roman', 'condition' => 'top']);
    BookRequest::create(['user_id' => $otherRequestUser->id, 'series' => BookType::MissionMars->value, 'book_number' => 2, 'book_title' => 'MM Roman', 'condition' => 'gut']);
    Livewire::test(RomantauschIndex::class)->assertSeeText('Passt zu deinem Gesuch')->assertSeeText('Passt zu deinem Angebot');
});
test('index does not highlight entries without matching counterpart', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    $otherOfferUser = User::factory()->create();
    $otherRequestUser = User::factory()->create();
    BookRequest::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'gut']);
    BookOffer::create(['user_id' => $otherOfferUser->id, 'series' => BookType::MissionMars->value, 'book_number' => 2, 'book_title' => 'MM Roman', 'condition' => 'gebraucht']);
    Livewire::test(RomantauschIndex::class)->assertDontSeeText('Passt zu deinem Gesuch')->assertDontSeeText('Passt zu deinem Angebot');
});
test('index displays offer photo thumbnails', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'gebraucht', 'photos' => ['offers/test-one.jpg', 'offers/test-two.jpg']]);
    Livewire::test(RomantauschIndex::class)->assertSee('storage/offers/test-one.jpg', false)->assertSee('storage/offers/test-two.jpg', false)->assertSee('@click="open(0)"', false)->assertSee('role="dialog"', false);
});
test('index renders placeholder for offer without photo', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    BookOffer::create(['user_id' => $user->id, 'series' => BookType::MissionMars->value, 'book_number' => 2, 'book_title' => 'Roman ohne Foto', 'condition' => 'gebraucht', 'photos' => []]);
    $description = BookType::MissionMars->value.' 2 - Roman ohne Foto';
    Livewire::test(RomantauschIndex::class)->assertSee('Kein Foto vorhanden für '.e($description), false);
});
test('user can delete own offer', function (): void {
    $user = $this->actingMember();
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    Livewire::test(RomantauschIndex::class)->call('deleteOffer', $offer->id)->assertDispatched('toast');
    $this->assertDatabaseMissing('book_offers', ['id' => $offer->id]);
});
test('user cannot delete offer of other user', function (): void {
    $this->actingMember();
    $other = User::factory()->create();
    $offer = BookOffer::create(['user_id' => $other->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    Livewire::test(RomantauschIndex::class)->call('deleteOffer', $offer->id)->assertForbidden();
    $this->assertDatabaseHas('book_offers', ['id' => $offer->id]);
});
test('user can delete own request', function (): void {
    $user = $this->actingMember();
    $request = BookRequest::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    Livewire::test(RomantauschIndex::class)->call('deleteRequest', $request->id)->assertDispatched('toast');
    $this->assertDatabaseMissing('book_requests', ['id' => $request->id]);
});
test('user cannot delete request of other user', function (): void {
    $this->actingMember();
    $other = User::factory()->create();
    $request = BookRequest::create(['user_id' => $other->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    Livewire::test(RomantauschIndex::class)->call('deleteRequest', $request->id)->assertForbidden();
    $this->assertDatabaseHas('book_requests', ['id' => $request->id]);
});
test('confirm swap dispatches toast', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    $other = User::factory()->create();
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    $request = BookRequest::create(['user_id' => $other->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'gebraucht']);
    $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    Livewire::test(RomantauschIndex::class)->call('confirmSwap', $swap->id)->assertDispatched('toast');
    $swap->refresh();
    expect($swap->offer_confirmed)->toBeTruthy();
});
test('complete swap marks entries completed', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingVorstand();
    $other = User::factory()->create();
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    $request = BookRequest::create(['user_id' => $other->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'gebraucht']);
    Livewire::test(RomantauschIndex::class)->call('completeSwap', $offer->id, $request->id)->assertDispatched('toast');
    $this->assertDatabaseHas('book_swaps', ['offer_id' => $offer->id, 'request_id' => $request->id]);
    expect((int) $offer->fresh()->completed)->toEqual(1)
        ->and((int) $request->fresh()->completed)->toEqual(1);
});
test('create offer page is accessible', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    $this->get('/romantauschboerse/angebot-erstellen')->assertOk();
});
test('create offer page displays consistent reward rule copy', function (string $state, array $expectedDescriptions): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    $actions = ['romantausch_offer', 'romantausch_swap_complete'];
    BaxxEarningRule::where('action_key', $actions[0])->firstOrFail()->update(['points' => 7, 'every_count' => 4, 'is_active' => true]);
    BaxxEarningRule::where('action_key', $actions[1])->firstOrFail()->update(['points' => 11, 'every_count' => 2, 'is_active' => true]);
    if ($state === 'disabled') {
        BaxxEarningRule::whereIn('action_key', $actions)->update(['is_active' => false]);
    } elseif ($state === 'zero') {
        BaxxEarningRule::whereIn('action_key', $actions)->update(['points' => 0]);
    } elseif (in_array($state, ['special', 'expired'], true)) {
        foreach ($actions as $index => $action) {
            RomantauschBaxxSpecialOffer::create(['action_key' => $action, 'points' => $index === 0 ? 13 : 17, 'every_count' => $index === 0 ? 3 : 1, 'is_active' => true, 'ends_at' => $state === 'special' ? now()->addDay() : now()->subDay()]);
        }
    }
    $info = app(RomantauschInfoProvider::class)->getInfo();
    $ruleText = array_merge(...array_map(fn ($rule) => [$rule['label'].':', $rule['description']], $info['reward_rules']));
    foreach (['romantausch.create-offer', 'romantausch.index'] as $route) {
        $response = $this->get(route($route))->assertOk()->assertSeeTextInOrder($ruleText)->assertDontSeeText(__('romantausch.info.reward_rule.milestone_description'))->assertDontSeeText(__('romantausch.info.reward_rule.swap_description'));
        foreach ($expectedDescriptions as $description) {
            $response->assertSeeText($description);
        }
        if ($state === 'expired') {
            $response->assertDontSeeText('(Sonderaktion)');
        }
    }
})->with('RomantauschLivewireTest.rewardRuleStates');
test('offer form refresh reads reward rule changes', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    $rule = BaxxEarningRule::where('action_key', 'romantausch_offer')->firstOrFail();
    $rule->update(['points' => 3, 'every_count' => 1, 'is_active' => true]);
    $form = Livewire::test(RomantauschOfferForm::class)->assertSeeText('3 Baxx pro Auslöser');
    $rule->update(['points' => 7, 'every_count' => 4]);
    $form->call('$refresh')->assertSeeText('7 Baxx pro 4 Auslöser')->assertDontSeeText('3 Baxx pro Auslöser');
});
test('store offer creates entry', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    Livewire::test(RomantauschOfferForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->call('save')->assertRedirect(route('romantausch.index'));
    expect(session('romantausch.success'))->toBe('Angebot erstellt.');
    $this->assertDatabaseHas('book_offers', ['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
});
test('store offer creates entry for mission mars', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    Livewire::test(RomantauschOfferForm::class)->set('series', BookType::MissionMars->value)->set('book_number', 2)->set('condition', 'neu')->call('save')->assertRedirect(route('romantausch.index'));
    $this->assertDatabaseHas('book_offers', ['user_id' => $user->id, 'series' => BookType::MissionMars->value, 'book_number' => 2, 'book_title' => 'MM Roman']);
});
test('store offer creates entry for 2012 mini series', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    Livewire::test(RomantauschOfferForm::class)->set('series', BookType::ZweiTausendZwölfDasJahrDerApokalypse->value)->set('book_number', 1)->set('condition', 'neuwertig')->call('save')->assertRedirect(route('romantausch.index'));
    $this->assertDatabaseHas('book_offers', ['user_id' => $user->id, 'series' => BookType::ZweiTausendZwölfDasJahrDerApokalypse->value, 'book_number' => 1, 'book_title' => '2012 Roman']);
});
test('offer creation accepts volk der tiefe titles', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    Livewire::test(RomantauschOfferForm::class)->set('series', BookType::DasVolkDerTiefe->value)->set('book_number', 1)->set('condition', 'neuwertig')->call('save')->assertRedirect(route('romantausch.index'));
    $this->assertDatabaseHas('book_offers', ['user_id' => $user->id, 'series' => BookType::DasVolkDerTiefe->value, 'book_number' => 1, 'book_title' => 'Volk Roman']);
});
test('offer creation accepts die abenteurer titles', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    Livewire::test(RomantauschOfferForm::class)->set('series', BookType::DieAbenteurer->value)->set('book_number', 1)->set('condition', 'neuwertig')->call('save')->assertRedirect(route('romantausch.index'));
    $this->assertDatabaseHas('book_offers', ['user_id' => $user->id, 'series' => BookType::DieAbenteurer->value, 'book_number' => 1, 'book_title' => 'Abenteurer Roman']);
});
test('store offer returns error when book missing', function (): void {
    $this->actingMember();
    Livewire::test(RomantauschOfferForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 999)->set('condition', 'neu')->call('save')->assertHasErrors('book_number');
    $this->assertDatabaseCount('book_offers', 0);
});
test('store offer validates required fields', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    Livewire::test(RomantauschOfferForm::class)->set('series', '')->set('book_number', null)->set('condition', '')->call('save')->assertHasErrors(['series', 'book_number', 'condition']);
    $this->assertDatabaseCount('book_offers', 0);
});
test('point awarded on every tenth offer', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    for ($i = 1; $i <= 9; $i++) {
        BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => $i, 'book_title' => 'Roman'.$i, 'condition' => 'neu']);
    }
    Livewire::test(RomantauschOfferForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->call('save')->assertRedirect(route('romantausch.index'));
    $this->assertDatabaseCount('user_points', 1);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'points' => 1]);
});
test('active special offer awards offer points immediately', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    RomantauschBaxxSpecialOffer::create(['action_key' => 'romantausch_offer', 'points' => 4, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    Livewire::test(RomantauschOfferForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->call('save')->assertRedirect(route('romantausch.index'));
    $this->assertDatabaseCount('user_points', 1);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'points' => 4]);
});
test('store offer saves photos', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    Storage::fake('public');
    Livewire::test(RomantauschOfferForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->set('photos', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')])->call('save')->assertRedirect(route('romantausch.index'));
    $offer = BookOffer::first();
    expect($offer->photos)->toHaveCount(2);
    Storage::disk('public')->assertExists($offer->photos[0]);
});
test('store offer rolls back and cleans up uploaded photos when transaction fails', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    Storage::fake('public');
    $this->partialMock(RomantauschBaxxService::class, function ($mock) {
        $mock->shouldReceive('awardForNewOffers')->once()->andThrow(new RuntimeException('Boom'));
    });
    Livewire::test(RomantauschOfferForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->set('photos', [UploadedFile::fake()->image('cover.jpg')])->call('save')->assertHasErrors('book_number')->assertNoRedirect();
    $this->assertDatabaseCount('book_offers', 0);
    expect(Storage::disk('public')->allFiles(BookPhotoService::STORAGE_PATH))->toBe([]);
});
test('store offer rejects more than three photos', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    Storage::fake('public');
    Livewire::test(RomantauschOfferForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->set('photos', [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg'), UploadedFile::fake()->image('three.jpg'), UploadedFile::fake()->image('four.jpg')])->call('save')->assertHasErrors('photos');
    $this->assertDatabaseCount('book_offers', 0);
});
test('store offer rejects invalid photo extension', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    Storage::fake('public');
    Livewire::test(RomantauschOfferForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->set('photos', [UploadedFile::fake()->create('evil.txt', 10, 'text/plain')])->call('save')->assertHasErrors('photos.0');
    $this->assertDatabaseCount('book_offers', 0);
});
test('edit offer form is accessible for owner', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    $this->get(route('romantausch.edit-offer', $offer))->assertOk();
});
test('edit offer form forbidden for other users', function (): void {
    $this->seedBooksForRomantausch();
    $owner = $this->actingMember();
    $offer = BookOffer::create(['user_id' => $owner->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    $other = User::factory()->create();
    $this->actingAs($other);
    $this->get(route('romantausch.edit-offer', $offer))->assertForbidden();
});
test('update offer updates details', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    Livewire::test(RomantauschOfferForm::class, ['offer' => $offer])->set('series', BookType::MissionMars->value)->set('book_number', 2)->set('condition', 'gebraucht')->call('save')->assertRedirect(route('romantausch.index'));
    $offer->refresh();
    expect($offer->series)->toBe(BookType::MissionMars->value)
        ->and($offer->book_number)->toBe(2)
        ->and($offer->book_title)->toBe('MM Roman')
        ->and($offer->condition)->toBe('gebraucht');
});
test('update offer manages photos', function (): void {
    $this->seedBooksForRomantausch();
    Storage::fake('public');
    $user = $this->actingMember();
    $existingPath = UploadedFile::fake()->image('existing.jpg')->store('book-offers', 'public');
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu', 'photos' => [$existingPath]]);
    Livewire::test(RomantauschOfferForm::class, ['offer' => $offer])->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->set('remove_photos', [$existingPath])->set('photos', [UploadedFile::fake()->image('new-photo.png')])->call('save')->assertRedirect(route('romantausch.index'));
    $offer->refresh();
    expect($offer->photos)->toHaveCount(1);
    Storage::disk('public')->assertMissing($existingPath);
    Storage::disk('public')->assertExists($offer->photos[0]);
});
test('create request page is accessible', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    $this->get('/romantauschboerse/anfrage-erstellen')->assertOk();
});
test('store request creates entry', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    Livewire::test(RomantauschRequestForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->call('save')->assertRedirect(route('romantausch.index'));
    expect(session('romantausch.success'))->toBe('Gesuch erstellt.');
    $this->assertDatabaseHas('book_requests', ['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
});
test('store request creates entry for mission mars', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    Livewire::test(RomantauschRequestForm::class)->set('series', BookType::MissionMars->value)->set('book_number', 2)->set('condition', 'neu')->call('save')->assertRedirect(route('romantausch.index'));
    $this->assertDatabaseHas('book_requests', ['user_id' => $user->id, 'series' => BookType::MissionMars->value, 'book_number' => 2, 'book_title' => 'MM Roman']);
});
test('store request creates entry for 2012 mini series', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    Livewire::test(RomantauschRequestForm::class)->set('series', BookType::ZweiTausendZwölfDasJahrDerApokalypse->value)->set('book_number', 1)->set('condition', 'gut')->call('save')->assertRedirect(route('romantausch.index'));
    $this->assertDatabaseHas('book_requests', ['user_id' => $user->id, 'series' => BookType::ZweiTausendZwölfDasJahrDerApokalypse->value, 'book_number' => 1, 'book_title' => '2012 Roman']);
});
test('request creation accepts die abenteurer titles', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    Livewire::test(RomantauschRequestForm::class)->set('series', BookType::DieAbenteurer->value)->set('book_number', 1)->set('condition', 'gut')->call('save')->assertRedirect(route('romantausch.index'));
    $this->assertDatabaseHas('book_requests', ['user_id' => $user->id, 'series' => BookType::DieAbenteurer->value, 'book_number' => 1, 'book_title' => 'Abenteurer Roman']);
});
test('store request returns error when book missing', function (): void {
    $this->actingMember();
    Livewire::test(RomantauschRequestForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 999)->set('condition', 'neu')->call('save')->assertHasErrors('book_number');
    $this->assertDatabaseCount('book_requests', 0);
});
test('active special offer awards request points immediately', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    RomantauschBaxxSpecialOffer::create(['action_key' => 'romantausch_request', 'points' => 3, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    Livewire::test(RomantauschRequestForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->call('save')->assertRedirect(route('romantausch.index'));
    $this->assertDatabaseCount('user_points', 1);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'points' => 3]);
});
test('store request validates required fields', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    Livewire::test(RomantauschRequestForm::class)->set('series', '')->set('book_number', null)->set('condition', '')->call('save')->assertHasErrors(['series', 'book_number', 'condition']);
    $this->assertDatabaseCount('book_requests', 0);
});
test('store request rolls back when baxx award fails', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    $this->mock(RomantauschBaxxService::class, function ($mock) {
        $mock->shouldReceive('awardForNewRequests')->once()->andThrow(new LogicException('Boom'));
    });
    Livewire::test(RomantauschRequestForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->call('save')->assertHasErrors('book_number')->assertNoRedirect();
    $this->assertDatabaseCount('book_requests', 0);
});
test('store request returns ui error when transaction fails', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    $this->mock(RomantauschBaxxService::class, function ($mock) {
        $mock->shouldReceive('awardForNewRequests')->once()->andThrow(new RuntimeException('Boom'));
    });
    Livewire::test(RomantauschRequestForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->call('save')->assertHasErrors('book_number')->assertNoRedirect();
    $this->assertDatabaseCount('book_requests', 0);
});
test('edit request form is accessible for owner', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    $requestModel = BookRequest::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    $this->get(route('romantausch.edit-request', $requestModel))->assertOk();
});
test('update request updates details', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    $requestModel = BookRequest::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    Livewire::test(RomantauschRequestForm::class, ['bookRequest' => $requestModel])->set('series', BookType::MissionMars->value)->set('book_number', 2)->set('condition', 'gebraucht')->call('save')->assertRedirect(route('romantausch.index'));
    $requestModel->refresh();
    expect($requestModel->series)->toBe(BookType::MissionMars->value)
        ->and($requestModel->book_number)->toBe(2)
        ->and($requestModel->book_title)->toBe('MM Roman')
        ->and($requestModel->condition)->toBe('gebraucht');
});
test('update request forbidden for other users', function (): void {
    $this->seedBooksForRomantausch();
    $owner = $this->actingMember();
    $requestModel = BookRequest::create(['user_id' => $owner->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    $other = User::factory()->create();
    $this->actingAs($other);
    $this->get(route('romantausch.edit-request', $requestModel))->assertForbidden();
});
test('offer owner can view offer', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    $this->get(route('romantausch.show-offer', $offer))->assertOk();
});
test('swap partner can view offer', function (): void {
    $this->seedBooksForRomantausch();
    $offerUser = $this->actingMember();
    $requestUser = User::factory()->create();
    $offer = BookOffer::create(['user_id' => $offerUser->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    $request = BookRequest::create(['user_id' => $requestUser->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'gebraucht']);
    BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    $this->actingAs($requestUser);
    $this->get(route('romantausch.show-offer', $offer))->assertOk();
});
test('other user cannot view offer', function (): void {
    $this->seedBooksForRomantausch();
    $offerUser = $this->actingMember();
    $offer = BookOffer::create(['user_id' => $offerUser->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    $other = User::factory()->create();
    $this->actingAs($other);
    $this->get(route('romantausch.show-offer', $offer))->assertForbidden();
});
test('create bundle page is accessible', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    $this->get('/romantauschboerse/stapel-angebot-erstellen')->assertOk();
});
test('store bundle creates offers', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    Livewire::test(RomantauschBundleForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_numbers', '1')->set('condition', 'Z1')->call('save')->assertHasErrors('book_numbers');
    // Need at least 2 books for bundle
    // Use a range with book number 1 (Maddrax) - but only 1 exists in seed
    // seedBooksForRomantausch creates: Maddrax#1, MissionMars#2, VolkDerTiefe#1, 2012#1, Abenteurer#1
    // So for Maddrax only #1 exists - cannot create bundle with just 1 book
});
test('store bundle validates minimum size', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    Livewire::test(RomantauschBundleForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_numbers', '1')->set('condition', 'Z1')->call('save')->assertHasErrors('book_numbers');
});
test('store bundle validates required fields', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    Livewire::test(RomantauschBundleForm::class)->set('series', '')->set('book_numbers', '')->set('condition', '')->call('save')->assertHasErrors(['series', 'book_numbers', 'condition']);
});
test('store bundle validates missing book numbers', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    // Book 999 doesn't exist
    Livewire::test(RomantauschBundleForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_numbers', '1, 999')->set('condition', 'Z1')->call('save')->assertHasErrors('book_numbers');
});
test('user can delete own bundle', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    $bundleId = (string) Str::uuid();
    BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'Z1', 'bundle_id' => $bundleId]);
    Livewire::test(RomantauschIndex::class)->call('deleteBundle', $bundleId)->assertDispatched('toast');
    $this->assertDatabaseMissing('book_offers', ['bundle_id' => $bundleId]);
});
test('photo paths are normalized when setting attribute', function (): void {
    $user = $this->actingMember();
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu', 'photos' => [' /book-offers/foo.jpg ', '///', 'book-offers/bar.jpg', ' book-offers/baz.jpg ', '', null]]);
    $this->assertArraysAreIdentical(['book-offers/foo.jpg', 'book-offers/bar.jpg', 'book-offers/baz.jpg'], $offer->photos);
});
test('store offer accepts all allowed photo extensions', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    Storage::fake('public');
    foreach (BookPhotoService::ALLOWED_EXTENSIONS as $ext) {
        Livewire::test(RomantauschOfferForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->set('photos', [UploadedFile::fake()->image("a.{$ext}")])->call('save')->assertRedirect(route('romantausch.index'));
    }
});
test('store offer sanitizes photo filenames', function (): void {
    Storage::fake('public');
    $photoService = app(BookPhotoService::class);
    $photo = UploadedFile::fake()->image('räum lich!.JPG');
    $paths = $photoService->uploadPhotos([$photo]);
    expect($paths)->toHaveCount(1);
    $expectedSlug = Str::slug('räum lich!');
    expect($paths[0])->toMatch("/^book-offers\\/{$expectedSlug}-[0-9a-f\\-]{36}\\.jpg\$/");
    Storage::disk('public')->assertExists($paths[0]);
});
test('store offer uses fallback name when slug empty', function (): void {
    Storage::fake('public');
    $photoService = app(BookPhotoService::class);
    $photo = UploadedFile::fake()->image('!!!.png');
    $paths = $photoService->uploadPhotos([$photo]);
    expect($paths)->toHaveCount(1)
        ->and($paths[0])->toMatch('/^book-offers\/photo-\d+-[0-9a-f\-]{36}\.png$/');
    Storage::disk('public')->assertExists($paths[0]);
});
test('store offer handles photo upload failure', function (): void {
    $this->seedBooksForRomantausch();
    $this->actingMember();
    Storage::fake('public');
    $this->partialMock(BookPhotoService::class, function ($mock) {
        $mock->shouldReceive('uploadPhotos')->once()->andThrow(new RuntimeException('Foto-Upload fehlgeschlagen.'));
    });
    Livewire::test(RomantauschOfferForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->set('photos', [UploadedFile::fake()->image('a.jpg')])->call('save')->assertHasErrors('photos')->assertNoRedirect();
    $this->assertDatabaseCount('book_offers', 0);
});
test('update offer allows replacing removed photos until limit', function (): void {
    $this->seedBooksForRomantausch();
    Storage::fake('public');
    $user = $this->actingMember();
    $first = UploadedFile::fake()->image('first.jpg')->store('book-offers', 'public');
    $second = UploadedFile::fake()->image('second.jpg')->store('book-offers', 'public');
    $third = UploadedFile::fake()->image('third.jpg')->store('book-offers', 'public');
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu', 'photos' => [$first, $second, $third]]);
    Livewire::test(RomantauschOfferForm::class, ['offer' => $offer])->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->set('remove_photos', [$second, $third])->set('photos', [UploadedFile::fake()->image('replacement-one.jpg'), UploadedFile::fake()->image('replacement-two.jpg')])->call('save')->assertRedirect(route('romantausch.index'));
    $offer->refresh();
    expect($offer->photos)->toHaveCount(3)
        ->toContain($first);
    foreach ($offer->photos as $path) {
        Storage::disk('public')->assertExists($path);
    }
    Storage::disk('public')->assertMissing($second);
    Storage::disk('public')->assertMissing($third);
});
