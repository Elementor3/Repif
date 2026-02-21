<?php
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
require_once __DIR__ . '/../services/users.php';
require_once __DIR__ . '/../services/stations.php';
require_once __DIR__ . '/../services/admin_posts.php';
require_once __DIR__ . '/../services/notifications.php';

$username = $_SESSION['username'];
$msg = '';
$err = '';

$activeTab = $_GET['tab'] ?? 'overview';
$perPage = 15;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Users
    if ($action === 'create_user') {
        $un = trim($_POST['username'] ?? '');
        $fn = trim($_POST['firstName'] ?? '');
        $ln = trim($_POST['lastName'] ?? '');
        $em = trim($_POST['email'] ?? '');
        $pw = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'User';
        if ($un && $fn && $ln && $em && $pw) {
            if (adminCreateUser($conn, $un, $fn, $ln, $em, $pw, $role)) {
                $msg = t('success');
            } else {
                $err = t('error_occurred');
            }
        }
    } elseif ($action === 'update_user') {
        $un = trim($_POST['username'] ?? '');
        $fn = trim($_POST['firstName'] ?? '');
        $ln = trim($_POST['lastName'] ?? '');
        $em = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'User';
        $pw = $_POST['new_password'] ?? '';
        if (adminUpdateUser($conn, $un, $fn, $ln, $em, $role, $pw ?: null)) {
            $msg = t('success');
        } else {
            $err = t('error_occurred');
        }
    } elseif ($action === 'delete_user') {
        $un = trim($_POST['username'] ?? '');
        if ($un !== $username) {
            if (!adminDeleteUser($conn, $un)) {
                $err = 'Cannot delete last admin';
            } else {
                $msg = t('success');
            }
        } else {
            $err = 'Cannot delete yourself';
        }
    }
    // Stations
    elseif ($action === 'create_station') {
        $serial = trim($_POST['serial'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($serial && $name) {
            if (adminCreateStation($conn, $serial, $name, $desc, $username)) {
                $msg = t('success');
            } else {
                $err = t('error_occurred');
            }
        }
    } elseif ($action === 'update_station') {
        $serial = trim($_POST['serial'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $regBy = trim($_POST['registeredBy'] ?? '') ?: null;
        if (adminUpdateStation($conn, $serial, $name, $desc, $regBy)) {
            $msg = t('success');
        }
    } elseif ($action === 'delete_station') {
        $serial = trim($_POST['serial'] ?? '');
        if (adminDeleteStation($conn, $serial)) {
            $msg = t('success');
        }
    }
    // Posts
    elseif ($action === 'create_post') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($title && $content) {
            $postId = createPost($conn, $username, $title, $content);
            if ($postId) {
                // Notify all users
                $allUsers = $conn->query("SELECT pk_username FROM user")->fetch_all(MYSQLI_ASSOC);
                foreach ($allUsers as $u) {
                    if ($u['pk_username'] !== $username) {
                        createNotification($conn, $u['pk_username'], 'admin_post', t('admin_post'), $title, '/admin/panel.php?tab=posts');
                    }
                }
                $msg = t('success');
            }
        }
    } elseif ($action === 'update_post') {
        $id = (int)($_POST['post_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if (updatePost($conn, $id, $title, $content)) $msg = t('success');
    } elseif ($action === 'delete_post') {
        $id = (int)($_POST['post_id'] ?? 0);
        if (deletePost($conn, $id)) $msg = t('success');
    }
}

// Data
$userPage = (int)($_GET['user_page'] ?? 1);
$stationPage = (int)($_GET['station_page'] ?? 1);
$postPage = (int)($_GET['post_page'] ?? 1);

$totalUsers = adminCountUsers($conn);
$totalStations = adminCountStations($conn);
$totalMeas = (int)$conn->query("SELECT COUNT(*) AS c FROM measurement")->fetch_assoc()['c'];
$totalColls = (int)$conn->query("SELECT COUNT(*) AS c FROM collection")->fetch_assoc()['c'];

$users = adminGetUsersPage($conn, $userPage, $perPage);
$stations = adminGetStationsPage($conn, $stationPage, $perPage);
$posts = getPosts($conn, $postPage, $perPage);
?>
<h2 class="mb-4"><i class="bi bi-shield-lock me-2"></i><?= t('admin_panel') ?></h2>

<?php if ($msg): ?><?= showSuccess($msg) ?><?php endif; ?>
<?php if ($err): ?><?= showError($err) ?><?php endif; ?>

<!-- Tab nav -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'overview' ? 'active' : '' ?>" href="?tab=overview"><?= t('dashboard') ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'users' ? 'active' : '' ?>" href="?tab=users"><?= t('users') ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'stations' ? 'active' : '' ?>" href="?tab=stations"><?= t('stations') ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'posts' ? 'active' : '' ?>" href="?tab=posts"><?= t('admin_posts') ?></a></li>
</ul>

<?php if ($activeTab === 'overview'): ?>
<!-- Overview stats -->
<div class="row g-3">
    <div class="col-md-3 col-6">
        <div class="card stat-card text-center p-3">
            <div class="stat-value text-primary"><?= $totalUsers ?></div>
            <div class="stat-label"><?= t('total_users') ?></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card text-center p-3">
            <div class="stat-value text-success"><?= $totalStations ?></div>
            <div class="stat-label"><?= t('total_stations') ?></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card text-center p-3">
            <div class="stat-value text-info"><?= $totalMeas ?></div>
            <div class="stat-label"><?= t('total_measurements') ?></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card text-center p-3">
            <div class="stat-value text-warning"><?= $totalColls ?></div>
            <div class="stat-label"><?= t('total_collections') ?></div>
        </div>
    </div>
</div>

<?php elseif ($activeTab === 'users'): ?>
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

<!-- Create User Modal -->
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

<!-- Edit User Modal -->
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

<?php elseif ($activeTab === 'stations'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?= t('stations') ?> (<?= $totalStations ?>)</h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createStationModal">
        <i class="bi bi-plus-circle me-1"></i><?= t('create') ?>
    </button>
</div>
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th><?= t('station_serial') ?></th><th><?= t('name') ?></th><th><?= t('description') ?></th><th><?= t('registered_by') ?></th><th><?= t('actions') ?></th></tr></thead>
        <tbody>
        <?php foreach ($stations as $s): ?>
        <tr>
            <td><code><?= e($s['pk_serialNumber']) ?></code></td>
            <td><?= e($s['name'] ?? '') ?></td>
            <td><?= e($s['description'] ?? '') ?></td>
            <td><?= $s['fk_registeredBy'] ? e($s['firstName'] . ' ' . $s['lastName'] . ' (' . $s['fk_registeredBy'] . ')') : '-' ?></td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editStation(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete_station">
                    <input type="hidden" name="serial" value="<?= e($s['pk_serialNumber']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= t('confirm_delete') ?>')"><i class="bi bi-trash"></i></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php $totalStationPages = max(1, ceil($totalStations / $perPage)); ?>
<?php if ($totalStationPages > 1): ?>
<nav><ul class="pagination pagination-sm justify-content-center">
    <?php for ($i = 1; $i <= $totalStationPages; $i++): ?>
    <li class="page-item <?= $i == $stationPage ? 'active' : '' ?>"><a class="page-link" href="?tab=stations&station_page=<?= $i ?>"><?= $i ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<!-- Create Station Modal -->
<div class="modal fade" id="createStationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= t('create') ?> <?= t('station') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post">
                <input type="hidden" name="action" value="create_station">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label"><?= t('station_serial') ?></label><input type="text" name="serial" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label"><?= t('name') ?></label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label"><?= t('description') ?></label><textarea name="description" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= t('create') ?></button></div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Station Modal -->
<div class="modal fade" id="editStationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= t('edit') ?> <?= t('station') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post">
                <input type="hidden" name="action" value="update_station">
                <input type="hidden" name="serial" id="editStSerial">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label"><?= t('name') ?></label><input type="text" name="name" id="editStName" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label"><?= t('description') ?></label><textarea name="description" id="editStDesc" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label"><?= t('registered_by') ?> (username or blank)</label><input type="text" name="registeredBy" id="editStRegBy" class="form-control"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= t('save') ?></button></div>
            </form>
        </div>
    </div>
</div>

<?php elseif ($activeTab === 'posts'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?= t('admin_posts') ?></h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPostModal">
        <i class="bi bi-plus-circle me-1"></i><?= t('publish') ?>
    </button>
</div>
<?php if (empty($posts)): ?>
<div class="alert alert-info">No posts yet</div>
<?php else: ?>
<?php foreach ($posts as $p): ?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><?= e($p['title']) ?></strong>
        <div class="d-flex gap-1 align-items-center">
            <small class="text-muted me-2"><?= formatDateTime($p['createdAt']) ?></small>
            <button class="btn btn-sm btn-outline-primary" onclick="editPost(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline">
                <input type="hidden" name="action" value="delete_post">
                <input type="hidden" name="post_id" value="<?= $p['pk_postID'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= t('confirm_delete') ?>')"><i class="bi bi-trash"></i></button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <p class="mb-0"><?= nl2br(e($p['content'])) ?></p>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Create Post Modal -->
<div class="modal fade" id="createPostModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= t('publish') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post">
                <input type="hidden" name="action" value="create_post">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label"><?= t('post_title') ?></label><input type="text" name="title" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label"><?= t('post_content') ?></label><textarea name="content" class="form-control" rows="6" required></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= t('publish') ?></button></div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Post Modal -->
<div class="modal fade" id="editPostModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= t('edit') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post">
                <input type="hidden" name="action" value="update_post">
                <input type="hidden" name="post_id" id="editPostId">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label"><?= t('post_title') ?></label><input type="text" name="title" id="editPostTitle" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label"><?= t('post_content') ?></label><textarea name="content" id="editPostContent" class="form-control" rows="6" required></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= t('save') ?></button></div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function editUser(u) {
    document.getElementById('editUserUsername').value = u.pk_username;
    document.getElementById('editUserFn').value = u.firstName;
    document.getElementById('editUserLn').value = u.lastName;
    document.getElementById('editUserEmail').value = u.email;
    document.getElementById('editUserRole').value = u.role;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
function editStation(s) {
    document.getElementById('editStSerial').value = s.pk_serialNumber;
    document.getElementById('editStName').value = s.name || '';
    document.getElementById('editStDesc').value = s.description || '';
    document.getElementById('editStRegBy').value = s.fk_registeredBy || '';
    new bootstrap.Modal(document.getElementById('editStationModal')).show();
}
function editPost(p) {
    document.getElementById('editPostId').value = p.pk_postID;
    document.getElementById('editPostTitle').value = p.title;
    document.getElementById('editPostContent').value = p.content;
    new bootstrap.Modal(document.getElementById('editPostModal')).show();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
