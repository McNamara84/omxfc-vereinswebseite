<?php

use App\Enums\Role;
use App\Models\Poll;

return [
    'auth' => [
        [
            'layout' => 'featured',
            'title' => 'Dashboard',
            'route' => 'dashboard',
            'tour_key' => 'dashboard',
            'icon' => 'o-home',
            'active_patterns' => ['dashboard'],
        ],
        [
            'layout' => 'featured',
            'title' => 'Aktuelle Veranstaltung',
            'route' => 'veranstaltungen.aktuell',
            'tour_key' => 'current-event',
            'icon' => 'o-calendar-days',
            'active_patterns' => ['veranstaltungen.*', 'fantreffen.2026*'],
            'accent' => true,
        ],
        [
            'layout' => 'featured',
            'title' => 'Aktuelle Umfrage',
            'route' => 'umfrage.aktuell',
            'tour_key' => 'active-poll',
            'icon' => 'o-chart-bar',
            'active_patterns' => ['umfrage.aktuell'],
            'visibility_flag' => 'showActivePollForAuth',
            'label_context_key' => 'activePollMenuLabel',
        ],
        [
            'layout' => 'section',
            'title' => 'Community',
            'tour_key' => 'section-community',
            'icon' => 'o-user-group',
            'items' => [
                ['title' => 'Mitgliederliste', 'route' => 'mitglieder.index', 'tour_key' => 'community-members'],
                ['title' => 'Mitgliederkarte', 'route' => 'mitglieder.karte', 'tour_key' => 'community-map'],
                ['title' => 'Rezensionen', 'route' => 'reviews.index', 'tour_key' => 'community-reviews'],
                ['title' => 'Fanfiction', 'route' => 'fanfiction.index', 'tour_key' => 'community-fanfiction'],
                ['title' => 'Tauschbörse', 'route' => 'romantausch.index', 'tour_key' => 'community-swap'],
                ['title' => 'Auktionen', 'route' => 'auktionen.index', 'tour_key' => 'community-auctions'],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Inhalte',
            'tour_key' => 'section-content',
            'icon' => 'o-sparkles',
            'items' => [
                ['title' => 'Maddraxiversum', 'route' => 'maddraxiversum.index', 'tour_key' => 'content-maddraxiversum'],
                ['title' => 'Kompendium', 'route' => 'kompendium.index', 'tour_key' => 'content-kompendium'],
                ['title' => 'Downloads', 'route' => 'downloads', 'tour_key' => 'content-downloads'],
                ['title' => '3D-Modelle', 'route' => '3d-modelle.index', 'tour_key' => 'content-3d-models'],
                ['title' => 'Statistik', 'route' => 'statistik.index', 'tour_key' => 'content-stats'],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Veranstaltungen',
            'tour_key' => 'section-events',
            'icon' => 'o-calendar',
            'items' => [
                ['title' => 'Fotos', 'route' => 'fotogalerie', 'tour_key' => 'events-photos'],
                ['title' => 'Meetings', 'route' => 'meetings', 'tour_key' => 'events-meetings'],
                ['title' => 'Termine', 'route' => 'termine', 'tour_key' => 'events-dates'],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Verein',
            'tour_key' => 'section-club',
            'icon' => 'o-building-office-2',
            'items' => [
                ['title' => 'Newsletter-Archiv', 'route' => 'newsletter.archiv.index', 'tour_key' => 'club-newsletters'],
                ['title' => 'Protokolle', 'route' => 'protokolle', 'tour_key' => 'club-minutes'],
                ['title' => 'Satzung', 'route' => 'satzung', 'tour_key' => 'club-bylaws'],
                ['title' => 'Kassenstand', 'route' => 'kassenstand.index', 'tour_key' => 'club-balance'],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Baxx',
            'tour_key' => 'section-baxx',
            'icon' => 'o-bolt',
            'items' => [
                ['title' => 'Baxx verdienen', 'route' => 'todos.index', 'tour_key' => 'baxx-todos'],
                ['title' => 'Belohnungen einlösen', 'route' => 'rewards.index', 'tour_key' => 'baxx-rewards'],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Teams & AG',
            'tour_key' => 'section-teams',
            'icon' => 'o-rectangle-group',
            'visible_any' => [
                ['vorstand' => true],
                ['has_non_personal_team' => true],
            ],
            'items' => [
                [
                    'title' => 'EARDRAX Dashboard',
                    'route' => 'hoerbuecher.index',
                    'tour_key' => 'teams-hoerbuecher',
                    'visible_any' => [
                        ['vorstand' => true],
                        ['team_any' => ['AG Fanhörbücher']],
                    ],
                ],
                [
                    'title' => 'Kompendium',
                    'route' => 'kompendium.index',
                    'tour_key' => 'teams-kompendium',
                    'team_any' => ['AG Maddraxikon'],
                ],
                [
                    'title' => 'AG verwalten',
                    'route' => 'ag.index',
                    'tour_key' => 'teams-manage',
                    'has_non_personal_owned_team' => true,
                ],
                [
                    'title' => 'Charakter-Editor',
                    'route' => 'rpg.char-editor',
                    'tour_key' => 'teams-char-editor',
                    'visible_any' => [
                        ['team_any' => ['AG Rollenspiel']],
                        ['mitglieder_roles_any' => [Role::Admin]],
                    ],
                ],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Vorstand',
            'tour_key' => 'section-board',
            'icon' => 'o-shield-check',
            'visible_any' => [
                ['roles_any' => [Role::Admin, Role::Vorstand, Role::Kassenwart]],
                ['veranstaltungsverwaltung' => true],
            ],
            'items' => [
                ['title' => 'Kassenbuch', 'route' => 'kassenbuch.index', 'tour_key' => 'board-ledger', 'roles_any' => [Role::Admin, Role::Vorstand, Role::Kassenwart]],
                ['title' => 'Auktionen', 'route' => 'admin.auktionen.index', 'tour_key' => 'board-auctions', 'roles_any' => [Role::Admin, Role::Vorstand, Role::Kassenwart]],
                ['title' => 'Statistik', 'route' => 'admin.statistiken.index', 'tour_key' => 'board-stats', 'roles_any' => [Role::Admin, Role::Vorstand, Role::Kassenwart]],
                ['title' => 'Veranstaltungen', 'route' => 'admin.veranstaltungen.index', 'tour_key' => 'board-events', 'veranstaltungsverwaltung' => true],
                ['title' => 'Fanfiction', 'route' => 'admin.fanfiction.index', 'tour_key' => 'board-fanfiction', 'roles_any' => [Role::Admin, Role::Vorstand, Role::Kassenwart]],
                ['title' => 'Touren', 'route' => 'admin.touren.index', 'tour_key' => 'board-tours', 'roles_any' => [Role::Admin, Role::Vorstand]],
                ['title' => 'Newsletter versenden', 'route' => 'newsletter.create', 'tour_key' => 'admin-newsletter', 'roles_any' => [Role::Admin, Role::Vorstand]],
                ['title' => 'Umfrage verwalten', 'route' => 'admin.umfragen.index', 'tour_key' => 'board-polls', 'roles_any' => [Role::Admin, Role::Vorstand, Role::Kassenwart], 'can' => ['manage', Poll::class]],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Admin',
            'tour_key' => 'section-admin',
            'icon' => 'o-cog-6-tooth',
            'roles_any' => [Role::Admin],
            'items' => [
                ['title' => 'Kurznachrichten', 'route' => 'admin.messages.index', 'tour_key' => 'admin-messages'],
                ['title' => 'Arbeitsgruppen', 'route' => 'arbeitsgruppen.index', 'tour_key' => 'admin-working-groups'],
                ['title' => 'Belohnungen', 'route' => 'rewards.admin', 'tour_key' => 'admin-rewards'],
                ['title' => 'Datenbank', 'route' => 'admin.datenbank.index', 'tour_key' => 'admin-database'],
            ],
        ],
    ],
    'guest' => [
        [
            'layout' => 'featured',
            'title' => 'Aktuelle Veranstaltung',
            'route' => 'veranstaltungen.aktuell',
            'icon' => 'o-calendar-days',
            'active_patterns' => ['veranstaltungen.*', 'fantreffen.2026*'],
            'accent' => true,
        ],
        [
            'layout' => 'featured',
            'title' => 'Aktuelle Umfrage',
            'route' => 'umfrage.aktuell',
            'icon' => 'o-chart-bar',
            'active_patterns' => ['umfrage.aktuell'],
            'visibility_flag' => 'showActivePollForGuest',
            'label_context_key' => 'activePollMenuLabel',
        ],
        [
            'layout' => 'featured',
            'title' => 'Mitglied werden',
            'route' => 'mitglied.werden',
            'icon' => 'o-user-plus',
            'active_patterns' => ['mitglied.werden*'],
        ],
        [
            'layout' => 'section',
            'title' => 'Verein',
            'icon' => 'o-building-office-2',
            'items' => [
                ['title' => 'Chronik', 'route' => 'chronik'],
                ['title' => 'Ehrenmitglieder', 'route' => 'ehrenmitglieder'],
                ['title' => 'Arbeitsgruppen', 'route' => 'arbeitsgruppen'],
                ['title' => 'Satzung', 'route' => 'satzung'],
                ['title' => 'Changelog', 'route' => 'changelog'],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Veranstaltungen',
            'icon' => 'o-calendar',
            'items' => [
                ['title' => 'Termine', 'route' => 'termine'],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Mitmachen',
            'icon' => 'o-heart',
            'items' => [
                ['title' => 'Spenden', 'route' => 'spenden'],
            ],
        ],
    ],
];