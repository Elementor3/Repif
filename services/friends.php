<?php
require_once __DIR__ . '/collections.php';

function getFriends(mysqli $conn, string $username): array {
    $stmt = $conn->prepare("SELECT u.* FROM friendship f JOIN user u ON (CASE WHEN f.pk_user1 = ? THEN f.pk_user2 ELSE f.pk_user1 END) = u.pk_username WHERE f.pk_user1 = ? OR f.pk_user2 = ?");
    $stmt->bind_param("sss", $username, $username, $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function areFriends(mysqli $conn, string $user1, string $user2): bool {
    $a = min($user1, $user2);
    $b = max($user1, $user2);
    $stmt = $conn->prepare("SELECT 1 FROM friendship WHERE pk_user1 = ? AND pk_user2 = ?");
    $stmt->bind_param("ss", $a, $b);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function sendFriendRequest(mysqli $conn, string $sender, string $receiver): bool {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO request (fk_sender, fk_receiver, status, createdAt) VALUES (?,?,'pending',?)");
    $stmt->bind_param("sss", $sender, $receiver, $now);
    return $stmt->execute();
}

function getPendingRequests(mysqli $conn, string $username): array {
    $stmt = $conn->prepare("SELECT r.*, u.firstName, u.lastName, u.avatar FROM request r JOIN user u ON r.fk_sender = u.pk_username WHERE r.fk_receiver = ? AND r.status = 'pending' ORDER BY r.createdAt DESC");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function acceptRequest(mysqli $conn, int $requestId, string $username): bool {
    $stmt = $conn->prepare("SELECT fk_sender, fk_receiver FROM request WHERE pk_requestID = ? AND fk_receiver = ? AND status = 'pending'");
    $stmt->bind_param("is", $requestId, $username);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    if (!$req) return false;
    $a = min($req['fk_sender'], $req['fk_receiver']);
    $b = max($req['fk_sender'], $req['fk_receiver']);
    $now = date('Y-m-d H:i:s');
    $ins = $conn->prepare("INSERT IGNORE INTO friendship (pk_user1, pk_user2, createdAt) VALUES (?,?,?)");
    $ins->bind_param("sss", $a, $b, $now);
    $ins->execute();
    $upd = $conn->prepare("UPDATE request SET status='accepted' WHERE pk_requestID=?");
    $upd->bind_param("i", $requestId);
    return $upd->execute();
}

function rejectRequest(mysqli $conn, int $requestId, string $username): bool {
    $stmt = $conn->prepare("UPDATE request SET status='rejected' WHERE pk_requestID=? AND fk_receiver=?");
    $stmt->bind_param("is", $requestId, $username);
    return $stmt->execute();
}

function removeFriend(mysqli $conn, string $user1, string $user2): bool {
    $a = min($user1, $user2);
    $b = max($user1, $user2);
    $stmt = $conn->prepare("DELETE FROM friendship WHERE pk_user1=? AND pk_user2=?");
    $stmt->bind_param("ss", $a, $b);
    $ok = $stmt->execute();
    if ($ok) {
        unshareAllBetweenUsers($conn, $user1, $user2);
    }
    return $ok;
}

function hasPendingRequest(mysqli $conn, string $sender, string $receiver): bool {
    $stmt = $conn->prepare("SELECT 1 FROM request WHERE fk_sender=? AND fk_receiver=? AND status='pending'");
    $stmt->bind_param("ss", $sender, $receiver);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}
?>
