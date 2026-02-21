<?php
require_once __DIR__ . '/../includes/header.php';
requireLogin();
require_once __DIR__ . '/../services/friends.php';
require_once __DIR__ . '/../services/users.php';
require_once __DIR__ . '/../services/notifications.php';

$username = $_SESSION['username'];
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_request') {
        $receiver = trim($_POST['receiver'] ?? '');
        if ($receiver && $receiver !== $username) {
            if (areFriends($conn, $username, $receiver)) {
                $err = 'Already friends';
            } elseif (hasPendingRequest($conn, $username, $receiver)) {
                $err = 'Request already sent';
            } else {
                $targetUser = getUserByUsername($conn, $receiver);
                if ($targetUser) {
                    sendFriendRequest($conn, $username, $receiver);
                    createNotification($conn, $receiver, 'friend_request', t('friend_request_sent'), $_SESSION['full_name'] . ' sent you a friend request', '/user/friends.php');
                    $msg = t('friend_request_sent');
                } else {
                    $err = 'User not found';
                }
            }
        }
    } elseif ($action === 'accept') {
        $reqId = (int)($_POST['request_id'] ?? 0);
        if (acceptRequest($conn, $reqId, $username)) {
            // Notify sender
            $stmt = $conn->prepare("SELECT fk_sender FROM request WHERE pk_requestID=?");
            $stmt->bind_param("i", $reqId);
            $stmt->execute();
            $req = $stmt->get_result()->fetch_assoc();
            if ($req) createNotification($conn, $req['fk_sender'], 'friend_accepted', t('friend_accepted'), $_SESSION['full_name'] . ' accepted your friend request', '/user/friends.php');
            $msg = t('friend_accepted');
        }
    } elseif ($action === 'reject') {
        $reqId = (int)($_POST['request_id'] ?? 0);
        rejectRequest($conn, $reqId, $username);
        $msg = t('success');
    } elseif ($action === 'remove') {
        $friend = trim($_POST['friend'] ?? '');
        if ($friend) {
            removeFriend($conn, $username, $friend);
            $msg = t('success');
        }
    }
}

$friends = getFriends($conn, $username);
$pendingRequests = getPendingRequests($conn, $username);

// Search users
$searchResults = [];
$searchQuery = trim($_GET['search'] ?? '');
if ($searchQuery) {
    $like = '%' . $searchQuery . '%';
    $stmt = $conn->prepare("SELECT pk_username, firstName, lastName, avatar FROM user WHERE (pk_username LIKE ? OR firstName LIKE ? OR lastName LIKE ?) AND pk_username != ? LIMIT 20");
    $stmt->bind_param("ssss", $like, $like, $like, $username);
    $stmt->execute();
    $searchResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<h2 class="mb-4"><i class="bi bi-people me-2"></i><?= t('friends') ?></h2>

<?php if ($msg): ?><?= showSuccess($msg) ?><?php endif; ?>
<?php if ($err): ?><?= showError($err) ?><?php endif; ?>

<div class="row g-4">
    <div class="col-md-8">
        <!-- Search -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control" placeholder="<?= t('search') ?> users..." value="<?= e($searchQuery) ?>">
                    <button type="submit" class="btn btn-primary"><?= t('search') ?></button>
                </form>
                <?php if ($searchQuery && $searchResults): ?>
                <div class="mt-3">
                    <?php foreach ($searchResults as $u): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($u['avatar']): ?>
                            <img src="/assets/avatars/presets/<?= e($u['avatar']) ?>" class="rounded-circle" width="32" height="32">
                            <?php else: ?>
                            <i class="bi bi-person-circle fs-4"></i>
                            <?php endif; ?>
                            <span><?= e($u['firstName'] . ' ' . $u['lastName']) ?> <small class="text-muted">(<?= e($u['pk_username']) ?>)</small></span>
                        </div>
                        <?php if (areFriends($conn, $username, $u['pk_username'])): ?>
                            <span class="badge bg-success">Friends</span>
                        <?php elseif (hasPendingRequest($conn, $username, $u['pk_username'])): ?>
                            <span class="badge bg-warning">Pending</span>
                        <?php else: ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="send_request">
                                <input type="hidden" name="receiver" value="<?= e($u['pk_username']) ?>">
                                <button type="submit" class="btn btn-sm btn-primary"><?= t('send_request') ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php elseif ($searchQuery): ?>
                <p class="text-muted mt-2">No users found</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Friends list -->
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><?= t('friends') ?> (<?= count($friends) ?>)</h5></div>
            <div class="card-body">
                <?php if (empty($friends)): ?>
                    <p class="text-muted"><?= t('no_friends') ?></p>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($friends as $f): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($f['avatar'] ?? ''): ?>
                            <img src="/assets/avatars/presets/<?= e($f['avatar']) ?>" class="rounded-circle" width="36" height="36">
                            <?php else: ?>
                            <i class="bi bi-person-circle fs-3"></i>
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold"><?= e($f['firstName'] . ' ' . $f['lastName']) ?></div>
                                <small class="text-muted">@<?= e($f['pk_username']) ?></small>
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="/user/chat.php?with=<?= urlencode($f['pk_username']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-chat"></i>
                            </a>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="friend" value="<?= e($f['pk_username']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove friend?')"><?= t('remove') ?></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pending requests -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><?= t('pending_requests') ?> (<?= count($pendingRequests) ?>)</h5></div>
            <div class="card-body">
                <?php if (empty($pendingRequests)): ?>
                    <p class="text-muted">No pending requests</p>
                <?php else: ?>
                <?php foreach ($pendingRequests as $r): ?>
                <div class="d-flex align-items-center gap-2 py-2 border-bottom">
                    <div class="flex-grow-1">
                        <div><?= e($r['firstName'] . ' ' . $r['lastName']) ?></div>
                        <small class="text-muted"><?= formatDateTime($r['createdAt']) ?></small>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="accept">
                            <input type="hidden" name="request_id" value="<?= $r['pk_requestID'] ?>">
                            <button type="submit" class="btn btn-sm btn-success w-100"><?= t('accept') ?></button>
                        </form>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="request_id" value="<?= $r['pk_requestID'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100"><?= t('reject') ?></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
