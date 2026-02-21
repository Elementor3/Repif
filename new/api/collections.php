
<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../services/collections.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

try {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'collection.create':
            svc_userCreateCollection(
                $conn,
                $_SESSION['username'],
                trim($_POST['collection_name']),
                trim($_POST['station']),
                trim($_POST['start_date']),
                trim($_POST['end_date']),
                trim($_POST['description']),
                false
            );
            header('Location: /user/collections.php?created=1');
            exit;

        case 'collection.edit':
            svc_userEditCollection(
                $conn,
                $_SESSION['username'],
                (int)$_POST['collection_id'],
                trim($_POST['new_name']),
                trim($_POST['new_description'])
            );
            header('Location: /user/collections.php?updated=1');
            exit;

        case 'collection.delete':
            svc_userDeleteCollection(
                $conn,
                $_SESSION['username'],
                (int)$_POST['collection_id']
            );
            header('Location: /user/collections.php?deleted=1');
            exit;

        case 'collection.share':
            svc_userShareCollection(
                $conn,
                $_SESSION['username'],
                (int)$_POST['collection_id'],
                trim($_POST['friend'])
            );
            header('Location: /user/collections.php?shared=1');
            exit;

        case 'collection.unshare':
            svc_userUnshareCollection(
                $conn,
                $_SESSION['username'],
                (int)$_POST['collection_id'],
                trim($_POST['friend'])
            );
            header('Location: /user/collections.php?unshared=1');
            exit;

        case 'collection.unshare_self':
            $id = (int)$_POST['collection_id'];
            $stmt = mysqli_prepare($conn, "DELETE FROM shares WHERE pk_collection=? AND pk_user=?");
            mysqli_stmt_bind_param($stmt, "is", $id, $_SESSION['username']);
            mysqli_stmt_execute($stmt);
            echo 'OK|Collection removed';
            header('Location: /user/collections.php?unshared=1');
            exit;

        default:
            throw new RuntimeException('Unknown action');
    }

} catch (Throwable $e) {
    header("Location: /user/collections.php?error=" . urlencode($e->getMessage()));
    exit;
}
