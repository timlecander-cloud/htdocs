<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db_connection.php';

$conn = getDbConnection(); // should return a pgsql connection resource

// Use error_log() to write to the server's error log:
$voter_id = $_GET['regn_num'] ?? null;
if (!$voter_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing voter ID']);
    exit;
}

//error_log("Debug: voter ID is $voter_id");

//// Prepare and execute voter details query
//pg_prepare($conn, "get_voter", "SELECT * FROM persons4 WHERE regn_num = $1");
//$result = pg_execute($conn, "get_voter", [$voter_id]);
//
//$data = pg_fetch_assoc($result);
//if (!$data) {
//    http_response_code(404);
//    echo json_encode(['error' => 'Voter not found']);
//    exit;
//}
//
//error_log("Voter data: " . print_r($data, true));

if (!preg_match('/^\d+$/', $voter_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid voter ID']);
    exit;
}

pg_prepare($conn, "get_voter", "SELECT * FROM persons4 WHERE regn_num = $1");
$result = pg_execute($conn, "get_voter", [$voter_id]);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed']);
    exit;
}

$data = pg_fetch_assoc($result);
if (!$data) {
    http_response_code(404);
    echo json_encode(['error' => 'Voter not found']);
    exit;
}

// Prepare and execute election history query
pg_prepare($conn, "get_history", "
    SELECT General_Election_Year, General_Election_Method 
    FROM voter_election_history 
    WHERE regn_num = $1
    ORDER BY General_Election_Year DESC
");
$historyResult = pg_execute($conn, "get_history", [$voter_id]);

$historyString = '';
while ($row = pg_fetch_assoc($historyResult)) {
    $year = $row['general_election_year'];
    $method = $row['general_election_method'];
    $historyString .= "$year ($method), ";
}
$historyString = rtrim($historyString, ', ');

// Add to response
$data['general_election_history'] = $historyString;

echo json_encode($data);
?>
