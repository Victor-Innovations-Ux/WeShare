<?php

namespace Models;

use Lib\Database;
use PDO;

class Photo {
    private ?int $id;
    private int $groupId;
    private string $filePath;
    private string $originalName;
    private ?string $mimeType;
    private ?int $fileSize;
    private ?int $uploadedByUserId;
    private ?int $uploadedByParticipantId;
    private ?string $uploadedAt;

    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? null;
        $this->groupId = $data['group_id'] ?? 0;
        $this->filePath = $data['file_path'] ?? '';
        $this->originalName = $data['original_name'] ?? '';
        $this->mimeType = $data['mime_type'] ?? null;
        $this->fileSize = $data['file_size'] ?? null;
        $this->uploadedByUserId = $data['uploaded_by_user_id'] ?? null;
        $this->uploadedByParticipantId = $data['uploaded_by_participant_id'] ?? null;
        $this->uploadedAt = $data['uploaded_at'] ?? null;
    }

    /**
     * Save photo to database
     */
    public function save(): bool {
        $db = Database::getInstance();

        if ($this->id === null) {
            // Insert new photo
            $stmt = $db->prepare("
                INSERT INTO photos (
                    group_id, file_path, original_name, mime_type, file_size,
                    uploaded_by_user_id, uploaded_by_participant_id
                )
                VALUES (
                    :group_id, :file_path, :original_name, :mime_type, :file_size,
                    :uploaded_by_user_id, :uploaded_by_participant_id
                )
            ");

            $result = $stmt->execute([
                'group_id' => $this->groupId,
                'file_path' => $this->filePath,
                'original_name' => $this->originalName,
                'mime_type' => $this->mimeType,
                'file_size' => $this->fileSize,
                'uploaded_by_user_id' => $this->uploadedByUserId,
                'uploaded_by_participant_id' => $this->uploadedByParticipantId
            ]);

            if ($result) {
                $this->id = (int)$db->lastInsertId();
            }

            return $result;
        }

        return false; // Photos cannot be updated
    }

    /**
     * Find photo by ID
     */
    public static function findById(int $id): ?self {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM photos WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        return $data ? new self($data) : null;
    }

    /**
     * Get photos by group with uploader information
     */
    public static function findByGroupWithUploader(int $groupId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT
                p.*,
                u.name as user_name,
                u.picture_url as user_picture,
                pa.name as participant_name
            FROM photos p
            LEFT JOIN users u ON p.uploaded_by_user_id = u.id
            LEFT JOIN participants pa ON p.uploaded_by_participant_id = pa.id
            WHERE p.group_id = :group_id
            ORDER BY p.uploaded_at DESC
        ");
        $stmt->execute(['group_id' => $groupId]);

        $photos = [];
        while ($data = $stmt->fetch()) {
            $photo = new self($data);

            // Add uploader information
            if ($data['user_name']) {
                $photo->uploaderName = $data['user_name'];
                $photo->uploaderPicture = $data['user_picture'];
                $photo->uploaderType = 'user';
            } else if ($data['participant_name']) {
                $photo->uploaderName = $data['participant_name'];
                $photo->uploaderPicture = null;
                $photo->uploaderType = 'participant';
            }

            $photos[] = $photo;
        }

        return $photos;
    }

    /**
     * Get photos by uploader (user)
     */
    public static function findByUser(int $userId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM photos
            WHERE uploaded_by_user_id = :user_id
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);

        $photos = [];
        while ($data = $stmt->fetch()) {
            $photos[] = new self($data);
        }

        return $photos;
    }

    /**
     * Get photos by uploader (participant)
     */
    public static function findByParticipant(int $participantId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM photos
            WHERE uploaded_by_participant_id = :participant_id
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute(['participant_id' => $participantId]);

        $photos = [];
        while ($data = $stmt->fetch()) {
            $photos[] = new self($data);
        }

        return $photos;
    }

    /**
     * Delete photo
     */
    public function delete(): bool {
        if ($this->id === null) {
            return false;
        }

        // Delete file from filesystem using FileUploadService for security
        $uploadService = new \Services\FileUploadService();
        $uploadService->deleteFile($this->filePath);

        // Delete from database
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM photos WHERE id = :id");
        return $stmt->execute(['id' => $this->id]);
    }

    /**
     * Check if user can delete photo
     */
    public function canBeDeletedBy(?int $userId = null, ?int $participantId = null): bool {
        // Photo can be deleted by its uploader
        if ($userId && $this->uploadedByUserId === $userId) {
            return true;
        }

        if ($participantId && $this->uploadedByParticipantId === $participantId) {
            return true;
        }

        // Group creator can delete any photo
        if ($userId) {
            $group = Group::findById($this->groupId);
            if ($group && $group->getCreatorId() === $userId) {
                return true;
            }
        }

        return false;
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getGroupId(): int { return $this->groupId; }
    public function getFilePath(): string { return $this->filePath; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getMimeType(): ?string { return $this->mimeType; }
    public function getFileSize(): ?int { return $this->fileSize; }
    public function getUploadedAt(): ?string { return $this->uploadedAt; }

    /**
     * Convert to array
     */
    public function toArray(): array {
        $data = [
            'id' => $this->id,
            'group_id' => $this->groupId,
            'file_path' => $this->filePath,
            'original_name' => $this->originalName,
            'mime_type' => $this->mimeType,
            'file_size' => $this->fileSize,
            'uploaded_at' => $this->uploadedAt
        ];

        // Add uploader info if available
        if (isset($this->uploaderName)) {
            $data['uploader'] = [
                'name' => $this->uploaderName,
                'picture' => $this->uploaderPicture ?? null,
                'type' => $this->uploaderType
            ];
        }

        return $data;
    }
}