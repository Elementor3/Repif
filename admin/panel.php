

<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$pageTitle = 'Admin Panel';
require_once '../includes/header.php';

// Какую вкладку показывать по умолчанию
$activeTab = $_GET['tab'] ?? 'users';
$allowedTabs = ['users','stations','measurements','collections'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'users';
}
?>
<div class="row">
  <div class="col-12">
    <h2>Admin Panel</h2>
    <p class="text-muted">Manage users, stations, measurements, and collections</p>

    <?php
    if (isset($_GET['error']))   echo showError($_GET['error']);
    if (isset($_GET['created'])) echo showSuccess('Created successfully!');
    if (isset($_GET['updated'])) echo showSuccess('Updated successfully!');
    if (isset($_GET['deleted'])) echo showSuccess('Deleted successfully!');
    ?>

    <!-- Навигация вкладок -->
    <ul class="nav nav-tabs admin-tabs" role="tablist">
      <li class="nav-item">
        <a class="nav-link <?= $activeTab==='users'?'active':'' ?>" href="?tab=users">Users</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab==='stations'?'active':'' ?>" href="?tab=stations">Stations</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab==='measurements'?'active':'' ?>" href="?tab=measurements">Measurements</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab==='collections'?'active':'' ?>" href="?tab=collections">Collections</a>
      </li>
    </ul>

    <div class="mt-3">
      <?php
        switch ($activeTab) {
          case 'users':        include __DIR__.'/tabs/tab_users.php'; break;
          case 'stations':     include __DIR__.'/tabs/tab_stations.php'; break;
          case 'measurements': include __DIR__.'/tabs/tab_measurements.php'; break;
          case 'collections':  include __DIR__.'/tabs/tab_collections.php'; break;
        }
      ?>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>

<?php
