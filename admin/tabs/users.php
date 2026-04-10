<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?= t('users') ?> (<?= $totalUsers ?>)</h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="bi bi-plus-circle me-1"></i><?= t('create') ?>
    </button>
</div>
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr><th><?= t('username') ?></th><th><?= t('first_name') ?></th><th><?= t('last_name') ?></th><th><?= t('email') ?></th><th><?= t('role') ?></th><th><?= t('created_at') ?></th><th><?= t('actions') ?></th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?= e($u['pk_username']) ?></td>
            <td><?= e($u['firstName']) ?></td>
            <td><?= e($u['lastName']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><span class="badge bg-<?= $u['role'] === 'Admin' ? 'danger' : 'secondary' ?>"><?= e($u['role']) ?></span></td>
            <td><?= formatDateTime($u['createdAt'] ?? null) ?></td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)">
                    <i class="bi bi-pencil"></i>
                </button>
                <?php if ($u['pk_username'] !== $username): ?>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="username" value="<?= e($u['pk_username']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= t('confirm_delete') ?>')"><i class="bi bi-trash"></i></button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php $totalUserPages = max(1, ceil($totalUsers / $perPage)); ?>
<?php if ($totalUserPages > 1): ?>
<nav><ul class="pagination pagination-sm justify-content-center">
    <?php for ($i = 1; $i <= $totalUserPages; $i++): ?>
    <li class="page-item <?= $i == $userPage ? 'active' : '' ?>"><a class="page-link" href="?tab=users&user_page=<?= $i ?>"><?= $i ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= t('create') ?> <?= t('users') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post">
                <input type="hidden" name="action" value="create_user">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label"><?= t('username') ?></label><input type="text" name="username" class="form-control" required></div>
                        <div class="col-6 mb-3"><label class="form-label"><?= t('role') ?></label><select name="role" class="form-select"><option>User</option><option>Admin</option></select></div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label"><?= t('first_name') ?></label><input type="text" name="firstName" class="form-control" required></div>
                        <div class="col-6 mb-3"><label class="form-label"><?= t('last_name') ?></label><input type="text" name="lastName" class="form-control" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label"><?= t('email') ?></label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label"><?= t('password') ?></label><input type="password" name="password" class="form-control" required></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= t('create') ?></button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= t('edit') ?> <?= t('users') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="username" id="editUserUsername">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label"><?= t('first_name') ?></label><input type="text" name="firstName" id="editUserFn" class="form-control" required></div>
                        <div class="col-6 mb-3"><label class="form-label"><?= t('last_name') ?></label><input type="text" name="lastName" id="editUserLn" class="form-control" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label"><?= t('email') ?></label><input type="email" name="email" id="editUserEmail" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label"><?= t('role') ?></label><select name="role" id="editUserRole" class="form-select"><option>User</option><option>Admin</option></select></div>
                    <div class="mb-3"><label class="form-label"><?= t('new_password') ?> (leave blank to keep)</label><input type="password" name="new_password" class="form-control"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= t('save') ?></button></div>
            </form>
        </div>
    </div>
</div>
