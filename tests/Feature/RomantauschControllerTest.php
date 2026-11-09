<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Services\Romantausch\BookPhotoService;
use App\Services\Romantausch\RomantauschBaxxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CreatesTestData;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesTestData::class, CreatesUserWithRole::class, RefreshDatabase::class);

test('store offer cleans up uploaded photos when transaction fails', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    Storage::fake('public');
    $this->mock(RomantauschBaxxService::class, function ($mock) {
        $mock->shouldReceive('awardForNewOffers')->once()->andThrow(new RuntimeException('Boom'));
    });
    $response = $this->actingAs($user)->from('/romantauschboerse/angebot-erstellen')->post(route('romantausch.store-offer'), ['series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'condition' => 'neu', 'photos' => [UploadedFile::fake()->image('cover.jpg')]]);
    $response->assertRedirect('/romantauschboerse/angebot-erstellen');
    $response->assertSessionHas('error', 'Angebot konnte aktuell nicht erstellt werden. Bitte versuche es später erneut.');
    $this->assertDatabaseCount('book_offers', 0);
    expect(Storage::disk('public')->allFiles(BookPhotoService::STORAGE_PATH))->toBe([]);
});

test('store request returns friendly error when transaction fails', function (): void {
    $this->seedBooksForRomantausch();
    $user = $this->actingMember();
    $this->mock(RomantauschBaxxService::class, function ($mock) {
        $mock->shouldReceive('awardForNewRequests')->once()->andThrow(new RuntimeException('Boom'));
    });
    $response = $this->actingAs($user)->from('/romantauschboerse/anfrage-erstellen')->post(route('romantausch.store-request'), ['series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'condition' => 'neu']);
    $response->assertRedirect('/romantauschboerse/anfrage-erstellen');
    $response->assertSessionHas('error', 'Gesuch konnte aktuell nicht erstellt werden. Bitte versuche es später erneut.');
    $this->assertDatabaseCount('book_requests', 0);
});
