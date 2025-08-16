<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MemberMapCacheService
{
    public function getMemberMapData(Team $team): array
    {
        $cacheKey = "member_map_data_team_{$team->id}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $members = $team->users()
            ->as('pivot')
            ->select('users.id', 'users.name', 'users.plz', 'users.land', 'users.stadt')
            ->withPivot('role')
            ->wherePivotNotIn('role', ['Anwärter'])
            ->get();

        $memberData = [];
        $totalLat = 0;
        $totalLon = 0;
        $memberCount = 0;

        foreach ($members as $member) {
            if (! empty($member->plz)) {
                $coordinates = $this->getCoordinatesForPostalCode($member->plz, $member->land);

                if ($coordinates) {
                    $totalLat += $coordinates['lat'];
                    $totalLon += $coordinates['lon'];
                    $memberCount++;

                    $jitter = $this->addJitter($coordinates['lat'], $coordinates['lon']);

                    $memberData[] = [
                        'name' => $member->name,
                        'city' => $member->stadt,
                        'role' => $member->pivot->role,
                        'lat' => $jitter['lat'],
                        'lon' => $jitter['lon'],
                        'profile_url' => route('profile.view', $member->id),
                    ];
                }
            }
        }

        $centerLat = $memberCount > 0 ? $totalLat / $memberCount : 51.1657;
        $centerLon = $memberCount > 0 ? $totalLon / $memberCount : 10.4515;

        $data = [
            'memberData' => $memberData,
            'centerLat' => $centerLat,
            'centerLon' => $centerLon,
        ];

        Cache::put($cacheKey, $data, now()->addHours(12));

        return $data;
    }

    public function refresh(Team $team): array
    {
        Cache::forget("member_map_data_team_{$team->id}");

        return $this->getMemberMapData($team);
    }

    private function addJitter($lat, $lon): array
    {
        $latJitter = (mt_rand(-50, 50) / 10000);
        $lonJitter = (mt_rand(-50, 50) / 10000);

        return [
            'lat' => $lat + $latJitter,
            'lon' => $lon + $lonJitter,
        ];
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

