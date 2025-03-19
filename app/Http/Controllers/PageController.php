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

    public function arbeitsgruppen()
    {
        return view('pages.arbeitsgruppen');
    }

    public function termine()
    {
        return view('pages.termine');
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
}
