<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

class RpgCharEditorPdfTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Admin']);
        return $user;
    }

    public function test_pdf_downloads_with_sanitized_filename(): void
    {
        $admin = $this->adminUser();
        Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::type('array'))
            ->andReturn(new class extends \Spatie\LaravelPdf\PdfBuilder {
                public function toResponse($request): \Illuminate\Http\Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->actingAs($admin)->post('/rpg/char-editor/pdf', [
            'character_name' => 'Foo/Bar',
            'portrait' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $this->assertStringContainsString('foobar.pdf', $response->headers->get('content-disposition'));
    }

    public function test_rejects_non_image_portrait(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post('/rpg/char-editor/pdf', [
            'portrait' => UploadedFile::fake()->create('bad.exe', 10, 'application/octet-stream'),
        ]);

        $response->assertSessionHasErrors('portrait');
    }

    public function test_pdf_generates_without_portrait(): void
    {
        $admin = $this->adminUser();

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => array_key_exists('portrait', $data) && is_null($data['portrait'])))
            ->andReturn(new class extends \Spatie\LaravelPdf\PdfBuilder {
                public function toResponse($request): \Illuminate\Http\Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->actingAs($admin)->post('/rpg/char-editor/pdf', [
            'character_name' => 'Foo',
        ]);

        $response->assertOk();
    }
}
