<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\LaravelPdf\Facades\Pdf;

class RpgCharEditorController extends Controller
{
    /**
     * Show the character editor form.
     */
    public function index()
    {
        return view('rpg.char-editor');
    }

    /**
     * Generate a character sheet PDF.
     */
    public function pdf(Request $request)
    {
        $portrait = null;
        if ($request->hasFile('portrait')) {
            $portrait = 'data:' . $request->file('portrait')->getMimeType() . ';base64,' . base64_encode($request->file('portrait')->get());
        }

        $data = [
            'character' => $request->all(),
            'attributes' => $request->input('attributes', []),
            'skills' => $request->input('skills', []),
            'advantages' => $request->input('advantages', []),
            'disadvantages' => $request->input('disadvantages', []),
            'portrait' => $portrait,
        ];

        $name = $request->input('character_name', 'charakter');

        return Pdf::view('rpg.char-sheet', $data)->download($name . '.pdf');
    }
}
