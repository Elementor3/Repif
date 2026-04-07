<?php

function ensureEmailVerificationSchema(mysqli $conn): void {
    $dbRes = $conn->query("SELECT DATABASE() AS db");
    $db = $dbRes ? ($dbRes->fetch_assoc()['db'] ?? '') : '';
    if ($db === '') return;

    $checkVerified = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='user' AND COLUMN_NAME='isEmailVerified'");
    $checkVerified->bind_param('s', $db);
    $checkVerified->execute();
    $hasVerified = (int)($checkVerified->get_result()->fetch_assoc()['cnt'] ?? 0) > 0;
    if (!$hasVerified) {
        $conn->query("ALTER TABLE user ADD COLUMN isEmailVerified TINYINT(1) NOT NULL DEFAULT 0 AFTER email");
    }

    $checkVerifiedAt = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='user' AND COLUMN_NAME='emailVerifiedAt'");
    $checkVerifiedAt->bind_param('s', $db);
    $checkVerifiedAt->execute();
    $hasVerifiedAt = (int)($checkVerifiedAt->get_result()->fetch_assoc()['cnt'] ?? 0) > 0;
    if (!$hasVerifiedAt) {
        $conn->query("ALTER TABLE user ADD COLUMN emailVerifiedAt DATETIME NULL AFTER isEmailVerified");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS email_verification (
        pk_verificationID INT PRIMARY KEY AUTO_INCREMENT,
        fk_user VARCHAR(50) NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        expiresAt DATETIME NOT NULL,
        used TINYINT NOT NULL DEFAULT 0,
        createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (fk_user) REFERENCES user(pk_username) ON DELETE CASCADE
    )");
}

function createEmailVerificationToken(mysqli $conn, string $username): ?string {
    ensureEmailVerificationSchema($conn);

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $stmt = $conn->prepare("INSERT INTO email_verification (fk_user, token, expiresAt) VALUES (?,?,?)");
    $stmt->bind_param('sss', $username, $token, $expires);

    return $stmt->execute() ? $token : null;
}

function verifyEmailToken(mysqli $conn, string $token): bool {
    ensureEmailVerificationSchema($conn);

    $stmt = $conn->prepare("SELECT fk_user FROM email_verification WHERE token=? AND used=0 AND expiresAt > NOW() LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) return false;

    $username = $row['fk_user'];

    $stmtUser = $conn->prepare("UPDATE user SET isEmailVerified=1, emailVerifiedAt=NOW() WHERE pk_username=?");
    $stmtUser->bind_param('s', $username);
    $okUser = $stmtUser->execute();

    $stmtToken = $conn->prepare("UPDATE email_verification SET used=1 WHERE token=?");
    $stmtToken->bind_param('s', $token);
    $okToken = $stmtToken->execute();

    return $okUser && $okToken;
}
