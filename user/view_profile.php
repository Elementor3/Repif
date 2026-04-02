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

if (!$viewUsername || $viewUsername === $username) {
    header('Location: /user/profile.php');
    exit;
}

$profile = getUserByUsername($conn, $viewUsername);
if (!$profile) {
    header('Location: /user/friends.php');
    exit;
}

$isFriend = areFriends($conn, $username, $viewUsername);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center mb-4 gap-2">
    <a href="/user/friends.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i><?= t('back') ?></a>
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
                    <a href="/user/chat.php?with=<?= urlencode($viewUsername) ?>" class="btn btn-primary btn-sm">
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
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
