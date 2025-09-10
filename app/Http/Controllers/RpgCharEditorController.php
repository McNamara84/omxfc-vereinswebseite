<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $request->validate([
            'portrait' => 'nullable|image|max:2048',
        ]);

        $portrait = null;
        if ($request->hasFile('portrait') && $request->file('portrait')->isValid()) {
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

        $name = Str::slug($request->input('character_name', 'charakter')) ?: 'charakter';

        return Pdf::view('rpg.char-sheet', $data)->download($name . '.pdf');
    }
}
