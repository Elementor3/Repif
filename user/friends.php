<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../services/friends.php';
require_once __DIR__ . '/../services/users.php';
require_once __DIR__ . '/../services/notifications.php';
requireLogin();

$username = $_SESSION['username'];
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_request') {
        $receiver = trim($_POST['receiver'] ?? '');
        if ($receiver && $receiver !== $username) {
            if (areFriends($conn, $username, $receiver)) {
                $err = t('already_friends');
            } elseif (hasPendingRequest($conn, $username, $receiver)) {
                $err = t('request_already_sent');
            } elseif (hasPendingRequest($conn, $receiver, $username)) {
                $err = t('incoming_request_exists');
            } else {
                $targetUser = getUserByUsername($conn, $receiver);
                if ($targetUser) {
                    sendFriendRequest($conn, $username, $receiver);
                    createNotification($conn, $receiver, 'friend_request', t('friend_request_sent'), $_SESSION['full_name'] . ' sent you a friend request', '/user/friends.php');
                } else {
                    $err = t('user_not_found');
                }
            }
        }
    } elseif ($action === 'accept') {
        $reqId = (int)($_POST['request_id'] ?? 0);
        if (acceptRequest($conn, $reqId, $username)) {
            $stmt = $conn->prepare("SELECT fk_sender FROM request WHERE pk_requestID=?");
            $stmt->bind_param("i", $reqId);
            $stmt->execute();
            $req = $stmt->get_result()->fetch_assoc();
            if ($req) createNotification($conn, $req['fk_sender'], 'friend_accepted', t('friend_accepted'), $_SESSION['full_name'] . ' accepted your friend request', '/user/friends.php');
        }
    } elseif ($action === 'reject') {
        $reqId = (int)($_POST['request_id'] ?? 0);
        rejectRequest($conn, $reqId, $username);
    } elseif ($action === 'remove') {
        $friend = trim($_POST['friend'] ?? '');
        if ($friend) {
            removeFriend($conn, $username, $friend);
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

$friends = getFriends($conn, $username);
$pendingRequests = getPendingRequests($conn, $username);
$incomingRequests = array_values(array_filter($pendingRequests, function ($r) {
    return ($r['direction'] ?? '') === 'incoming';
}));
$outgoingRequests = array_values(array_filter($pendingRequests, function ($r) {
    return ($r['direction'] ?? '') === 'outgoing';
}));
?>
<h2 class="mb-4"><i class="bi bi-people me-2"></i><?= t('friends') ?></h2>

<?php if ($err): ?><?= showError($err) ?><?php endif; ?>

<div class="row g-4">
    <div class="col-md-8">
        <!-- Search -->
        <div class="card mb-4">
            <div class="card-body">
                <input type="text" id="friendsSearch" class="form-control form-control-sm" placeholder="<?= t('search_users') ?>">
                <div id="friendsSearchResults"
                    class="chat-search-results d-none mt-2"
                    data-no-users-msg="<?= e(t('no_users_found')) ?>"
                    data-error-msg="<?= e(t('error_occurred')) ?>"
                    data-send-request-label="<?= e(t('send_request')) ?>"
                    data-friends-label="<?= e(t('friends')) ?>"
                    data-no-friends-msg="<?= e(t('no_friends')) ?>"
                    data-no-pending-msg="<?= e(t('no_pending_requests')) ?>"
                    data-incoming-request-label="<?= e(t('incoming_request')) ?>"
                    data-outgoing-request-label="<?= e(t('outgoing_request')) ?>"
                    data-cancel-label="<?= e(t('cancel')) ?>"
                    data-remove-label="<?= e(t('remove')) ?>"
                    data-chat-label="<?= e(t('chat')) ?>"
                    data-remove-confirm-msg="Remove friend?"
                    data-view-profile-label="<?= e(t('view_profile')) ?>"></div>
            </div>
        </div>

        <!-- Friends list -->
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><?= t('friends') ?> (<span id="friendsCount"><?= count($friends) ?></span>)</h5></div>
            <div class="card-body">
                <p id="friendsEmpty" class="text-muted <?= empty($friends) ? '' : 'd-none' ?>"><?= t('no_friends') ?></p>
                <div id="friendsList" class="list-group list-group-flush">
                    <?php foreach ($friends as $f): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center" data-friend-username="<?= e($f['pk_username']) ?>">
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
                            <a href="/user/view_profile.php?user=<?= urlencode($f['pk_username']) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-person"></i>
                            </a>
                            <a href="/user/chat.php?with=<?= urlencode($f['pk_username']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-chat"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger js-remove-friend" data-friend="<?= e($f['pk_username']) ?>" title="<?= e(t('remove')) ?>">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending requests -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><?= t('pending_requests') ?></h5></div>
            <div class="card-body">
                <h6 class="mb-2"><?= t('incoming_requests') ?> (<span id="incomingCount"><?= count($incomingRequests) ?></span>)</h6>
                <div id="incomingRequestsList" class="mb-3">
                    <?php foreach ($incomingRequests as $r): ?>
                    <div class="d-flex align-items-center gap-2 py-2 border-bottom" data-request-id="<?= (int)$r['pk_requestID'] ?>">
                        <div>
                            <?php if (!empty($r['avatar'])): ?>
                            <img src="/assets/avatars/presets/<?= e($r['avatar']) ?>" class="rounded-circle" width="36" height="36" alt="avatar">
                            <?php else: ?>
                            <i class="bi bi-person-circle fs-3"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= e($r['firstName'] . ' ' . $r['lastName']) ?></div>
                            <small class="text-muted d-block">@<?= e($r['pk_username']) ?></small>
                            <small class="text-muted"><?= e(date('d.m.Y H:i', strtotime($r['createdAt']))) ?></small>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <a href="/user/view_profile.php?user=<?= urlencode($r['pk_username']) ?>" class="btn btn-sm btn-outline-secondary" title="<?= e(t('view_profile')) ?>">
                                <i class="bi bi-person"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-success js-incoming-action" data-action="accept" data-request-id="<?= (int)$r['pk_requestID'] ?>" title="<?= e(t('accept')) ?>">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger js-incoming-action" data-action="reject" data-request-id="<?= (int)$r['pk_requestID'] ?>" title="<?= e(t('reject')) ?>">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p id="incomingEmpty" class="text-muted <?= empty($incomingRequests) ? '' : 'd-none' ?>"><?= t('no_pending_requests') ?></p>

                <h6 class="mb-2"><?= t('outgoing_requests') ?> (<span id="outgoingCount"><?= count($outgoingRequests) ?></span>)</h6>
                <div id="outgoingRequestsList">
                    <?php foreach ($outgoingRequests as $r): ?>
                    <div class="d-flex align-items-center gap-2 py-2 border-bottom" data-request-id="<?= (int)$r['pk_requestID'] ?>" data-outgoing-username="<?= e($r['pk_username']) ?>">
                        <div>
                            <?php if (!empty($r['avatar'])): ?>
                            <img src="/assets/avatars/presets/<?= e($r['avatar']) ?>" class="rounded-circle" width="36" height="36" alt="avatar">
                            <?php else: ?>
                            <i class="bi bi-person-circle fs-3"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= e($r['firstName'] . ' ' . $r['lastName']) ?></div>
                            <small class="text-muted d-block">@<?= e($r['pk_username']) ?></small>
                            <small class="text-muted"><?= e(date('d.m.Y H:i', strtotime($r['createdAt']))) ?></small>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <a href="/user/view_profile.php?user=<?= urlencode($r['pk_username']) ?>" class="btn btn-sm btn-outline-secondary" title="<?= e(t('view_profile')) ?>">
                                <i class="bi bi-person"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger js-cancel-request" data-request-id="<?= (int)$r['pk_requestID'] ?>" title="<?= e(t('cancel')) ?>">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p id="outgoingEmpty" class="text-muted <?= empty($outgoingRequests) ? '' : 'd-none' ?>"><?= t('no_pending_requests') ?></p>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
