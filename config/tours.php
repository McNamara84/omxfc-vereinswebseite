<?php

$tours = [
    'hauptmenue' => [
        'version' => 7,
        'title' => 'Hauptmenü entdecken',
        'description' => 'Führt neue Mitglieder durch Navbar, Sidebar, aktuelle Hinweise und das Profilmenü.',
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
                'description' => 'Hier findest du die Funktionen aus Community.',
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
                'title' => 'Bereich Lesen & Entdecken',
                'description' => 'Hier findest du die Funktionen aus Lesen & Entdecken.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="section-content"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="section-content"]',
                ],
                'reveal' => [
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]'],
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
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-content"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-content"]'],
                ],
            ],
            [
                'key' => 'community-cover-ratings',
                'title' => 'Cover-Bewertungen',
                'description' => 'Bewerte Titelbilder mit Brinas und entdecke anschließend die anonymisierten Ergebnisse der Community.',
                'config_enabled' => 'cover-ratings.enabled',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="community-cover-ratings"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="community-cover-ratings"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-content"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-content"]'],
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
                'title' => 'Bereich Termine & Treffen',
                'description' => 'Hier findest du die Funktionen aus Termine & Treffen.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="section-events"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="section-events"]',
                ],
                'reveal' => [
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]'],
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
                'key' => 'events-meetings',
                'title' => 'Online-Treffen',
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
                'key' => 'events-photos',
                'title' => 'Veranstaltungsfotos',
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
                'key' => 'section-projects',
                'title' => 'Bereich Projekte & Mitmachen',
                'description' => 'Hier findest du die Funktionen aus Projekte & Mitmachen.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="section-projects"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="section-projects"]',
                ],
                'reveal' => [
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]'],
                ],
            ],
            [
                'key' => 'baxx-todos',
                'title' => 'Challenges & Baxx',
                'description' => 'Hier findest du Aufgaben und Challenges, mit denen du Baxx sammelst.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="baxx-todos"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="baxx-todos"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-projects"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-projects"]'],
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
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-projects"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-projects"]'],
                ],
            ],
            [
                'key' => 'teams-hoerbuecher',
                'title' => 'Fanhörbücher (EARDRAX)',
                'description' => 'Hier steuerst du im passenden Teamkontext die Arbeit rund um Fanhörbücher.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="teams-hoerbuecher"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="teams-hoerbuecher"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-projects"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-projects"]'],
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
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-projects"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-projects"]'],
                ],
            ],
            [
                'key' => 'teams-char-editor',
                'title' => 'Charakter-Editor',
                'description' => 'Wenn du in der AG Rollenspiel aktiv bist oder den globalen Adminzugriff hast, erreichst du hier den Charakter-Editor.',
                'selectors' => [
                    'desktop' => '[data-tour-device="desktop"][data-tour-key="teams-char-editor"]',
                    'mobile' => '[data-tour-device="mobile"][data-tour-key="teams-char-editor"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-device="desktop"][data-tour-key="section-projects"]'],
                    'mobile' => ['[data-tour-device="mobile"][data-tour-key="mobile-menu-toggle"]', '[data-tour-device="mobile"][data-tour-key="section-projects"]'],
                ],
            ],
            [
                'key' => 'teams-characters',
                'title' => 'Meine Charaktere',
                'description' => 'Hier verwaltest du gespeicherte Charaktere, siehst ihren EP-Stand und öffnest die PDF-Charakterbögen. Als AG-Mitglied kannst du Verbesserungen beantragen; die AG-Leitung vergibt EP und prüft Anträge.',
                'selectors' => [
                    'desktop' => '[data-tour-key="teams-characters"]',
                    'mobile' => '[data-tour-key="teams-characters"]',
                ],
                'reveal' => [
                    'desktop' => ['[data-tour-key="section-projects"]'],
                    'mobile' => ['[data-tour-key="mobile-menu-toggle"]', '[data-tour-key="section-projects"]'],
                ],
            ],
            [
                'key' => 'section-club',
                'title' => 'Bereich Verein',
                'description' => 'Hier findest du die Funktionen aus Verein.',
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
                'key' => 'navigation-search',
                'title' => 'Suche',
                'description' => 'Suche nach einer Seite oder Funktion, auch mit bisherigen Bezeichnungen.',
                'selectors' => ['desktop' => '[data-tour-key="navigation-search"]', 'mobile' => '[data-tour-key="navigation-search"]'],
            ],
            [
                'key' => 'public-site-switch',
                'title' => 'Zur öffentlichen Website',
                'description' => 'Hier wechselst du zur öffentlichen Startseite. Über Zum Mitgliederbereich kommst du zurück.',
                'selectors' => ['desktop' => '[data-tour-key="public-site-switch"]', 'mobile' => '[data-tour-key="public-site-switch"]'],
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
        'version' => 2,
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
                'key' => 'profile-maddraxikon-baxx',
                'title' => 'Maddraxikon & Baxx',
                'description' => 'Hier verknüpfst du freiwillig dein verifiziertes Maddraxikon-Konto, trennst die Verbindung wieder und verfolgst deine Wiki-Beiträge.',
                'selectors' => [
                    'desktop' => '[data-tour-profile-key="profile-maddraxikon-baxx"]',
                    'mobile' => '[data-tour-profile-key="profile-maddraxikon-baxx"]',
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

// Desktop und mobiler Drawer verwenden im Mitgliederbereich dieselbe
// Sidebar-Struktur. Die historischen gerätespezifischen Selektoren werden hier
// normalisiert, damit die Tour nur noch einen DOM-Vertrag pflegt. Die öffentliche
// Navbar rendert weiterhin beide Varianten; der Runner wählt dort den sichtbaren
// Treffer aus.
$stripDeviceConstraint = static fn (string $selector): string => preg_replace(
    '/\[data-tour-device="(?:desktop|mobile)"\]/',
    '',
    $selector,
) ?? $selector;

foreach ($tours['hauptmenue']['steps'] as &$step) {
    foreach (($step['selectors'] ?? []) as $device => $selector) {
        $step['selectors'][$device] = $stripDeviceConstraint($selector);
    }

    foreach (($step['reveal'] ?? []) as $device => $selectors) {
        $step['reveal'][$device] = array_map($stripDeviceConstraint, $selectors);
    }

    if (($step['key'] ?? null) === 'profile-settings') {
        $profileTrigger = '[data-tour-key="profile-menu"]';
        $step['reveal']['desktop'] = [$profileTrigger];
        $step['reveal']['mobile'] = [$profileTrigger];
    }
}
unset($step);

return $tours;
