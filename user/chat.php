<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../services/chat.php';
require_once __DIR__ . '/../services/friends.php';
requireLogin();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_group') {
        $name = trim($_POST['group_name'] ?? '');
        $desc = trim($_POST['group_description'] ?? '');
        $members = $_POST['members'] ?? [];
        if ($name && $members) {
            $convId = createGroupConversation($conn, $name, $username, $members, $desc);
            header("Location: /user/chat.php?conv=$convId");
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

$activeConvId = isset($_GET['conv']) ? (int)$_GET['conv'] : 0;

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
            <?php if ($activeConv['type'] === 'group'): ?>
            <strong class="chat-group-title" style="cursor:pointer;" data-conv-id="<?= $activeConvId ?>" data-bs-toggle="modal" data-bs-target="#groupInfoModal" title="<?= t('group_info') ?>">
                <?= e($activeConv['display_name'] ?? $activeConv['name'] ?? 'Chat') ?>
                <i class="bi bi-info-circle ms-1 text-muted" style="font-size:0.85rem;"></i>
            </strong>
            <span class="badge bg-secondary"><?= t('group_chat') ?></span>
            <?php else: ?>
            <strong><?= e($activeConv['display_name'] ?? $activeConv['name'] ?? 'Chat') ?></strong>
            <?php endif; ?>
        </div>
        <div class="chat-messages" id="chatMessages">
            <?php
            $messages = getMessages($conn, $activeConvId, 0);
            $lastDate = null;
            foreach ($messages as $m):
                $isOwn = $m['fk_sender'] === $username;
                $msgDate = date('Y-m-d', strtotime($m['createdAt']));
                $msgTime = date('H:i', strtotime($m['createdAt']));
                $msgDateLabel = date('d M Y', strtotime($m['createdAt']));
                if ($msgDate !== $lastDate):
                    $lastDate = $msgDate;
            ?>
            <div class="chat-date-separator"><span><?= e($msgDateLabel) ?></span></div>
            <?php endif; ?>
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
                <small class="text-muted"><?= e($msgTime) ?></small>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="chat-input-area">
            <form id="chatForm" enctype="multipart/form-data">
                <input type="hidden" id="convId" value="<?= $activeConvId ?>">
                <div id="attachmentPreview" class="attachment-preview"></div>
                <div class="d-flex gap-2">
                    <input type="text" id="chatMsg" class="form-control" placeholder="<?= t('type_message') ?>">
                    <label class="btn btn-outline-secondary" title="<?= t('upload_file') ?>">
                        <i class="bi bi-paperclip"></i>
                        <input type="file" id="chatFile" class="d-none" multiple>
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
                        <label class="form-label"><?= t('group_description') ?></label>
                        <input type="text" name="group_description" class="form-control" placeholder="<?= t('description') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= t('add_member') ?></label>
                        <input type="text" id="newGroupSearch" class="form-control form-control-sm mb-2" placeholder="<?= t('search_members') ?>">
                        <div class="add-members-list" id="newGroupMemberList">
                        <?php foreach ($friends as $f): ?>
                        <div class="form-check friend-item">
                            <input class="form-check-input" type="checkbox" name="members[]" value="<?= e($f['pk_username']) ?>" id="m_<?= e($f['pk_username']) ?>">
                            <label class="form-check-label" for="m_<?= e($f['pk_username']) ?>">
                                <?= e($f['firstName'] . ' ' . $f['lastName']) ?> <small class="text-muted">(<?= e($f['pk_username']) ?>)</small>
                            </label>
                        </div>
                        <?php endforeach; ?>
                        </div>
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

<?php if ($activeConv && $activeConv['type'] === 'group'): ?>
<!-- Group Info Modal -->
<div class="modal fade" id="groupInfoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= t('group_info') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="groupInfoContent">
                    <div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>
                </div>
                <!-- Add members section (hidden until opened) -->
                <div id="addMembersSection" class="mt-3 d-none">
                    <h6><?= t('add_members') ?></h6>
                    <input type="text" id="addMemberSearch" class="form-control form-control-sm mb-2" placeholder="<?= t('search_members') ?>">
                    <div class="add-members-list" id="addMemberList">
                        <?php
                        // Get current participants to filter them out client-side
                        foreach ($friends as $f):
                        ?>
                        <div class="form-check add-member-item" data-name="<?= e(strtolower($f['firstName'] . ' ' . $f['lastName'] . ' ' . $f['pk_username'])) ?>">
                            <input class="form-check-input" type="checkbox" value="<?= e($f['pk_username']) ?>" id="am_<?= e($f['pk_username']) ?>">
                            <label class="form-check-label" for="am_<?= e($f['pk_username']) ?>">
                                <?= e($f['firstName'] . ' ' . $f['lastName']) ?> <small class="text-muted">(<?= e($f['pk_username']) ?>)</small>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn btn-sm btn-primary mt-2" id="doAddMembersBtn"><?= t('add_members') ?></button>
                    <button class="btn btn-sm btn-secondary mt-2" id="cancelAddMembersBtn"><?= t('cancel') ?></button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                <button type="button" class="btn btn-outline-primary d-none" id="addMembersBtn"><?= t('add_members') ?></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($activeConv): ?>
<script>
var lastMsgId = <?= !empty($messages) ? (int)end($messages)['pk_messageID'] : 0 ?>;
var lastRenderedDate = <?= !empty($messages) ? json_encode(date('Y-m-d', strtotime(end($messages)['createdAt']))) : 'null' ?>;
var convId = <?= $activeConvId ?>;
var currentUser = <?= json_encode($username) ?>;
var isGroupChat = <?= $activeConv['type'] === 'group' ? 'true' : 'false' ?>;

// Scroll to bottom
var cm = document.getElementById('chatMessages');
cm.scrollTop = cm.scrollHeight;

// --- Attachment preview ---
$('#chatFile').on('change', function() {
    var preview = $('#attachmentPreview');
    preview.empty();
    var files = this.files;
    if (!files.length) return;
    for (var i = 0; i < files.length; i++) {
        var name = files[i].name;
        var pill = $('<span class="attachment-pill">')
            .append($('<span>').text(name))
            .append($('<i class="bi bi-x attachment-pill-close">'));
        preview.append(pill);
    }
});
$(document).on('click', '.attachment-pill-close', function() {
    $('#chatFile').val('');
    $('#attachmentPreview').empty();
});

// --- Send message ---
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
                $('#attachmentPreview').empty();
                loadNewMessages();
            }
        },
        dataType: 'json'
    });
});

// --- Date helpers ---
function msgDateStr(createdAt) {
    // createdAt: "YYYY-MM-DD HH:MM:SS" or similar
    return createdAt ? createdAt.substring(0, 10) : '';
}
function msgTimeStr(createdAt) {
    return createdAt ? createdAt.substring(11, 16) : '';
}
function formatDateLabel(dateStr) {
    // dateStr: "YYYY-MM-DD"
    var d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString(undefined, {day: '2-digit', month: 'short', year: 'numeric'});
}

function appendMessage(m) {
    var isOwn = m.fk_sender === currentUser;
    var dateStr = msgDateStr(m.createdAt);
    if (dateStr && dateStr !== lastRenderedDate) {
        lastRenderedDate = dateStr;
        $('#chatMessages').append('<div class="chat-date-separator"><span>' + formatDateLabel(dateStr) + '</span></div>');
    }
    var html = '<div class="chat-message ' + (isOwn ? 'own' : 'other') + '">';
    if (!isOwn) html += '<small class="text-muted d-block mb-1">' + $('<div>').text(m.firstName + ' ' + m.lastName).html() + '</small>';
    html += '<div class="bubble">';
    if (m.file_name) {
        html += '<a href="/uploads/chat/' + encodeURIComponent(m.file_path.replace(/.*[\\/]/, '')) + '" target="_blank" class="text-white d-block"><i class="bi bi-file-earmark me-1"></i>' + $('<div>').text(m.file_name).html() + '</a>';
    } else {
        html += $('<div>').text(m.message || '').html();
    }
    html += '</div><small class="text-muted">' + msgTimeStr(m.createdAt) + '</small></div>';
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

// --- New group modal search ---
$('#newGroupSearch').on('input', function() {
    var q = $(this).val().toLowerCase();
    $('#newGroupMemberList .friend-item').each(function() {
        var text = $(this).text().toLowerCase();
        $(this).toggle(text.includes(q));
    });
});

<?php if ($activeConv['type'] === 'group'): ?>
// --- Group info modal ---
var groupInfoLoaded = false;
$('#groupInfoModal').on('show.bs.modal', function() {
    if (groupInfoLoaded) return;
    $.get('/api/chat.php', { action: 'get_group_info', chat_id: convId }, function(res) {
        if (!res.success) { $('#groupInfoContent').html('<p class="text-danger">' + (res.error || 'Error') + '</p>'); return; }
        var d = res.data;
        var html = '<h6>' + $('<div>').text(d.name).html() + '</h6>';
        if (d.description) html += '<p class="text-muted small">' + $('<div>').text(d.description).html() + '</p>';
        html += '<strong class="small"><?= t('members') ?></strong>';
        html += '<ul class="list-unstyled group-members-list mt-1">';
        var memberUsernames = [];
        if (d.members.length === 0) {
            html += '<li class="text-muted small"><?= t('no_members_yet') ?></li>';
        } else {
            d.members.forEach(function(m) {
                memberUsernames.push(m.username);
                var badge = m.role === 'owner' ? '<span class="badge bg-warning text-dark ms-1 small"><?= t('group_owner') ?></span>' : '';
                html += '<li class="py-1 border-bottom"><i class="bi bi-person-fill me-1"></i>' + $('<div>').text(m.full_name).html() + ' <small class="text-muted">@' + $('<div>').text(m.username).html() + '</small>' + badge + '</li>';
            });
        }
        html += '</ul>';
        $('#groupInfoContent').html(html);
        groupInfoLoaded = true;
        // Hide already-members from add list
        memberUsernames.forEach(function(u) {
            $('#am_' + CSS.escape(u)).closest('.add-member-item').addClass('already-member').hide();
        });
        // Show add-members button for owners
        var isOwner = d.members.some(function(m){ return m.username === currentUser && m.role === 'owner'; });
        if (isOwner) $('#addMembersBtn').removeClass('d-none');
    }, 'json');
});

$('#addMembersBtn').on('click', function() {
    $('#addMembersSection').removeClass('d-none');
    $(this).addClass('d-none');
});

$('#cancelAddMembersBtn').on('click', function() {
    $('#addMembersSection').addClass('d-none');
    $('#addMembersBtn').removeClass('d-none');
    $('#addMemberSearch').val('');
    $('#addMemberList .add-member-item:not(.already-member)').show();
});

$('#addMemberSearch').on('input', function() {
    var q = $(this).val().toLowerCase();
    $('#addMemberList .add-member-item:not(.already-member)').each(function() {
        var text = $(this).data('name') || '';
        $(this).toggle(text.includes(q));
    });
});

$('#doAddMembersBtn').on('click', function() {
    var selected = [];
    $('#addMemberList input[type=checkbox]:checked').each(function() {
        selected.push($(this).val());
    });
    if (!selected.length) return;
    $.ajax({
        url: '/api/chat.php',
        type: 'POST',
        data: { action: 'add_group_members', chat_id: convId, members: selected },
        traditional: true,
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                groupInfoLoaded = false;
                $('#addMembersSection').addClass('d-none');
                $('#groupInfoContent').html('<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>');
                // Re-trigger load
                $('#groupInfoModal').trigger('show.bs.modal');
            } else {
                alert(res.error || 'Error');
            }
        }
    });
});
<?php endif; ?>
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
