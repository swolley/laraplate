---
inclusion: fileMatch
fileMatchPattern: ['**/*.php', '**/*.blade.php']
---

# PHP & Laravel Standards

## Deps
- PHP 8.5+, Laravel 12+, nwidart/laravel-modules ^12, filament ^5, sanctum ^4

## PHP Rules
- `declare(strict_types=1);` always
- Curly braces always, even single-line
- Constructor property promotion
- Explicit return types + param typehints
- `readonly` when value never changes after init
- `#[Override]` when overriding parent
- `static` on closures/functions with no external refs
- PHP 8.5 features: match, enums, named args, union/intersection types

## Naming
- Classes: PascalCase (`UserController`)
- Methods/public props: camelCase (`getUserById`)
- Local vars/private props: snake_case (`$user_id`)
- DB columns: snake_case
- Constants: UPPER_SNAKE_CASE
- Enum cases: PascalCase (`Monthly`)
- Models: singular. Controllers: plural (`UsersController`)

## Code Style
- PSR-12
- SOLID principles
- No code duplication — iterate/modularize
- PHPDoc over inline comments. No inline comments unless very complex.
- PHPDoc/comments: English only
- Array shapes in PHPDoc when useful

## Static Analysis
- PHPStan suppress: `@phpstan-ignore` with specific error code + reason comment
- Pint suppress: inline comment + reason
- Use `@var`, `@param`, `@return` for complex types PHPStan can't infer
