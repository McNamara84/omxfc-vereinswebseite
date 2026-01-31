<?php

namespace App\Http\Controllers;

use App\Enums\FanfictionStatus;
use App\Enums\Role;
use App\Http\Controllers\Concerns\MembersTeamAware;
use App\Models\Fanfiction;
use App\Services\UserRoleService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FanfictionController extends Controller
{
    use MembersTeamAware;

    public function __construct(private readonly UserRoleService $userRoleService) {}

    protected function getUserRoleService(): UserRoleService
    {
        return $this->userRoleService;
    }

    /**
     * Öffentliche Ansicht für Gäste (Teaser).
     */
    public function publicIndex(): View
    {
        $fanfictions = Fanfiction::with('author')
            ->published()
            ->forTeam($this->memberTeam()->id)
            ->orderByDesc('published_at')
            ->paginate(12);

        return view('fanfiction.public-index', [
            'fanfictions' => $fanfictions,
        ]);
    }

    /**
     * Übersicht für eingeloggte Mitglieder.
     */
    public function index(Request $request): View
    {
        $role = $this->authorizeMemberArea();

        $query = Fanfiction::with(['author', 'comments.user'])
            ->published()
            ->forTeam($this->memberTeam()->id);

        // Optional: Filter by author
        if ($request->filled('author')) {
            $query->where('user_id', $request->input('author'));
        }

        $fanfictions = $query->orderByDesc('published_at')->paginate(15);

        return view('fanfiction.index', [
            'fanfictions' => $fanfictions,
            'role' => $role,
        ]);
    }

    /**
     * Einzelansicht einer Fanfiction mit Kommentaren.
     */
    public function show(Fanfiction $fanfiction): View
    {
        $role = $this->authorizeMemberArea();

        // Ensure fanfiction is published (unless user is Vorstand/Admin)
        if ($fanfiction->status !== FanfictionStatus::Published && ! in_array($role, [Role::Vorstand, Role::Admin], true)) {
            abort(404);
        }

        $fanfiction->load(['author', 'comments.user', 'comments.children.user']);

        return view('fanfiction.show', [
            'fanfiction' => $fanfiction,
            'role' => $role,
        ]);
    }
}
