<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/../services/notifications.php';

$theme = $_SESSION['theme'] ?? 'light';
$locale = $_SESSION['locale'] ?? 'en';
$unreadCount = 0;
if (isLoggedIn()) {
    $unreadCount = getUnreadCount($conn, $_SESSION['username']);
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
</head>

<body>
    <nav class="navbar navbar-expand-lg fixed-top navbar-themed">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/user/dashboard.php">
                <i class="bi bi-cloud-sun-fill text-primary"></i> WeatherStation
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <?php if (isLoggedIn()): ?>
                    <ul class="navbar-nav me-auto">
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
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link <?= $currentPage === 'panel.php' ? 'active' : '' ?>" href="/admin/panel.php">
                                    <i class="bi bi-shield-lock"></i> <?= t('admin_panel') ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <ul class="navbar-nav align-items-center gap-2">
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
                                    <a href="#" class="small text-danger" id="clearNotifBtn"><?= t('clear') ?></a>
                                </div>
                                <div id="notifList"
                                    data-empty-msg="<?= e(t('no_notifications')) ?>"
                                    data-load-error-msg="<?= e(t('failed_to_load_notification')) ?>"
                                    data-clear-error-msg="<?= e(t('failed_to_clear_notifications')) ?>"
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
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <?php if (!empty($_SESSION['avatar'])): ?>
                                    <img src="/assets/avatars/presets/<?= e($_SESSION['avatar']) ?>" class="rounded-circle me-1" width="24" height="24" alt="avatar">
                                <?php else: ?>
                                    <i class="bi bi-person-circle"></i>
                                <?php endif; ?>
                                <?= e($_SESSION['full_name'] ?? $_SESSION['username']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="/user/profile.php"><i class="bi bi-person me-2"></i><?= t('profile') ?></a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item text-danger" href="/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i><?= t('logout') ?></a></li>
                            </ul>
                        </li>
                    </ul>
                <?php else: ?>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="/auth/login.php"><?= t('login') ?></a></li>
                        <li class="nav-item"><a class="nav-link" href="/auth/register.php"><?= t('register') ?></a></li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <div class="container mt-5 pt-3">
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