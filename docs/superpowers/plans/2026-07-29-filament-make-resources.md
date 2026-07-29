# Filament make-resources — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship `filament:make-resources` with permanent Filament ClassGenerator rebinds that inject Laraplate `HasTable` / `HasForm` / `HasRecords`, gated by `laraplate_owned`.

**Architecture:** Ownership via helper + Module macro; trait FQN from generation target; rebind Filament ClassGenerators in Core; command loops Coolsam/Filament makers.

**Tech Stack:** Laravel Artisan, Filament v4 ClassGenerators, Coolsam Modules, Pest, Nwidart Modules.

---

### Task 1: Ownership flag + API

- [x] Set `"laraplate_owned": true` manually on official `module.json` files (Core, CMS, ERP, AI, MES) — no stub / `module:make` change
- [x] Add `is_laraplate_owned_module()` (+ path helper for tests) and `Module::isLaraplateOwned()` macro
- [x] Normalize `modules()` `$filter` to receive module **name**
- [x] Tests for flag / composer fallback / App / explicit false
- [x] Run tests; pint

### Task 2: Trait resolver + ClassGenerator rebinds

- [x] `FilamentTraitResolver` (module Utils trait if present, else Core; App → Core)
- [x] Laraplate ClassGenerators for Resource / Table / Form / ListRecords
- [x] Bind in `CoreServiceProvider`
- [x] Tests (resolver unit; generators integration)
- [x] Run tests; pint

### Task 3: `filament:make-resources`

- [x] Console command + feature tests (owned reject; App; pivot filter)
- [x] Loop makers; continue on skip/collision failure
- [x] Run tests; pint

### Task 4: App panel discovery

- [x] `discoverResources` / `discoverPages` on `AdminPanelProvider`
- [x] Smoke/assert if cheap; pint

### Task 5: Docs handoff

- [x] Short Core RAG / README note for command + ownership
- [x] Mark plan tasks done

---

**Spec:** `docs/superpowers/specs/2026-07-29-filament-make-resources-design.md`
