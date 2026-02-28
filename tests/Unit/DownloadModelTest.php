<?php

namespace Tests\Unit;

use App\Models\Download;
use App\Models\Reward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DownloadModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_can_be_created(): void
    {
        $download = Download::factory()->create([
            'title' => 'Test Download',
            'category' => 'Klemmbaustein-Anleitungen',
        ]);

        $this->assertDatabaseHas('downloads', [
            'id' => $download->id,
            'title' => 'Test Download',
            'category' => 'Klemmbaustein-Anleitungen',
        ]);
    }

    public function test_slug_is_auto_generated(): void
    {
        $download = Download::factory()->create([
            'title' => 'Mein neuer Download',
            'slug' => null,
        ]);

        $download->refresh();
        $this->assertEquals('mein-neuer-download', $download->slug);
    }

    public function test_slug_collision_is_resolved(): void
    {
        Download::factory()->create(['slug' => 'test-download']);
        $download2 = Download::factory()->create([
            'title' => 'Test Download',
            'slug' => null,
        ]);

        $download2->refresh();
        $this->assertEquals('test-download-2', $download2->slug);
    }

    public function test_slug_fallback_when_title_has_only_special_characters(): void
    {
        $download = Download::factory()->create([
            'title' => '!!!???',
            'slug' => null,
        ]);

        $download->refresh();
        $this->assertEquals('download', $download->slug);
    }

    public function test_slug_fallback_collision_is_resolved(): void
    {
        Download::factory()->create(['slug' => 'download']);
        $download2 = Download::factory()->create([
            'title' => '***',
            'slug' => null,
        ]);

        $download2->refresh();
        $this->assertEquals('download-2', $download2->slug);
    }

    public function test_route_key_name_is_slug(): void
    {
        $download = Download::factory()->create();
        $this->assertEquals('slug', $download->getRouteKeyName());
    }

    public function test_active_scope_filters_inactive(): void
    {
        Download::factory()->create(['is_active' => true, 'title' => 'Aktiv']);
        Download::factory()->create(['is_active' => false, 'title' => 'Inaktiv']);

        $active = Download::active()->get();

        $this->assertTrue($active->contains('title', 'Aktiv'));
        $this->assertFalse($active->contains('title', 'Inaktiv'));
    }

    public function test_by_category_scope(): void
    {
        Download::factory()->create(['category' => 'Fanstories', 'title' => 'Story']);
        Download::factory()->create(['category' => 'Klemmbaustein-Anleitungen', 'title' => 'Bauanleitung']);

        $stories = Download::byCategory('Fanstories')->get();

        $this->assertTrue($stories->contains('title', 'Story'));
        $this->assertFalse($stories->contains('title', 'Bauanleitung'));
    }

    public function test_reward_relationship(): void
    {
        $download = Download::factory()->create();
        $reward = Reward::factory()->create([
            'download_id' => $download->id,
        ]);

        $this->assertNotNull($download->reward);
        $this->assertEquals($reward->id, $download->reward->id);
    }

    public function test_formatted_file_size_bytes(): void
    {
        $download = Download::factory()->create(['file_size' => 500]);
        $this->assertEquals('500 B', $download->formatted_file_size);
    }

    public function test_formatted_file_size_kilobytes(): void
    {
        $download = Download::factory()->create(['file_size' => 2048]);
        $this->assertEquals('2,0 KB', $download->formatted_file_size);
    }

    public function test_formatted_file_size_megabytes(): void
    {
        $download = Download::factory()->create(['file_size' => 2621440]);
        $this->assertEquals('2,5 MB', $download->formatted_file_size);
    }

    public function test_formatted_file_size_null(): void
    {
        $download = Download::factory()->create(['file_size' => null]);
        $this->assertEquals('–', $download->formatted_file_size);
    }

    public function test_reward_belongs_to_download(): void
    {
        $download = Download::factory()->create();
        $reward = Reward::factory()->create(['download_id' => $download->id]);

        $this->assertNotNull($reward->download);
        $this->assertEquals($download->id, $reward->download->id);
    }

    public function test_reward_download_nullable(): void
    {
        $reward = Reward::factory()->create(['download_id' => null]);

        $this->assertNull($reward->download);
    }
}
