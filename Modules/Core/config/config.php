<?php

declare(strict_types=1);

return [
    'name' => 'Core',

    /**
     * optimistic locking table column.
     */
    'locking' => [
        'lock_version_column' => env('LOCKIN_LOCK_VERSION_COLUMN', 'lock_version'),
        'lock_at_column' => env('LOCKIN_LOCK_AT_COLUMN', 'locked_at'),
        'lock_by_column' => env('LOCKIN_LOCK_BY_COLUMN', 'locked_user_id'),
        'unlock_allowed' => env('LOCKIN_UNLOCK_ALLOWED', true),
        'can_be_unlocked' => explode(',', env('LOCKING_CAN_BE_UNLOCKED', '')),
        'prevent_modifications_on_locked_objects' => env('LOCKING_PREVENT_MODIFICATIONS_ON_LOCKED', false),
        'prevent_notifications_to_locked_objects' => env('LOCKING_PREVENT_MODIFICATIONS_TO_LOCKED', false),
    ],

    'dynamic_entities' => env('ENABLE_DYNAMIC_ENTITIES', false),
    'expose_crud_api' => env('EXPOSE_CRUD_API', false),
    'enable_user_licenses' => env('ENABLE_USER_LICENSE', false),
    'force_https' => env('FORCE_HTTPS', false),
];
