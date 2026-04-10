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
<?php
    $postRecipients = getAdminPostNotificationRecipients($conn, (int)$p['pk_postID']);
    $editPayload = $p;
    $editPayload['recipients'] = $postRecipients;
    $editPayload['audience'] = detectPostAudience($postRecipients, $allUsernames, $regularUsernames, $adminUsernames);
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong class="post-card-title" title="<?= e($p['title']) ?>"><?= e($p['title']) ?></strong>
        <div class="d-flex gap-1 align-items-center">
            <small class="text-muted me-2"><?= formatDateTime($p['createdAt']) ?></small>
            <button class="btn btn-sm btn-outline-primary" onclick="editPost(<?= htmlspecialchars(json_encode($editPayload), ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
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

<div class="modal fade" id="createPostModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= t('publish') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post">
                <input type="hidden" name="action" value="create_post">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label"><?= t('post_title') ?></label><input type="text" name="title" class="form-control" maxlength="<?= $postTitleMaxLen ?>" required></div>
                    <div class="mb-3"><label class="form-label"><?= t('post_content') ?></label><textarea name="content" class="form-control" rows="6" required></textarea></div>
                    <div class="mb-3">
                        <label class="form-label"><?= t('post_visibility') ?></label>
                        <select name="audience" id="postAudience" class="form-select">
                            <option value="all"><?= t('post_target_all') ?></option>
                            <option value="users"><?= t('post_target_users') ?></option>
                            <option value="admins"><?= t('post_target_admins') ?></option>
                            <option value="selected"><?= t('post_target_selected') ?></option>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="postRecipientsWrap">
                        <label class="form-label"><?= t('select_recipients') ?></label>
                        <input type="text" id="postRecipientsSearch" class="form-control form-control-sm mb-2" placeholder="<?= t('search_members') ?>">
                        <div id="postRecipientsList" class="group-member-list mb-1">
                            <?php foreach ($postTargetUsers as $pu): ?>
                                <?php $recipientId = 'post_recipient_' . $pu['pk_username']; ?>
                                <div class="group-member-item form-check">
                                    <input class="form-check-input post-recipient-check" type="checkbox" name="recipients[]" value="<?= e($pu['pk_username']) ?>" id="<?= e($recipientId) ?>">
                                    <label class="form-check-label" for="<?= e($recipientId) ?>">
                                        <?= e($pu['firstName'] . ' ' . $pu['lastName']) ?>
                                        <small class="text-muted d-block">@<?= e($pu['pk_username']) ?> (<?= e($pu['role']) ?>)</small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($postTargetUsers)): ?>
                                <div class="text-muted small px-3 py-1"><?= t('no_users_found') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= t('publish') ?></button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editPostModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= t('edit') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post">
                <input type="hidden" name="action" value="update_post">
                <input type="hidden" name="post_id" id="editPostId">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label"><?= t('post_title') ?></label><input type="text" name="title" id="editPostTitle" class="form-control" maxlength="<?= $postTitleMaxLen ?>" required></div>
                    <div class="mb-3"><label class="form-label"><?= t('post_content') ?></label><textarea name="content" id="editPostContent" class="form-control" rows="6" required></textarea></div>
                    <div class="mb-3">
                        <label class="form-label"><?= t('post_visibility') ?></label>
                        <select name="audience" id="editPostAudience" class="form-select">
                            <option value="all"><?= t('post_target_all') ?></option>
                            <option value="users"><?= t('post_target_users') ?></option>
                            <option value="admins"><?= t('post_target_admins') ?></option>
                            <option value="selected"><?= t('post_target_selected') ?></option>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="editPostRecipientsWrap">
                        <label class="form-label"><?= t('select_recipients') ?></label>
                        <input type="text" id="editPostRecipientsSearch" class="form-control form-control-sm mb-2" placeholder="<?= t('search_members') ?>">
                        <div id="editPostRecipientsList" class="group-member-list mb-1">
                            <?php foreach ($postTargetUsers as $pu): ?>
                                <?php $editRecipientId = 'edit_post_recipient_' . $pu['pk_username']; ?>
                                <div class="group-member-item form-check">
                                    <input class="form-check-input edit-post-recipient-check" type="checkbox" name="recipients[]" value="<?= e($pu['pk_username']) ?>" id="<?= e($editRecipientId) ?>">
                                    <label class="form-check-label" for="<?= e($editRecipientId) ?>">
                                        <?= e($pu['firstName'] . ' ' . $pu['lastName']) ?>
                                        <small class="text-muted d-block">@<?= e($pu['pk_username']) ?> (<?= e($pu['role']) ?>)</small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($postTargetUsers)): ?>
                                <div class="text-muted small px-3 py-1"><?= t('no_users_found') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= t('save') ?></button></div>
            </form>
        </div>
    </div>
</div>
