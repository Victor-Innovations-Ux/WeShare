<?php

namespace Services;

/**
 * Secure file upload service with validation and storage management
 */
class FileUploadService {
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const MAX_FILE_SIZE = 10485760; // 10MB

    private string $baseUploadPath;
    private bool $createThumbnails;

    public function __construct(?string $baseUploadPath = null, bool $createThumbnails = false) {
        $this->baseUploadPath = $baseUploadPath ?? $_ENV['PHOTO_PATH'] ?? '/photo';
        $this->createThumbnails = $createThumbnails;
    }

    /**
     * Validate uploaded file
     * Returns array with ['valid' => bool, 'error' => string|null]
     */
    public function validateFile(array $file): array {
        // Check for upload errors
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['valid' => false, 'error' => 'Invalid file upload'];
        }

        // Handle specific upload errors
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['valid' => false, 'error' => 'File exceeds maximum size of 10MB'];
            case UPLOAD_ERR_PARTIAL:
                return ['valid' => false, 'error' => 'File was only partially uploaded'];
            case UPLOAD_ERR_NO_FILE:
                return ['valid' => false, 'error' => 'No file was uploaded'];
            case UPLOAD_ERR_NO_TMP_DIR:
                return ['valid' => false, 'error' => 'Missing temporary folder'];
            case UPLOAD_ERR_CANT_WRITE:
                return ['valid' => false, 'error' => 'Failed to write file to disk'];
            case UPLOAD_ERR_EXTENSION:
                return ['valid' => false, 'error' => 'Upload blocked by PHP extension'];
            default:
                return ['valid' => false, 'error' => 'Unknown upload error'];
        }

        // Validate file exists
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'Invalid upload'];
        }

        // Check file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['valid' => false, 'error' => 'File size exceeds 10MB limit'];
        }

        // Validate MIME type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            return ['valid' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF and WEBP are allowed'];
        }

        // Validate extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            return ['valid' => false, 'error' => 'Invalid file extension'];
        }

        // Verify it's actually an image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['valid' => false, 'error' => 'File is not a valid image'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Generate secure filename using UUID
     */
    public function generateSecureFilename(string $originalExtension): string {
        // Use PHP's random_bytes for cryptographically secure randomness
        $uuid = bin2hex(random_bytes(16));

        // Format as UUID v4
        $uuid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($uuid, 0, 8),
            substr($uuid, 8, 4),
            substr($uuid, 12, 4),
            substr($uuid, 16, 4),
            substr($uuid, 20, 12)
        );

        return $uuid . '.' . strtolower($originalExtension);
    }

    /**
     * Get storage path for group
     */
    public function getGroupStoragePath(int $groupId, string $subdir = 'originals'): string {
        return $this->baseUploadPath . '/groups/' . $groupId . '/' . $subdir;
    }

    /**
     * Create directory structure for group if not exists
     */
    private function ensureGroupDirectory(int $groupId): bool {
        $originalsPath = $this->getGroupStoragePath($groupId, 'originals');

        if (!is_dir($originalsPath)) {
            if (!mkdir($originalsPath, 0755, true)) {
                return false;
            }
        }

        if ($this->createThumbnails) {
            $thumbsPath = $this->getGroupStoragePath($groupId, 'thumbnails');
            if (!is_dir($thumbsPath)) {
                if (!mkdir($thumbsPath, 0755, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Store uploaded file securely
     * Returns ['success' => bool, 'path' => string|null, 'error' => string|null]
     */
    public function storeFile(array $file, int $groupId): array {
        // Validate file
        $validation = $this->validateFile($file);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'path' => null,
                'error' => $validation['error']
            ];
        }

        // Ensure directory exists
        if (!$this->ensureGroupDirectory($groupId)) {
            return [
                'success' => false,
                'path' => null,
                'error' => 'Failed to create storage directory'
            ];
        }

        // Generate secure filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $this->generateSecureFilename($extension);

        // Build full path
        $storagePath = $this->getGroupStoragePath($groupId, 'originals');
        $fullPath = $storagePath . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            return [
                'success' => false,
                'path' => null,
                'error' => 'Failed to save file'
            ];
        }

        // Set restrictive permissions (read/write for owner only)
        chmod($fullPath, 0644);

        // Relative path for database storage
        $relativePath = 'groups/' . $groupId . '/originals/' . $filename;

        return [
            'success' => true,
            'path' => $relativePath,
            'error' => null
        ];
    }

    /**
     * Delete file from storage
     */
    public function deleteFile(string $relativePath): bool {
        $fullPath = $this->baseUploadPath . '/' . $relativePath;

        // Security check: ensure path is within upload directory
        $realBasePath = realpath($this->baseUploadPath);
        $realFilePath = realpath(dirname($fullPath)) . '/' . basename($fullPath);

        if (strpos($realFilePath, $realBasePath) !== 0) {
            error_log("Security warning: attempted path traversal - $relativePath");
            return false;
        }

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }

    /**
     * Get full path for file download
     */
    public function getFilePath(string $relativePath): ?string {
        $fullPath = $this->baseUploadPath . '/' . $relativePath;

        // Security check: path traversal protection
        $realBasePath = realpath($this->baseUploadPath);
        $realFilePath = realpath($fullPath);

        if (!$realFilePath || strpos($realFilePath, $realBasePath) !== 0) {
            error_log("Security warning: attempted path traversal - $relativePath");
            return null;
        }

        return file_exists($fullPath) ? $fullPath : null;
    }

    /**
     * Create thumbnail (optional feature)
     */
    private function createThumbnail(string $sourcePath, string $thumbPath, int $maxWidth = 300): bool {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }

        list($width, $height, $type) = $imageInfo;

        // Calculate new dimensions
        $ratio = $width / $height;
        $newWidth = min($width, $maxWidth);
        $newHeight = $newWidth / $ratio;

        // Create source image
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }

        // Create thumbnail
        $thumb = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG and GIF
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Save thumbnail as JPEG
        $result = imagejpeg($thumb, $thumbPath, 85);

        imagedestroy($source);
        imagedestroy($thumb);

        return $result;
    }
}
