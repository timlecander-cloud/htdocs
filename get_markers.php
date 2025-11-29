<?php
// get_markers.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set proper JSON header
header('Content-Type: application/json');

// Initialize error logging
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, 'map_errors.log');
}

try {
    require 'db_connection.php'; // Adjust path if needed

    $conn = getDbConnection();

    $lat_north = $_GET['north'];
    $lat_south = $_GET['south'];
    $lng_east = $_GET['east'];
    $lng_west = $_GET['west'];
    $parties = $_GET['parties'] ?? [];
    $rawTownships = $_GET['townships'] ?? '';
    $townships = array_filter(array_map('trim', explode(',', $rawTownships)));
    $rawPrecincts = $_GET['precincts'] ?? '';
    $precincts = array_filter(array_map('trim', explode(',', $rawPrecincts)));
    $rawWards = $_GET['wards'] ?? '';
    $wards = array_filter(array_map('trim', explode(',', $rawWards)));
    $rawSupervisors = $_GET['supervisors'] ?? '';
    $supervisors = array_filter(array_map('trim', explode(',', $rawSupervisors)));

    $includeNeighborhoods = isset($_GET['neighborhoods']) && $_GET['neighborhoods'] === 'true';

    $pgPartyArray = '{' . implode(',', array_map(function($s) { return '"' . $s . '"'; }, $parties)) . '}';

    $pgTownshipArray = implode(',', $townships); // ✅ Now it's an array
    $pgPrecinctArray = implode(',', $precincts);
    $pgWardArray = implode(',', $wards);
    $pgSupervisorArray = implode(',', $supervisors);

    if ($lat_north === null || $lat_south === null || $lng_east === null || $lng_west === null) {
        throw new Exception('Missing or invalid parameters');
    }

    $markers = [];

    $townshipString = $pgTownshipArray;
    $precinctString = $pgPrecinctArray;
    $wardString = $pgWardArray;
    $supervisorString = $pgSupervisorArray;

    // Assume $townshipString and $precinctString are already defined
    switch (true) {
        case (!empty($townshipString)):
            $areaString = $townshipString;
            $target_field = 'full_township';
            break;
        case (!empty($precinctString)):
            $areaString = $precinctString;
            $target_field = 'precinct';
            break;
        case (!empty($wardString)):
            $areaString = $wardString;
            $target_field = 'city_council_ward';
            break;
        case (!empty($supervisorString)):
            $areaString = $supervisorString;
            $target_field = 'county_supervisor';
            break;
        default:
            $areaString = 'all'; // Fallback, could also be 'all'
            $target_field = 'full_township'; // Safe default
            break;
    }

    $paramsLog = [
        $_GET['north'] ?? null,
        $_GET['south'] ?? null,
        $_GET['east'] ?? null,
        $_GET['west'] ?? null,                
        $pgPartyArray,
        $townshipString,
        $includeNeighborhoods,
        $precinctString,
        $wardString,
        $supervisorString,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ];

    $queryLog = "
        INSERT INTO marker_request_log (
            lat_north, lat_south, lng_east, lng_west, party, township, neighborhood,
            precinct, ward, supervisor, ip_address, user_agent
        ) VALUES (
            $1, $2, $3, $4, $5,
            $6, $7, $8, $9, $10, $11, $12
        )
    ";

    pg_query_params($conn, $queryLog, $paramsLog);

    $params1 = [$lat_south, $lat_north, $lng_west, $lng_east, $pgPartyArray, $areaString];

    $query1 = sprintf(
        "SELECT p.latitude, p.longitude, p.first_name, p.last_name, p.party,
            p.house_num || COALESCE(' ' || p.house_suffix, '') || ' ' ||
            COALESCE(p.pre_dir || ' ', '') || 
	    p.street_name || ' ' || 
	    COALESCE(p.street_type, '') ||
	    CASE
	      WHEN p.street_type IS NOT NULL AND p.street_type <> ''
	           AND p.post_dir IS NOT NULL AND p.post_dir <> ''
	      THEN ' '
	      ELSE ''
	    END || 
	    COALESCE(p.post_dir || ' ', '') AS address,
            COALESCE(' ' || p.unit_type, '') || COALESCE(' ' || p.unit_num, '') AS apartment,
            p.full_township AS township, p.precinct AS precinct, p.city_council_ward AS ward, p.county_supervisor AS supervisor, p.voterstatus AS voterstatus, p.regn_num AS voterid, p.strong_voter AS strong_voter, p.young_strong_voter AS young_strong_voter, p.needs_ride_to_poll AS needs_ride_to_poll, p.township_trustee_clerk AS township_trustee_or_clerk, p.neighborhood_member_level as neighborhood_member_level
         FROM persons4 p
         WHERE p.latitude BETWEEN \$1 AND \$2
         AND p.longitude BETWEEN \$3 AND \$4
         AND p.latitude IS NOT NULL
         AND p.longitude IS NOT NULL
         AND p.party = ANY(\$5)
         AND (%s = ANY(string_to_array(\$6, ',')) OR \$6 = 'all' OR \$6 = '')",pg_escape_identifier($conn,$target_field)
    );

    if ($includeNeighborhoods) {
        // Add JOIN and filter for known neighborhoods
	$query1 = sprintf(
	    "SELECT p.latitude, p.longitude, p.first_name, p.last_name, p.party,
	        p.house_num || COALESCE(' ' || p.house_suffix, '') || ' ' ||
	        COALESCE(p.pre_dir || ' ', '') || p.street_name || ' ' || COALESCE(p.street_type, '') || COALESCE(p.post_dir || ' ', '') AS address,
	        COALESCE(' ' || p.unit_type, '') || COALESCE(' ' || p.unit_num, '') AS apartment,
	        p.full_township AS township, p.precinct AS precinct, p.city_council_ward AS ward, p.county_supervisor AS supervisor, p.voterstatus AS voterstatus, p.regn_num AS voterid, p.strong_voter AS strong_voter, p.young_strong_voter AS young_strong_voter, p.needs_ride_to_poll AS needs_ride_to_poll, p.township_trustee_clerk AS township_trustee_or_clerk, p.neighborhood_member_level as neighborhood_member_level
	     FROM persons4 p
	     JOIN addresses a
	       ON a.addno_full = (
	            p.house_num || 
	            CASE 
	              WHEN p.house_suffix IS NOT NULL AND p.house_suffix <> '' 
	                THEN ' ' || p.house_suffix 
	              ELSE '' 
	            END
	         )
	       AND a.st_name = TRIM(p.street_name)
	       AND (p.pre_dir IS NULL OR p.pre_dir = '' OR a.st_predir = p.pre_dir)
	       AND (p.post_dir IS NULL OR p.post_dir = '' OR a.st_posdir = p.post_dir)
	       AND (p.unit_type IS NULL OR p.unit_type = '' OR a.unit_type = p.unit_type)
	       AND (p.unit_num IS NULL OR p.unit_num = '' OR a.unit_num = p.unit_num)
	       AND (TRIM(UPPER(p.street_type)) = TRIM(UPPER(a.st_postyp)))
	     WHERE p.latitude BETWEEN \$1 AND \$2
	       AND p.longitude BETWEEN \$3 AND \$4
	       AND p.latitude IS NOT NULL
	       AND p.longitude IS NOT NULL
	       AND p.party = ANY(\$5)
	       AND (\$6 = '' OR \$6 = 'all' OR p.%s = ANY(string_to_array(\$6, ',')))
	       AND a.neighborhood IS NOT NULL AND TRIM(a.neighborhood) <> ''",
	    pg_escape_identifier($conn, $target_field)
	);
    }

    $query1 .= " ORDER BY p.longitude, p.latitude";

    $result1 = pg_query_params($conn, $query1, $params1);

    if (!$result1) {
	error_log('PG error: ' . pg_last_error($conn));
        throw new Exception("Query 1 failed: " . pg_last_error($conn));
    }

    while ($row = pg_fetch_assoc($result1)) {
        $markers[] = [
            'latitude' => floatval($row['latitude']),
            'longitude' => floatval($row['longitude']),
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'party' => trim($row['party']),
            'address' => $row['address'],
            'apartment' => $row['apartment'],
            'township' => $row['township'],
            'precinct' => $row['precinct'] ?? '',
            'ward' => $row['ward'] ?? '', 
            'supervisor' => $row['supervisor'] ?? '', 
            'voterstatus' => $row['voterstatus'],
            'voterid' => $row['voterid'],
            'strong_voter' => $row['strong_voter'],
            'young_strong_voter' => $row['young_strong_voter'],
            'needs_ride_to_poll' => $row['needs_ride_to_poll'],
            'township_trustee_or_clerk' => $row['township_trustee_or_clerk'],
            'neighborhood_member_level' => $row['neighborhood_member_level']
        ];
    }
    pg_free_result($result1);

    $params2 = [$lat_south, $lat_north, $lng_west, $lng_east, $pgTownshipArray];
    $params2 = [$lat_south, $lat_north, $lng_west, $lng_east, $areaString];

    if (in_array('NOT REGISTERED', $parties)) {

	$query2 = sprintf(
	    "SELECT a.latitude, a.longitude, a.oid_,
	    --    a.addno_full || ' ' || 
		--COALESCE(a.st_predir || ' ', '') || 
		--a.st_name || ' ' || 
		--COALESCE(a.st_postyp, '') ||
		--CASE
		--  WHEN a.st_postyp IS NOT NULL AND a.st_postyp <> ''
		--       AND a.st_posdir IS NOT NULL AND a.st_posdir <> ''
		--  THEN ' '
		--  ELSE ''
		--END ||
		--COALESCE(a.st_posdir || ' ', '') AS address,
            a.addno_full || ' ' ||
        COALESCE(NULLIF(a.st_predir, '') || ' ', '') ||
        a.st_name || ' ' ||
        COALESCE(a.st_postyp, '') ||
        CASE
            WHEN a.st_postyp IS NOT NULL AND a.st_postyp <> ''
                AND a.st_posdir IS NOT NULL AND a.st_posdir <> ''
            THEN ' '
        ELSE ''
        END ||
        COALESCE(NULLIF(a.st_posdir, '') || ' ', '') AS address,
	        COALESCE(' ' || a.unit_type, '') || COALESCE(' ' || a.unit_num, '') AS apartment,
	        a.full_township AS township, a.precinct AS precinct, a.city_council_ward AS ward, a.county_supervisor AS supervisor
	     FROM addresses a
            LEFT JOIN persons4 p 
            ON a.addno_full = (
                p.house_num || COALESCE(
                    CASE WHEN NULLIF(p.house_suffix, '') IS NOT NULL THEN
                        ' ' || p.house_suffix
                    ELSE ''
                    END,
                    ''
                )
            )
            AND a.st_name = TRIM(p.street_name)
            AND (p.pre_dir IS NULL OR p.pre_dir = '' OR a.st_predir = p.pre_dir)
            AND (p.post_dir IS NULL OR p.post_dir = '' OR a.st_posdir = p.post_dir)
            AND (
                (a.unit_num IS NULL AND (p.unit_num IS NULL OR p.unit_num = ''))
                OR (a.unit_num IS NOT NULL AND a.unit_num = p.unit_num)
            )
            AND (p.street_type IS NULL OR p.street_type = '' OR a.st_postyp = p.street_type)
	     WHERE p.house_num IS NULL
	       AND a.latitude BETWEEN \$1 AND \$2 
	       AND a.longitude BETWEEN \$3 AND \$4 
	       AND a.latitude IS NOT NULL 
	       AND a.longitude IS NOT NULL
	       AND (\$5 = '' OR \$5 = 'all' OR a.%s = ANY(string_to_array(\$5, ',')))
	       AND (a.non_residence_entity IS NULL OR TRIM(a.non_residence_entity) = '')",
	    pg_escape_identifier($conn, $target_field)
	);

	if ($includeNeighborhoods) {
	    $query2 .= " AND a.neighborhood IS NOT NULL AND TRIM(a.neighborhood) <> ''";
	}

	$query2 .= " ORDER BY p.longitude, p.latitude";

    $result2 = pg_query_params($conn, $query2, $params2);

    if (!$result2) {
        throw new Exception("Query 2 failed: " . pg_last_error($conn));
    }

    while ($row = pg_fetch_assoc($result2)) {
        $markers[] = [
            'latitude' => floatval($row['latitude']),
            'longitude' => floatval($row['longitude']),
            'first_name' => '',
            'last_name' => '',
            'party' => 'NOT REGISTERED',
            'address' => $row['address'],
	        'apartment' => $row['apartment'],
            'township' => $row['township'],
            'precinct' => $row['precinct'] ?? '',
            'ward' => $row['ward'] ?? '',
            'supervisor' => $row['supervisor'] ?? '',
	        'voterstatus' => '',
	        'voterid' => $row['oid_'],
	        'strong_voter' => '',
            'young_strong_voter' => '',
            'needs_ride_to_poll' => '',
            'township_trustee_or_clerk' => '',
            'neighborhood_member_level' => ''
        ];
    }

    pg_free_result($result2);
    
    }

    echo json_encode([
        'success' => true,
        'count' => count($markers),
        'markers' => $markers
    ]);

} catch (Exception $e) {
    logError($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
