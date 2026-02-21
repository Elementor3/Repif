<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../services/stations.php';
require_once '../services/collections.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_stats':
            // Stations count
            $stmt = mysqli_prepare($conn,
                "SELECT COUNT(*) as cnt FROM station WHERE fk_registeredBy = ?"
            );
            mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $stations = mysqli_fetch_assoc($res)['cnt'];
            
            // Collections count
            $stmt = mysqli_prepare($conn,
                "SELECT COUNT(*) as cnt FROM collection WHERE fk_user = ?"
            );
            mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $collections = mysqli_fetch_assoc($res)['cnt'];
            
            // Friends count
            $stmt = mysqli_prepare($conn,
                "SELECT COUNT(*) as cnt FROM friendship
                 WHERE pk_user1 = ? OR pk_user2 = ?"
            );
            mysqli_stmt_bind_param($stmt, "ss", $_SESSION['username'], $_SESSION['username']);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $friends = mysqli_fetch_assoc($res)['cnt'];
            
            echo "$stations|$collections|$friends";
            break;
            
        default:
            echo 'ERROR|Unknown action';
    }
    exit;
}
?>