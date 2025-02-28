<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
// use Rector\Laravel\Set\LaravelSetList;
use Rector\Set\ValueObject\SetList;
use RectorLaravel\Set\LaravelSetList;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/app',
        __DIR__ . '/Modules',
    ]);

    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        LaravelSetList::LARAVEL_110,  // Per migrare a Laravel 11
        // LaravelSetList::LARAVEL_120,  // Per migrare a Laravel 12
        LevelSetList::UP_TO_PHP_84,   // Per supporto PHP 8.4
    ]);

    // Skip alcuni file/directory se necessario
    $rectorConfig->skip([
        __DIR__ . '/vendor',
        __DIR__ . '/storage',
    ]);
};
