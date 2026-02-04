<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AntragAnAdmin;
use App\Mail\AntragAnVorstand;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CustomEmailVerificationController extends Controller
{
    public function __invoke(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('mitglied.werden.bestaetigt')->with('status', 'Deine E-Mail-Adresse wurde bereits verifiziert.');
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        // Versenden der Mails an Admin und Vorstand
        Mail::to('vorstand@maddrax-fanclub.de')->queue(new AntragAnVorstand($user));
        Mail::to('holgerehrmann@gmail.com')->queue(new AntragAnAdmin($user));

        return redirect()->route('mitglied.werden.bestaetigt')->with('status', 'Deine E-Mail-Adresse wurde erfolgreich bestätigt.');
    }
}
