<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Team;
use Illuminate\Support\Facades\Storage;

class PageController extends Controller
{
    public function home()
    {
        $homeContent = config('content.home');

        $team = Team::membersTeam();

        if ($team) {
            $memberCount = $team->activeUsers()->count();
            $reviewCount = Review::withoutTrashed()
                ->where('team_id', $team->id)
                ->count();
        } else {
            // Fallback, falls das Team nicht gefunden wird
            $memberCount = 0;
            $reviewCount = 0;
        }

        $homeDescription = sprintf(
            data_get($homeContent, 'hero.meta_description_template', 'Aktuelle Projekte, Chronik und Vorteile einer Mitgliedschaft im offiziellen MADDRAX Fanclub e. V. sowie %d Community-Rezensionen zu MADDRAX-Romanen.'),
            $reviewCount
        );

        $organizationUrl = config('app.url') ?? url('/');
        $logoUrl = asset('images/omxfc-logo.png');
        $sameAs = [
            'https://www.facebook.com/mxikon',
            'https://www.instagram.com/offizieller_maddrax_fanclub/',
            'https://www.youtube.com/@mxikon',
        ];

        $structuredData = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    'name' => config('app.name', 'Offizieller MADDRAX Fanclub e. V.'),
                    'url' => $organizationUrl,
                    'logo' => $logoUrl,
                    'sameAs' => $sameAs,
                ],
                [
                    '@type' => 'WebSite',
                    'name' => config('app.name', 'Offizieller MADDRAX Fanclub e. V.'),
                    'url' => $organizationUrl,
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => route('kompendium.search').'?q={search_term_string}',
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                [
                    '@type' => 'CreativeWorkSeries',
                    'name' => 'MADDRAX-Romanserie',
                    'about' => 'Community-Rezensionen zu MADDRAX-Büchern',
                    'url' => route('reviews.index'),
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => config('app.name', 'Offizieller MADDRAX Fanclub e. V.'),
                        'url' => $organizationUrl,
                    ],
                    'reviewCount' => $reviewCount,
                ],
            ],
        ];

        return view('pages.home', compact(
            'homeContent',
            'memberCount',
            'reviewCount',
            'homeDescription',
            'structuredData'
        ));
    }

    public function satzung()
    {
        return view('pages.satzung');
    }

    public function chronik()
    {
        return view('pages.chronik');
    }

    public function ehrenmitglieder()
    {
        return view('pages.ehrenmitglieder');
    }

    public function termine()
    {
        $baseCalendarId = 'Nzk5YTNmNDU0Y2NlYWJlZjg4M2JiYTg4ZWJjMTI0NTUyYTcxMzFhZDc2OTA2OWJjZDJiNjJkYmZkYzcxMWMwZkBncm91cC5jYWxlbmRhci5nb29nbGUuY29t';

        $calendarUrl = "https://calendar.google.com/calendar/embed?height=600&wkst=2&bgcolor=%23ffffff&ctz=Europe%2FBerlin&showTitle=0&showNav=1&showPrint=0&showCalendars=0&showTabs=0&showTz=0&src={$baseCalendarId}&color=%23D50000";

        $calendarUrlAgenda = "https://calendar.google.com/calendar/embed?mode=AGENDA&height=600&wkst=2&bgcolor=%23ffffff&ctz=Europe%2FBerlin&showTitle=0&showNav=0&showPrint=0&showCalendars=0&showTabs=0&showTz=0&src={$baseCalendarId}&color=%23D50000";

        $calendarLink = "https://calendar.google.com/calendar/u/0?cid={$baseCalendarId}";

        return view('pages.termine', compact('calendarUrl', 'calendarUrlAgenda', 'calendarLink'));
    }

    public function mitgliedWerden()
    {
        return view('pages.mitglied_werden', [
            'membershipPage' => config('content.mitglied_werden'),
        ]);
    }

    public function impressum()
    {
        return view('pages.impressum');
    }

    public function datenschutz()
    {
        return view('pages.datenschutz');
    }

    public function spenden()
    {
        return view('pages.spenden', [
            'donationPage' => config('content.spenden'),
        ]);
    }

    public function changelog()
    {
        return view('pages.changelog');
    }

    public function mitgliedWerdenErfolgreich()
    {
        return view('pages.mitglied_werden_erfolgreich');
    }

    public function mitgliedWerdenBestaetigt()
    {
        return view('pages.mitglied_werden_bestaetigt');
    }

    public function protokolle()
    {
        $protokolle = [
            2023 => [
                ['datum' => '20. Mai 2023', 'titel' => 'Gründungsversammlung', 'datei' => '2023-05-20-gruendungsversammlung.pdf'],
            ],
            2024 => [
                ['datum' => '26. Januar 2024', 'titel' => 'Außerordentliche Mitgliederversammlung', 'datei' => '2024-01-26-aomv.pdf'],
                ['datum' => '11. Mai 2024', 'titel' => 'Jahreshauptversammlung', 'datei' => '2024-05-11-jhv.pdf'],
                ['datum' => '22. November 2024', 'titel' => 'Außerordentliche Mitgliederversammlung', 'datei' => '2024-11-22-aomv.pdf'],
            ],
            2025 => [
                ['datum' => '9. Februar 2025', 'titel' => 'Jahreshauptversammlung', 'datei' => '2025-02-09-jhv.pdf'],
                ['datum' => '21. August 2025', 'titel' => 'Außerordentliche Mitgliederversammlung', 'datei' => '2025-08-21-aomv.pdf'],
            ],
            2026 => [
                ['datum' => '6. März 2026', 'titel' => 'Außerordentliche Mitgliederversammlung', 'datei' => '2026-03-06-aomv.pdf'],
            ],
        ];

        krsort($protokolle);

        return view('pages.protokolle', compact('protokolle'));
    }

    public function downloadProtokoll($datei)
    {
        $path = 'protokolle/'.$datei;

        if (Storage::disk('private')->exists($path)) {
            return Storage::disk('private')->download($path);
        }

        return redirect()->back()->withErrors('Die Datei existiert nicht.');
    }
}
