
<?php
// admin/tabs/tab_users.php

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../services/users.php';

// Pagination
$perPage = 3;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) {
    $currentPage = 1;
}

$totalUsers = svc_adminCountUsers($conn);
$totalPages = max(1, (int)ceil($totalUsers / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$users = svc_adminGetUsersPage($conn, $currentPage, $perPage);

?>

<!-- Create button -->
<button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#createUserModal">
  Create New User
</button>

<!-- Card wrapper -->
<div class="card">
  <div class="card-header">
    <h5 class="mb-0">All Users</h5>
  </div>

  <div class="card-body">

    <div class="table-responsive">
      <table class="table table-striped">
        <thead>
          <tr>
            <th class="text-nowrap">Username</th>
            <th class="text-nowrap">First name</th>
            <th class="text-nowrap">Last name</th>
            <th class="text-nowrap">Email</th>
            <th class="text-nowrap">Role</th>
            <th class="text-nowrap">Created</th>
            <th class="text-nowrap">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
          <tr>
            <td colspan="7" class="text-center text-muted">No users found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($users as $u): ?>
            <?php $created = $u['createdAt'] ? formatDateTime($u['createdAt']) : '-'; ?>
            <tr>
              <td><?php echo e($u['pk_username']); ?></td>
              <td><?php echo e($u['firstName']); ?></td>
              <td><?php echo e($u['lastName']); ?></td>
              <td><?php echo e($u['email']); ?></td>
              <td><?php echo e($u['role']); ?></td>
              <td class="text-nowrap"><?php echo e($created); ?></td>
              <td class="text-nowrap">
                <button class="btn btn-sm btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#editUserModal"
                        data-username="<?php echo e($u['pk_username']); ?>"
                        data-firstname="<?php echo e($u['firstName']); ?>"
                        data-lastname="<?php echo e($u['lastName']); ?>"
                        data-email="<?php echo e($u['email']); ?>"
                        data-role="<?php echo e($u['role']); ?>">
                  Edit
                </button>
                <button class="btn btn-sm btn-danger"
                        data-bs-toggle="modal"
                        data-bs-target="#deleteUserModal"
                        data-username="<?php echo e($u['pk_username']); ?>">
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
      <nav aria-label="Users pagination" class="mt-3">
        <ul class="pagination justify-content-end mb-0">

          <!-- Prev -->
          <?php $prevPage = max(1, $currentPage - 1); ?>
          <li class="page-item <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>">
            <a class="page-link" href="/admin/panel.php?tab=users&page=<?php echo $prevPage; ?>" aria-label="Previous">
              &laquo;
            </a>
          </li>

          <?php
          // Простая пагинация с "..."
          $maxVisible = 5;

          if ($totalPages <= $maxVisible) {
              // Показать все
              for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?php echo ($p === $currentPage) ? 'active' : ''; ?>">
                  <a class="page-link" href="/admin/panel.php?tab=users&page=<?php echo $p; ?>">
                    <?php echo $p; ?>
                  </a>
                </li>
              <?php endfor;
          } else {
              $start = max(1, $currentPage - 2);
              $end   = min($totalPages, $currentPage + 2);

              if ($start > 1): ?>
                <li class="page-item <?php echo ($currentPage === 1) ? 'active' : ''; ?>">
                  <a class="page-link" href="/admin/panel.php?tab=users&page=1">1</a>
                </li>
                <?php if ($start > 2): ?>
                  <li class="page-item disabled">
                    <span class="page-link">...</span>
                  </li>
                <?php endif;
              endif;

              for ($p = $start; $p <= $end; $p++):
                  if ($p > 1 && $p < $totalPages): ?>
                    <li class="page-item <?php echo ($p === $currentPage) ? 'active' : ''; ?>">
                      <a class="page-link" href="/admin/panel.php?tab=users&page=<?php echo $p; ?>">
                        <?php echo $p; ?>
                      </a>
                    </li>
              <?php
                  endif;
              endfor;

              if ($end < $totalPages - 1): ?>
                <li class="page-item disabled">
                  <span class="page-link">...</span>
                </li>
              <?php endif; ?>

              <li class="page-item <?php echo ($currentPage === $totalPages) ? 'active' : ''; ?>">
                <a class="page-link" href="/admin/panel.php?tab=users&page=<?php echo $totalPages; ?>">
                  <?php echo $totalPages; ?>
                </a>
              </li>
          <?php } ?>

          <!-- Next -->
          <?php $nextPage = min($totalPages, $currentPage + 1); ?>
          <li class="page-item <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
            <a class="page-link" href="/admin/panel.php?tab=users&page=<?php echo $nextPage; ?>" aria-label="Next">
              &raquo;
            </a>
          </li>

        </ul>
      </nav>
    <?php endif; ?>

  </div>
</div>


<!-- ================ CREATE USER MODAL ================ -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <form action="/admin/api/users.php" method="post">
        <input type="hidden" name="action" value="admin.user.create">
        <input type="hidden" name="page" value="<?php echo $currentPage; ?>">

        <div class="modal-header">
          <h5 class="modal-title">Create New User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <input type="text" style="display:none" autocomplete="off">
          <input type="password" style="display:none" autocomplete="new-password">

          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="username" autocomplete="off" maxlength="50" required>
          </div>

          <div class="mb-3">
            <label class="form-label">First name</label>
            <input type="text" class="form-control" name="firstName" autocomplete="off" maxlength="100" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Last name</label>
            <input type="text" class="form-control" name="lastName" autocomplete="off" maxlength="100" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" autocomplete="off" maxlength="100" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" name="password" autocomplete="new-password" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Role</label>
            <select class="form-select" name="role">
              <option value="User" selected>User</option>
              <option value="Admin">Admin</option>
            </select>
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


<!-- ================ EDIT USER MODAL ================ -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <form action="/admin/api/users.php" method="post">
        <input type="hidden" name="action" value="admin.user.update">
        <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
        <input type="hidden" name="username" id="editUsernameHidden">

        <div class="modal-header">
          <h5 class="modal-title">Edit User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" id="editUsernameDisplay" disabled>
          </div>

          <div class="mb-3">
            <label class="form-label">First name</label>
            <input type="text" class="form-control" id="editFirstName" name="firstName" maxlength="100" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Last name</label>
            <input type="text" class="form-control" id="editLastName" name="lastName" maxlength="100" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" id="editEmail" name="email" maxlength="100" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Role</label>
            <select class="form-select" id="editRole" name="role">
              <option value="User">User</option>
              <option value="Admin">Admin</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">New password (leave empty to keep current)</label>
            <input type="password" class="form-control" name="new_password" autocomplete="new-password">
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


<!-- ================ DELETE USER MODAL ================ -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <form action="/admin/api/users.php" method="post">
        <input type="hidden" name="action" value="admin.user.delete">
        <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
        <input type="hidden" name="username" id="deleteUsernameHidden">

        <div class="modal-header">
          <h5 class="modal-title">Delete User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <p>Are you sure you want to delete user <strong id="deleteUsernameCaption"></strong>?</p>
          <p class="text-danger mb-0"><small>This action cannot be undone.</small></p>
        </div>

        <div class="modal-footer">
          <button class="btn btn-danger" type="submit">Delete</button>
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        </div>

      </form>

    </div>
  </div>
</div>


<!-- ================ MODAL SCRIPT (jQuery) ================ -->
<script>
$(function () {

  // Fill Edit User modal
  $('#editUserModal').on('show.bs.modal', function (event) {
    var btn = $(event.relatedTarget);

    var username  = btn.data('username')  || '';
    var firstName = btn.data('firstname') || '';
    var lastName  = btn.data('lastname')  || '';
    var email     = btn.data('email')     || '';
    var role      = btn.data('role')      || 'User';

    $('#editUsernameHidden').val(username);
    $('#editUsernameDisplay').val(username);
    $('#editFirstName').val(firstName);
    $('#editLastName').val(lastName);
    $('#editEmail').val(email);
    $('#editRole').val(role);
  });

  // Fill Delete User modal
  $('#deleteUserModal').on('show.bs.modal', function (event) {
    var btn = $(event.relatedTarget);
    var username = btn.data('username') || '';

    $('#deleteUsernameHidden').val(username);
    $('#deleteUsernameCaption').text(username);
  });

});
</script>
