
<?php
require_once __DIR__ . '/../../services/stations.php';

// Pagination settings
$perPage = 3;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) {
    $currentPage = 1;
}

$totalStations = svc_adminCountStations($conn);
$totalPages    = max(1, (int)ceil($totalStations / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

try {
    $stations = svc_adminGetStationsPage($conn, $currentPage, $perPage);
} catch (Throwable $e) {
    $stations  = [];
    $loadError = $e->getMessage();
}

// Messages
$successMessage = '';
$errorMessage   = '';

if (!empty($_GET['created'])) {
    $successMessage = 'Station created successfully.';
} elseif (!empty($_GET['updated'])) {
    $successMessage = 'Station updated successfully.';
} elseif (!empty($_GET['deleted'])) {
    $successMessage = 'Station deleted.';
}

if (!empty($_GET['error'])) {
    $errorMessage = $_GET['error'];
}
?>

<!-- Create button -->
<button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#createStationModal">
  Create New Station
</button>

<!-- Alerts -->
<?php if (!empty($loadError ?? '')): ?>
  <div class="alert alert-danger">
    Error loading stations: <?php echo e($loadError); ?>
  </div>
<?php endif; ?>

<!-- Card -->
<div class="card">
  <div class="card-header">
    <h5 class="mb-0">All Stations</h5>
  </div>

  <div class="card-body">

    <!-- Table -->
    <div class="table-responsive">
      <table class="table table-striped">
        <thead>
          <tr>
            <th class="text-nowrap">Serial</th>
            <th class="text-nowrap">Name</th>
            <th class="text-nowrap">Description</th>
            <th class="text-nowrap">Created by</th>
            <th class="text-nowrap">Registered by</th>
            <th class="text-nowrap">Created</th>
            <th class="text-nowrap">Registered</th>
            <th class="text-nowrap">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($stations)): ?>
          <tr>
            <td colspan="8" class="text-center text-muted">No stations found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($stations as $st): ?>
            <?php
              $serial     = $st['pk_serialNumber'];
              $created    = $st['createdAt']    ? formatDateTime($st['createdAt'])    : '-';
              $registered = $st['registeredAt'] ? formatDateTime($st['registeredAt']) : '-';

              // Name
              $rawName  = $st['name'] ?: 'No name';
              $trimName = (strlen($rawName) > 30) ? substr($rawName, 0, 30) . "…" : $rawName;

              // Description
              $rawDesc  = $st['description'] ?: 'No description';
              $trimDesc = (strlen($rawDesc) > 30) ? substr($rawDesc, 0, 30) . "…" : $rawDesc;
            ?>
            <tr>
              <td><?php echo e($serial); ?></td>

              <td>
                <?php
                  if ($st['name']) {
                      echo e($trimName);
                  } else {
                      echo '<i>No name</i>';
                  }
                ?>
              </td>

              <td>
                <?php
                  if ($st['description']) {
                      echo e($trimDesc);
                  } else {
                      echo '<i>No description</i>';
                  }
                ?>
              </td>

              <td class="text-nowrap"><?php echo e($st['fk_createdBy']    ?: '-'); ?></td>
              <td class="text-nowrap"><?php echo e($st['fk_registeredBy'] ?: '-'); ?></td>

              <td class="text-nowrap"><?php echo e($created); ?></td>
              <td class="text-nowrap"><?php echo e($registered); ?></td>

              <td class="text-nowrap">
                <!-- Edit -->
                <button class="btn btn-sm btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#editStationModal"
                        data-serial="<?php echo e($serial); ?>"
                        data-name="<?php echo e($st['name'] ?? ''); ?>"
                        data-description="<?php echo e($st['description'] ?? ''); ?>"
                        data-owner="<?php echo e($st['fk_registeredBy'] ?? ''); ?>">
                  Edit
                </button>

                <!-- Delete -->
                <button class="btn btn-sm btn-danger"
                        data-bs-toggle="modal"
                        data-bs-target="#deleteStationModal"
                        data-serial="<?php echo e($serial); ?>">
                  Delete
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination (right aligned) -->
    <?php if ($totalPages > 1): ?>
      <nav aria-label="Stations pagination" class="mt-3">
        <ul class="pagination justify-content-end mb-0">

          <!-- Prev -->
          <?php $prevPage = max(1, $currentPage - 1); ?>
          <li class="page-item <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>">
            <a class="page-link"
               href="/admin/panel.php?tab=stations&page=<?php echo $prevPage; ?>"
               aria-label="Previous">
              &laquo;
            </a>
          </li>

          <?php
          $maxVisible = 5;

          if ($totalPages <= $maxVisible) {
              // Show all pages
              for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?php echo ($p === $currentPage) ? 'active' : ''; ?>">
                  <a class="page-link"
                     href="/admin/panel.php?tab=stations&page=<?php echo $p; ?>">
                    <?php echo $p; ?>
                  </a>
                </li>
              <?php endfor;
          } else {
              $start = max(1, $currentPage - 2);
              $end   = min($totalPages, $currentPage + 2);

              // First page
              if ($start > 1): ?>
                <li class="page-item <?php echo ($currentPage === 1) ? 'active' : ''; ?>">
                  <a class="page-link"
                     href="/admin/panel.php?tab=stations&page=1">
                    1
                  </a>
                </li>
                <?php if ($start > 2): ?>
                  <li class="page-item disabled">
                    <span class="page-link">...</span>
                  </li>
                <?php endif;
              endif;

              // Middle pages
              for ($p = $start; $p <= $end; $p++):
                  if ($p > 1 && $p < $totalPages): ?>
                    <li class="page-item <?php echo ($p === $currentPage) ? 'active' : ''; ?>">
                      <a class="page-link"
                         href="/admin/panel.php?tab=stations&page=<?php echo $p; ?>">
                        <?php echo $p; ?>
                      </a>
                    </li>
              <?php
                  endif;
              endfor;

              // Last page
              if ($end < $totalPages - 1): ?>
                <li class="page-item disabled">
                  <span class="page-link">...</span>
                </li>
              <?php endif; ?>

              <li class="page-item <?php echo ($currentPage === $totalPages) ? 'active' : ''; ?>">
                <a class="page-link"
                   href="/admin/panel.php?tab=stations&page=<?php echo $totalPages; ?>">
                  <?php echo $totalPages; ?>
                </a>
              </li>
          <?php } ?>

          <!-- Next -->
          <?php $nextPage = min($totalPages, $currentPage + 1); ?>
          <li class="page-item <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
            <a class="page-link"
               href="/admin/panel.php?tab=stations&page=<?php echo $nextPage; ?>"
               aria-label="Next">
              &raquo;
            </a>
          </li>

        </ul>
      </nav>
    <?php endif; ?>

  </div>
</div>



<!-- ========================= CREATE MODAL ========================= -->
<div class="modal fade" id="createStationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="/admin/api/stations.php" method="POST">

        <input type="hidden" name="action" value="admin.station.create">
        <input type="hidden" name="page" value="<?php echo $currentPage; ?>">

        <div class="modal-header">
          <h5 class="modal-title">Create New Station</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <div class="mb-3">
            <label class="form-label">Serial</label>
            <input type="text" class="form-control" name="serialNumber" maxlength="50" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" name="name" maxlength="100">
          </div>

          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" maxlength="1000" rows="3"></textarea>
          </div>

        </div>

        <div class="modal-footer">
          <button class="btn btn-primary" type="submit">Create</button>
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        </div>

      </form>
    </div>
  </div>
</div>



<!-- ========================= EDIT MODAL ========================= -->
<div class="modal fade" id="editStationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="/admin/api/stations.php" method="POST">

        <input type="hidden" name="action" value="admin.station.update">
        <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
        <input type="hidden" name="serialNumber" id="editSerialHidden">

        <div class="modal-header">
          <h5 class="modal-title">Edit Station</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <div class="mb-3">
            <label class="form-label">Serial</label>
            <input type="text" class="form-control" id="editSerialDisplay" disabled>
          </div>

          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" id="editName" name="name" maxlength="100">
          </div>

          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="editDescription" name="description" maxlength="1000" rows="3"></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Registered by (username)</label>
            <input type="text" class="form-control" id="editOwner" name="registeredBy" maxlength="50">
          </div>

        </div>

        <div class="modal-footer">
          <button class="btn btn-primary" type="submit">Save</button>
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        </div>

      </form>
    </div>
  </div>
</div>



<!-- ========================= DELETE MODAL ========================= -->
<div class="modal fade" id="deleteStationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="/admin/api/stations.php" method="POST">

        <input type="hidden" name="action" value="admin.station.delete">
        <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
        <input type="hidden" name="serialNumber" id="deleteSerialHidden">

        <div class="modal-header">
          <h5 class="modal-title">Delete Station</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <p>Are you sure you want to delete station <strong id="deleteSerialCaption"></strong>?</p>
          <p class="text-danger"><small>This will also delete all related measurements.</small></p>
        </div>

        <div class="modal-footer">
          <button class="btn btn-danger" type="submit">Delete</button>
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        </div>

      </form>
    </div>
  </div>
</div>



<!-- ========================= MODAL SCRIPT ========================= -->
<script>
$(function () {

  // EDIT
  $('#editStationModal').on('show.bs.modal', function (event) {
    var btn = $(event.relatedTarget);

    $('#editSerialHidden').val(btn.data('serial'));
    $('#editSerialDisplay').val(btn.data('serial'));
    $('#editName').val(btn.data('name'));
    $('#editDescription').val(btn.data('description'));
    $('#editOwner').val(btn.data('owner'));
  });

  // DELETE
  $('#deleteStationModal').on('show.bs.modal', function (event) {
    var btn = $(event.relatedTarget);

    $('#deleteSerialHidden').val(btn.data('serial'));
    $('#deleteSerialCaption').text(btn.data('serial'));
  });

});
</script>
