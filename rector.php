<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\MethodCall\RemoveNullArgOnNullDefaultParamRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\Php85\Rector\Property\AddOverrideAttributeToOverriddenPropertiesRector;
use RectorLaravel\Set\LaravelSetList;
use RectorLaravel\Set\LaravelSetProvider;

$paths = array_merge(
    [
        __DIR__ . '/app',
        __DIR__ . '/bootstrap/app.php',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/public',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ],
    array_map(static fn ($module) => "{$module}/app", glob(__DIR__ . '/Modules/*', GLOB_ONLYDIR) ?: []),
);

return RectorConfig::configure()
    ->withSetProviders(LaravelSetProvider::class)
    ->withSets([
        LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL,
        LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
        LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER,
        LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES,
        LaravelSetList::LARAVEL_FACTORIES,
        LaravelSetList::LARAVEL_IF_HELPERS,
        LaravelSetList::LARAVEL_LEGACY_FACTORIES_TO_CLASSES,
    ])
    ->withComposerBased(laravel: true)
    ->withCache(
        cacheDirectory: '/tmp/rector',
        cacheClass: FileCacheStorage::class,
    )
    ->withPaths($paths)
    ->withSkip([
        AddOverrideAttributeToOverriddenMethodsRector::class,
        // Same defect as the methods rule above, on properties: it reads a property
        // coming from a trait as one inherited from a parent. A trait is copied into
        // the class, so there is no parent property, and PHP refuses to load it:
        //   Modules\CMS\Models\Content::$sortable has #[\Override] attribute,
        //   but no matching parent property exists
        // Verified 2026-09-17 on $sortable, which comes from Spatie's SortableTrait.
        AddOverrideAttributeToOverriddenPropertiesRector::class,
        // Turns where('col', null) into where('col'), which changes query semantics (e.g. soft-delete unique rules).
        RemoveNullArgOnNullDefaultParamRector::class,
        __DIR__ . '/vendor',
        __DIR__ . '/node_modules',
        __DIR__ . '/storage',
        // Pattern per qualsiasi file che potrebbe avere conflitti di namespace con Model
        '**/Model.php',
        // Ignora file con troppe righe che potrebbero causare problemi di analisi
        '**/vendor/**',
        '**/node_modules/**',
        '**/storage/**',
        // Esclude il file SortableTrait dalla regola di privatizzazione
        __DIR__ . '/Modules/Core/app/Models/Concerns/SortableTrait.php',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withPhpSets();
