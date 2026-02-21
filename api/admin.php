<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/users.php';
require_once __DIR__ . '/../services/stations.php';
require_once __DIR__ . '/../services/admin_posts.php';
require_once __DIR__ . '/../services/notifications.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    // --- USERS ---
    case 'create_user':
        $username  = trim($_POST['username'] ?? '');
        $firstName = trim($_POST['firstName'] ?? '');
        $lastName  = trim($_POST['lastName'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        $role      = in_array($_POST['role'] ?? '', ['User', 'Admin']) ? $_POST['role'] : 'User';
        if (!$username || !$firstName || !$lastName || !$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'Missing fields']); exit;
        }
        if (getUserByUsername($conn, $username)) {
            echo json_encode(['success' => false, 'message' => 'Username taken']); exit;
        }
        if (getUserByEmail($conn, $email)) {
            echo json_encode(['success' => false, 'message' => 'Email taken']); exit;
        }
        $ok = adminCreateUser($conn, $username, $firstName, $lastName, $email, $password, $role);
        echo json_encode(['success' => $ok]);
        break;

    case 'update_user':
        $username  = trim($_POST['username'] ?? '');
        $firstName = trim($_POST['firstName'] ?? '');
        $lastName  = trim($_POST['lastName'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $role      = in_array($_POST['role'] ?? '', ['User', 'Admin']) ? $_POST['role'] : 'User';
        $newPass   = $_POST['newPassword'] ?? null;
        if (!$username || !$firstName || !$lastName || !$email) {
            echo json_encode(['success' => false, 'message' => 'Missing fields']); exit;
        }
        // Cannot demote last admin
        if ($role !== 'Admin') {
            $result = $conn->query("SELECT COUNT(*) AS cnt FROM user WHERE role='Admin'");
            $adminCount = (int)$result->fetch_assoc()['cnt'];
            $self = getUserByUsername($conn, $username);
            if ($self && $self['role'] === 'Admin' && $adminCount <= 1) {
                echo json_encode(['success' => false, 'message' => 'Cannot demote the last admin']); exit;
            }
        }
        $ok = adminUpdateUser($conn, $username, $firstName, $lastName, $email, $role, $newPass ?: null);
        echo json_encode(['success' => $ok]);
        break;

    case 'delete_user':
        $username = trim($_POST['username'] ?? '');
        if ($username === ($_SESSION['username'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete yourself']); exit;
        }
        $ok = adminDeleteUser($conn, $username);
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Deleted' : 'Cannot delete last admin']);
        break;

    case 'get_user':
        $username = trim($_GET['username'] ?? '');
        $user = getUserByUsername($conn, $username);
        if (!$user) { echo json_encode(['success' => false]); exit; }
        unset($user['password_hash']);
        echo json_encode(['success' => true, 'user' => $user]);
        break;

    // --- STATIONS ---
    case 'create_station':
        $serial      = trim($_POST['serial'] ?? '');
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $createdBy   = trim($_POST['createdBy'] ?? $_SESSION['username']);
        if (!$serial || !$name) {
            echo json_encode(['success' => false, 'message' => 'Missing fields']); exit;
        }
        $ok = adminCreateStation($conn, $serial, $name, $description, $createdBy);
        echo json_encode(['success' => $ok]);
        break;

    case 'update_station':
        $serial      = trim($_POST['serial'] ?? '');
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $regBy       = trim($_POST['registeredBy'] ?? '') ?: null;
        if (!$serial || !$name) {
            echo json_encode(['success' => false, 'message' => 'Missing fields']); exit;
        }
        $ok = adminUpdateStation($conn, $serial, $name, $description, $regBy);
        echo json_encode(['success' => $ok]);
        break;

    case 'delete_station':
        $serial = trim($_POST['serial'] ?? '');
        $ok = adminDeleteStation($conn, $serial);
        echo json_encode(['success' => $ok]);
        break;

    // --- ADMIN POSTS ---
    case 'create_post':
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if (!$title || !$content) {
            echo json_encode(['success' => false, 'message' => 'Missing fields']); exit;
        }
        $postId = createPost($conn, $_SESSION['username'], $title, $content);
        if (!$postId) { echo json_encode(['success' => false]); exit; }

        // Notify all users
        $users = $conn->query("SELECT pk_username, email FROM user");
        while ($u = $users->fetch_assoc()) {
            createNotification($conn, $u['pk_username'], 'admin_post', $title, substr(strip_tags($content), 0, 200), '/user/dashboard.php');
            sendEmail($u['email'], $title, '<h2>' . htmlspecialchars($title) . '</h2><p>' . nl2br(htmlspecialchars($content)) . '</p>');
        }
        echo json_encode(['success' => true, 'postId' => $postId]);
        break;

    case 'update_post':
        $id      = (int)($_POST['id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if (!$id || !$title || !$content) {
            echo json_encode(['success' => false, 'message' => 'Missing fields']); exit;
        }
        $ok = updatePost($conn, $id, $title, $content);
        echo json_encode(['success' => $ok]);
        break;

    case 'delete_post':
        $id = (int)($_POST['id'] ?? 0);
        $ok = deletePost($conn, $id);
        echo json_encode(['success' => $ok]);
        break;

    case 'get_post':
        $id   = (int)($_GET['id'] ?? 0);
        $post = getPostById($conn, $id);
        echo json_encode(['success' => (bool)$post, 'post' => $post]);
        break;

    // --- COLLECTIONS (admin view) ---
    case 'delete_collection':
        require_once __DIR__ . '/../services/collections.php';
        $id = (int)($_POST['id'] ?? 0);
        $ok = deleteCollection($conn, $id);
        echo json_encode(['success' => $ok]);
        break;

    // --- MEASUREMENTS (admin) ---
    case 'delete_measurement':
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM measurement WHERE pk_measurementID=?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        echo json_encode(['success' => $ok]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
?>
