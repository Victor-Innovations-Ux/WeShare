<?php

namespace Services;

use Models\User;
use Models\Participant;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthService {
    private static string $algorithm = 'HS256';

    /**
     * Generate JWT token for user
     */
    public static function generateUserToken(User $user): string {
        $payload = [
            'iat' => time(),
            'exp' => time() + ($_ENV['JWT_EXPIRY'] ?? 86400),
            'type' => 'user',
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName()
        ];

        return JWT::encode($payload, $_ENV['JWT_SECRET'], self::$algorithm);
    }

    /**
     * Generate JWT token for participant
     */
    public static function generateParticipantToken(Participant $participant): string {
        $payload = [
            'iat' => time(),
            'exp' => time() + ($_ENV['JWT_EXPIRY'] ?? 86400),
            'type' => 'participant',
            'participant_id' => $participant->getId(),
            'group_id' => $participant->getGroupId(),
            'name' => $participant->getName()
        ];

        return JWT::encode($payload, $_ENV['JWT_SECRET'], self::$algorithm);
    }

    /**
     * Verify and decode JWT token
     */
    public static function verifyToken(string $token): ?array {
        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], self::$algorithm));
            return (array) $decoded;
        } catch (\Exception $e) {
            error_log("JWT verification failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get bearer token from Authorization header
     */
    public static function getBearerToken(): ?string {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Authenticate request and return user/participant data
     */
    public static function authenticate(): ?array {
        $token = self::getBearerToken();

        if (!$token) {
            return null;
        }

        $payload = self::verifyToken($token);

        if (!$payload) {
            return null;
        }

        // Check if token is expired
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Check if authenticated as user
     */
    public static function isUser(): bool {
        $auth = self::authenticate();
        return $auth && $auth['type'] === 'user';
    }

    /**
     * Check if authenticated as participant
     */
    public static function isParticipant(): bool {
        $auth = self::authenticate();
        return $auth && $auth['type'] === 'participant';
    }

    /**
     * Get authenticated user ID
     */
    public static function getUserId(): ?int {
        $auth = self::authenticate();
        return ($auth && $auth['type'] === 'user') ? $auth['user_id'] : null;
    }

    /**
     * Get authenticated participant ID
     */
    public static function getParticipantId(): ?int {
        $auth = self::authenticate();
        return ($auth && $auth['type'] === 'participant') ? $auth['participant_id'] : null;
    }

    /**
     * Generate random state for OAuth
     */
    public static function generateState(): string {
        return bin2hex(random_bytes(16));
    }

    /**
     * Store OAuth state in session
     */
    public static function storeState(string $state): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_state_created'] = time();
    }

    /**
     * Verify OAuth state
     */
    public static function verifyState(string $state): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['oauth_state'])) {
            return false;
        }

        // Check if state is not expired (5 minutes)
        if (time() - $_SESSION['oauth_state_created'] > 300) {
            unset($_SESSION['oauth_state']);
            unset($_SESSION['oauth_state_created']);
            return false;
        }

        $valid = $_SESSION['oauth_state'] === $state;

        // Clean up
        unset($_SESSION['oauth_state']);
        unset($_SESSION['oauth_state_created']);

        return $valid;
    }
}