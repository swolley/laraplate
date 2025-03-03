<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
// use Rector\Laravel\Set\LaravelSetList;
use Rector\ValueObject\PhpVersion;
use Rector\Set\ValueObject\SetList;
use RectorLaravel\Set\LaravelSetList;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    // Rileva automaticamente tutti i moduli
    $modules = array_filter(glob(__DIR__ . '/Modules/*'), 'is_dir');
    $paths = array_merge(
        [__DIR__ . '/app'],
        array_map(fn($module) => "$module/app", $modules),
        array_map(fn($module) => "$module/tests", $modules),
    );

    $rectorConfig->paths($paths);

    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        LaravelSetList::LARAVEL_110,  // Per migrare a Laravel 11
        // LaravelSetList::LARAVEL_120,  // Per migrare a Laravel 12
        LevelSetList::UP_TO_PHP_84,   // Per supporto PHP 8.4
    ]);

    // Skip directories e files specifici
    $rectorConfig->skip([
        __DIR__ . '/vendor',
        __DIR__ . '/storage',
        __DIR__ . '/Modules/*/vendor',
        __DIR__ . '/Modules/*/node_modules',
        // Aggiungi altri pattern da escludere se necessario
    ]);

    // Imposta il livello PHP target
    $rectorConfig->phpVersion(PhpVersion::PHP_84);
};
