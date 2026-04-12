<?php
if (!function_exists('adminUsersCellText')) {
    function adminUsersCellText(string $text, string $title): string {
        $full = trim($text);
        if ($full === '') {
            return '<span class="text-muted">-</span>';
        }

        return '<span class="admin-users-cell-text js-admin-users-fit-text" data-title="' . e($title) . '" data-full-text="' . e($full) . '">' . e($full) . '</span>'
            . '<button type="button" class="btn btn-link btn-sm p-0 align-baseline admin-users-fulltext-trigger js-admin-open-full-text js-admin-users-fit-more d-none" data-title="' . e($title) . '" data-full-text="' . e($full) . '">...</button>';
    }
}

$usersFilterBaseQuery = ['tab' => 'users'];
$usersFilterBaseQuery['users_per_page'] = (int)($usersPerPage ?? 20);
$usersFilterBaseQuery['users_created_from'] = trim((string)($adminUserFilters['createdFrom'] ?? ''));
$usersFilterBaseQuery['users_created_to'] = trim((string)($adminUserFilters['createdTo'] ?? ''));
foreach ((array)($adminUserFilters['id'] ?? []) as $v) {
    $usersFilterBaseQuery['users_id'][] = (string)$v;
}
foreach ((array)($adminUserFilters['firstName'] ?? []) as $v) {
    $usersFilterBaseQuery['users_first_name'][] = (string)$v;
}
foreach ((array)($adminUserFilters['lastName'] ?? []) as $v) {
    $usersFilterBaseQuery['users_last_name'][] = (string)$v;
}
foreach ((array)($adminUserFilters['email'] ?? []) as $v) {
    $usersFilterBaseQuery['users_email'][] = (string)$v;
}
foreach ((array)($adminUserFilters['role'] ?? []) as $v) {
    $usersFilterBaseQuery['users_role'][] = (string)$v;
}
?>

<?php
$usersFrom = $totalUsers > 0 ? (($userPage - 1) * $usersPerPage + 1) : 0;
$usersTo = min($userPage * $usersPerPage, $totalUsers);
$usersPaginationInfo = str_replace(['{from}', '{to}', '{total}'], [$usersFrom, $usersTo, $totalUsers], t('pagination_info'));
?>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form id="adminUsersFilterForm" method="get" class="admin-users-filters">
            <input type="hidden" name="tab" value="users">
            <input type="hidden" name="user_page" value="1">
            <input type="hidden" name="users_per_page" value="<?= (int)$usersPerPage ?>">
            <div class="row g-2 align-items-end mb-2">
            <div class="col-6 col-md-3">
                <label for="usersFilterId" class="form-label mb-1"><?= t('username') ?></label>
                <div class="admin-multicombo" data-multi-combo>
                    <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle" id="usersFilterId">
                        <span data-role="summary" data-base-label="<?= e(t('username')) ?>"><?= empty($adminUserFilters['id']) ? t('username') . ': all' : t('username') . ': ' . count((array)$adminUserFilters['id']) ?></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="admin-multicombo-panel d-none" data-role="panel">
                        <input type="text" class="form-control form-control-sm mb-2" placeholder="Search..." data-role="search">
                        <div class="admin-multicombo-options" data-role="options">
                            <?php foreach (($userFilterUsernameOptions ?? []) as $opt): ?>
                            <label class="admin-multicombo-option" data-label="<?= e(strtolower((string)$opt)) ?>">
                                <input type="checkbox" name="users_id[]" value="<?= e($opt) ?>" <?= in_array((string)$opt, (array)($adminUserFilters['id'] ?? []), true) ? 'checked' : '' ?>>
                                <span><?= e($opt) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label for="usersFilterFirstName" class="form-label mb-1"><?= t('first_name') ?></label>
                <div class="admin-multicombo" data-multi-combo>
                    <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle" id="usersFilterFirstName">
                        <span data-role="summary" data-base-label="<?= e(t('first_name')) ?>"><?= empty($adminUserFilters['firstName']) ? t('first_name') . ': all' : t('first_name') . ': ' . count((array)$adminUserFilters['firstName']) ?></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="admin-multicombo-panel d-none" data-role="panel">
                        <input type="text" class="form-control form-control-sm mb-2" placeholder="Search..." data-role="search">
                        <div class="admin-multicombo-options" data-role="options">
                            <?php foreach (($userFilterFirstNameOptions ?? []) as $opt): ?>
                            <label class="admin-multicombo-option" data-label="<?= e(strtolower((string)$opt)) ?>">
                                <input type="checkbox" name="users_first_name[]" value="<?= e($opt) ?>" <?= in_array((string)$opt, (array)($adminUserFilters['firstName'] ?? []), true) ? 'checked' : '' ?>>
                                <span><?= e($opt) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label for="usersFilterLastName" class="form-label mb-1"><?= t('last_name') ?></label>
                <div class="admin-multicombo" data-multi-combo>
                    <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle" id="usersFilterLastName">
                        <span data-role="summary" data-base-label="<?= e(t('last_name')) ?>"><?= empty($adminUserFilters['lastName']) ? t('last_name') . ': all' : t('last_name') . ': ' . count((array)$adminUserFilters['lastName']) ?></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="admin-multicombo-panel d-none" data-role="panel">
                        <input type="text" class="form-control form-control-sm mb-2" placeholder="Search..." data-role="search">
                        <div class="admin-multicombo-options" data-role="options">
                            <?php foreach (($userFilterLastNameOptions ?? []) as $opt): ?>
                            <label class="admin-multicombo-option" data-label="<?= e(strtolower((string)$opt)) ?>">
                                <input type="checkbox" name="users_last_name[]" value="<?= e($opt) ?>" <?= in_array((string)$opt, (array)($adminUserFilters['lastName'] ?? []), true) ? 'checked' : '' ?>>
                                <span><?= e($opt) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label for="usersFilterEmail" class="form-label mb-1"><?= t('email') ?></label>
                <div class="admin-multicombo" data-multi-combo>
                    <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle" id="usersFilterEmail">
                        <span data-role="summary" data-base-label="<?= e(t('email')) ?>"><?= empty($adminUserFilters['email']) ? t('email') . ': all' : t('email') . ': ' . count((array)$adminUserFilters['email']) ?></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="admin-multicombo-panel d-none" data-role="panel">
                        <input type="text" class="form-control form-control-sm mb-2" placeholder="Search..." data-role="search">
                        <div class="admin-multicombo-options" data-role="options">
                            <?php foreach (($userFilterEmailOptions ?? []) as $opt): ?>
                            <label class="admin-multicombo-option" data-label="<?= e(strtolower((string)$opt)) ?>">
                                <input type="checkbox" name="users_email[]" value="<?= e($opt) ?>" <?= in_array((string)$opt, (array)($adminUserFilters['email'] ?? []), true) ? 'checked' : '' ?>>
                                <span><?= e($opt) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            </div>
            <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label for="usersFilterRole" class="form-label mb-1"><?= t('role') ?></label>
                <div class="admin-multicombo" data-multi-combo>
                    <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle" id="usersFilterRole">
                        <span data-role="summary" data-base-label="<?= e(t('role')) ?>"><?= empty($adminUserFilters['role']) ? t('role') . ': all' : t('role') . ': ' . count((array)$adminUserFilters['role']) ?></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="admin-multicombo-panel d-none" data-role="panel">
                        <div class="admin-multicombo-options" data-role="options">
                            <?php foreach (['User', 'Admin'] as $roleOpt): ?>
                            <label class="admin-multicombo-option" data-label="<?= e(strtolower($roleOpt)) ?>">
                                <input type="checkbox" name="users_role[]" value="<?= e($roleOpt) ?>" <?= in_array($roleOpt, (array)($adminUserFilters['role'] ?? []), true) ? 'checked' : '' ?>>
                                <span><?= e($roleOpt) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <label for="usersFilterCreatedFrom" class="form-label mb-1">Created from</label>
                <div class="input-group input-group-sm">
                    <input id="usersFilterCreatedFrom" type="text" name="users_created_from" value="<?= e($adminUserFilters['createdFrom'] ?? '') ?>" class="form-control form-control-sm js-admin-users-datetime" placeholder="DD.MM.YYYY HH:mm" autocomplete="off">
                    <span class="input-group-text slot-picker-icon measurement-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                </div>
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <label for="usersFilterCreatedTo" class="form-label mb-1">Created untill</label>
                <div class="input-group input-group-sm">
                    <input id="usersFilterCreatedTo" type="text" name="users_created_to" value="<?= e($adminUserFilters['createdTo'] ?? '') ?>" class="form-control form-control-sm js-admin-users-datetime" placeholder="DD.MM.YYYY HH:mm" autocomplete="off">
                    <span class="input-group-text slot-picker-icon measurement-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                </div>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2 admin-collections-filter-actions">
                <a href="?tab=users" class="btn btn-outline-secondary btn-sm admin-ajax-link"><?= t('clear') ?></a>
            </div>
            </div>
        </form>
    </div>
</div>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center mb-3 gap-2">
    <div class="d-flex flex-column align-items-start admin-collections-summary">
        <h5 class="mb-0"><?= t('users') ?></h5>
        <span class="pagination-info text-nowrap"><?= e($usersPaginationInfo) ?></span>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <form method="get" class="d-flex align-items-center gap-2 admin-users-per-page-form">
            <input type="hidden" name="tab" value="users">
            <input type="hidden" name="user_page" value="1">
            <input type="hidden" name="users_created_from" value="<?= e((string)($adminUserFilters['createdFrom'] ?? '')) ?>">
            <input type="hidden" name="users_created_to" value="<?= e((string)($adminUserFilters['createdTo'] ?? '')) ?>">
            <?php foreach ((array)($adminUserFilters['id'] ?? []) as $v): ?>
                <input type="hidden" name="users_id[]" value="<?= e((string)$v) ?>">
            <?php endforeach; ?>
            <?php foreach ((array)($adminUserFilters['firstName'] ?? []) as $v): ?>
                <input type="hidden" name="users_first_name[]" value="<?= e((string)$v) ?>">
            <?php endforeach; ?>
            <?php foreach ((array)($adminUserFilters['lastName'] ?? []) as $v): ?>
                <input type="hidden" name="users_last_name[]" value="<?= e((string)$v) ?>">
            <?php endforeach; ?>
            <?php foreach ((array)($adminUserFilters['email'] ?? []) as $v): ?>
                <input type="hidden" name="users_email[]" value="<?= e((string)$v) ?>">
            <?php endforeach; ?>
            <?php foreach ((array)($adminUserFilters['role'] ?? []) as $v): ?>
                <input type="hidden" name="users_role[]" value="<?= e((string)$v) ?>">
            <?php endforeach; ?>
            <label for="usersPerPage" class="form-label mb-0 small"><?= t('per_page') ?></label>
            <select id="usersPerPage" class="form-select form-select-sm" name="users_per_page" style="width:auto;">
                <?php foreach ([10, 20, 50, 100] as $pp): ?>
                <option value="<?= $pp ?>" <?= (int)$usersPerPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="bi bi-plus-circle me-1"></i><?= t('create') ?>
        </button>
    </div>
</div>

<div class="alert alert-secondary py-2 px-3 small d-sm-none" id="usersScrollHint" role="status">
    <i class="bi bi-arrow-left-right me-1"></i><?= t('table_horizontal_scroll_hint') ?>
</div>

<div class="table-responsive admin-users-table-wrap" id="adminUsersTableWrap">
    <table id="adminUsersTable" class="table table-sm table-hover align-middle text-center text-nowrap table-striped">
        <thead>
            <tr>
                <th>Avatar</th>
                <th><?= t('username') ?></th>
                <th><?= t('first_name') ?></th>
                <th><?= t('last_name') ?></th>
                <th><?= t('email') ?></th>
                <th><?= t('role') ?></th>
                <th><?= t('created_at') ?></th>
                <th><?= t('actions') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
        <tr>
            <td colspan="8" class="text-center text-muted py-3"><?= t('no_data') ?></td>
        </tr>
        <?php else: ?>
        <?php foreach ($users as $u): ?>
        <?php
            $userId = (string)($u['pk_username'] ?? '');
            $avatarUrl = getAvatarUrl((string)($u['avatar'] ?? ''), $userId);
            $createdRaw = (string)($u['createdAt'] ?? '');
            $createdFmt = formatDateTime($createdRaw ?: null);
        ?>
        <tr>
            <td>
                <?php if ($avatarUrl): ?>
                    <img src="<?= e($avatarUrl) ?>" alt="avatar" class="admin-users-avatar">
                <?php else: ?>
                    <span class="admin-users-avatar admin-users-avatar-fallback"><i class="bi bi-person"></i></span>
                <?php endif; ?>
            </td>
            <td class="admin-users-fit-cell"><?= adminUsersCellText($userId, t('username')) ?></td>
            <td class="admin-users-fit-cell"><?= adminUsersCellText((string)($u['firstName'] ?? ''), t('first_name')) ?></td>
            <td class="admin-users-fit-cell"><?= adminUsersCellText((string)($u['lastName'] ?? ''), t('last_name')) ?></td>
            <td class="admin-users-fit-cell admin-users-email-cell">
                <div class="admin-users-email-content">
                    <span class="admin-users-email-text"><?= adminUsersCellText((string)($u['email'] ?? ''), t('email')) ?></span>
                    <?php if ((int)($u['isEmailVerified'] ?? 0) === 1): ?>
                        <span class="text-success admin-users-verify-icon" title="Verified"><i class="bi bi-check-lg"></i></span>
                    <?php else: ?>
                        <span class="text-danger admin-users-verify-icon" title="Not verified"><i class="bi bi-x-lg"></i></span>
                    <?php endif; ?>
                </div>
            </td>
            <td><span class="admin-users-cell-text" title="<?= e((string)($u['role'] ?? '')) ?>"><?= e((string)($u['role'] ?? '')) ?></span></td>
            <td><span class="admin-users-cell-text" title="<?= e($createdFmt) ?>"><?= e($createdFmt) ?></span></td>
            <td>
                <div class="admin-actions-row">
                    <button class="btn btn-sm btn-outline-primary" type="button" title="Edit user" aria-label="Edit user" onclick="editUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <a class="btn btn-sm btn-outline-info" href="<?= e(buildAdminProfileUrl($userId)) ?>" title="View profile" aria-label="View profile">
                        <i class="bi bi-person-vcard"></i>
                    </a>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary js-admin-open-user-friends"
                        data-username="<?= e($userId) ?>"
                        data-friends='<?= e(json_encode($adminUserFriendsByUsername[$userId] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
                        title="View friends"
                        aria-label="View friends">
                        <i class="bi bi-people"></i>
                    </button>
                    <?php if ($u['pk_username'] !== $username): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="username" value="<?= e($u['pk_username']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete user" aria-label="Delete user" onclick="return confirm('<?= t('confirm_delete') ?>')"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php else: ?>
                    <span class="admin-actions-placeholder" aria-hidden="true"></span>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalUserPages > 1): ?>
<nav id="usersPaginationNav">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php for ($i = 1; $i <= $totalUserPages; $i++): ?>
        <?php $pageQuery = $usersFilterBaseQuery; $pageQuery['user_page'] = $i; ?>
        <li class="page-item <?= $i == $userPage ? 'active' : '' ?>"><a class="page-link" href="?<?= e(http_build_query($pageQuery)) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<div class="modal fade" id="adminUsersFullTextModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adminUsersFullTextTitle">Text</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="adminUsersFullTextBody"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="adminUserFriendsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adminUserFriendsTitle">Friends</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="adminUserFriendsBody"></div>
        </div>
    </div>
</div>

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
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="createUserIsEmailVerified" name="is_email_verified" value="1">
                        <label class="form-check-label" for="createUserIsEmailVerified">Email verified</label>
                    </div>
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
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="editUserIsEmailVerified" name="is_email_verified" value="1">
                        <label class="form-check-label" for="editUserIsEmailVerified">Email verified</label>
                    </div>
                    <div class="mb-3"><label class="form-label"><?= t('role') ?></label><select name="role" id="editUserRole" class="form-select"><option>User</option><option>Admin</option></select></div>
                    <div class="mb-3"><label class="form-label"><?= t('new_password') ?> (leave blank to keep)</label><input type="password" name="new_password" class="form-control"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= t('save') ?></button></div>
            </form>
        </div>
    </div>
</div>
