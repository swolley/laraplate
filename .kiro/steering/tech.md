# Tech Stack

## Core Technologies

| Layer | Technology | Version |
|-------|-----------|---------|
| Language | PHP | >= 8.5 |
| Framework | Laravel | ^12.0 |
| Admin Panel | Filament | ^5.0 |
| Frontend | Livewire | ^4.0 |
| CSS | Tailwind CSS | ^4.0 |
| Build Tool | Vite | — |
| Auth | Laravel Sanctum | ^4.0 |
| Modules | nwidart/laravel-modules | ^12.0 |
| Testing | PestPHP | ^4.0 |
| Static Analysis | PHPStan / Larastan | — |
| Code Style | Laravel Pint | — |
| Refactoring | Rector | — |

## Key Libraries
- `coolsam/modules` ^5.0 — module management companion
- `filament/spatie-laravel-media-library-plugin` — media handling in Filament
- `wikimedia/composer-merge-plugin` — merges module `composer.json` files into root
- `fakerphp/faker` — test data generation
- `laravel/sail` — Docker dev environment

## Database
Maintain compatibility across **MySQL, PostgreSQL, SQLite, and Oracle**. Always use Eloquent ORM abstractions; avoid database-specific SQL.

## Cache & Queues
- Cache: support `database`, `file`, and `array` drivers — no driver-specific features
- Queues: use extensively for background work (embeddings, media processing, emails, AI tasks)

---

## Common Commands

### Development
```bash
composer dev          # Start server + queue + logs + Vite concurrently
composer setup        # Fresh install: composer, .env, key, migrate, npm build
```

### Testing
```bash
composer test                 # Full pipeline: type-coverage, unit, lint, types, refactor, licenses
composer test:unit            # Pest with coverage (target: 100%)
composer test:type-coverage   # Type coverage (target: 100%)
composer test:types           # PHPStan static analysis
composer test:lint            # Pint + Rector dry-run
composer test:typos           # Peck typo check

# Run specific tests (prefer this for speed)
php artisan test --compact --filter=SomeTest
```

### Code Quality
```bash
composer lint         # Rector + Pint + IDE helper generation
composer check        # PHPStan analysis
composer refactor     # Rector automated refactoring
vendor/bin/pint --dirty   # Format only changed files (run before finalizing)
```

### Artisan
```bash
php artisan module:install {Name}   # Install and activate a module
php artisan make:test --pest {Name} # Create a Pest test
php artisan make:model {Name} -mfs  # Model + migration + factory + seeder
```

### Versioning
```bash
composer version:major / version:minor / version:patch
```

### Frontend
```bash
npm run dev     # Vite dev server
npm run build   # Production build (required after frontend changes)
```
