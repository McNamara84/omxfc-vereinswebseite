<?php

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

class RomantauschControllerTest extends TestCase
{
    use CreatesTestData;
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_store_offer_cleans_up_uploaded_photos_when_transaction_fails(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();

        Storage::fake('public');

        $this->mock(RomantauschBaxxService::class, function ($mock) {
            $mock->shouldReceive('awardForNewOffers')
                ->once()
                ->andThrow(new RuntimeException('Boom'));
        });

        $response = $this->actingAs($user)
            ->from('/romantauschboerse/angebot-erstellen')
            ->post(route('romantausch.store-offer'), [
                'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
                'book_number' => 1,
                'condition' => 'neu',
                'photos' => [UploadedFile::fake()->image('cover.jpg')],
            ]);

        $response->assertRedirect('/romantauschboerse/angebot-erstellen');
        $response->assertSessionHas('error', 'Angebot konnte aktuell nicht erstellt werden. Bitte versuche es später erneut.');
        $this->assertDatabaseCount('book_offers', 0);
        $this->assertSame([], Storage::disk('public')->allFiles(BookPhotoService::STORAGE_PATH));
    }

    public function test_store_request_returns_friendly_error_when_transaction_fails(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();

        $this->mock(RomantauschBaxxService::class, function ($mock) {
            $mock->shouldReceive('awardForNewRequests')
                ->once()
                ->andThrow(new RuntimeException('Boom'));
        });

        $response = $this->actingAs($user)
            ->from('/romantauschboerse/anfrage-erstellen')
            ->post(route('romantausch.store-request'), [
                'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
                'book_number' => 1,
                'condition' => 'neu',
            ]);

        $response->assertRedirect('/romantauschboerse/anfrage-erstellen');
        $response->assertSessionHas('error', 'Gesuch konnte aktuell nicht erstellt werden. Bitte versuche es später erneut.');
        $this->assertDatabaseCount('book_requests', 0);
    }
}