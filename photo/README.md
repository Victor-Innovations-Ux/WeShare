# WeShare Photo Storage

## Architecture

```
/photo/
├── .htaccess           # Security configuration (deny all direct access)
├── .gitignore          # Ignore uploaded files
├── groups/             # Photos organized by group
│   ├── {group_id}/
│   │   ├── originals/
│   │   │   └── {uuid}.{ext}
│   │   └── thumbnails/  (optional future feature)
│   │       └── {uuid}_thumb.jpg
```

## Security Features

1. **Path Traversal Protection**: FileUploadService validates all paths using realpath()
2. **MIME Type Validation**: Double-check (extension + finfo)
3. **Secure Filenames**: UUID v4 format (no original filenames preserved in storage)
4. **Group Isolation**: Each group has its own subdirectory
5. **No Direct Access**: .htaccess blocks all direct HTTP access
6. **No Script Execution**: PHP execution disabled in this directory
7. **Restrictive Permissions**: Files created with 0644, directories with 0755

## Access Control

All photo access MUST go through `/api/photos/download/:id` endpoint which:
- Validates authentication (JWT token)
- Checks authorization (user/participant belongs to group)
- Serves file with proper headers
- Logs access (if configured)

## File Storage Path

Photos are stored as:
`/photo/groups/{group_id}/originals/{uuid}.{ext}`

Database stores relative path:
`groups/{group_id}/originals/{uuid}.{ext}`

## Setup

1. Ensure `/photo` directory exists with write permissions
2. Set PHOTO_PATH in .env to point to this directory
3. Verify .htaccess is active (requires mod_rewrite and AllowOverride)

## Migration

To migrate from old `/uploads` structure, run:
```bash
php php-api/Migrations/migrate_photos.php
```

## Disk Space Management

- Max file size: 10MB per photo
- Recommended: Monitor disk usage and implement cleanup for deleted groups
- Future: Implement automatic compression and thumbnail generation
