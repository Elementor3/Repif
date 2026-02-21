<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Shared Collections';

// Load shared collections
$stmt = mysqli_prepare($conn,
    "SELECT c.pk_collectionID, c.name, c.description, c.fk_user AS owner, c.createdAt,
            (SELECT COUNT(*) FROM contains WHERE pkfk_collection = c.pk_collectionID) AS measurement_count
     FROM collection c
     JOIN shares s ON c.pk_collectionID = s.pk_collection
     WHERE s.pk_user = ?
     ORDER BY c.createdAt DESC"
);
mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($stmt);
$sharedCollections = mysqli_stmt_get_result($stmt);

require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h2>Collections Shared With Me</h2>
        <p class="text-muted">View measurement collections that your friends have shared with you.</p>
        
        <div id="alert-container"></div>

        <?php if (mysqli_num_rows($sharedCollections) === 0): ?>
            <div class="alert alert-info">
                No collections have been shared with you yet.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Collection Name</th>
                                    <th>Owner</th>
                                    <th>Description</th>
                                    <th>Measurements</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($col = mysqli_fetch_assoc($sharedCollections)): ?>
                                    <tr id="collection-<?= $col['pk_collectionID'] ?>">
                                        <td><strong><?= htmlspecialchars($col['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($col['owner']) ?></td>
                                        <td><?= htmlspecialchars(substr($col['description'] ?: '-', 0, 50)) ?></td>
                                        <td><?= $col['measurement_count'] ?></td>
                                        <td><?= formatDateTime($col['createdAt']) ?></td>
                                        <td class="table-actions">
                                            <button class="btn btn-sm btn-info view-collection" 
                                                    data-id="<?= $col['pk_collectionID'] ?>"
                                                    data-name="<?= htmlspecialchars($col['name']) ?>"
                                                    data-owner="<?= htmlspecialchars($col['owner']) ?>">
                                                View
                                            </button>
                                            <button class="btn btn-sm btn-danger unshare-collection"
                                                    data-id="<?= $col['pk_collectionID'] ?>"
                                                    data-name="<?= htmlspecialchars($col['name']) ?>">
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Measurements Modal -->
<div class="modal fade" id="viewMeasurementsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="measurements-loading" class="text-center">
                    <div class="spinner-border"></div>
                </div>
                <div id="measurements-content" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Temp</th>
                                    <th>Humidity</th>
                                    <th>Pressure</th>
                                    <th>Light</th>
                                    <th>Quality</th>
                                </tr>
                            </thead>
                            <tbody id="measurements-list"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.view-collection').click(function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var owner = $(this).data('owner');
        
        $('#modalTitle').text(name + ' (by ' + owner + ')');
        $('#measurements-loading').show();
        $('#measurements-content').hide();
        $('#measurements-list').empty();
        
        $.get('/api/collections_view.php', {id: id}, function(html) {
            $('#measurements-loading').hide();
            $('#measurements-list').html(html);
            $('#measurements-content').show();
        }).fail(function() {
            $('#measurements-loading').hide();
            $('#measurements-list').html('<tr><td colspan="6" class="text-danger">Failed to load measurements</td></tr>');
            $('#measurements-content').show();
        });
        
        $('#viewMeasurementsModal').modal('show');
    });
    
    $('.unshare-collection').click(function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        
        if (!confirm('Remove "' + name + '" from your shared collections?')) {
            return;
        }
        
        $.post('/api/collections.php', {
            action: 'collection.unshare_self',
            collection_id: id
        }, function(response) {
            var parts = response.split('|');
            if (parts[0] === 'OK') {
                $('#collection-' + id).fadeOut();
                showAlert('success', parts[1]);
            } else {
                showAlert('danger', parts[1]);
            }
        });
    });
});

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