<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class PageController extends Controller
{
    public function home()
    {
        $whoWeAre = "Wir Maddrax-Fans sind eine muntere Gruppe unterschiedlicher Typen und Charaktere, die alle eine große Leidenschaft eint: Mit archetypischen Helden in einer dystopen Welt der Zukunft auf eine außergewöhnliche Abenteuerreise zu gehen. Manchmal gruselig, phantastisch, unglaublich – und manchmal einfach nur schräg und sonderbar. All das macht das Maddraxiversum aus, in dem alles anders ist, als gedacht.";

        $whatWeDo = "Wir treffen uns in unterschiedlichen Konstellationen mal online, um sich über dies und das auszutauschen, oder bei Fantreffen, um den Autor:innen Details aus der Schreibwerkstatt und dem Lektor Pläne für Künftiges zu entlocken – und einfach eine gute Zeit mit Gleichgesinnten zu haben. In mehreren Arbeitsgruppen werkeln wir gemeinsam an den unterschiedlichsten Fanprojekten, je nach Interessen der einzelnen Mitglieder.";

        $currentProjects = [
            [
                'title' => 'Maddraxikon',
                'description' => 'Gemeinsam erfassen wir Informationen aus den Romanen im größten Fan-Wiki zur Serie und tauschen uns bei unseren AG-Treffen über die neuesten Artikel und Funktionen im Maddraxikon aus. Auch Neueinsteigern wird geholfen.'
            ],
            [
                'title' => 'Fanhörbücher',
                'description' => 'Die AG Fanhörbücher macht die ersten 249 Romane als gratis Hörbücher auf YouTube zugänglich und plant regelmäßig weitere Veröffentlichungen.'
            ],
            [
                'title' => 'MAPDRAX',
                'description' => 'Die AG MAPDRAX kartografiert das Maddraxiversum mit dem Tool Inkarnate und bietet regelmäßig Unterstützung für Neueinsteiger an.'
            ],
            [
                'title' => 'Fantreffen',
                'description' => 'Ein Orga-Team kümmert sich um die Organisation des nächsten Fantreffens, geplant für Mai 2026.'
            ]
        ];

        $membershipBenefits = [
            'Austausch über die aktuellen Romanen mit anderen Fans',
            'Kostenlose Teilnahme an den jährlichen Fantreffen',
            'Kontakt zu Maddrax-Autor:innen',
            'Aktive Mitgestaltung des Vereinslebens',
            'Zugriff auf die neuesten Hörbücher noch vor der Veröffentlichung',
            'Zugriff auf die MAPDRAX-Beta noch vor der Veröffentlichung',
            'Zugang zu exklusiven  Sprecherrollen in den Fanhörbüchern',
        ];

        $galleryImages = [
            'images/chronik/gruendungsversammlung.jpg',
            'images/chronik/jahreshauptversammlung2024.jpg',
            'images/chronik/jahreshauptversammlung2025.jpg',
            'images/chronik/maddraxcon2025-1.jpg',
            'images/chronik/maddraxcon2025-2.jpg',
            // Weitere Bilder hier einfügen
        ];


        $team = Team::where('name', 'Mitglieder')->first();

        if ($team) {
            $memberCount = $team->users()
                ->wherePivotNotIn('role', ['Anwärter'])
                ->count();
        } else {
            // Fallback, falls das Team nicht gefunden wird
            $memberCount = 0;
        }

        return view('pages.home', compact(
            'whoWeAre',
            'whatWeDo',
            'currentProjects',
            'membershipBenefits',
            'galleryImages',
            'memberCount'
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

    public function arbeitsgruppen()
    {
        return view('pages.arbeitsgruppen');
    }

    public function termine()
    {
        $calendarUrl = 'https://calendar.google.com/calendar/embed?height=600&wkst=2&bgcolor=%23ffffff&ctz=Europe%2FBerlin&showTitle=0&showNav=1&showPrint=0&showCalendars=0&showTabs=0&showTz=0&src=Nzk5YTNmNDU0Y2NlYWJlZjg4M2JiYTg4ZWJjMTI0NTUyYTcxMzFhZDc2OTA2OWJjZDJiNjJkYmZkYzcxMWMwZkBncm91cC5jYWxlbmRhci5nb29nbGUuY29t&color=%23D50000';

        $calendarLink = 'https://calendar.google.com/calendar/u/0?cid=Nzk5YTNmNDU0Y2NlYWJlZjg4M2JiYTg4ZWJjMTI0NTUyYTcxMzFhZDc2OTA2OWJjZDJiNjJkYmZkYzcxMWMwZkBncm91cC5jYWxlbmRhci5nb29nbGUuY29t';

        return view('pages.termine', compact('calendarUrl', 'calendarLink'));
    }

    public function mitgliedWerden()
    {
        return view('pages.mitglied_werden');
    }

    public function impressum()
    {
        return view('pages.impressum');
    }

    public function datenschutz()
    {
        return view('pages.datenschutz');
    }

    public function changelog()
    {
        return view('pages.changelog');
    }

    public function mitgliedWerdenErfolgreich()
    {
        return view('pages.mitglied_werden_erfolgreich');
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
                ['datum' => '9. Februar 2025', 'titel' => 'Jahreshauptversammlung (liegt noch nicht vor)', 'datei' => '2025-02-09-jhv.pdf'],
            ],
        ];

        return view('pages.protokolle', compact('protokolle'));
    }

    public function downloadProtokoll($datei)
    {
        $path = 'protokolle/' . $datei;

        if (Storage::disk('private')->exists($path)) {
            return Storage::disk('private')->download($path);
        }

        return redirect()->back()->withErrors('Die Datei existiert nicht.');
    }
}
