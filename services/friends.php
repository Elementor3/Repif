<?php
require_once __DIR__ . '/collections.php';

function getFriends(mysqli $conn, string $username): array {
    $stmt = $conn->prepare("SELECT u.* FROM friendship f JOIN user u ON (CASE WHEN f.pkfk_user1 = ? THEN f.pkfk_user2 ELSE f.pkfk_user1 END) = u.pk_username WHERE f.pkfk_user1 = ? OR f.pkfk_user2 = ?");
    $stmt->bind_param("sss", $username, $username, $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function areFriends(mysqli $conn, string $user1, string $user2): bool {
    $stmt = $conn->prepare(
        "SELECT 1
         FROM friendship
         WHERE (pkfk_user1 = ? AND pkfk_user2 = ?)
            OR (pkfk_user1 = ? AND pkfk_user2 = ?)
         LIMIT 1"
    );
    $stmt->bind_param("ssss", $user1, $user2, $user2, $user1);
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
    $stmt = $conn->prepare("SELECT r.pk_requestID, r.fk_sender, r.fk_receiver, r.status, r.createdAt, CASE WHEN r.fk_sender = ? THEN 'outgoing' ELSE 'incoming' END AS direction, u.pk_username, u.firstName, u.lastName, u.avatar FROM request r JOIN user u ON u.pk_username = CASE WHEN r.fk_sender = ? THEN r.fk_receiver ELSE r.fk_sender END WHERE (r.fk_sender = ? OR r.fk_receiver = ?) AND r.status = 'pending' ORDER BY r.createdAt DESC");
    $stmt->bind_param("ssss", $username, $username, $username, $username);
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
    $ins = $conn->prepare("INSERT IGNORE INTO friendship (pkfk_user1, pkfk_user2, createdAt) VALUES (?,?,?)");
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

function cancelOutgoingRequest(mysqli $conn, int $requestId, string $username): bool {
    $stmt = $conn->prepare("DELETE FROM request WHERE pk_requestID=? AND fk_sender=? AND status='pending'");
    $stmt->bind_param("is", $requestId, $username);
    $stmt->execute();
    return $stmt->affected_rows === 1;
}

function removeFriend(mysqli $conn, string $user1, string $user2): bool {
    $stmt = $conn->prepare(
        "DELETE FROM friendship
         WHERE (pkfk_user1 = ? AND pkfk_user2 = ?)
            OR (pkfk_user1 = ? AND pkfk_user2 = ?)"
    );
    $stmt->bind_param("ssss", $user1, $user2, $user2, $user1);
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

function hasPendingRequestBetween(mysqli $conn, string $user1, string $user2): bool {
    return hasPendingRequest($conn, $user1, $user2) || hasPendingRequest($conn, $user2, $user1);
}
?>
