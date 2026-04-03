<?php

namespace Models;

use Lib\Database;
use PDO;

class Participant {
    private ?int $id;
    private int $groupId;
    private string $name;
    private ?string $sessionToken;
    private ?string $joinedAt;

    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? null;
        $this->groupId = $data['group_id'] ?? 0;
        $this->name = $data['name'] ?? '';
        $this->sessionToken = $data['session_token'] ?? null;
        $this->joinedAt = $data['joined_at'] ?? null;
    }

    /**
     * Generate unique session token
     */
    private static function generateSessionToken(): string {
        return bin2hex(random_bytes(32));
    }

    /**
     * Save participant to database
     */
    public function save(): bool {
        $db = Database::getInstance();

        if ($this->id === null) {
            // Generate session token for new participant
            if (empty($this->sessionToken)) {
                $this->sessionToken = self::generateSessionToken();
            }

            // Insert new participant
            $stmt = $db->prepare("
                INSERT INTO participants (group_id, name, session_token)
                VALUES (:group_id, :name, :session_token)
            ");

            $result = $stmt->execute([
                'group_id' => $this->groupId,
                'name' => $this->name,
                'session_token' => $this->sessionToken
            ]);

            if ($result) {
                $this->id = (int)$db->lastInsertId();
            }

            return $result;
        } else {
            // Update existing participant
            $stmt = $db->prepare("
                UPDATE participants
                SET name = :name
                WHERE id = :id
            ");

            return $stmt->execute([
                'id' => $this->id,
                'name' => $this->name
            ]);
        }
    }

    /**
     * Find participant by ID
     */
    public static function findById(int $id): ?self {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM participants WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        return $data ? new self($data) : null;
    }

    /**
     * Find participant by session token
     */
    public static function findBySessionToken(string $token): ?self {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM participants WHERE session_token = :token");
        $stmt->execute(['token' => $token]);
        $data = $stmt->fetch();

        return $data ? new self($data) : null;
    }

    /**
     * Get participants by group
     */
    public static function findByGroup(int $groupId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM participants
            WHERE group_id = :group_id
            ORDER BY joined_at DESC
        ");
        $stmt->execute(['group_id' => $groupId]);

        $participants = [];
        while ($data = $stmt->fetch()) {
            $participants[] = new self($data);
        }

        return $participants;
    }

    /**
     * Check if participant exists in group
     */
    public static function existsInGroup(int $groupId, string $name): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM participants
            WHERE group_id = :group_id AND name = :name
        ");
        $stmt->execute([
            'group_id' => $groupId,
            'name' => $name
        ]);
        $result = $stmt->fetch();

        return $result['count'] > 0;
    }

    /**
     * Get uploaded photos count
     */
    public function getPhotoCount(): int {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM photos
            WHERE uploaded_by_participant_id = :participant_id
        ");
        $stmt->execute(['participant_id' => $this->id]);
        $result = $stmt->fetch();

        return $result['count'];
    }

    /**
     * Delete participant
     */
    public function delete(): bool {
        if ($this->id === null) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM participants WHERE id = :id");
        return $stmt->execute(['id' => $this->id]);
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getGroupId(): int { return $this->groupId; }
    public function getName(): string { return $this->name; }
    public function getSessionToken(): ?string { return $this->sessionToken; }
    public function getJoinedAt(): ?string { return $this->joinedAt; }

    // Setters
    public function setName(string $name): void { $this->name = $name; }

    /**
     * Convert to array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'group_id' => $this->groupId,
            'name' => $this->name,
            'joined_at' => $this->joinedAt,
            'photo_count' => $this->getPhotoCount()
        ];
    }
}