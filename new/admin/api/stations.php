
<?php
// admin/api/stations.php

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../services/stations.php';

requireAdmin(); // only admin

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$redirectBase = '/admin/panel.php?tab=stations';

// Current page (from hidden input)
$currentPage = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($currentPage < 1) {
    $currentPage = 1;
}

function admin_stations_redirect_error(string $base, string $message, int $page): void
{
    $msg = urlencode($message);
    $extra = "&error={$msg}";
    if ($page > 1) {
        $extra .= "&page={$page}";
    }

    header("Location: {$base}{$extra}");
    exit;
}

try {
    $action = $_POST['action'] ?? '';

    switch ($action) {

        case 'admin.station.create': {
            $serial = $_POST['serialNumber'] ?? '';
            $name   = $_POST['name'] ?? '';
            $desc   = $_POST['description'] ?? '';

            $createdBy = $_SESSION['username'] ?? null;
            if (!$createdBy) {
                throw new RuntimeException("No admin username in session.");
            }

            $ok = svc_adminCreateStation($conn, $serial, $name, $desc, $createdBy);
            if (!$ok) {
                admin_stations_redirect_error($redirectBase, "Unexpected error. Operation could not be completed.", $currentPage);
            }

            $extra = '&created=1';
            if ($currentPage > 1) {
                $extra .= "&page={$currentPage}";
            }
            header("Location: {$redirectBase}{$extra}");
            exit;
        }

        case 'admin.station.update': {
            $serial = $_POST['serialNumber'] ?? '';
            $name   = $_POST['name'] ?? '';
            $desc   = $_POST['description'] ?? '';
            $owner  = $_POST['registeredBy'] ?? null;
            if ($owner === '') {
                $owner = null;
            }

            $ok = svc_adminUpdateStation($conn, $serial, $name, $desc, $owner);
            if (!$ok) {
                admin_stations_redirect_error($redirectBase, "Unexpected error. Operation could not be completed.", $currentPage);
            }

            $extra = '&updated=1';
            if ($currentPage > 1) {
                $extra .= "&page={$currentPage}";
            }
            header("Location: {$redirectBase}{$extra}");
            exit;
        }

        case 'admin.station.delete': {
            $serial = $_POST['serialNumber'] ?? '';

            $ok = svc_adminDeleteStation($conn, $serial);
            if (!$ok) {
                admin_stations_redirect_error($redirectBase, "Unexpected error. Operation could not be completed.", $currentPage);
            }

            $extra = '&deleted=1';
            if ($currentPage > 1) {
                $extra .= "&page={$currentPage}";
            }
            header("Location: {$redirectBase}{$extra}");
            exit;
        }

        default:
            throw new RuntimeException("Unknown action.");
    }

} catch (RuntimeException $e) {
    admin_stations_redirect_error($redirectBase, $e->getMessage(), $currentPage);
}
