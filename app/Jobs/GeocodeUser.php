<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeocodeUser implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user)
    {
        //
    }

    public function handle(): void
    {
        if (empty($this->user->plz)) {
            return;
        }

        $coordinates = $this->getCoordinatesForPostalCode($this->user->plz, $this->user->land);

        if ($coordinates) {
            $this->user->lat = $coordinates['lat'];
            $this->user->lon = $coordinates['lon'];
            $this->user->saveQuietly();
        }
    }

    private function getCoordinatesForPostalCode($postalCode, $country = 'Deutschland')
    {
        $cacheKey = 'postal_code_'.$country.'_'.$postalCode;

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = Http::get('https://nominatim.openstreetmap.org/search', [
            'postalcode' => $postalCode,
            'country' => $country,
            'format' => 'json',
            'limit' => 1,
            'email' => config('mail.from.address'),
        ]);

        if ($response->successful() && count($response->json()) > 0) {
            $data = $response->json()[0];
            $result = [
                'lat' => (float) $data['lat'],
                'lon' => (float) $data['lon'],
            ];

            Cache::put($cacheKey, $result, now()->addDays(30));

            return $result;
        }

        return null;
    }
}
