<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../services/stations.php';
require_once __DIR__ . '/../../services/users.php';

function buildAbsoluteUrl(string $path): string {
    $path = '/' . ltrim($path, '/');
    $proto = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
        ? strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO'])
        : (((string)($_SERVER['HTTPS'] ?? '') !== '' && (string)$_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'));
    $host = (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return $path;
    }
    return $proto . '://' . $host . $path;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
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
$payload = array_merge($payload, $_GET, $_POST);

$serial = trim((string)($payload['serial'] ?? ''));
if ($serial === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing serial']);
    exit;
}

$ownership = getActiveStationOwnershipBySerial($conn, $serial);
$registered = $ownership !== null;

$response = [
    'success' => true,
    'registered' => $registered,
];

if ($registered && $ownership !== null) {
    $username = (string)($ownership['fk_ownerId'] ?? $ownership['fk_registeredBy'] ?? '');
    if ($username !== '') {
        $user = getUserByUsername($conn, $username);
        if ($user !== null) {
            $avatarPath = getAvatarUrl((string)($user['avatar'] ?? ''), $username);
            $response['owner'] = [
                'username' => $username,
                'avatar' => $avatarPath ? buildAbsoluteUrl($avatarPath) : ''
            ];
        }
    }
}

echo json_encode($response);
