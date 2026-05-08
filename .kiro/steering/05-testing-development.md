---
inclusion: fileMatch
fileMatchPattern: ['**/tests/**', '**/*Test.php', '**/phpunit.xml', '**/pest.php']
---

# Testing

## Rules
- Every change needs a test. Write or update, then run.
- PestPHP only — `php artisan make:test --pest <name>`
- Most tests: Feature. Unit only when truly isolated.
- Tests live in `Modules/{Name}/tests/Feature/` or `Unit/`
- Use factories. Check for existing states before manual setup.
- Test happy path + failure path + edge cases.
- Run minimum tests needed: `php artisan test --compact --filter=X`

## `final` Classes
- NEVER remove `final` to make something testable.
- Instead:
  - Mock with `Mockery::mock(FinalClass::class)`
  - Extract interface → mock the interface
  - Create `Fake*`/`Stub*` implementing same interface
  - Test via public API (HTTP, service calls)

## Coverage
- Target: 100% type coverage (`composer test:type-coverage`)

## Tools
- Pint, PHPStan, Rector — see `laravel-boost.md`
- IDE Helper for autocomplete
