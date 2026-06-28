<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Controllers\RpgCharEditorController;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\TestCase;

class RpgCharEditorPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::store('rpg_pdf_exports')->flush();
    }

    protected function tearDown(): void
    {
        Cache::store('rpg_pdf_exports')->flush();

        parent::tearDown();
    }

    private function validPdfPayload(array $overrides = []): array
    {
        $payload = array_replace_recursive([
            'player_name' => 'Spieler Eins',
            'character_name' => 'Foo Bar',
            'gender' => 'maennlich',
            'race' => 'Barbar',
            'culture' => 'Landbewohner',
            'description' => 'Ein erfahrener Charakter aus Wudan.',
            'attributes' => [
                'st' => 2,
                'ge' => 1,
            ],
            'barbar_attribute_bonus' => 'st',
            'skills' => [
                ['name' => 'Nahkampf', 'value' => 1],
                ['name' => 'Überleben', 'value' => 1],
                ['name' => 'Athletik', 'value' => 1],
                ['name' => 'Intuition', 'value' => 1],
                ['name' => 'Beruf: Viehzüchter', 'value' => 2],
                ['name' => 'Kunde: Wetter', 'value' => 1],
            ],
            'advantages' => ['Zäh'],
            'disadvantages' => ['Auffällig'],
            'clothing' => 'kleidung-einfach',
            'equipment_items' => [
                ['id' => 'messer-dolch', 'quantity' => 1],
                ['id' => 'seil', 'quantity' => 1],
                ['id' => 'rucksack', 'quantity' => 1],
                ['id' => 'wasserschlauch', 'quantity' => 1],
                ['id' => 'wochenration', 'quantity' => 1],
                ['id' => 'bogen', 'quantity' => 1],
            ],
            'equipment' => 'Messer, Seil, Feldflasche',
        ], $overrides);

        if (! array_key_exists('attributes', $overrides)) {
            $payload['attributes'] = $this->validAttributesForRace((string) ($payload['race'] ?? ''));
        }

        foreach (['attributes', 'skills', 'advantages', 'disadvantages', 'equipment_items'] as $listKey) {
            if (array_key_exists($listKey, $overrides)) {
                $payload[$listKey] = $overrides[$listKey];
            }
        }

        return $payload;
    }

    private function validAttributesForRace(string $race): array
    {
        return match (trim($race)) {
            'Barbar' => ['st' => 2, 'ge' => 1],
            'Guul' => ['au' => -1],
            'Nosfera' => ['ge' => 1, 'au' => -1],
            'Taratze' => ['st' => 1, 'wa' => 1, 'in' => -1, 'au' => -1],
            'Wulfane' => ['ro' => 1, 'au' => -1],
            'Techno' => ['st' => -1, 'ro' => -1, 'in' => 1],
            default => [],
        };
    }

    private function createMember(Role|string $role = Role::Mitglied): User
    {
        $team = Team::membersTeam();

        if (! $team) {
            $team = Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
        }

        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role instanceof Role ? $role->value : $role]);

        return $user->refresh();
    }

    private function addAgRollenspielMembership(User $user): User
    {
        $owner = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'name' => 'AG Rollenspiel',
            'personal_team' => false,
        ]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user->refresh();
    }

    private function createManagementUserWithDifferentCurrentTeam(Role $role): User
    {
        $managementTeam = Team::membersTeam() ?? Team::factory()->create([
            'name' => 'Mitglieder',
            'personal_team' => false,
        ]);

        $user = User::factory()->create(['current_team_id' => $managementTeam->id]);
        $managementTeam->users()->attach($user, ['role' => $role->value]);

        $otherTeam = Team::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nebenverein',
            'personal_team' => false,
        ]);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);

        $user->forceFill(['current_team_id' => $otherTeam->id])->save();

        return $user->refresh();
    }

    public function test_char_sheet_formats_gender_values_for_pdf_view(): void
    {
        $html = view('rpg.char-sheet', [
            'character' => [
                'player_name' => 'Spieler Eins',
                'character_name' => 'Foo Bar',
                'gender' => 'maennlich',
                'race' => 'Barbar',
                'culture' => 'Landbewohner',
                'description' => '',
                'equipment' => '',
            ],
            'attributes' => [],
            'skills' => [],
            'advantages' => [],
            'disadvantages' => [],
            'portrait' => null,
        ])->render();

        $this->assertStringContainsString('Geschlecht:</strong> Männlich', $html);
        $this->assertStringNotContainsString('maennlich', $html);
    }

    public function test_char_sheet_does_not_render_trailing_spaces_for_plain_advantages(): void
    {
        $html = view('rpg.char-sheet', [
            'character' => [
                'player_name' => '',
                'character_name' => '',
                'gender' => 'maennlich',
                'race' => 'Barbar',
                'culture' => 'Landbewohner',
                'description' => '',
                'equipment' => '',
            ],
            'attributes' => [],
            'skills' => [],
            'advantages' => ['Zäh'],
            'disadvantages' => [],
            'advantage_details' => [],
            'disadvantage_details' => [],
            'advantage_counts' => [],
            'portrait' => null,
        ])->render();

        $this->assertStringContainsString('<li>Zäh</li>', $html);
        $this->assertStringNotContainsString('<li>Zäh </li>', $html);
    }

    public function test_pdf_export_post_redirects_to_get_viewer_url(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload());

        $response
            ->assertStatus(Response::HTTP_SEE_OTHER)
            ->assertRedirect();

        $path = parse_url($response->headers->get('Location'), PHP_URL_PATH);

        $this->assertMatchesRegularExpression('#^/rpg/char-editor/pdf/[0-9a-f-]{36}$#', $path);
    }


    public function test_pdf_export_includes_structured_equipment_and_server_ammunition(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => ($data['equipment']['clothing']['id'] ?? null) === 'kleidung-wanderer'
                && collect($data['equipment']['items'])->contains(fn ($item) => $item['id'] === 'bogen' && $item['quantity'] === 2)
                && collect($data['equipment']['ammunition'])->contains(fn ($ammo) => $ammo['source'] === 'Bogen' && $ammo['quantity'] === 60 && $ammo['unit'] === 'Pfeile')
                && ($data['equipment']['notes'] ?? null) === 'Bogen gewachst'))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'clothing' => 'kleidung-wanderer',
            'equipment_items' => [
                ['id' => 'bogen', 'quantity' => 2],
                ['id' => 'seil', 'quantity' => 1],
                ['id' => 'rucksack', 'quantity' => 1],
                ['id' => 'wasserschlauch', 'quantity' => 1],
                ['id' => 'wochenration', 'quantity' => 1],
            ],
            'equipment' => 'Bogen gewachst',
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_requires_valid_clothing_from_equipment_rules(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'clothing' => 'smoking',
        ]));

        $response->assertSessionHasErrors('clothing');
    }

    public function test_pdf_export_requires_exactly_six_counted_equipment_items(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'equipment_items' => [
                ['id' => 'messer-dolch', 'quantity' => 1],
                ['id' => 'seil', 'quantity' => 1],
                ['id' => 'rucksack', 'quantity' => 1],
                ['id' => 'wasserschlauch', 'quantity' => 1],
                ['id' => 'wochenration', 'quantity' => 1],
            ],
        ]));

        $response->assertSessionHasErrors('equipment_items');
    }

    public function test_pdf_export_rejects_unknown_equipment_ids(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'equipment_items' => [
                ['id' => 'messer-dolch', 'quantity' => 1],
                ['id' => 'seil', 'quantity' => 1],
                ['id' => 'rucksack', 'quantity' => 1],
                ['id' => 'wasserschlauch', 'quantity' => 1],
                ['id' => 'wochenration', 'quantity' => 1],
                ['id' => 'laserdrache', 'quantity' => 1],
            ],
        ]));

        $response->assertSessionHasErrors('equipment_items');
    }

    public function test_pdf_export_rejects_high_tech_equipment_without_advantage(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'equipment_items' => [
                ['id' => 'funkgeraet', 'quantity' => 1],
                ['id' => 'messer-dolch', 'quantity' => 1],
                ['id' => 'seil', 'quantity' => 1],
                ['id' => 'rucksack', 'quantity' => 1],
                ['id' => 'wasserschlauch', 'quantity' => 1],
                ['id' => 'wochenration', 'quantity' => 1],
            ],
        ]));

        $response->assertSessionHasErrors('equipment_items');
    }

    public function test_pdf_export_limits_high_tech_equipment_to_four_with_advantage(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'advantages' => ['Zäh', 'High-Tech-Ausrüstung'],
            'disadvantages' => ['Auffällig'],
            'equipment_items' => [
                ['id' => 'funkgeraet', 'quantity' => 5],
                ['id' => 'seil', 'quantity' => 1],
            ],
        ]));

        $response->assertSessionHasErrors('equipment_items');
    }
    public function test_pdf_export_get_route_can_be_opened_repeatedly_by_pdf_viewers(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload());
        $path = parse_url($response->headers->get('Location'), PHP_URL_PATH);

        Pdf::shouldReceive('view')
            ->twice()
            ->with('rpg.char-sheet', \Mockery::type('array'))
            ->andReturnUsing(fn () => new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $this->actingAs($member)->get($path)->assertOk();
        $this->actingAs($member)->get($path)->assertOk();
    }

    public function test_pdf_export_replaces_previous_cached_payload_when_new_export_is_created(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        $firstResponse = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'character_name' => 'Erster Charakter',
        ]));
        $firstToken = basename(parse_url($firstResponse->headers->get('Location'), PHP_URL_PATH));
        $firstCacheKey = 'rpg-char-editor-pdf:'.$firstToken;
        $firstSessionPayloadKey = 'rpg-char-editor-pdf.'.$firstToken;

        $firstResponse
            ->assertRedirect()
            ->assertSessionMissing($firstSessionPayloadKey)
            ->assertSessionHas('rpg-char-editor-pdf.active-token', $firstToken);

        $this->assertTrue(Cache::store('rpg_pdf_exports')->has($firstCacheKey));

        $secondResponse = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'character_name' => 'Zweiter Charakter',
        ]));
        $secondToken = basename(parse_url($secondResponse->headers->get('Location'), PHP_URL_PATH));
        $secondCacheKey = 'rpg-char-editor-pdf:'.$secondToken;
        $secondSessionPayloadKey = 'rpg-char-editor-pdf.'.$secondToken;

        $secondResponse
            ->assertRedirect()
            ->assertSessionMissing($firstSessionPayloadKey)
            ->assertSessionMissing($secondSessionPayloadKey)
            ->assertSessionHas('rpg-char-editor-pdf.active-token', $secondToken);

        $this->assertFalse(Cache::store('rpg_pdf_exports')->has($firstCacheKey));
        $this->assertTrue(Cache::store('rpg_pdf_exports')->has($secondCacheKey));

        Pdf::shouldReceive('view')->never();

        $this->actingAs($member)->get('/rpg/char-editor/pdf/'.$firstToken)->assertNotFound();
    }

    public function test_pdf_export_get_route_is_scoped_to_exporting_user(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());
        $otherMember = $this->addAgRollenspielMembership($this->createMember());

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload());
        $path = parse_url($response->headers->get('Location'), PHP_URL_PATH);

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::type('array'))
            ->andReturnUsing(fn () => new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $this->actingAs($otherMember)->get($path)->assertNotFound();
        $this->actingAs($member)->get($path)->assertOk();
    }

    public function test_pdf_export_get_route_rejects_unknown_tokens(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $this->actingAs($member)
            ->get('/rpg/char-editor/pdf/00000000-0000-4000-8000-000000000000')
            ->assertNotFound();
    }

    public function test_pdf_export_get_route_rejects_expired_tokens(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());
        $token = '00000000-0000-4000-8000-000000000001';

        Pdf::shouldReceive('view')->never();

        $cacheKey = 'rpg-char-editor-pdf:'.$token;

        Cache::store('rpg_pdf_exports')->put($cacheKey, [
            'user_id' => (string) $member->getAuthIdentifier(),
            'expires_at' => now()->subMinute()->getTimestamp(),
            'data' => $this->validPdfPayload(),
        ], now()->addMinute());

        $this->withSession([
            'rpg-char-editor-pdf.active-token' => $token,
        ])
            ->actingAs($member)
            ->get('/rpg/char-editor/pdf/'.$token)
            ->assertNotFound()
            ->assertSessionMissing('rpg-char-editor-pdf.active-token');

        $this->assertFalse(Cache::store('rpg_pdf_exports')->has($cacheKey));
    }

    public function test_pdf_view_receives_normalized_browser_payload_for_disabled_editor_fields(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(function ($data) {
                return $data['character'] === [
                    'player_name' => 'Holger',
                    'character_name' => 'Holli',
                    'gender' => 'weiblich',
                    'race' => 'Barbar',
                    'culture' => 'Landbewohner',
                    'description' => 'Beschreibung aus dem Editor',
                    'equipment' => 'Seil und Messer',
                ]
                    && $data['attributes'] === ['st' => '2', 'ge' => '1']
                    && $data['skills'] === [
                        ['name' => 'Nahkampf', 'value' => '4'],
                        ['name' => 'Überleben', 'value' => '1'],
                        ['name' => 'Intuition', 'value' => '1'],
                        ['name' => 'Beruf: Landwirt', 'value' => '2'],
                        ['name' => 'Kunde: Wetter', 'value' => '1'],
                    ]
                    && $data['advantages'] === ['Zäh', 'Anführer']
                    && $data['disadvantages'] === ['Abergläubisch']
                    && $data['portrait'] === null;
            }))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', [
            '_token' => 'ignored by payload whitelist',
            'player_name' => 'Holger',
            'character_name' => 'Holli',
            'gender' => 'weiblich',
            'race' => 'Barbar',
            'culture' => 'Landbewohner',
            'description' => 'Beschreibung aus dem Editor',
            'attributes' => [
                'st' => '2',
                'ge' => '1',
                'unknown' => '99',
            ],
            'skills' => [
                ['value' => '4'],
                ['name' => 'Nahkampf', 'value' => '4'],
                ['name' => 'Überleben', 'value' => '1'],
                ['name' => 'Intuition', 'value' => '1'],
                ['name' => 'Beruf: Landwirt', 'value' => '2'],
                ['name' => 'Kunde: Wetter', 'value' => '1'],
                ['name' => '', 'value' => '4'],
            ],
            'advantages' => ['Zaeh', 'Zaeh', 'Anfuehrer', ''],
            'disadvantages' => ['Aberglaeubisch', ''],
            'disadvantage_details' => ['Aberglaeubisch' => 'Salz, Omen, dreimal klopfen'],
            'clothing' => 'kleidung-einfach',
            'equipment_items' => [
                ['id' => 'messer-dolch', 'quantity' => 1],
                ['id' => 'seil', 'quantity' => 1],
                ['id' => 'rucksack', 'quantity' => 1],
                ['id' => 'wasserschlauch', 'quantity' => 1],
                ['id' => 'wochenration', 'quantity' => 1],
                ['id' => 'bogen', 'quantity' => 1],
            ],
            'equipment' => 'Seil und Messer',
            'unexpected' => 'wird nicht an die PDF-View gereicht',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertOk();
    }

    public function test_pdf_export_rejects_missing_or_invalid_gender_for_all_cultures(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $payloadWithoutGender = $this->validPdfPayload();
        unset($payloadWithoutGender['gender']);

        foreach ([$payloadWithoutGender, $this->validPdfPayload(['gender' => '']), $this->validPdfPayload(['gender' => 'unbekannt'])] as $payload) {
            $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);

            $response->assertSessionHasErrors('gender');
        }
    }

    public function test_pdf_export_rejects_missing_or_invalid_race_and_culture(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $payloadWithoutRace = $this->validPdfPayload();
        unset($payloadWithoutRace['race']);

        foreach ([$payloadWithoutRace, $this->validPdfPayload(['race' => '']), $this->validPdfPayload(['race' => 'Unbekannte Rasse'])] as $payload) {
            $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);

            $response->assertSessionHasErrors('race');
        }

        $payloadWithoutCulture = $this->validPdfPayload();
        unset($payloadWithoutCulture['culture']);

        foreach ([$payloadWithoutCulture, $this->validPdfPayload(['culture' => '']), $this->validPdfPayload(['culture' => 'Atlantisbewohner'])] as $payload) {
            $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);

            $response->assertSessionHasErrors('culture');
        }
    }

    public function test_pdf_export_accepts_negative_race_modifier_floor_from_rulebook(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => ($data['attributes']['au'] ?? null) === '-2'))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $payload = $this->validPdfPayload([
            'race' => 'Guul',
            'culture' => 'Stadtbewohner',
            'attributes' => [
                'au' => -2,
            ],
            'advantages' => ['Zäh', 'Natürliche Waffen'],
            'disadvantages' => ['Primitiv', 'Gejagt'],
        ]);
        $payload['skills'] = [
            ['name' => 'Heimlichkeit', 'value' => 2],
            ['name' => 'Intuition', 'value' => 1],
            ['name' => 'Natürliche Waffen', 'value' => 1],
            ['name' => 'Beruf', 'value' => 1],
            ['name' => 'Kunde', 'value' => 1],
            ['name' => 'Unterhalten', 'value' => 1],
        ];

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $payload);

        $response->assertOk();
    }

    public function test_pdf_export_rejects_barbar_attribute_above_absolute_max(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'barbar_attribute_bonus' => 'st',
            'attributes' => [
                'st' => 3,
                'ge' => 0,
            ],
        ]));

        $response->assertSessionHasErrors('attributes');
    }

    public function test_pdf_export_rejects_unmodified_attribute_above_base_max(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'barbar_attribute_bonus' => 'st',
            'attributes' => [
                'st' => 2,
                'ge' => 2,
            ],
        ]));

        $response->assertSessionHasErrors([
            'attributes' => 'Das Attribut Geschicklichkeit (GE) muss im Bereich von -1 bis 1 liegen.',
        ]);
    }

    public function test_pdf_export_rejects_attribute_point_budget_overflow(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'barbar_attribute_bonus' => 'st',
            'attributes' => [
                'st' => 2,
                'ge' => 1,
                'ro' => 1,
            ],
        ]));

        $response->assertSessionHasErrors([
            'attributes' => 'Die gewählten Attribute überschreiten die verfügbaren Attributspunkte.',
        ]);
    }

    public function test_pdf_export_accepts_guul_with_natural_weapons_requirement(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(function ($data) {
                $skills = collect($data['skills'])->keyBy('name');

                return $data['character']['race'] === 'Guul'
                    && $data['character']['culture'] === 'Stadtbewohner'
                    && ($data['attributes']['au'] ?? null) === '-1'
                    && (($skills['Natürliche Waffen'] ?? [])['value'] ?? null) === '2'
                    && in_array('Natürliche Waffen', $data['advantages'], true)
                    && in_array('Primitiv', $data['disadvantages'], true)
                    && in_array('Gejagt', $data['disadvantages'], true);
            }))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $payload = $this->validPdfPayload([
            'race' => 'Guul',
            'culture' => 'Stadtbewohner',
            'attributes' => [
                'au' => -1,
            ],
            'advantages' => ['Zäh', 'Natürliche Waffen'],
            'disadvantages' => ['Primitiv', 'Gejagt'],
        ]);
        $payload['skills'] = [
            ['name' => 'Heimlichkeit', 'value' => 2],
            ['name' => 'Intuition', 'value' => 1],
            ['name' => 'Natürliche Waffen', 'value' => 2],
            ['name' => 'Beruf', 'value' => 1],
            ['name' => 'Kunde', 'value' => 1],
            ['name' => 'Unterhalten', 'value' => 1],
        ];

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $payload);

        $response->assertOk();
    }

    public function test_pdf_export_rejects_guul_without_natural_weapons_advantage(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $payload = $this->validPdfPayload([
            'race' => 'Guul',
            'culture' => 'Stadtbewohner',
            'attributes' => [
                'au' => -1,
            ],
            'advantages' => ['Zäh'],
            'disadvantages' => ['Primitiv', 'Gejagt'],
        ]);
        $payload['skills'] = [
            ['name' => 'Heimlichkeit', 'value' => 2],
            ['name' => 'Intuition', 'value' => 1],
            ['name' => 'Natürliche Waffen', 'value' => 1],
            ['name' => 'Beruf', 'value' => 1],
            ['name' => 'Kunde', 'value' => 1],
            ['name' => 'Unterhalten', 'value' => 1],
        ];

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);

        $response->assertSessionHasErrors('advantages');
    }

    public function test_pdf_export_rejects_guul_without_au_modifier(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $payload = $this->validPdfPayload([
            'race' => 'Guul',
            'culture' => 'Stadtbewohner',
            'attributes' => [],
            'advantages' => ['Zäh', 'Natürliche Waffen'],
            'disadvantages' => ['Primitiv', 'Gejagt'],
        ]);
        $payload['skills'] = [
            ['name' => 'Heimlichkeit', 'value' => 2],
            ['name' => 'Intuition', 'value' => 1],
            ['name' => 'Natürliche Waffen', 'value' => 1],
            ['name' => 'Beruf', 'value' => 1],
            ['name' => 'Kunde', 'value' => 1],
            ['name' => 'Unterhalten', 'value' => 1],
        ];

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);

        $response->assertSessionHasErrors([
            'attributes' => 'Das Attribut Auftreten (AU) muss für die Rasse Guul übermittelt werden.',
        ]);
    }

    public function test_pdf_export_rejects_guul_with_invalid_au_modifier_label(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $payload = $this->validPdfPayload([
            'race' => 'Guul',
            'culture' => 'Stadtbewohner',
            'attributes' => [
                'au' => 1,
            ],
            'advantages' => ['Zäh', 'Natürliche Waffen'],
            'disadvantages' => ['Primitiv', 'Gejagt'],
        ]);
        $payload['skills'] = [
            ['name' => 'Heimlichkeit', 'value' => 2],
            ['name' => 'Intuition', 'value' => 1],
            ['name' => 'Natürliche Waffen', 'value' => 1],
            ['name' => 'Beruf', 'value' => 1],
            ['name' => 'Kunde', 'value' => 1],
            ['name' => 'Unterhalten', 'value' => 1],
        ];

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);

        $response->assertSessionHasErrors([
            'attributes' => 'Das Attribut Auftreten (AU) passt nicht zu den Rassenmodifikatoren von Guul.',
        ]);
    }

    public function test_pdf_export_rejects_nosfera_without_ge_modifier_label(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $payload = $this->validPdfPayload([
            'race' => 'Nosfera',
            'culture' => 'Stadtbewohner',
            'attributes' => [
                'au' => -1,
            ],
            'advantages' => ['Zäh', 'Nachtsicht'],
            'disadvantages' => ['Blutdurst', 'Lichtscheu', 'Gejagt'],
        ]);
        $payload['attributes'] = [
            'au' => -1,
        ];
        $payload['skills'] = [
            ['name' => 'Intuition', 'value' => 2],
            ['name' => 'Heimlichkeit', 'value' => 2],
            ['name' => 'Beruf', 'value' => 1],
            ['name' => 'Kunde', 'value' => 1],
            ['name' => 'Unterhalten', 'value' => 1],
        ];

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);

        $response->assertSessionHasErrors([
            'attributes' => 'Das Attribut Geschicklichkeit (GE) muss für die Rasse Nosfera übermittelt werden.',
        ]);
    }

    public function test_pdf_export_rejects_nosfera_without_nachtsicht_advantage(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $payload = $this->validPdfPayload([
            'race' => 'Nosfera',
            'culture' => 'Stadtbewohner',
            'attributes' => [
                'ge' => 1,
                'au' => -1,
            ],
            'advantages' => ['Zäh'],
            'disadvantages' => ['Blutdurst', 'Lichtscheu', 'Gejagt'],
        ]);

        $payload['skills'] = [
            ['name' => 'Intuition', 'value' => 2],
            ['name' => 'Heimlichkeit', 'value' => 2],
            ['name' => 'Beruf', 'value' => 1],
            ['name' => 'Kunde', 'value' => 1],
            ['name' => 'Unterhalten', 'value' => 1],
        ];

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);

        $response->assertSessionHasErrors('advantages');
    }

    public function test_pdf_export_rejects_techno_without_bildung_race_bonus(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $payload = $this->validPdfPayload([
            'race' => 'Techno',
            'culture' => 'Bunkermensch',
            'attributes' => [
                'st' => -1,
                'ro' => -1,
                'in' => 1,
            ],
            'advantages' => ['Zäh', 'High-Tech-Ausrüstung'],
            'disadvantages' => ['Tödliche Immunschwäche'],
        ]);
        $payload['skills'] = [
            ['name' => 'Bildung', 'value' => 2],
            ['name' => 'Nahkampf', 'value' => 1],
            ['name' => 'Fahren', 'value' => 2],
            ['name' => 'Feuerwaffen', 'value' => 2],
            ['name' => 'Heiler', 'value' => 2],
            ['name' => 'Pilot', 'value' => 2],
            ['name' => 'Techniker', 'value' => 2],
            ['name' => 'Wissenschaftler', 'value' => 2],
        ];

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);

        $response->assertSessionHasErrors('skills');
    }

    public function test_pdf_export_accepts_hydrit_with_meeresbewohner_culture(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['character']['race'] === 'Hydrit'
                && $data['character']['culture'] === 'Meeresbewohner'))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'race' => 'Hydrit',
            'culture' => 'Meeresbewohner',
            'skills' => [
                ['name' => 'Athletik', 'value' => 2],
                ['name' => 'Bildung', 'value' => 1],
                ['name' => 'Natürliche Waffen', 'value' => 1],
                ['name' => 'Beruf: Farmer', 'value' => 1],
                ['name' => 'Wissenschaftler', 'value' => 1],
            ],
            'advantages' => ['Zäh', 'Kiemen', 'Natürliche Waffen'],
            'disadvantages' => ['Anfälligkeit gegen Wahnsinn'],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_rejects_hydrit_with_other_culture(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'race' => 'Hydrit',
            'culture' => 'Landbewohner',
        ]));

        $response->assertSessionHasErrors('culture');
    }

    public function test_pdf_export_rejects_meeresbewohner_for_non_hydrit(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'race' => 'Barbar',
            'culture' => 'Meeresbewohner',
        ]));

        $response->assertSessionHasErrors('culture');
    }

    public function test_pdf_export_rejects_missing_culture_skill_requirements(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $cases = [
            'Landbewohner ohne Wetterkunde' => $this->validPdfPayload([
                'skills' => [
                    ['name' => 'Nahkampf', 'value' => 1],
                    ['name' => 'Überleben', 'value' => 1],
                    ['name' => 'Intuition', 'value' => 1],
                    ['name' => 'Beruf: Viehzüchter', 'value' => 2],
                ],
            ]),
            'Stadtbewohner ohne Wahlbonus' => $this->validPdfPayload([
                'culture' => 'Stadtbewohner',
                'skills' => [
                    ['name' => 'Nahkampf', 'value' => 1],
                    ['name' => 'Überleben', 'value' => 1],
                    ['name' => 'Intuition', 'value' => 1],
                    ['name' => 'Beruf', 'value' => 1],
                    ['name' => 'Kunde', 'value' => 1],
                ],
            ]),
            'Meeresbewohner ohne Berufsbonus' => $this->validPdfPayload([
                'race' => 'Hydrit',
                'culture' => 'Meeresbewohner',
                'skills' => [
                    ['name' => 'Athletik', 'value' => 2],
                    ['name' => 'Bildung', 'value' => 1],
                    ['name' => 'Natürliche Waffen', 'value' => 1],
                    ['name' => 'Wissenschaftler', 'value' => 1],
                ],
                'advantages' => ['Zäh', 'Kiemen', 'Natürliche Waffen'],
                'disadvantages' => ['Anfälligkeit gegen Wahnsinn'],
            ]),
            'Bunkermensch ohne Nahkampf' => $this->validPdfPayload([
                'race' => 'Techno',
                'culture' => 'Bunkermensch',
                'attributes' => [
                    'st' => -1,
                    'ro' => -1,
                    'in' => 1,
                ],
                'skills' => [
                    ['name' => 'Bildung', 'value' => 3],
                    ['name' => 'Fahren', 'value' => 2],
                    ['name' => 'Feuerwaffen', 'value' => 2],
                    ['name' => 'Heiler', 'value' => 2],
                    ['name' => 'Pilot', 'value' => 2],
                    ['name' => 'Techniker', 'value' => 2],
                    ['name' => 'Wissenschaftler', 'value' => 2],
                ],
                'advantages' => ['Zäh', 'High-Tech-Ausrüstung'],
                'disadvantages' => ['Tödliche Immunschwäche'],
            ]),
            'Mensch des 21. Jahrhunderts ohne zweiten Wissensbonus' => $this->validPdfPayload([
                'race' => 'Präkristofluu',
                'culture' => 'Mensch des 21. Jahrhunderts',
                'skills' => [
                    ['name' => 'Beruf', 'value' => 3],
                    ['name' => 'Bildung', 'value' => 1],
                    ['name' => 'Fahren', 'value' => 2],
                    ['name' => 'Feuerwaffen', 'value' => 2],
                ],
                'advantages' => ['Zäh', 'High-Tech-Ausrüstung'],
            ]),
            'Nomade ohne Bewegungsbonus' => $this->validPdfPayload([
                'culture' => 'Nomade',
                'skills' => [
                    ['name' => 'Nahkampf', 'value' => 1],
                    ['name' => 'Überleben', 'value' => 1],
                    ['name' => 'Intuition', 'value' => 1],
                ],
            ]),
            'Ruinenbewohner ohne Diebeskunst' => $this->validPdfPayload([
                'culture' => 'Ruinenbewohner',
                'skills' => [
                    ['name' => 'Nahkampf', 'value' => 1],
                    ['name' => 'Überleben', 'value' => 1],
                    ['name' => 'Intuition', 'value' => 1],
                    ['name' => 'Heimlichkeit', 'value' => 1],
                    ['name' => 'Fernkampf', 'value' => 1],
                ],
            ]),
            'Untergrundbewohner ohne Bergmann' => $this->validPdfPayload([
                'culture' => 'Untergrundbewohner',
                'skills' => [
                    ['name' => 'Nahkampf', 'value' => 1],
                    ['name' => 'Überleben', 'value' => 1],
                    ['name' => 'Intuition', 'value' => 1],
                    ['name' => 'Athletik', 'value' => 1],
                ],
            ]),
            'Volk der 13 Inseln ohne Beruf' => $this->validPdfPayload([
                'culture' => 'Volk der 13 Inseln',
                'skills' => [
                    ['name' => 'Nahkampf', 'value' => 1],
                    ['name' => 'Überleben', 'value' => 1],
                    ['name' => 'Intuition', 'value' => 1],
                    ['name' => 'Athletik', 'value' => 1],
                ],
            ]),
            'Disuuslachter ohne Seemann' => $this->validPdfPayload([
                'culture' => 'Disuuslachter (Nordmann)',
                'skills' => [
                    ['name' => 'Nahkampf', 'value' => 1],
                    ['name' => 'Überleben', 'value' => 1],
                    ['name' => 'Intuition', 'value' => 1],
                ],
            ]),
        ];

        foreach ($cases as $payload) {
            $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);

            $response->assertSessionHasErrors('skills');
        }
    }

    public function test_pdf_export_accepts_techno_with_bunkermensch_culture(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['character']['race'] === 'Techno'
                && $data['character']['culture'] === 'Bunkermensch'))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'race' => 'Techno',
            'culture' => 'Bunkermensch',
            'attributes' => [
                'st' => -1,
                'ro' => -1,
                'in' => 1,
            ],
            'skills' => [
                ['name' => 'Bildung', 'value' => 3],
                ['name' => 'Nahkampf', 'value' => 1],
                ['name' => 'Fahren', 'value' => 2],
                ['name' => 'Feuerwaffen', 'value' => 2],
                ['name' => 'Heiler', 'value' => 2],
                ['name' => 'Pilot', 'value' => 2],
                ['name' => 'Techniker', 'value' => 2],
                ['name' => 'Wissenschaftler', 'value' => 2],
            ],
            'advantages' => ['Zäh', 'High-Tech-Ausrüstung'],
            'disadvantages' => ['Tödliche Immunschwäche'],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_rejects_techno_with_other_culture(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'race' => 'Techno',
            'culture' => 'Stadtbewohner',
        ]));

        $response->assertSessionHasErrors('culture');
    }

    public function test_pdf_export_rejects_bunkermensch_for_non_techno(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'race' => 'Barbar',
            'culture' => 'Bunkermensch',
        ]));

        $response->assertSessionHasErrors('culture');
    }

    public function test_pdf_export_accepts_praekristofluu_with_mensch_des_21_jahrhunderts_culture(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['character']['race'] === 'Präkristofluu'
                && $data['character']['culture'] === 'Mensch des 21. Jahrhunderts'))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'race' => 'Präkristofluu',
            'culture' => 'Mensch des 21. Jahrhunderts',
            'skills' => [
                ['name' => 'Beruf', 'value' => 3],
                ['name' => 'Bildung', 'value' => 2],
                ['name' => 'Fahren', 'value' => 2],
                ['name' => 'Feuerwaffen', 'value' => 2],
                ['name' => 'Pilot', 'value' => 2],
                ['name' => 'Techniker', 'value' => 2],
                ['name' => 'Wissenschaftler', 'value' => 2],
            ],
            'advantages' => ['Zäh', 'High-Tech-Ausrüstung'],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_rejects_praekristofluu_with_other_culture(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'race' => 'Präkristofluu',
            'culture' => 'Landbewohner',
        ]));

        $response->assertSessionHasErrors('culture');
    }

    public function test_pdf_export_rejects_mensch_des_21_jahrhunderts_for_non_praekristofluu(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'race' => 'Barbar',
            'culture' => 'Mensch des 21. Jahrhunderts',
        ]));

        $response->assertSessionHasErrors('culture');
    }

    public function test_pdf_export_accepts_nosfera_with_general_culture(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(function ($data) {
                $skills = collect($data['skills'])->keyBy('name');

                return $data['character']['race'] === 'Nosfera'
                    && $data['character']['culture'] === 'Stadtbewohner'
                    && ($data['attributes']['ge'] ?? null) === '1'
                    && ($data['attributes']['au'] ?? null) === '-1'
                    && $skills->has('Intuition')
                    && $skills->has('Heimlichkeit')
                    && in_array('Nachtsicht', $data['advantages'], true)
                    && ! in_array('Psychisches Reservoir', $data['advantages'], true)
                    && in_array('Blutdurst', $data['disadvantages'], true)
                    && in_array('Lichtscheu', $data['disadvantages'], true)
                    && in_array('Gejagt', $data['disadvantages'], true);
            }))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'race' => 'Nosfera',
            'culture' => 'Stadtbewohner',
            'attributes' => [
                'ge' => 1,
                'au' => -1,
            ],
            'skills' => [
                ['name' => 'Intuition', 'value' => 2],
                ['name' => 'Heimlichkeit', 'value' => 2],
                ['name' => 'Beruf', 'value' => 1],
                ['name' => 'Kunde', 'value' => 1],
                ['name' => 'Unterhalten', 'value' => 1],
            ],
            'advantages' => ['Zäh', 'Nachtsicht'],
            'disadvantages' => ['Blutdurst', 'Lichtscheu', 'Gejagt'],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_accepts_taratze_with_general_culture(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(function ($data) {
                $skills = collect($data['skills'])->keyBy('name');
                $skillValue = fn (string $skillName) => ($skills[$skillName] ?? [])['value'] ?? null;

                return $data['character']['race'] === 'Taratze'
                    && $data['character']['culture'] === 'Stadtbewohner'
                    && ($data['attributes']['st'] ?? null) === '1'
                    && ($data['attributes']['wa'] ?? null) === '1'
                    && ($data['attributes']['in'] ?? null) === '-1'
                    && ($data['attributes']['au'] ?? null) === '-1'
                    && $skillValue('Intuition') === '2'
                    && $skillValue('Heimlichkeit') === '1'
                    && $skillValue('Überleben') === '1'
                    && in_array('Auffällig', $data['disadvantages'], true)
                    && in_array('Primitiv', $data['disadvantages'], true)
                    && in_array('Gejagt', $data['disadvantages'], true);
            }))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'race' => 'Taratze',
            'culture' => 'Stadtbewohner',
            'attributes' => [
                'st' => 1,
                'wa' => 1,
                'in' => -1,
                'au' => -1,
            ],
            'skills' => [
                ['name' => 'Intuition', 'value' => 2],
                ['name' => 'Heimlichkeit', 'value' => 1],
                ['name' => 'Überleben', 'value' => 1],
                ['name' => 'Beruf', 'value' => 1],
                ['name' => 'Kunde', 'value' => 1],
                ['name' => 'Unterhalten', 'value' => 1],
            ],
            'advantages' => ['Zäh'],
            'disadvantages' => ['Auffällig', 'Primitiv', 'Gejagt'],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_accepts_wulfane_with_general_culture(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(function ($data) {
                $skills = collect($data['skills'])->keyBy('name');
                $skillValue = fn (string $skillName) => ($skills[$skillName] ?? [])['value'] ?? null;

                return $data['character']['race'] === 'Wulfane'
                    && $data['character']['culture'] === 'Landbewohner'
                    && ($data['attributes']['ro'] ?? null) === '1'
                    && ($data['attributes']['au'] ?? null) === '-1'
                    && $skillValue('Intuition') === '1'
                    && $skillValue('Nahkampf') === '1'
                    && in_array('Ehrenkodex', $data['disadvantages'], true);
            }))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'race' => 'Wulfane',
            'culture' => 'Landbewohner',
            'attributes' => [
                'ro' => 1,
                'au' => -1,
            ],
            'skills' => [
                ['name' => 'Intuition', 'value' => 1],
                ['name' => 'Nahkampf', 'value' => 1],
                ['name' => 'Beruf: Landwirt', 'value' => 2],
                ['name' => 'Kunde: Wetter', 'value' => 1],
            ],
            'advantages' => ['Zäh'],
            'disadvantages' => ['Ehrenkodex'],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_accepts_nomade_culture(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['character']['race'] === 'Barbar'
                && $data['character']['culture'] === 'Nomade'))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'culture' => 'Nomade',
            'skills' => [
                ['name' => 'Nahkampf', 'value' => 1],
                ['name' => 'Reiten', 'value' => 1],
                ['name' => 'Überleben', 'value' => 1],
                ['name' => 'Intuition', 'value' => 1],
            ],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_accepts_ruinenbewohner_culture(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(function ($data) {
                $skills = collect($data['skills'])->keyBy('name');

                return $data['character']['race'] === 'Barbar'
                    && $data['character']['culture'] === 'Ruinenbewohner'
                    && $skills->has('Diebeskunst')
                    && $skills->has('Heimlichkeit')
                    && $skills->has('Fernkampf')
                    && ! $skills->has('Fernwaffen');
            }))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'culture' => 'Ruinenbewohner',
            'skills' => [
                ['name' => 'Nahkampf', 'value' => 1],
                ['name' => 'Diebeskunst', 'value' => 1],
                ['name' => 'Heimlichkeit', 'value' => 1],
                ['name' => 'Fernkampf', 'value' => 1],
                ['name' => 'Überleben', 'value' => 1],
                ['name' => 'Intuition', 'value' => 1],
            ],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_accepts_untergrundbewohner_culture(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(function ($data) {
                $skills = collect($data['skills'])->keyBy('name');

                return $data['character']['race'] === 'Barbar'
                    && $data['character']['culture'] === 'Untergrundbewohner'
                    && $skills->has('Athletik')
                    && $skills->has('Beruf: Bergmann')
                    && $skills->has('Überleben');
            }))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'culture' => 'Untergrundbewohner',
            'skills' => [
                ['name' => 'Nahkampf', 'value' => 1],
                ['name' => 'Athletik', 'value' => 1],
                ['name' => 'Beruf: Bergmann', 'value' => 1],
                ['name' => 'Überleben', 'value' => 1],
                ['name' => 'Intuition', 'value' => 1],
            ],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_accepts_disuuslachter_culture_for_barbar(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(function ($data) {
                $skills = collect($data['skills'])->keyBy('name');

                return $data['character']['race'] === 'Barbar'
                    && $data['character']['culture'] === 'Disuuslachter (Nordmann)'
                    && $skills->has('Nahkampf')
                    && $skills->has('Überleben')
                    && $skills->has('Beruf: Seemann');
            }))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'culture' => 'Disuuslachter (Nordmann)',
            'skills' => [
                ['name' => 'Nahkampf', 'value' => 1],
                ['name' => 'Überleben', 'value' => 1],
                ['name' => 'Beruf: Seemann', 'value' => 1],
                ['name' => 'Intuition', 'value' => 1],
            ],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_rejects_disuuslachter_culture_for_non_barbar(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'race' => 'Nosfera',
            'culture' => 'Disuuslachter (Nordmann)',
        ]));

        $response->assertSessionHasErrors('culture');
    }

    public function test_pdf_export_accepts_volk_der_13_inseln_for_barbar_with_required_advantage(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['character']['race'] === 'Barbar'
                && $data['character']['culture'] === 'Volk der 13 Inseln'
                && in_array('Psychische Kraft', $data['advantages'], true)))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'gender' => 'weiblich',
            'culture' => 'Volk der 13 Inseln',
            'skills' => [
                ['name' => 'Nahkampf', 'value' => 1],
                ['name' => 'Überleben', 'value' => 1],
                ['name' => 'Intuition', 'value' => 1],
                ['name' => 'Athletik', 'value' => 1],
                ['name' => 'Beruf: Bauer', 'value' => 1],
            ],
            'advantages' => ['Zaeh', 'Psychische Kraft'],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_accepts_male_volk_der_13_inseln_without_required_advantage(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['character']['gender'] === 'maennlich'
                && $data['character']['culture'] === 'Volk der 13 Inseln'
                && ! in_array('Psychische Kraft', $data['advantages'], true)))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'gender' => 'maennlich',
            'culture' => 'Volk der 13 Inseln',
            'skills' => [
                ['name' => 'Nahkampf', 'value' => 1],
                ['name' => 'Überleben', 'value' => 1],
                ['name' => 'Intuition', 'value' => 1],
                ['name' => 'Athletik', 'value' => 1],
                ['name' => 'Beruf: Fischer', 'value' => 1],
            ],
            'advantages' => ['Zaeh'],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_rejects_volk_der_13_inseln_without_valid_gender(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        foreach (['', 'unbekannt'] as $gender) {
            $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
                'gender' => $gender,
                'culture' => 'Volk der 13 Inseln',
                'advantages' => ['Zaeh'],
            ]));

            $response->assertSessionHasErrors('gender');
        }
    }

    public function test_pdf_export_rejects_volk_der_13_inseln_for_non_barbar(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'race' => 'Guul',
            'culture' => 'Volk der 13 Inseln',
        ]));

        $response->assertSessionHasErrors('culture');
    }

    public function test_pdf_export_rejects_female_volk_der_13_inseln_without_psychische_kraft(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'gender' => 'weiblich',
            'culture' => 'Volk der 13 Inseln',
            'advantages' => ['Zaeh'],
        ]));

        $response->assertSessionHasErrors('advantages');
    }

    public function test_pdf_normalizes_collection_payloads_to_trimmed_scalar_strings(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(function ($data) {
                return $data['attributes'] === [
                    'st' => '2',
                    'ge' => '',
                    'ro' => '',
                    'wi' => '0',
                    'wa' => '',
                    'in' => '1',
                    'au' => '-1',
                ]
                    && $data['skills'] === [
                        ['name' => 'Nahkampf', 'value' => '1'],
                        ['name' => 'Überleben', 'value' => '1'],
                        ['name' => 'Intuition', 'value' => '1'],
                        ['name' => 'Beruf: Viehzüchter', 'value' => '2'],
                        ['name' => 'Kunde: Wetter', 'value' => '1'],
                        ['name' => 'Diebeskunst', 'value' => '4'],
                    ]
                    && $data['advantages'] === ['Zäh', 'Anführer']
                    && $data['disadvantages'] === ['Abergläubisch'];
            }))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', [
            'character_name' => 'Collection Payload',
            'gender' => 'maennlich',
            'race' => 'Barbar',
            'culture' => 'Landbewohner',
            'attributes' => [
                'st' => ' 2 ',
                'ge' => ['manipuliert'],
                'ro' => false,
                'wi' => 0,
                'wa' => null,
                'in' => true,
                'au' => ' -1 ',
            ],
            'skills' => [
                ['name' => ['manipuliert'], 'value' => '4'],
                ['name' => ' Nahkampf ', 'value' => 1],
                ['name' => ' Überleben ', 'value' => 1],
                ['name' => ' Intuition ', 'value' => 1],
                ['name' => ' Beruf: Viehzüchter ', 'value' => 2],
                ['name' => ' Kunde: Wetter ', 'value' => 1],
                ['name' => ' Diebeskunst ', 'value' => 4],
                ['name' => false, 'value' => '3'],
            ],
            'advantages' => [' Zaeh ', ['manipuliert'], false, 'Anfuehrer', 'Zaeh'],
            'disadvantages' => [['manipuliert'], ' Aberglaeubisch ', null, 'Aberglaeubisch'],
            'disadvantage_details' => [' Aberglaeubisch ' => ' Salz, Omen, dreimal klopfen '],
            'clothing' => 'kleidung-einfach',
            'equipment_items' => [
                ['id' => 'messer-dolch', 'quantity' => 1],
                ['id' => 'seil', 'quantity' => 1],
                ['id' => 'rucksack', 'quantity' => 1],
                ['id' => 'wasserschlauch', 'quantity' => 1],
                ['id' => 'wochenration', 'quantity' => 1],
                ['id' => 'bogen', 'quantity' => 1],
            ],
        ]);

        $response->assertOk();
    }

    public function test_pdf_export_accepts_new_rulebook_disadvantages(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => in_array('Taratzenfutter', $data['disadvantages'], true)))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'disadvantages' => ['Taratzenfutter'],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_rejects_unknown_advantages_and_disadvantages(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $this->actingAs($member)
            ->post('/rpg/char-editor/pdf', $this->validPdfPayload([
                'advantages' => ['Zäh', 'Laserblick'],
            ]))
            ->assertSessionHasErrors('advantages');

        $this->actingAs($member)
            ->post('/rpg/char-editor/pdf', $this->validPdfPayload([
                'disadvantages' => ['Pechmagnet'],
            ]))
            ->assertSessionHasErrors('disadvantages');
    }

    public function test_pdf_export_rejects_unknown_and_restricted_skills(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $this->actingAs($member)
            ->post('/rpg/char-editor/pdf', $this->validPdfPayload([
                'skills' => [
                    ['name' => 'Nahkampf', 'value' => 1],
                    ['name' => 'Überleben', 'value' => 1],
                    ['name' => 'Intuition', 'value' => 1],
                    ['name' => 'Beruf: Viehzüchter', 'value' => 2],
                    ['name' => 'Kunde: Wetter', 'value' => 1],
                    ['name' => 'Fernwaffen', 'value' => 1],
                ],
            ]))
            ->assertSessionHasErrors('skills');

        $this->actingAs($member)
            ->post('/rpg/char-editor/pdf', $this->validPdfPayload([
                'skills' => [
                    ['name' => 'Nahkampf', 'value' => 1],
                    ['name' => 'Überleben', 'value' => 1],
                    ['name' => 'Intuition', 'value' => 1],
                    ['name' => 'Beruf: Viehzüchter', 'value' => 2],
                    ['name' => 'Kunde: Wetter', 'value' => 1],
                    ['name' => 'Natürliche Waffen', 'value' => 1],
                ],
            ]))
            ->assertSessionHasErrors('skills');
    }

    public function test_pdf_export_rejects_invalid_skill_values_and_duplicates(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $this->actingAs($member)
            ->post('/rpg/char-editor/pdf', $this->validPdfPayload([
                'skills' => [
                    ['name' => 'Nahkampf', 'value' => 5],
                    ['name' => 'Überleben', 'value' => 1],
                    ['name' => 'Intuition', 'value' => 1],
                    ['name' => 'Beruf: Viehzüchter', 'value' => 2],
                    ['name' => 'Kunde: Wetter', 'value' => 1],
                ],
            ]))
            ->assertSessionHasErrors('skills');

        $this->actingAs($member)
            ->post('/rpg/char-editor/pdf', $this->validPdfPayload([
                'skills' => [
                    ['name' => 'Nahkampf', 'value' => 1],
                    ['name' => 'Nahkampf', 'value' => 2],
                    ['name' => 'Überleben', 'value' => 1],
                    ['name' => 'Intuition', 'value' => 1],
                    ['name' => 'Beruf: Viehzüchter', 'value' => 2],
                    ['name' => 'Kunde: Wetter', 'value' => 1],
                ],
            ]))
            ->assertSessionHasErrors('skills');
    }

    public function test_pdf_export_rejects_skill_point_budget_overflow(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'skills' => [
                ['name' => 'Nahkampf', 'value' => 1],
                ['name' => 'Überleben', 'value' => 1],
                ['name' => 'Intuition', 'value' => 1],
                ['name' => 'Beruf: Viehzüchter', 'value' => 2],
                ['name' => 'Kunde: Wetter', 'value' => 1],
                ['name' => 'Athletik', 'value' => 4],
                ['name' => 'Diebeskunst', 'value' => 4],
                ['name' => 'Fahren', 'value' => 4],
                ['name' => 'Fernkampf', 'value' => 4],
                ['name' => 'Feuerwaffen', 'value' => 4],
                ['name' => 'Handeln', 'value' => 1],
            ],
        ]));

        $response->assertSessionHasErrors('skills');
    }

    public function test_pdf_export_applies_base_skill_grant_to_only_one_specialization(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'culture' => 'Stadtbewohner',
            'skills' => [
                ['name' => 'Nahkampf', 'value' => 1],
                ['name' => 'Überleben', 'value' => 1],
                ['name' => 'Intuition', 'value' => 1],
                ['name' => 'Kunde', 'value' => 1],
                ['name' => 'Unterhalten', 'value' => 1],
                ['name' => 'Beruf: Künstler', 'value' => 4],
                ['name' => 'Beruf: Seemann', 'value' => 4],
                ['name' => 'Athletik', 'value' => 4],
                ['name' => 'Diebeskunst', 'value' => 4],
                ['name' => 'Fahren', 'value' => 4],
                ['name' => 'Fernkampf', 'value' => 2],
            ],
        ]));

        $response->assertSessionHasErrors('skills');
    }

    public function test_pdf_export_enforces_skill_cross_rules(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $this->actingAs($member)
            ->post('/rpg/char-editor/pdf', $this->validPdfPayload([
                'skills' => [
                    ['name' => 'Nahkampf', 'value' => 1],
                    ['name' => 'Überleben', 'value' => 1],
                    ['name' => 'Intuition', 'value' => 1],
                    ['name' => 'Bildung', 'value' => 1],
                    ['name' => 'Beruf: Viehzüchter', 'value' => 2],
                    ['name' => 'Kunde: Wetter', 'value' => 1],
                ],
            ]))
            ->assertSessionHasErrors('skills');

        $this->actingAs($member)
            ->post('/rpg/char-editor/pdf', $this->validPdfPayload([
                'skills' => [
                    ['name' => 'Nahkampf', 'value' => 1],
                    ['name' => 'Überleben', 'value' => 1],
                    ['name' => 'Intuition', 'value' => 1],
                    ['name' => 'Beruf: Viehzüchter', 'value' => 2],
                    ['name' => 'Kunde: Wetter', 'value' => 1],
                    ['name' => 'Wissenschaftler', 'value' => 1],
                ],
            ]))
            ->assertSessionHasErrors('skills');
    }

    public function test_pdf_export_accepts_skill_aliases_and_specializations(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(function ($data) {
                $skills = collect($data['skills'])->keyBy('name');

                return $skills->has('Überleben')
                    && $skills->has('Beruf: Künstler')
                    && (($skills['Beruf: Künstler'] ?? [])['value'] ?? null) === '2';
            }))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'culture' => 'Stadtbewohner',
            'skills' => [
                ['name' => 'Nahkampf', 'value' => 1],
                ['name' => 'Ueberleben', 'value' => 1],
                ['name' => 'Intuition', 'value' => 1],
                ['name' => 'Beruf: Kuenstler', 'value' => 2],
                ['name' => 'Kunde', 'value' => 1],
                ['name' => 'Unterhalten', 'value' => 1],
            ],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_applies_advantage_costs_and_repeatable_counts(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => ($data['advantage_counts']['Panzerung'] ?? null) === 2))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'advantages' => ['Zäh', 'Panzerung'],
            'advantage_counts' => ['Panzerung' => 2],
            'disadvantages' => ['Auffällig', 'Taratzenfutter'],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_accepts_required_special_details_with_form_normalized_keys(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['advantages'] === ['Zäh', 'Gesteigertes Attribut']
                && $data['disadvantages'] === ['Auffällig']
                && $data['advantage_details'] === ['Gesteigertes Attribut' => 'ST +1']
                && $data['disadvantage_details'] === []))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'advantages' => ['Zäh', 'Gesteigertes Attribut'],
            'advantage_details' => [
                'Gesteigertes_Attribut' => 'ST +1',
                'Tiergefaehrte' => 'Rabe',
            ],
            'disadvantages' => ['Auffaellig'],
            'disadvantage_details' => ['Feind' => 'Rivalin'],
        ]));

        $response->assertOk();
    }

    public function test_pdf_export_rejects_too_expensive_advantage_selection(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'advantages' => ['Zäh', 'Gestaltwandler'],
            'disadvantages' => ['Auffällig', 'Taratzenfutter', 'Blutdurst'],
        ]));

        $response->assertSessionHasErrors('advantages');
    }

    public function test_pdf_export_rejects_missing_required_special_details(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'advantages' => ['Zäh', 'Anführer'],
            'disadvantages' => ['Aberglaeubisch'],
        ]));

        $response->assertSessionHasErrors('disadvantage_details');
    }

    public function test_pdf_export_validates_special_detail_and_count_payload_limits(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'advantage_details' => ['Gesteigertes_Attribut' => str_repeat('x', 256)],
            'disadvantage_details' => ['Feind' => str_repeat('x', 256)],
            'advantage_counts' => ['Panzerung' => 21],
        ]));

        $response->assertSessionHasErrors([
            'advantage_details.Gesteigertes_Attribut',
            'disadvantage_details.Feind',
            'advantage_counts.Panzerung',
        ]);
    }

    public function test_pdf_export_rejects_counts_for_non_repeatable_advantages(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'advantages' => ['Zäh', 'Anführer'],
            'advantage_counts' => ['Anführer' => 2],
            'disadvantages' => ['Auffällig'],
        ]));

        $response->assertSessionHasErrors('advantages');
    }

    public function test_portrait_data_url_payload_ignores_non_scalar_values(): void
    {
        $controller = app(RpgCharEditorController::class);
        $method = new \ReflectionMethod($controller, 'portraitDataUrlPayload');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($controller, ['manipuliert']));
        $this->assertNull($method->invoke($controller, (object) ['manipuliert' => true]));
    }

    public function test_pdf_normalizes_character_fields_to_trimmed_scalar_strings(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['character'] === [
                'player_name' => 'Holger',
                'character_name' => '',
                'gender' => 'weiblich',
                'race' => 'Barbar',
                'culture' => 'Landbewohner',
                'description' => '',
                'equipment' => '',
            ]))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'player_name' => ' Holger ',
            'character_name' => ['manipuliert'],
            'gender' => ' weiblich ',
            'race' => ' Barbar ',
            'culture' => ' Landbewohner ',
            'description' => ['manipuliert'],
            'equipment' => null,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('charakter.pdf', $response->headers->get('content-disposition'));
    }

    public function test_pdf_includes_base64_portrait_from_editor_preview_when_file_input_is_disabled(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());
        $image = UploadedFile::fake()->image('avatar.png', 1, 1);
        $dataUrl = 'data:image/png;base64,'.base64_encode($image->get());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['portrait'] === $dataUrl))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', [
            ...$this->validPdfPayload(['character_name' => 'Preview Portrait']),
            'portrait_data_url' => $dataUrl,
        ]);

        $response->assertOk();
    }

    public function test_pdf_rejects_editor_preview_portrait_data_url_above_character_limit(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        $maxChars = (new \ReflectionClass(RpgCharEditorController::class))
            ->getReflectionConstant('PORTRAIT_DATA_URL_MAX_CHARS')
            ->getValue();

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', [
            ...$this->validPdfPayload(),
            'portrait_data_url' => str_repeat('A', $maxChars + 1),
        ]);

        $response->assertSessionHasErrors('portrait_data_url');
    }

    public function test_pdf_rejects_editor_preview_portrait_data_url_with_line_breaks(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());
        $image = UploadedFile::fake()->image('avatar.png', 1, 1);
        $base64 = base64_encode($image->get());
        $dataUrl = 'data:image/png;base64,'.substr($base64, 0, 8)."\n".substr($base64, 8);

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', [
            ...$this->validPdfPayload(),
            'portrait_data_url' => $dataUrl,
        ]);

        $response->assertSessionHasErrors([
            'portrait_data_url' => 'Das Porträt konnte nicht für den PDF-Export verarbeitet werden.',
        ]);
    }

    public function test_pdf_rejects_editor_preview_portrait_data_url_with_mismatched_mime_type(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());
        $image = UploadedFile::fake()->image('avatar.jpg', 1, 1);
        $dataUrl = 'data:image/png;base64,'.base64_encode($image->get());

        Pdf::shouldReceive('view')->never();

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', [
            ...$this->validPdfPayload(),
            'portrait_data_url' => $dataUrl,
        ]);

        $response->assertSessionHasErrors([
            'portrait_data_url' => 'Das Porträt konnte nicht für den PDF-Export verarbeitet werden.',
        ]);
    }

    public function test_pdf_rejects_invalid_editor_preview_portrait_data(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', [
            ...$this->validPdfPayload(),
            'portrait_data_url' => 'data:image/png;base64,'.base64_encode('not an image'),
        ]);

        $response->assertSessionHasErrors([
            'portrait_data_url' => 'Das Porträt konnte nicht für den PDF-Export verarbeitet werden.',
        ]);
    }

    public function test_pdf_downloads_with_sanitized_filename_for_ag_rollenspiel_member(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::type('array'))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'character_name' => 'Foo/Bar',
            'gender' => 'maennlich',
            'portrait' => UploadedFile::fake()->image('avatar.jpg'),
        ]));

        $this->assertStringContainsString('foobar.pdf', $response->headers->get('content-disposition'));
    }

    public function test_rejects_non_image_portrait_for_ag_rollenspiel_member(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', [
            'portrait' => UploadedFile::fake()->create('bad.exe', 10, 'application/octet-stream'),
        ]);

        $response->assertSessionHasErrors('portrait');
    }

    public function test_pdf_endpoint_renders_real_pdf_for_ag_rollenspiel_member(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload());

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('foo-bar.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_pdf_generates_without_portrait_for_global_admin_with_different_current_team(): void
    {
        $admin = $this->createManagementUserWithDifferentCurrentTeam(Role::Admin);

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => array_key_exists('portrait', $data) && is_null($data['portrait'])))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($admin)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'character_name' => 'Foo',
            'gender' => 'maennlich',
        ]));

        $response->assertOk();
    }

    public function test_pdf_endpoint_renders_real_pdf_with_uploaded_portrait(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', [
            ...$this->validPdfPayload(['character_name' => 'Mit Portrait']),
            'portrait' => UploadedFile::fake()->image('avatar.png', 120, 120),
        ]);

        $response->assertOk();
        $this->assertStringContainsString('mit-portrait.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_pdf_includes_base64_portrait_when_uploaded_for_ag_rollenspiel_member(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => str_starts_with($data['portrait'] ?? '', 'data:image')))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload([
            'character_name' => 'Foo',
            'gender' => 'maennlich',
            'portrait' => UploadedFile::fake()->image('avatar.png'),
        ]));

        $response->assertOk();
    }

    public function test_member_without_ag_rollenspiel_is_forbidden_from_pdf_endpoint(): void
    {
        $member = $this->createMember();

        $this->actingAs($member)
            ->post('/rpg/char-editor/pdf', ['character_name' => 'Foo'])
            ->assertForbidden();
    }

    public function test_dompdf_dependency_is_installed(): void
    {
        $this->assertTrue(class_exists('Dompdf\\Dompdf'));
    }
}
