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

$token = trim((string)($payload['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing token']);
    exit;
}

$station = getStationByRegistrationToken($conn, $token);
if (!$station) {
    echo json_encode(['success' => true, 'status' => 'expired']);
    exit;
}

$serial = (string)($station['pk_serialNumber'] ?? '');
$isRegistered = !empty($station['is_registered']);
$expiresAtRaw = (string)($station['registration_expires_at'] ?? '');

$status = 'pending';
if ($isRegistered) {
    $status = 'registered';
} elseif ($expiresAtRaw !== '') {
    try {
        $expiresAt = new DateTimeImmutable($expiresAtRaw);
        if ($expiresAt <= new DateTimeImmutable('now')) {
            $status = 'expired';
        }
    } catch (Throwable $e) {
        $status = 'expired';
    }
}

if ($status !== 'pending' && $serial !== '') {
    $cleanup = $conn->prepare(
        "UPDATE station
         SET registration_code = NULL,
             registration_token = NULL,
             registration_requested_at = NULL,
             registration_expires_at = NULL
         WHERE pk_serialNumber = ?"
    );
    if ($cleanup) {
        $cleanup->bind_param('s', $serial);
        $cleanup->execute();
        $cleanup->close();
    }
}

$response = [
    'success' => true,
    'status' => $status,
];

// Add owner information for registered status
if ($status === 'registered' && $serial !== '') {
    $ownership = getActiveStationOwnershipBySerial($conn, $serial);
    if ($ownership !== null) {
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
}

echo json_encode($response);
