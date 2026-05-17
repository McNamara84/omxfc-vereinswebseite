<?php

return [
    'hauptmenue' => [
        'version' => 1,
        'title' => 'Hauptmenü entdecken',
        'description' => 'Fuehrt neue Mitglieder durch Schnellzugriff, Bereiche und Profil-Einstieg des Hauptmenues.',
        'self_service_enabled' => true,
        'auto_assign_on_member_approval' => true,
        'audience' => ['mitglied'],
        'steps' => [
            [
                'key' => 'mobile-menu-toggle',
                'title' => 'Hauptmenü öffnen',
                'description' => 'Auf kleineren Geräten öffnest du hier die komplette Hauptnavigation.',
                'selectors' => [
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]',
                ],
            ],
            [
                'key' => 'dashboard',
                'title' => 'Dashboard',
                'description' => 'Hier landest du nach dem Login und bekommst den schnellsten Überblick über den Verein.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="dashboard"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="dashboard"]',
                ],
                'reveal' => [
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]'],
                ],
            ],
            [
                'key' => 'current-event',
                'title' => 'Aktuelle Veranstaltung',
                'description' => 'Dieser Schnellzugriff führt direkt zur aktuell wichtigsten Veranstaltung.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="current-event"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="current-event"]',
                ],
                'reveal' => [
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]'],
                ],
            ],
            [
                'key' => 'active-poll',
                'title' => 'Aktuelle Umfrage',
                'description' => 'Wenn gerade eine Umfrage läuft, erreichst du sie hier ohne Umwege.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="active-poll"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="active-poll"]',
                ],
                'reveal' => [
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]'],
                ],
            ],
            [
                'key' => 'section-community',
                'title' => 'Bereich Community',
                'description' => 'Hier bündelt der Verein Austausch, Profile, Rezensionen und weitere Community-Inhalte.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="section-community"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="section-community"]',
                ],
                'reveal' => [
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]'],
                ],
            ],
            [
                'key' => 'community-members',
                'title' => 'Mitgliederliste',
                'description' => 'In der Mitgliederliste findest du andere Profile, Rollen und Vereinskontakte.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="community-members"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="community-members"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-community"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-community"]'],
                ],
            ],
            [
                'key' => 'community-map',
                'title' => 'Mitgliederkarte',
                'description' => 'Die Mitgliederkarte hilft dir beim regionalen Überblick und Vernetzen.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="community-map"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="community-map"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-community"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-community"]'],
                ],
            ],
            [
                'key' => 'community-reviews',
                'title' => 'Rezensionen',
                'description' => 'Hier liest und schreibst du Rezensionen und verfolgst Kommentare dazu.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="community-reviews"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="community-reviews"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-community"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-community"]'],
                ],
            ],
            [
                'key' => 'community-fanfiction',
                'title' => 'Fanfiction',
                'description' => 'Im Fanfiction-Bereich findest du veröffentlichte Texte und später den Weg zum eigenen Beitrag.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="community-fanfiction"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="community-fanfiction"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-community"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-community"]'],
                ],
            ],
            [
                'key' => 'community-swap',
                'title' => 'Tauschbörse',
                'description' => 'Über die Tauschbörse bietest du Hefte an, suchst Ausgaben und koordinierst Tausche.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="community-swap"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="community-swap"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-community"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-community"]'],
                ],
            ],
            [
                'key' => 'community-auctions',
                'title' => 'Auktionen',
                'description' => 'Hier verfolgst du Vereinsauktionen und gibst auf Angebote Gebote ab.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="community-auctions"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="community-auctions"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-community"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-community"]'],
                ],
            ],
            [
                'key' => 'section-content',
                'title' => 'Bereich Inhalte',
                'description' => 'Unter Inhalte liegen Nachschlagewerke, Downloads, Statistik und weitere Maddrax-Angebote.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="section-content"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="section-content"]',
                ],
                'reveal' => [
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]'],
                ],
            ],
            [
                'key' => 'content-maddraxiversum',
                'title' => 'Maddraxiversum',
                'description' => 'Das Maddraxiversum bündelt spielerische und experimentelle Vereinsinhalte.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="content-maddraxiversum"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="content-maddraxiversum"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-content"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-content"]'],
                ],
            ],
            [
                'key' => 'content-kompendium',
                'title' => 'Kompendium',
                'description' => 'Im Kompendium recherchierst du Begriffe, Figuren und Hintergründe aus dem Maddrax-Universum.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="content-kompendium"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="content-kompendium"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-content"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-content"]'],
                ],
            ],
            [
                'key' => 'content-downloads',
                'title' => 'Downloads',
                'description' => 'Hier liegen zentrale Vereinsdateien und weitere direkt verfügbare Materialien.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="content-downloads"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="content-downloads"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-content"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-content"]'],
                ],
            ],
            [
                'key' => 'content-3d-models',
                'title' => '3D-Modelle',
                'description' => 'Der Bereich 3D-Modelle sammelt entsprechende Vereinsprojekte und Präsentationen.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="content-3d-models"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="content-3d-models"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-content"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-content"]'],
                ],
            ],
            [
                'key' => 'content-stats',
                'title' => 'Statistik',
                'description' => 'In der Statistik findest du Auswertungen zu Romanen, Zyklen, Autor:innen und Rezensionen.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="content-stats"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="content-stats"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-content"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-content"]'],
                ],
            ],
            [
                'key' => 'section-events',
                'title' => 'Bereich Veranstaltungen',
                'description' => 'Hier findest du Treffen, Termine und Rückblicke auf Vereinsveranstaltungen.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="section-events"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="section-events"]',
                ],
                'reveal' => [
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]'],
                ],
            ],
            [
                'key' => 'events-photos',
                'title' => 'Fotos',
                'description' => 'Die Fotogalerie sammelt Bilder vergangener Treffen und anderer Veranstaltungen.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="events-photos"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="events-photos"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-events"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-events"]'],
                ],
            ],
            [
                'key' => 'events-meetings',
                'title' => 'Meetings',
                'description' => 'Über Meetings erreichst du laufende und vergangene interne oder digitale Runden.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="events-meetings"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="events-meetings"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-events"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-events"]'],
                ],
            ],
            [
                'key' => 'events-dates',
                'title' => 'Termine',
                'description' => 'Hier behältst du kommende Termine, Fristen und öffentliche Veranstaltungen im Blick.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="events-dates"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="events-dates"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-events"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-events"]'],
                ],
            ],
            [
                'key' => 'section-club',
                'title' => 'Bereich Verein',
                'description' => 'Der Vereinsbereich bündelt Regeln, Protokolle, Newsletter und weitere formale Informationen.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="section-club"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="section-club"]',
                ],
                'reveal' => [
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]'],
                ],
            ],
            [
                'key' => 'club-newsletters',
                'title' => 'Newsletter-Archiv',
                'description' => 'Im Archiv blätterst du durch veröffentlichte Newsletter-Ausgaben des Vereins.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="club-newsletters"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="club-newsletters"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-club"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-club"]'],
                ],
            ],
            [
                'key' => 'club-minutes',
                'title' => 'Protokolle',
                'description' => 'Hier findest du wichtige Protokolle aus dem Vereinsleben.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="club-minutes"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="club-minutes"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-club"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-club"]'],
                ],
            ],
            [
                'key' => 'club-bylaws',
                'title' => 'Satzung',
                'description' => 'Die Satzung beschreibt Regeln, Rechte und Grundstrukturen des Vereins.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="club-bylaws"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="club-bylaws"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-club"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-club"]'],
                ],
            ],
            [
                'key' => 'club-balance',
                'title' => 'Kassenstand',
                'description' => 'Hier siehst du den freigegebenen Stand der Vereinskasse in der Mitgliederansicht.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="club-balance"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="club-balance"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-club"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-club"]'],
                ],
            ],
            [
                'key' => 'section-baxx',
                'title' => 'Bereich Baxx',
                'description' => 'In diesem Bereich dreht sich alles um Vereinswährung, Aufgaben und Belohnungen.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="section-baxx"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="section-baxx"]',
                ],
                'reveal' => [
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]'],
                ],
            ],
            [
                'key' => 'baxx-todos',
                'title' => 'Baxx verdienen',
                'description' => 'Hier findest du Aufgaben und Challenges, mit denen du Baxx sammelst.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="baxx-todos"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="baxx-todos"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-baxx"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-baxx"]'],
                ],
            ],
            [
                'key' => 'baxx-rewards',
                'title' => 'Belohnungen einlösen',
                'description' => 'Im Belohnungsbereich gibst du gesammelte Baxx für freigeschaltete Extras aus.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="baxx-rewards"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="baxx-rewards"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-baxx"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-baxx"]'],
                ],
            ],
            [
                'key' => 'section-teams',
                'title' => 'Bereich Teams & AG',
                'description' => 'Dieser Bereich erscheint, wenn du in Arbeitsgruppen aktiv bist oder Teamzugänge hast.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="section-teams"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="section-teams"]',
                ],
                'reveal' => [
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]'],
                ],
            ],
            [
                'key' => 'teams-hoerbuecher',
                'title' => 'EARDRAX Dashboard',
                'description' => 'Hier steuerst du im passenden Teamkontext die Arbeit rund um Fanhörbücher.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="teams-hoerbuecher"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="teams-hoerbuecher"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-teams"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-teams"]'],
                ],
            ],
            [
                'key' => 'teams-kompendium',
                'title' => 'Kompendium-Teamzugang',
                'description' => 'Je nach Arbeitsgruppe findest du hier den teambezogenen Einstieg in das Kompendium.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="teams-kompendium"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="teams-kompendium"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-teams"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-teams"]'],
                ],
            ],
            [
                'key' => 'teams-manage',
                'title' => 'AG verwalten',
                'description' => 'Wenn du eine Arbeitsgruppe leitest, gelangst du hier in deren Verwaltung.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="teams-manage"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="teams-manage"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-teams"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-teams"]'],
                ],
            ],
            [
                'key' => 'section-board',
                'title' => 'Bereich Vorstand',
                'description' => 'Dieser Bereich erscheint nur mit passenden Rechten und bündelt interne Verwaltungsseiten.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="section-board"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="section-board"]',
                ],
                'reveal' => [
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]'],
                ],
            ],
            [
                'key' => 'board-ledger',
                'title' => 'Kassenbuch',
                'description' => 'Im Kassenbuch verwaltet der berechtigte Personenkreis Einnahmen, Ausgaben und Anfragen.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="board-ledger"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="board-ledger"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-board"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-board"]'],
                ],
            ],
            [
                'key' => 'board-auctions',
                'title' => 'Auktionen verwalten',
                'description' => 'Hier steuerst du Vereinsauktionen, Zuschläge und interne Auktionsabläufe.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="board-auctions"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="board-auctions"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-board"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-board"]'],
                ],
            ],
            [
                'key' => 'board-stats',
                'title' => 'Admin-Statistik',
                'description' => 'Die Admin-Statistik verdichtet interne Kennzahlen und weitere Auswertungen.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="board-stats"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="board-stats"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-board"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-board"]'],
                ],
            ],
            [
                'key' => 'board-events',
                'title' => 'Veranstaltungen verwalten',
                'description' => 'Hier pflegst du interne Veranstaltungsdaten und zugehörige Verwaltungsfunktionen.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="board-events"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="board-events"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-board"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-board"]'],
                ],
            ],
            [
                'key' => 'board-fanfiction',
                'title' => 'Fanfiction verwalten',
                'description' => 'Dieser Zugang führt zur internen Verwaltung von Fanfiction-Inhalten.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="board-fanfiction"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="board-fanfiction"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-board"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-board"]'],
                ],
            ],
            [
                'key' => 'board-tours',
                'title' => 'Touren verwalten',
                'description' => 'Vorstand und Admin können hier Touren gezielt neu zuweisen und den Status pro Mitglied prüfen.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="board-tours"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="board-tours"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-board"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-board"]'],
                ],
            ],
            [
                'key' => 'board-polls',
                'title' => 'Umfragen verwalten',
                'description' => 'Hier erstellst, bearbeitest und steuerst du Umfragen mit den nötigen Rechten.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="board-polls"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="board-polls"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-board"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-board"]'],
                ],
            ],
            [
                'key' => 'section-admin',
                'title' => 'Bereich Admin',
                'description' => 'Mit Admin-Rechten erscheint zusätzlich dieser Bereich für weitergehende Verwaltung.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="section-admin"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="section-admin"]',
                ],
                'reveal' => [
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]'],
                ],
            ],
            [
                'key' => 'admin-newsletter',
                'title' => 'Newsletter versenden',
                'description' => 'Hier startest und verwaltest du den Versand von Newsletter-Ausgaben.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="admin-newsletter"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="admin-newsletter"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-board"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-board"]'],
                ],
            ],
            [
                'key' => 'admin-messages',
                'title' => 'Kurznachrichten',
                'description' => 'Dieser Bereich bündelt interne Kurznachrichten und schnelle Admin-Kommunikation.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="admin-messages"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="admin-messages"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-admin"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-admin"]'],
                ],
            ],
            [
                'key' => 'admin-char-editor',
                'title' => 'Charakter-Editor',
                'description' => 'Hier bearbeitest du mit Admin-Rechten den Charakter-Editor und zugehörige Inhalte.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="admin-char-editor"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="admin-char-editor"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-admin"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-admin"]'],
                ],
            ],
            [
                'key' => 'admin-working-groups',
                'title' => 'Arbeitsgruppen',
                'description' => 'Über diesen Einstieg verwaltest du Arbeitsgruppen, Zuständigkeiten und Struktur.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="admin-working-groups"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="admin-working-groups"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-admin"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-admin"]'],
                ],
            ],
            [
                'key' => 'admin-rewards',
                'title' => 'Belohnungen verwalten',
                'description' => 'Hier pflegst du das Belohnungssystem und steuerst verfügbare Baxx-Prämien.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="admin-rewards"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="admin-rewards"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-admin"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-admin"]'],
                ],
            ],
            [
                'key' => 'profile-settings',
                'title' => 'Profil und Einstellungen',
                'description' => 'Hier pflegst du persönliche Daten, Serienpräferenzen und kannst Touren später erneut starten.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="profile-settings"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="profile-settings"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="profile-menu"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]'],
                ],
            ],
        ],
    ],
    'profilpflege' => [
        'version' => 1,
        'title' => 'Profil pflegen',
        'description' => 'Zeigt dir die wichtigsten Bereiche deiner Profil- und Sicherheitseinstellungen.',
        'self_service_enabled' => true,
        'auto_assign_on_member_approval' => true,
        'audience' => ['mitglied'],
        'steps' => [
            [
                'key' => 'profile-header',
                'title' => 'Profil & Einstellungen',
                'description' => 'Hier bündelst du alles, was dein Mitgliederkonto, deine Sichtbarkeit und deine Sicherheit betrifft.',
                'selectors' => [
                    'desktop' => '[data-tour-profile-key="profile-header"]',
                    'mobile' => '[data-tour-profile-key="profile-header"]',
                ],
            ],
            [
                'key' => 'profile-tour-overview',
                'title' => 'Touren & Hilfestart',
                'description' => 'In diesem Bereich startest du Touren später erneut, wenn du Funktionen noch einmal geführt ansehen möchtest.',
                'selectors' => [
                    'desktop' => '[data-tour-profile-key="profile-tour-overview"]',
                    'mobile' => '[data-tour-profile-key="profile-tour-overview"]',
                ],
            ],
            [
                'key' => 'profile-public-view',
                'title' => 'Öffentliches Profil ansehen',
                'description' => 'Mit diesem Button prüfst du schnell, wie andere Mitglieder dein Profil im Vereinsbereich sehen.',
                'selectors' => [
                    'desktop' => '[data-tour-profile-key="profile-public-view"]',
                    'mobile' => '[data-tour-profile-key="profile-public-view"]',
                ],
            ],
            [
                'key' => 'profile-personal-data',
                'title' => 'Persönliche Daten',
                'description' => 'Hier hältst du Name, Foto, Adresse, Beitrag und Kontaktmöglichkeiten aktuell.',
                'selectors' => [
                    'desktop' => '[data-tour-profile-key="profile-personal-data"]',
                    'mobile' => '[data-tour-profile-key="profile-personal-data"]',
                ],
            ],
            [
                'key' => 'profile-series-data',
                'title' => 'Serienspezifische Daten',
                'description' => 'In diesem Abschnitt pflegst du Lieblingsromane, Figuren und weitere Maddrax-Vorlieben für dein Profil.',
                'selectors' => [
                    'desktop' => '[data-tour-profile-key="profile-series-data"]',
                    'mobile' => '[data-tour-profile-key="profile-series-data"]',
                ],
            ],
            [
                'key' => 'profile-password',
                'title' => 'Passwort ändern',
                'description' => 'Nutze diesen Bereich, wenn du dein Passwort erneuern oder nach einem Vorfall absichern möchtest.',
                'selectors' => [
                    'desktop' => '[data-tour-profile-key="profile-password"]',
                    'mobile' => '[data-tour-profile-key="profile-password"]',
                ],
            ],
            [
                'key' => 'profile-two-factor',
                'title' => 'Zwei-Faktor-Authentisierung',
                'description' => 'Hier aktivierst du einen zusätzlichen Sicherheitsfaktor, damit dein Zugang besser geschützt ist.',
                'selectors' => [
                    'desktop' => '[data-tour-profile-key="profile-two-factor"]',
                    'mobile' => '[data-tour-profile-key="profile-two-factor"]',
                ],
            ],
            [
                'key' => 'profile-browser-sessions',
                'title' => 'Browser-Sitzungen',
                'description' => 'Über die Sitzungsübersicht erkennst du aktive Geräte und meldest fremde oder alte Logins ab.',
                'selectors' => [
                    'desktop' => '[data-tour-profile-key="profile-browser-sessions"]',
                    'mobile' => '[data-tour-profile-key="profile-browser-sessions"]',
                ],
            ],
        ],
    ],
];