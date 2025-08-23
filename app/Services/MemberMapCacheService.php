<?php

namespace App\Services;

use App\Models\Team;
use App\Jobs\GeocodeUser;
use Illuminate\Support\Facades\Cache;

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
            ->select('users.id', 'users.name', 'users.plz', 'users.land', 'users.stadt', 'users.lat', 'users.lon')
            ->withPivot('role')
            ->wherePivotNotIn('role', ['Anwärter'])
            ->get();

        $memberData = [];
        $totalLat = 0;
        $totalLon = 0;
        $memberCount = 0;

        foreach ($members as $member) {
            if (! empty($member->plz)) {
                if (is_null($member->lat) || is_null($member->lon)) {
                    GeocodeUser::dispatchSync($member);
                    $member->refresh();
                }

                if (! is_null($member->lat) && ! is_null($member->lon)) {
                    $totalLat += $member->lat;
                    $totalLon += $member->lon;
                    $memberCount++;

                    $jitter = $this->addJitter($member->lat, $member->lon);

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

        Cache::put($cacheKey, $data, now()->addHours(24));

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
}

