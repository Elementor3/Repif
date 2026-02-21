
<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../services/collections.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

try {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'admin.collection.create':
            svc_adminCreateCollection(
                $conn,
                $_SESSION['username'],
                trim($_POST['collection_name']),
                trim($_POST['station']),
                trim($_POST['start_date']),
                trim($_POST['end_date']),
                trim($_POST['description'])
            );
            header('Location: /admin/panel.php?tab=collections&created=1');
            exit;

        case 'admin.collection.edit':
            svc_adminEditCollection(
                $conn,
                (int)$_POST['collection_id'],
                trim($_POST['new_name']),
                trim($_POST['new_description'])
            );
            header('Location: /admin/panel.php?tab=collections&updated=1');
            exit;

        case 'admin.collection.delete':
            svc_adminDeleteCollection(
                $conn,
                (int)$_POST['collection_id']
            );
            header('Location: /admin/panel.php?tab=collections&deleted=1');
            exit;

        default:
            throw new RuntimeException("Unknown admin action: $action");
    }

} catch (Throwable $e) {
    header("Location: /admin/panel.php?tab=collections&error=" . urlencode($e->getMessage()));
    exit;
}
