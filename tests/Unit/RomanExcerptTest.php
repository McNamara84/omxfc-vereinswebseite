<?php

namespace Tests\Unit;

use App\Models\RomanExcerpt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RomanExcerptTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_a_stable_typesense_document_id_from_the_path(): void
    {
        $path = 'romane/maddrax/001 - Der Gott aus dem Eis.txt';

        $this->assertSame(
            RomanExcerpt::scoutDocumentId($path),
            RomanExcerpt::scoutDocumentId($path)
        );
    }

    #[Test]
    public function it_exposes_id_and_path_in_the_searchable_payload(): void
    {
        $excerpt = new RomanExcerpt([
            'path' => 'romane/maddrax/001 - Der Gott aus dem Eis.txt',
            'cycle' => 'maddrax',
            'roman_nr' => 1,
            'title' => 'Der Gott aus dem Eis',
            'body' => 'Matthew Drax reist erneut in die Zukunft.',
        ]);

        $payload = $excerpt->toSearchableArray();

        $this->assertSame($excerpt->getScoutKey(), $payload['id']);
        $this->assertSame('romane/maddrax/001 - Der Gott aus dem Eis.txt', $payload['path']);
        $this->assertSame('1', $payload['roman_nr']);
        $this->assertArrayHasKey('body', $payload);
    }

    #[Test]
    public function it_removes_stop_words_without_expanding_the_body_into_token_arrays(): void
    {
        $excerpt = new RomanExcerpt([
            'path' => 'romane/maddrax/002 - Test.txt',
            'cycle' => 'maddrax',
            'roman_nr' => 2,
            'title' => 'Test',
            'body' => 'Und Matthew reist, weil die Zukunft anders ist.',
        ]);

        $payload = $excerpt->toSearchableArray();

        $this->assertSame('Matthew reist Zukunft anders', $payload['body']);
    }
}
