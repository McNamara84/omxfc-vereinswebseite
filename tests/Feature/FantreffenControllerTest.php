<?php

namespace Tests\Feature;

use App\Http\Controllers\FantreffenController;
use App\Models\FantreffenVipAuthor;
use App\Models\Veranstaltung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FantreffenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_ignores_legacy_cached_vip_author_models(): void
    {
        $veranstaltung = Veranstaltung::featuredPublic() ?? Veranstaltung::query()->orderByDesc('ist_highlight')->firstOrFail();

        $author = FantreffenVipAuthor::create([
            'veranstaltung_id' => $veranstaltung->id,
            'name' => 'Oliver Fröhlich',
            'pseudonym' => 'Ian Rolf Hill',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        Cache::put('fantreffen_vip_authors', FantreffenVipAuthor::active()->ordered()->get(), now()->addHour());

        $view = app(FantreffenController::class)->create();
        $vipAuthors = $view->getData()['vipAuthors'];

        $this->assertCount(1, $vipAuthors);
        $this->assertTrue($vipAuthors->first()->is($author));
    }
}
