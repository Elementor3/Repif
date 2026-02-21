<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = 'Dashboard';
require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
        <p class="lead">You are logged in as <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
        
        <?php if (isAdmin()): ?>
            <div class="alert alert-info">
                <strong>Administrator Access:</strong> You have full system access. Visit the <a href="/admin/panel.php">Admin Panel</a> to manage all users and stations.
            </div>
        <?php endif; ?>
        
        <div class="row mt-4" id="stats-cards">
            <!-- Stats will be loaded via AJAX -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">My Stations</h5>
                        <p class="card-text" id="stations-count">Loading...</p>
                        <a href="stations.php" class="btn btn-primary">View Stations</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Collections</h5>
                        <p class="card-text" id="collections-count">Loading...</p>
                        <a href="collections.php" class="btn btn-primary">View Collections</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Friends</h5>
                        <p class="card-text" id="friends-count">Loading...</p>
                        <a href="friends.php" class="btn btn-secondary">Manage Friends</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Shared Collections</h5>
                        <p class="card-text">View collections shared with you by friends.</p>
                        <a href="shared.php" class="btn btn-secondary">View Shared</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Measurements</h5>
                        <p class="card-text">View and filter measurement data from your stations.</p>
                        <a href="measurements.php" class="btn btn-secondary">View Data</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Profile</h5>
                        <p class="card-text">Update your account information.</p>
                        <a href="profile.php" class="btn btn-secondary">Edit Profile</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    loadStats();
});

function loadStats() {
    $.get('/api/dashboard.php?action=get_stats', function(response) {
        var parts = response.split('|');
        if (parts.length === 3) {
            $('#stations-count').text(parts[0] + ' registered stations');
            $('#collections-count').text(parts[1] + ' collections');
            $('#friends-count').text(parts[2] + ' friends');
        } else {
            $('#stations-count').text('Error loading');
            $('#collections-count').text('Error loading');
            $('#friends-count').text('Error loading');
        }
    }).fail(function() {
        $('#stations-count').text('Error loading');
        $('#collections-count').text('Error loading');
        $('#friends-count').text('Error loading');
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>