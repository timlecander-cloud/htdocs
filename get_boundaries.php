<?php
require 'db_connection.php';

$conn = getDbConnection();

$type = $_GET['type']; // 'precinct', 'township', or 'supervisor'

// Decide table + name column
switch ($type) {
    case 'precinct':
        $table = 'precinct_boundaries';
        $name_column = 'shortname';
        break;

    case 'supervisor':
        $table = 'supervisor_boundaries';
        //$name_column = 'supervisorname'; // <-- adjust to your actual column name
        $name_column = 'district_name';
        break;

    case 'township':
    default:
        $table = 'township_boundaries';
        $name_column = 'politicalname';
        break;
}

// Add override_lat/override_lng to the query
$query = "SELECT $name_column AS name, coordinates, override_lat, override_lng FROM $table";
$result = pg_query($conn, $query);

$features = [];
while ($row = pg_fetch_assoc($result)) {
    $raw_coords = json_decode($row['coordinates']); // [ [lat, lng], ... ]

    // Convert to GeoJSON [ [ [lng, lat], ... ] ]
    $geojson_coords = array_map(function($pair) {
        return [$pair[1], $pair[0]];
    }, $raw_coords);

    $properties = ['name' => $row['name']];

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
