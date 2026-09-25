<?php
// config/database.php

date_default_timezone_set('Asia/Jakarta');

define('DB_SQLITE_FILE', __DIR__ . '/../database/kol_packing.sqlite');

// MySQL InfinityFree Configuration
define('DB_MYSQL_NAME', 'if0_38464190_iegkolaffiliate');
define('DB_MYSQL_USER', 'if0_38464190');
define('DB_MYSQL_PASS', 'Dhaniel0');

function getDB() {
    static $db = null;
    if ($db !== null) {
        return $db;
    }

    $httpHost = strtolower($_SERVER['HTTP_HOST'] ?? '');

    // Detect if running on InfinityFree remote web hosting
    $isInfinityFree = (
        strpos($httpHost, 'great-site.net') !== false ||
        strpos($httpHost, 'xo.je') !== false ||
        strpos($httpHost, 'rf.gd') !== false ||
        strpos($httpHost, 'infinityfree') !== false ||
        strpos($httpHost, '42web.io') !== false ||
        strpos($httpHost, 'epizy.com') !== false ||
        strpos($httpHost, 'page.gd') !== false ||
        strpos($httpHost, 'infy.uk') !== false
    );

    // Try MySQL first if on InfinityFree
    if ($isInfinityFree) {
        $candidateHosts = [
            'sql202.byetcluster.com',
            'sql202.epizy.com',
            'sql202.infinityfree.com',
            '127.0.0.1',
            'localhost'
        ];

        foreach ($candidateHosts as $host) {
            try {
                $dsn = "mysql:host={$host};dbname=" . DB_MYSQL_NAME . ";charset=utf8mb4";
                $db = new PDO($dsn, DB_MYSQL_USER, DB_MYSQL_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 4
                ]);
                $db->exec("SET time_zone = '+07:00'");
                initMySQL($db);
                return $db;
            } catch (Exception $e) {
                error_log("MySQL connection failed on {$host}: " . $e->getMessage());
            }
        }
    }

    // Local / SQLite Driver
    $dbDir = dirname(DB_SQLITE_FILE);
    if (!is_dir($dbDir)) {
        @mkdir($dbDir, 0777, true);
    }

    $isNew = !file_exists(DB_SQLITE_FILE);
    $db = new PDO('sqlite:' . DB_SQLITE_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if ($isNew || filesize(DB_SQLITE_FILE) === 0) {
        initSQLite($db);
    }

    migrateTables($db);

    return $db;
}

function initMySQL($db) {
    // Create users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(150) NOT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'operator',
        is_active INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Create packings table
    $db->exec("CREATE TABLE IF NOT EXISTS packings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        resi_no VARCHAR(100) NOT NULL,
        user_id INT NULL,
        operator_name VARCHAR(150) NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        duration_seconds INT NOT NULL,
        video_filename VARCHAR(255) NOT NULL,
        video_filesize INT DEFAULT 0,
        notes TEXT,
        gdrive_url TEXT NULL,
        synced_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_resi (resi_no),
        INDEX idx_date (created_at),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed default accounts if empty
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM users");
    $row = $stmt->fetch();
    if ($row && $row['cnt'] == 0) {
        $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
        $opPass = password_hash('operator123', PASSWORD_BCRYPT);
        $insert = $db->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, ?)");
        $insert->execute(['admin', $adminPass, 'Administrator KOL', 'admin']);
        $insert->execute(['operator', $opPass, 'Operator Packing 1', 'operator']);
    }
}

function initSQLite($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        name TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'operator',
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS packings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        resi_no TEXT NOT NULL,
        user_id INTEGER,
        operator_name TEXT NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        duration_seconds INTEGER NOT NULL,
        video_filename TEXT NOT NULL,
        video_filesize INTEGER DEFAULT 0,
        notes TEXT,
        gdrive_url TEXT NULL,
        synced_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_packings_resi ON packings(resi_no)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_packings_date ON packings(created_at)");

    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch()['count'];
    if ($count == 0) {
        $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
        $opPass = password_hash('operator123', PASSWORD_BCRYPT);
        $insert = $db->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, ?)");
        $insert->execute(['admin', $adminPass, 'Administrator KOL', 'admin']);
        $insert->execute(['operator', $opPass, 'Operator Packing 1', 'operator']);
    }
}

function migrateTables($db) {
    static $alreadyRun = false;
    if ($alreadyRun) return;
    $alreadyRun = true;

    try {
        $db->exec("ALTER TABLE packings ADD COLUMN gdrive_url TEXT NULL");
    } catch (Exception $e) {}

    try {
        $db->exec("ALTER TABLE packings ADD COLUMN synced_at DATETIME NULL");
    } catch (Exception $e) {}

    // Pastikan user superadmin Daniel (Password: Dh@niel0) tersedia
    try {
        $stmtDan = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmtDan->execute(['Daniel']);
        $danRow = $stmtDan->fetch();
        $danPassHash = password_hash('Dh@niel0', PASSWORD_BCRYPT);

        if ($danRow) {
            $up = $db->prepare("UPDATE users SET password = ?, role = 'superadmin', is_active = 1 WHERE id = ?");
            $up->execute([$danPassHash, $danRow['id']]);
        } else {
            $in = $db->prepare("INSERT INTO users (username, password, name, role, is_active) VALUES (?, ?, ?, ?, 1)");
            $in->execute(['Daniel', $danPassHash, 'Daniel', 'superadmin']);
        }
    } catch (Exception $e) {
        error_log("Failed to seed/update superadmin Daniel: " . $e->getMessage());
    }
}

