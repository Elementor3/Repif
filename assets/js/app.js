$(function () {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function () {
        $('.auto-dismiss').fadeOut('slow', function () { $(this).remove(); });
    }, 5000);

    // Theme toggle
    $('#themeToggleBtn').on('click', function () {
        var current = $('html').attr('data-bs-theme');
        var next = current === 'dark' ? 'light' : 'dark';
        var $themeIcon = $('#themeIcon');

        // Apply theme instantly in UI, then persist preference on backend.
        $('html').attr('data-bs-theme', next);
        var iconClass = next === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill';
        if ($themeIcon.length) {
            $themeIcon.removeClass('bi-sun-fill bi-moon-fill').addClass(iconClass);
        }

        $.post('/api/profile.php', { action: 'set_theme', theme: next }, function (res) {
            if (!res || !res.success) {
                $('html').attr('data-bs-theme', current);
                var oldIconClass = current === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill';
                if ($themeIcon.length) {
                    $themeIcon.removeClass('bi-sun-fill bi-moon-fill').addClass(oldIconClass);
                }
            }
        }, 'json').fail(function () {
            $('html').attr('data-bs-theme', current);
            var oldIconClass = current === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill';
            if ($themeIcon.length) {
                $themeIcon.removeClass('bi-sun-fill bi-moon-fill').addClass(oldIconClass);
            }
        });
    });

    function truncateText(text, maxLen) {
        text = (text || '').toString().trim();
        if (text.length <= maxLen) return text;
        return text.slice(0, maxLen).trimEnd() + '...';
    }

    function toSingleLine(text) {
        return (text || '')
            .toString()
            .replace(/\r?\n+/g, ' ')
            .replace(/\s{2,}/g, ' ')
            .trim();
    }

    // Notification poll
    function loadNotifications() {
        $.get('/api/notifications.php', { action: 'get_count' }, function (res) {
            if (res.success) {
                var count = res.count;
                if (count > 0) {
                    $('#notifBadge').text(count).removeClass('d-none');
                } else {
                    $('#notifBadge').text('0').addClass('d-none');
                }
            }
        }, 'json').fail(function () { });
    }

    function renderEmptyNotifications() {
        var emptyMsg = $('#notifList').data('empty-msg') || 'No notifications';
        $('#notifList').html('<div class="text-center text-muted py-3"></div>').find('div').text(emptyMsg);
    }

    function loadChatUnreadBadge() {
        if (!$('#chatUnreadBadge').length) return;

        $.get('/api/chat.php', { action: 'get_unread_counts' }, function (res) {
            if (!res || !res.success) return;

            var total = parseInt(res.total || 0, 10);
            if (total > 0) {
                $('#chatUnreadBadge').text(total).removeClass('d-none');
            } else {
                $('#chatUnreadBadge').text('0').addClass('d-none');
            }
        }, 'json').fail(function () { });
    }

    function loadNotificationList() {
        $.get('/api/notifications.php', { action: 'get_all' }, function (res) {
            if (res.success && res.notifications && res.notifications.length > 0) {
                var html = '';
                $.each(res.notifications, function (i, n) {
                    var titlePreview = truncateText(toSingleLine(n.title), 60);
                    var preview = truncateText(toSingleLine(n.message), 100);
                    html += '<div class="notif-item ' + (n.is_read == 0 ? 'unread' : '') + '" data-id="' + n.pk_notificationID + '">';
                    html += '<button type="button" class="notif-delete-btn" data-id="' + n.pk_notificationID + '" aria-label="Delete">&times;</button>';
                    html += '<div class="notif-title">' + $('<div>').text(titlePreview).html() + '</div>';
                    html += '<div class="notif-msg">' + $('<div>').text(preview).html() + '</div>';
                    html += '<div class="notif-time">' + n.createdAt + '</div>';
                    html += '</div>';
                });
                $('#notifList').html(html);
            } else {
                renderEmptyNotifications();
            }
        }, 'json').fail(function () { });
    }

    function openNotificationModal(n) {
        $('#notifModalTitle').text(n.title || '');
        $('#notifModalMessage').text(n.message || '');
        $('#notifModalTime').text(n.createdAt || '');

        var modalEl = document.getElementById('notifDetailModal');
        if (modalEl) {
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }

    if ($('#notifDropdown').length) {
        loadNotifications();
        setInterval(loadNotifications, 30000);

        $('#notifDropdown').on('click', function () {
            loadNotificationList();
        });

        // Клик по уведомлению: mark read + get full + modal
        $(document).on('click', '.notif-item', function () {
            var $item = $(this);
            var id = $item.data('id');

            $item.removeClass('unread');

            $.post('/api/notifications.php', { action: 'mark_read', notificationId: id }, function () {
                loadNotifications();
            }, 'json');

            $.get('/api/notifications.php', { action: 'get_one', id: id }, function (res) {
                if (res.success && res.notification) {
                    openNotificationModal(res.notification);
                } else {
                    alert($('#notifList').data('load-error-msg') || 'Failed to load notification');
                }
            }, 'json').fail(function () {
                alert($('#notifList').data('load-error-msg') || 'Failed to load notification');
            });
        });

        // Mark all read: только read, без удаления
        $('#markAllReadBtn').on('click', function (e) {
            e.preventDefault();
            $.post('/api/notifications.php', { action: 'mark_all_read' }, function (res) {
                if (res.success) {
                    loadNotifications();
                    loadNotificationList();
                }
            }, 'json');
        });

        // Clear: удаляет все уведомления текущего пользователя
        $('#clearNotifBtn').on('click', function (e) {
            e.preventDefault();
            $.post('/api/notifications.php', { action: 'clear_all' }, function (res) {
                if (res.success) {
                    renderEmptyNotifications();
                    $('#notifBadge').text('0').addClass('d-none');
                } else {
                    alert($('#notifList').data('clear-error-msg') || 'Failed to clear notifications');
                }
            }, 'json').fail(function () {
                alert($('#notifList').data('clear-error-msg') || 'Failed to clear notifications');
            });
        });

        // Delete one notification from list
        $(document).on('click', '.notif-delete-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var id = $(this).data('id');
            if (!id) return;

            $.post('/api/notifications.php', { action: 'delete_one', notificationId: id }, function (res) {
                if (res.success) {
                    loadNotifications();
                    loadNotificationList();
                } else {
                    alert($('#notifList').data('delete-error-msg') || 'Failed to delete notification');
                }
            }, 'json').fail(function () {
                alert($('#notifList').data('delete-error-msg') || 'Failed to delete notification');
            });
        });
    }

    if ($('#chatUnreadBadge').length) {
        loadChatUnreadBadge();
        setInterval(loadChatUnreadBadge, 3000);
    }

    // Global AJAX error handler
    $(document).ajaxError(function (event, xhr, settings, error) {
        if (xhr.status === 401 || xhr.status === 403) {
            window.location.href = '/auth/login.php';
        }
    });
});
