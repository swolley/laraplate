---
inclusion: always
---

# Project Structure

## Root
```
laraplate/
├── app/                    # Thin shell — minimal host app
│   ├── Console/Kernel.php
│   ├── Http/Controllers/   # Only AppController + base Controller
│   ├── Http/Middleware/
│   ├── Http/Kernel.php     # Middleware registration
│   ├── Models/, Policies/
│   ├── Providers/          # AppServiceProvider, RouteServiceProvider, etc.
│   └── View/
├── Modules/                # All domain code lives here
│   ├── Core/               # Priority 0
│   ├── Cms/                # Priority 1
│   ├── AI/
│   └── ERP/                # Priority 999
├── config/, database/, resources/, routes/, tests/
├── modules_statuses.json   # Enable/disable modules
└── composer.json           # Merges all Modules/*/composer.json
```

## Module Layout
```
Modules/{Name}/
├── app/
│   ├── Actions/, Casts/, Console/, Contracts/, Events/
│   ├── Filament/Resources/
│   ├── Http/Controllers/, Http/Requests/
│   ├── Jobs/, Listeners/, Models/, Observers/
│   ├── Providers/{Name}ServiceProvider.php
│   │              EventServiceProvider.php
│   │              RouteServiceProvider.php
│   ├── Rules/, Services/
│   └── Helpers/helpers.php
├── config/config.php
├── database/factories/, migrations/, seeders/
├── lang/, resources/views/
├── routes/web.php, routes/api.php
├── tests/Feature/, tests/Unit/, tests/Pest.php, tests/TestCase.php
├── module.json, composer.json, phpstan.neon, pint.json, rector.php
```

## Conventions

### Namespaces
- Host: `App\`
- Modules: `Modules\{Name}\` → `Modules/{Name}/app/`
- Tests: `Modules\{Name}\Tests\Feature\` / `Unit\`

### Registration
- `module.json` — name, alias, priority, providers
- `modules_statuses.json` — active/inactive
- `wikimedia/composer-merge-plugin` — merges module composer.json

### Routing
- Split by concern: `web.php`, `api.php`, `auth.php`, `crud.php`
- Named routes + `route()` — never hardcode URLs

### App Structure (Laravel 10 layout — do not migrate)
- Middleware → `app/Http/Middleware/` + `app/Http/Kernel.php`
- Exceptions → `app/Exceptions/Handler.php`
- Schedule → `app/Console/Kernel.php`

### Class Rules
- Controllers: `final`, no property mutations, method injection
- Models: `final`
- Services: `final readonly`
- Static-safe closures/functions: `static`

### Migrations
- Live in module's `database/migrations/`
- When modifying column: re-declare ALL existing attributes
