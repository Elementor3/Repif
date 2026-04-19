<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/measurement_insert.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'error' => 'Method Not Allowed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = $_POST;
}

$result = insertMeasurement($conn, $data);

http_response_code((int)$result['status']);
if (!empty($data['from_form'])) {
    $redirect = '/user/manual_sender.php';
    if (!empty($result['ok'])) {
        header('Location: ' . $redirect . '?ok=1&id=' . urlencode((string)$result['measurement_id']));
    } else {
        header('Location: ' . $redirect . '?error=' . urlencode((string)$result['error']));
    }
    exit;
}

if (!empty($result['ok'])) {
    echo json_encode([
        'status' => 'ok',
        'measurement_id' => $result['measurement_id'],
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'error' => $result['error'],
    ]);
}