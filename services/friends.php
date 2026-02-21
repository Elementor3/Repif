<?php
// services/friends.php

function svc_getFriendRequests($conn, $username) {
    $stmt = mysqli_prepare($conn,
        "SELECT r.pk_requestID, u.pk_username, u.firstName, u.lastName, r.createdAt
         FROM request r
         JOIN user u ON u.pk_username = r.fk_sender
         WHERE r.fk_receiver=? AND r.status='pending'
         ORDER BY r.createdAt DESC"
    );
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

function svc_getFriendsList($conn, $username) {
    $stmt = mysqli_prepare($conn,
        "SELECT u.pk_username, u.firstName, u.lastName, u.email, f.createdAt
         FROM friendship f
         JOIN user u ON u.pk_username = 
            CASE
                WHEN f.pk_user1 = ? THEN f.pk_user2
                ELSE f.pk_user1
            END
         WHERE ? IN (f.pk_user1, f.pk_user2)
         ORDER BY u.pk_username"
    );
    mysqli_stmt_bind_param($stmt, "ss", $username, $username);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

function svc_sendFriendRequest($conn, $from, $to) {
    if ($to === $from) {
        throw new RuntimeException("You cannot add yourself.");
    }
    
    // check if user exists
    $stmt = mysqli_prepare($conn, "SELECT 1 FROM user WHERE pk_username=?");
    mysqli_stmt_bind_param($stmt, "s", $to);
    mysqli_stmt_execute($stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) === 0) {
        throw new RuntimeException("User not found.");
    }
    
    // check if already friends
    $u1 = $u2 = '';
    if (strcmp($from, $to) <= 0) {
        $u1 = $from; $u2 = $to;
    } else {
        $u1 = $to; $u2 = $from;
    }
    
    $stmt = mysqli_prepare($conn,
        "SELECT 1 FROM friendship WHERE pk_user1=? AND pk_user2=? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "ss", $u1, $u2);
    mysqli_stmt_execute($stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
        throw new RuntimeException("You are already friends.");
    }
    
    // check if request exists
    $stmt = mysqli_prepare($conn,
        "SELECT 1 FROM request
         WHERE ((fk_sender=? AND fk_receiver=?) OR (fk_sender=? AND fk_receiver=?))
           AND status='pending'"
    );
    mysqli_stmt_bind_param($stmt, "ssss", $from, $to, $to, $from);
    mysqli_stmt_execute($stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
        throw new RuntimeException("Friend request already exists.");
    }
    
    $stmt = mysqli_prepare($conn,
        "INSERT INTO request (fk_sender,fk_receiver,status,createdAt)
         VALUES (?, ?, 'pending', NOW())"
    );
    mysqli_stmt_bind_param($stmt, "ss", $from, $to);
    return mysqli_stmt_execute($stmt);
}

function svc_acceptFriendRequest($conn, $requestId, $username) {
    mysqli_begin_transaction($conn);
    
    $stmt = mysqli_prepare($conn,
        "SELECT fk_sender FROM request
         WHERE pk_requestID=? AND fk_receiver=? AND status='pending'"
    );
    mysqli_stmt_bind_param($stmt, "is", $requestId, $username);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($res) === 0) {
        mysqli_rollback($conn);
        throw new RuntimeException("Request not found.");
    }
    
    $sender = mysqli_fetch_assoc($res)['fk_sender'];
    
    if (strcmp($sender, $username) <= 0) {
        $u1 = $sender; $u2 = $username;
    } else {
        $u1 = $username; $u2 = $sender;
    }
    
    $stmt = mysqli_prepare($conn,
        "INSERT INTO friendship (pk_user1, pk_user2, createdAt) VALUES (?, ?, NOW())"
    );
    mysqli_stmt_bind_param($stmt, "ss", $u1, $u2);
    mysqli_stmt_execute($stmt);
    
    $stmt = mysqli_prepare($conn,
        "UPDATE request SET status='accepted' WHERE pk_requestID=?"
    );
    mysqli_stmt_bind_param($stmt, "i", $requestId);
    mysqli_stmt_execute($stmt);
    
    mysqli_commit($conn);
    return true;
}

function svc_rejectFriendRequest($conn, $requestId, $username) {
    $stmt = mysqli_prepare($conn,
        "UPDATE request SET status='rejected'
         WHERE pk_requestID=? AND fk_receiver=? AND status='pending'"
    );
    mysqli_stmt_bind_param($stmt, "is", $requestId, $username);
    return mysqli_stmt_execute($stmt);
}

function svc_removeFriend($conn, $username, $friend) {
    $u1 = $u2 = '';
    if (strcmp($username, $friend) <= 0) {
        $u1 = $username; $u2 = $friend;
    } else {
        $u1 = $friend; $u2 = $username;
    }
    
    mysqli_begin_transaction($conn);
    
    $stmt = mysqli_prepare($conn,
        "DELETE FROM friendship WHERE pk_user1=? AND pk_user2=?"
    );
    mysqli_stmt_bind_param($stmt, "ss", $u1, $u2);
    mysqli_stmt_execute($stmt);
    
    // unshare collections
    $stmt = mysqli_prepare($conn,
        "DELETE FROM shares
         WHERE (pk_user=? AND pk_collection IN
                (SELECT pk_collectionID FROM collection WHERE fk_user=?))
            OR (pk_user=? AND pk_collection IN
                (SELECT pk_collectionID FROM collection WHERE fk_user=?))"
    );
    mysqli_stmt_bind_param($stmt, "ssss", $friend, $username, $username, $friend);
    mysqli_stmt_execute($stmt);
    
    mysqli_commit($conn);
    return true;
}

function svc_getFriendshipStatus($conn, $user1, $user2) {
    if ($user1 === $user2) return 'self';
    
    $u1 = $u2 = '';
    if (strcmp($user1, $user2) <= 0) {
        $u1 = $user1; $u2 = $user2;
    } else {
        $u1 = $user2; $u2 = $user1;
    }
    
    $stmt = mysqli_prepare($conn,
        "SELECT 1 FROM friendship WHERE pk_user1=? AND pk_user2=? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "ss", $u1, $u2);
    mysqli_stmt_execute($stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
        return 'friends';
    }
    
    $stmt = mysqli_prepare($conn,
        "SELECT status FROM request
         WHERE (fk_sender=? AND fk_receiver=?) OR (fk_sender=? AND fk_receiver=?)
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "ssss", $user1, $user2, $user2, $user1);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($res)) {
        return $row['status'];
    }
    
    return 'none';
}
?>