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
        $description = trim($_POST['group_description'] ?? '');
        $members = $_POST['members'] ?? [];
        if ($name && $members) {
            $convId = createGroupConversation($conn, $name, $description, $username, $members);
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
            if ($c['pk_conversationID'] == $activeConvId) {
                $activeConv = $c;
                break;
            }
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
        <div class="p-2 border-bottom">
            <input type="text" id="userSearch" class="form-control form-control-sm mb-2" placeholder="<?= t('search_users') ?>">
            <div id="searchResults" class="chat-search-results d-none"></div>
            <button class="btn btn-sm btn-primary w-100" data-bs-toggle="modal" data-bs-target="#newGroupModal">
                <i class="bi bi-people-fill me-1"></i><?= t('new_group') ?>
            </button>
        </div>
        <div class="chat-sidebar-list">
            <?php if (empty($conversations)): ?>
                <div class="text-center text-muted py-4 small"><?= t('no_conversations') ?></div>
                <div class="px-2">
                    <?php foreach ($friends as $f): ?>
                        <a href="/user/chat.php?with=<?= urlencode($f['pk_username']) ?>" class="d-block text-decoration-none chat-conv-item">
                            <span class="conv-avatar"><i class="bi bi-person-circle"></i></span>
                            <div class="conv-info">
                                <div class="conv-name"><?= e($f['firstName'] . ' ' . $f['lastName']) ?></div>
                                <div class="conv-preview"><?= t('new_chat') ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $c): ?>
                    <a href="/user/chat.php?conv=<?= $c['pk_conversationID'] ?>" class="text-decoration-none chat-conv-item <?= $activeConvId == $c['pk_conversationID'] ? 'active' : '' ?>">
                        <span class="conv-avatar">
                            <?php if ($c['type'] === 'group'): ?>
                                <i class="bi bi-people-fill"></i>
                            <?php else: ?>
                                <i class="bi bi-person-circle"></i>
                            <?php endif; ?>
                        </span>
                        <div class="conv-info">
                            <div class="conv-name"><?= e($c['display_name'] ?? $c['name'] ?? 'Chat') ?></div>
                            <div class="conv-preview"><?= e($c['last_message'] ?? '') ?></div>
                        </div>
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
                    <strong id="groupTitleLink" style="cursor:pointer;" data-conv-id="<?= $activeConvId ?>">
                        <?= e($activeConv['display_name'] ?? $activeConv['name'] ?? 'Chat') ?>
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
                    if ($msgDate !== $lastDate):
                        $lastDate = $msgDate;
                        $today = date('Y-m-d');
                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                        if ($msgDate === $today) {
                            $dateLabel = t('today');
                        } elseif ($msgDate === $yesterday) {
                            $dateLabel = t('yesterday');
                        } else {
                            $dateLabel = date('d M Y', strtotime($msgDate));
                        }
                ?>
                        <div class="chat-date-separator"><span><?= e($dateLabel) ?></span></div>
                    <?php endif; ?>
                    <div class="chat-message <?= $isOwn ? 'own' : 'other' ?>">
                        <?php if (!$isOwn): ?>
                            <small class="text-muted d-block mb-1"><?= e($m['firstName'] . ' ' . $m['lastName']) ?></small>
                        <?php endif; ?>
                        <div class="bubble">
                            <?php if ($m['file_path']): ?>
                                <?php
                                $viewUrl     = '/download_chat_file.php?id=' . (int)$m['pk_messageID'] . '&mode=view';
                                $downloadUrl = '/download_chat_file.php?id=' . (int)$m['pk_messageID'] . '&mode=download';
                                $fileName    = $m['file_name'] ?: basename($m['file_path']);
                                ?>
                                <div class="d-flex align-items-center justify-content-between">
                                    <a href="<?= $viewUrl ?>" target="_blank" class="text-white text-truncate me-2">
                                        <i class="bi bi-file-earmark me-1"></i><?= e($fileName) ?>
                                    </a>
                                    <a href="<?= $downloadUrl ?>" class="btn btn-sm btn-outline-light">
                                        <i class="bi bi-download"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <?= e($m['message'] ?? '') ?>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted"><?= e($msgTime) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="chat-input-area">
                <div id="attachmentPreview" class="attachment-preview"></div>
                <form id="chatForm" enctype="multipart/form-data">
                    <input type="hidden" id="convId" value="<?= $activeConvId ?>">
                    <div class="d-flex gap-2">
                        <input type="text" id="chatMsg" class="form-control" placeholder="<?= t('type_message') ?>">
                        <label class="btn btn-outline-secondary" title="<?= t('upload_file') ?>">
                            <i class="bi bi-paperclip"></i>
                            <input type="file" id="chatFile" name="files[]" class="d-none" multiple>
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
            <div class="modal-body">
                <div id="groupModalError" class="alert alert-danger d-none"></div>
                <div class="mb-3">
                    <label class="form-label"><?= t('group_name') ?></label>
                    <input type="text" id="groupName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= t('group_description') ?></label>
                    <input type="text" id="groupDescription" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= t('add_member') ?></label>
                    <input type="text" id="newGroupMemberSearch" class="form-control form-control-sm mb-2" placeholder="<?= t('search_members') ?>">
                    <div id="newGroupMemberList" class="group-member-list mb-1">
                        <?php foreach ($friends as $f): ?>
                            <div class="group-member-item form-check">
                                <input class="form-check-input group-member-check" type="checkbox" value="<?= e($f['pk_username']) ?>" id="m_<?= e($f['pk_username']) ?>">
                                <label class="form-check-label" for="m_<?= e($f['pk_username']) ?>">
                                    <?= e($f['firstName'] . ' ' . $f['lastName']) ?>
                                    <small class="text-muted d-block">@<?= e($f['pk_username']) ?></small>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($friends)): ?>
                            <div class="text-muted small px-3 py-1"><?= t('no_friends') ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                <button type="button" id="createGroupBtn" class="btn btn-primary"><?= t('create') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Group Info Modal -->
<div class="modal fade" id="groupInfoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="groupInfoModalTitle"><?= t('group_info') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="groupInfoError" class="alert alert-danger d-none"></div>
                <div id="groupInfoSuccess" class="alert alert-success d-none"></div>
                <div class="mb-3">
                    <label class="form-label mb-0"><?= t('group_name') ?></label>
                    <div id="groupInfoNameView" class="fw-semibold"></div>
                    <div id="groupInfoNameEdit" class="d-none mt-1">
                        <input type="text" id="groupInfoNameInput" class="form-control form-control-sm">
                    </div>
                </div>

                <div id="groupInfoDescriptionWrapper" class="mb-3">
                    <label class="form-label mb-1"><?= t('group_description') ?></label>
                    <p id="groupInfoDescription" class="text-muted small mb-1"></p>
                    <div id="groupInfoDescriptionEdit" class="d-none">
                        <textarea id="groupInfoDescriptionInput" class="form-control form-control-sm mb-1" rows="2"></textarea>
                    </div>
                    <button type="button" id="groupInfoSaveBtn" class="btn btn-sm btn-primary"><?= t('save') ?></button>
                </div>
                <h6><?= t('members') ?></h6>
                <ul id="groupInfoMembers" class="list-unstyled mb-3"></ul>
                <hr id="groupInfoAddDivider" class="d-none">
                <div id="groupInfoAddSection" class="d-none">
                    <h6><?= t('add_members') ?></h6>
                    <input type="text" id="groupInfoMemberSearch" class="form-control form-control-sm mb-2" placeholder="<?= t('search_members') ?>">
                    <div id="groupInfoAddList" class="group-member-list mb-2"></div>
                    <button type="button" id="groupInfoAddBtn" class="btn btn-sm btn-primary"><?= t('add') ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($activeConv): ?>
    <script>
        var chatLastMsgId = <?= !empty($messages) ? (int)end($messages)['pk_messageID'] : 0 ?>;
        var chatConvId = <?= $activeConvId ?>;
        var chatCurrentUser = <?= json_encode($username) ?>;
        var chatIsGroup = <?= $activeConv['type'] === 'group' ? 'true' : 'false' ?>;
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var chatSelectedFiles = [];
            var cm = document.getElementById('chatMessages');
            cm.scrollTop = cm.scrollHeight;

            // Attachment preview with multiple files and per-file remove
            function renderAttachmentPreview() {
                var preview = $('#attachmentPreview');
                preview.empty();
                chatSelectedFiles.forEach(function(f, idx) {
                    var pill = $('<span class="attachment-pill me-1 mb-1"></span>');
                    pill.append($('<span></span>').text(f.name));
                    var closeBtn = $('<button type="button" class="attachment-pill-close ms-1" aria-label="Remove">&times;</button>');
                    closeBtn.on('click', function() {
                        // remove only this file
                        chatSelectedFiles.splice(idx, 1);
                        renderAttachmentPreview();
                    });
                    pill.append(closeBtn);
                    preview.append(pill);
                });
                if (!chatSelectedFiles.length) {
                    preview.empty();
                }
            }

            $('#chatFile').on('change', function() {
                var files = Array.from(this.files || []);
                if (files.length) {
                    chatSelectedFiles = chatSelectedFiles.concat(files);
                }
                // clear input so same files can be selected again later
                $('#chatFile').val('');
                renderAttachmentPreview();
            });

            // Send message (multiple files + text)
            $('#chatForm').on('submit', function(e) {
                e.preventDefault();
                var msg = $('#chatMsg').val().trim();

                if (!msg && !chatSelectedFiles.length) return;

                var formData = new FormData();
                formData.append('action', 'send');
                formData.append('conversation_id', chatConvId);
                formData.append('message', msg);

                chatSelectedFiles.forEach(function(f) {
                    formData.append('files[]', f);
                });

                $.ajax({
                    url: '/api/chat.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            $('#chatMsg').val('');
                            chatSelectedFiles = [];
                            $('#chatFile').val('');
                            $('#attachmentPreview').empty();
                            loadNewMessages();
                            if (res.errors && res.errors.length) {
                                alert(res.errors.join('\n'));
                            }
                        } else if (res.message) {
                            alert(res.message);
                        }
                    }
                });
            });

            function timeFromDateStr(dateStr) {
                if (!dateStr) return '';
                // dateStr: "Y-m-d H:i:s" or "Y-m-d H:i"
                var parts = dateStr.split(' ');
                if (parts.length >= 2) return parts[1].substring(0, 5);
                return '';
            }

            function appendMessage(m) {
                //console.log('appendMessage called with', m);
                var isOwn = m.fk_sender === chatCurrentUser;
                var html = '<div class="chat-message ' + (isOwn ? 'own' : 'other') + '">';
                if (!isOwn) html += '<small class="text-muted d-block mb-1">' + esc(m.firstName + ' ' + m.lastName) + '</small>';
                html += '<div class="bubble">';
                if (m.file_name) {
                    var viewUrl     = '/download_chat_file.php?id=' + encodeURIComponent(m.pk_messageID) + '&mode=view';
                    var downloadUrl = '/download_chat_file.php?id=' + encodeURIComponent(m.pk_messageID) + '&mode=download';
                    html += '<div class="d-flex align-items-center justify-content-between">';
                    html += '<a href="' + viewUrl + '" target="_blank" class="text-white text-truncate me-2">';
                    html += '<i class="bi bi-file-earmark me-1"></i>' + esc(m.file_name) + '</a>';
                    html += '<a href="' + downloadUrl + '" class="btn btn-sm btn-outline-light" download>';
                    html += '<i class="bi bi-download"></i></a>';
                    html += '</div>';
                } else {
                    html += esc(m.message || '');
                }
                html += '</div><small class="text-muted">' + esc(timeFromDateStr(m.createdAt)) + '</small></div>';
                $('#chatMessages').append(html);
                cm.scrollTop = cm.scrollHeight;
            }

            function loadNewMessages() {
                $.get('/api/chat.php', {
                    action: 'get_messages',
                    conversation_id: chatConvId,
                    since_id: chatLastMsgId
                }, function(res) {
                    //console.log('loadNewMessages -> response', res);
                    if (res.success && res.messages.length) {
                        res.messages.forEach(function(m) {
                            appendMessage(m);
                            chatLastMsgId = m.pk_messageID;
                        });
                    }
                }, 'json');
            }

            setInterval(loadNewMessages, 3000);

            // Group info modal
            if (chatIsGroup) {

                function loadGroupInfo(convId) {
                    $('#groupInfoError').addClass('d-none');
                    $('#groupInfoSuccess').addClass('d-none');
                    $('#groupInfoMembers').empty();
                    $('#groupInfoAddList').empty();
                    $('#groupInfoMemberSearch').val('');

                    $.get('/api/chat.php', {
                        action: 'get_group_info',
                        chat_id: convId
                    }, function(res) {
                        if (!res.success) {
                            $('#groupInfoError').removeClass('d-none')
                                .text(res.message || '<?= t('error_occurred') ?>');
                            $('#groupInfoModal').modal('show');
                            return;
                        }
                        var d = res.data;
                        $('#groupInfoModalTitle').text(d.name || '<?= t('group_info') ?>');
                        var isOwner = d.createdBy === chatCurrentUser;

                        // Name
                        $('#groupInfoNameView').text(d.name || '<?= t('group_info') ?>');
                        $('#groupInfoNameInput').val(d.name || '');

                        // Description
                        if (isOwner) {
                            $('#groupInfoNameEdit').removeClass('d-none');

                            $('#groupInfoDescription').addClass('d-none');
                            $('#groupInfoDescriptionEdit').removeClass('d-none');
                            $('#groupInfoDescriptionInput').val(d.description || '');
                        } else {
                            $('#groupInfoNameEdit').addClass('d-none');

                            $('#groupInfoDescription').removeClass('d-none').text(d.description || '');
                            $('#groupInfoDescriptionEdit').addClass('d-none');
                        }

                        // Members list
                        var ul = $('#groupInfoMembers');
                        if (d.members && d.members.length) {
                            $.each(d.members, function(i, m) {
                                var badge = m.role === 'owner' ?
                                    ' <span class="badge bg-secondary ms-1"><?= t('group_owner') ?></span>' :
                                    '';
                                var removeBtn = '';
                                if (isOwner && m.role !== 'owner') {
                                    removeBtn =
                                        ' <button type="button" class="btn btn-sm btn-outline-danger group-remove-member ms-1 py-0 px-1" ' +
                                        'data-username="' + esc(m.pk_username) + '" ' +
                                        'title="<?= t('remove_member') ?>">&times;</button>';
                                }
                                ul.append(
                                    '<li class="py-1 d-flex align-items-center">' +
                                    '<span><i class="bi bi-person me-1"></i>' +
                                    esc(m.firstName + ' ' + m.lastName + ' (@' + m.pk_username + ')') +
                                    badge +
                                    '</span>' +
                                    removeBtn +
                                    '</li>'
                                );
                            });
                        } else {
                            ul.append('<li class="text-muted small"><?= t('no_members_yet') ?></li>');
                        }

                        // Add members section (only if owner)
                        if (isOwner) {
                            $('#groupInfoAddDivider').removeClass('d-none');
                            $('#groupInfoAddSection').removeClass('d-none');
                            var addList = $('#groupInfoAddList');
                            addList.empty();
                            if (d.addable_friends && d.addable_friends.length) {
                                $.each(d.addable_friends, function(i, f) {
                                    var id = 'gaf_' + f.pk_username;
                                    addList.append(
                                        '<div class="form-check group-member-item px-3 py-1">' +
                                        '<input class="form-check-input group-member-check" type="checkbox" ' +
                                        'value="' + esc(f.pk_username) + '" id="' + esc(id) + '">' +
                                        '<label class="form-check-label" for="' + esc(id) + '">' +
                                        esc(f.firstName + ' ' + f.lastName) +
                                        ' <small class="text-muted d-block">@' + esc(f.pk_username) + '</small>' +
                                        '</label>' +
                                        '</div>'
                                    );
                                });
                            } else {
                                addList.append('<div class="text-muted small px-3 py-1"><?= t('no_friends_to_add') ?></div>');
                            }
                        } else {
                            $('#groupInfoAddDivider').addClass('d-none');
                            $('#groupInfoAddSection').addClass('d-none');
                        }

                        $('#groupInfoModal').modal('show');
                    }, 'json').fail(function() {
                        $('#groupInfoError').removeClass('d-none')
                            .text('<?= t('error_occurred') ?>');
                        $('#groupInfoModal').modal('show');
                    });
                }

                // Open modal
                $('#groupTitleLink').on('click', function() {
                    var convId = $(this).data('conv-id');
                    loadGroupInfo(convId);
                });

                // Member search filter (keep your current logic but use .group-member-item)
                $('#groupInfoMemberSearch').on('input', function() {
                    var q = $(this).val().toLowerCase();
                    $('#groupInfoAddList .group-member-item').each(function() {
                        var text = $(this).text().toLowerCase();
                        $(this).toggle(text.indexOf(q) !== -1);
                    });
                });

                // Add members submit
                $('#groupInfoAddBtn').on('click', function() {
                    var selected = [];
                    $('#groupInfoAddList .group-member-check:checked').each(function() {
                        selected.push($(this).val());
                    });
                    if (!selected.length) return;

                    $.post('/api/chat.php', {
                        action: 'add_group_members',
                        chat_id: chatConvId,
                        'members[]': selected
                    }, function(res) {
                        if (res.success) {
                            $('#groupInfoSuccess').removeClass('d-none')
                                .text('<?= t('add_members_success') ?>');
                            $('#groupInfoError').addClass('d-none');
                            // re-load full info so names, roles, and ordering are correct
                            loadGroupInfo(chatConvId);
                        } else {
                            $('#groupInfoError').removeClass('d-none')
                                .text(res.message || '<?= t('error_occurred') ?>');
                            $('#groupInfoSuccess').addClass('d-none');
                        }
                    }, 'json').fail(function() {
                        $('#groupInfoError').removeClass('d-none')
                            .text('<?= t('error_occurred') ?>');
                    });
                });
                // Save group name + description (owner only)
                $('#groupInfoSaveBtn').on('click', function() {
                    var name = $('#groupInfoNameInput').val().trim();
                    var desc = $('#groupInfoDescriptionInput').val();

                    $.post('/api/chat.php', {
                        action: 'update_group',
                        chat_id: chatConvId,
                        name: name,
                        description: desc
                    }, function(res) {
                        if (res.success) {
                            $('#groupInfoSuccess').removeClass('d-none').text('<?= t('success') ?>');
                            $('#groupInfoError').addClass('d-none');

                            if (res.data && res.data.name !== undefined) {
                                var newName = res.data.name || '<?= t('group_info') ?>';
                                $('#groupInfoNameView').text(newName);
                                $('#groupInfoNameInput').val(newName);
                                $('#groupTitleLink').text(newName);
                            }
                            if (res.data && res.data.description !== undefined) {
                                var newDesc = res.data.description || '';
                                $('#groupInfoDescription').text(newDesc);
                                $('#groupInfoDescriptionInput').val(newDesc);
                            }
                        } else {
                            $('#groupInfoError').removeClass('d-none').text(res.message || '<?= t('error_occurred') ?>');
                            $('#groupInfoSuccess').addClass('d-none');
                        }
                    }, 'json').fail(function() {
                        $('#groupInfoError').removeClass('d-none').text('<?= t('error_occurred') ?>');
                    });
                })

                // Remove group member
                $(document).on('click', '.group-remove-member', function() {
                    var username = $(this).data('username');
                    if (!username) return;
                    if (!confirm('<?= t('confirm_remove_member') ?>')) return;

                    $.post('/api/chat.php', {
                        action: 'remove_group_member',
                        chat_id: chatConvId,
                        member_username: username
                    }, function(res) {
                        if (res.success) {
                            $('#groupInfoSuccess').removeClass('d-none')
                                .text('<?= t('member_removed') ?>');
                            $('#groupInfoError').addClass('d-none');
                            // re-load full info so lists stay in sync
                            loadGroupInfo(chatConvId);
                        } else {
                            $('#groupInfoError').removeClass('d-none')
                                .text(res.message || '<?= t('error_occurred') ?>');
                            $('#groupInfoSuccess').addClass('d-none');
                        }
                    }, 'json').fail(function() {
                        $('#groupInfoError').removeClass('d-none')
                            .text('<?= t('error_occurred') ?>');
                    });
                });
            }
        });
    </script>
<?php endif; ?>

<script>
    function esc(str) {
        return $('<div>').text(str).html();
    }
    document.addEventListener('DOMContentLoaded', function() {
        // User search
        var searchTimer;
        $('#userSearch').on('input', function() {
            clearTimeout(searchTimer);
            var q = $(this).val().trim();
            if (q.length < 2) {
                $('#searchResults').addClass('d-none').html('');
                return;
            }
            searchTimer = setTimeout(function() {
                $.get('/api/chat.php', {
                    action: 'search_users',
                    query: q
                }, function(res) {
                    if (!res.success || !res.users.length) {
                        $('#searchResults').addClass('d-none').html('');
                        return;
                    }
                    var html = '';
                    $.each(res.users, function(i, u) {
                        html += '<div class="chat-search-item" data-username="' + esc(u.pk_username) + '">' +
                            '<i class="bi bi-person me-1"></i>' + esc(u.firstName + ' ' + u.lastName) +
                            ' <small class="text-muted">@' + esc(u.pk_username) + '</small></div>';
                    });
                    $('#searchResults').removeClass('d-none').html(html);
                }, 'json').fail(function() {});
            }, 300);
        });

        $(document).on('click', '.chat-search-item', function() {
            var otherUser = $(this).data('username');
            $('#userSearch').val('');
            $('#searchResults').addClass('d-none').html('');
            $.post('/api/chat.php', {
                action: 'create_private_chat',
                with_user: otherUser
            }, function(res) {
                if (res.success) {
                    window.location.href = '/user/chat.php?conv=' + res.conversation_id;
                } else {
                    alert(res.message || '<?= t('error_occurred') ?>');
                }
            }, 'json').fail(function() {
                alert('<?= t('error_occurred') ?>');
            });
        });

        // New Group member search filter
        $('#newGroupMemberSearch').on('input', function() {
            var q = $(this).val().toLowerCase();
            $('#newGroupMemberList .group-member-item').each(function() {
                var label = $(this).find('label').text().toLowerCase();
                $(this).toggle(label.indexOf(q) !== -1);
            });
        });

        // Create group via AJAX
        $('#createGroupBtn').on('click', function() {
            var name = $('#groupName').val().trim();
            var description = $('#groupDescription').val().trim();
            var members = [];
            $('.group-member-check:checked').each(function() {
                members.push($(this).val());
            });
            if (!name) {
                $('#groupModalError').removeClass('d-none').text('<?= t('group_name_required') ?>');
                return;
            }
            if (members.length < 2) {
                $('#groupModalError').removeClass('d-none').text('<?= t('select_min_2_members') ?>');
                return;
            }
            $('#groupModalError').addClass('d-none');
            $.post('/api/chat.php', {
                action: 'create_group_chat',
                group_name: name,
                group_description: description,
                'members[]': members
            }, function(res) {
                if (res.success) {
                    window.location.href = '/user/chat.php?conv=' + res.conversation_id;
                } else {
                    $('#groupModalError').removeClass('d-none').text(res.message || '<?= t('error_occurred') ?>');
                }
            }, 'json').fail(function() {
                $('#groupModalError').removeClass('d-none').text('<?= t('error_occurred') ?>');
            });
        });

        $('#newGroupModal').on('hidden.bs.modal', function() {
            $('#groupName').val('');
            $('#groupDescription').val('');
            $('.group-member-check').prop('checked', false);
            $('#newGroupMemberSearch').val('');
            $('#newGroupMemberList .group-member-item').show();
            $('#groupModalError').addClass('d-none');
        });
    });
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>