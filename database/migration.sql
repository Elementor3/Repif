-- Migration: Add new columns to user table
ALTER TABLE user ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) NULL AFTER role;
ALTER TABLE user ADD COLUMN IF NOT EXISTS locale VARCHAR(5) NOT NULL DEFAULT 'en' AFTER avatar;
ALTER TABLE user ADD COLUMN IF NOT EXISTS theme VARCHAR(10) NOT NULL DEFAULT 'light' AFTER locale;

-- sample table
CREATE TABLE IF NOT EXISTS sample (
    pk_sampleID INT PRIMARY KEY AUTO_INCREMENT,
    fk_collection INT NOT NULL,
    fk_station VARCHAR(50) NOT NULL,
    startDateTime DATETIME NOT NULL,
    endDateTime DATETIME NOT NULL,
    FOREIGN KEY (fk_collection) REFERENCES collection(pk_collectionID) ON DELETE CASCADE,
    FOREIGN KEY (fk_station) REFERENCES station(pk_serialNumber) ON DELETE CASCADE
);

-- chat_conversation table
CREATE TABLE IF NOT EXISTS chat_conversation (
    pk_conversationID INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('private','group') NOT NULL,
    name VARCHAR(100) NULL,
    description TEXT NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    createdBy VARCHAR(50) NULL,
    FOREIGN KEY (createdBy) REFERENCES user(pk_username) ON DELETE SET NULL
);
-- For existing installations: add the description column if it does not exist yet
ALTER TABLE chat_conversation ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER name;

-- chat_participant table
CREATE TABLE IF NOT EXISTS chat_participant (
    fk_conversation INT NOT NULL,
    fk_user VARCHAR(50) NOT NULL,
    role ENUM('owner','member') NOT NULL DEFAULT 'member',
    joinedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (fk_conversation, fk_user),
    FOREIGN KEY (fk_conversation) REFERENCES chat_conversation(pk_conversationID) ON DELETE CASCADE,
    FOREIGN KEY (fk_user) REFERENCES user(pk_username) ON DELETE CASCADE
);
-- For existing installations: add the role column if it does not exist yet
ALTER TABLE chat_participant ADD COLUMN IF NOT EXISTS role ENUM('owner','member') NOT NULL DEFAULT 'member' AFTER fk_user;

-- chat_message table
CREATE TABLE IF NOT EXISTS chat_message (
    pk_messageID INT PRIMARY KEY AUTO_INCREMENT,
    fk_conversation INT NOT NULL,
    fk_sender VARCHAR(50) NULL,
    message TEXT NULL,
    file_path VARCHAR(500) NULL,
    file_name VARCHAR(255) NULL,
    file_size INT NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fk_conversation) REFERENCES chat_conversation(pk_conversationID) ON DELETE CASCADE,
    FOREIGN KEY (fk_sender) REFERENCES user(pk_username) ON DELETE SET NULL
);

-- notification table
CREATE TABLE IF NOT EXISTS notification (
    pk_notificationID INT PRIMARY KEY AUTO_INCREMENT,
    fk_user VARCHAR(50) NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(500) NULL,
    is_read TINYINT NOT NULL DEFAULT 0,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fk_user) REFERENCES user(pk_username) ON DELETE CASCADE
);

-- admin_post table
CREATE TABLE IF NOT EXISTS admin_post (
    pk_postID INT PRIMARY KEY AUTO_INCREMENT,
    fk_author VARCHAR(50) NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fk_author) REFERENCES user(pk_username) ON DELETE SET NULL
);

-- password_reset table
CREATE TABLE IF NOT EXISTS password_reset (
    pk_resetID INT PRIMARY KEY AUTO_INCREMENT,
    fk_user VARCHAR(50) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expiresAt DATETIME NOT NULL,
    used TINYINT NOT NULL DEFAULT 0,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fk_user) REFERENCES user(pk_username) ON DELETE CASCADE
);
