# Filament Toggle inline default — Design

Date: 2026-07-29  
Status: approved

## Goal

Make Filament `Toggle` fields match other form fields by default: **label above, switch below**.

## Non-goals

- Changing Checkbox or other boolean field layouts.
- Per-resource form rewrites of every `Toggle::make(...)`.
- Changing form-level `inlineLabel()` behaviour.

## Filament concepts

| API | Meaning |
|-----|---------|
| `Toggle::inline(true\|false)` | Switch beside its field label vs label stacked above the switch |
| Schema/form `inlineLabel()` | Horizontal form layout: labels left, controls right |

When a form/container uses `inlineLabel()`, Filament already forces `Toggle::isInline()` to `false`, so the switch sits in the content column beside the label like other fields.

## Decision

Register a global default in Core boot:

```php
Toggle::configureUsing(fn (Toggle $toggle): Toggle => $toggle->inline(false));
```

- Applies to all existing and future Filament toggles.
- Forms with `inlineLabel()` remain correct (native Filament interaction).
- Local override still allowed: `Toggle::make('x')->inline(true)`.

## Tests

Feature test in Core: assert `Toggle` inside a stacked `Schema` has `isInline() === false`; with `inlineLabel()` still `isInline() === false` and `hasInlineLabel() === true`; explicit `->inline(true)` overrides the default when not using form inline labels.

## Docs

Patch-only UX default — no RAG documentation update.
