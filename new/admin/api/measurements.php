
<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../services/measurements.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Detect AJAX (jQuery sends this header automatically)
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

// These are used only for non-AJAX fallback redirect:
$filterStation = $_POST['filter_station'] ?? '';
$filterStart   = $_POST['filter_start'] ?? '';
$filterEnd     = $_POST['filter_end'] ?? '';
$page          = isset($_POST['page']) ? (int)$_POST['page'] : 1;

/*
 * Build redirect URL (tab + filters + page preserved).
 */
function buildMeasurementsRedirect(
    string $suffix,
    string $filterStation,
    string $filterStart,
    string $filterEnd,
    int $page
): string {
    $qs = 'tab=measurements';

    if ($filterStation !== '') {
        $qs .= '&filter_station=' . urlencode($filterStation);
    }
    if ($filterStart !== '') {
        $qs .= '&filter_start=' . urlencode($filterStart);
    }
    if ($filterEnd !== '') {
        $qs .= '&filter_end=' . urlencode($filterEnd);
    }
    if ($page > 1) {
        $qs .= '&page=' . $page;
    }

    if ($suffix !== '') {
        $qs .= '&' . ltrim($suffix, '&');
    }

    return '/admin/panel.php?' . $qs;
}

try {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'admin.measurement.delete':
            $id = isset($_POST['measurement_id']) ? (int)$_POST['measurement_id'] : 0;
            svc_adminDeleteMeasurement($conn, $id);

            if ($isAjax) {
                header('Content-Type: text/plain; charset=utf-8');
                echo 'OK';
                exit;
            }

            header('Location: ' . buildMeasurementsRedirect('deleted=1', $filterStation, $filterStart, $filterEnd, $page));
            exit;

        default:
            throw new RuntimeException('Unknown measurement admin action: ' . $action);
    }

} catch (Throwable $e) {
    if ($isAjax) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'ERROR: ' . $e->getMessage();
        exit;
    }

    $msg = urlencode($e->getMessage());
    header('Location: ' . buildMeasurementsRedirect('error=' . $msg, $filterStation, $filterStart, $filterEnd, $page));
    exit;
}
