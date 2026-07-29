# Filament Environment Indicator Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace `pxlrbt/filament-environment-indicator` with Core plugin + module version dropdown.

**Architecture:** `ModuleVersionCatalog` + Filament plugin + Blade/Alpine; wire in `AdminPanelProvider`.

**Tech stack:** Laravel 12, Filament 5, Pest, Core module views (`core::`).

---

### Task 1: Catalog DTO + service (TDD)

**Files:**
- Create: `Modules/Core/app/Filament/Data/ModuleVersionEntry.php`
- Create: `Modules/Core/app/Filament/Services/ModuleVersionCatalog.php`
- Create: `Modules/Core/tests/Unit/Filament/ModuleVersionCatalogTest.php`

### Task 2: Plugin + Blade views

**Files:**
- Create: `Modules/Core/app/Filament/Plugins/EnvironmentIndicatorPlugin.php`
- Create: `Modules/Core/resources/views/filament/environment-indicator/badge.blade.php`
- Create: `Modules/Core/resources/views/filament/environment-indicator/debug-mode-warning.blade.php`
- Create: `Modules/Core/tests/Unit/Filament/EnvironmentIndicatorPluginTest.php`

### Task 3: Wire + remove package

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `composer.json`, `Modules/Core/composer.json`
- Delete: `public/css/filament-environment-indicator/styles.css` (and dir if empty)
- Modify: `Modules/Core/README.md`, RAG MODULE note, specs INDEX

### Task 4: Verify

- Run catalog + plugin Pest tests
- `vendor/bin/pint --dirty`
