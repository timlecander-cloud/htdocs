<?php
require 'db_connection.php';

$conn = getDbConnection();

$type = $_GET['type']; // 'precinct' or 'township'
$table = ($type === 'precinct') ? 'precinct_boundaries' : 'township_boundaries';
$name_column = ($type === 'precinct') ? 'shortname' : 'politicalname';

// Add override_lat/override_lng to the query
$query = "SELECT $name_column AS name, coordinates, override_lat, override_lng FROM $table";
$result = pg_query($conn, $query);

$features = [];
while ($row = pg_fetch_assoc($result)) {
    $raw_coords = json_decode($row['coordinates']); // [ [lat, lng], [lat, lng], ... ]

    // Convert to GeoJSON-compliant [ [ [lng, lat], ... ] ]
    $geojson_coords = array_map(function($pair) {
        return [$pair[1], $pair[0]]; // flip to [lng, lat]
    }, $raw_coords);

    $properties = ['name' => $row['name']];

    // Only include overrides if present
    if (!empty($row['override_lat']) && !empty($row['override_lng'])) {
        $properties['override_lat'] = (float)$row['override_lat'];
        $properties['override_lng'] = (float)$row['override_lng'];
    }

    $features[] = [
        'type' => 'Feature',
        'properties' => $properties,
        'geometry' => [
            'type' => 'Polygon',
            'coordinates' => [ $geojson_coords ]
        ]
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'type' => 'FeatureCollection',
    'features' => $features
]);
?>
