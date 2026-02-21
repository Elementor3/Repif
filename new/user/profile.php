
<?php
// /user/profile.php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../services/users.php';

requireLogin(); // user must be logged in

$username = $_SESSION['username'] ?? null;
if (!$username) {
    header("Location: /auth/login.php");
    exit();
}

// Fetch current user data
try {
    $currentUser = svc_getUserByUsername($conn, $username);
    if (!$currentUser) {
        throw new RuntimeException("User account not found.");
    }
} catch (Throwable $e) {
    $currentUser = null;
    $loadError = $e->getMessage();
}

$pageTitle = "My Profile";
require_once '../includes/header.php';
?>

<div class="container mt-4">

    <h1 class="h3 mb-4">My Profile</h1>

    <!-- Error from loading user -->
    <?php if (!empty($loadError ?? '')): ?>
        <div class="alert alert-danger">
            <?= e($loadError) ?>
        </div>
    <?php endif; ?>

    <!-- Error messages from API -->
    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= e($_GET['error']) ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Profile updated -->
    <?php if (!empty($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Profile updated successfully.
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Password changed -->
    <?php if (!empty($_GET['pwd_changed'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Password changed successfully.
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($currentUser): ?>

    <div class="row">

        <!-- PROFILE INFO -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Profile Information</h5>
                </div>
                <div class="card-body">

                    <form action="/api/users.php" method="POST">
                        <input type="hidden" name="action" value="user.profile.update">

                        <!-- Username -->
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text"
                                   class="form-control"
                                   value="<?= e($currentUser['pk_username']) ?>"
                                   disabled>
                        </div>

                        <!-- First name -->
                        <div class="mb-3">
                            <label class="form-label">First name</label>
                            <input type="text"
                                   class="form-control"
                                   name="firstName"
                                   maxlength="100"
                                   required
                                   value="<?= e($currentUser['firstName']) ?>">
                        </div>

                        <!-- Last name -->
                        <div class="mb-3">
                            <label class="form-label">Last name</label>
                            <input type="text"
                                   class="form-control"
                                   name="lastName"
                                   maxlength="100"
                                   required
                                   value="<?= e($currentUser['lastName']) ?>">
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email"
                                   class="form-control"
                                   name="email"
                                   maxlength="100"
                                   required
                                   value="<?= e($currentUser['email']) ?>">
                        </div>

                        <button class="btn btn-primary" type="submit">Save changes</button>
                    </form>

                </div>
            </div>
        </div>


        <!-- CHANGE PASSWORD -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Change Password</h5>
                </div>
                <div class="card-body">

                    <form action="/api/users.php" method="POST">
                        <input type="hidden" name="action" value="user.password.change">

                        <!-- New password -->
                        <div class="mb-3">
                            <label class="form-label">New password</label>
                            <input type="password"
                                   class="form-control"
                                   name="new_password"
                                   required
                                   autocomplete="new-password">
                        </div>

                        <!-- Confirm password -->
                        <div class="mb-3">
                            <label class="form-label">Confirm new password</label>
                            <input type="password"
                                   class="form-control"
                                   name="new_password_confirm"
                                   required
                                   autocomplete="new-password">
                        </div>

                        <button class="btn btn-primary" type="submit">Change password</button>
                    </form>

                </div>
            </div>
        </div>

    </div><!-- /.row -->

    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
