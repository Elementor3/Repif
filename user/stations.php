
<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();

// ===================== LOAD DATA ======================
$stmt = mysqli_prepare(
    $conn,
    "SELECT pk_serialNumber, name, description, createdAt, registeredAt
     FROM station
     WHERE fk_registeredBy = ?
     ORDER BY name, pk_serialNumber"
);
mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($stmt);
$stations = mysqli_stmt_get_result($stmt);

$pageTitle = 'My Stations';
require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-12">

        <h2>My Stations</h2>

        <!-- SUCCESS & ERROR MESSAGES -->
        <?php if (isset($_GET['registered'])) echo showSuccess("Station registered successfully!"); ?>
        <?php if (isset($_GET['updated']))    echo showSuccess("Station updated successfully!"); ?>
        <?php if (isset($_GET['error']))      echo showError(e($_GET['error'])); ?>


        <!-- ================= REGISTER NEW STATION ================= -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Register New Station</h5>
            </div>
            <div class="card-body">

                <form method="POST" action="/api/stations.php" class="row g-3">
                    <input type="hidden" name="action" value="station.register">

                    <div class="col-md-8">
                        <label for="serial_number" class="form-label">Station Serial Number</label>
                        <input type="text" class="form-control" id="serial_number" name="serial_number"
                               placeholder="e.g., ST001" required>
                        <small class="text-muted">Enter the serial number from your purchased station.</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            Register Station
                        </button>
                    </div>
                </form>

            </div>
        </div>


        <!-- ================== STATIONS LIST ================== -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Registered Stations</h5>
            </div>

            <div class="card-body">

                <?php if (mysqli_num_rows($stations) === 0): ?>

                    <p class="text-muted">You have no registered stations yet. Register one using the form above.</p>

                <?php else: ?>

                    <div class="table-responsive">
                        <table class="table table-striped align-middle">

                            <thead>
                                <tr>
                                    <th>Serial Number</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Registered At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php mysqli_data_seek($stations, 0); ?>
                                <?php while ($st = mysqli_fetch_assoc($stations)): ?>
                                    <tr>
                                        <td><strong><?php echo e($st['pk_serialNumber']); ?></strong></td>
                                        <td><?php echo e($st['name'] ?: '-'); ?></td>
                                        <td><?php echo e(substr($st['description'] ?: '-', 0, 50)); ?></td>
                                        <td><?php echo formatDateTime($st['registeredAt']); ?></td>
                                        <td class="table-actions">

                                            <!-- Edit button -->
                                            <button class="btn btn-sm btn-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editStation_<?php echo e($st['pk_serialNumber']); ?>">
                                                Edit
                                            </button>

                                        </td>
                                    </tr>
                                <?php endwhile; ?>

                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

            </div>
        </div>

    </div>
</div>


<!-- ============================================================= -->
<!-- =================== MODALS (OUTSIDE TABLE) =================== -->
<!-- ============================================================= -->

<?php mysqli_data_seek($stations, 0); ?>
<?php while ($st = mysqli_fetch_assoc($stations)): ?>

<div class="modal fade" id="editStation_<?php echo e($st['pk_serialNumber']); ?>" tabindex="-1"
     aria-labelledby="editStationLabel_<?php echo e($st['pk_serialNumber']); ?>" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

        <form method="POST" action="/api/stations.php">
            <input type="hidden" name="action" value="station.update">
            <input type="hidden" name="serial_number" value="<?php echo e($st['pk_serialNumber']); ?>">

            <div class="modal-header">
                <h5 class="modal-title" id="editStationLabel_<?php echo e($st['pk_serialNumber']); ?>">
                    Edit Station #<?php echo e($st['pk_serialNumber']); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" value="<?php echo e($st['name']); ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="3"><?php echo e($st['description']); ?></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-primary" type="submit">Save Changes</button>
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
            </div>

        </form>

    </div>
  </div>
</div>

<?php endwhile; ?>


<?php require_once '../includes/footer.php'; ?>
