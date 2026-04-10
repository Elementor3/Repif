(function ($) {
    'use strict';

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function getI18n() {
        var $el = $('#collectionsClientI18n');
        return {
            defaultError: $el.data('default-error') || 'Error occurred',
            confirmDelete: $el.data('confirm-delete') || 'Are you sure?',
            slotInvalidRange: $el.data('slot-invalid-range') || 'Start date/time must be earlier than end date/time',
            slotOverlap: $el.data('slot-overlap') || 'Slot overlaps an existing slot',
            noSlots: $el.data('no-slots') || 'No slots found',
            descriptionLabel: $el.data('description-label') || 'Description',
            createdAtLabel: $el.data('created-at-label') || 'Created At',
            editLabel: $el.data('edit-label') || 'Edit',
            viewLabel: $el.data('view-label') || 'View',
            measurementsLabel: $el.data('measurements-label') || 'Measurements',
            allMeasurementsLabel: $el.data('all-measurements-label') || 'All measurements',
            timeFrameLabel: $el.data('time-frame-label') || 'Time frame',
            timeLabel: $el.data('time-label') || 'Time',
            deleteLabel: $el.data('delete-label') || 'Delete',
            actionsLabel: $el.data('actions-label') || 'Actions',
            ownerLabel: $el.data('owner-label') || 'Owner',
            shareLabel: $el.data('share-label') || 'Share',
            unshareLabel: $el.data('unshare-label') || 'Unshare',
            sharedWithLabel: $el.data('shared-with-label') || 'Shared with me',
            searchMembersPlaceholder: $el.data('search-members-placeholder') || 'Search friends...',
            viewProfileLabel: $el.data('view-profile-label') || 'View Profile',
            chatLabel: $el.data('chat-label') || 'Chat',
            noFriendsLabel: $el.data('no-friends-label') || 'No friends available to add',
            noSharedUsersLabel: $el.data('no-shared-users-label') || 'No shared users',
            selectUsersToShareLabel: $el.data('select-users-to-share-label') || 'Select users to share',
            mineTabUrl: $el.data('mine-tab-url') || '/user/collections.php?tab=mine',
            returnTo: $el.data('return-to') || (window.location.pathname + window.location.search)
        };
    }

    function showAlert(message, type, $target) {
        var cls = type === 'success' ? 'success' : 'danger';
        var html = '' +
            '<div class="alert alert-' + cls + ' alert-dismissible fade show auto-dismiss" role="alert">' +
            escapeHtml(message) +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
            '</div>';
        $target.html(html);

        window.setTimeout(function () {
            var $alert = $target.find('.auto-dismiss').first();
            if (!$alert.length) {
                return;
            }
            $alert.fadeOut(220, function () {
                $alert.remove();
            });
        }, 4500);
    }

    function buildUrlWithBackParam(profileUrl, backUrl) {
        var rawProfileUrl = String(profileUrl || '').trim();
        var rawBackUrl = String(backUrl || '').trim();
        if (rawProfileUrl === '' || rawBackUrl === '') {
            return rawProfileUrl || '#';
        }

        try {
            var url = new URL(rawProfileUrl, window.location.origin);
            url.searchParams.set('back', rawBackUrl);
            return url.pathname + url.search + url.hash;
        } catch (e) {
            var splitter = rawProfileUrl.indexOf('?') >= 0 ? '&' : '?';
            var cleaned = rawProfileUrl.replace(/([?&])back=[^&#]*/i, '$1').replace(/[?&]$/, '');
            return cleaned + splitter + 'back=' + encodeURIComponent(rawBackUrl);
        }
    }

    function buildModalBackUrl(collectionId) {
        return i18nGlobal.mineTabUrl + '&share_modal=' + encodeURIComponent(String(Number(collectionId || 0)));
    }

    function getRequestedShareModalId() {
        try {
            var params = new URLSearchParams(window.location.search || '');
            return Number(params.get('share_modal') || 0);
        } catch (e) {
            return 0;
        }
    }

    function toMeasurementsLink(query) {
        var params = $.extend({}, query || {});
        params.return_to = i18nGlobal.returnTo || (window.location.pathname + window.location.search);
        return '/user/measurements.php?' + $.param(params);
    }

    function toSlotMeasurementsLink(collectionId, stationSerial, start, end) {
        var dateFrom = formatDateTime(start);
        var dateTo = formatDateTime(end);
        return toMeasurementsLink({
            collection: collectionId,
            station: stationSerial,
            date_from: dateFrom,
            date_to: dateTo
        });
    }

    function normalizeEuToIsoDateTime(value) {
        var raw = String(value || '').trim();
        var m = raw.match(/^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2})$/);
        if (m) {
            return m[3] + '-' + m[2] + '-' + m[1] + ' ' + m[4] + ':' + m[5];
        }

        m = raw.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::\d{2})?$/);
        if (m) {
            return m[1] + '-' + m[2] + '-' + m[3] + ' ' + m[4] + ':' + m[5];
        }

        return raw;
    }

    function toIsoDateOnly(value) {
        var normalized = normalizeEuToIsoDateTime(value);
        var m = String(normalized || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? (m[1] + '-' + m[2] + '-' + m[3]) : '';
    }

    function parseDateTimeValue(value) {
        var raw = normalizeEuToIsoDateTime(value);
        if (raw === '') {
            return NaN;
        }
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(raw)) {
            raw += ':00';
        }
        return Date.parse(raw.replace(' ', 'T'));
    }

    function hasSlotOverlapInDom(station, start, end) {
        var startMs = parseDateTimeValue(start);
        var endMs = parseDateTimeValue(end);
        if (!isFinite(startMs) || !isFinite(endMs)) {
            return false;
        }

        var isOverlap = false;
        $('#slotsTableBody tr').each(function () {
            var $row = $(this);
            var rowStation = String($row.data('station') || '');
            if (rowStation !== String(station || '')) {
                return;
            }
            var rowStart = parseDateTimeValue($row.data('start'));
            var rowEnd = parseDateTimeValue($row.data('end'));
            if (!isFinite(rowStart) || !isFinite(rowEnd)) {
                return;
            }

            if (startMs <= rowEnd && endMs >= rowStart) {
                isOverlap = true;
                return false;
            }
        });

        return isOverlap;
    }

    function initDateTimeInputs() {
        if (!$.fn.datetimepicker) {
            return;
        }

        $('.js-datetime-input').each(function () {
            var $input = $(this);
            if ($input.data('dtp-initialized')) {
                return;
            }
            $input.datetimepicker({
                format: 'd.m.Y H:i',
                step: 5,
                dayOfWeekStart: 1,
                scrollInput: false,
                closeOnDateSelect: false
            });
            $input.data('dtp-initialized', true);
        });

        $('.slot-picker-icon').off('click.slotPicker').on('click.slotPicker', function () {
            var $icon = $(this);
            var $input = $icon.siblings('input.js-datetime-input').first();
            if (!$input.length) {
                $input = $icon.closest('.input-group').find('input.js-datetime-input').first();
            }
            if ($input.length) {
                $input.trigger('focus');
                try {
                    $input.datetimepicker('show');
                } catch (e) {
                    // Fallback to focus only when show method is unavailable.
                }
            }
        });
    }

    var i18nGlobal = getI18n();
    var collectionShareFriends = Array.isArray(window.collectionShareFriends) ? window.collectionShareFriends : [];
    var collectionSharesByCollection = (window.collectionSharesByCollection && typeof window.collectionSharesByCollection === 'object') ? window.collectionSharesByCollection : {};

    function getEditCollectionStateKey() {
        var collectionId = Number($('#editCollectionId').val() || 0);
        if (!collectionId) {
            return '';
        }
        return 'collections.edit.state.' + collectionId;
    }

    function readEditCollectionState() {
        var key = getEditCollectionStateKey();
        if (!key || !window.sessionStorage) {
            return null;
        }
        try {
            var raw = sessionStorage.getItem(key) || '';
            if (!raw) {
                return null;
            }
            var data = JSON.parse(raw);
            return data && typeof data === 'object' ? data : null;
        } catch (e) {
            return null;
        }
    }

    function saveEditCollectionState(extra) {
        var key = getEditCollectionStateKey();
        if (!key || !window.sessionStorage) {
            return;
        }

        var payload = {
            name: String($('#editCollectionName').val() || ''),
            description: String($('#editCollectionDescription').val() || ''),
            slot_station: String($('#addSlotForm select[name="station"]').val() || ''),
            slot_start: String($('#addSlotForm input[name="start"]').val() || ''),
            slot_end: String($('#addSlotForm input[name="end"]').val() || ''),
            share_search: String($('#collectionShareSearch').val() || ''),
            share_selected: [],
            ts: Date.now()
        };

        $('#collectionShareFriendsList .js-share-friend-check:checked').each(function () {
            payload.share_selected.push(String($(this).val() || ''));
        });

        if (extra && typeof extra === 'object') {
            $.extend(payload, extra);
        }

        try {
            sessionStorage.setItem(key, JSON.stringify(payload));
        } catch (e) {
            // Ignore storage write issues.
        }
    }

    function buildShareUserCard(friend, i18n, options) {
        var username = String(friend.username || '').trim();
        var firstName = String(friend.firstName || '').trim();
        var lastName = String(friend.lastName || '').trim();
        var firstLine = firstName || username;
        var chatUrl = String(friend.chatUrl || ('/user/chat.php?with=' + encodeURIComponent(username)));
        var avatarUrl = String(friend.avatarUrl || '').trim();
        var profileUrl = String(friend.profileUrl || '#');
        var backUrl = (options && options.backUrl) ? String(options.backUrl) : '';

        if (backUrl === '') {
            backUrl = String(i18nGlobal.returnTo || '').trim();
        }

        if (backUrl !== '') {
            profileUrl = buildUrlWithBackParam(profileUrl, backUrl);
            chatUrl = buildUrlWithBackParam(chatUrl, backUrl);
        }

        return '' +
            '<div class="share-user-card-wrap" data-share-user="' + escapeHtml(username) + '">' +
            '<div class="share-user-card">' +
            '<div class="d-flex align-items-center gap-2">' +
            (avatarUrl ? '<img src="' + escapeHtml(avatarUrl) + '" class="share-user-avatar" alt="avatar">' : '<span class="share-user-avatar"><i class="bi bi-person-circle"></i></span>') +
            '<div class="share-user-meta">' +
            '<div class="share-user-firstname">' + escapeHtml(firstLine) + '</div>' +
            '<div class="share-user-lastname">' + escapeHtml(lastName) + '</div>' +
            '<div class="share-user-username">@' + escapeHtml(username) + '</div>' +
            '</div>' +
            '</div>' +
            '<div class="mt-2 d-flex justify-content-center gap-2">' +
            '<a href="' + escapeHtml(profileUrl) + '" class="btn btn-sm btn-outline-secondary" title="' + escapeHtml(i18n.viewProfileLabel) + '"><i class="bi bi-person"></i></a>' +
            '<a href="' + escapeHtml(chatUrl) + '" class="btn btn-sm btn-outline-primary" title="' + escapeHtml(i18n.chatLabel) + '"><i class="bi bi-chat"></i></a>' +
            '<button type="button" class="btn btn-sm btn-outline-danger js-unshare-collection-user" data-username="' + escapeHtml(username) + '" title="' + escapeHtml(i18n.unshareLabel) + '"><i class="bi bi-x-circle"></i></button>' +
            '</div>' +
            '</div>' +
            '</div>';
    }

    function collectAlreadySharedUsers() {
        var map = {};
        $('#collectionSharesList [data-share-user]').each(function () {
            var username = String($(this).data('share-user') || '').trim();
            if (username) {
                map[username] = true;
            }
        });
        return map;
    }

    function renderShareFriendsList(i18n, preselected) {
        var $list = $('#collectionShareFriendsList');
        if (!$list.length) {
            return;
        }

        var sharedMap = collectAlreadySharedUsers();
        var selectedMap = {};
        (preselected || []).forEach(function (u) {
            var val = String(u || '').trim();
            if (val) {
                selectedMap[val] = true;
            }
        });

        var query = String($('#collectionShareSearch').val() || '').trim().toLowerCase();
        var html = '';
        var visible = 0;

        collectionShareFriends.forEach(function (friend) {
            var username = String(friend.username || '').trim();
            if (!username || sharedMap[username]) {
                return;
            }
            var firstName = String(friend.firstName || '').trim();
            var lastName = String(friend.lastName || '').trim();
            var fullName = (firstName + ' ' + lastName).trim();
            var haystack = (username + ' ' + firstName + ' ' + lastName + ' ' + fullName).toLowerCase();
            if (query && haystack.indexOf(query) === -1) {
                return;
            }

            var checked = selectedMap[username] ? ' checked' : '';
            var avatarUrl = String(friend.avatarUrl || '').trim();
            var fallbackName = fullName || username;

            html += '' +
                '<label class="share-friend-item" data-share-friend="' + escapeHtml(username) + '">' +
                '<input type="checkbox" class="form-check-input js-share-friend-check" value="' + escapeHtml(username) + '"' + checked + '>' +
                (avatarUrl ? '<img src="' + escapeHtml(avatarUrl) + '" class="share-user-avatar" alt="avatar">' : '<span class="share-user-avatar"><i class="bi bi-person-circle"></i></span>') +
                '<span class="share-friend-meta">' +
                '<span class="share-user-username">@' + escapeHtml(username) + '</span>' +
                '<span class="share-user-fullname">' + escapeHtml(fallbackName) + '</span>' +
                '</span>' +
                '</label>';
            visible += 1;
        });

        if (!visible) {
            html = '<div class="text-muted small py-2">' + escapeHtml(i18n.noFriendsLabel) + '</div>';
        }

        $list.html(html);
    }

    function appendSharedUserCardByUsername(username, i18n) {
        var key = String(username || '').trim();
        if (!key) {
            return;
        }
        var exists = false;
        $('#collectionSharesList [data-share-user]').each(function () {
            if (String($(this).data('share-user') || '') === key) {
                exists = true;
                return false;
            }
        });
        if (exists) {
            return;
        }

        var friend = null;
        for (var i = 0; i < collectionShareFriends.length; i += 1) {
            if (String(collectionShareFriends[i].username || '') === key) {
                friend = collectionShareFriends[i];
                break;
            }
        }
        if (!friend) {
            return;
        }

        $('#collectionSharesList').append(buildShareUserCard(friend, i18n));
        $('#collectionSharesEmpty').addClass('d-none');
    }

    function getCollectionSharesLocal(collectionId) {
        var key = String(collectionId || '0');
        var rows = collectionSharesByCollection[key];
        return Array.isArray(rows) ? rows.slice() : [];
    }

    function setCollectionSharesLocal(collectionId, rows) {
        var key = String(collectionId || '0');
        collectionSharesByCollection[key] = Array.isArray(rows) ? rows.slice() : [];
    }

    function setCollectionShareFriendsLocal(rows) {
        collectionShareFriends = Array.isArray(rows) ? rows.slice() : [];
        window.collectionShareFriends = collectionShareFriends;
    }

    function normalizeShareRow(row) {
        var safe = row && typeof row === 'object' ? row : {};
        return {
            username: String(safe.username || '').trim(),
            firstName: String(safe.firstName || '').trim(),
            lastName: String(safe.lastName || '').trim(),
            fullName: String(safe.fullName || '').trim(),
            avatarUrl: String(safe.avatarUrl || '').trim(),
            profileUrl: String(safe.profileUrl || '#').trim() || '#',
            chatUrl: String(safe.chatUrl || '').trim()
        };
    }

    function loadShareData(collectionId) {
        var id = Number(collectionId || 0);
        if (!id) {
            return $.Deferred().reject().promise();
        }

        return ajaxPost({
            action: 'get_share_data',
            collection_id: id,
            return_to: i18nGlobal.returnTo || (window.location.pathname + window.location.search)
        }).then(function (res) {
            var data = (res && res.success && res.data) ? res.data : null;
            if (!data) {
                return $.Deferred().reject(res).promise();
            }

            var shares = Array.isArray(data.shares) ? data.shares.map(normalizeShareRow).filter(function (row) {
                return row.username !== '';
            }) : [];
            var friends = Array.isArray(data.friends) ? data.friends.map(normalizeShareRow).filter(function (row) {
                return row.username !== '';
            }) : [];

            setCollectionSharesLocal(id, shares);
            setCollectionShareFriendsLocal(friends);
            return { collectionId: id, shares: shares, friends: friends };
        });
    }

    function findFriendByUsername(username) {
        var key = String(username || '').trim();
        if (!key) {
            return null;
        }
        for (var i = 0; i < collectionShareFriends.length; i += 1) {
            if (String(collectionShareFriends[i].username || '') === key) {
                return collectionShareFriends[i];
            }
        }
        return null;
    }

    function upsertCollectionShare(collectionId, username) {
        var key = String(username || '').trim();
        if (!key) {
            return;
        }
        var rows = getCollectionSharesLocal(collectionId);
        var exists = false;
        rows.forEach(function (row) {
            if (String(row.username || '') === key) {
                exists = true;
            }
        });
        if (exists) {
            return;
        }
        var friend = findFriendByUsername(key);
        if (!friend) {
            return;
        }
        rows.push({
            username: String(friend.username || ''),
            firstName: String(friend.firstName || ''),
            lastName: String(friend.lastName || ''),
            avatarUrl: String(friend.avatarUrl || ''),
            profileUrl: String(friend.profileUrl || '#')
        });
        setCollectionSharesLocal(collectionId, rows);
    }

    function renderEditCollectionShares(collectionId, i18n) {
        var $list = $('#collectionSharesList');
        var $empty = $('#collectionSharesEmpty');
        if (!$list.length || !$empty.length) {
            return;
        }

        var rows = getCollectionSharesLocal(collectionId);
        if (!rows.length) {
            $list.empty();
            $empty.removeClass('d-none');
            return;
        }

        var html = '';
        rows.forEach(function (row) {
            html += buildShareUserCard(row, i18n);
        });
        $list.html(html);
        $list.attr('data-collection-id', String(Number(collectionId || 0)));
        $empty.addClass('d-none');
    }

    function removeCollectionShare(collectionId, username) {
        var key = String(username || '').trim();
        var rows = getCollectionSharesLocal(collectionId).filter(function (row) {
            return String(row.username || '') !== key;
        });
        setCollectionSharesLocal(collectionId, rows);
    }

    function renderCollectionCardSharePreview(collectionId) {
        var $card = $('[data-collection-id="' + Number(collectionId || 0) + '"]').first();
        if (!$card.length) {
            return;
        }

        var i18n = i18nGlobal;
        var rows = getCollectionSharesLocal(collectionId);
        var $strip = $card.find('[data-collection-share-strip]').first();
        if (!$strip.length) {
            return;
        }

        if (!rows.length) {
            $strip.html('<span class="text-muted small">' + escapeHtml(i18n.noSharedUsersLabel) + '</span>');
            $strip.attr('data-hydrated', '1');
            return;
        }

        var maxPreview = getCollectionSharePreviewCapacity($strip, rows.length);
        var preview = rows.slice(0, maxPreview);
        var hasMore = rows.length > preview.length;
        var html = '';
        preview.forEach(function (row) {
            var username = String(row.username || '');
            var avatarUrl = String(row.avatarUrl || '');
            var profileUrl = String(row.profileUrl || '#');
            var fullName = (String(row.firstName || '') + ' ' + String(row.lastName || '')).trim() || username;
            html += '<a href="' + escapeHtml(profileUrl) + '" class="collection-share-item" title="' + escapeHtml(fullName) + '">';
            html += avatarUrl
                ? '<img src="' + escapeHtml(avatarUrl) + '" class="collection-share-avatar" alt="avatar">'
                : '<span class="collection-share-avatar"><i class="bi bi-person-circle"></i></span>';
            html += '<span class="collection-share-username">@' + escapeHtml(username) + '</span>';
            html += '</a>';
        });

        if (hasMore) {
            html += '<button type="button" class="btn btn-sm btn-outline-secondary collection-share-more js-open-card-share-modal" data-collection-id="' + Number(collectionId || 0) + '" title="' + escapeHtml(i18n.sharedWithLabel) + '">...</button>';
        }

        $strip.html(html);
        $strip.attr('data-hydrated', '1');
    }

    function getCollectionSharePreviewCapacity($strip, totalRows) {
        if (!$strip.length || totalRows <= 0) {
            return 0;
        }

        var style = window.getComputedStyle($strip.get(0));
        var gap = parseFloat(style.columnGap || style.gap || '0') || 0;
        var available = Math.floor($strip.innerWidth());
        if (available <= 0) {
            return Math.min(totalRows, 1);
        }

        var $probeItem = $('<a href="#" class="collection-share-item" style="position:absolute;visibility:hidden;pointer-events:none;"><span class="collection-share-avatar"></span><span class="collection-share-username">@probe</span></a>');
        var $probeMore = $('<button type="button" class="btn btn-sm btn-outline-secondary collection-share-more" style="position:absolute;visibility:hidden;pointer-events:none;">...</button>');
        $strip.append($probeItem);
        $strip.append($probeMore);

        var itemWidth = Math.ceil($probeItem.outerWidth() || 0);
        var moreWidth = Math.ceil($probeMore.outerWidth() || 0);

        $probeItem.remove();
        $probeMore.remove();

        if (itemWidth <= 0) {
            itemWidth = 42;
        }
        if (moreWidth <= 0) {
            moreWidth = 34;
        }

        var count = Math.min(totalRows, Math.max(1, Math.floor((available + gap) / (itemWidth + gap))));
        while (count > 1) {
            var needMore = totalRows > count;
            var used = (count * itemWidth) + ((count - 1) * gap) + (needMore ? (gap + moreWidth) : 0);
            if (used <= available + 0.5) {
                break;
            }
            count -= 1;
        }

        return Math.max(1, Math.min(totalRows, count));
    }

    function renderAllCollectionCardSharePreviews() {
        $('[data-collection-id]').each(function () {
            var id = Number($(this).data('collection-id') || 0);
            if (id > 0) {
                renderCollectionCardSharePreview(id);
            }
        });
    }

    function renderCardShareModalUsers(collectionId, i18n) {
        var rows = getCollectionSharesLocal(collectionId);
        var $list = $('#collectionCardShareModalUsersList');
        var $empty = $('#collectionCardShareModalUsersEmpty');

        if (!rows.length) {
            $list.empty();
            $empty.removeClass('d-none');
            return;
        }

        var html = '';
        var modalBackUrl = buildModalBackUrl(collectionId);
        rows.forEach(function (row) {
            html += buildShareUserCard(row, i18n, { backUrl: modalBackUrl });
        });
        $list.html(html);
        $list.attr('data-collection-id', String(Number(collectionId || 0)));
        $empty.addClass('d-none');
    }

    function renderCardShareModalFriends(collectionId, i18n, preselected) {
        var selectedMap = {};
        (preselected || []).forEach(function (value) {
            var key = String(value || '').trim();
            if (key) {
                selectedMap[key] = true;
            }
        });

        var sharedMap = {};
        getCollectionSharesLocal(collectionId).forEach(function (row) {
            var key = String(row.username || '').trim();
            if (key) {
                sharedMap[key] = true;
            }
        });

        var query = String($('#collectionCardShareModalSearch').val() || '').trim().toLowerCase();
        var html = '';
        var visible = 0;

        collectionShareFriends.forEach(function (friend) {
            var username = String(friend.username || '').trim();
            if (!username || sharedMap[username]) {
                return;
            }
            var firstName = String(friend.firstName || '').trim();
            var lastName = String(friend.lastName || '').trim();
            var fullName = (firstName + ' ' + lastName).trim();
            var haystack = (username + ' ' + firstName + ' ' + lastName + ' ' + fullName).toLowerCase();
            if (query && haystack.indexOf(query) === -1) {
                return;
            }

            var avatarUrl = String(friend.avatarUrl || '').trim();
            var checked = selectedMap[username] ? ' checked' : '';
            var fallbackName = fullName || username;
            html += '<label class="share-friend-item" data-share-friend="' + escapeHtml(username) + '">';
            html += '<input type="checkbox" class="form-check-input js-card-share-friend-check" value="' + escapeHtml(username) + '"' + checked + '>';
            html += avatarUrl
                ? '<img src="' + escapeHtml(avatarUrl) + '" class="share-user-avatar" alt="avatar">'
                : '<span class="share-user-avatar"><i class="bi bi-person-circle"></i></span>';
            html += '<span class="share-friend-meta">';
            html += '<span class="share-user-username">@' + escapeHtml(username) + '</span>';
            html += '<span class="share-user-fullname">' + escapeHtml(fallbackName) + '</span>';
            html += '</span>';
            html += '</label>';
            visible += 1;
        });

        if (!visible) {
            html = '<div class="text-muted small py-2">' + escapeHtml(i18n.noFriendsLabel) + '</div>';
        }

        $('#collectionCardShareModalFriendsList').html(html);
    }

    function openCardShareModal(collectionId, i18n) {
        var id = Number(collectionId || 0);
        if (!id) {
            return;
        }

        var $card = $('[data-collection-id="' + id + '"]').first();
        var collectionName = String($card.data('collection-name') || '').trim();
        $('#collectionCardShareModalCollectionId').val(String(id));
        $('#collectionCardShareModalTitle').text(collectionName !== '' ? (i18n.shareLabel + ': ' + collectionName) : i18n.shareLabel);
        $('#collectionCardShareModalSearch').val('');
        loadShareData(id)
            .done(function () {
                renderCollectionCardSharePreview(id);
                renderCardShareModalUsers(id, i18n);
                renderCardShareModalFriends(id, i18n, []);
            })
            .fail(function () {
                renderCardShareModalUsers(id, i18n);
                renderCardShareModalFriends(id, i18n, []);
            });

        var modalEl = document.getElementById('collectionCardShareModal');
        if (modalEl) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function getCheckedValues(selector) {
        var result = [];
        $(selector + ':checked').each(function () {
            result.push(String($(this).val() || ''));
        });
        return result;
    }

    function refreshSharesForCollection(collectionId, i18n) {
        var id = Number(collectionId || 0);
        if (!id) {
            return $.Deferred().resolve().promise();
        }

        var editSelected = getCheckedValues('#collectionShareFriendsList .js-share-friend-check');
        var modalSelected = getCheckedValues('#collectionCardShareModalFriendsList .js-card-share-friend-check');

        return loadShareData(id).done(function () {
            renderCollectionCardSharePreview(id);

            var currentEditId = Number($('#editCollectionId').val() || 0);
            if (currentEditId === id) {
                renderEditCollectionShares(id, i18n);
                renderShareFriendsList(i18n, editSelected);
            }

            var modalCollectionId = Number($('#collectionCardShareModalCollectionId').val() || 0);
            var modalShown = $('#collectionCardShareModal').hasClass('show');
            if (modalShown && modalCollectionId === id) {
                renderCardShareModalUsers(id, i18n);
                renderCardShareModalFriends(id, i18n, modalSelected);
            }
        });
    }

    function scheduleLiveShareRefresh(i18n) {
        window.setInterval(function () {
            var editCollectionId = Number($('#editCollectionId').val() || 0);
            if (editCollectionId > 0) {
                refreshSharesForCollection(editCollectionId, i18n);
            }

            var modalCollectionId = Number($('#collectionCardShareModalCollectionId').val() || 0);
            var modalShown = $('#collectionCardShareModal').hasClass('show');
            if (modalShown && modalCollectionId > 0 && modalCollectionId !== editCollectionId) {
                refreshSharesForCollection(modalCollectionId, i18n);
            }

            if (editCollectionId === 0 && !modalShown) {
                $('[data-collection-id]').each(function () {
                    var id = Number($(this).data('collection-id') || 0);
                    if (id > 0) {
                        loadShareData(id)
                            .done(function () {
                                renderCollectionCardSharePreview(id);
                            });
                    }
                });
            }
        }, 10000);
    }

    function scheduleSharedTabLiveRefresh() {
        window.setInterval(function () {
            if (document.hidden) {
                return;
            }

            if ($('#editCollectionId').length) {
                return;
            }

            var params = new URLSearchParams(window.location.search || '');
            var tab = params.get('tab') || 'mine';
            if (tab !== 'shared') {
                return;
            }

            if ($('.modal.show').length > 0) {
                return;
            }

            var activeEl = document.activeElement;
            if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.tagName === 'SELECT' || activeEl.isContentEditable)) {
                return;
            }

            var url = new URL(window.location.href);
            url.searchParams.set('_live', String(Date.now()));

            $.ajax({
                url: url.toString(),
                type: 'GET',
                dataType: 'html',
                cache: false
            }).done(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(String(html || ''), 'text/html');
                var incoming = doc.getElementById('sharedCollectionsTabContent');
                var current = document.getElementById('sharedCollectionsTabContent');
                if (!incoming || !current) {
                    return;
                }
                current.innerHTML = incoming.innerHTML;

                var stamp = String(Date.now());
                current.querySelectorAll('img.share-user-avatar[src]').forEach(function (img) {
                    var raw = String(img.getAttribute('src') || '').trim();
                    if (raw === '') {
                        return;
                    }
                    try {
                        var u = new URL(raw, window.location.origin);
                        u.searchParams.set('_live', stamp);
                        img.setAttribute('src', u.pathname + u.search);
                    } catch (e) {
                        // keep original src on parse failures
                    }
                });
            });
        }, 15000);
    }

    function loadCollectionsTabByUrl(rawUrl, pushHistory) {
        if ($('#editCollectionId').length) {
            return;
        }

        var url = new URL(rawUrl, window.location.origin);
        url.searchParams.set('_tab_ajax', '1');

        $.ajax({
            url: url.toString(),
            type: 'GET',
            dataType: 'html',
            cache: false
        }).done(function (html) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(String(html || ''), 'text/html');
            var incomingNav = doc.getElementById('collectionsTabsNav');
            var incomingContent = doc.getElementById('collectionsTabContent');
            var currentNav = document.getElementById('collectionsTabsNav');
            var currentContent = document.getElementById('collectionsTabContent');
            if (!incomingNav || !incomingContent || !currentNav || !currentContent) {
                window.location.href = rawUrl;
                return;
            }

            currentNav.innerHTML = incomingNav.innerHTML;
            currentContent.innerHTML = incomingContent.innerHTML;

            initDateTimeInputs();
            renderAllCollectionCardSharePreviews();

            if (pushHistory) {
                var clean = new URL(rawUrl, window.location.origin);
                clean.searchParams.delete('_tab_ajax');
                history.pushState({ tab: clean.searchParams.get('tab') || 'mine' }, '', clean.pathname + clean.search);
            }
        }).fail(function () {
            window.location.href = rawUrl;
        });
    }

    function pad2(num) {
        return String(num).padStart(2, '0');
    }

    function formatDateTime(value) {
        var text = String(value || '').trim();
        if (text === '') {
            return '-';
        }
        var normalized = text.replace(' ', 'T');
        var date = new Date(normalized);
        if (isNaN(date.getTime())) {
            return text;
        }
        return pad2(date.getDate()) + '.' + pad2(date.getMonth() + 1) + '.' + date.getFullYear() + ' ' + pad2(date.getHours()) + ':' + pad2(date.getMinutes());
    }

    function ajaxPost(payload) {
        return $.ajax({
            url: '/api/collections.php',
            type: 'POST',
            dataType: 'json',
            data: payload
        });
    }

    function buildCollectionCard(data, i18n) {
        var id = Number(data.collection_id || 0);
        var name = String(data.name || '').trim();
        var desc = String(data.description || '').trim();
        var created = formatDateTime(data.created_at || '');
        var safeName = escapeHtml(name);
        var safeDesc = escapeHtml(desc === '' ? '-' : desc);
        var deleteTitle = escapeHtml(i18n.deleteLabel);

        return '' +
            '<div class="col-12 col-sm-6 col-lg-4 col-xl-3" data-collection-col="' + id + '">' +
            '<div class="card station-list-card collection-list-card h-100" data-collection-id="' + id + '" data-collection-name="' + safeName + '">' +
            '<form method="post" class="collection-card-delete js-delete-collection-form">' +
            '<input type="hidden" name="action" value="delete">' +
            '<input type="hidden" name="collection_id" value="' + id + '">' +
            '<button type="submit" class="btn btn-sm btn-outline-danger" title="' + deleteTitle + '"><i class="bi bi-x-circle"></i></button>' +
            '</form>' +
            '<div class="card-body d-flex flex-column">' +
            '<h6 class="mb-2 station-card-title collection-card-title text-truncate" data-collection-name>' + safeName + '</h6>' +
            '<div class="small text-muted mb-1">' + escapeHtml(i18n.descriptionLabel) + '</div>' +
            '<div class="collection-card-meta mb-2" data-collection-description>' + safeDesc + '</div>' +
            '<div class="small text-muted mb-1">' + escapeHtml(i18n.createdAtLabel) + '</div>' +
            '<div class="small" data-collection-created>' + escapeHtml(created) + '</div>' +
            '<div class="small text-muted mb-1 mt-2">' + escapeHtml(i18n.sharedWithLabel) + '</div>' +
            '<div class="collection-card-shares" data-collection-share-strip><span class="text-muted small">' + escapeHtml(i18n.noSharedUsersLabel) + '</span></div>' +
            '</div>' +
            '<div class="card-footer bg-transparent border-top-0 pt-1">' +
            '<div class="d-flex gap-2 station-card-actions">' +
            '<a href="/user/collections.php?edit=' + id + '" class="btn btn-outline-primary" title="' + escapeHtml(i18n.editLabel) + '"><i class="bi bi-pencil"></i></a>' +
            '<a href="' + escapeHtml(toMeasurementsLink({ collection: id })) + '" class="btn btn-outline-secondary" title="' + escapeHtml(i18n.measurementsLabel) + '"><i class="bi bi-graph-up"></i></a>' +
            '<button type="button" class="btn btn-outline-secondary js-open-card-share-modal" data-collection-id="' + id + '" title="' + escapeHtml(i18n.shareLabel) + '"><i class="bi bi-share"></i></button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
    }

    function syncMyCollectionEmptyState() {
        var $grid = $('#myCollectionsGrid');
        var $empty = $('#myCollectionsEmpty');
        if (!$grid.length || !$empty.length) {
            return;
        }
        var hasItems = $grid.children().length > 0;
        $grid.toggleClass('d-none', !hasItems);
        $empty.toggleClass('d-none', hasItems);
    }

    $(function () {
        var i18n = i18nGlobal;
        var $globalAlerts = $('#collectionsAjaxAlerts');

        initDateTimeInputs();
        renderAllCollectionCardSharePreviews();
        scheduleLiveShareRefresh(i18n);
        scheduleSharedTabLiveRefresh();

        var previewResizeTimer = null;
        $(window).on('resize.collectionsSharePreview', function () {
            if (previewResizeTimer) {
                clearTimeout(previewResizeTimer);
            }
            previewResizeTimer = setTimeout(function () {
                renderAllCollectionCardSharePreviews();
            }, 120);
        });

        $(document).on('click', '#collectionsTabsNav a.js-collections-tab-link', function (e) {
            var href = String($(this).attr('href') || '').trim();
            if (href === '' || href.indexOf('#') === 0 || href.indexOf('javascript:') === 0) {
                return;
            }
            e.preventDefault();
            loadCollectionsTabByUrl(href, true);
        });

        $(window).on('popstate.collectionsTabs', function () {
            loadCollectionsTabByUrl(window.location.href, false);
        });

        $('#createCollectionForm').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var data = $form.serializeArray();
            var $modalAlerts = $form.find('[data-modal-alerts]');

            ajaxPost(data)
                .done(function (res) {
                    if (!res || !res.success || !res.data) {
                        showAlert((res && res.message) || i18n.defaultError, 'danger', $modalAlerts);
                        return;
                    }

                    var html = buildCollectionCard(res.data, i18n);
                    var $grid = $('#myCollectionsGrid');
                    if ($grid.length) {
                        $grid.prepend(html);
                        setCollectionSharesLocal(Number(res.data.collection_id || 0), []);
                        syncMyCollectionEmptyState();
                    }

                    $form[0].reset();
                    $modalAlerts.empty();
                    var modalEl = document.getElementById('createModal');
                    if (modalEl) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    }
                })
                .fail(function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) || i18n.defaultError;
                    showAlert(message, 'danger', $modalAlerts);
                });
        });

        $(document).on('submit', '.js-delete-collection-form', function (e) {
            e.preventDefault();
            if (!window.confirm(i18n.confirmDelete)) {
                return;
            }

            var $form = $(this);
            var payload = $form.serializeArray();
            var collectionId = Number($form.find('input[name="collection_id"]').val() || 0);

            ajaxPost(payload)
                .done(function (res) {
                    if (!res || !res.success) {
                        showAlert((res && res.message) || i18n.defaultError, 'danger', $globalAlerts);
                        return;
                    }
                    $('[data-collection-col="' + collectionId + '"]').remove();
                    syncMyCollectionEmptyState();
                })
                .fail(function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) || i18n.defaultError;
                    showAlert(message, 'danger', $globalAlerts);
                });
        });

        $(document).on('click', '.js-open-card-share-modal', function () {
            var collectionId = Number($(this).data('collection-id') || 0);
            openCardShareModal(collectionId, i18n);
        });

        $('#collectionCardShareModalSearch').on('input', function () {
            var collectionId = Number($('#collectionCardShareModalCollectionId').val() || 0);
            var selected = [];
            $('#collectionCardShareModalFriendsList .js-card-share-friend-check:checked').each(function () {
                selected.push(String($(this).val() || ''));
            });
            renderCardShareModalFriends(collectionId, i18n, selected);
        });

        $(document).on('click', '#collectionCardShareModalShareBtn', function () {
            var collectionId = Number($('#collectionCardShareModalCollectionId').val() || 0);
            if (!collectionId) {
                return;
            }

            var selected = [];
            $('#collectionCardShareModalFriendsList .js-card-share-friend-check:checked').each(function () {
                selected.push(String($(this).val() || ''));
            });

            if (!selected.length) {
                showAlert(i18n.selectUsersToShareLabel, 'danger', $globalAlerts);
                return;
            }

            ajaxPost({ action: 'share_many', collection_id: collectionId, share_with: selected })
                .done(function (res) {
                    if (!res || !res.success) {
                        showAlert((res && res.message) || i18n.defaultError, 'danger', $globalAlerts);
                        return;
                    }

                    loadShareData(collectionId)
                        .done(function () {
                            renderCollectionCardSharePreview(collectionId);
                            renderCardShareModalUsers(collectionId, i18n);
                            renderCardShareModalFriends(collectionId, i18n, []);
                            var editCollectionId = Number($('#editCollectionId').val() || 0);
                            if (editCollectionId === collectionId) {
                                renderEditCollectionShares(collectionId, i18n);
                                renderShareFriendsList(i18n, []);
                                saveEditCollectionState({ share_selected: [] });
                            }
                        })
                        .fail(function () {
                            showAlert(i18n.defaultError, 'danger', $globalAlerts);
                        });
                })
                .fail(function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) || i18n.defaultError;
                    showAlert(message, 'danger', $globalAlerts);
                });
        });

        var autosaveTimer = null;
        var lastNameSaved = null;
        var lastDescriptionSaved = null;

        function saveName() {
            var collectionId = Number($('#editCollectionId').val() || 0);
            var name = String($('#editCollectionName').val() || '');
            if (!collectionId || name.trim() === '' || name === lastNameSaved) {
                return;
            }

            ajaxPost({ action: 'update_name', collection_id: collectionId, name: name })
                .done(function (res) {
                    if (!res || !res.success) {
                        showAlert((res && res.message) || i18n.defaultError, 'danger', $globalAlerts);
                        return;
                    }
                    lastNameSaved = name;
                    $('#editCollectionTitle').text(name.trim());
                })
                .fail(function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) || i18n.defaultError;
                    showAlert(message, 'danger', $globalAlerts);
                });
        }

        function saveDescription() {
            var collectionId = Number($('#editCollectionId').val() || 0);
            var description = String($('#editCollectionDescription').val() || '');
            if (!collectionId || description === lastDescriptionSaved) {
                return;
            }

            ajaxPost({ action: 'update_description', collection_id: collectionId, description: description })
                .done(function (res) {
                    if (!res || !res.success) {
                        showAlert((res && res.message) || i18n.defaultError, 'danger', $globalAlerts);
                        return;
                    }
                    lastDescriptionSaved = description;
                })
                .fail(function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) || i18n.defaultError;
                    showAlert(message, 'danger', $globalAlerts);
                });
        }

        function scheduleAutosave(fn) {
            if (autosaveTimer) {
                clearTimeout(autosaveTimer);
            }
            autosaveTimer = setTimeout(fn, 400);
        }

        if ($('#editCollectionId').length) {
            var restoredState = readEditCollectionState();
            if (restoredState) {
                if (typeof restoredState.name === 'string' && restoredState.name !== '') {
                    $('#editCollectionName').val(restoredState.name);
                    $('#editCollectionTitle').text(restoredState.name);
                }
                if (typeof restoredState.description === 'string') {
                    $('#editCollectionDescription').val(restoredState.description);
                }
                if (typeof restoredState.slot_station === 'string' && restoredState.slot_station !== '') {
                    $('#addSlotForm select[name="station"]').val(restoredState.slot_station);
                }
                if (typeof restoredState.slot_start === 'string') {
                    $('#addSlotForm input[name="start"]').val(restoredState.slot_start);
                }
                if (typeof restoredState.slot_end === 'string') {
                    $('#addSlotForm input[name="end"]').val(restoredState.slot_end);
                }
                if (typeof restoredState.share_search === 'string') {
                    $('#collectionShareSearch').val(restoredState.share_search);
                }
            }

            lastNameSaved = String($('#editCollectionName').val() || '');
            lastDescriptionSaved = String($('#editCollectionDescription').val() || '');

            var editCollectionId = Number($('#editCollectionId').val() || 0);
            loadShareData(editCollectionId)
                .done(function () {
                    renderEditCollectionShares(editCollectionId, i18n);
                    renderCollectionCardSharePreview(editCollectionId);
                    renderShareFriendsList(i18n, (restoredState && restoredState.share_selected) || []);
                })
                .fail(function () {
                    renderShareFriendsList(i18n, (restoredState && restoredState.share_selected) || []);
                });

            $('#editCollectionName, #editCollectionDescription').on('input', function () {
                saveEditCollectionState();
            });

            $('#addSlotForm').on('input change', 'input, select', function () {
                saveEditCollectionState();
            });

            $('#collectionShareSearch').on('input', function () {
                var selected = [];
                $('#collectionShareFriendsList .js-share-friend-check:checked').each(function () {
                    selected.push(String($(this).val() || ''));
                });
                renderShareFriendsList(i18n, selected);
                saveEditCollectionState();
            });

            $(document).on('change', '#collectionShareFriendsList .js-share-friend-check', function () {
                saveEditCollectionState();
            });

            $('#shareSelectedFriendsBtn').on('click', function () {
                var selected = [];
                $('#collectionShareFriendsList .js-share-friend-check:checked').each(function () {
                    selected.push(String($(this).val() || ''));
                });

                if (!selected.length) {
                    showAlert(i18n.selectUsersToShareLabel, 'danger', $globalAlerts);
                    return;
                }

                ajaxPost({
                    action: 'share_many',
                    collection_id: Number($('#editCollectionId').val() || 0),
                    share_with: selected
                })
                    .done(function (res) {
                        if (!res || !res.success) {
                            showAlert((res && res.message) || i18n.defaultError, 'danger', $globalAlerts);
                            return;
                        }

                        var currentCollectionId = Number($('#editCollectionId').val() || 0);
                        loadShareData(currentCollectionId)
                            .done(function () {
                                renderEditCollectionShares(currentCollectionId, i18n);
                                renderCollectionCardSharePreview(currentCollectionId);
                                renderShareFriendsList(i18n, []);
                                saveEditCollectionState({ share_selected: [] });

                                var modalCollectionId = Number($('#collectionCardShareModalCollectionId').val() || 0);
                                if (modalCollectionId === currentCollectionId) {
                                    renderCardShareModalUsers(currentCollectionId, i18n);
                                    renderCardShareModalFriends(currentCollectionId, i18n, []);
                                }
                            })
                            .fail(function () {
                                showAlert(i18n.defaultError, 'danger', $globalAlerts);
                            });
                    })
                    .fail(function (xhr) {
                        var message = (xhr.responseJSON && xhr.responseJSON.message) || i18n.defaultError;
                        showAlert(message, 'danger', $globalAlerts);
                    });
            });

            $('#editCollectionName').on('input', function () {
                scheduleAutosave(saveName);
            }).on('blur', function () {
                saveName();
            });

            $('#editCollectionDescription').on('input', function () {
                scheduleAutosave(saveDescription);
            }).on('blur', function () {
                saveDescription();
            });

            $(window).on('beforeunload.collectionsEditState', function () {
                saveEditCollectionState();
            });

            $(document).on('click.collectionsEditState', 'a[href], button[data-bs-dismiss], .btn-close', function () {
                saveEditCollectionState();
            });

            $(document).on('click', 'a[href*="/user/measurements.php"]', function () {
                saveEditCollectionState();
            });
        }

        $(document).on('click', '.js-unshare-collection-user', function () {
            var $btn = $(this);
            var username = String($btn.data('username') || '').trim();
            if (!username) {
                return;
            }

            var modalCollectionId = Number($('#collectionCardShareModalCollectionId').val() || 0);
            var inModal = $btn.closest('#collectionCardShareModalUsersList').length > 0;
            var domCollectionId = Number($btn.closest('[data-collection-id]').data('collection-id') || 0);
            var collectionId = inModal
                ? Number(modalCollectionId || domCollectionId || 0)
                : Number($('#editCollectionId').val() || domCollectionId || modalCollectionId || 0);
            if (!collectionId) {
                return;
            }

            ajaxPost({
                action: 'unshare',
                collection_id: collectionId,
                unshare_user: username
            })
                .done(function (res) {
                    if (!res || !res.success) {
                        showAlert((res && res.message) || i18n.defaultError, 'danger', $globalAlerts);
                        return;
                    }

                    loadShareData(collectionId)
                        .done(function () {
                            renderCollectionCardSharePreview(collectionId);
                            renderEditCollectionShares(collectionId, i18n);
                            renderShareFriendsList(i18n, []);
                            saveEditCollectionState({ share_selected: [] });

                            if (modalCollectionId > 0 && modalCollectionId === collectionId) {
                                renderCardShareModalUsers(modalCollectionId, i18n);
                                renderCardShareModalFriends(modalCollectionId, i18n, []);
                            }
                        })
                        .fail(function () {
                            showAlert(i18n.defaultError, 'danger', $globalAlerts);
                        });
                })
                .fail(function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) || i18n.defaultError;
                    showAlert(message, 'danger', $globalAlerts);
                });
        });

        $('#addSlotForm').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var station = String($form.find('select[name="station"]').val() || '');
            var start = String($form.find('input[name="start"]').val() || '');
            var end = String($form.find('input[name="end"]').val() || '');
            var normalizedStart = normalizeEuToIsoDateTime(start);
            var normalizedEnd = normalizeEuToIsoDateTime(end);
            var startMs = parseDateTimeValue(start);
            var endMs = parseDateTimeValue(end);

            if (!isFinite(startMs) || !isFinite(endMs) || startMs >= endMs) {
                showAlert(i18n.slotInvalidRange, 'danger', $globalAlerts);
                return;
            }

            if (hasSlotOverlapInDom(station, start, end)) {
                showAlert(i18n.slotOverlap, 'danger', $globalAlerts);
                return;
            }

            var payload = $form.serializeArray();
            payload = payload.map(function (item) {
                if (item.name === 'start') {
                    return { name: item.name, value: normalizedStart };
                }
                if (item.name === 'end') {
                    return { name: item.name, value: normalizedEnd };
                }
                return item;
            });

            ajaxPost(payload)
                .done(function (res) {
                    if (!res || !res.success || !res.data) {
                        showAlert((res && res.message) || i18n.defaultError, 'danger', $globalAlerts);
                        return;
                    }

                    var stationLabel = $('#slotsTableWrap thead th').eq(0).text() || 'Station';
                    var timeFrameLabel = $('#slotsTableWrap thead th').eq(1).text() || i18n.timeFrameLabel;

                    var rowHtml = '' +
                        '<tr data-slot-id="' + Number(res.data.slot_id || 0) + '" data-station="' + escapeHtml(res.data.station_serial || station) + '" data-start="' + escapeHtml(res.data.start || start) + '" data-end="' + escapeHtml(res.data.end || end) + '">' +
                        '<td data-label="' + escapeHtml(stationLabel) + '" class="slot-station-cell">' + escapeHtml(res.data.station || '-') + '</td>' +
                        '<td data-label="' + escapeHtml(timeFrameLabel) + '" data-label-mobile="' + escapeHtml(i18n.timeLabel) + '" class="slot-timeframe-cell">' +
                        '<span class="slot-timeframe-text">' + escapeHtml(formatDateTime(res.data.start || '')) + ' - ' + escapeHtml(formatDateTime(res.data.end || '')) + '</span>' +
                        '<span class="slot-mobile-window">' + escapeHtml(formatDateTime(res.data.start || '')) + ' - ' + escapeHtml(formatDateTime(res.data.end || '')) + '</span>' +
                        '</td>' +
                        '<td data-label="' + escapeHtml(i18n.actionsLabel) + '" class="slot-actions-cell">' +
                        '<div class="slot-row-actions">' +
                        '<a href="' + escapeHtml(toSlotMeasurementsLink(Number($('#editCollectionId').val() || 0), res.data.station_serial || station, res.data.start || start, res.data.end || end)) + '" class="btn btn-outline-secondary slot-view-btn" title="' + escapeHtml(i18n.viewLabel) + '"><i class="bi bi-eye"></i></a>' +
                        '<form method="post" class="d-inline js-remove-slot-form">' +
                        '<input type="hidden" name="action" value="remove_slot">' +
                        '<input type="hidden" name="sample_id" value="' + Number(res.data.slot_id || 0) + '">' +
                        '<input type="hidden" name="collection_id" value="' + Number($('#editCollectionId').val() || 0) + '">' +
                        '<button type="submit" class="btn btn-outline-danger slot-delete-btn" title="' + escapeHtml(i18n.deleteLabel) + '"><i class="bi bi-x-circle"></i></button>' +
                        '</form>' +
                        '</div>' +
                        '</td>' +
                        '</tr>';

                    $('#slotsTableBody').prepend(rowHtml);
                    $('#slotsTableWrap').removeClass('d-none');
                    $('#slotsTableWrap').scrollTop(0);
                    $('#slotsEmptyState').addClass('d-none');
                    $form[0].reset();
                    saveEditCollectionState({ slot_start: '', slot_end: '' });
                    initDateTimeInputs();
                })
                .fail(function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) || i18n.defaultError;
                    showAlert(message, 'danger', $globalAlerts);
                });
        });

        $(document).on('submit', '.js-remove-slot-form', function (e) {
            e.preventDefault();
            if (!window.confirm(i18n.confirmDelete)) {
                return;
            }

            var $form = $(this);
            var payload = $form.serializeArray();
            var slotId = Number($form.find('input[name="sample_id"]').val() || 0);

            ajaxPost(payload)
                .done(function (res) {
                    if (!res || !res.success) {
                        showAlert((res && res.message) || i18n.defaultError, 'danger', $globalAlerts);
                        return;
                    }

                    $('#slotsTableBody tr[data-slot-id="' + slotId + '"]').remove();
                    if ($('#slotsTableBody tr').length === 0) {
                        $('#slotsTableWrap').addClass('d-none');
                        $('#slotsEmptyState').removeClass('d-none');
                    }
                })
                .fail(function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) || i18n.defaultError;
                    showAlert(message, 'danger', $globalAlerts);
                });
        });

        var createModal = document.getElementById('createModal');
        if (createModal) {
            createModal.addEventListener('show.bs.modal', function () {
                $('#createCollectionForm [data-modal-alerts]').empty();
            });
        }

        var restoreModalId = getRequestedShareModalId();
        if (restoreModalId > 0) {
            openCardShareModal(restoreModalId, i18n);
        }

        $('#collectionCardShareModal').on('hidden.bs.modal', function () {
            if (!window.history || typeof window.history.replaceState !== 'function') {
                return;
            }

            try {
                var currentUrl = new URL(window.location.href);
                if (!currentUrl.searchParams.has('share_modal')) {
                    return;
                }
                currentUrl.searchParams.delete('share_modal');
                var nextUrl = currentUrl.pathname + (currentUrl.search ? currentUrl.search : '') + currentUrl.hash;
                window.history.replaceState({}, document.title, nextUrl);
            } catch (e) {
                // Ignore malformed location values.
            }
        });
    });
})(jQuery);
