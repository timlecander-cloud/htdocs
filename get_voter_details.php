<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db_connection.php';

$pdo = getDbConnection();

$voter_id = $_GET['regn_num'] ?? null;
if (!$voter_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing voter ID']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM persons4 WHERE regn_num = ?");
$stmt->execute([$voter_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    http_response_code(404);
    echo json_encode(['error' => 'Voter not found']);
    exit;
}

// Fetch election history
$historyStmt = $pdo->prepare("
    SELECT General_Election_Year, General_Election_Method 
    FROM voter_election_history 
    WHERE regn_num = ?
    ORDER BY General_Election_Year DESC
");
$historyStmt->execute([$voter_id]);
$historyRows = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Concatenate history into a string
$historyString = '';
foreach ($historyRows as $row) {
    $year = $row['general_election_year'];
    $method = $row['general_election_method'];
    $historyString .= "$year ($method), ";
}
$historyString = rtrim($historyString, ', '); // remove trailing comma

// Add to response
$data['general_election_history'] = $historyString;

echo json_encode($data);
?>
