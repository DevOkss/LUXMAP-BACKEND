<?php

namespace App\Services;

class GpsValidationService
{
    private const EARTH_RADIUS_M = 6371000;

    public function isWithinRadius(
        float $eventLat,
        float $eventLng,
        float $userLat,
        float $userLng,
        int $radiusMeters
    ): bool {
        $distance = $this->haversineDistance($eventLat, $eventLng, $userLat, $userLng);

        return $distance <= $radiusMeters;
    }

    public function isValidCoordinate(?float $latitude, ?float $longitude): bool
    {
        if ($latitude === null || $longitude === null) {
            return false;
        }

        return $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180;
    }

    private function haversineDistance(
        float $lat1, float $lng1,
        float $lat2, float $lng2
    ): float {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2)
            + cos($lat1Rad) * cos($lat2Rad)
            * sin($deltaLng / 2) * sin($deltaLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_M * $c;
    }
}
