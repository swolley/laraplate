<?php

namespace Modules\Cms\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Modules\Cms\Models\Location;

class NominatimService
{
    private const BASE_URL = 'https://nominatim.openstreetmap.org';
    private const CACHE_TTL = 86400; // 24 hours

    public function search(
        string $query, 
        ?string $city = null, 
        ?string $province = null, 
        ?string $country = null,
        int $limit = 1
    ): ?array 
    {
        $cache_key = 'nominatim_' . md5($query);
        
        return Cache::remember($cache_key, self::CACHE_TTL, function () use ($query, $city, $province, $country, $limit) {
            $params = [
                'q' => $query,
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => $limit
            ];
            if ($city) {
                $params['city'] = $city;
            }
            if ($province) {
                $params['province'] = $province;
            }
            if ($country) {
                $params['country'] = $country;
            }
            $response = Http::withHeaders([
                'User-Agent' => config('app.name') . ' Application'
            ])->get(self::BASE_URL . '/search', $params);

            if (!$response->successful() || empty($response->json())) {
                return $limit > 1 ? [] : null;
            }

            $result = $response->json();
            if ($limit > 1) {
                return array_map(fn(array $result) => $this->getAddressDetails($result), $result);
            }

            return $this->getAddressDetails($result);
        });
    }

    private function getAddressDetails(array $result): Location
    {
        $address = $result['address'];
        
        return Location::make([
            'address' => $address['road'] ?? null,
            'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
            'province' => $address['state'] ?? null,
            'country' => $address['country'] ?? null,
            'postcode' => $address['postcode'] ?? null,
            'latitude' => $result['lat'] ?? null,
            'longitude' => $result['lon'] ?? null,
        ]);
    }
} 