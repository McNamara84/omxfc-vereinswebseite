<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Typesense\Client;

pest()->extend(TestCase::class);

test('verified clone can be persisted without an export path', function (): void {
    $host = getenv('TYPESENSE_INTEGRATION_HOST');
    $key = getenv('TYPESENSE_INTEGRATION_API_KEY');
    if (! $host || ! $key) {
        $this->markTestSkipped('Requires an isolated Typesense integration server.');
    }
    $client = new Client(['api_key' => $key, 'nodes' => [['host' => $host, 'port' => '8108', 'protocol' => 'http']], 'connection_timeout_seconds' => 5]);
    $source = 'snapshot_review_'.bin2hex(random_bytes(8));
    $target = $source.'_clone';
    $created = [];
    try {
        expect($client->debug->retrieve()['version'])->toBe('30.2');
        $client->collections->create(['name' => $source, 'fields' => [['name' => 'title', 'type' => 'string', 'locale' => 'de']]]);
        $created[] = $source;
        $client->collections[$source]->documents->create(['id' => '1', 'title' => 'Überraschung im Kompendium']);
        $client->collections[$source]->documents->create(['id' => '2', 'title' => 'Änderungen und neue Abenteuer']);
        $client->collections->create(['name' => $target], ['src_name' => $source, 'copy_documents' => 'true']);
        $created[] = $target;
        // 30.2 explicitly supports an internal Raft snapshot without a
        // snapshot_path. This checks the real server, beyond client mocks.
        expect($client->operations->perform('snapshot')['success'])->toBeTrue();
        expect($client->collections[$target]->retrieve()['num_documents'])->toBe(2)
            ->and($client->collections[$target]->documents['1']->retrieve())->toBe($client->collections[$source]->documents['1']->retrieve())
            ->and($client->collections[$target]->documents->search(['q' => 'Überraschung', 'query_by' => 'title'])['found'])->toBe(1);
    } finally {
        foreach (array_reverse($created) as $collection) {
            $client->collections[$collection]->delete();
        }
    }
});
