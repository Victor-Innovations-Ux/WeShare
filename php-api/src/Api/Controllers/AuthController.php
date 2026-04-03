<?php

namespace Api\Controllers;

use Services\AuthService;
use Services\GoogleAuthService;
use Models\User;
use Models\Participant;
use Models\Group;
use Lib\Request;
use Lib\Response;
use Lib\Validator;

class AuthController {
    /**
     * Initiate Google OAuth login
     */
    public static function googleLogin(): void {
        $googleAuth = new GoogleAuthService();
        $state = AuthService::generateState();
        AuthService::storeState($state);

        $authUrl = $googleAuth->getAuthUrl($state);
        Response::success(['auth_url' => $authUrl]);
    }

    /**
     * Handle Google OAuth callback
     */
    public static function googleCallback(): void {
        $request = new Request();
        $code = $request->getQuery('code');
        $state = $request->getQuery('state');

        // Validate state
        if (!$state || !AuthService::verifyState($state)) {
            Response::error('Invalid state parameter', 400);
            return;
        }

        // Validate code
        if (!$code) {
            Response::error('Authorization code missing', 400);
            return;
        }

        // Authenticate with Google
        $googleAuth = new GoogleAuthService();
        $user = $googleAuth->authenticate($code);

        if (!$user) {
            Response::error('Authentication failed', 401);
            return;
        }

        // Generate JWT token
        $token = AuthService::generateUserToken($user);

        Response::success([
            'token' => $token,
            'user' => $user->toArray()
        ]);
    }

    /**
     * Join a group as participant
     */
    public static function joinGroup(): void {
        $request = new Request();
        $body = $request->getBody();

        // Validate input
        $validator = new Validator($body);
        $validator->required('share_code', 'Share code is required')
                  ->required('name', 'Name is required')
                  ->min('name', 2, 'Name must be at least 2 characters')
                  ->max('name', 100, 'Name must not exceed 100 characters');

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        // Find group by share code
        $group = Group::findByShareCode($body['share_code']);
        if (!$group) {
            Response::notFound('Group not found or inactive');
            return;
        }

        // Check if participant name already exists in group
        if (Participant::existsInGroup($group->getId(), $body['name'])) {
            Response::error('This name is already taken in this group', 400);
            return;
        }

        // Create participant
        $participant = new Participant([
            'group_id' => $group->getId(),
            'name' => $body['name']
        ]);

        if (!$participant->save()) {
            Response::error('Failed to join group', 500);
            return;
        }

        // Generate JWT token
        $token = AuthService::generateParticipantToken($participant);

        Response::success([
            'token' => $token,
            'participant' => $participant->toArray(),
            'group' => $group->toArray()
        ], 'Successfully joined group');
    }

    /**
     * Get current user/participant info
     */
    public static function me(): void {
        $auth = $GLOBALS['auth'] ?? null;

        if (!$auth) {
            Response::unauthorized();
            return;
        }

        $data = ['type' => $auth['type']];

        if ($auth['type'] === 'user') {
            $user = User::findById($auth['user_id']);
            if (!$user) {
                Response::unauthorized('User not found');
                return;
            }
            $data['user'] = $user->toArray();
        } else if ($auth['type'] === 'participant') {
            $participant = Participant::findById($auth['participant_id']);
            if (!$participant) {
                Response::unauthorized('Participant not found');
                return;
            }
            $data['participant'] = $participant->toArray();

            // Include group info
            $group = Group::findById($participant->getGroupId());
            if ($group) {
                $data['group'] = $group->toArray();
            }
        }

        Response::success($data);
    }

    /**
     * Logout (client-side token removal)
     */
    public static function logout(): void {
        // JWT is stateless, so we just return success
        // Client should remove token from storage
        Response::success(null, 'Logged out successfully');
    }
}