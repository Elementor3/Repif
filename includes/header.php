<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/../services/notifications.php';
require_once __DIR__ . '/../services/chat.php';

$theme = $_SESSION['theme'] ?? 'light';
$locale = $_SESSION['locale'] ?? 'en';
$unreadCount = 0;
$chatUnreadCount = 0;
$currentUserDisplayName = '';
$currentUserAvatarUrl = null;
if (isLoggedIn()) {
    $currentUsername = (string)($_SESSION['username'] ?? '');
    $unreadCount = getUnreadCount($conn, $currentUsername);
    $chatUnreadCount = getTotalUnreadChatCount($conn, $currentUsername);

    $profileStmt = $conn->prepare("SELECT firstName, lastName, avatar FROM user WHERE pk_username = ? LIMIT 1");
    $profileStmt->bind_param("s", $currentUsername);
    $profileStmt->execute();
    $profileRow = $profileStmt->get_result()->fetch_assoc();

    $avatarValue = (string)($_SESSION['avatar'] ?? '');
    if ($profileRow) {
        $fullName = trim((string)($profileRow['firstName'] ?? '') . ' ' . (string)($profileRow['lastName'] ?? ''));
        $currentUserDisplayName = $fullName !== '' ? $fullName : $currentUsername;
        $avatarValue = (string)($profileRow['avatar'] ?? '');
        $_SESSION['full_name'] = $currentUserDisplayName;
        $_SESSION['avatar'] = $avatarValue;
    } else {
        $currentUserDisplayName = (string)($_SESSION['full_name'] ?? $currentUsername);
    }

    $currentUserAvatarUrl = getAvatarUrl($avatarValue, $currentUsername);
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="<?= e($locale) ?>" data-bs-theme="<?= e($theme) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeatherStation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <?php if ($currentPage === 'profile.php'): ?>
    <link rel="stylesheet" href="/assets/css/profile.css">
    <?php endif; ?>
    <?php if ($currentPage === 'friends.php'): ?>
    <link rel="stylesheet" href="/assets/css/friends.css">
    <?php endif; ?>
    <?php if ($currentPage === 'stations.php' || $currentPage === 'collections.php'): ?>
    <link rel="stylesheet" href="/assets/css/stations.css">
    <?php endif; ?>
    <?php if ($currentPage === 'collections.php'): ?>
    <link rel="stylesheet" href="/assets/css/collections.css">
    <?php endif; ?>
    <?php if ($currentPage === 'measurements.php'): ?>
    <link rel="stylesheet" href="/assets/css/measurements.css">
    <?php endif; ?>
    <?php if ($currentPage === 'collections.php' || $currentPage === 'measurements.php'): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery-datetimepicker@2.5.21/build/jquery.datetimepicker.min.css">
    <?php endif; ?>
    <?php if ($currentPage === 'chat.php'): ?>
    <link rel="stylesheet" href="/assets/css/chat.css">
    <?php endif; ?>
</head>

<body>
    <nav class="navbar navbar-expand-lg fixed-top navbar-themed">
        <div class="container-fluid px-3 px-lg-4">
            <?php if (isLoggedIn()): ?>
                <a class="navbar-brand fw-semibold d-flex align-items-center text-nowrap me-1 navbar-identity" href="/user/profile.php" id="navbarIdentityLink">
                    <?php if (!empty($currentUserAvatarUrl)): ?>
                        <img
                            src="<?= e($currentUserAvatarUrl) ?>"
                            class="rounded-circle me-1 navbar-identity-avatar"
                            width="32"
                            height="32"
                            alt="avatar"
                            id="navbarIdentityAvatarImg"
                            onerror="this.classList.add('d-none'); var f = document.getElementById('navbarIdentityAvatarFallback'); if (f) f.classList.remove('d-none');">
                        <i class="bi bi-person-circle fs-4 me-1 navbar-identity-avatar-fallback d-none" id="navbarIdentityAvatarFallback"></i>
                    <?php else: ?>
                        <i class="bi bi-person-circle fs-4 me-1 navbar-identity-avatar-fallback" id="navbarIdentityAvatarFallback"></i>
                    <?php endif; ?>
                    <span class="text-truncate navbar-identity-name" id="navbarIdentityName"><?= e($currentUserDisplayName) ?></span>
                </a>
                <div class="collapse navbar-collapse justify-content-center" id="navbarMain">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="/user/dashboard.php">
                                <i class="bi bi-speedometer2"></i> <?= t('dashboard') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'stations.php' ? 'active' : '' ?>" href="/user/stations.php">
                                <i class="bi bi-broadcast-pin"></i> <?= t('stations') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'measurements.php' ? 'active' : '' ?>" href="/user/measurements.php">
                                <i class="bi bi-graph-up"></i> <?= t('measurements') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'collections.php' ? 'active' : '' ?>" href="/user/collections.php">
                                <i class="bi bi-collection"></i> <?= t('collections') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'friends.php' ? 'active' : '' ?>" href="/user/friends.php">
                                <i class="bi bi-people"></i> <?= t('friends') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'chat.php' ? 'active' : '' ?>" href="/user/chat.php">
                                <i class="bi bi-chat-dots"></i> <?= t('chat') ?>
                                <span class="badge bg-danger rounded-pill ms-1 chat-total-badge <?= $chatUnreadCount > 0 ? '' : 'd-none' ?>" id="chatUnreadBadge">
                                    <?= (int)$chatUnreadCount ?>
                                </span>
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link <?= $currentPage === 'panel.php' ? 'active' : '' ?>" href="/admin/panel.php">
                                    <i class="bi bi-shield-lock"></i> <?= t('admin_panel') ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="/auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> <?= t('logout') ?>
                            </a>
                        </li>
                    </ul>
                </div>
                <ul class="navbar-nav align-items-center flex-row gap-2 ms-auto ms-lg-2 text-nowrap">
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative" href="#" id="notifDropdown" data-bs-toggle="dropdown">
                            <i class="bi bi-bell fs-5"></i>
                            <span class="badge bg-danger rounded-pill notif-badge position-absolute top-0 start-100 translate-middle <?= $unreadCount === 0 ? 'd-none' : '' ?>" id="notifBadge">
                                <?= $unreadCount ?>
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end notif-dropdown p-0" id="notifDropdownMenu" style="min-width:320px;max-width:360px;">
                            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                                <strong><?= t('notifications') ?></strong>
                                <a href="#" class="small text-muted" id="markAllReadBtn"><?= t('mark_all_read') ?></a>
                                <a href="#" class="small text-danger" id="clearNotifBtn"><?= t('clear_all') ?></a>
                            </div>
                            <div id="notifList"
                                data-empty-msg="<?= e(t('no_notifications')) ?>"
                                data-load-error-msg="<?= e(t('failed_to_load_notification')) ?>"
                                data-clear-error-msg="<?= e(t('failed_to_clear_notifications')) ?>"
                                data-delete-error-msg="<?= e(t('failed_to_delete_notification')) ?>"
                                style="max-height:300px;overflow-y:auto;">
                                <div class="text-center text-muted py-3"><?= t('no_notifications') ?></div>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item">
                        <form method="post" action="/api/profile.php" class="d-inline" id="localeSwitcherForm">
                            <input type="hidden" name="action" value="set_locale">
                            <select name="locale" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;">
                                <option value="en" <?= $locale === 'en' ? 'selected' : '' ?>>EN</option>
                                <option value="fr" <?= $locale === 'fr' ? 'selected' : '' ?>>FR</option>
                                <option value="uk" <?= $locale === 'uk' ? 'selected' : '' ?>>UK</option>
                            </select>
                        </form>
                    </li>
                    <li class="nav-item">
                        <button class="btn btn-link nav-link" id="themeToggleBtn" title="Toggle theme">
                            <i class="bi <?= $theme === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill' ?>" id="themeIcon"></i>
                        </button>
                    </li>
                </ul>
                <button class="navbar-toggler ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                    <span class="navbar-toggler-icon"></span>
                </button>
            <?php else: ?>
                <a class="navbar-brand fw-bold" href="/user/dashboard.php">
                    <i class="bi bi-cloud-sun-fill text-primary"></i> WeatherStation
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="/auth/login.php"><?= t('login') ?></a></li>
                        <li class="nav-item"><a class="nav-link" href="/auth/register.php"><?= t('register') ?></a></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    <div class="container mt-2 pt-0">
        <div class="modal fade" id="notifDetailModal" tabindex="-1" aria-labelledby="notifDetailModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="notifDetailModalLabel"><?= t('notification_details') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <h6 id="notifModalTitle" class="mb-2"></h6>
                        <div id="notifModalMessage" class="text-muted"></div>
                        <div id="notifModalTime" class="small text-secondary mt-3"></div>
                    </div>
                </div>
            </div>
        </div>