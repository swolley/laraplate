# Agent Rules (caveman)

## Source Of Truth

- Use existing rules. Do not recreate unless missing.
- Main rules:
  - `CLAUDE.md`
  - `.cursor/rules/00-master.mdc`
  - `.cursor/rules/laravel-boost.mdc`
  - Relevant `.cursor/rules/*.mdc`
  - `.kiro/steering/*.md` only for Kiro specs or when Cursor rules miss needed context.
- Specs:
  - Read `docs/superpowers/specs/INDEX.md` first when present.
  - Read `docs/superpowers/plans/INDEX.md` first when present.
  - Read `.cursor/plans/INDEX.md` first when present.
  - Read `.kiro/specs/INDEX.md` first when present.
  - Open only relevant specs/plans after using indexes or search.
- Module context:
  - `Modules/*/README.md`
  - `Modules/*/.cursor/rules/module-context.mdc`
  - `Modules/*/.cursor/mcp.json`

## Load Policy

- Cursor uses `.cursor/rules/*.mdc` frontmatter to decide rule inclusion.
- Codex starts from `AGENTS.md`, then loads only relevant rules/specs/plans.
- `README.md` and `INDEX.md` files are navigation aids, not extra law.
- Do not bulk-load all specs/plans. Use `rg` and indexes, then open the smallest relevant section/file.
- If two rule files duplicate each other, keep the stricter or more local rule.

## MCP

- Root MCP config:
  - `.mcp.json`
  - `.cursor/mcp.json`
- Module MCP config:
  - `Modules/*/.cursor/mcp.json`
- Existing module example:
  - `Modules/AI/.cursor/mcp.json`
- Before work in a module, check its local MCP config.
- If an MCP is not exposed as an active tool in this Codex session, read config as reference and say the tool is unavailable.
- Do not assume root MCP covers module-specific MCP.

## Always

- Chat in Italian.
- Code, PHPDoc, comments, docs content in English unless user asks otherwise.
- Rules use caveman syntax to save tokens: short bullets, few words, no fluff. Never trade clarity, precision, completeness.
- Follow existing code style and sibling files.
- Touch only files related to current task.
- No new dependencies without user approval.
- No new base folders without user approval.
- Create docs only when user asks.
- Feature implementations must update affected user/operator and developer RAG docs when the behavior is worth documenting. Use the docs of the module where the change was made; cross-module work updates each affected module's own docs. Added, removed, renamed, or behavior-changing env vars must be documented in the affected module README using the existing style. Patch-only fixes, formatting, narrow tests, and internal refactors can skip RAG docs, but state that judgment when finishing.

## Project

- Laravel modular app.
- Stack: PHP 8.5+, Laravel 12, Filament 5, Livewire 4, Sanctum 4, Tailwind 4.
- Laravel 10-style structure is intentional. Do not migrate.
- Modules use `nwidart/laravel-modules`.
- `Core` is foundation. Other modules reuse Core contracts/services.
- Module dependencies must be explicit in `module.json`.
- Table names are prefixed by lowercase module name.

## PHP

- Every PHP file: `declare(strict_types=1);`.
- Always braces for control structures.
- Explicit param types and return types.
- Prefer constructor property promotion.
- Use `#[Override]` when overriding.
- Use `final`, `readonly`, `static` where existing rules require.
- Never remove `final`, `readonly`, `static` to make tests easier.
- Need mocking? Use interfaces or Mockery.
- Prefer PHPDoc over inline comments.
- Keep comments rare and useful.

## Laravel

- Laravel way first.
- Use `php artisan make:* --no-interaction` for framework files.
- Controllers thin.
- Business logic in services/repositories/actions matching local pattern.
- Use Form Requests for validation.
- Use policies/gates/Sanctum for auth.
- Eloquent first. Avoid raw SQL unless justified.
- Prevent N+1 with eager loading.
- Use queues for heavy work.
- Use `config()` outside config files, not `env()`.
- Prefer named routes and `route()`.

## Testing

- Every code change needs automated test proof.
- Prefer Pest and feature tests unless unit test fits better.
- Keep tests inside module `tests/` for module work.
- Use factories/states for setup.
- Never declare classes, traits, interfaces, or enums inside test files; put them in that module's `Modules/{Module}/tests/Stubs/` (or module equivalent like `tests/Support/`) with PSR-4 namespaces registered in that module's `composer.json` `autoload-dev`.
- Run minimal relevant tests:
  - `php artisan test --compact path/or/filter`
- Before done after code changes:
  - `vendor/bin/pint --dirty`

## Performance

- Cache hot data with Laravel cache helpers.
- Use queues for slow work.
- Add indexes when query pattern needs.
- Use transactions for multi-step consistency.
- Prefer portable Eloquent/query builder over vendor SQL.

## Security

- Validate input.
- Authorize actions.
- Keep CSRF/XSS protections.
- Log useful context, never secrets.
- Fail safe with clear expected error handling.

## Frontend

- Tailwind v4 syntax only.
- Use `@import "tailwindcss"`.
- Livewire components need one root element.
- Validate and authorize Livewire actions.
- Use `wire:key` in loops.
- If UI change not visible, mention `npm run build`, `npm run dev`, or `composer run dev`.

## Before Work

- Read relevant Cursor/Kiro/module rules first.
- If working in `Modules/{Name}`, read:
  - `Modules/{Name}/README.md`
  - `Modules/{Name}/.cursor/rules/module-context.mdc`
  - `Modules/{Name}/.cursor/mcp.json` when present
- Check sibling files before editing.
- For Laravel ecosystem docs, use Laravel Boost docs tool when available.
- If tool unavailable, rely on local rules and installed code.

## Before Final

- Summarize changed files.
- State tests/format commands run.
- If not run, say why.

@RTK.md
