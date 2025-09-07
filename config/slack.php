<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Slack Application Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options specific to the Slack application
    |
    */

    'team' => [
        'max_members' => env('TEAM_MAX_MEMBERS', 100),
        'slug_min_length' => env('TEAM_SLUG_MIN_LENGTH', 3),
        'slug_max_length' => env('TEAM_SLUG_MAX_LENGTH', 50),
        'avatar_max_size' => env('TEAM_AVATAR_MAX_SIZE', 2048), // KB
    ],

    'invitation' => [
        'expiration_hours' => env('INVITATION_EXPIRATION_HOURS', 72),
        'max_pending_per_team' => env('INVITATION_MAX_PENDING', 50),
    ],

    'activity_log' => [
        'enabled' => env('ACTIVITY_LOG_ENABLED', true),
        'retention_days' => env('ACTIVITY_LOG_RETENTION_DAYS', 365),
        'export_formats' => ['csv', 'json', 'xlsx'],
    ],

    'channels' => [
        'max_per_team' => env('CHANNELS_MAX_PER_TEAM', 50),
        'name_min_length' => env('CHANNEL_NAME_MIN_LENGTH', 1),
        'name_max_length' => env('CHANNEL_NAME_MAX_LENGTH', 21),
    ],

    'messages' => [
        'max_length' => env('MESSAGE_MAX_LENGTH', 4000),
        'edit_time_limit' => env('MESSAGE_EDIT_TIME_LIMIT', 300), // seconds
        'pagination_limit' => env('MESSAGE_PAGINATION_LIMIT', 50),
    ],

    'uploads' => [
        'max_file_size' => env('UPLOAD_MAX_FILE_SIZE', 10240), // KB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'],
        'storage_disk' => env('UPLOAD_STORAGE_DISK', 'local'),
    ],

];
