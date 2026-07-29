# Filament environment indicator (custom) — design

## Goal

Replace `pxlrbt/filament-environment-indicator` with a Core-owned Filament plugin that:

1. Shows an environment badge (color by `APP_ENV`) for **super-admins only**.
2. Shows a **Debug Mode** warning badge when the app is in production and debug is enabled.
3. On badge click, opens a dropdown listing **App** plus every installed module under `Modules/`, each with its Composer `version`, with **disabled** modules styled gray.

## Non-goals

- Git branch on the badge.
- Colored topbar border.
- Visibility for non–super-admin users.
- Editing module enablement from the dropdown.

## Architecture

| Unit | Responsibility |
|------|----------------|
| `ModuleVersionCatalog` | Build ordered list: App first, then modules A–Z; version from each `composer.json`; enabled via Nwidart (`Module::isEnabled`) / activator semantics for modules; App always enabled. |
| `ModuleVersionEntry` | Readonly DTO: `name`, `version`, `enabled`, `isApp`. |
| `EnvironmentIndicatorPlugin` | Filament `Plugin`: visibility gate, render hooks for badge + debug warning. |
| Blade views under `core::filament.environment-indicator.*` | Badge (Alpine dropdown) + debug warning badge. |

Wiring: `AdminPanelProvider` registers Core plugin; remove `pxlrbt` from root and Core `composer.json`.

## UX

- Badge label: `ucfirst(app()->environment())`.
- Colors: production red, staging orange, local/development blue, else pink.
- Dropdown: `App  vX.Y.Z` then modules; disabled rows use muted gray text.
- Missing `version` in composer → show `unknown`.

## Testing

- Unit: catalog order, App first, disabled flag, version parsing (incl. missing file).
- Feature/unit: plugin visibility only for super-admin; debug warning only when production+debug.

## Docs

- Update Core README dependency list.
- Short note in Core RAG MODULE Filament section if present.
