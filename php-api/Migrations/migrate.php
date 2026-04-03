<?php

require_once __DIR__ . '/../src/Config/env.php';
require_once __DIR__ . '/../src/Lib/Database.php';

use Lib\Database;

echo "Starting database migration...\n";

try {
    // Get all migration files
    $migrationFiles = glob(__DIR__ . '/*.sql');
    sort($migrationFiles);

    // Connect to database
    $db = Database::getInstance();

    // Create migrations table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT PRIMARY KEY AUTO_INCREMENT,
            filename VARCHAR(255) UNIQUE NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Get executed migrations
    $stmt = $db->query("SELECT filename FROM migrations");
    $executedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Execute new migrations
    foreach ($migrationFiles as $file) {
        $filename = basename($file);

        if (in_array($filename, $executedMigrations)) {
            echo "Skipping already executed migration: $filename\n";
            continue;
        }

        echo "Executing migration: $filename\n";

        // Read and execute SQL file
        $sql = file_get_contents($file);

        // Split by statements (simple approach)
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $db->exec($statement);
            }
        }

        // Record migration
        $stmt = $db->prepare("INSERT INTO migrations (filename) VALUES (:filename)");
        $stmt->execute(['filename' => $filename]);

        echo "Migration $filename executed successfully\n";
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}