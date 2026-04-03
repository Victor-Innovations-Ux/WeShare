<?php

namespace Api\Controllers;

use Models\Group;
use Models\User;
use Models\Participant;
use Lib\Request;
use Lib\Response;
use Lib\Validator;

class GroupController {
    /**
     * Create a new group
     */
    public static function create(): void {
        $request = new Request();
        $body = $request->getBody();
        $auth = $GLOBALS['auth'] ?? null;
        $userId = $auth['user_id'] ?? null;

        // Validate input
        $validator = new Validator($body);
        $validator->required('name', 'Group name is required')
                  ->min('name', 3, 'Group name must be at least 3 characters')
                  ->max('name', 100, 'Group name must not exceed 100 characters');

        // If no user auth, creator_name is required
        if (!$userId) {
            $validator->required('creator_name', 'Creator name is required')
                     ->min('creator_name', 2, 'Creator name must be at least 2 characters')
                     ->max('creator_name', 100, 'Creator name must not exceed 100 characters');
        }

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        // Create group
        $group = new Group([
            'name' => $body['name'],
            'creator_id' => $userId,
            'is_active' => true
        ]);

        if (!$group->save()) {
            Response::error('Failed to create group', 500);
            return;
        }

        $responseData = $group->toArray();

        // If creating without auth, create a participant as the creator
        if (!$userId && isset($body['creator_name'])) {
            $participant = new Participant([
                'group_id' => $group->getId(),
                'name' => $body['creator_name']
            ]);

            if (!$participant->save()) {
                // Rollback group creation
                $group->delete();
                Response::error('Failed to create participant', 500);
                return;
            }

            // Generate JWT token for the participant
            $token = \Services\AuthService::generateParticipantToken($participant);
            $responseData['token'] = $token;
            $responseData['participant'] = $participant->toArray();
        }

        Response::success($responseData, 'Group created successfully', 201);
    }

    /**
     * List user's groups
     */
    public static function list(): void {
        $userId = $GLOBALS['auth']['user_id'] ?? null;

        $groups = Group::findByCreator($userId);

        // Add statistics to each group
        $groupsWithStats = array_map(function($group) {
            $data = $group->toArray();
            $data['statistics'] = $group->getStatistics();
            return $data;
        }, $groups);

        Response::success($groupsWithStats);
    }

    /**
     * Get group by share code
     */
    public static function getByCode(array $params): void {
        $shareCode = strtoupper($params['code'] ?? '');

        if (empty($shareCode)) {
            Response::error('Share code is required', 400);
            return;
        }

        $group = Group::findByShareCode($shareCode);

        if (!$group) {
            Response::notFound('Group not found or inactive');
            return;
        }

        $data = $group->toArray();
        $data['statistics'] = $group->getStatistics();

        // Get creator info
        $creator = User::findById($group->getCreatorId());
        if ($creator) {
            $data['creator'] = [
                'name' => $creator->getName(),
                'picture_url' => $creator->getPictureUrl()
            ];
        }

        Response::success($data);
    }

    /**
     * Get group participants
     */
    public static function getParticipants(array $params): void {
        $groupId = $params['id'] ?? 0;
        $auth = $GLOBALS['auth'] ?? null;

        // Verify user has access to this group
        $hasAccess = false;

        if ($auth['type'] === 'user') {
            $group = Group::findById($groupId);
            if ($group && $group->getCreatorId() === $auth['user_id']) {
                $hasAccess = true;
            }
        } else if ($auth['type'] === 'participant') {
            $participant = Participant::findById($auth['participant_id']);
            if ($participant && $participant->getGroupId() == $groupId) {
                $hasAccess = true;
            }
        }

        if (!$hasAccess) {
            Response::forbidden('You do not have access to this group');
            return;
        }

        // Get participants
        $participants = Participant::findByGroup($groupId);
        $participantData = array_map(fn($p) => $p->toArray(), $participants);

        // Get creator as well
        $group = Group::findById($groupId);
        $creator = User::findById($group->getCreatorId());

        $data = [
            'creator' => $creator ? [
                'id' => $creator->getId(),
                'name' => $creator->getName(),
                'picture_url' => $creator->getPictureUrl(),
                'role' => 'creator'
            ] : null,
            'participants' => $participantData
        ];

        Response::success($data);
    }

    /**
     * Update group
     */
    public static function update(array $params): void {
        $request = new Request();
        $body = $request->getBody();
        $groupId = $params['id'] ?? 0;
        $userId = $GLOBALS['auth']['user_id'] ?? null;

        // Find group
        $group = Group::findById($groupId);
        if (!$group) {
            Response::notFound('Group not found');
            return;
        }

        // Check if user is creator
        if ($group->getCreatorId() !== $userId) {
            Response::forbidden('Only the creator can update this group');
            return;
        }

        // Validate input
        if (isset($body['name'])) {
            $validator = new Validator($body);
            $validator->min('name', 3, 'Group name must be at least 3 characters')
                      ->max('name', 100, 'Group name must not exceed 100 characters');

            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
                return;
            }

            $group->setName($body['name']);
        }

        if (isset($body['is_active'])) {
            $group->setActive((bool)$body['is_active']);
        }

        if (!$group->save()) {
            Response::error('Failed to update group', 500);
            return;
        }

        Response::success($group->toArray(), 'Group updated successfully');
    }

    /**
     * Delete group
     */
    public static function delete(array $params): void {
        $groupId = $params['id'] ?? 0;
        $userId = $GLOBALS['auth']['user_id'] ?? null;

        // Find group
        $group = Group::findById($groupId);
        if (!$group) {
            Response::notFound('Group not found');
            return;
        }

        // Check if user is creator
        if ($group->getCreatorId() !== $userId) {
            Response::forbidden('Only the creator can delete this group');
            return;
        }

        // Delete all photos physically (the model handles this)
        if (!$group->delete()) {
            Response::error('Failed to delete group', 500);
            return;
        }

        Response::success(null, 'Group deleted successfully');
    }
}