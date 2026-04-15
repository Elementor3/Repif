$(function () {
    if (!$('#friendsSearch').length || !$('#friendsSearchResults').length) return;

    var friendsSearchTimer;
    var SEARCH_STORAGE_KEY = 'friendsSearchQuery';
    var referrer = document.referrer || '';

    var $friendsSearch = $('#friendsSearch');
    var $friendsResults = $('#friendsSearchResults');
    var $friendsList = $('#friendsList');
    var $friendsCount = $('#friendsCount');
    var $friendsEmpty = $('#friendsEmpty');
    var $incomingList = $('#incomingRequestsList');
    var $incomingCount = $('#incomingCount');
    var $incomingEmpty = $('#incomingEmpty');
    var $outgoingList = $('#outgoingRequestsList');
    var $outgoingCount = $('#outgoingCount');
    var $outgoingEmpty = $('#outgoingEmpty');
    var backToFriends = encodeURIComponent('/user/friends.php');

    function esc(str) {
        return $('<div>').text(str || '').html();
    }

    function parseSqlDate(sqlDate) {
        if (!sqlDate) return new Date();
        var p = String(sqlDate).split(/[- :]/);
        if (p.length < 5) return new Date();
        return new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]), Number(p[3]), Number(p[4]), Number(p[5] || 0));
    }

    function formatEuDateFromSql(sqlDate) {
        var d = parseSqlDate(sqlDate);
        var dd = String(d.getDate()).padStart(2, '0');
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var yyyy = d.getFullYear();
        var hh = String(d.getHours()).padStart(2, '0');
        var mi = String(d.getMinutes()).padStart(2, '0');
        return dd + '.' + mm + '.' + yyyy + ' ' + hh + ':' + mi;
    }

    function avatarSrc(avatar, username) {
        if (!avatar) return '';
        if (String(avatar).indexOf('upload:') === 0) {
            var token = String(avatar).slice(7);
            return '/download_avatar.php?user=' + encodeURIComponent(username || '') + '&v=' + encodeURIComponent(token);
        }
        return '/assets/avatars/' + encodeURIComponent(avatar);
    }

    function avatarMarkup(avatar, username, size, iconClass) {
        if (avatar) {
            return '<img src="' + avatarSrc(avatar, username) + '" class="rounded-circle" width="' + size + '" height="' + size + '" alt="avatar">';
        }
        return '<i class="bi bi-person-circle ' + iconClass + '"></i>';
    }

    function refreshSearchCurrentQuery() {
        if (($friendsSearch.val() || '').trim().length > 0) {
            $friendsSearch.trigger('input');
        }
    }

    function renderFriendsList(friends) {
        var html = '';
        $.each(friends || [], function (_, f) {
            html += '<div class="list-group-item d-flex justify-content-between align-items-center" data-friend-username="' + esc(f.pk_username) + '">';
            html += '<div class="d-flex align-items-center gap-2">';
            html += avatarMarkup(f.avatar || '', f.pk_username || '', 36, 'fs-3');
            html += '<div><div class="fw-semibold">' + esc((f.firstName || '') + ' ' + (f.lastName || '')) + '</div><small class="text-muted">@' + esc(f.pk_username) + '</small></div>';
            html += '</div>';
            html += '<div class="d-flex gap-1">';
            html += '<a href="/user/view_profile.php?user=' + encodeURIComponent(f.pk_username) + '&back=' + backToFriends + '" class="btn btn-sm btn-outline-secondary" title="' + esc($friendsResults.data('view-profile-label')) + '"><i class="bi bi-person"></i></a>';
            html += '<a href="/user/chat.php?with=' + encodeURIComponent(f.pk_username) + '&back=' + backToFriends + '" class="btn btn-sm btn-outline-primary" title="' + esc($friendsResults.data('chat-label')) + '"><i class="bi bi-chat"></i></a>';
            html += '<button type="button" class="btn btn-sm btn-outline-danger js-remove-friend" data-friend="' + esc(f.pk_username) + '" title="' + esc($friendsResults.data('remove-label')) + '"><i class="bi bi-x-lg"></i></button>';
            html += '</div></div>';
        });

        $friendsList.html(html);
        $friendsCount.text((friends || []).length);
        if ((friends || []).length === 0) $friendsEmpty.removeClass('d-none');
        else $friendsEmpty.addClass('d-none');
    }

    function renderRequests(requests) {
        var incoming = [];
        var outgoing = [];
        $.each(requests || [], function (_, r) {
            if (r.direction === 'incoming') incoming.push(r);
            else outgoing.push(r);
        });

        var incomingHtml = '';
        $.each(incoming, function (_, r) {
            incomingHtml += '<div class="d-flex align-items-center gap-2 py-2 border-bottom" data-request-id="' + Number(r.pk_requestID) + '">';
            incomingHtml += '<div>' + avatarMarkup(r.avatar || '', r.pk_username || '', 36, 'fs-3') + '</div>';
            incomingHtml += '<div class="flex-grow-1"><div class="fw-semibold">' + esc((r.firstName || '') + ' ' + (r.lastName || '')) + '</div><small class="text-muted d-block">@' + esc(r.pk_username) + '</small><small class="text-muted">' + esc(formatEuDateFromSql(r.createdAt)) + '</small></div>';
            incomingHtml += '<div class="d-flex align-items-center gap-1">';
            incomingHtml += '<a href="/user/view_profile.php?user=' + encodeURIComponent(r.pk_username) + '&back=' + backToFriends + '" class="btn btn-sm btn-outline-secondary" title="' + esc($friendsResults.data('view-profile-label')) + '"><i class="bi bi-person"></i></a>';
            incomingHtml += '<button type="button" class="btn btn-sm btn-outline-success js-incoming-action" data-action="accept" data-request-id="' + Number(r.pk_requestID) + '"><i class="bi bi-check-lg"></i></button>';
            incomingHtml += '<button type="button" class="btn btn-sm btn-outline-danger js-incoming-action" data-action="reject" data-request-id="' + Number(r.pk_requestID) + '"><i class="bi bi-x-lg"></i></button>';
            incomingHtml += '</div></div>';
        });

        var outgoingHtml = '';
        $.each(outgoing, function (_, r) {
            outgoingHtml += '<div class="d-flex align-items-center gap-2 py-2 border-bottom" data-request-id="' + Number(r.pk_requestID) + '" data-outgoing-username="' + esc(r.pk_username) + '">';
            outgoingHtml += '<div>' + avatarMarkup(r.avatar || '', r.pk_username || '', 36, 'fs-3') + '</div>';
            outgoingHtml += '<div class="flex-grow-1"><div class="fw-semibold">' + esc((r.firstName || '') + ' ' + (r.lastName || '')) + '</div><small class="text-muted d-block">@' + esc(r.pk_username) + '</small><small class="text-muted">' + esc(formatEuDateFromSql(r.createdAt)) + '</small></div>';
            outgoingHtml += '<div class="d-flex align-items-center gap-1">';
            outgoingHtml += '<a href="/user/view_profile.php?user=' + encodeURIComponent(r.pk_username) + '&back=' + backToFriends + '" class="btn btn-sm btn-outline-secondary" title="' + esc($friendsResults.data('view-profile-label')) + '"><i class="bi bi-person"></i></a>';
            outgoingHtml += '<button type="button" class="btn btn-sm btn-outline-danger js-cancel-request" data-request-id="' + Number(r.pk_requestID) + '" title="' + esc($friendsResults.data('cancel-label')) + '"><i class="bi bi-x-lg"></i></button>';
            outgoingHtml += '</div></div>';
        });

        $incomingList.html(incomingHtml);
        $outgoingList.html(outgoingHtml);
        $incomingCount.text(incoming.length);
        $outgoingCount.text(outgoing.length);
        if (incoming.length === 0) $incomingEmpty.removeClass('d-none'); else $incomingEmpty.addClass('d-none');
        if (outgoing.length === 0) $outgoingEmpty.removeClass('d-none'); else $outgoingEmpty.addClass('d-none');
    }

    function buildActionHtml(user) {
        var relation = user.relation || 'none';
        if (relation === 'friends') {
            return '<span class="btn btn-sm btn-outline-success disabled friends-search-action">' + esc($friendsResults.data('friends-label')) + '</span>';
        }
        if (relation === 'pending_outgoing') {
            return '<span class="btn btn-sm btn-outline-warning disabled friends-search-action">' + esc($friendsResults.data('outgoing-request-label')) + '</span>';
        }
        if (relation === 'pending_incoming') {
            return '<span class="btn btn-sm btn-outline-info disabled friends-search-action">' + esc($friendsResults.data('incoming-request-label')) + '</span>';
        }
        return '<button type="button" class="btn btn-sm btn-primary friends-search-action js-send-friend-request" data-username="' + esc(user.pk_username) + '">' + esc($friendsResults.data('send-request-label')) + '</button>';
    }

    function renderSearchResults(users) {
        if (!users.length) {
            $friendsResults.removeClass('d-none').html('<div class="chat-search-item text-muted">' + esc($friendsResults.data('no-users-msg')) + '</div>');
            return;
        }

        var html = '';
        $.each(users, function (_, user) {
            html += '<div class="chat-search-item d-flex justify-content-between align-items-center gap-2">';
            html += '<div class="d-flex align-items-center gap-2">' + avatarMarkup(user.avatar || '', user.pk_username || '', 32, 'fs-4') + '<span>' + esc((user.firstName || '') + ' ' + (user.lastName || '')) + ' <small class="text-muted">@' + esc(user.pk_username) + '</small></span></div>';
            html += '<div class="d-flex align-items-center gap-1">';
            html += '<a href="/user/view_profile.php?user=' + encodeURIComponent(user.pk_username) + '&back=' + backToFriends + '" class="btn btn-sm btn-outline-secondary" title="' + esc($friendsResults.data('view-profile-label')) + '"><i class="bi bi-person"></i></a>';
            html += '<a href="/user/chat.php?with=' + encodeURIComponent(user.pk_username) + '&back=' + backToFriends + '" class="btn btn-sm btn-outline-primary" title="' + esc($friendsResults.data('chat-label')) + '"><i class="bi bi-chat"></i></a>';
            html += buildActionHtml(user);
            html += '</div></div>';
        });
        $friendsResults.removeClass('d-none').html(html);
    }

    function loadSearchResults(query) {
        $.get('/api/friends.php', { action: 'search_users', query: query }, function (res) {
            if (!res || !res.success) {
                $friendsResults.removeClass('d-none').html('<div class="chat-search-item text-danger">' + esc($friendsResults.data('error-msg')) + '</div>');
                return;
            }
            renderSearchResults(res.users || []);
        }, 'json').fail(function () {
            $friendsResults.removeClass('d-none').html('<div class="chat-search-item text-danger">' + esc($friendsResults.data('error-msg')) + '</div>');
        });
    }

    function refreshPanels() {
        $.when(
            $.get('/api/friends.php', { action: 'get_friends' }, null, 'json'),
            $.get('/api/friends.php', { action: 'get_requests' }, null, 'json')
        ).done(function (friendsResp, requestsResp) {
            var friendsData = friendsResp[0] || {};
            var requestsData = requestsResp[0] || {};
            if (friendsData.success) renderFriendsList(friendsData.friends || []);
            if (requestsData.success) renderRequests(requestsData.requests || []);
            refreshSearchCurrentQuery();
        });
    }

    if (referrer.indexOf('/user/view_profile.php') === -1) {
        sessionStorage.removeItem(SEARCH_STORAGE_KEY);
    }
    var savedQuery = sessionStorage.getItem(SEARCH_STORAGE_KEY) || '';
    if (savedQuery) $friendsSearch.val(savedQuery);

    $friendsSearch.on('input', function () {
        clearTimeout(friendsSearchTimer);
        var q = ($(this).val() || '').trim();
        if (q.length < 1) {
            sessionStorage.removeItem(SEARCH_STORAGE_KEY);
            $friendsResults.addClass('d-none').html('');
            return;
        }
        sessionStorage.setItem(SEARCH_STORAGE_KEY, q);
        friendsSearchTimer = setTimeout(function () { loadSearchResults(q); }, 250);
    });

    if (($friendsSearch.val() || '').trim().length > 0) $friendsSearch.trigger('input');

    $(document).on('click', '.js-send-friend-request', function () {
        var $btn = $(this);
        var receiver = String($btn.data('username') || '');
        if (!receiver) return;
        $btn.prop('disabled', true);
        $.post('/api/friends.php', { action: 'send_request', receiver: receiver }, function (res) {
            if (res && res.success) {
                refreshPanels();
            } else {
                $btn.prop('disabled', false);
                alert((res && res.message) ? res.message : $friendsResults.data('error-msg'));
            }
        }, 'json').fail(function (xhr) {
            $btn.prop('disabled', false);
            var msg = $friendsResults.data('error-msg');
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            alert(msg);
        });
    });

    $(document).on('click', '.js-incoming-action', function () {
        var $btn = $(this);
        var action = String($btn.data('action') || '');
        var requestId = Number($btn.data('request-id') || 0);
        if (!requestId || (action !== 'accept' && action !== 'reject')) return;
        $btn.prop('disabled', true);
        $.post('/api/friends.php', { action: action, request_id: requestId }, function (res) {
            if (res && res.success) {
                refreshPanels();
            } else {
                $btn.prop('disabled', false);
                alert((res && res.message) ? res.message : $friendsResults.data('error-msg'));
            }
        }, 'json').fail(function (xhr) {
            $btn.prop('disabled', false);
            var msg = $friendsResults.data('error-msg');
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            alert(msg);
        });
    });

    $(document).on('click', '.js-cancel-request', function () {
        var $btn = $(this);
        var requestId = Number($btn.data('request-id') || 0);
        if (!requestId) return;
        $btn.prop('disabled', true);
        $.post('/api/friends.php', { action: 'cancel_request', request_id: requestId }, function (res) {
            if (res && res.success) {
                refreshPanels();
            } else {
                $btn.prop('disabled', false);
                alert((res && res.message) ? res.message : $friendsResults.data('error-msg'));
            }
        }, 'json').fail(function (xhr) {
            $btn.prop('disabled', false);
            var msg = $friendsResults.data('error-msg');
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            alert(msg);
        });
    });

    $(document).on('click', '.js-remove-friend', function () {
        var $btn = $(this);
        var friend = String($btn.data('friend') || '');
        if (!friend) return;
        var confirmMsg = $friendsResults.data('remove-confirm-msg') || 'Remove friend?';
        if (!window.confirm(confirmMsg)) return;

        $btn.prop('disabled', true);
        $.post('/api/friends.php', { action: 'remove', friend: friend }, function (res) {
            if (res && res.success) {
                refreshPanels();
            } else {
                $btn.prop('disabled', false);
                alert((res && res.message) ? res.message : $friendsResults.data('error-msg'));
            }
        }, 'json').fail(function (xhr) {
            $btn.prop('disabled', false);
            var msg = $friendsResults.data('error-msg');
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            alert(msg);
        });
    });

    refreshPanels();
    setInterval(refreshPanels, 3000);
});
