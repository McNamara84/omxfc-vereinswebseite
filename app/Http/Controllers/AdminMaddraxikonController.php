<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;

class AdminMaddraxikonController extends Controller
{
    private const CACHE_KEY = 'admin.maddraxikon.navigation';
    private const CACHE_TTL_MINUTES = 10;
    private const API_ENDPOINT = 'https://de.maddraxikon.com/api.php';

    public function index(): View
    {
        $errorMessage = null;
        $content = null;

        try {
            $content = Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_TTL_MINUTES), function () {
                $response = Http::timeout(5)
                    ->retry(2, 500)
                    ->acceptJson()
                    ->get(self::API_ENDPOINT, [
                        'action' => 'parse',
                        'page' => 'Vorlage:Hauptseite/Navigation',
                        'prop' => 'text',
                        'format' => 'json',
                        'formatversion' => 2,
                    ]);

                if ($response->failed()) {
                    $response->throw();
                }

                $html = $response->json('parse.text');

                if (!is_string($html) || $html === '') {
                    throw new \RuntimeException('MediaWiki response did not contain expected HTML content.');
                }

                return $html;
            });
        } catch (Throwable $exception) {
            report($exception);
            $errorMessage = __('Der Inhalt des Maddraxikon konnte aktuell nicht geladen werden. Bitte versuchen Sie es später erneut.');
        }

        return view('admin.maddraxikon', [
            'content' => $content,
            'errorMessage' => $errorMessage,
        ]);
    }
}
