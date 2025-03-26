<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\AntragAnVorstand;
use App\Mail\AntragAnAdmin;


class CustomEmailVerificationController extends Controller
{
    public function __invoke(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('login')->with('status', 'Deine E-Mail-Adresse wurde bereits verifiziert.');
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        // Versenden der Mails an Admin und Vorstand
        Mail::to('info@maddraxikon.com')->send(new AntragAnVorstand($user));
        Mail::to('holgerehrmann@gmail.com')->send(new AntragAnAdmin($user));

        return redirect()->route('login')->with('status', 'Deine E-Mail-Adresse wurde erfolgreich bestätigt.');
    }
}
