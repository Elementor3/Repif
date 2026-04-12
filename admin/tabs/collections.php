<?php
$renderMiniUser = static function (array $user, string $extraClass = ''): string {
    $username = (string)($user['username'] ?? $user['pk_username'] ?? '');
    $firstName = trim((string)($user['firstName'] ?? ''));
    $lastName = trim((string)($user['lastName'] ?? ''));
    $avatarUrl = (string)($user['avatarUrl'] ?? '');
    $profileUrl = (string)($user['profileUrl'] ?? '#');
    $tooltip = trim($firstName . ' ' . $lastName);
    if ($tooltip === '') {
        $tooltip = '@' . $username;
    }

    return '<a class="collection-share-item ' . e($extraClass) . '" href="' . e($profileUrl) . '" title="' . e($tooltip) . '">' .
        ($avatarUrl !== ''
            ? '<img src="' . e($avatarUrl) . '" class="collection-share-avatar" alt="avatar">'
            : '<span class="collection-share-avatar"><i class="bi bi-person-circle"></i></span>') .
        '<span class="collection-share-username">@' . e($username) . '</span>' .
        '</a>';
};

$collectionsFrom = $filteredCollectionsTotal > 0 ? (($collectionsPage - 1) * $collectionsPerPage + 1) : 0;
$collectionsTo = min($collectionsPage * $collectionsPerPage, $filteredCollectionsTotal);
$formatUserOptionLabel = static function (array $user): string {
    $username = (string)($user['pk_username'] ?? '');
    $fullName = trim((string)($user['firstName'] ?? '') . ' ' . (string)($user['lastName'] ?? ''));
    if ($fullName === '') {
        return '@' . $username;
    }
    return $fullName . ' (@' . $username . ')';
};
?>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end admin-collections-filters" id="adminCollectionsFilterForm">
            <input type="hidden" name="tab" value="collections">
            <div class="col-6 col-md-2 col-lg-1">
                <label class="form-label">ID</label>
                <input type="number" min="1" class="form-control form-control-sm" name="collections_id" value="<?= $collectionsIdFilter > 0 ? (int)$collectionsIdFilter : '' ?>" placeholder="ID">
            </div>
            <div class="col-6 col-md-3 col-lg-3 col-xl-2">
                <label class="form-label"><?= t('name') ?></label>
                <div class="admin-multicombo" data-multi-combo>
                    <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle">
                        <span data-role="summary" data-base-label="<?= e(t('name')) ?>"><?= empty($collectionsNameFilter) ? t('name') . ': all' : t('name') . ': ' . count($collectionsNameFilter) ?></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="admin-multicombo-panel d-none" data-role="panel">
                        <input type="text" class="form-control form-control-sm mb-2" placeholder="Search..." data-role="search">
                        <div class="admin-multicombo-options" data-role="options">
                            <?php foreach ($collectionNameFilterOptions as $nameOpt): ?>
                            <label class="admin-multicombo-option" data-label="<?= e(strtolower((string)$nameOpt)) ?>">
                                <input type="checkbox" name="collections_name[]" value="<?= e((string)$nameOpt) ?>" <?= in_array((string)$nameOpt, $collectionsNameFilter, true) ? 'checked' : '' ?>>
                                <span><?= e((string)$nameOpt) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 col-lg-3 col-xl-2">
                <label class="form-label"><?= t('owner') ?></label>
                <div class="admin-multicombo" data-multi-combo>
                    <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle">
                        <span data-role="summary" data-base-label="<?= e(t('owner')) ?>"><?= empty($collectionsOwnerFilter) ? t('owner') . ': all' : t('owner') . ': ' . count($collectionsOwnerFilter) ?></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="admin-multicombo-panel d-none" data-role="panel">
                        <input type="text" class="form-control form-control-sm mb-2" placeholder="Search..." data-role="search">
                        <div class="admin-multicombo-options" data-role="options">
                            <?php foreach ($allUsersForCollectionOwnerView as $ownerOpt): ?>
                            <?php $ownerUsername = (string)($ownerOpt['pk_username'] ?? ''); ?>
                            <label class="admin-multicombo-option" data-label="<?= e(strtolower($formatUserOptionLabel($ownerOpt))) ?>">
                                <input type="checkbox" name="collections_owner[]" value="<?= e($ownerUsername) ?>" <?= in_array($ownerUsername, $collectionsOwnerFilter, true) ? 'checked' : '' ?>>
                                <span><?= e($formatUserOptionLabel($ownerOpt)) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 col-lg-3 col-xl-2">
                <label class="form-label"><?= t('shared_with') ?></label>
                <div class="admin-multicombo" data-multi-combo>
                    <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle">
                        <span data-role="summary" data-base-label="<?= e(t('shared_with')) ?>"><?= empty($collectionsSharedUsersFilter) ? t('shared_with') . ': all' : t('shared_with') . ': ' . count($collectionsSharedUsersFilter) ?></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="admin-multicombo-panel d-none" data-role="panel">
                        <input type="text" class="form-control form-control-sm mb-2" placeholder="Search..." data-role="search">
                        <div class="admin-multicombo-options" data-role="options">
                            <?php foreach ($allUsersForCollectionOwnerView as $sharedOpt): ?>
                            <?php $sharedUsername = (string)($sharedOpt['pk_username'] ?? ''); ?>
                            <label class="admin-multicombo-option" data-label="<?= e(strtolower($formatUserOptionLabel($sharedOpt))) ?>">
                                <input type="checkbox" name="collections_shared_users[]" value="<?= e($sharedUsername) ?>" <?= in_array($sharedUsername, $collectionsSharedUsersFilter, true) ? 'checked' : '' ?>>
                                <span><?= e($formatUserOptionLabel($sharedOpt)) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-3 col-xl-2">
                <label class="form-label">Created from</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control form-control-sm js-admin-collections-datetime" name="collections_created_from" value="<?= e($collectionsCreatedFromInput) ?>" autocomplete="off" placeholder="DD.MM.YYYY HH:mm">
                    <span class="input-group-text slot-picker-icon measurement-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-3 col-xl-2">
                <label class="form-label">Created untill</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control form-control-sm js-admin-collections-datetime" name="collections_created_to" value="<?= e($collectionsCreatedToInput) ?>" autocomplete="off" placeholder="DD.MM.YYYY HH:mm">
                    <span class="input-group-text slot-picker-icon measurement-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                </div>
            </div>
            <div class="col-12 col-lg-auto col-xl-1 d-flex gap-2 admin-collections-filter-actions">
                <a href="?tab=collections" class="btn btn-outline-secondary btn-sm admin-ajax-link"><?= t('clear') ?></a>
            </div>
        </form>
    </div>
</div>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center mb-3 gap-2">
    <div class="d-flex flex-column align-items-start admin-collections-summary">
        <h5 class="mb-0"><?= t('collections') ?></h5>
        <span class="pagination-info text-nowrap"><?= str_replace(['{from}', '{to}', '{total}'], [$collectionsFrom, $collectionsTo, $filteredCollectionsTotal], t('pagination_info')) ?></span>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <form method="get" class="d-flex align-items-center gap-2 admin-collections-per-page-form">
            <input type="hidden" name="tab" value="collections">
            <input type="hidden" name="collections_id" value="<?= $collectionsIdFilter > 0 ? (int)$collectionsIdFilter : '' ?>">
            <?php foreach ($collectionsNameFilter as $collectionNameFilterValue): ?>
            <input type="hidden" name="collections_name[]" value="<?= e($collectionNameFilterValue) ?>">
            <?php endforeach; ?>
            <input type="hidden" name="collections_created_from" value="<?= e($collectionsCreatedFromInput) ?>">
            <input type="hidden" name="collections_created_to" value="<?= e($collectionsCreatedToInput) ?>">
            <?php foreach ($collectionsOwnerFilter as $ownerFilterValue): ?>
            <input type="hidden" name="collections_owner[]" value="<?= e($ownerFilterValue) ?>">
            <?php endforeach; ?>
            <?php foreach ($collectionsSharedUsersFilter as $sharedFilterValue): ?>
            <input type="hidden" name="collections_shared_users[]" value="<?= e($sharedFilterValue) ?>">
            <?php endforeach; ?>
            <label for="collectionsPerPage" class="form-label mb-0 small"><?= t('per_page') ?></label>
            <select id="collectionsPerPage" class="form-select form-select-sm" name="collections_per_page" style="width:auto;">
                <?php foreach ([10, 20, 50, 100] as $pp): ?>
                <option value="<?= $pp ?>" <?= $collectionsPerPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createCollectionAdminModal">
            <i class="bi bi-plus-circle me-1"></i><?= t('create') ?>
        </button>
    </div>
</div>

<?php if (empty($collectionsPageItems)): ?>
<div class="alert alert-info"><?= t('no_collections') ?></div>
<?php else: ?>
<div class="alert alert-secondary py-2 px-3 small d-sm-none" id="collectionsScrollHint" role="status">
    <i class="bi bi-arrow-left-right me-1"></i><?= t('table_horizontal_scroll_hint') ?>
</div>
<div class="table-responsive admin-collections-table-wrap" id="adminCollectionsTableWrap">
    <table class="table table-sm table-hover align-middle text-center text-nowrap table-striped" id="adminCollectionsTable">
        <thead>
            <tr>
                <th>ID</th>
                <th><?= t('name') ?></th>
                <th class="admin-col-description"><?= t('description') ?></th>
                <th><?= t('owner') ?></th>
                <th><?= t('created_at') ?></th>
                <th class="admin-col-shared"><?= t('shared_with') ?></th>
                <th><?= t('actions') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($collectionsPageItems as $collection): ?>
        <?php
            $collectionId = (int)($collection['pk_collectionID'] ?? 0);
            $isOwnedByCurrentAdmin = ((string)($collection['fk_user'] ?? '') === $username);
            $collectionMeasUrl = buildAdminMeasurementsUrl(['collection' => $collectionId]);
            $shares = $collectionSharesViewByCollection[$collectionId] ?? [];
            $ownerView = [
                'username' => (string)($collection['fk_user'] ?? ''),
                'firstName' => (string)($collection['ownerFirstName'] ?? ''),
                'lastName' => (string)($collection['ownerLastName'] ?? ''),
                'avatarUrl' => (string)(getAvatarUrl((string)($collection['ownerAvatar'] ?? ''), (string)($collection['fk_user'] ?? '')) ?? ''),
                'profileUrl' => buildAdminProfileUrl((string)($collection['fk_user'] ?? '')),
            ];
            $payload = [
                'pk_collectionID' => $collectionId,
                'name' => (string)($collection['name'] ?? ''),
                'description' => (string)($collection['description'] ?? ''),
                'fk_user' => (string)($collection['fk_user'] ?? ''),
            ];
        ?>
        <tr>
            <td><?= $collectionId ?></td>
            <td><?= e(trim((string)($collection['name'] ?? '')) !== '' ? (string)$collection['name'] : '-') ?></td>
            <td class="admin-col-description">
                <?php
                    $fullDescription = trim((string)($collection['description'] ?? ''));
                    if ($fullDescription === '') {
                        $fullDescription = '-';
                    }
                ?>
                <span class="js-collection-description-text" data-full-description="<?= e($fullDescription) ?>"><?= e($fullDescription) ?></span>
                <button
                    type="button"
                    class="btn btn-link btn-sm p-0 ms-1 align-baseline js-collection-description-more"
                    onclick="openCollectionDescriptionModal('<?= e((string)($collection['name'] ?? '')) ?>', <?= htmlspecialchars(json_encode($fullDescription), ENT_QUOTES) ?>)">...
                </button>
            </td>
            <td>
                <div class="d-flex justify-content-center">
                    <?php if ((string)($ownerView['username'] ?? '') === ''): ?>
                        <span class="text-muted">-</span>
                    <?php else: ?>
                        <?= $renderMiniUser($ownerView, 'admin-owner-mini') ?>
                    <?php endif; ?>
                </div>
            </td>
            <?php $createdAtView = formatDateTime($collection['createdAt'] ?? null); ?>
            <td><?= $createdAtView !== '' ? e($createdAtView) : '-' ?></td>
            <td class="admin-col-shared">
                <?php if (empty($shares)): ?>
                    <span class="text-muted">-</span>
                <?php else: ?>
                    <div class="collection-card-shares js-admin-shared-users"
                         data-collection-id="<?= $collectionId ?>"
                         data-collection-name="<?= e((string)($collection['name'] ?? '')) ?>"
                         data-shares="<?= e(json_encode($shares, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                         data-hydrated="0"></div>
                <?php endif; ?>
            </td>
            <td>
                <div class="admin-actions-row">
                <button class="btn btn-sm btn-outline-primary" onclick="editAdminCollection(<?= htmlspecialchars(json_encode($payload), ENT_QUOTES) ?>)">
                    <i class="bi bi-pencil"></i>
                </button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e($collectionMeasUrl) ?>" title="<?= e(t('measurements')) ?>">
                    <i class="bi bi-graph-up"></i>
                </a>
                <button class="btn btn-sm btn-outline-secondary" onclick="openCollectionSlotsModal(<?= $collectionId ?>, '<?= e((string)($collection['name'] ?? '')) ?>')">
                    <i class="bi bi-calendar-range"></i>
                </button>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete_collection_admin">
                    <input type="hidden" name="collection_id" value="<?= $collectionId ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= t('confirm_delete') ?>')"><i class="bi bi-trash"></i></button>
                </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($filteredCollectionsPages > 1): ?>
<nav>
    <ul class="pagination pagination-sm justify-content-center mt-3" id="adminCollectionsPaginationList">
        <?php for ($i = 1; $i <= $filteredCollectionsPages; $i++): ?>
        <li class="page-item <?= $i === $collectionsPage ? 'active' : '' ?>">
            <a class="page-link admin-ajax-link" href="?<?= http_build_query([
                'tab' => 'collections',
                'collections_page' => $i,
                'collections_per_page' => $collectionsPerPage,
                'collections_id' => $collectionsIdFilter > 0 ? $collectionsIdFilter : '',
                'collections_name' => $collectionsNameFilter,
                'collections_owner' => $collectionsOwnerFilter,
                'collections_shared_users' => $collectionsSharedUsersFilter,
                'collections_created_from' => $collectionsCreatedFromInput,
                'collections_created_to' => $collectionsCreatedToInput,
            ]) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<div class="modal fade" id="collectionSharedUsersModal" tabindex="-1">
    <div class="modal-dialog modal-lg collection-share-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="collectionSharedUsersModalTitle"><?= t('shared_with') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="collectionSharedUsersModalUsersList"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="collectionDescriptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="collectionDescriptionModalTitle"><?= t('description') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="collectionDescriptionModalBody"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="createCollectionAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= t('create') ?> <?= t('collection') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post">
                <input type="hidden" name="action" value="create_collection_admin">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= t('owner') ?></label>
                        <select name="owner" class="form-select" required>
                            <?php foreach ($allUsersForCollectionOwner as $owner): ?>
                            <option value="<?= e($owner['pk_username']) ?>"><?= e($owner['firstName'] . ' ' . $owner['lastName'] . ' (@' . $owner['pk_username'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label"><?= t('name') ?></label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label"><?= t('description') ?></label><textarea name="description" class="form-control" rows="3"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= t('create') ?></button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editCollectionAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= t('edit') ?> <?= t('collection') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post">
                <input type="hidden" name="action" value="update_collection_admin">
                <input type="hidden" name="collection_id" id="editCollectionAdminId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= t('owner') ?></label>
                        <input type="hidden" name="owner" id="editCollectionAdminOwner" required>
                        <input type="text" id="editCollectionAdminOwnerSearch" class="form-control mb-2" placeholder="Search users...">
                        <div id="editCollectionAdminOwnerList" class="share-friends-list admin-share-friends-list"></div>
                    </div>
                    <div class="mb-3"><label class="form-label"><?= t('name') ?></label><input type="text" name="name" id="editCollectionAdminName" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label"><?= t('description') ?></label><textarea name="description" id="editCollectionAdminDescription" class="form-control" rows="3"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= t('save') ?></button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="collectionSlotsAdminModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="collectionSlotsAdminTitle"><?= t('slots') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="addCollectionSlotAdminForm" class="row g-2 align-items-end mb-3">
                    <input type="hidden" name="action" value="add_collection_slot_admin">
                    <input type="hidden" name="collection_id" id="collectionSlotsAdminCollectionId">
                    <div class="col-12 col-md-4">
                        <label class="form-label"><?= t('station') ?></label>
                        <select name="station" class="form-select" required>
                            <?php foreach ($allStationsForFilters as $st): ?>
                            <option value="<?= e($st['pk_serialNumber']) ?>"><?= e($st['name'] ?? $st['pk_serialNumber']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label"><?= t('start_datetime_pipe') ?></label>
                        <input type="text" name="start" class="form-control js-datetime-input" autocomplete="off" placeholder="DD.MM.YYYY HH:mm" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label"><?= t('end_datetime_pipe') ?></label>
                        <input type="text" name="end" class="form-control js-datetime-input" autocomplete="off" placeholder="DD.MM.YYYY HH:mm" required>
                    </div>
                    <div class="col-12 col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary"><?= t('add_slot') ?></button>
                    </div>
                </form>

                <div id="collectionSlotsAdminEmpty" class="text-muted d-none"><?= t('no_slots') ?></div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th><?= t('station') ?></th>
                                <th><?= t('time_frame') ?></th>
                                <th><?= t('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody id="collectionSlotsAdminTbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script id="adminCollectionsConfig" type="application/json"><?= json_encode([
    'slotsByCollection' => $slotsByCollection,
    'collectionSharesByCollection' => $collectionSharesByCollection,
    'collectionSharesViewByCollection' => $collectionSharesViewByCollection,
    'allUsers' => $allUsersForCollectionOwnerView,
    'currentAdminUsername' => $username,
    'confirmDelete' => t('confirm_delete'),
    'deleteLabel' => t('delete'),
    'shareLabel' => t('share'),
    'noSharedUsersLabel' => t('no_shared_users'),
    'viewProfileLabel' => t('view_profile'),
    'sharedWithLabel' => t('shared_with'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
