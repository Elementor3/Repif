<?php
require_once __DIR__ . '/../includes/header.php';
requireLogin();
require_once __DIR__ . '/../services/chat.php';
require_once __DIR__ . '/../services/friends.php';

$username = $_SESSION['username'];
$msg = '';

// Handle opening a private chat with a friend
if (isset($_GET['with'])) {
    $withUser = trim($_GET['with']);
    if (areFriends($conn, $username, $withUser)) {
        $convId = getOrCreatePrivateConversation($conn, $username, $withUser);
        header("Location: /user/chat.php?conv=$convId");
        exit;
    }
}

$activeConvId = isset($_GET['conv']) ? (int)$_GET['conv'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_group') {
        $name = trim($_POST['group_name'] ?? '');
        $members = $_POST['members'] ?? [];
        if ($name && $members) {
            $convId = createGroupConversation($conn, $name, $username, $members);
            header("Location: /user/chat.php?conv=$convId");
            exit;
        }
    }
}

$conversations = getConversations($conn, $username);
$activeConv = null;
if ($activeConvId) {
    if (isParticipant($conn, $activeConvId, $username)) {
        foreach ($conversations as $c) {
            if ($c['pk_conversationID'] == $activeConvId) { $activeConv = $c; break; }
        }
        if (!$activeConv) {
            $stmt = $conn->prepare("SELECT cc.*, '' AS display_name FROM chat_conversation cc WHERE cc.pk_conversationID = ?");
            $stmt->bind_param("i", $activeConvId);
            $stmt->execute();
            $activeConv = $stmt->get_result()->fetch_assoc();
        }
    }
}

$friends = getFriends($conn, $username);
?>
<h2 class="mb-3"><i class="bi bi-chat-dots me-2"></i><?= t('chat') ?></h2>

<div class="chat-container">
    <!-- Sidebar -->
    <div class="chat-sidebar">
        <div class="p-2 border-bottom d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#newGroupModal">
                <i class="bi bi-people-fill me-1"></i><?= t('new_group') ?>
            </button>
        </div>
        <div class="chat-sidebar-list">
            <?php if (empty($conversations)): ?>
                <div class="text-center text-muted py-4 small"><?= t('no_conversations') ?></div>
                <div class="px-2">
                    <?php foreach ($friends as $f): ?>
                    <a href="/user/chat.php?with=<?= urlencode($f['pk_username']) ?>" class="d-block text-decoration-none chat-conv-item">
                        <div class="conv-name"><?= e($f['firstName'] . ' ' . $f['lastName']) ?></div>
                        <div class="conv-preview"><?= t('new_chat') ?></div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $c): ?>
                <a href="/user/chat.php?conv=<?= $c['pk_conversationID'] ?>" class="text-decoration-none chat-conv-item <?= $activeConvId == $c['pk_conversationID'] ? 'active' : '' ?>">
                    <div class="conv-name">
                        <?php if ($c['type'] === 'group'): ?><i class="bi bi-people-fill me-1 text-primary"></i><?php endif; ?>
                        <?= e($c['display_name'] ?? $c['name'] ?? 'Chat') ?>
                    </div>
                    <div class="conv-preview"><?= e($c['last_message'] ?? '') ?></div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main chat area -->
    <div class="chat-main">
        <?php if ($activeConv): ?>
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <strong><?= e($activeConv['display_name'] ?? $activeConv['name'] ?? 'Chat') ?></strong>
            <?php if ($activeConv['type'] === 'group'): ?>
            <span class="badge bg-secondary"><?= t('group_chat') ?></span>
            <?php endif; ?>
        </div>
        <div class="chat-messages" id="chatMessages">
            <?php
            $messages = getMessages($conn, $activeConvId, 0);
            foreach ($messages as $m):
                $isOwn = $m['fk_sender'] === $username;
            ?>
            <div class="chat-message <?= $isOwn ? 'own' : 'other' ?>">
                <?php if (!$isOwn): ?>
                <small class="text-muted d-block mb-1"><?= e($m['firstName'] . ' ' . $m['lastName']) ?></small>
                <?php endif; ?>
                <div class="bubble">
                    <?php if ($m['file_path']): ?>
                    <a href="/uploads/chat/<?= e(basename($m['file_path'])) ?>" target="_blank" class="text-white d-block">
                        <i class="bi bi-file-earmark me-1"></i><?= e($m['file_name']) ?>
                    </a>
                    <?php else: ?>
                    <?= e($m['message'] ?? '') ?>
                    <?php endif; ?>
                </div>
                <small class="text-muted"><?= formatDateTime($m['createdAt']) ?></small>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="chat-input-area">
            <form id="chatForm" enctype="multipart/form-data">
                <input type="hidden" id="convId" value="<?= $activeConvId ?>">
                <div class="d-flex gap-2">
                    <input type="text" id="chatMsg" class="form-control" placeholder="<?= t('type_message') ?>">
                    <label class="btn btn-outline-secondary" title="<?= t('upload_file') ?>">
                        <i class="bi bi-paperclip"></i>
                        <input type="file" id="chatFile" class="d-none">
                    </label>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i></button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="d-flex align-items-center justify-content-center flex-grow-1 text-muted">
            <div class="text-center">
                <i class="bi bi-chat-dots display-1 mb-3"></i>
                <p><?= t('no_conversations') ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- New Group Modal -->
<div class="modal fade" id="newGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= t('new_group') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="create_group">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= t('group_name') ?></label>
                        <input type="text" name="group_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= t('add_member') ?></label>
                        <?php foreach ($friends as $f): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="members[]" value="<?= e($f['pk_username']) ?>" id="m_<?= e($f['pk_username']) ?>">
                            <label class="form-check-label" for="m_<?= e($f['pk_username']) ?>">
                                <?= e($f['firstName'] . ' ' . $f['lastName']) ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= t('create') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($activeConv): ?>
<script>
var lastMsgId = <?= !empty($messages) ? (int)end($messages)['pk_messageID'] : 0 ?>;
var convId = <?= $activeConvId ?>;
var currentUser = <?= json_encode($username) ?>;

// Scroll to bottom
var cm = document.getElementById('chatMessages');
cm.scrollTop = cm.scrollHeight;

// Send message
$('#chatForm').on('submit', function(e) {
    e.preventDefault();
    var msg = $('#chatMsg').val().trim();
    var file = $('#chatFile')[0].files[0];
    if (!msg && !file) return;

    var formData = new FormData();
    formData.append('action', 'send');
    formData.append('conversation_id', convId);
    formData.append('message', msg);
    if (file) formData.append('file', file);

    $.ajax({
        url: '/api/chat.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            if (res.success) {
                $('#chatMsg').val('');
                $('#chatFile').val('');
                loadNewMessages();
            }
        },
        dataType: 'json'
    });
});

function appendMessage(m) {
    var isOwn = m.fk_sender === currentUser;
    var html = '<div class="chat-message ' + (isOwn ? 'own' : 'other') + '">';
    if (!isOwn) html += '<small class="text-muted d-block mb-1">' + $('<div>').text(m.firstName + ' ' + m.lastName).html() + '</small>';
    html += '<div class="bubble">';
    if (m.file_name) {
        html += '<a href="/uploads/chat/' + encodeURIComponent(m.file_path) + '" target="_blank" class="text-white d-block"><i class="bi bi-file-earmark me-1"></i>' + $('<div>').text(m.file_name).html() + '</a>';
    } else {
        html += $('<div>').text(m.message || '').html();
    }
    html += '</div><small class="text-muted">' + m.createdAt + '</small></div>';
    $('#chatMessages').append(html);
    cm.scrollTop = cm.scrollHeight;
}

function loadNewMessages() {
    $.get('/api/chat.php', { action: 'get_messages', conversation_id: convId, since_id: lastMsgId }, function(res) {
        if (res.success && res.messages.length) {
            res.messages.forEach(function(m) {
                appendMessage(m);
                lastMsgId = m.pk_messageID;
            });
        }
    }, 'json');
}

setInterval(loadNewMessages, 3000);
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
