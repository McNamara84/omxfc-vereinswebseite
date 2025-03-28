<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    public function home()
    {
        return view('pages.home');
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
}
