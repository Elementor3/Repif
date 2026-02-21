<?php
// ================================================
// LOAD COLLECTIONS + PAGINATION
// ================================================

$page    = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 3;

// Count collections
$countRes = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM collection");
$countRow = mysqli_fetch_assoc($countRes);
$total    = (int)$countRow['cnt'];

$totalPages = max(1, ceil($total / $perPage));
$page = max(1, min($page, $totalPages));
$offset = ($page - 1) * $perPage;

// Load collections
$res = mysqli_query(
    $conn,
    "SELECT c.pk_collectionID, c.name, c.description, c.fk_user, c.createdAt,
            (SELECT COUNT(*) FROM contains WHERE pkfk_collection = c.pk_collectionID) AS measurement_count
     FROM collection c
     ORDER BY c.createdAt DESC
     LIMIT {$perPage} OFFSET {$offset}"
);

$collections = [];
while ($row = mysqli_fetch_assoc($res)) {
    $collections[] = $row;
}

// Load stations (CREATE modal)
$stationsRes = mysqli_query(
    $conn,
    "SELECT pk_serialNumber, name FROM station ORDER BY name, pk_serialNumber"
);
?>

<!-- CREATE -->
<button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#adminCreateCollectionModal">
  Create New Collection
</button>

<div class="card">
  <div class="card-header">
    <h5 class="mb-0">All Collections</h5>
  </div>

  <div class="card-body">

    <!-- TABLE -->
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th>Name</th>
            <th>Owner</th>
            <th>Description</th>
            <th>Measurements</th>
            <th>Created</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>

        <?php foreach ($collections as $col): ?>
          <?php $cid = (int)$col['pk_collectionID']; ?>
          <tr>
            <td><?= e($col['name']) ?></td>
            <td><?= e($col['fk_user']) ?></td>
            <td><?= e(substr($col['description'] ?: '-', 0, 60)) ?></td>
            <td><?= e($col['measurement_count']) ?></td>
            <td class="text-nowrap"><?= formatDateTime($col['createdAt']) ?></td>
            <td class="text-end">
              <button class="btn btn-sm btn-info"
                      data-bs-toggle="modal"
                      data-bs-target="#viewCollModal_<?= $cid ?>">View</button>
              <button class="btn btn-sm btn-primary"
                      data-bs-toggle="modal"
                      data-bs-target="#editCollModal_<?= $cid ?>">Edit</button>
              <button class="btn btn-sm btn-danger"
                      data-bs-toggle="modal"
                      data-bs-target="#deleteCollModal_<?= $cid ?>">Delete</button>
            </td>
          </tr>
        <?php endforeach; ?>

        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
      <ul class="pagination justify-content-end mb-0">

        <!-- Prev -->
        <li class="page-item <?= $page === 1 ? 'disabled' : '' ?>">
          <?= $page === 1
              ? '<span class="page-link">&laquo;</span>'
              : '<a class="page-link" href="?tab=collections&page='.($page-1).'">&laquo;</a>' ?>
        </li>

        <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);

        if ($start > 1): ?>
          <li class="page-item"><a class="page-link" href="?tab=collections&page=1">1</a></li>
          <?php if ($start > 2): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
          <?php endif;
        endif;

        for ($p = $start; $p <= $end; $p++): ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="?tab=collections&page=<?= $p ?>"><?= $p ?></a>
          </li>
        <?php endfor;

        if ($end < $totalPages): ?>
          <?php if ($end < $totalPages - 1): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
          <?php endif; ?>
          <li class="page-item">
            <a class="page-link" href="?tab=collections&page=<?= $totalPages ?>"><?= $totalPages ?></a>
          </li>
        <?php endif; ?>

        <!-- Next -->
        <li class="page-item <?= $page === $totalPages ? 'disabled' : '' ?>">
          <?= $page === $totalPages
              ? '<span class="page-link">&raquo;</span>'
              : '<a class="page-link" href="?tab=collections&page='.($page+1).'">&raquo;</a>' ?>
        </li>

      </ul>
    </nav>
    <?php endif; ?>

  </div>
</div>

<!-- CREATE MODAL -->
<div class="modal fade" id="adminCreateCollectionModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="/admin/api/collections.php">
      <input type="hidden" name="action" value="admin.collection.create">

      <div class="modal-header">
        <h5 class="modal-title">Create Collection</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="mb-3">
          <label class="form-label">Name *</label>
          <input class="form-control" name="collection_name" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Station *</label>
          <select class="form-select" name="station" required>
            <option value="">-- Select --</option>
            <?php while ($st = mysqli_fetch_assoc($stationsRes)): ?>
              <option value="<?= e($st['pk_serialNumber']) ?>">
                <?= e($st['name'] ?: $st['pk_serialNumber']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Start *</label>
          <input type="datetime-local" class="form-control" name="start_date" required>
        </div>

        <div class="mb-3">
          <label class="form-label">End *</label>
          <input type="datetime-local" class="form-control" name="end_date" required>
        </div>

        <textarea class="form-control" name="description" rows="2"></textarea>

      </div>

      <div class="modal-footer">
        <button class="btn btn-primary">Create</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>

    </form>
  </div>
</div>

<?php foreach ($collections as $col): ?>
<?php $cid = (int)$col['pk_collectionID']; ?>

<!-- VIEW -->
<div class="modal fade" id="viewCollModal_<?= $cid ?>" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title"><?= e($col['name']) ?></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body collection-view-scroll">
        <?php
        $stmt = mysqli_prepare(
          $conn,
          "SELECT m.timestamp, m.temperature, m.humidity, m.airPressure,
                  m.lightIntensity, m.airQuality
           FROM measurement m
           JOIN contains c ON m.pk_measurementID = c.pkfk_measurement
           WHERE c.pkfk_collection = ?
           ORDER BY m.timestamp DESC"
        );
        mysqli_stmt_bind_param($stmt, "i", $cid);
        mysqli_stmt_execute($stmt);
        $meas = mysqli_stmt_get_result($stmt);
        ?>

        <?php if (mysqli_num_rows($meas) === 0): ?>
          <p class="text-muted">No measurements.</p>
        <?php else: ?>
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Time</th><th>Temp</th><th>Hum</th><th>Press</th><th>Light</th><th>Quality</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($m = mysqli_fetch_assoc($meas)): ?>
              <tr>
                <td><?= formatDateTime($m['timestamp']) ?></td>
                <td><?= e($m['temperature']) ?></td>
                <td><?= e($m['humidity']) ?></td>
                <td><?= e($m['airPressure']) ?></td>
                <td><?= e($m['lightIntensity']) ?></td>
                <td><?= e($m['airQuality']) ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>

<!-- EDIT -->
<div class="modal fade" id="editCollModal_<?= $cid ?>" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="/admin/api/collections.php">
      <input type="hidden" name="action" value="admin.collection.edit">
      <input type="hidden" name="collection_id" value="<?= $cid ?>">

      <div class="modal-header">
        <h5 class="modal-title">Edit Collection</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input class="form-control mb-2" name="new_name" value="<?= e($col['name']) ?>" required>
        <textarea class="form-control" name="new_description" rows="3"><?= e($col['description']) ?></textarea>
      </div>

      <div class="modal-footer">
        <button class="btn btn-primary">Save</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE -->
<div class="modal fade" id="deleteCollModal_<?= $cid ?>" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="/admin/api/collections.php">
      <input type="hidden" name="action" value="admin.collection.delete">
      <input type="hidden" name="collection_id" value="<?= $cid ?>">

      <div class="modal-header">
        <h5 class="modal-title">Delete</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        Delete <strong><?= e($col['name']) ?></strong>?
      </div>

      <div class="modal-footer">
        <button class="btn btn-danger">Delete</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<?php endforeach; ?>

<style>
.collection-view-scroll {
  max-height: 65vh;
  overflow-y: auto;
}
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
