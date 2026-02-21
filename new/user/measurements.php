<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();

// Get user's stations for dropdown
$stmt = mysqli_prepare($conn,
    "SELECT pk_serialNumber, name FROM station WHERE fk_registeredBy = ? ORDER BY name"
);
mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($stmt);
$userStations = mysqli_stmt_get_result($stmt);

$pageTitle = 'Measurements';
require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h2>View Measurements</h2>
        
        <div id="alert-container"></div>
        
        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Filter Measurements</h5>
            </div>
            <div class="card-body">
                <form id="filterForm" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Select Station *</label>
                        <select class="form-select" id="station" required>
                            <option value="">-- Select Station --</option>
                            <?php while ($station = mysqli_fetch_assoc($userStations)): ?>
                                <option value="<?= htmlspecialchars($station['pk_serialNumber']) ?>">
                                    <?= htmlspecialchars($station['name'] ?: $station['pk_serialNumber']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Start Date/Time</label>
                        <input type="datetime-local" class="form-control" id="start_date">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">End Date/Time</label>
                        <input type="datetime-local" class="form-control" id="end_date">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">View Data</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Results -->
        <div class="card" id="results-card" style="display: none;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Measurement Data</h5>
                <small class="text-muted" id="pagination-info"></small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Temp (°C)</th>
                                <th>Humidity (%)</th>
                                <th>Pressure (hPa)</th>
                                <th>Light (lux)</th>
                                <th>Air Quality (ppm)</th>
                            </tr>
                        </thead>
                        <tbody id="measurements-container">
                            <tr><td colspan="6" class="text-center">No data</td></tr>
                        </tbody>
                    </table>
                </div>
                
                <nav id="pagination" style="display: none;">
                    <ul class="pagination justify-content-end mb-0">
                        <li class="page-item" id="prev-page">
                            <a class="page-link" href="#">&laquo;</a>
                        </li>
                        <li class="page-item" id="next-page">
                            <a class="page-link" href="#">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<script>
var currentPage = 1;
var totalPages = 1;
var currentStation = '';
var currentStart = '';
var currentEnd = '';

$(document).ready(function() {
    $('#filterForm').submit(function(e) {
        e.preventDefault();
        currentPage = 1;
        loadMeasurements();
    });
    
    $('#prev-page a').click(function(e) {
        e.preventDefault();
        if (currentPage > 1) {
            currentPage--;
            loadMeasurements();
        }
    });
    
    $('#next-page a').click(function(e) {
        e.preventDefault();
        if (currentPage < totalPages) {
            currentPage++;
            loadMeasurements();
        }
    });
});

function loadMeasurements() {
    var station = $('#station').val();
    if (!station) {
        showAlert('danger', 'Please select a station');
        return;
    }
    
    currentStation = station;
    currentStart = $('#start_date').val();
    currentEnd = $('#end_date').val();
    
    $.get('/api/measurements.php', {
        station: station,
        start: currentStart,
        end: currentEnd,
        page: currentPage
    }, function(response) {
        if (response.error) {
            showAlert('danger', response.error);
            return;
        }
        
        $('#measurements-container').html(response.html);
        $('#pagination-info').text('Page ' + currentPage + ' of ' + response.pages + ' (Total: ' + response.total + ')');
        
        totalPages = response.pages;
        if (totalPages > 1) {
            $('#pagination').show();
            $('#prev-page').toggleClass('disabled', currentPage <= 1);
            $('#next-page').toggleClass('disabled', currentPage >= totalPages);
        } else {
            $('#pagination').hide();
        }
        
        $('#results-card').show();
    }, 'json').fail(function() {
        showAlert('danger', 'Failed to load measurements');
    });
}

function showAlert(type, message) {
    var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                    escapeHtml(message) +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                    '</div>';
    $('#alert-container').html(alertHtml);
    
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}

function escapeHtml(text) {
    return $('<div>').text(text).html();
}
</script>

<?php require_once '../includes/footer.php'; ?>