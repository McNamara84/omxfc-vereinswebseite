<?php

namespace Tests\Feature;

use App\Mail\MitgliedAntragEingereicht;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use App\Enums\Role;

class MitgliedschaftControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_form_displays_accessible_fields(): void
    {
        $response = $this->get('/mitglied-werden');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('<x-input', $html, 'Compiled view should not contain unresolved Blade components.');
        $this->assertStringContainsString('action="' . route('mitglied.store') . '"', $html);
        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="satzung_check"[^>]*required/mi', $html);
        $this->assertStringContainsString('data-success-url="' . route('mitglied.werden.erfolgreich') . '"', $html);

        $fields = [
            'vorname' => ['aria' => 'aria-describedby="error-vorname"', 'ids' => ['error-vorname']],
            'nachname' => ['aria' => 'aria-describedby="error-nachname"', 'ids' => ['error-nachname']],
            'strasse' => ['aria' => 'aria-describedby="error-strasse"', 'ids' => ['error-strasse']],
            'hausnummer' => ['aria' => 'aria-describedby="error-hausnummer"', 'ids' => ['error-hausnummer']],
            'plz' => ['aria' => 'aria-describedby="error-plz"', 'ids' => ['error-plz']],
            'stadt' => ['aria' => 'aria-describedby="error-stadt"', 'ids' => ['error-stadt']],
            'land' => ['aria' => 'aria-describedby="error-land"', 'ids' => ['error-land']],
            'mail' => ['aria' => 'aria-describedby="error-mail"', 'ids' => ['error-mail']],
            'passwort' => ['aria' => 'aria-describedby="passwort-hint error-passwort"', 'ids' => ['passwort-hint', 'error-passwort']],
            'passwort_confirmation' => ['aria' => 'aria-describedby="passwort_confirmation-hint error-passwort_confirmation"', 'ids' => ['passwort_confirmation-hint', 'error-passwort_confirmation']],
            'mitgliedsbeitrag' => ['aria' => 'aria-describedby="mitgliedsbeitrag-hint beitrag-output error-mitgliedsbeitrag"', 'ids' => ['mitgliedsbeitrag-hint', 'beitrag-output', 'error-mitgliedsbeitrag']],
            'telefon' => ['aria' => 'aria-describedby="telefon-hint error-telefon"', 'ids' => ['telefon-hint', 'error-telefon']],
            'verein_gefunden' => ['aria' => 'aria-describedby="error-verein_gefunden"', 'ids' => ['error-verein_gefunden']],
        ];

        foreach ($fields as $field => $expectation) {
            $this->assertStringContainsString('id="' . $field . '"', $html);
            $this->assertStringContainsString($expectation['aria'], $html);

            foreach ($expectation['ids'] as $id) {
                $this->assertStringContainsString('id="' . $id . '"', $html);
            }

            $this->assertStringContainsString('data-error-for="' . $field . '"', $html);
        }

        $this->assertStringContainsString('data-output-target="beitrag-output"', $html);
        $this->assertStringContainsString('data-output-suffix="€"', $html);
    }

    public function test_membership_application_creates_user_and_assigns_anwaerter_role(): void
    {
        Mail::fake();

        $data = [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'strasse' => 'Musterstraße',
            'hausnummer' => '1',
            'plz' => '12345',
            'stadt' => 'Musterstadt',
            'land' => 'Deutschland',
            'mail' => 'max@example.com',
            'passwort' => 'secret123',
            'passwort_confirmation' => 'secret123',
            'mitgliedsbeitrag' => 12,
            'telefon' => '0123456789',
            'verein_gefunden' => 'Internet',
            'satzung_check' => 'on',
        ];

        $response = $this->postJson(route('mitglied.store'), $data);

        $response->assertOk()->assertJson(['success' => true]);

        $user = User::where('email', 'max@example.com')->first();
        $this->assertNotNull($user);

        $team = Team::membersTeam();
        $this->assertTrue($team->users()->where('user_id', $user->id)->wherePivot('role', Role::Anwaerter->value)->exists());

        Mail::assertQueued(MitgliedAntragEingereicht::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_membership_application_requires_first_name(): void
    {
        Mail::fake();

        $data = [
            'nachname' => 'Mustermann',
            'strasse' => 'Musterstraße',
            'hausnummer' => '1',
            'plz' => '12345',
            'stadt' => 'Musterstadt',
            'land' => 'Deutschland',
            'mail' => 'max@example.com',
            'passwort' => 'secret123',
            'passwort_confirmation' => 'secret123',
            'mitgliedsbeitrag' => 12,
            'satzung_check' => 'on',
        ];

        $response = $this->postJson(route('mitglied.store'), $data);

        $response->assertStatus(422)->assertJsonValidationErrors(['vorname']);
    }

    public function test_membership_application_requires_accepting_satzung(): void
    {
        Mail::fake();

        $data = [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'strasse' => 'Musterstraße',
            'hausnummer' => '1',
            'plz' => '12345',
            'stadt' => 'Musterstadt',
            'land' => 'Deutschland',
            'mail' => 'max@example.com',
            'passwort' => 'secret123',
            'passwort_confirmation' => 'secret123',
            'mitgliedsbeitrag' => 12,
        ];

        $response = $this->postJson(route('mitglied.store'), $data);

        $response->assertStatus(422)->assertJsonValidationErrors(['satzung_check']);
    }

    public function test_membership_form_fields_share_brand_focus_styles(): void
    {
        $response = $this->get('/mitglied-werden');

        $response->assertOk();
        $html = $response->getContent();

        $fieldsToInspect = [
            ['input', 'vorname'],
            ['select', 'land'],
            ['select', 'verein_gefunden'],
            ['input', 'mitgliedsbeitrag'],
        ];

        foreach ($fieldsToInspect as [$tag, $id]) {
            $classes = $this->extractClassAttribute($html, $tag, $id);

            $this->assertNotNull($classes, sprintf('Erwartete %s#%s mit Klassenattribut.', $tag, $id));
            $this->assertStringContainsString('focus:border-[#8B0116]', $classes);
            $this->assertStringContainsString('focus:ring-[#8B0116]', $classes);
            $this->assertStringContainsString('dark:focus:border-[#ff4b63]', $classes);
            $this->assertStringContainsString('dark:focus:ring-[#ff4b63]', $classes);
        }
    }

    public function test_membership_form_relies_on_app_bundle_for_initialization(): void
    {
        $response = $this->get('/mitglied-werden');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('id="mitgliedschaft-form"', $html);
        $this->assertMatchesRegularExpression('/build\/assets\/mitgliedschaft-page-[^\"\']+\.js/', $html);
    }

    private function extractClassAttribute(string $html, string $tag, string $id): ?string
    {
        $pattern = sprintf('/<%s[^>]*\bid="%s"[^>]*\bclass="([^"]*)"/m', $tag, $id);

        if (preg_match($pattern, $html, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
