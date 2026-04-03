#!/usr/bin/env php
<?php

/**
 * Migration script to move photos from /uploads to /photo structure
 *
 * This script:
 * 1. Reads all photos from database
 * 2. Copies files from old uploads/ directory to new /photo/groups structure
 * 3. Updates database with new file paths
 * 4. Validates migration success
 *
 * Usage: php Migrations/migrate_photos.php
 */

// Load environment and autoloader
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Config/env.php';

// Autoloader for custom classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use Lib\Database;
use Services\FileUploadService;

class PhotoMigration {
    private string $oldUploadPath;
    private string $newPhotoPath;
    private FileUploadService $uploadService;
    private int $successCount = 0;
    private int $errorCount = 0;
    private array $errors = [];

    public function __construct() {
        $this->oldUploadPath = __DIR__ . '/../uploads/';
        $this->newPhotoPath = $_ENV['PHOTO_PATH'] ?? '/photo';
        $this->uploadService = new FileUploadService($this->newPhotoPath);
    }

    /**
     * Run migration
     */
    public function run(): void {
        echo "=== WeShare Photo Migration ===\n";
        echo "From: {$this->oldUploadPath}\n";
        echo "To: {$this->newPhotoPath}\n\n";

        // Check if new photo path exists
        if (!is_dir($this->newPhotoPath)) {
            echo "Creating photo directory: {$this->newPhotoPath}\n";
            if (!mkdir($this->newPhotoPath, 0755, true)) {
                die("ERROR: Failed to create photo directory\n");
            }
        }

        // Get all photos from database
        $photos = $this->getAllPhotos();
        $totalPhotos = count($photos);

        echo "Found {$totalPhotos} photos to migrate\n\n";

        if ($totalPhotos === 0) {
            echo "No photos to migrate. Exiting.\n";
            return;
        }

        // Confirm migration
        echo "This will migrate all photos to the new structure.\n";
        echo "Continue? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);

        if (strtolower($line) !== 'yes') {
            echo "Migration cancelled.\n";
            return;
        }

        // Migrate each photo
        foreach ($photos as $photo) {
            $this->migratePhoto($photo);
        }

        // Print summary
        echo "\n=== Migration Summary ===\n";
        echo "Total photos: {$totalPhotos}\n";
        echo "Successfully migrated: {$this->successCount}\n";
        echo "Errors: {$this->errorCount}\n";

        if ($this->errorCount > 0) {
            echo "\nErrors encountered:\n";
            foreach ($this->errors as $error) {
                echo "  - {$error}\n";
            }
        }

        echo "\nMigration complete!\n";
    }

    /**
     * Get all photos from database
     */
    private function getAllPhotos(): array {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM photos ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    /**
     * Migrate single photo
     */
    private function migratePhoto(array $photo): void {
        $photoId = $photo['id'];
        $oldPath = $photo['file_path'];
        $groupId = $photo['group_id'];

        echo "[{$photoId}] Migrating: {$oldPath} ... ";

        // Check if already migrated
        if (strpos($oldPath, 'groups/') === 0) {
            echo "SKIPPED (already migrated)\n";
            $this->successCount++;
            return;
        }

        // Build full old path
        $oldFullPath = __DIR__ . '/../' . $oldPath;

        // Check if old file exists
        if (!file_exists($oldFullPath)) {
            echo "ERROR (file not found)\n";
            $this->errors[] = "Photo #{$photoId}: File not found at {$oldPath}";
            $this->errorCount++;
            return;
        }

        // Get file extension
        $extension = pathinfo($oldPath, PATHINFO_EXTENSION);

        // Generate new secure filename
        $newFilename = $this->uploadService->generateSecureFilename($extension);

        // Ensure group directory exists
        $groupDir = $this->uploadService->getGroupStoragePath($groupId, 'originals');
        if (!is_dir($groupDir)) {
            if (!mkdir($groupDir, 0755, true)) {
                echo "ERROR (failed to create directory)\n";
                $this->errors[] = "Photo #{$photoId}: Failed to create directory {$groupDir}";
                $this->errorCount++;
                return;
            }
        }

        // Build new full path
        $newFullPath = $groupDir . '/' . $newFilename;
        $newRelativePath = 'groups/' . $groupId . '/originals/' . $newFilename;

        // Copy file to new location
        if (!copy($oldFullPath, $newFullPath)) {
            echo "ERROR (failed to copy)\n";
            $this->errors[] = "Photo #{$photoId}: Failed to copy file";
            $this->errorCount++;
            return;
        }

        // Set permissions
        chmod($newFullPath, 0644);

        // Update database
        if (!$this->updatePhotoPath($photoId, $newRelativePath)) {
            echo "ERROR (failed to update database)\n";
            $this->errors[] = "Photo #{$photoId}: Failed to update database";
            $this->errorCount++;
            // Don't delete the copied file - manual intervention needed
            return;
        }

        echo "OK\n";
        $this->successCount++;

        // Optional: Delete old file (commented out for safety)
        // unlink($oldFullPath);
    }

    /**
     * Update photo path in database
     */
    private function updatePhotoPath(int $photoId, string $newPath): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE photos SET file_path = :path WHERE id = :id");
        return $stmt->execute([
            'path' => $newPath,
            'id' => $photoId
        ]);
    }

    /**
     * Cleanup old uploads directory (optional)
     */
    public function cleanupOldUploads(): void {
        echo "\n=== Cleanup Old Uploads ===\n";
        echo "This will DELETE all files from {$this->oldUploadPath}\n";
        echo "Make sure migration was successful before proceeding!\n";
        echo "Continue? (yes/no): ";

        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);

        if (strtolower($line) !== 'yes') {
            echo "Cleanup cancelled.\n";
            return;
        }

        $this->deleteDirectory($this->oldUploadPath);
        echo "Cleanup complete!\n";
    }

    /**
     * Recursively delete directory
     */
    private function deleteDirectory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..', '.gitkeep']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        // Don't delete the uploads directory itself, just empty it
        // rmdir($dir);
    }
}

// Run migration
$migration = new PhotoMigration();
$migration->run();

// Optionally cleanup (commented out for safety)
// $migration->cleanupOldUploads();
