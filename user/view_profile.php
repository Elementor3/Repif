<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../services/users.php';
require_once __DIR__ . '/../services/friends.php';
require_once __DIR__ . '/../services/stations.php';
require_once __DIR__ . '/../services/collections.php';
requireLogin();

$username = $_SESSION['username'];
$viewUsername = trim($_GET['user'] ?? '');
$backParam = trim((string)($_GET['back'] ?? ''));

function resolveSafeBackUrl(string $candidate): string {
    $default = '/user/friends.php';
    if ($candidate === '') {
        return $default;
    }

    $parts = parse_url($candidate);
    if ($parts === false) {
        return $default;
    }

    if (isset($parts['scheme']) || isset($parts['host'])) {
        return $default;
    }

    $path = (string)($parts['path'] ?? '');
    $isUserPath = strncmp($path, '/user/', 6) === 0;
    $isAdminPath = strncmp($path, '/admin/', 7) === 0;
    if ($path === '' || (!$isUserPath && !$isAdminPath)) {
        return $default;
    }

    $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
    return $path . $query;
}

$backUrl = resolveSafeBackUrl($backParam);

if (!$viewUsername || $viewUsername === $username) {
    header('Location: /user/profile.php');
    exit;
}

$profile = getUserByUsername($conn, $viewUsername);
if (!$profile) {
    header('Location: /user/friends.php');
    exit;
}

$isAdminViewer = isAdmin();
$isAdminProfileView = $isAdminViewer && (trim((string)($_GET['admin_view'] ?? '')) === '1' || strncmp($backUrl, '/admin/', 7) === 0);

$adminViewedFriends = [];
$adminViewedStations = [];
$adminViewedCollections = [];
if ($isAdminProfileView) {
    $adminViewedFriends = getFriends($conn, $viewUsername);
    $adminViewedStations = getUserStationsList($conn, $viewUsername);
    $adminViewedCollections = getUserCollections($conn, $viewUsername);
}

$chatBackUrl = '/user/view_profile.php?user=' . urlencode($viewUsername);
if ($backUrl !== '') {
    $chatBackUrl .= '&back=' . urlencode($backUrl);
}

$isFriend = areFriends($conn, $username, $viewUsername);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center mb-4 gap-2">
    <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i><?= t('back') ?></a>
    <h2 class="mb-0"><i class="bi bi-person-circle me-2"></i><?= e($profile['firstName'] . ' ' . $profile['lastName']) ?></h2>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body py-4">
                <?php if (!empty($profile['avatar'])): ?>
                <img src="<?= e(getAvatarUrl($profile['avatar'], $profile['pk_username']) ?? '') ?>" class="rounded-circle mb-3" width="96" height="96" alt="avatar">
                <?php else: ?>
                <i class="bi bi-person-circle display-1 mb-3 text-muted"></i>
                <?php endif; ?>
                <h4><?= e($profile['firstName'] . ' ' . $profile['lastName']) ?></h4>
                <p class="text-muted">@<?= e($profile['pk_username']) ?></p>
                <?php if ($isFriend): ?>
                <span class="badge bg-success mb-2"><i class="bi bi-people-fill me-1"></i><?= t('friends') ?></span>
                <div class="d-flex gap-2 justify-content-center mt-2">
                    <a href="<?= e('/user/chat.php?with=' . urlencode($viewUsername) . '&back=' . urlencode($chatBackUrl)) ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-chat me-1"></i><?= t('chat') ?>
                    </a>
                </div>
                <?php else: ?>
                <span class="badge bg-secondary mb-2"><i class="bi bi-person me-1"></i><?= t('profile') ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><?= t('profile') ?></h5></div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th class="w-25"><?= t('username') ?></th>
                        <td><?= e($profile['pk_username']) ?></td>
                    </tr>
                    <tr>
                        <th><?= t('first_name') ?></th>
                        <td><?= e($profile['firstName']) ?></td>
                    </tr>
                    <tr>
                        <th><?= t('last_name') ?></th>
                        <td><?= e($profile['lastName']) ?></td>
                    </tr>
                    <?php if ($isAdminProfileView): ?>
                    <tr>
                        <th>Email</th>
                        <td><?= e((string)($profile['email'] ?? '-')) ?></td>
                    </tr>
                    <tr>
                        <th>Email verified</th>
                        <td>
                            <?php if ((int)($profile['isEmailVerified'] ?? 0) === 1): ?>
                                <span class="badge bg-success">Yes</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">No</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <?php if ($isAdminProfileView): ?>
        <div class="card mt-4">
            <div class="card-header"><h5 class="mb-0">Friends list</h5></div>
            <div class="card-body">
                <?php if (empty($adminViewedFriends)): ?>
                    <div class="text-muted">No friends</div>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($adminViewedFriends as $friend): ?>
                            <?php $friendUsername = (string)($friend['pk_username'] ?? ''); ?>
                            <a class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2" href="<?= e('/user/view_profile.php?user=' . urlencode($friendUsername) . '&back=' . urlencode($backUrl) . '&admin_view=1') ?>">
                                <?php $friendAvatar = getAvatarUrl((string)($friend['avatar'] ?? ''), $friendUsername); ?>
                                <?php if ($friendAvatar): ?>
                                    <img src="<?= e($friendAvatar) ?>" alt="avatar" class="rounded-circle" width="22" height="22">
                                <?php else: ?>
                                    <i class="bi bi-person-circle"></i>
                                <?php endif; ?>
                                <span>@<?= e($friendUsername) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header"><h5 class="mb-0">Stations list</h5></div>
            <div class="card-body">
                <?php if (empty($adminViewedStations)): ?>
                    <div class="text-muted">No stations</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Serial</th>
                                    <th>Name</th>
                                    <th>Registered at</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminViewedStations as $station): ?>
                                    <tr>
                                        <td><?= e((string)($station['pk_serialNumber'] ?? '')) ?></td>
                                        <td><?= e((string)($station['name'] ?? '')) ?></td>
                                        <td><?= e(formatDateTime((string)($station['registeredAt'] ?? ''))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header"><h5 class="mb-0">Collections list</h5></div>
            <div class="card-body">
                <?php if (empty($adminViewedCollections)): ?>
                    <div class="text-muted">No collections</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Created at</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminViewedCollections as $collection): ?>
                                    <tr>
                                        <td><?= e((string)($collection['pk_collectionID'] ?? '')) ?></td>
                                        <td><?= e((string)($collection['name'] ?? '')) ?></td>
                                        <td><?= e(formatDateTime((string)($collection['createdAt'] ?? ''))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
