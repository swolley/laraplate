---
inclusion: always
---

# Coding Principles

## General
- Clean, readable, simple code. No over-engineering.
- SOLID principles.
- No `else` when avoidable — early return instead.
- No code duplication — iterate/modularize.
- Descriptive names with aux verbs (`isLoading`, `hasError`).
- Self-documenting code. PHPDoc over inline comments.
- No inline comments unless very complex logic.
- Comments/PHPDoc: English only.
- Commit messages: conventional commit format.
- TDD always.

## Changes & Fixes
- Plan before coding.
- One file at a time.
- Don't touch unrelated files.
- Explain root cause before fixing.

## Language
- Chat: Italian. Code/comments: English.
- Ask if requirements unclear.

## Workflow
- `vendor/bin/pint --dirty` before done
- `composer test` before deploy
- `composer refactor` for automated improvements
- `composer test:type-coverage` — 100% target
- `composer test:types` — PHPStan
- `composer test:lint` — Pint
