<?php

namespace Models;

use Lib\Database;
use PDO;

class Group {
    private ?int $id;
    private string $shareCode;
    private string $name;
    private ?int $creatorId;
    private bool $isActive;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? null;
        $this->shareCode = $data['share_code'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->creatorId = $data['creator_id'] ?? null;
        $this->isActive = $data['is_active'] ?? true;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    /**
     * Generate unique share code
     */
    private static function generateShareCode(): string {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        // Check if code already exists
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM `groups` WHERE share_code = :code");
        $stmt->execute(['code' => $code]);
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            // Recursively generate new code if exists
            return self::generateShareCode();
        }

        return $code;
    }

    /**
     * Save group to database
     */
    public function save(): bool {
        $db = Database::getInstance();

        if ($this->id === null) {
            // Generate share code for new group
            if (empty($this->shareCode)) {
                $this->shareCode = self::generateShareCode();
            }

            // Insert new group
            $stmt = $db->prepare("
                INSERT INTO `groups` (share_code, name, creator_id, is_active)
                VALUES (:share_code, :name, :creator_id, :is_active)
            ");

            $result = $stmt->execute([
                'share_code' => $this->shareCode,
                'name' => $this->name,
                'creator_id' => $this->creatorId,
                'is_active' => $this->isActive
            ]);

            if ($result) {
                $this->id = (int)$db->lastInsertId();
            }

            return $result;
        } else {
            // Update existing group
            $stmt = $db->prepare("
                UPDATE `groups`
                SET name = :name, is_active = :is_active
                WHERE id = :id
            ");

            return $stmt->execute([
                'id' => $this->id,
                'name' => $this->name,
                'is_active' => $this->isActive
            ]);
        }
    }

    /**
     * Find group by ID
     */
    public static function findById(int $id): ?self {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM `groups` WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        return $data ? new self($data) : null;
    }

    /**
     * Find group by share code
     */
    public static function findByShareCode(string $shareCode): ?self {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM `groups` WHERE share_code = :share_code AND is_active = 1");
        $stmt->execute(['share_code' => $shareCode]);
        $data = $stmt->fetch();

        return $data ? new self($data) : null;
    }

    /**
     * Get groups by creator
     */
    public static function findByCreator(int $creatorId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM `groups`
            WHERE creator_id = :creator_id
            ORDER BY created_at DESC
        ");
        $stmt->execute(['creator_id' => $creatorId]);

        $groups = [];
        while ($data = $stmt->fetch()) {
            $groups[] = new self($data);
        }

        return $groups;
    }

    /**
     * Get group statistics
     */
    public function getStatistics(): array {
        $db = Database::getInstance();

        // Get participant count
        $stmt = $db->prepare("
            SELECT COUNT(*) as participant_count
            FROM participants
            WHERE group_id = :group_id
        ");
        $stmt->execute(['group_id' => $this->id]);
        $participantData = $stmt->fetch();

        // Get photo count
        $stmt = $db->prepare("
            SELECT COUNT(*) as photo_count
            FROM photos
            WHERE group_id = :group_id
        ");
        $stmt->execute(['group_id' => $this->id]);
        $photoData = $stmt->fetch();

        return [
            'participant_count' => $participantData['participant_count'],
            'photo_count' => $photoData['photo_count']
        ];
    }

    /**
     * Delete group
     */
    public function delete(): bool {
        if ($this->id === null) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM `groups` WHERE id = :id");
        return $stmt->execute(['id' => $this->id]);
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getShareCode(): string { return $this->shareCode; }
    public function getName(): string { return $this->name; }
    public function getCreatorId(): ?int { return $this->creatorId; }
    public function isActive(): bool { return $this->isActive; }

    // Setters
    public function setName(string $name): void { $this->name = $name; }
    public function setActive(bool $active): void { $this->isActive = $active; }

    /**
     * Convert to array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'share_code' => $this->shareCode,
            'name' => $this->name,
            'creator_id' => $this->creatorId,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
}