<?php

namespace App\Http\Controllers;

class RpgCharEditorController extends Controller
{
    /**
     * Show the character editor form.
     */
    public function index()
    {
        return view('rpg.char-editor');
    }
}
