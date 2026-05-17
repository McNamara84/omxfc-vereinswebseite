<?php

namespace App\Http\Controllers;

use App\Enums\NewsletterAusgabeStatus;
use App\Http\Controllers\Concerns\MembersTeamAware;
use App\Models\NewsletterAusgabe;
use App\Services\UserRoleService;
use Illuminate\View\View;

class NewsletterArchivController extends Controller
{
    use MembersTeamAware;

    public function __construct(
        private readonly UserRoleService $userRoleService,
    ) {}

    protected function getUserRoleService(): UserRoleService
    {
        return $this->userRoleService;
    }

    public function index(): View
    {
        $this->authorizeMemberArea();

        $ausgaben = NewsletterAusgabe::query()
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('sent_at')
            ->paginate(12);

        return view('newsletter.archiv.index', [
            'ausgaben' => $ausgaben,
        ]);
    }

    public function show(NewsletterAusgabe $newsletterAusgabe): View
    {
        $this->authorizeMemberArea();

        if ($newsletterAusgabe->status !== NewsletterAusgabeStatus::Veroeffentlicht) {
            abort(404);
        }

        return view('newsletter.archiv.show', [
            'newsletterAusgabe' => $newsletterAusgabe,
        ]);
    }
}