<?php

namespace Api\Controllers;

use Models\Photo;
use Models\Group;
use Models\Participant;
use Services\FileUploadService;
use Lib\Request;
use Lib\Response;

class PhotoController {
    /**
     * Upload a photo
     */
    public static function upload(): void {
        $request = new Request();
        $auth = $GLOBALS['auth'] ?? null;

        // Get group ID from request
        $groupId = $request->getBody('group_id');
        if (!$groupId) {
            Response::error('Group ID is required', 400);
            return;
        }

        // Verify group exists
        $group = Group::findById($groupId);
        if (!$group || !$group->isActive()) {
            Response::notFound('Group not found or inactive');
            return;
        }

        // Verify user has access to this group
        $hasAccess = false;
        $uploaderId = null;
        $uploaderType = null;

        if ($auth['type'] === 'user') {
            // Check if user is the creator
            if ($group->getCreatorId() === $auth['user_id']) {
                $hasAccess = true;
                $uploaderId = $auth['user_id'];
                $uploaderType = 'user';
            }
        } else if ($auth['type'] === 'participant') {
            // Check if participant belongs to this group
            $participant = Participant::findById($auth['participant_id']);
            if ($participant && $participant->getGroupId() == $groupId) {
                $hasAccess = true;
                $uploaderId = $auth['participant_id'];
                $uploaderType = 'participant';
            }
        }

        if (!$hasAccess) {
            Response::forbidden('You do not have access to this group');
            return;
        }

        // Check if file was uploaded
        $file = $request->getFile('photo');
        if (!$file) {
            Response::error('No photo uploaded', 400);
            return;
        }

        // Use FileUploadService for secure upload
        $uploadService = new FileUploadService();
        $result = $uploadService->storeFile($file, $groupId);

        if (!$result['success']) {
            Response::error($result['error'], 400);
            return;
        }

        // Get MIME type for database
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_ENV['PHOTO_PATH'] . '/' . $result['path']);
        finfo_close($finfo);

        // Save to database
        $photo = new Photo([
            'group_id' => $groupId,
            'file_path' => $result['path'],
            'original_name' => $file['name'],
            'mime_type' => $mimeType,
            'file_size' => $file['size'],
            'uploaded_by_user_id' => $uploaderType === 'user' ? $uploaderId : null,
            'uploaded_by_participant_id' => $uploaderType === 'participant' ? $uploaderId : null
        ]);

        if (!$photo->save()) {
            // Delete uploaded file
            $uploadService->deleteFile($result['path']);
            Response::error('Failed to save photo information', 500);
            return;
        }

        Response::success($photo->toArray(), 'Photo uploaded successfully', 201);
    }

    /**
     * Get photos by group
     */
    public static function getByGroup(array $params): void {
        $groupId = $params['groupId'] ?? 0;
        $auth = $GLOBALS['auth'] ?? null;

        // Verify group exists
        $group = Group::findById($groupId);
        if (!$group) {
            Response::notFound('Group not found');
            return;
        }

        // Verify user has access to this group
        $hasAccess = false;

        if ($auth['type'] === 'user') {
            if ($group->getCreatorId() === $auth['user_id']) {
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

        // Get photos with uploader information
        $photos = Photo::findByGroupWithUploader($groupId);
        $photosData = array_map(fn($p) => $p->toArray(), $photos);

        Response::success($photosData);
    }

    /**
     * Delete a photo
     */
    public static function delete(array $params): void {
        $photoId = $params['id'] ?? 0;
        $auth = $GLOBALS['auth'] ?? null;

        // Find photo
        $photo = Photo::findById($photoId);
        if (!$photo) {
            Response::notFound('Photo not found');
            return;
        }

        // Check permissions
        $userId = $auth['type'] === 'user' ? $auth['user_id'] : null;
        $participantId = $auth['type'] === 'participant' ? $auth['participant_id'] : null;

        if (!$photo->canBeDeletedBy($userId, $participantId)) {
            Response::forbidden('You do not have permission to delete this photo');
            return;
        }

        // Delete photo (file and database record)
        if (!$photo->delete()) {
            Response::error('Failed to delete photo', 500);
            return;
        }

        Response::success(null, 'Photo deleted successfully');
    }

    /**
     * Download a photo
     */
    public static function download(array $params): void {
        $photoId = $params['id'] ?? 0;
        $auth = $GLOBALS['auth'] ?? null;

        // Find photo
        $photo = Photo::findById($photoId);
        if (!$photo) {
            Response::notFound('Photo not found');
            return;
        }

        // Get group to verify access
        $group = Group::findById($photo->getGroupId());
        if (!$group) {
            Response::notFound('Group not found');
            return;
        }

        // Verify user has access to this group
        $hasAccess = false;

        if ($auth['type'] === 'user') {
            if ($group->getCreatorId() === $auth['user_id']) {
                $hasAccess = true;
            }
        } else if ($auth['type'] === 'participant') {
            $participant = Participant::findById($auth['participant_id']);
            if ($participant && $participant->getGroupId() == $photo->getGroupId()) {
                $hasAccess = true;
            }
        }

        if (!$hasAccess) {
            Response::forbidden('You do not have access to this photo');
            return;
        }

        // Use FileUploadService for secure file access
        $uploadService = new FileUploadService();
        $filePath = $uploadService->getFilePath($photo->getFilePath());

        if (!$filePath) {
            Response::notFound('Photo file not found');
            return;
        }

        // Send file
        header('Content-Type: ' . $photo->getMimeType());
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename="' . $photo->getOriginalName() . '"');
        header('Cache-Control: max-age=86400');

        readfile($filePath);
        exit;
    }
}