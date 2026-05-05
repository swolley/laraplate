# Project Structure

## Root Layout

```
laraplate/
├── app/                        # Minimal host application (thin shell)
│   ├── Console/Kernel.php
│   ├── Http/
│   │   ├── Controllers/        # Only AppController + base Controller
│   │   ├── Middleware/
│   │   └── Kernel.php          # Middleware registration (Laravel 10 structure)
│   ├── Models/
│   ├── Policies/
│   ├── Providers/              # AppServiceProvider, RouteServiceProvider, etc.
│   └── View/
├── Modules/                    # All feature code lives here
│   ├── Core/                   # Priority 0 — loads first
│   ├── Cms/                    # Priority 1
│   ├── AI/                     # AI/ML features
│   └── Business/               # Priority 999 — loads last (ERP, WIP)
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── resources/
├── routes/
├── tests/
├── modules_statuses.json       # Enable/disable modules here
└── composer.json               # Merges all Modules/*/composer.json via merge-plugin
```

> The host `app/` is intentionally thin. All domain logic belongs in a module.

---

## Module Structure

Every module follows this standard layout (each is an independent git submodule):

```
Modules/{Name}/
├── app/
│   ├── Actions/
│   ├── Casts/
│   ├── Console/
│   ├── Contracts/
│   ├── Events/
│   ├── Filament/
│   │   └── Resources/
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Requests/           # Form Request classes for all validation
│   ├── Jobs/
│   ├── Listeners/
│   ├── Models/
│   ├── Observers/
│   ├── Providers/
│   │   ├── {Name}ServiceProvider.php
│   │   ├── EventServiceProvider.php
│   │   └── RouteServiceProvider.php
│   ├── Rules/
│   ├── Services/
│   └── Helpers/helpers.php     # Global helper functions (autoloaded)
├── config/config.php           # Accessed via config('{alias}.key')
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── lang/
├── resources/views/
├── routes/
│   ├── web.php
│   └── api.php
├── tests/
│   ├── Feature/
│   ├── Unit/
│   ├── Pest.php
│   └── TestCase.php
├── module.json                 # Name, alias, priority, providers
├── composer.json               # Module dependencies (merged into root)
├── phpstan.neon
├── pint.json
└── rector.php
```

---

## Key Conventions

### Namespaces
- Host app: `App\`
- Modules: `Modules\{Name}\` (maps to `Modules/{Name}/app/`)
- Module tests: `Modules\{Name}\Tests\Feature\` / `Modules\{Name}\Tests\Unit\`

### Module Registration
- `module.json` declares the module name, alias, priority, and service providers
- `modules_statuses.json` at root controls which modules are active
- Module `composer.json` files are auto-merged into root via `wikimedia/composer-merge-plugin`

### Routing
- Split route files by concern: `web.php`, `api.php`, `auth.php`, `crud.php`
- Use named routes and the `route()` helper — never hardcode URLs

### Laravel 12 App Structure
This project uses the **Laravel 12 directory structure** (not the newer streamlined layout):
- Middleware → `app/Http/Middleware/` + registered in `app/Http/Kernel.php`
- Exception handling → `app/Exceptions/Handler.php`
- Console schedule → `app/Console/Kernel.php`
- Do not migrate to the new structure unless explicitly requested

### Class Design Rules
- Controllers: `final` classes, no property mutations, use method injection
- Models: `final` classes
- Services: `final` and `readonly`, contain business logic
- All static-safe functions/closures must be declared `static`

### Migrations
- Always live inside the owning module's `database/migrations/`
- When modifying a column, re-declare **all** existing column attributes to avoid data loss
