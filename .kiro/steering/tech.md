---
inclusion: always
---

# Tech Stack

| Layer | Tech | Version |
|-------|------|---------|
| Language | PHP | >= 8.5 |
| Framework | Laravel | ^12.0 |
| Admin | Filament | ^5.0 |
| Frontend | Livewire | ^4.0 |
| CSS | Tailwind | ^4.0 |
| Build | Vite | — |
| Auth | Sanctum | ^4.0 |
| Modules | nwidart/laravel-modules | ^12.0 |
| Testing | PestPHP | ^4.0 |
| Analysis | PHPStan/Larastan | — |
| Style | Pint | — |
| Refactor | Rector | — |

## Key Libs
- `coolsam/modules` ^5.0
- `filament/spatie-laravel-media-library-plugin`
- `wikimedia/composer-merge-plugin`
- `fakerphp/faker`
- `laravel/sail`

## DB
MySQL/PostgreSQL/SQLite/Oracle — Eloquent only, no DB-specific SQL.

## Cache & Queues
- Cache: `database`/`file`/`array` only
- Queues: use heavily for slow ops

## Commands

### Dev
```bash
composer dev      # server + queue + logs + vite
composer setup    # fresh install
```

### Testing
```bash
composer test                # full pipeline
composer test:unit           # Pest + coverage
composer test:type-coverage  # 100% type coverage
composer test:types          # PHPStan
composer test:lint           # Pint + Rector dry-run
composer test:typos          # typo check
php artisan test --compact --filter=SomeTest  # targeted
```

### Quality
```bash
composer lint           # Rector + Pint + IDE helper
composer check          # PHPStan
composer refactor       # Rector
vendor/bin/pint --dirty # format changed files only
```

### Artisan
```bash
php artisan module:install {Name}
php artisan make:test --pest {Name}
php artisan make:model {Name} -mfs
```

### Frontend
```bash
npm run dev    # Vite dev
npm run build  # production build
```
