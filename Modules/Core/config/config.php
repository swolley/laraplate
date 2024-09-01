<?php

declare(strict_types=1);

return [
    'name' => 'Core',

    /**
     * optimistic locking table column.
     */
    'locking' => [
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'lock_version_column' => env('LOCKIN_LOCK_VERSION_COLUMN', 'lock_version'),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'lock_at_column' => env('LOCKIN_LOCK_AT_COLUMN', 'locked_at'),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'lock_by_column' => env('LOCKIN_LOCK_BY_COLUMN', 'locked_user_id'),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'unlock_allowed' => env('LOCKIN_UNLOCK_ALLOWED', true),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'can_be_unlocked' => explode(',', env('LOCKING_CAN_BE_UNLOCKED', '')),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'prevent_modifications_on_locked_objects' => env('LOCKING_PREVENT_MODIFICATIONS_ON_LOCKED', false),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'prevent_notifications_to_locked_objects' => env('LOCKING_PREVENT_MODIFICATIONS_TO_LOCKED', false),
    ],

    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'dynamic_entities' => env('ENABLE_DYNAMIC_ENTITIES', false),
    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'dynamic_gridutils' => env('ENABLE_DYNAMIC_GRIDUTILS', false),
    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'expose_crud_api' => env('EXPOSE_CRUD_API', false),
    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'enable_user_licenses' => env('ENABLE_USER_LICENSE', false),
    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'force_https' => env('FORCE_HTTPS', false),

    'extended_class_suffix' => '_extended',
];
