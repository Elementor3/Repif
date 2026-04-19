<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../services/chat.php';
require_once __DIR__ . '/../../services/friends.php';
requireLogin();

$username = $_SESSION['username'];
$msg = '';
$backParam = trim((string)($_GET['back'] ?? ''));

function resolveSafeChatBackUrl(string $candidate): string {
    if ($candidate === '') {
        return '';
    }

    $parts = parse_url($candidate);
    if ($parts === false) {
        return '';
    }

    if (isset($parts['scheme']) || isset($parts['host'])) {
        return '';
    }

    $path = (string)($parts['path'] ?? '');
    if ($path === '' || strncmp($path, '/user/', 6) !== 0) {
        return '';
    }

    $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
    return $path . $query;
}

$chatBackUrl = resolveSafeChatBackUrl($backParam);
$chatBackQuery = $chatBackUrl !== '' ? ('&back=' . urlencode($chatBackUrl)) : '';

// Handle opening a private chat with a friend
if (isset($_GET['with'])) {
    $withUser = trim($_GET['with']);
    if ($withUser !== '' && $withUser !== $username) {
        $stmt = $conn->prepare("SELECT pk_username FROM user WHERE pk_username = ? LIMIT 1");
        $stmt->bind_param("s", $withUser);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        if ($exists) {
            $convId = getOrCreatePrivateConversation($conn, $username, $withUser);
            $redirectUrl = '/user/chat.php?conv=' . (int)$convId;
            if ($chatBackUrl !== '') {
                $redirectUrl .= '&back=' . urlencode($chatBackUrl);
            }
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_group') {
        $name = trim($_POST['group_name'] ?? '');
        $description = trim($_POST['group_description'] ?? '');
        $members = (array)($_POST['members'] ?? []);
        if ($name) {
            $convId = createGroupConversation($conn, $name, $description, $username, $members);
            $redirectUrl = '/user/chat.php?conv=' . (int)$convId;
            if ($chatBackUrl !== '') {
                $redirectUrl .= '&back=' . urlencode($chatBackUrl);
            }
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';

$activeConvId = isset($_GET['conv']) ? (int)$_GET['conv'] : 0;

if ($activeConvId && isParticipant($conn, $activeConvId, $username)) {
    markConversationRead($conn, $activeConvId, $username);
}

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

            if ($activeConv && ($activeConv['type'] ?? '') === 'private') {
                $stmt2 = $conn->prepare("SELECT u.pk_username, u.firstName, u.lastName, u.avatar FROM chat_participant cp JOIN user u ON cp.fk_user = u.pk_username WHERE cp.fk_conversation = ? AND cp.fk_user != ? LIMIT 1");
                $stmt2->bind_param("is", $activeConvId, $username);
                $stmt2->execute();
                $other = $stmt2->get_result()->fetch_assoc();
                if ($other) {
                    $activeConv['display_name'] = $other['firstName'] . ' ' . $other['lastName'];
                    $activeConv['other_username'] = $other['pk_username'];
                    $activeConv['other_avatar'] = $other['avatar'];
                } else {
                    $activeConv['display_name'] = getUnknownUserMarker();
                    $activeConv['other_username'] = '';
                    $activeConv['other_avatar'] = '';
                }
            }
        }
    }
}

$initialMessages = [];
if ($activeConv) {
    $initialMessages = getMessages($conn, $activeConvId, 0);
}

$isDeletedUserMarker = function (?string $value): bool {
    $marker = trim((string)($value ?? ''));
    return $marker === getUnknownUserMarker();
};

$renderChatName = function (?string $displayName, ?string $name) use ($isDeletedUserMarker): string {
    $candidate = trim((string)($displayName ?? ''));
    if ($isDeletedUserMarker($candidate)) {
        return t('deleted_user');
    }
    if ($candidate !== '') {
        return $candidate;
    }

    $fallback = trim((string)($name ?? ''));
    if ($isDeletedUserMarker($fallback)) {
        return t('deleted_user');
    }
    return $fallback !== '' ? $fallback : 'Chat';
};

$renderSystemText = function (array $message): string {
    $systemType = (string)($message['system_type'] ?? '');
    $actorName = trim((string)($message['system_actor_name'] ?? ''));
    $actorUsername = trim((string)($message['system_actor_username'] ?? ''));
    $actor = $actorName !== '' ? $actorName : ($actorUsername !== '' ? $actorUsername : t('deleted_user'));

    if ($systemType === 'left_group') {
        return str_replace('{name}', $actor, t('group_left_notice'));
    }
    if ($systemType === 'joined_group') {
        return str_replace('{name}', $actor, t('group_joined_notice'));
    }

    return '';
};
?>
<div class="d-flex align-items-center mb-3 gap-2">
    <?php if ($chatBackUrl !== ''): ?>
        <a href="<?= e($chatBackUrl) ?>" class="btn btn-outline-secondary btn-sm" id="chatPageBackBtn">
            <i class="bi bi-arrow-left me-1"></i><?= t('back') ?>
        </a>
    <?php endif; ?>
    <h2 class="mb-0"><i class="bi bi-chat-dots me-2"></i><?= t('chat') ?></h2>
</div>

<div class="chat-container <?= $activeConv ? 'chat-mobile-open' : '' ?>" id="chatContainer">
    <!-- Sidebar -->
    <div class="chat-sidebar">
        <div class="p-2 border-bottom">
            <input type="text" id="userSearch" class="form-control form-control-sm mb-2" placeholder="<?= t('search_users') ?>">
            <div id="searchResults" class="chat-search-results d-none"></div>
            <button class="btn btn-sm btn-primary w-100" data-bs-toggle="modal" data-bs-target="#newGroupModal">
                <i class="bi bi-people-fill me-1"></i><?= t('new_group') ?>
            </button>
        </div>
        <div class="chat-sidebar-list" id="chatSidebarList">
            <?php if (empty($conversations)): ?>
                <div class="text-center text-muted py-4 small"><?= t('no_conversations') ?></div>
            <?php else: ?>
                <?php foreach ($conversations as $c): ?>
                    <a href="/user/chat.php?conv=<?= $c['pk_conversationID'] ?><?= e($chatBackQuery) ?>" data-conv-id="<?= (int)$c['pk_conversationID'] ?>" class="text-decoration-none chat-conv-item <?= $activeConvId == $c['pk_conversationID'] ? 'active' : '' ?>">
                        <?php if ($c['type'] === 'group' && ($convGroupAvatarUrl = getGroupAvatarUrl((string)($c['avatar'] ?? ''), (int)$c['pk_conversationID']))): ?>
                            <img src="<?= e($convGroupAvatarUrl) ?>" class="conv-avatar" alt="avatar">
                        <?php elseif ($c['type'] === 'group'): ?>
                            <span class="conv-avatar">
                                <i class="bi bi-people-fill"></i>
                            </span>
                        <?php elseif (!empty($c['other_username']) && !empty($c['other_avatar']) && ($convAvatarUrl = getAvatarUrl($c['other_avatar'], $c['other_username']))): ?>
                            <img src="<?= e($convAvatarUrl) ?>" class="conv-avatar" alt="avatar">
                        <?php else: ?>
                            <span class="conv-avatar">
                                <i class="bi bi-person-circle"></i>
                            </span>
                        <?php endif; ?>
                        <div class="conv-info">
                            <div class="conv-name"><?= e($renderChatName($c['display_name'] ?? null, $c['name'] ?? null)) ?></div>
                            <div class="conv-preview">
                                <?php
                                $system = parseSystemMessageToken((string)($c['last_message'] ?? ''));
                                if ($system && in_array(($system['type'] ?? ''), ['left_group', 'joined_group'], true)) {
                                    $systemActor = trim((string)($system['actor_name'] ?? ''));
                                    if ($systemActor === '') {
                                        $systemActor = trim((string)($system['actor_username'] ?? '')) ?: t('deleted_user');
                                    }
                                    if (($system['type'] ?? '') === 'joined_group') {
                                        echo e(str_replace('{name}', $systemActor, t('group_joined_notice')));
                                    } else {
                                        echo e(str_replace('{name}', $systemActor, t('group_left_notice')));
                                    }
                                } else {
                                    echo e($c['last_message'] ?? '');
                                }
                                ?>
                            </div>
                        </div>
                        <span class="badge rounded-pill bg-danger ms-2 chat-conv-unread <?= ((int)($c['unread_count'] ?? 0) > 0) ? '' : 'd-none' ?>"
                            data-conv-unread="<?= (int)$c['pk_conversationID'] ?>">
                            <?= (int)($c['unread_count'] ?? 0) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main chat area -->
    <div class="chat-main" id="chatMain">
        <?php if ($activeConv): ?>
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center chat-main-header">
                <?php if ($activeConv['type'] === 'group'): ?>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary d-sm-none" id="chatBackBtn" title="<?= e(t('back')) ?>">
                            <i class="bi bi-arrow-left"></i>
                        </button>
                        <button type="button" class="chat-group-header-trigger" id="groupHeaderInfoTrigger" data-conv-id="<?= $activeConvId ?>" title="<?= e(t('group_info')) ?>">
                            <?php $activeGroupAvatarUrl = getGroupAvatarUrl((string)($activeConv['avatar'] ?? ''), (int)$activeConvId); ?>
                            <?php if (!empty($activeGroupAvatarUrl)): ?>
                                <img src="<?= e($activeGroupAvatarUrl) ?>" class="chat-header-avatar" alt="avatar">
                            <?php else: ?>
                                <span class="chat-header-avatar"><i class="bi bi-people-fill"></i></span>
                            <?php endif; ?>
                            <strong id="groupHeaderInfoTitle">
                                <?= e($renderChatName($activeConv['display_name'] ?? null, $activeConv['name'] ?? null)) ?>
                            </strong>
                        </button>
                        <span class="badge bg-secondary"><?= t('group_chat') ?></span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="chatLeaveGroupBtn" title="<?= e(t('leave_group')) ?>">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary d-sm-none" id="chatBackBtn" title="<?= e(t('back')) ?>">
                            <i class="bi bi-arrow-left"></i>
                        </button>
                        <?php $activeAvatarUrl = getAvatarUrl($activeConv['other_avatar'] ?? null, $activeConv['other_username'] ?? null); ?>
                        <?php if (!empty($activeAvatarUrl)): ?>
                            <img src="<?= e($activeAvatarUrl) ?>" class="chat-header-avatar" alt="avatar">
                        <?php else: ?>
                            <span class="chat-header-avatar"><i class="bi bi-person-circle"></i></span>
                        <?php endif; ?>
                        <strong><?= e($renderChatName($activeConv['display_name'] ?? null, $activeConv['name'] ?? null)) ?></strong>
                        <?php if (!empty($activeConv['other_username'])): ?>
                        <a href="/user/view_profile.php?user=<?= urlencode($activeConv['other_username']) ?>&back=<?= urlencode('/user/chat.php?conv=' . (int)$activeConvId) ?>" class="btn btn-sm btn-outline-secondary" title="<?= e(t('view_profile')) ?>">
                            <i class="bi bi-person"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="chat-messages" id="chatMessages">
                <?php
                $lastDate = null;
                foreach ($initialMessages as $m):
                    $isOwn = $m['fk_sender'] === $username;
                    $isSystem = !empty($m['system_type']);
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
                    <?php if ($isSystem): ?>
                        <?php $systemText = $renderSystemText($m); ?>
                        <?php if ($systemText !== ''): ?>
                            <div class="chat-date-separator chat-system-message"><span><?= e($systemText) ?></span></div>
                        <?php endif; ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <div class="chat-message <?= $isOwn ? 'own' : 'other' ?>">
                        <?php if (!$isOwn): ?>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <?php $senderAvatarUrl = getAvatarUrl($m['avatar'] ?? null, $m['fk_sender'] ?? null); ?>
                                <?php if (!empty($senderAvatarUrl)): ?>
                                    <img src="<?= e($senderAvatarUrl) ?>" class="chat-msg-avatar" alt="avatar">
                                <?php else: ?>
                                    <i class="bi bi-person-circle text-muted"></i>
                                <?php endif; ?>
                                <?php
                                $senderName = trim((string)($m['firstName'] ?? '') . ' ' . (string)($m['lastName'] ?? ''));
                                if ($senderName === '') {
                                    $senderName = trim((string)($m['fk_sender'] ?? '')) ?: t('deleted_user');
                                }
                                if ($isDeletedUserMarker($senderName)) {
                                    $senderName = t('deleted_user');
                                }
                                ?>
                                <small class="text-muted d-block mb-0"><?= e($senderName) ?></small>
                            </div>
                        <?php endif; ?>
                        <div class="bubble">
                            <?php if (!empty($m['message'])): ?>
                                <div><?= e($m['message']) ?></div>
                            <?php endif; ?>
                            <?php
                            $attachments = (isset($m['attachments']) && is_array($m['attachments'])) ? $m['attachments'] : [];
                            if (!empty($attachments)):
                            ?>
                                <?php foreach ($attachments as $attachment): ?>
                                    <?php
                                    $attachmentId = (int)($attachment['pk_fileID'] ?? 0);
                                    $viewUrl = '/download_chat_file.php?id=' . $attachmentId . '&mode=view';
                                    $downloadUrl = '/download_chat_file.php?id=' . $attachmentId . '&mode=download';
                                    $attachmentPath = (string)($attachment['file_path'] ?? '');
                                    $fileName = (string)($attachment['file_name'] ?? '');
                                    if ($fileName === '') {
                                        $fileName = basename($attachmentPath);
                                    }
                                    ?>
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <a href="<?= e($viewUrl) ?>" target="_blank" class="text-white text-truncate me-2">
                                            <i class="bi bi-file-earmark me-1"></i><?= e($fileName) ?>
                                        </a>
                                        <a href="<?= e($downloadUrl) ?>" class="btn btn-sm btn-outline-light">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
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
                    <label class="form-label mb-1"><?= t('group_avatar') ?></label>
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <img id="newGroupAvatarPreview" src="" alt="Group avatar" class="chat-group-avatar-editor d-none">
                            <span id="newGroupAvatarIcon" class="chat-group-avatar-editor"><i class="bi bi-people-fill"></i></span>
                        </div>
                        <div>
                            <label class="btn btn-sm btn-outline-primary mb-1" for="newGroupAvatarInput"><?= t('avatar_upload_device') ?></label>
                            <input type="file" id="newGroupAvatarInput" class="d-none" accept="<?= e(getAvatarUploadAcceptAttribute()) ?>">
                            <button type="button" id="newGroupAvatarClearBtn" class="btn btn-sm btn-outline-danger mb-1 d-none"><?= t('clear') ?></button>
                        </div>
                    </div>
                </div>
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
                        <div class="text-muted small px-3 py-1"><?= t('search_members') ?></div>
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
                <div class="mb-3">
                    <label class="form-label mb-1"><?= t('group_avatar') ?></label>
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <img id="groupInfoAvatarPreview" src="" alt="Group avatar" class="chat-group-avatar-editor d-none">
                            <span id="groupInfoAvatarIcon" class="chat-group-avatar-editor"><i class="bi bi-people-fill"></i></span>
                        </div>
                        <div id="groupInfoAvatarEdit" class="d-none">
                            <label class="btn btn-sm btn-outline-primary mb-1" for="groupInfoAvatarInput"><?= t('avatar_upload_device') ?></label>
                            <input type="file" id="groupInfoAvatarInput" class="d-none" accept="<?= e(getAvatarUploadAcceptAttribute()) ?>">
                            <button type="button" id="groupInfoAvatarClearBtn" class="btn btn-sm btn-outline-danger mb-1 d-none"><?= t('clear') ?></button>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label mb-0"><?= t('group_name') ?></label>
                    <div id="groupInfoNameView" class="mt-1"></div>
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

<script>
    window.chatPageData = {
        currentUser: <?= json_encode($username) ?>,
        activeConvId: <?= (int)$activeConvId ?>,
        initialLastMsgId: <?= !empty($initialMessages) ? (int)end($initialMessages)['pk_messageID'] : 0 ?>,
        backUrl: <?= json_encode($chatBackUrl) ?>,
        i18n: {
            viewProfileTitle: <?= json_encode(t('view_profile')) ?>,
            errorText: <?= json_encode(t('error_occurred')) ?>,
            noConversationsText: <?= json_encode(t('no_conversations')) ?>,
            groupChatText: <?= json_encode(t('group_chat')) ?>,
            typeMessageText: <?= json_encode(t('type_message')) ?>,
            uploadFileText: <?= json_encode(t('upload_file')) ?>,
            backText: <?= json_encode(t('back')) ?>,
            groupInfoText: <?= json_encode(t('group_info')) ?>,
            groupOwnerText: <?= json_encode(t('group_owner')) ?>,
            removeMemberText: <?= json_encode(t('remove_member')) ?>,
            noMembersYetText: <?= json_encode(t('no_members_yet')) ?>,
            noFriendsToAddText: <?= json_encode(t('no_friends_to_add')) ?>,
            confirmRemoveMemberText: <?= json_encode(t('confirm_remove_member')) ?>,
            groupNameRequiredText: <?= json_encode(t('group_name_required')) ?>,
            unknownUserText: <?= json_encode(t('deleted_user')) ?>,
            leaveGroupText: <?= json_encode(t('leave_group')) ?>,
            confirmLeaveGroupText: <?= json_encode(t('confirm_leave_group')) ?>,
            groupLeftNoticeText: <?= json_encode(t('group_left_notice')) ?>,
            groupJoinedNoticeText: <?= json_encode(t('group_joined_notice')) ?>
        }
    };
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>