<?php

namespace Lib;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;

    /**
     * Get PDO database connection instance (singleton)
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $host = $_ENV['DB_HOST'] ?? 'localhost';
                $dbname = $_ENV['DB_NAME'] ?? 'weshare';
                $user = $_ENV['DB_USER'] ?? 'root';
                $password = $_ENV['DB_PASSWORD'] ?? '';
                $port = $_ENV['DB_PORT'] ?? '3306';

                $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

                self::$instance = new PDO($dsn, $user, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new \Exception("Unable to connect to database");
            }
        }

        return self::$instance;
    }

    /**
     * Close database connection
     */
    public static function close(): void {
        self::$instance = null;
    }
}