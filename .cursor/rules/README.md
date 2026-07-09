# Cursor Rules (caveman)

- Folder has rule files for Cursor behavior.
- `laravel-boost.mdc` is main law.
- `00-master.mdc` is map.
- Other files are context rules by glob.
- This README is a human/navigation index, not an agent rule file.

## Apply Mode

- `alwaysApply: true` => always active.
- `globs: [...]` => active only on matching files.

## Rule Set

- `00-master.mdc` entrypoint + key defaults.
- `01-php-laravel-standards.mdc` PHP/Blade code style/type rules.
- `02-architecture-patterns.mdc` controllers/services/models/repositories architecture.
- `03-performance-optimization.mdc` cache/queue/db performance.
- `04-error-handling-security.mdc` error handling + security + validation.
- `05-testing-development.mdc` Pest testing workflow.
- `06-coding-principles.mdc` always-on coding principles.
- `07-laraplate-specific.mdc` module architecture context.
- `08-versioning.mdc` semantic module versioning workflow.
- `09-database-guidelines.mdc` database migrations, queries, indexes, multi-DB compatibility (MySQL, MariaDB, PostgreSQL, Oracle, SQLite).
- `laravel-boost.mdc` ecosystem source of truth.

## Maintenance

- Keep files short.
- No duplicate rules across files.
- If rule already in `laravel-boost.mdc`, reference it.
- Keep language policy: chat IT, code docs EN.

## Codex Compatibility

- Codex should start from `AGENTS.md`.
- Load this folder through `00-master.mdc` and relevant `.mdc` files only.
- Do not treat README content as additional law.
