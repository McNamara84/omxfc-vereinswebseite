<?php

use App\Enums\Role;
use App\Models\Poll;

return [
    'auth' => [
        [
            'layout' => 'featured',
            'title' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'o-home',
            'active_patterns' => ['dashboard'],
        ],
        [
            'layout' => 'featured',
            'title' => 'Fantreffen 2026',
            'route' => 'fantreffen.2026',
            'icon' => 'o-calendar-days',
            'active_patterns' => ['fantreffen.2026*'],
            'accent' => true,
        ],
        [
            'layout' => 'featured',
            'title' => 'Aktuelle Umfrage',
            'route' => 'umfrage.aktuell',
            'icon' => 'o-chart-bar',
            'active_patterns' => ['umfrage.aktuell'],
            'visibility_flag' => 'showActivePollForAuth',
            'label_context_key' => 'activePollMenuLabel',
        ],
        [
            'layout' => 'section',
            'title' => 'Community',
            'icon' => 'o-user-group',
            'items' => [
                ['title' => 'Mitgliederliste', 'route' => 'mitglieder.index'],
                ['title' => 'Mitgliederkarte', 'route' => 'mitglieder.karte'],
                ['title' => 'Rezensionen', 'route' => 'reviews.index'],
                ['title' => 'Fanfiction', 'route' => 'fanfiction.index'],
                ['title' => 'Tauschbörse', 'route' => 'romantausch.index'],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Inhalte',
            'icon' => 'o-sparkles',
            'items' => [
                ['title' => 'Maddraxiversum', 'route' => 'maddraxiversum.index'],
                ['title' => 'Kompendium', 'route' => 'kompendium.index'],
                ['title' => 'Downloads', 'route' => 'downloads'],
                ['title' => '3D-Modelle', 'route' => '3d-modelle.index'],
                ['title' => 'Statistik', 'route' => 'statistik.index'],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Veranstaltungen',
            'icon' => 'o-calendar',
            'items' => [
                ['title' => 'Fotos', 'route' => 'fotogalerie'],
                ['title' => 'Meetings', 'route' => 'meetings'],
                ['title' => 'Termine', 'route' => 'termine'],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Verein',
            'icon' => 'o-building-office-2',
            'items' => [
                ['title' => 'Protokolle', 'route' => 'protokolle'],
                ['title' => 'Satzung', 'route' => 'satzung'],
                ['title' => 'Kassenstand', 'route' => 'kassenstand.index'],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Baxx',
            'icon' => 'o-bolt',
            'items' => [
                ['title' => 'Challenges', 'route' => 'todos.index'],
                ['title' => 'Belohnungen', 'route' => 'rewards.index'],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Teams & AG',
            'icon' => 'o-rectangle-group',
            'visible_any' => [
                ['vorstand' => true],
                ['has_non_personal_team' => true],
            ],
            'items' => [
                [
                    'title' => 'EARDRAX Dashboard',
                    'route' => 'hoerbuecher.index',
                    'visible_any' => [
                        ['vorstand' => true],
                        ['team_any' => ['AG Fanhörbücher']],
                    ],
                ],
                [
                    'title' => 'Kompendium',
                    'route' => 'kompendium.index',
                    'team_any' => ['AG Maddraxikon'],
                ],
                [
                    'title' => 'AG verwalten',
                    'route' => 'ag.index',
                    'has_non_personal_owned_team' => true,
                ],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Vorstand',
            'icon' => 'o-shield-check',
            'roles_any' => [Role::Admin, Role::Vorstand, Role::Kassenwart],
            'items' => [
                ['title' => 'Kassenbuch', 'route' => 'kassenbuch.index'],
                ['title' => 'Statistik', 'route' => 'admin.statistiken.index'],
                ['title' => 'Anmeldungen FT', 'route' => 'admin.fantreffen.2026'],
                ['title' => 'Fanfiction', 'route' => 'admin.fanfiction.index'],
                ['title' => 'Umfrage verwalten', 'route' => 'admin.umfragen.index', 'can' => ['manage', Poll::class]],
            ],
        ],
        [
            'layout' => 'section',
            'title' => 'Admin',
            'icon' => 'o-cog-6-tooth',
            'roles_any' => [Role::Admin],
            'items' => [
                ['title' => 'Newsletter versenden', 'route' => 'newsletter.create'],
                ['title' => 'Kurznachrichten', 'route' => 'admin.messages.index'],
                ['title' => 'Charakter-Editor', 'route' => 'rpg.char-editor'],
                ['title' => 'Arbeitsgruppen', 'route' => 'arbeitsgruppen.index'],
                ['title' => 'Belohnungen', 'route' => 'rewards.admin'],
            ],
        ],
    ],
    'guest' => [
        [
            'layout' => 'featured',
            'title' => 'Fantreffen 2026',
            'route' => 'fantreffen.2026',
            'icon' => 'o-calendar-days',
            'active_patterns' => ['fantreffen.2026*'],
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