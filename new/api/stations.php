
<?php
// /api/stations.php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../services/stations.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

try {
    $action = $_POST['action'] ?? '';

    switch ($action) {

        // Зарегистрировать станцию на себя
        case 'station.register': {
            $serial = trim($_POST['serial_number'] ?? '');
            svc_userRegisterStation($conn, $serial, $_SESSION['username']);
            header('Location: /user/stations.php?registered=1');
            exit;
        }

        // Обновить name/description своей станции
        case 'station.update': {
            $serial = trim($_POST['serial_number'] ?? '');
            $name   = trim($_POST['name'] ?? '');
            $desc   = trim($_POST['description'] ?? '');
            svc_userUpdateStation($conn, $serial,$_SESSION['username'], $name, $desc);
            header('Location: /user/stations.php?updated=1');
            exit;
        }

        default:
            throw new RuntimeException('Unknown action');
    }

} catch (Throwable $e) {
    header('Location: /user/stations.php?error=' . urlencode($e->getMessage()));
    exit;
}
