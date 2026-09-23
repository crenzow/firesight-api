<?php

/**
 * Checks if a point (lng, lat) is inside a given polygon ring using Ray-Casting algorithm.
 */
function isPointInPolygon(array $point, array $polygon): bool {
    $x = $point[0]; // lng
    $y = $point[1]; // lat
    $inside = false;
    for ($i = 0, $j = count($polygon) - 1; $i < count($polygon); $j = $i++) {
        $xi = $polygon[$i][0];
        $yi = $polygon[$i][1];
        $xj = $polygon[$j][0];
        $yj = $polygon[$j][1];

        $intersect = (($yi > $y) != ($yj > $y))
            && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi);
        if ($intersect) $inside = !$inside;
    }
    return $inside;
}

/**
 * Given a lat/lng, reads the Lian barangay boundaries GeoJSON and returns the matched barangay_id.
 * Returns null if the point is outside all polygons (e.g. testing in Nasugbu).
 */
function findBarangayByLocation(float $lat, float $lng, PDO $pdo): ?int {
    $geojsonPath = __DIR__ . '/../map/barangay_boundaries_lian.json';
    if (!file_exists($geojsonPath)) {
        return null;
    }

    $json = file_get_contents($geojsonPath);
    $data = json_decode($json, true);
    if (!isset($data['features'])) {
        return null;
    }

    $point = [(float)$lng, (float)$lat];
    $matchedBarangayName = null;

    foreach ($data['features'] as $feature) {
        $geometry = $feature['geometry'];
        if ($geometry['type'] === 'Polygon') {
            $polygon = $geometry['coordinates'][0];
            if (isPointInPolygon($point, $polygon)) {
                $matchedBarangayName = $feature['properties']['BGY_NAME']
                    ?? $feature['properties']['name']
                    ?? $feature['properties']['adm4_name']
                    ?? null;
                break;
            }
        } elseif ($geometry['type'] === 'MultiPolygon') {
            foreach ($geometry['coordinates'] as $poly) {
                if (isPointInPolygon($point, $poly[0])) {
                    $matchedBarangayName = $feature['properties']['BGY_NAME']
                        ?? $feature['properties']['name']
                        ?? $feature['properties']['adm4_name']
                        ?? null;
                    break 2;
                }
            }
        }
    }

    if ($matchedBarangayName) {
        // Look up the ID in the database
        $stmt = $pdo->prepare(
            "SELECT barangay_id FROM barangay
             WHERE LOWER(REPLACE(TRIM(barangay_name), '-', ' ')) = LOWER(REPLACE(TRIM(:name), '-', ' '))
             LIMIT 1"
        );
        $stmt->execute(['name' => $matchedBarangayName]);
        $row = $stmt->fetch();
        if ($row) {
            return (int)$row['barangay_id'];
        }
    }

    return null;
}

/** Returns a short human-readable location for notification titles. */
function findReadableLocation(float $lat, float $lng, ?string $barangayName = null): string {
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: FireSight/1.0\r\n",
            'timeout' => 3,
        ],
    ]);
    $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat='
        . rawurlencode((string) $lat) . '&lon=' . rawurlencode((string) $lng);
    $response = @file_get_contents($url, false, $context);
    $data = $response !== false ? json_decode($response, true) : null;
    $address = is_array($data) && isset($data['address']) ? $data['address'] : [];

    $parts = [];
    foreach (['road', 'village', 'neighbourhood', 'suburb', 'town', 'city'] as $key) {
        $value = trim((string) ($address[$key] ?? ''));
        if ($value !== '' && !in_array($value, $parts, true)) {
            $parts[] = $value;
        }
        if (count($parts) === 3) {
            break;
        }
    }

    if ($parts) {
        return implode(', ', $parts);
    }

    return trim((string) $barangayName) !== '' ? trim((string) $barangayName) : 'submitted location';
}