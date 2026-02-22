$(function () {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function () {
        $('.auto-dismiss').fadeOut('slow', function () { $(this).remove(); });
    }, 5000);

    // Theme toggle
    $('#themeToggleBtn').on('click', function () {
        var current = $('html').attr('data-bs-theme');
        var next = current === 'dark' ? 'light' : 'dark';
        $.post('/api/profile.php', { action: 'set_theme', theme: next }, function (res) {
            if (res.success) {
                location.reload();
            }
        }, 'json').fail(function () {
            location.reload();
        });
    });

    // Notification poll
    function loadNotifications() {
        $.get('/api/notifications.php', { action: 'get_count' }, function (res) {
            if (res.success) {
                var count = res.count;
                if (count > 0) {
                    $('#notifBadge').text(count).removeClass('d-none');
                } else {
                    $('#notifBadge').addClass('d-none');
                }
            }
        }, 'json').fail(function () {});
    }

    function loadNotificationList() {
        $.get('/api/notifications.php', { action: 'get_all' }, function (res) {
            if (res.success && res.notifications.length > 0) {
                var html = '';
                $.each(res.notifications, function (i, n) {
                    html += '<div class="notif-item ' + (n.is_read == 0 ? 'unread' : '') + '" data-id="' + n.pk_notificationID + '">';
                    html += '<div class="notif-title">' + $('<div>').text(n.title).html() + '</div>';
                    html += '<div class="notif-msg">' + $('<div>').text(n.message).html() + '</div>';
                    html += '<div class="notif-time">' + n.createdAt + '</div>';
                    html += '</div>';
                });
                $('#notifList').html(html);
            } else {
                var emptyMsg = $('#notifList').data('empty-msg') || 'No notifications';
                $('#notifList').html('<div class="text-center text-muted py-3"></div>').find('div').text(emptyMsg);
            }
        }, 'json').fail(function () {});
    }

    if ($('#notifDropdown').length) {
        loadNotifications();
        setInterval(loadNotifications, 30000);

        $('#notifDropdown').on('click', function () {
            loadNotificationList();
        });

        $(document).on('click', '.notif-item', function () {
            var id = $(this).data('id');
            $.post('/api/notifications.php', { action: 'mark_read', notificationId: id }, function () {
                loadNotifications();
            }, 'json');
            $(this).removeClass('unread');
        });

        $('#markAllReadBtn').on('click', function (e) {
            e.preventDefault();
            $.post('/api/notifications.php', { action: 'mark_all_read' }, function (res) {
                if (res.success) {
                    loadNotifications();
                    loadNotificationList();
                }
            }, 'json');
        });
    }

    // Global AJAX error handler
    $(document).ajaxError(function (event, xhr, settings, error) {
        if (xhr.status === 401 || xhr.status === 403) {
            window.location.href = '/auth/login.php';
        }
    });
});
