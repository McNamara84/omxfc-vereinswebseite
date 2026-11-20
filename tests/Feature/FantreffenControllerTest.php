<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\FantreffenController;
use App\Models\FantreffenAnmeldung;
use App\Models\FantreffenVipAuthor;
use App\Models\Veranstaltung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('legacy registration rejects honeypot submissions without saving an attendee', function (): void {
    $before = FantreffenAnmeldung::query()->count();
    $request = Request::create('/fantreffen/2026', 'POST', ['website' => 'https://spam.example']);

    $response = app(FantreffenController::class)->store($request);

    expect($response->getTargetUrl())->toBe(route('fantreffen.2026'))
        ->and(session('errors')->getBag('default')->has('error'))->toBeTrue()
        ->and(FantreffenAnmeldung::query()->count())->toBe($before);
});

test('create ignores legacy cached vip author models', function (): void {
    $veranstaltung = Veranstaltung::featuredPublic() ?? Veranstaltung::query()->orderByDesc('ist_highlight')->firstOrFail();
    $author = FantreffenVipAuthor::create(['veranstaltung_id' => $veranstaltung->id, 'name' => 'Oliver Fröhlich', 'pseudonym' => 'Ian Rolf Hill', 'is_active' => true, 'sort_order' => 0]);
    Cache::put('fantreffen_vip_authors', FantreffenVipAuthor::active()->ordered()->get(), now()->addHour());
    $view = app(FantreffenController::class)->create();
    $vipAuthors = $view->getData()['vipAuthors'];
    expect($vipAuthors)->toHaveCount(1)
        ->and($vipAuthors->first()->is($author))->toBeTrue();
});
