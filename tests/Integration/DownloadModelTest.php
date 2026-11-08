<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Download;
use App\Models\Reward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('download can be created', function (): void {
    $download = Download::factory()->create(['title' => 'Test Download', 'category' => 'Klemmbaustein-Anleitungen']);
    $this->assertDatabaseHas('downloads', ['id' => $download->id, 'title' => 'Test Download', 'category' => 'Klemmbaustein-Anleitungen']);
});

test('slug is auto generated', function (): void {
    $download = Download::factory()->create(['title' => 'Mein neuer Download', 'slug' => null]);
    $download->refresh();
    expect($download->slug)->toEqual('mein-neuer-download');
});

test('slug collision is resolved', function (): void {
    Download::factory()->create(['slug' => 'test-download']);
    $download2 = Download::factory()->create(['title' => 'Test Download', 'slug' => null]);
    $download2->refresh();
    expect($download2->slug)->toEqual('test-download-2');
});

test('slug fallback when title has only special characters', function (): void {
    $download = Download::factory()->create(['title' => '!!!???', 'slug' => null]);
    $download->refresh();
    expect($download->slug)->toEqual('download');
});

test('slug fallback collision is resolved', function (): void {
    Download::factory()->create(['slug' => 'download']);
    $download2 = Download::factory()->create(['title' => '***', 'slug' => null]);
    $download2->refresh();
    expect($download2->slug)->toEqual('download-2');
});

test('route key name is slug', function (): void {
    $download = Download::factory()->create();
    expect($download->getRouteKeyName())->toEqual('slug');
});

test('active scope filters inactive', function (): void {
    Download::factory()->create(['is_active' => true, 'title' => 'Aktiv']);
    Download::factory()->create(['is_active' => false, 'title' => 'Inaktiv']);
    $active = Download::active()->get();
    expect($active->contains('title', 'Aktiv'))->toBeTrue()
        ->and($active->contains('title', 'Inaktiv'))->toBeFalse();
});

test('by category scope', function (): void {
    Download::factory()->create(['category' => 'Fanstories', 'title' => 'Story']);
    Download::factory()->create(['category' => 'Klemmbaustein-Anleitungen', 'title' => 'Bauanleitung']);
    $stories = Download::byCategory('Fanstories')->get();
    expect($stories->contains('title', 'Story'))->toBeTrue()
        ->and($stories->contains('title', 'Bauanleitung'))->toBeFalse();
});

test('reward relationship', function (): void {
    $download = Download::factory()->create();
    $reward = Reward::factory()->create(['download_id' => $download->id]);
    expect($download->reward)->not->toBeNull()
        ->and($download->reward->id)->toEqual($reward->id);
});

test('formatted file size bytes', function (): void {
    $download = Download::factory()->create(['file_size' => 500]);
    expect($download->formatted_file_size)->toEqual('500 B');
});

test('formatted file size kilobytes', function (): void {
    $download = Download::factory()->create(['file_size' => 2048]);
    expect($download->formatted_file_size)->toEqual('2,0 KB');
});

test('formatted file size megabytes', function (): void {
    $download = Download::factory()->create(['file_size' => 2621440]);
    expect($download->formatted_file_size)->toEqual('2,5 MB');
});

test('formatted file size null', function (): void {
    $download = Download::factory()->create(['file_size' => null]);
    expect($download->formatted_file_size)->toEqual('–');
});

test('reward belongs to download', function (): void {
    $download = Download::factory()->create();
    $reward = Reward::factory()->create(['download_id' => $download->id]);
    expect($reward->download)->not->toBeNull()
        ->and($reward->download->id)->toEqual($download->id);
});

test('reward download nullable', function (): void {
    $reward = Reward::factory()->create(['download_id' => null]);
    expect($reward->download)->toBeNull();
});
