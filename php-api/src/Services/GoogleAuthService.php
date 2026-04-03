<?php

namespace Services;

use Models\User;

class GoogleAuthService {
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct() {
        $this->clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
        $this->redirectUri = $_ENV['GOOGLE_REDIRECT_URI'] ?? '';
    }

    /**
     * Get Google OAuth authorization URL
     */
    public function getAuthUrl(string $state): string {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account'
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken(string $code): ?array {
        $url = 'https://oauth2.googleapis.com/token';

        $params = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Failed to get access token: HTTP $httpCode - $response");
            return null;
        }

        $data = json_decode($response, true);

        if (!isset($data['access_token'])) {
            error_log("No access token in response: $response");
            return null;
        }

        return $data;
    }

    /**
     * Get user info from Google
     */
    public function getUserInfo(string $accessToken): ?array {
        $url = 'https://www.googleapis.com/oauth2/v2/userinfo';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Failed to get user info: HTTP $httpCode - $response");
            return null;
        }

        $data = json_decode($response, true);

        if (!isset($data['id'])) {
            error_log("No user ID in response: $response");
            return null;
        }

        return $data;
    }

    /**
     * Authenticate user with Google OAuth code
     */
    public function authenticate(string $code): ?User {
        // Get access token
        $tokenData = $this->getAccessToken($code);
        if (!$tokenData) {
            return null;
        }

        // Get user info
        $userInfo = $this->getUserInfo($tokenData['access_token']);
        if (!$userInfo) {
            return null;
        }

        // Create or update user
        return User::createOrUpdateFromGoogle($userInfo);
    }

    /**
     * Verify ID token (alternative method for client-side auth)
     */
    public function verifyIdToken(string $idToken): ?array {
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);

        // Verify the token is for our app
        if ($data['aud'] !== $this->clientId) {
            return null;
        }

        // Check token expiry
        if ($data['exp'] < time()) {
            return null;
        }

        return $data;
    }
}