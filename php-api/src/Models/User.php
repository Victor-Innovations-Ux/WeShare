<?php

namespace Models;

use Lib\Database;
use PDO;

class User {
    private ?int $id;
    private string $googleId;
    private string $email;
    private string $name;
    private ?string $pictureUrl;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? null;
        $this->googleId = $data['google_id'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->pictureUrl = $data['picture_url'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    /**
     * Save user to database
     */
    public function save(): bool {
        $db = Database::getInstance();

        if ($this->id === null) {
            // Insert new user
            $stmt = $db->prepare("
                INSERT INTO users (google_id, email, name, picture_url)
                VALUES (:google_id, :email, :name, :picture_url)
            ");

            $result = $stmt->execute([
                'google_id' => $this->googleId,
                'email' => $this->email,
                'name' => $this->name,
                'picture_url' => $this->pictureUrl
            ]);

            if ($result) {
                $this->id = (int)$db->lastInsertId();
            }

            return $result;
        } else {
            // Update existing user
            $stmt = $db->prepare("
                UPDATE users
                SET email = :email, name = :name, picture_url = :picture_url
                WHERE id = :id
            ");

            return $stmt->execute([
                'id' => $this->id,
                'email' => $this->email,
                'name' => $this->name,
                'picture_url' => $this->pictureUrl
            ]);
        }
    }

    /**
     * Find user by ID
     */
    public static function findById(int $id): ?self {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        return $data ? new self($data) : null;
    }

    /**
     * Find user by Google ID
     */
    public static function findByGoogleId(string $googleId): ?self {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE google_id = :google_id");
        $stmt->execute(['google_id' => $googleId]);
        $data = $stmt->fetch();

        return $data ? new self($data) : null;
    }

    /**
     * Find user by email
     */
    public static function findByEmail(string $email): ?self {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $data = $stmt->fetch();

        return $data ? new self($data) : null;
    }

    /**
     * Create or update user from Google OAuth data
     */
    public static function createOrUpdateFromGoogle(array $googleData): self {
        $user = self::findByGoogleId($googleData['id']);

        if (!$user) {
            $user = new self([
                'google_id' => $googleData['id'],
                'email' => $googleData['email'],
                'name' => $googleData['name'],
                'picture_url' => $googleData['picture'] ?? null
            ]);
        } else {
            // Update existing user data
            $user->email = $googleData['email'];
            $user->name = $googleData['name'];
            $user->pictureUrl = $googleData['picture'] ?? null;
        }

        $user->save();
        return $user;
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getGoogleId(): string { return $this->googleId; }
    public function getEmail(): string { return $this->email; }
    public function getName(): string { return $this->name; }
    public function getPictureUrl(): ?string { return $this->pictureUrl; }

    /**
     * Convert to array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'google_id' => $this->googleId,
            'email' => $this->email,
            'name' => $this->name,
            'picture_url' => $this->pictureUrl,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
}