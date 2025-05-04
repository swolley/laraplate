<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
// use Rector\Laravel\Set\LaravelSetList;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;

// use Rector\Set\ValueObject\SetList;
// use RectorLaravel\Set\LaravelSetList;
// use Rector\Set\ValueObject\LevelSetList;

$modules = array_filter(glob(__DIR__ . '/Modules/*'), 'is_dir');
$paths = array_merge(
    [__DIR__ . '/app'],
    array_map(fn ($module) => "{$module}/app", $modules),
    // array_map(fn($module) => "$module/tests", $modules),
);

return RectorConfig::configure()
    ->withSkip([
        AddOverrideAttributeToOverriddenMethodsRector::class,
        __DIR__ . '/vendor',
        __DIR__ . '/node_modules',
        __DIR__ . '/storage',
        __DIR__ . '/Modules/*/vendor',
        __DIR__ . '/Modules/*/node_modules',
        // Pattern per qualsiasi file che potrebbe avere conflitti di namespace con Model
        '**/Model.php',
        // Ignora file con troppe righe che potrebbero causare problemi di analisi
        '**/vendor/**',
    ])
    ->withPaths($paths)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        strictBooleans: true,
        // LaravelSetList::LARAVEL_120,  // Per migrare a Laravel 12
    )
    ->withPhpSets(
        php84: true,
    )
    ->withPhpVersion(PhpVersion::PHP_84);
