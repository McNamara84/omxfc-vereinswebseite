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
        $role = $this->authorizeMemberArea();

        $ausgaben = NewsletterAusgabe::query()
            ->visibleInArchivFor($role)
            ->orderByDesc('published_at')
            ->orderByDesc('sent_at')
            ->paginate(12);

        return view('newsletter.archiv.index', [
            'ausgaben' => $ausgaben,
        ]);
    }

    public function show(NewsletterAusgabe $newsletterAusgabe): View
    {
        $role = $this->authorizeMemberArea();

        if (! $newsletterAusgabe->isVisibleInArchivFor($role)) {
            abort(404);
        }

        return view('newsletter.archiv.show', [
            'newsletterAusgabe' => $newsletterAusgabe,
        ]);
    }
}