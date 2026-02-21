
<?php
require_once __DIR__ . '/../../services/measurements.php';

// Filters from GET
$filterStation    = $_GET['filter_station'] ?? '';
$filterStartLocal = $_GET['filter_start'] ?? ''; // datetime-local format
$filterEndLocal   = $_GET['filter_end'] ?? '';   // datetime-local format

// Page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

$perPage = 20;

// Convert datetime-local to MySQL DATETIME using existing helper
$startMySQL = $filterStartLocal !== '' ? convertToMySQLDateTime($filterStartLocal) : null;
$endMySQL   = $filterEndLocal   !== '' ? convertToMySQLDateTime($filterEndLocal)   : null;

// Load stations for the filter dropdown
$filterStations = mysqli_query(
    $conn,
    "SELECT pk_serialNumber, name FROM station ORDER BY name, pk_serialNumber"
);

$loadError   = '';
$measurements = [];
$totalCount  = 0;
$totalPages  = 1;

try {
    // Total count for pagination
    $totalCount = svc_adminCountMeasurements(
        $conn,
        $filterStation !== '' ? $filterStation : null,
        $startMySQL,
        $endMySQL
    );

    if ($totalCount < 0) {
        $totalCount = 0;
    }

    $totalPages = max(1, (int)ceil($totalCount / $perPage));

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;

    // Measurements for current page
    $measurements = svc_adminGetMeasurements(
        $conn,
        $filterStation !== '' ? $filterStation : null,
        $startMySQL,
        $endMySQL,
        $perPage,
        $offset
    );
} catch (Throwable $e) {
}

// Base query for pagination links
$queryBase = 'tab=measurements';
if ($filterStation !== '') {
    $queryBase .= '&filter_station=' . urlencode($filterStation);
}
if ($filterStartLocal !== '') {
    $queryBase .= '&filter_start=' . urlencode($filterStartLocal);
}
if ($filterEndLocal !== '') {
    $queryBase .= '&filter_end=' . urlencode($filterEndLocal);
}
?>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0">Filter Measurements</h5>
    </div>
    <div class="card-body">

        <?php if ($loadError !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo e($loadError); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="GET" action="">
            <input type="hidden" name="tab" value="measurements">

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="filter_station" class="form-label">Station</label>
                    <select class="form-select" id="filter_station" name="filter_station">
                        <option value="">All Stations</option>
                        <?php if ($filterStations): ?>
                            <?php while ($st = mysqli_fetch_assoc($filterStations)): ?>
                                <?php
                                $serial = $st['pk_serialNumber'];
                                $name   = $st['name'] ?: $serial;
                                ?>
                                <option value="<?php echo e($serial); ?>"
                                    <?php echo ($filterStation === $serial) ? 'selected' : ''; ?>>
                                    <?php echo e($name); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="filter_start" class="form-label">Start Date/Time</label>
                    <input
                        type="datetime-local"
                        class="form-control"
                        id="filter_start"
                        name="filter_start"
                        value="<?php echo e($filterStartLocal); ?>"
                    >
                </div>

                <div class="col-md-3">
                    <label for="filter_end" class="form-label">End Date/Time</label>
                    <input
                        type="datetime-local"
                        class="form-control"
                        id="filter_end"
                        name="filter_end"
                        value="<?php echo e($filterEndLocal); ?>"
                    >
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-flex w-100 gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            Filter
                        </button>
                        <a href="/admin/panel.php?tab=measurements" class="btn btn-outline-secondary flex-fill">
                            Reset
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Measurement Data</h5>
        <small class="text-muted">
            Total: <?php echo (int)$totalCount; ?> | Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?>
        </small>
    </div>
    <div class="card-body">

        <div id="measurementsAlertContainer"></div>

        <?php if (empty($measurements)): ?>
            <p class="text-muted">No measurements found for the selected filters.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Station</th>
                            <th>Timestamp</th>
                            <th>Temp (°C)</th>
                            <th>Humidity (%)</th>
                            <th>Pressure (hPa)</th>
                            <th>Light (lux)</th>
                            <th>Air quality (ppm)</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($measurements as $m): ?>
                            <tr id="measurement-row-<?php echo e($m['pk_measurementID']); ?>">
                                <td><?php echo e($m['fk_station']); ?></td>
                                <td><?php echo formatDateTime($m['timestamp']); ?></td>
                                <td><?php echo e($m['temperature']); ?></td>
                                <td><?php echo e($m['humidity']); ?></td>
                                <td><?php echo e($m['airPressure']); ?></td>
                                <td><?php echo e($m['lightIntensity']); ?></td>
                                <td><?php echo e($m['airQuality']); ?></td>
                                <td class="table-actions text-end">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-danger btn-delete-measurement"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteMeasurementModal"
                                        data-id="<?php echo e($m['pk_measurementID']); ?>"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav aria-label="Measurements pagination" class="mt-3">
                    <ul class="pagination justify-content-end mb-0">

                        <!-- Previous page -->
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <?php if ($page > 1): ?>
                                <a class="page-link" href="?<?php echo $queryBase; ?>&page=<?php echo $page - 1; ?>">&laquo;</a>
                            <?php else: ?>
                                <span class="page-link">&laquo;</span>
                            <?php endif; ?>
                        </li>

                        <?php
                        // If few pages - show all
                        if ($totalPages <= 7) {
                            for ($p = 1; $p <= $totalPages; $p++) {
                                if ($p === $page) {
                                    echo '<li class="page-item active"><span class="page-link">' . $p . '</span></li>';
                                } else {
                                    echo '<li class="page-item"><a class="page-link" href="?' . $queryBase . '&page=' . $p . '">' . $p . '</a></li>';
                                }
                            }
                        } else {
                            // Many pages: 1 ... window ... last
                            // 1
                            if ($page === 1) {
                                echo '<li class="page-item active"><span class="page-link">1</span></li>';
                            } else {
                                echo '<li class="page-item"><a class="page-link" href="?' . $queryBase . '&page=1">1</a></li>';
                            }

                            $window = 2;
                            $start  = max(2, $page - $window);
                            $end    = min($totalPages - 1, $page + $window);

                            if ($start > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }

                            for ($p = $start; $p <= $end; $p++) {
                                if ($p === $page) {
                                    echo '<li class="page-item active"><span class="page-link">' . $p . '</span></li>';
                                } else {
                                    echo '<li class="page-item"><a class="page-link" href="?' . $queryBase . '&page=' . $p . '">' . $p . '</a></li>';
                                }
                            }

                            if ($end < $totalPages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }

                            // last page
                            if ($totalPages === $page) {
                                echo '<li class="page-item active"><span class="page-link">' . $totalPages . '</span></li>';
                            } else {
                                echo '<li class="page-item"><a class="page-link" href="?' . $queryBase . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
                            }
                        }
                        ?>

                        <!-- Next page -->
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <?php if ($page < $totalPages): ?>
                                <a class="page-link" href="?<?php echo $queryBase; ?>&page=<?php echo $page + 1; ?>">&raquo;</a>
                            <?php else: ?>
                                <span class="page-link">&raquo;</span>
                            <?php endif; ?>
                        </li>

                    </ul>
                </nav>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- Delete Measurement Modal -->
<div class="modal fade" id="deleteMeasurementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- No action, will be handled by jQuery AJAX -->
            <form id="deleteMeasurementForm">
                <input type="hidden" name="measurement_id" id="deleteMeasurementId">

                <div class="modal-header">
                    <h5 class="modal-title">Delete Measurement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>
                        Are you sure you want to delete this measurement?
                        <br>
                        <span class="text-danger"><small>This action cannot be undone.</small></span>
                    </p>
                    <div id="deleteMeasurementError" class="alert alert-danger d-none"></div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit"
                            class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>


<style>
    /* Prevent column wrapping */
    .table td,
    .table th {
        white-space: nowrap;
    }

    /* Horizontal scroll at high zoom */
    .table-responsive {
        overflow-x: auto;
    }
</style>


<script>
$(function () {
    var deleteModal  = $('#deleteMeasurementModal');
    var deleteForm   = $('#deleteMeasurementForm');
    var errorBox     = $('#deleteMeasurementError');
    var alertContainer = $('#measurementsAlertContainer');
    var currentId    = null;

    // When modal opens - get measurement ID from button
    deleteModal.on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id     = button.data('id') || '';
        currentId  = id;
        $('#deleteMeasurementId').val(id);
        errorBox.addClass('d-none').text('');
    });

    // Handle delete form submit via AJAX (jQuery)
    deleteForm.on('submit', function (e) {
        e.preventDefault();

        if (!currentId) {
            return;
        }

        $.post('/admin/api/measurements.php', {
            action:         'admin.measurement.delete',
            measurement_id: currentId
        }, function (response) {
            // Plain text response, NOT JSON
            response = $.trim(response);

            if (response.indexOf('OK') === 0) {
                // Hide modal
                deleteModal.modal('hide');

                // Remove row from table
                $('#measurement-row-' + currentId).remove();

                // Show success alert
                var alertHtml =
                    '<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                    'Measurement deleted successfully.' +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                    '</div>';
                alertContainer.html(alertHtml);
            } else {
                // Show error inside modal
                errorBox.removeClass('d-none').text(response);
            }
        }).fail(function () {
            errorBox.removeClass('d-none').text('Server error while deleting measurement.');
        });
    });
});
</script>
