<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: /user/dashboard.php");
    exit();
}

$error = '';
$success = '';

// Process registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $firstName = trim($_POST['firstname'] ?? '');
    $lastName = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username) || empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        // Check if username already exists
        $stmt = mysqli_prepare($conn, "SELECT pk_username FROM user WHERE pk_username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $error = 'Username already exists.';
        } else {
            // Check if email already exists
            $stmt = mysqli_prepare($conn, "SELECT pk_username FROM user WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                $error = 'Email already registered.';
            } else {
                // Create new user
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $createdAt = date('Y-m-d H:i:s');
                
                $stmt = mysqli_prepare($conn, 
                    "INSERT INTO user (pk_username, firstName, lastName, email, password_hash, role, createdAt) 
                        VALUES (?, ?, ?, ?, ?, 'User', ?)"
                );
                mysqli_stmt_bind_param($stmt, "ssssss", $username, $firstName, $lastName, $email, $passwordHash, $createdAt);

                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Registration successful! You can now login.';
                    // Auto-redirect after 2 seconds
                    header("refresh:2;url=login.php");
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}

$pageTitle = 'Register';
require_once '../includes/header.php';
?>

<div class="row justify-content-center mt-6">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Register New Account</h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <?php echo showError($error); ?>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <?php echo showSuccess($success); ?>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo e($_POST['username'] ?? ''); ?>" required autofocus>
                        <small class="text-muted">This will be your login name</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="firstname" class="form-label">First Name *</label>
                        <input type="text" class="form-control" id="firstname" name="firstname" 
                               value="<?php echo e($_POST['firstname'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="lastname" class="form-label">Last Name *</label>
                        <input type="text" class="form-control" id="lastname" name="lastname" 
                               value="<?php echo e($_POST['lastname'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo e($_POST['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Register</button>
                    </div>
                </form>
                
                <div class="mt-3 text-center">
                    <p class="mb-0">Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>