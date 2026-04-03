-- Create database if not exists
CREATE DATABASE IF NOT EXISTS weshare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE weshare;

-- Users table (for group creators who login with Google)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    google_id VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    picture_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_google_id (google_id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Groups table (events)
CREATE TABLE IF NOT EXISTS `groups` (
    id INT PRIMARY KEY AUTO_INCREMENT,
    share_code VARCHAR(10) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    creator_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_share_code (share_code),
    INDEX idx_creator (creator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Participants table (people who join with just their name)
CREATE TABLE IF NOT EXISTS participants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    session_token VARCHAR(255) UNIQUE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
    INDEX idx_group (group_id),
    INDEX idx_session_token (session_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos table
CREATE TABLE IF NOT EXISTS photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100),
    file_size INT,
    uploaded_by_user_id INT,
    uploaded_by_participant_id INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by_participant_id) REFERENCES participants(id) ON DELETE SET NULL,
    INDEX idx_group (group_id),
    INDEX idx_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions table for JWT tokens
CREATE TABLE IF NOT EXISTS sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    participant_id INT,
    token VARCHAR(500) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;