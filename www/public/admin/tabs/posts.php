<?php
if (!function_exists('adminPostAuthorCircle')) {
    function adminPostAuthorCircle(string $username, string $firstName, string $lastName, string $avatar): string {
        if (trim($username) === '') {
            return '<span class="text-muted">-</span>';
        }

        $avatarUrl = getAvatarUrl($avatar, $username);
        $tooltip = trim($firstName . ' ' . $lastName);
        if ($tooltip === '') {
            $tooltip = '@' . $username;
        }

        return '<div class="collection-card-shares justify-content-center"><a class="collection-share-item admin-shared-mini" href="' . e(buildAdminProfileUrl($username)) . '" title="' . e($tooltip) . '">' .
            ($avatarUrl
                ? '<img src="' . e($avatarUrl) . '" class="collection-share-avatar" alt="avatar">'
                : '<span class="collection-share-avatar"><i class="bi bi-person-circle"></i></span>') .
            '<span class="collection-share-username">@' . e($username) . '</span></a></div>';
    }
}

$postsFrom = $totalPosts > 0 ? (($postPage - 1) * $postsPerPage + 1) : 0;
$postsTo = min($postPage * $postsPerPage, $totalPosts);
$postsPaginationInfo = str_replace(['{from}', '{to}', '{total}'], [$postsFrom, $postsTo, $totalPosts], t('pagination_info'));

$postBaseQuery = [
    'tab' => 'posts',
    'posts_id' => (int)($adminPostFilters['id'] ?? 0) > 0 ? (int)$adminPostFilters['id'] : '',
    'posts_description' => (string)($adminPostFilters['description'] ?? ''),
    'posts_created_from' => (string)($postsCreatedFromInput ?? ''),
    'posts_created_to' => (string)($postsCreatedToInput ?? ''),
    'posts_per_page' => (int)$postsPerPage,
];
foreach ((array)($adminPostFilters['titles'] ?? []) as $titleValue) {
    $postBaseQuery['posts_title'][] = (string)$titleValue;
}
foreach ((array)($adminPostFilters['authors'] ?? []) as $authorValue) {
    $postBaseQuery['posts_author'][] = (string)$authorValue;
}
?>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" id="adminPostsFilterForm" class="admin-users-filters">
            <input type="hidden" name="tab" value="posts">
            <input type="hidden" name="post_page" value="1">
            <input type="hidden" name="posts_per_page" value="<?= (int)$postsPerPage ?>">
            <div class="row g-2 align-items-end mb-2">
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label mb-1"><?= t('post_title') ?></label>
                    <div class="admin-multicombo" data-multi-combo>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle">
                            <span data-role="summary" data-base-label="<?= e(t('post_title')) ?>"><?= e(t('post_title')) ?>: <?= empty($adminPostFilters['titles']) ? e(t('any')) : count((array)$adminPostFilters['titles']) ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="admin-multicombo-panel d-none" data-role="panel">
                            <input type="text" class="form-control form-control-sm mb-2" placeholder="<?= e(t('search')) ?>..." data-role="search">
                            <div class="admin-multicombo-options" data-role="options">
                                <?php foreach (($postTitleOptions ?? []) as $titleOpt): ?>
                                    <?php $titleValue = (string)$titleOpt; if ($titleValue === '') { continue; } ?>
                                    <label class="admin-multicombo-option" data-label="<?= e(strtolower($titleValue)) ?>">
                                        <input type="checkbox" name="posts_title[]" value="<?= e($titleValue) ?>" <?= in_array($titleValue, (array)($adminPostFilters['titles'] ?? []), true) ? 'checked' : '' ?>>
                                        <span><?= e($titleValue) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-8 col-lg-9">
                    <label class="form-label mb-1"><?= t('post_content') ?></label>
                    <input type="text" class="form-control form-control-sm" name="posts_description" value="<?= e((string)($adminPostFilters['description'] ?? '')) ?>" placeholder="<?= e(t('search')) ?>...">
                </div>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                    <label class="form-label mb-1">ID</label>
                    <input type="number" min="1" class="form-control form-control-sm" name="posts_id" value="<?= (int)($adminPostFilters['id'] ?? 0) > 0 ? (int)$adminPostFilters['id'] : '' ?>" placeholder="ID">
                </div>
                <div class="col-12 col-sm-6 col-md-5 col-lg-4">
                    <label class="form-label mb-1"><?= t('created_by') ?></label>
                    <div class="admin-multicombo" data-multi-combo>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle">
                            <span data-role="summary" data-base-label="<?= e(t('created_by')) ?>"><?= e(t('created_by')) ?>: <?= empty($adminPostFilters['authors']) ? e(t('any')) : count((array)$adminPostFilters['authors']) ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="admin-multicombo-panel d-none" data-role="panel">
                            <input type="text" class="form-control form-control-sm mb-2" placeholder="<?= e(t('search')) ?>..." data-role="search">
                            <div class="admin-multicombo-options" data-role="options">
                                <?php foreach (($postAuthorOptions ?? []) as $authorOpt): ?>
                                    <?php $username = (string)($authorOpt['pk_username'] ?? ''); if ($username === '') { continue; } ?>
                                    <?php $full = trim((string)($authorOpt['firstName'] ?? '') . ' ' . (string)($authorOpt['lastName'] ?? '')); ?>
                                    <?php if ($full === '') { $full = $username; } else { $full .= ' (@' . $username . ')'; } ?>
                                    <label class="admin-multicombo-option" data-label="<?= e(strtolower($full . ' ' . $username)) ?>">
                                        <input type="checkbox" name="posts_author[]" value="<?= e($username) ?>" <?= in_array($username, (array)($adminPostFilters['authors'] ?? []), true) ? 'checked' : '' ?>>
                                        <span><?= e($full) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label mb-1"><?= t('created_from_label') ?></label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control form-control-sm js-admin-posts-datetime" name="posts_created_from" value="<?= e((string)($postsCreatedFromInput ?? '')) ?>" placeholder="DD.MM.YYYY HH:mm" autocomplete="off">
                        <span class="input-group-text measurement-picker-icon"><i class="bi bi-calendar-event"></i></span>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label mb-1"><?= t('created_until_label') ?></label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control form-control-sm js-admin-posts-datetime" name="posts_created_to" value="<?= e((string)($postsCreatedToInput ?? '')) ?>" placeholder="DD.MM.YYYY HH:mm" autocomplete="off">
                        <span class="input-group-text measurement-picker-icon"><i class="bi bi-calendar-event"></i></span>
                    </div>
                </div>
                <div class="col-12 col-md-4 col-lg-2 d-flex gap-2">
                    <a href="?tab=posts" class="btn btn-outline-secondary btn-sm w-100 admin-ajax-link"><?= t('clear') ?></a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center mb-3 gap-2">
    <span class="pagination-info text-nowrap"><?= e($postsPaginationInfo) ?></span>
    <div class="d-flex align-items-center gap-2">
        <label for="postsPerPageSelect" class="form-label mb-0 small"><?= t('per_page') ?></label>
        <form method="get" class="d-inline-block" id="postsPerPageForm">
            <input type="hidden" name="tab" value="posts">
            <input type="hidden" name="posts_id" value="<?= (int)($adminPostFilters['id'] ?? 0) > 0 ? (int)$adminPostFilters['id'] : '' ?>">
            <input type="hidden" name="posts_description" value="<?= e((string)($adminPostFilters['description'] ?? '')) ?>">
            <input type="hidden" name="posts_created_from" value="<?= e((string)($postsCreatedFromInput ?? '')) ?>">
            <input type="hidden" name="posts_created_to" value="<?= e((string)($postsCreatedToInput ?? '')) ?>">
            <?php foreach ((array)($adminPostFilters['titles'] ?? []) as $titleValue): ?>
                <input type="hidden" name="posts_title[]" value="<?= e((string)$titleValue) ?>">
            <?php endforeach; ?>
            <?php foreach ((array)($adminPostFilters['authors'] ?? []) as $authorValue): ?>
                <input type="hidden" name="posts_author[]" value="<?= e((string)$authorValue) ?>">
            <?php endforeach; ?>
            <select id="postsPerPageSelect" class="form-select form-select-sm" name="posts_per_page" style="width:auto;" onchange="this.form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));">
                <?php foreach ([10, 20, 50, 100] as $pp): ?>
                <option value="<?= $pp ?>" <?= (int)$postsPerPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPostModal">
            <i class="bi bi-plus-circle me-1"></i><?= t('publish') ?>
        </button>
    </div>
</div>

<?php if (empty($posts)): ?>
<div class="alert alert-info"><?= t('no_posts_yet') ?></div>
<?php else: ?>
<div class="alert alert-secondary py-2 px-3 small d-sm-none" id="postsScrollHint" role="status">
    <i class="bi bi-arrow-left-right me-1"></i><?= t('table_horizontal_scroll_hint') ?>
</div>
<div class="table-responsive admin-posts-table-wrap">
    <table id="adminPostsTable" class="table table-sm table-hover align-middle text-nowrap table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th><?= t('post_title') ?></th>
                <th><?= t('post_content') ?></th>
                <th><?= t('created_by') ?></th>
                <th><?= t('post_visibility') ?></th>
                <th><?= t('created_at') ?></th>
                <th><?= t('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($posts as $p): ?>
            <?php
                $postRecipients = getAdminPostNotificationRecipients($conn, (int)$p['pk_postID']);
                $editPayload = $p;
                $editPayload['recipients'] = $postRecipients;
                $editPayload['audience'] = detectPostAudience($postRecipients, $allUsernames, $regularUsernames, $adminUsernames);
                $authorUsername = (string)($p['fk_author'] ?? '');
                $postVisibilityMap = [
                    'all' => t('post_target_all'),
                    'users' => t('post_target_users'),
                    'admins' => t('post_target_admins'),
                    'selected' => t('post_target_selected'),
                ];
                $postVisibilityLabel = (string)($postVisibilityMap[(string)$editPayload['audience']] ?? t('post_target_selected'));
            ?>
            <tr>
                <td><?= e((string)$p['pk_postID']) ?></td>
                <td><span class="admin-users-cell-text" title="<?= e((string)$p['title']) ?>"><?= e((string)$p['title']) ?></span></td>
                <td>
                    <span class="admin-users-cell-text" title="<?= e((string)$p['content']) ?>"><?= e((string)$p['content']) ?></span>
                </td>
                <td class="admin-stations-user-col"><?= adminPostAuthorCircle($authorUsername, (string)($p['firstName'] ?? ''), (string)($p['lastName'] ?? ''), (string)($p['avatar'] ?? '')) ?></td>
                <td><span class="admin-users-cell-text" title="<?= e($postVisibilityLabel) ?>"><?= e($postVisibilityLabel) ?></span></td>
                <td><?= e(formatDateTime((string)$p['createdAt'])) ?></td>
                <td>
                    <div class="admin-actions-row">
                        <button class="btn btn-sm btn-outline-primary" type="button" title="<?= e(t('edit')) ?>" aria-label="<?= e(t('edit')) ?>" onclick="editPost(<?= htmlspecialchars(json_encode($editPayload), ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="delete_post">
                            <input type="hidden" name="post_id" value="<?= (int)$p['pk_postID'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= e(t('delete')) ?>" aria-label="<?= e(t('delete')) ?>" onclick="return confirm('<?= t('confirm_delete') ?>')"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($postTotalPages > 1): ?>
<nav>
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php for ($i = 1; $i <= $postTotalPages; $i++): ?>
        <li class="page-item <?= $i === (int)$postPage ? 'active' : '' ?>">
            <a class="page-link" href="?<?= e(http_build_query(array_merge($postBaseQuery, ['post_page' => $i]))) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
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
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
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
                        <div class="admin-multicombo" data-multi-combo>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle">
                                <span data-role="summary" data-base-label="<?= e(t('select_recipients')) ?>"><?= e(t('select_recipients')) ?>: <?= e(t('any')) ?></span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="admin-multicombo-panel d-none" data-role="panel">
                                <input type="text" class="form-control form-control-sm mb-2" placeholder="<?= e(t('search_members')) ?>" data-role="search">
                                <div id="editPostRecipientsList" class="admin-multicombo-options admin-post-recipients-options" data-role="options">
                                    <?php foreach ($postTargetUsers as $pu): ?>
                                        <?php $editRecipientId = 'edit_post_recipient_' . $pu['pk_username']; ?>
                                        <?php $recipientLabel = trim((string)($pu['firstName'] ?? '') . ' ' . (string)($pu['lastName'] ?? '')); ?>
                                        <?php if ($recipientLabel === '') { $recipientLabel = (string)$pu['pk_username']; } ?>
                                        <label class="admin-multicombo-option" data-label="<?= e(strtolower($recipientLabel . ' @' . (string)$pu['pk_username'] . ' ' . (string)$pu['role'])) ?>">
                                            <input class="form-check-input edit-post-recipient-check" type="checkbox" name="recipients[]" value="<?= e($pu['pk_username']) ?>" id="<?= e($editRecipientId) ?>">
                                            <span><?= e($recipientLabel) ?> <small class="text-muted">@<?= e($pu['pk_username']) ?> (<?= e($pu['role']) ?>)</small></span>
                                        </label>
                                    <?php endforeach; ?>
                                    <?php if (empty($postTargetUsers)): ?>
                                        <div class="text-muted small px-3 py-1"><?= t('no_users_found') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= t('save') ?></button></div>
            </form>
        </div>
    </div>
</div>
