<?php

namespace Tests\Feature;

use App\Models\ResourceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElmoResourceTypesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_without_api_key_is_rejected(): void
    {
        config(['elmo.api_key' => 'test-key']);

        $response = $this->getJson('/api/v1/resource-types/elmo');

        $response->assertUnauthorized();
        $response->assertExactJson([
            'message' => 'Missing API key.',
        ]);
    }

    public function test_request_with_invalid_api_key_is_rejected(): void
    {
        config(['elmo.api_key' => 'test-key']);

        $response = $this->withHeader('X-API-KEY', 'wrong-key')
            ->getJson('/api/v1/resource-types/elmo');

        $response->assertUnauthorized();
        $response->assertExactJson([
            'message' => 'Invalid API key.',
        ]);
    }

    public function test_request_is_blocked_when_api_key_is_not_configured(): void
    {
        config(['elmo.api_key' => '']);

        $response = $this->withHeader('X-API-KEY', 'any')
            ->getJson('/api/v1/resource-types/elmo');

        $response->assertStatus(503);
        $response->assertExactJson([
            'message' => 'ELMO API key is not configured.',
        ]);
    }

    public function test_returns_elmo_resource_types_when_api_key_is_valid(): void
    {
        config(['elmo.api_key' => 'test-key']);

        $elmoResources = ResourceType::factory()->count(2)->sequence(
            ['name' => 'Audio Guide', 'slug' => 'audio-guide'],
            ['name' => 'World Map', 'slug' => 'world-map']
        )->create();

        ResourceType::factory()->create([
            'application' => 'ERNIE',
            'name' => 'External Resource',
            'slug' => 'external-resource',
        ]);

        $response = $this->withHeader('X-API-KEY', 'test-key')
            ->getJson('/api/v1/resource-types/elmo');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJson([
            'data' => [
                [
                    'id' => $elmoResources[0]->id,
                    'application' => 'ELMO',
                    'slug' => 'audio-guide',
                    'name' => 'Audio Guide',
                    'description' => $elmoResources[0]->description,
                    'updated_at' => $elmoResources[0]->updated_at->toISOString(),
                ],
                [
                    'id' => $elmoResources[1]->id,
                    'application' => 'ELMO',
                    'slug' => 'world-map',
                    'name' => 'World Map',
                    'description' => $elmoResources[1]->description,
                    'updated_at' => $elmoResources[1]->updated_at->toISOString(),
                ],
            ],
        ]);
    }
}
