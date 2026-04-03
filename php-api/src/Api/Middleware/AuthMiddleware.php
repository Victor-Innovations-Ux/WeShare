<?php

namespace Api\Middleware;

use Services\AuthService;
use Lib\Response;

class AuthMiddleware {
    /**
     * Require authentication (user or participant)
     */
    public static function requireAuth(): bool {
        $auth = AuthService::authenticate();

        if (!$auth) {
            Response::unauthorized('Authentication required');
            return false;
        }

        // Store auth data for controllers
        $GLOBALS['auth'] = $auth;

        return true;
    }

    /**
     * Require user authentication (Google login)
     */
    public static function requireUser(): bool {
        $auth = AuthService::authenticate();

        if (!$auth || $auth['type'] !== 'user') {
            Response::unauthorized('User authentication required');
            return false;
        }

        // Store auth data for controllers
        $GLOBALS['auth'] = $auth;

        return true;
    }

    /**
     * Require participant authentication
     */
    public static function requireParticipant(): bool {
        $auth = AuthService::authenticate();

        if (!$auth || $auth['type'] !== 'participant') {
            Response::unauthorized('Participant authentication required');
            return false;
        }

        // Store auth data for controllers
        $GLOBALS['auth'] = $auth;

        return true;
    }

    /**
     * Optional authentication (don't fail if not authenticated)
     */
    public static function optionalAuth(): bool {
        $auth = AuthService::authenticate();

        if ($auth) {
            $GLOBALS['auth'] = $auth;
        }

        return true;
    }
}