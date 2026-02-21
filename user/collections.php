
<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();
// --------------------- LOAD DATA ---------------------

$stmt = mysqli_prepare($conn,
    "SELECT c.pk_collectionID, c.name, c.description, c.createdAt,
           (SELECT COUNT(*) FROM contains WHERE pkfk_collection = c.pk_collectionID) AS measurement_count
     FROM collection c
     WHERE c.fk_user = ?
     ORDER BY c.createdAt DESC"
);
mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($stmt);
$collections = mysqli_stmt_get_result($stmt);

$userStations = getUserStations($conn, $_SESSION['username']);

$stmt = mysqli_prepare($conn,
    "SELECT pk_user2 AS friend_username
     FROM friendship WHERE pk_user1 = ?
     UNION
     SELECT pk_user1 AS friend_username
     FROM friendship WHERE pk_user2 = ?
     ORDER BY friend_username"
);
mysqli_stmt_bind_param($stmt, "ss", $_SESSION['username'], $_SESSION['username']);
mysqli_stmt_execute($stmt);
$friends = mysqli_stmt_get_result($stmt);

$pageTitle = "My Collections";
require_once "../includes/header.php";
?>

<!-- ====================== PAGE HTML ========================= -->

<div class="row">
    <div class="col-12">

        <h2>My Collections</h2>

        <?php if (isset($_GET['created'])) echo showSuccess("Collection created successfully!"); ?>
        <?php if (isset($_GET['updated'])) echo showSuccess("Collection updated successfully!"); ?>
        <?php if (isset($_GET['deleted'])) echo showSuccess("Collection deleted successfully!"); ?>
        <?php if (isset($_GET['shared'])) echo showSuccess("Collection shared!"); ?>
        <?php if (isset($_GET['unshared'])) echo showSuccess("Access removed!"); ?>

        <?php if (isset($_GET['error'])) echo showError(e($_GET['error'])); ?>



        <!-- CREATE COLLECTION CARD -->
        <div class="card mb-4">
            <div class="card-header"><h5>Create New Collection</h5></div>
            <div class="card-body">

                <form method="POST" action="/api/collections.php">
                    <input type="hidden" name="action" value="collection.create">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Collection Name *</label>
                            <input type="text" class="form-control" name="collection_name" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Select Station *</label>
                            <select class="form-select" name="station" required>
                                <option value="">-- Select Station --</option>
                                <?php mysqli_data_seek($userStations, 0); ?>
                                <?php while ($st = mysqli_fetch_assoc($userStations)): ?>
                                    <option value="<?php echo e($st['pk_serialNumber']); ?>">
                                        <?php echo e($st['name'] ?: $st['pk_serialNumber']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Start Date/Time *</label>
                            <input type="datetime-local" class="form-control" name="start_date" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">End Date/Time *</label>
                            <input type="datetime-local" class="form-control" name="end_date" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>

                        <div class="col-12">
                            <button class="btn btn-primary">Create Collection</button>
                        </div>

                    </div>
                </form>

            </div>
        </div>




        <!-- ===================== COLLECTIONS LIST ======================= -->

        <div class="card">
            <div class="card-header">
                <h5>My Collections</h5>
            </div>

            <div class="card-body">

                <?php if (mysqli_num_rows($collections) === 0): ?>
                    <p class="text-muted">You have no collections yet.</p>

                <?php else: ?>

                    <div class="table-responsive">
                        <table class="table table-striped">

                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Measurements</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php mysqli_data_seek($collections, 0); ?>
                                <?php while ($col = mysqli_fetch_assoc($collections)): ?>
                                    <tr>
                                        <td>#<?php echo e($col['pk_collectionID']); ?></td>
                                        <td><strong><?php echo e($col['name']); ?></strong></td>
                                        <td><?php echo e(substr($col['description'] ?: '-', 0, 50)); ?></td>
                                        <td><?php echo e($col['measurement_count']); ?></td>
                                        <td><?php echo formatDateTime($col['createdAt']); ?></td>

                                        <td class="table-actions">

                                            <button class="btn btn-sm btn-info"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewModal_<?php echo $col['pk_collectionID']; ?>">
                                                View
                                            </button>

                                            <button class="btn btn-sm btn-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editModal_<?php echo $col['pk_collectionID']; ?>">
                                                Edit
                                            </button>

                                            <button class="btn btn-sm btn-success"
                                                data-bs-toggle="modal"
                                                data-bs-target="#shareModal_<?php echo $col['pk_collectionID']; ?>">
                                                Share
                                            </button>

                                            <button class="btn btn-sm btn-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteModal_<?php echo $col['pk_collectionID']; ?>">
                                                Delete
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
<!-- ================== MODALS MOVED TO THE BOTTOM ================= -->
<!-- ============================================================= -->

<?php mysqli_data_seek($collections, 0); ?>
<?php while ($col = mysqli_fetch_assoc($collections)): ?>

<!-- VIEW MODAL -->
<div class="modal fade" id="viewModal_<?php echo $col['pk_collectionID']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    Collection: <?php echo e($col['name']); ?>
                    <small class="text-muted">(ID: <?php echo e($col['pk_collectionID']); ?>)</small>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <?php
                $stmt = mysqli_prepare($conn,
                    "SELECT m.timestamp, m.temperature, m.humidity, m.airPressure,
                            m.lightIntensity, m.airQuality
                     FROM measurement m
                     JOIN contains c ON m.pk_measurementID = c.pkfk_measurement
                     WHERE c.pkfk_collection = ?
                     ORDER BY m.timestamp DESC"
                );
                mysqli_stmt_bind_param($stmt, "i", $col['pk_collectionID']);
                mysqli_stmt_execute($stmt);
                $data = mysqli_stmt_get_result($stmt);
                ?>

                <?php if (mysqli_num_rows($data) === 0): ?>
                    <p class="text-muted">No measurements in this collection.</p>
                <?php else: ?>
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
                            <tbody>
                                <?php while ($m = mysqli_fetch_assoc($data)): ?>
                                    <tr>
                                        <td><?php echo formatDateTime($m['timestamp']); ?></td>
                                        <td><?php echo e($m['temperature']); ?></td>
                                        <td><?php echo e($m['humidity']); ?></td>
                                        <td><?php echo e($m['airPressure']); ?></td>
                                        <td><?php echo e($m['lightIntensity']); ?></td>
                                        <td><?php echo e($m['airQuality']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>



<!-- EDIT MODAL -->
<div class="modal fade" id="editModal_<?php echo $col['pk_collectionID']; ?>" tabindex="-1" aria-labelledby="editModalLabel_<?php echo $col['pk_collectionID']; ?>" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <form method="POST" action="/api/collections.php">
        <input type="hidden" name="action" value="collection.edit">
        <input type="hidden" name="collection_id" value="<?php echo $col['pk_collectionID']; ?>">

        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel_<?php echo $col['pk_collectionID']; ?>">
            Edit Collection
            <small class="text-muted">#<?php echo e($col['pk_collectionID']); ?></small>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">New Name *</label>
            <input
              type="text"
              name="new_name"
              class="form-control"
              value="<?php echo e($col['name']); ?>"
              required
            >
          </div>

          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea
              name="new_description"
              class="form-control"
              rows="3"
            ><?php echo e($col['description']); ?></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save changes</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>

      </form>

    </div>
  </div>
</div>
</div>
</div>



<!-- SHARE MODAL -->
<div class="modal fade" id="shareModal_<?php echo $col['pk_collectionID']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Share Collection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">


                <form method="POST" action="/api/collections.php" class="mb-3">
                    <input type="hidden" name="action" value="collection.share">
                    <input type="hidden" name="collection_id" value="<?php echo $col['pk_collectionID']; ?>">

                    <label class="form-label">Share with Friend</label>
                    <div class="input-group">

                        <select name="friend" class="form-select" required>
                            <option value="">-- Select Friend --</option>

                            <?php mysqli_data_seek($friends, 0); ?>
                            <?php while ($f = mysqli_fetch_assoc($friends)): ?>
                                <option value="<?php echo e($f['friend_username']); ?>">
                                    <?php echo e($f['friend_username']); ?>
                                </option>
                            <?php endwhile; ?>

                        </select>

                        <button class="btn btn-success">Share</button>
                    </div>
                </form>

                <hr>

                <h6>Currently shared with:</h6>

                <?php
                $stmt = mysqli_prepare($conn, "SELECT pk_user FROM shares WHERE pk_collection = ?");
                mysqli_stmt_bind_param($stmt, "i", $col['pk_collectionID']);
                mysqli_stmt_execute($stmt);
                $shared = mysqli_stmt_get_result($stmt);
                ?>

                <?php if (mysqli_num_rows($shared) === 0): ?>
                    <p class="text-muted">Not shared with anyone.</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php while ($s = mysqli_fetch_assoc($shared)): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo e($s['pk_user']); ?>

                                <form method="POST" action="/api/collections.php">
                                    <input type="hidden" name="action" value="collection.unshare">
                                    <input type="hidden" name="collection_id" value="<?php echo $col['pk_collectionID']; ?>">
                                    <input type="hidden" name="friend" value="<?php echo e($s['pk_user']); ?>">
                                    <button class="btn btn-sm btn-danger">Unshare</button>
                                </form>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php endif; ?>

            </div>

        </div>
    </div>
</div>


<!-- DELETE MODAL -->
<div class="modal fade" id="deleteModal_<?php echo $col['pk_collectionID']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <form method="POST" action="/api/collections.php">
                <input type="hidden" name="action" value="collection.delete">
                <input type="hidden" name="collection_id" value="<?php echo $col['pk_collectionID']; ?>">

                <div class="modal-header">
                    <h5 class="modal-title">Delete Collection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    Are you sure you want to delete
                    <strong><?php echo e($col['name']); ?></strong>?
                    <p class="text-danger mt-2">This action cannot be undone.</p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-danger">Delete</button>
                </div>

            </form>

        </div>
    </div>
</div>

<?php endwhile; ?>

<?php require_once '../includes/footer.php'; ?>
