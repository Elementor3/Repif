<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/stations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = [];
}
$payload = array_merge($payload, $_POST);

$serial = trim((string)($payload['serial'] ?? ''));
if ($serial === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing serial']);
    exit;
}

$result = requestStationRegistrationCode($conn, $serial, 300);
if (!$result) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Station not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => $result,
]);
