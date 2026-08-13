# Modulo MES — Piano di implementazione completo

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Completare il modulo MES (Manufacturing Execution System) di Laraplate — da dominio produzione a API REST, Filament, test suite e documentazione — seguendo i vincoli in `Modules/MES/.cursor/rules/module-context.mdc` e i pattern ERP/Core del monorepo.

**Architecture:** Modulo Laravel (`Modules/MES`, submodule git) con dipendenza unidirezionale MES→ERP. Modelli MES con prefisso tabella `mes_`, trait `BelongsToCompany`, FK fisiche verso `items`, `warehouses`, `companies`, `sales_orders`. Movimenti magazzino via contratto `StockMovementRecorder` (adapter `ErpStockMovementRecorder`). Numerazione ordini produzione via `DocumentNumberAllocator` ERP (nuovo `DocumentType::ProductionOrder`). Servizi di dominio orchestrano snapshot BOM/Routing, backflush, tracciabilità lotti, qualità, capacità e OEE.

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Livewire 4, Sanctum 4, Pest 4, nwidart/laravel-modules, Tailwind 4.

**Decisioni bloccate:** `docs/superpowers/specs/2026-07-09-mes-module-decisions-design.md`

---

## Decisioni confermate (2026-07-09)

Dettaglio completo: `docs/superpowers/specs/2026-07-09-mes-module-decisions-design.md`

| # | Decisione | Scelta |
|---|-----------|--------|
| D1 | Scope | Modulo **completo** (tutti i task del piano) |
| D2 | Fonte requisiti | `module-context.mdc` + questo piano (Kiro rimosso) |
| D3 | Numerazione PO | `DocumentNumberAllocator` + `DocumentType::ProductionOrder` |
| D4 | Link SalesOrder | `sales_order_id` + `sales_order_line_id` (entrambi nullable, vincolo applicativo) |
| D5 | Backflush | Per operazione collegata via `bom_lines.routing_operation_id`; fallback ultima operazione se FK null |
| D6 | Turno operatore | Warning non bloccante; `OperatorLog` sempre |
| D7 | Testing | Pest + invarianti esplicite; no lib PBT |
| D8 | Git | Commit submodule `Modules/MES`; bump monorepo |
| D9 | DIFF audit | `ProductionOrder`, `Bom`, `Routing` |
| D10 | KPI | Materializzazione job + cache |

Ordine di consegna tecnico: dominio + test (Task 0–13) prima di API/Filament (Task 14–15), coerente con D1.

---

## Current Truth (stato codice al 2026-07-09)

| Task piano | Stato reale nel codice | Gap principale |
|------------|------------------------|----------------|
| 0 Baseline test | ⚠️ Parziale | Slug company >64 char in alcuni test; verificare suite |
| 1 Scaffolding | ✅ Fatto | TestCase/Pest ok; binding ok |
| 2 `tracing_type` | ✅ Migration + cast ERP | Test da stabilizzare |
| 3 Work Center | ✅ Modelli, migration, factory | Test unicità codice mancante |
| 4 BOM | ⚠️ Solo migration header/lines | Modelli, `routing_operation_id` su lines, service, test, lock |
| 5 Routing | ⚠️ Solo migration `mes_routings` | `mes_routing_operations`, modelli, service, test |
| 6 ProductionOrder | ⚠️ Solo migration | Dominio, `sales_order_line_id`, DocumentType ERP |
| 7–13 | ❌ Assente | Migration, modelli, servizi, job, observer |
| 14 API | ❌ `routes/api.php` vuoto | Controller, resources, auth |
| 15 Filament | ❌ Assente | 8 resources + widget |
| 16 Test suite | ⚠️ Parziale | Factory mancanti, E2E, type coverage |
| 17 Docs | ⚠️ Parziale | Mancano `MES_GUIDA_SEMPLICE.md`, `docs/rag/MODULE.md` |

**Test attuali:** `php artisan test Modules/MES/tests --compact` → 31 pass, 5 fail (baseline da sistemare in Task 0).

### Ri-verifica 2026-08-13

Stato codice del submodule `Modules/MES` (commit `1dccb8d`) **invariato** rispetto al 2026-07-09: il monorepo è avanzato solo su AI/Core/CMS/ERP. Gap confermati leggendo il codice:

- `mes_bom_lines` **senza** `routing_operation_id`; `mes_production_orders` **senza** `sales_order_line_id`; tabella `mes_routing_operations` **assente**; `DocumentType::ProductionOrder` **assente** in ERP.
- Modelli presenti: solo `WorkCenter`, `WorkCenterCalendar`. Servizi: solo `ErpStockMovementRecorder`. Nessun Job. Nessun Filament. `routes/api.php` vuoto.
- Enum presenti: `MESTables`, `WorkCenterType`.

Due aggiornamenti importanti emersi in questa sessione, dettagliati sotto:

1. **Task 14 (API) ridefinito** → niente 11 controller custom: si usano le rotte generiche di Core (CRUD + domain-action registry). Vedi sezione «API — architettura rivista (2026-08-13)» e il Task 14 aggiornato.
2. **Ambiente di esecuzione cloud** → il codice richiede **PHP 8.5** (usa `#[Override]` su proprietà) e Composer non può usare i dist di GitHub (proxy repo-scoped). Provisioning documentato nella sezione «Esecuzione in ambiente cloud».

### Avanzamento implementazione (2026-08-13)

Branch `claude/mes-pending-work-2sdtwr` su `swolley/laraplate-mes` (puntatore bumpato nel padre):

- ✅ **Task 5 — Routing**: `mes_routing_operations`, modelli `Routing`/`RoutingOperation`, `RoutingResolverService` (versione attiva per data + lock guard), factory, test. (`laraplate-mes` `a614087`)
- ✅ **Task 4 — BOM**: enum `ConsumptionMethod`, `routing_operation_id` su `mes_bom_lines`, modelli `Bom`/`BomLine`, `BomExplosionService` (esplosione multi-livello + lock guard), factory, test. (`laraplate-mes` `25ad38d`)
- ✅ **Task 6 — Production Order**: `DocumentType::ProductionOrder` in ERP (`laraplate-erp` `1d1587a`), `sales_order_line_id`, enum `ProductionOrderStatus`, modello `ProductionOrder`, `ProductionOrderService` (create con numero via `DocumentNumberAllocator` + snapshot BOM/routing immutabili, release/complete/cancel via `DomainException`), factory, test. (`laraplate-mes` `2893783`)
- ✅ **Task 7 — Operazioni**: `mes_production_order_operations`, enum `ProductionOrderOperationStatus`, modello, `ProductionOrderOperationService` (generazione da snapshot, start/complete/skip, efficienza standard/actual clampata, operazioni parallele), `release()` genera le operazioni, factory, test. (`laraplate-mes` `a1a3a9c`)
- ✅ **Task 8 — Backflush**: `mes_material_consumptions` (unique operation+item), modello `MaterialConsumption`, `BackflushMaterialsJob` (queued, idempotente, match linea↔operazione per D5, stock-out via `StockMovementRecorder`), dispatch su complete operazione, factory, test. (`laraplate-mes` `f0ffc67`)

- ✅ **Task 9 — Lotti/seriali**: `mes_lot_numbers`/`mes_serial_numbers`/`mes_lot_lineages`, modelli, `LotTracingService` (trace forward/backward BFS, generazione codice, lineage), lotto su `complete()` per item tracciati, factory, test. (`laraplate-mes` `fc5f9a9`)
- ✅ **Task 10 — Qualità/NC**: `mes_quality_checks`/`_measurements`/`mes_non_conformances`, 3 enum, modelli, `QualityCheckService` (valuta limiti → apre NC), `NonConformanceService` (resolve con disposition, rework → PO collegato, close), factory, test. (`laraplate-mes` `d7704ee`)
- ✅ **Task 11 — Capacità**: `CapacityService` (carico in minuti standard, schedule, stima completamento, overload, reschedule), test. Nessuna tabella nuova. (`laraplate-mes` `012fa0f`)
- ✅ **Task 12 — Fermi/OEE**: `mes_downtimes`, enum `DowntimeCause`, modello, `DowntimeService` (open/close + flag WC down), `OeeCalculatorService` (A×P×Q clampato [0,1]), factory, test. (`laraplate-mes` `816c178`)
- ✅ **Task 13 — Turni/operatori**: `mes_shifts`/`mes_shift_instances`/`mes_operator_logs`, enum `OperatorLogAction`, modelli, `ShiftVerificationService` (log operatore sempre, turno warning non-bloccante D6, efficienza media), log su start/complete operazione, factory, test. (`laraplate-mes` `53199d0`)

**Dominio MES (Task 4-13) COMPLETO.** Verifica applicata a ogni file: `php -l` (con strip di `#[Override]`, 8.5-only) + `pint`. **Test scritti ma non eseguiti** (container PHP 8.4). Da lanciare dall'app base su PHP 8.5.

**ERP:** `DocumentType::ProductionOrder` mergiato su `master` di `laraplate-erp` (`1d1587a`, fast-forward).

**Parti differite ai task API/Filament** (segnalate nei commit): pipeline `SalesOrderConfirmed` (evento/listener/job auto-creazione PO), consumo manuale ed evento stock-shortage (residuo Task 8), quality-check auto su complete operazione (opzionale). Restano i Task 14 (API domain-action/policy), 15 (Filament), 16 (test hardening/E2E), 17 (docs).

**Riferimenti obbligatori prima di ogni task:**

- Decisioni: `docs/superpowers/specs/2026-07-09-mes-module-decisions-design.md`
- Modello esistente: `Modules/MES/app/Models/WorkCenter.php`
- Contratto stock: `Modules/MES/app/Contracts/StockMovementRecorder.php`
- Numerazione ERP: `Modules/ERP/app/Services/Accounting/DocumentNumberAllocator.php`
- Filament pattern: `Modules/Core/app/Filament/Resources/Users/UserResource.php`

---

## API — architettura rivista (2026-08-13)

Il piano originale (Task 14) prevedeva ~11 controller REST custom sotto `api/v1/mes`. **Superato**: Core espone già un CRUD generico e un registry di domain-action per qualsiasi entità, quindi MES **non dichiara rotte né controller CRUD**.

**Coperto gratis dal CRUD generico Core** (rotte in `Modules/Core/routes/crud.php`, controller `CrudController`, risoluzione entità per convenzione via `DynamicEntity`/`models()` scan — nessun registry):

```
/app/crud/{select|detail|search|insert|update|delete|history|facets|tree}/mes/{entity}
```

Ogni modello in `Modules/MES/app/Models/` è auto-scoperto. I permessi per-tabella (`{conn}.{table}.{op}`) li genera il comando globale `permission:refresh`. Esposizione esterna `/api/v1` gated dal flag `core.expose_crud_api` (nessun lavoro per-modello).

**Verbi di dominio via registry** (rotta unica `POST /app/crud/{action}/{module}/{entity}`, dispatcher `DomainActionDispatcher`, registry `DomainActionRegistry` — pattern ERP in `ErpDomainActionRegistrar` + `ERPModelPolicy`):

| Entità | Verbi |
|--------|-------|
| production-orders | `release`, `complete`, `cancel` |
| operations | `start`, `complete`, `skip` |
| quality-checks | `execute`, `disposition` |
| non-conformances | `resolve`, `close` |
| downtimes | `open`, `close` |
| boms | `explode` (record-scoped) |
| lot-numbers | `forward_trace`, `backward_trace` |

Nessuno collide coi verbi riservati Core (approve/lock/…) → `OverridesGenericCrudActions` non serve. Il backflush **non è una rotta**: interno, scatenato dall'observer sul complete operazione.

**Cosa MES deve aggiungere (Task 14 aggiornato):** `MesDomainActionRegistrar`, `MesModelPolicy` (un metodo camelCase per verbo, predicato-di-stato + `hasPermission`), wiring in `MESServiceProvider::boot()` (`Gate::policy` + `register`), e seeding dei permessi domain in `MESDatabaseSeeder` (`permission:refresh` **non** li crea). Nessuna rotta.

**Genuinamente non coperto** (read aggregati senza singolo record id): OEE, carico/schedule capacità, dashboard produzione. **Decisione presa 2026-08-13:** esporli **solo come widget Filament** (Task 15), niente rotte read custom.

Mappatura completa e riferimenti file: report in-session (Core `CrudController`/`DynamicEntity`/`DomainActionRegistry`, ERP `ErpDomainActionRegistrar`/`ERPModelPolicy`).

---

## Esecuzione in ambiente cloud (Claude Code on the web)

Vincoli scoperti il 2026-08-13 provisionando l'ambiente; **il codice non gira senza questi passi**:

1. **PHP 8.5 obbligatorio.** Il codice usa `#[Override]` su proprietà (feature 8.5). Il container base ha PHP 8.4 → l'app non fa boot (`Attribute "Override" cannot target property`). Installare `php8.5` + estensioni (`mbstring xml curl intl gd zip bcmath gmp sqlite3 pgsql mysql redis soap`) dal PPA ondrej. Richiede **network access = Full** (o Custom con `ppa.launchpadcontent.net` in allowlist): il PPA è altrimenti bloccato dal proxy (403).
2. **Composer non può usare i dist GitHub.** Il proxy GitHub è repo-scoped e indipendente dal livello di rete: gli archivi `api.github.com`/`codeload` danno 403. Installare **da sorgente** (`composer install --prefer-source`): il `git clone` dei repo pubblici funziona. Unico pacchetto senza source è **`phpstan/phpstan`** (dist-only): costruire il suo archivio da un `git clone` del tag e puntare il lock a un file locale, ripristinando il lock dopo l'install.
3. **Submodule.** `git submodule update --init --recursive`; per pushare il codice MES serve attaccare `swolley/laraplate-mes` alla sessione (`add_repo` push).

Setup script pronto (idempotente, container cachato dopo la prima corsa):

```bash
set -uo pipefail
export DEBIAN_FRONTEND=noninteractive COMPOSER_ALLOW_SUPERUSER=1
cd "${CLAUDE_PROJECT_DIR:-$(pwd)}"

if ! command -v php8.5 >/dev/null 2>&1; then
  apt-get update
  apt-get install -y --no-install-recommends \
    php8.5-cli php8.5-common php8.5-mbstring php8.5-xml php8.5-curl \
    php8.5-intl php8.5-gd php8.5-zip php8.5-bcmath php8.5-gmp \
    php8.5-sqlite3 php8.5-pgsql php8.5-mysql php8.5-redis php8.5-soap
  update-alternatives --set php /usr/bin/php8.5 || true
fi

git submodule update --init --recursive
[ -f .env ] || cp .env.example .env

if [ ! -f vendor/autoload.php ]; then
  PHPSTAN_VER=$(php -r '$l=json_decode(file_get_contents("composer.lock"),true);foreach(array_merge($l["packages"],$l["packages-dev"]) as $p){if($p["name"]==="phpstan/phpstan"){echo $p["version"];break;}}')
  if [ -n "$PHPSTAN_VER" ]; then
    rm -rf /tmp/phpstan-src /tmp/phpstan.zip
    GIT_LFS_SKIP_SMUDGE=1 git clone --depth 1 --branch "$PHPSTAN_VER" https://github.com/phpstan/phpstan /tmp/phpstan-src
    git -C /tmp/phpstan-src archive --format=zip --prefix=phpstan-phpstan/ HEAD -o /tmp/phpstan.zip
    php -r '$p="composer.lock";$d=json_decode(file_get_contents($p),true);foreach(["packages","packages-dev"] as $s){foreach($d[$s] as &$pk){if($pk["name"]==="phpstan/phpstan"){$pk["dist"]=["type"=>"zip","url"=>"file:///tmp/phpstan.zip","reference"=>$pk["dist"]["reference"]??"","shasum"=>""];}}unset($pk);}file_put_contents($p,json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");'
  fi
  composer install --prefer-source --ignore-platform-reqs --no-interaction --no-progress
  git checkout -- composer.lock 2>/dev/null || true
fi

grep -q '^APP_KEY=base64:' .env || php artisan key:generate --no-interaction --force
```

**Nota di provenienza (2026-08-13):** in questa sessione web `vendor/` è stato installato con successo con questo metodo, ma i test **non** sono stati eseguibili perché il container aveva solo PHP 8.4 e la rete verso il PPA era chiusa. Nessun task d'implementazione (4–17) è quindi stato eseguito: vanno lavorati in una sessione con PHP 8.5 seguendo questo provisioning.

---

## File Structure Map

### Enums (`Modules/MES/app/Enums/`)

| File | Responsabilità |
|------|----------------|
| `MESTables.php` | Nomi tabella centralizzati (espandere) |
| `WorkCenterType.php` | ✅ Esiste |
| `ConsumptionMethod.php` | `backflush`, `manual` |
| `ProductionOrderStatus.php` | `draft`, `released`, `in_progress`, `completed`, `cancelled` |
| `ProductionOrderOperationStatus.php` | `planned`, `ready`, `in_progress`, `completed`, `skipped` |
| `QualityCheckStatus.php` | `pending`, `passed`, `failed`, `conditional` |
| `NonConformanceStatus.php` | `open`, `under_review`, `resolved`, `closed` |
| `NonConformanceDisposition.php` | `scrap`, `rework`, `use_as_is`, `return_to_supplier` |
| `DowntimeCause.php` | cause da design |
| `OperatorLogAction.php` | `started`, `completed`, `paused`, `resumed` |

### Modifiche ERP (solo dove richiesto da MES)

| File | Modifica |
|------|----------|
| `Modules/ERP/app/Casts/DocumentType.php` | Aggiungere `ProductionOrder` case |
| `Modules/ERP/app/Events/SalesOrderConfirmed.php` | Nuovo evento |
| `Modules/ERP/app/Models/Item.php` | ✅ `tracing_type` già presente |

### Test support

| File | Responsabilità |
|------|----------------|
| `Modules/MES/tests/Support/MesTestHelpers.php` | Factory helpers company/item/warehouse con slug bounded |

---

## Wave dependency graph

```
Task 0 (baseline)
  → Task 1–3 (verify/finish T1–T3)
  → Task 4 (BOM)
  → Task 5 (Routing)
  → Task 6 (ProductionOrder + ERP DocumentType)
  → Task 7 (Operations)
  → Task 8 (Material consumption + Backflush job)
  → Task 9 (Lots/serials)
  → Task 10 (Quality)
  → Task 11 (Capacity) ∥ Task 12 (Downtime/OEE) ∥ Task 13 (Shifts)
  → Task 14 (API)
  → Task 15 (Filament)
  → Task 16 (Test suite hardening)
  → Task 17 (Docs)
```

---

### Task 0: Baseline test verde

**Files:**
- Create: `Modules/MES/tests/Support/MesTestHelpers.php`
- Modify: `Modules/MES/tests/Feature/ItemTracingTypeTest.php`
- Modify: `Modules/MES/tests/Feature/WorkCenterModelTest.php`
- Modify: `Modules/MES/tests/Feature/WorkCenterCalendarModelTest.php`
- Modify: `Modules/MES/tests/Pest.php`

- [ ] **Step 1: Creare helper con slug bounded**

```php
<?php

declare(strict_types=1);

namespace Modules\MES\Tests\Support;

use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Warehouse;

final class MesTestHelpers
{
    public static function makeCompany(): Company
    {
        return Company::query()->withoutGlobalScopes()->create([
            'slug' => mb_substr(fake()->unique()->slug(), 0, 64),
            'name' => fake()->company(),
            'fiscal_country' => 'IT',
            'default_currency' => 'EUR',
        ]);
    }

    public static function makeItem(int $company_id): Item
    {
        return Item::query()->withoutGlobalScopes()->create([
            'company_id' => $company_id,
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('SKU-####'),
            'uom' => 'pcs',
            'costing_method' => 'fifo',
        ]);
    }

    public static function makeWarehouse(int $company_id): Warehouse
    {
        return Warehouse::query()->withoutGlobalScopes()->create([
            'company_id' => $company_id,
            'code' => fake()->unique()->bothify('WH-##'),
            'name' => fake()->words(2, true),
        ]);
    }
}
```

- [ ] **Step 2: Correggere import TracingType**

In `ItemTracingTypeTest.php` sostituire:

```php
use Modules\ERP\Enums\TracingType;
```

con:

```php
use Modules\ERP\Casts\TracingType;
```

- [ ] **Step 3: Usare helper nei test Feature**

In `WorkCenterModelTest.php`, `WorkCenterCalendarModelTest.php`, `ItemTracingTypeTest.php` sostituire helper locali con `MesTestHelpers::makeCompany()` e aggiungere in `Pest.php`:

```php
uses(Modules\MES\Tests\Support\MesTestHelpers::class);
```

(opzionale: funzioni globali `mesCompany()` che delegano al helper)

- [ ] **Step 4: Eseguire test baseline**

Run:

```bash
cd /srv/http/laraplate && php artisan test Modules/MES/tests --compact
```

Expected: 0 failures.

- [ ] **Step 5: Commit (submodule MES)**

```bash
cd Modules/MES && git add tests/Support/MesTestHelpers.php tests/Feature/ItemTracingTypeTest.php tests/Feature/WorkCenterModelTest.php tests/Feature/WorkCenterCalendarModelTest.php tests/Pest.php
git commit -m "test(mes): fix tracing type import and bounded company slug helpers"
```

---

### Task 1: Verifica scaffolding T1

**Files:**
- Verify: `Modules/MES/module.json`, `composer.json`, `app/Providers/*`, `app/Contracts/StockMovementRecorder.php`, `app/Services/ErpStockMovementRecorder.php`
- Verify root: `modules_statuses.json` contiene `"MES": true`

- [ ] **Step 1: Verificare modulo abilitato**

Run:

```bash
cd /srv/http/laraplate && php artisan module:list | rg MES
```

Expected: MES enabled.

- [ ] **Step 2: Verificare binding contratto**

Run:

```bash
cd /srv/http/laraplate && php artisan test Modules/MES/tests/Feature/ServiceProviderBindingTest.php --compact
```

Expected: PASS.

- [ ] **Step 3: Commit solo se mancano file (già presenti → skip)**

---

### Task 2: Completare T2 tracing_type

**Files:**
- Modify: `Modules/MES/tests/Feature/ItemTracingTypeTest.php` (copertura relazione MES→Item)
- Verify: `Modules/MES/database/migrations/2026_05_08_000000_add_tracing_type_to_items_table.php`

- [ ] **Step 1: Aggiungere test relazione Eloquent**

```php
it('reads tracing_type from ERP item via Eloquent', function (): void {
    $company = MesTestHelpers::makeCompany();
    $item = MesTestHelpers::makeItem($company->id);
    $item->update(['tracing_type' => TracingType::Lot]);

    $fresh = Item::query()->withoutGlobalScopes()->findOrFail($item->id);

    expect($fresh->tracing_type)->toBe(TracingType::Lot);
});
```

- [ ] **Step 2: Run test file**

Run:

```bash
cd /srv/http/laraplate && php artisan test Modules/MES/tests/Feature/ItemTracingTypeTest.php --compact
```

Expected: PASS.

- [ ] **Step 3: Commit**

```bash
cd Modules/MES && git commit -am "test(mes): cover ERP item tracing_type reads"
```

---

### Task 3: Completare T3 Work Center

**Files:**
- Create: `Modules/MES/tests/Feature/WorkCenterCrudTest.php`
- Modify: `Modules/MES/app/Http/Requests/WorkCenterRequest.php` (se manca unique per company)

- [ ] **Step 1: Scrivere test unicità codice per company**

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\MES\Enums\WorkCenterType;
use Modules\MES\Models\WorkCenter;
use Modules\MES\Tests\Support\MesTestHelpers;

uses(RefreshDatabase::class);

it('rejects duplicate work center code within same company', function (): void {
    $company = MesTestHelpers::makeCompany();

    WorkCenter::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'WC-DUP',
        'name' => 'First',
        'type' => WorkCenterType::Machine->value,
        'capacity_per_hour' => 10,
        'capacity_uom' => 'pcs',
    ]);

    expect(fn () => WorkCenter::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'WC-DUP',
        'name' => 'Second',
        'type' => WorkCenterType::Machine->value,
        'capacity_per_hour' => 10,
        'capacity_uom' => 'pcs',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('deactivates work center', function (): void {
    $company = MesTestHelpers::makeCompany();
    $wc = WorkCenter::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'WC-OFF',
        'name' => 'To deactivate',
        'type' => WorkCenterType::Machine->value,
        'capacity_per_hour' => 10,
        'capacity_uom' => 'pcs',
        'is_active' => true,
    ]);

    $wc->update(['is_active' => false]);

    expect($wc->fresh()->is_active)->toBeFalse();
});
```

- [ ] **Step 2: Run test**

Run:

```bash
cd /srv/http/laraplate && php artisan test Modules/MES/tests/Feature/WorkCenterCrudTest.php Modules/MES/tests/Feature/WorkCenterModelTest.php --compact
```

Expected: PASS.

- [ ] **Step 3: Commit**

```bash
cd Modules/MES && git add tests/Feature/WorkCenterCrudTest.php && git commit -m "test(mes): work center uniqueness and deactivation"
```

---

### Task 4: Distinta base (BOM)

**Files:**
- Modify: `Modules/MES/app/Enums/MESTables.php`
- Create: `Modules/MES/app/Enums/ConsumptionMethod.php`
- Create: `Modules/MES/database/migrations/2026_05_08_000007_add_routing_operation_id_to_mes_bom_lines_table.php`
- Create: `Modules/MES/app/Models/Bom.php`
- Create: `Modules/MES/app/Models/BomLine.php`
- Create: `Modules/MES/app/Services/BomExplosionService.php`
- Create: `Modules/MES/app/Exceptions/BomLockedException.php`
- Create: `Modules/MES/database/factories/BomFactory.php`
- Create: `Modules/MES/database/factories/BomLineFactory.php`
- Create: `Modules/MES/tests/Feature/BomExplosionServiceTest.php`

**Decision D5:** `mes_bom_lines.routing_operation_id` nullable FK → `mes_routing_operations` (migration in this task; FK enforced after Task 5 creates routing operations table — use deferred FK or add column in Task 5 if ordering requires).

- [ ] **Step 1: Migration `routing_operation_id` on bom lines**

```php
Schema::table(MESTables::BomLines->value, function (Blueprint $table): void {
    $table->foreignId('routing_operation_id')
        ->nullable()
        ->after('consumption_method')
        ->constrained(MESTables::RoutingOperations->value)
        ->nullOnDelete();
});
```

> **Ordering note:** if `mes_routing_operations` does not exist yet, add this column in Task 5 instead, or create routing_operations migration before this ALTER.

- [ ] **Step 2: Espandere MESTables e ConsumptionMethod**

```php
// Modules/MES/app/Enums/ConsumptionMethod.php
<?php

declare(strict_types=1);

namespace Modules\MES\Enums;

enum ConsumptionMethod: string
{
    case Backflush = 'backflush';
    case Manual = 'manual';

    public static function validationRule(): string
    {
        return 'in:' . implode(',', array_column(self::cases(), 'value'));
    }
}
```

Aggiungere a `MESTables.php` i case già usati dalle migration (`Boms`, `BomLines` già presenti).

- [ ] **Step 3: Scrivere test fallente esplosione multi-livello**

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\MES\Services\BomExplosionService;
use Modules\MES\Tests\Support\MesTestHelpers;

uses(RefreshDatabase::class);

it('explodes multi-level bom quantities', function (): void {
    $company = MesTestHelpers::makeCompany();
    $finished = MesTestHelpers::makeItem($company->id);
    $semi = MesTestHelpers::makeItem($company->id);
    $raw = MesTestHelpers::makeItem($company->id);

    // parent bom: 1 finished = 2 semi
    $parent_bom = \Modules\MES\Models\Bom::factory()->create([
        'company_id' => $company->id,
        'item_id' => $finished->id,
        'valid_from' => now()->subDay()->toDateString(),
    ]);
    \Modules\MES\Models\BomLine::factory()->create([
        'bom_id' => $parent_bom->id,
        'item_id' => $semi->id,
        'quantity' => 2,
        'uom' => 'pcs',
    ]);

    // child bom: 1 semi = 3 raw
    $child_bom = \Modules\MES\Models\Bom::factory()->create([
        'company_id' => $company->id,
        'item_id' => $semi->id,
        'valid_from' => now()->subDay()->toDateString(),
    ]);
    \Modules\MES\Models\BomLine::factory()->create([
        'bom_id' => $child_bom->id,
        'item_id' => $raw->id,
        'quantity' => 3,
        'uom' => 'pcs',
    ]);

    $lines = resolve(BomExplosionService::class)->explode($finished->id, 10, now());

    $raw_line = collect($lines)->firstWhere('item_id', $raw->id);
    expect($raw_line)->not->toBeNull()
        ->and($raw_line['quantity'])->toEqual(60.0); // 10 * 2 * 3
});
```

- [ ] **Step 4: Run test → FAIL**

Run:

```bash
cd /srv/http/laraplate && php artisan test Modules/MES/tests/Feature/BomExplosionServiceTest.php --compact
```

Expected: FAIL (class not found).

- [ ] **Step 5: Implementare modelli Bom e BomLine**

Seguire pattern `WorkCenter.php`:

- `final class`, `BelongsToCompany`, `MESTables` per `$table`
- Relazioni: `Bom` → `item()` BelongsTo ERP Item, `bomLines()` HasMany
- `BomLine` → `bom()`, `item()`, `routingOperation()` BelongsTo nullable
- `getRules()` con validazione version, date, quantity > 0

- [ ] **Step 6: Implementare BomExplosionService**

```php
<?php

declare(strict_types=1);

namespace Modules\MES\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\MES\Models\Bom;
use Modules\MES\Models\ProductionOrder;

final class BomExplosionService
{
    /**
     * @return list<array{item_id: int, quantity: float, uom: string, consumption_method: string, level: int}>
     */
    public function explode(int $item_id, float $quantity, CarbonInterface $on_date, int $level = 0): array
    {
        $bom = $this->getActiveBom($item_id, $on_date);
        if ($bom === null) {
            return [];
        }

        $result = [];
        foreach ($bom->bomLines as $line) {
            $line_qty = (float) $line->quantity * $quantity;
            $child_bom = $this->getActiveBom($line->item_id, $on_date);
            if ($child_bom !== null) {
                array_push($result, ...$this->explode($line->item_id, $line_qty, $on_date, $level + 1));
                continue;
            }
            $result[] = [
                'item_id' => $line->item_id,
                'quantity' => $line_qty,
                'uom' => $line->uom,
                'consumption_method' => $line->consumption_method->value,
                'level' => $level,
            ];
        }

        return $result;
    }

    public function getActiveBom(int $item_id, CarbonInterface $on_date): ?Bom
    {
        return Bom::query()
            ->where('item_id', $item_id)
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $on_date)
            ->where(function ($q) use ($on_date): void {
                $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $on_date);
            })
            ->orderByDesc('valid_from')
            ->first();
    }

    public function assertNotLocked(Bom $bom): void
    {
        $released = ProductionOrder::query()
            ->where('status', '!=', 'draft')
            ->where('bom_snapshot->id', $bom->id)
            ->exists();

        if ($released) {
            throw new \Modules\MES\Exceptions\BomLockedException("BOM {$bom->id} is locked by a released production order.");
        }
    }
}
```

Nota: `ProductionOrder` e campo snapshot arrivano in Task 6; fino ad allora stubbare test lock in Task 6.

- [ ] **Step 7: Factory Bom/BomLine + run test PASS**

- [ ] **Step 8: Commit**

```bash
cd Modules/MES && git add app/Models/Bom.php app/Models/BomLine.php app/Services/BomExplosionService.php app/Enums/ConsumptionMethod.php database/factories/BomFactory.php database/factories/BomLineFactory.php tests/Feature/BomExplosionServiceTest.php
git commit -m "feat(mes): add BOM models and multi-level explosion service"
```

---

### Task 5: Routing e operazioni — T5

**Files:**
- Create: `Modules/MES/database/migrations/2026_05_08_000006_create_mes_routing_operations_table.php`
- Modify: `Modules/MES/app/Enums/MESTables.php` (add `RoutingOperations`)
- Create: `Modules/MES/app/Models/Routing.php`
- Create: `Modules/MES/app/Models/RoutingOperation.php`
- Create: `Modules/MES/app/Services/RoutingResolverService.php`
- Create: `Modules/MES/app/Exceptions/RoutingLockedException.php`
- Create: factories + `Modules/MES/tests/Feature/RoutingResolverServiceTest.php`

- [ ] **Step 1: Migration routing operations**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\MES\Enums\MESTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = MESTables::RoutingOperations->value;
        Schema::create($table_name, function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('routing_id')->constrained(MESTables::Routings->value, 'id', "{$table_name}_routing_id_FK")->cascadeOnDelete();
            $table->foreignId('work_center_id')->constrained(MESTables::WorkCenters->value, 'id', "{$table_name}_work_center_id_FK")->restrictOnDelete();
            $table->integer('sequence');
            $table->string('description', 255);
            $table->integer('setup_time_minutes')->default(0);
            $table->decimal('cycle_time_minutes', 10, 4)->default(0);
            $table->boolean('is_parallel')->default(false);
            $table->unique(['routing_id', 'sequence'], "{$table_name}_routing_sequence_UNIQUE");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(MESTables::RoutingOperations->value);
    }
};
```

- [ ] **Step 2: Test risoluzione versione attiva + operazioni parallele**

```php
it('resolves active routing by date', function (): void {
    $company = MesTestHelpers::makeCompany();
    $item = MesTestHelpers::makeItem($company->id);

    $old = \Modules\MES\Models\Routing::factory()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'version' => 'v1',
        'valid_from' => now()->subYear()->toDateString(),
        'valid_to' => now()->subMonth()->toDateString(),
    ]);
    $new = \Modules\MES\Models\Routing::factory()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'version' => 'v2',
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => null,
    ]);

    $resolved = resolve(\Modules\MES\Services\RoutingResolverService::class)
        ->getActiveRouting($item->id, now());

    expect($resolved?->id)->toBe($new->id)->not->toBe($old->id);
});
```

- [ ] **Step 3: Implementare modelli + RoutingResolverService** (stesso pattern BOM: `getActiveRouting()`, `assertNotLocked()`)

- [ ] **Step 4: Run migrate + test**

Run:

```bash
cd /srv/http/laraplate && php artisan migrate --path=Modules/MES/database/migrations --no-interaction
php artisan test Modules/MES/tests/Feature/RoutingResolverServiceTest.php --compact
```

- [ ] **Step 5: Commit**

---

### Task 6: Ordini di produzione — T6

**Files:**
- Modify: `Modules/ERP/app/Casts/DocumentType.php` (add `ProductionOrder`)
- Create: `Modules/MES/database/migrations/2026_05_10_000002_add_sales_order_line_id_to_mes_production_orders_table.php`
- Create: `Modules/MES/app/Enums/ProductionOrderStatus.php`
- Create: `Modules/MES/app/Models/ProductionOrder.php`
- Create: `Modules/MES/app/Services/ProductionOrderService.php`
- Create: `Modules/MES/app/Observers/ProductionOrderObserver.php`
- Create: `Modules/ERP/app/Events/SalesOrderConfirmed.php`
- Create: `Modules/MES/app/Listeners/HandleSalesOrderConfirmedListener.php`
- Create: `Modules/MES/app/Jobs/CreateProductionOrderFromSalesOrderJob.php`
- Modify: `Modules/MES/app/Providers/EventServiceProvider.php`
- Create: `Modules/MES/tests/Feature/ProductionOrderServiceTest.php`

- [ ] **Step 1: Aggiungere DocumentType ProductionOrder in ERP**

```php
// In Modules/ERP/app/Casts/DocumentType.php add case:
case ProductionOrder = 'production_order';

// In defaultGapAllowed():
self::ProductionOrder => true,
```

- [ ] **Step 2: Test snapshot immutabile**

```php
it('freezes bom and routing snapshots on create', function (): void {
    // setup item with bom+routing active
    $order = resolve(\Modules\MES\Services\ProductionOrderService::class)->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'quantity_planned' => 5,
        'uom' => 'pcs',
        'planned_start_at' => now(),
        'planned_end_at' => now()->addDay(),
        'warehouse_id' => $warehouse->id,
    ]);

    expect($order->bom_snapshot)->toBeArray()->toHaveKey('lines')
        ->and($order->routing_snapshot)->toBeArray()->toHaveKey('operations');

    // mutate live bom after create
    $bom->bomLines()->delete();

    $order->refresh();
    expect($order->bom_snapshot['lines'])->not->toBeEmpty();
});
```

- [ ] **Step 3: Implementare ProductionOrderService**

Metodi richiesti:

```php
public function create(array $payload): ProductionOrder;
public function release(ProductionOrder $order): ProductionOrder;
public function complete(ProductionOrder $order, float $quantity_produced, ?string $lot_code = null): ProductionOrder;
public function cancel(ProductionOrder $order): ProductionOrder;
```

Regole implementative:

- `create()`: alloca numero con `DocumentNumberAllocator::next($company, DocumentType::ProductionOrder, (int) now()->format('Y'))`, snapshot JSON da `BomExplosionService` + routing operations ordinate
- `release()`: solo da `draft`; genera `ProductionOrderOperation` per ogni voce snapshot; status → `released`
- `complete()`: verifica operazioni non `in_progress`; aggiorna qty; lot handling in Task 9
- `cancel()`: solo da `draft|released`

- [ ] **Step 4: Observer transizioni stato** — log/eventi dominio tipizzati (`ProductionOrderReleased`, ecc.)

- [ ] **Step 5: SalesOrderConfirmed pipeline**

```php
// Modules/ERP/app/Events/SalesOrderConfirmed.php
final class SalesOrderConfirmed
{
    public function __construct(public readonly \Modules\ERP\Models\SalesOrder $salesOrder) {}
}

// Listener dispatches CreateProductionOrderFromSalesOrderJob on config flag
```

- [ ] **Step 6: Test release genera operazioni + unicità numero**

- [ ] **Step 7: Commit (MES + ERP DocumentType/event)**

---

### Task 7: Esecuzione operazioni — T7

**Files:**
- Create: migration `mes_production_order_operations`
- Create: `ProductionOrderOperation` model + enum status
- Create: `ProductionOrderOperationService` (start/complete/skip)
- Create: `ProductionOrderOperationObserver`
- Create: `Modules/MES/tests/Feature/ProductionOrderOperationServiceTest.php`

- [ ] **Step 1: Migration** (schema design.md righe 235–251)

- [ ] **Step 2: Test efficienza**

```php
it('calculates efficiency as actual over standard percent', function (): void {
    // standard = setup 10 + cycle 2 * qty 5 = 20 min; actual 30 min => 66.67%
});
```

Formula: `(standard_minutes / actual_minutes) * 100`, clamp 0–999.99.

- [ ] **Step 3: Implementare start/complete con warning capacità non bloccante**

- [ ] **Step 4: Observer on completed** — dispatch `BackflushMaterialsJob` (Task 8), crea QualityCheck pending se piano (Task 10)

- [ ] **Step 5: Commit**

---

### Task 8: Consumo materiali — T8

**Files:**
- Create: migration `mes_material_consumptions`
- Create: `MaterialConsumption` model
- Create: `Modules/MES/app/Jobs/BackflushMaterialsJob.php`
- Create: `Modules/MES/app/Http/Requests/MaterialConsumptionRequest.php`
- Create: `Modules/MES/tests/Feature/BackflushMaterialsJobTest.php`

- [ ] **Step 1: Test backflush crea consumption + invoca recorder**

Mock `StockMovementRecorder` con `Mockery::mock` e assert `record()` chiamato con `direction=out`.

- [ ] **Step 2: Implementare job**

```php
final class BackflushMaterialsJob implements ShouldQueue
{
    public function __construct(public readonly int $production_order_operation_id) {}

    public function handle(StockMovementRecorder $recorder): void
    {
        // 1. Load operation + order + sequence position
        // 2. Select snapshot bom lines where consumption_method = backflush AND:
        //    - routing_operation_id matches this operation's routing_operation_id, OR
        //    - routing_operation_id is null AND this is the last operation in sequence
        // 3. Skip lines already backflushed for this (operation_id, item_id) — idempotent
        // 4. Create MaterialConsumption, invoke recorder (direction=out), compute variance
        // 5. On insufficient stock: record consumption with stock_shortage=true, dispatch StockShortageDetected
    }
}
```

Queue: `config('mes.queue.connection')`, `config('mes.queue.name')`.

See decision D5 in `docs/superpowers/specs/2026-07-09-mes-module-decisions-design.md`.

- [ ] **Step 3: Consumo manuale via Form Request + service method**

- [ ] **Step 4: Commit**

---

### Task 9: Tracciabilità lotti — T7/R7

**Files:**
- Create: migrations `mes_lot_numbers`, `mes_serial_numbers`, `mes_lot_lineages`
- Create: models `LotNumber`, `SerialNumber`, `LotLineage`
- Create: `LotTracingService`
- Modify: `ProductionOrderService::complete()` per generazione lotto
- Create: `Modules/MES/tests/Feature/LotTracingServiceTest.php`

- [ ] **Step 1: Test forward/backward trace simmetrico**

```php
it('forward and backward traces are symmetric', function (): void {
    // parent -> child lineage
    $forward = $service->forwardTrace($parent->id);
    $backward = $service->backwardTrace($child->id);
    expect($forward)->toContain($child->id);
    expect($backward)->toContain($parent->id);
});
```

- [ ] **Step 2: `generateLotCode()` usando `config('mes.lot_number_format')`** — uncomment keys in `config/config.php`

- [ ] **Step 3: Integrate complete() when Item.tracing_type = lot|serial**

- [ ] **Step 4: Commit**

---

### Task 10: Qualità e non conformità — T8/R8

**Files:**
- Create: 5 migrations quality_*
- Create: 5 models
- Create: `QualityCheckService`
- Create: `NonConformanceService` con creazione PO rilavorazione
- Create: `Modules/MES/tests/Feature/QualityCheckFlowTest.php`

- [ ] **Step 1: Test failed check → NonConformance**

- [ ] **Step 2: Implementare execute check con measurements + limits**

- [ ] **Step 3: Rework production order when disposition=rework**

- [ ] **Step 4: Commit**

---

### Task 11: Scheduling e capacità — T9

**Files:**
- Create: `Modules/MES/app/Services/CapacityService.php`
- Create: `Modules/MES/tests/Feature/CapacityServiceTest.php`

- [ ] **Step 1: Test CapacityLoad >= 0**

- [ ] **Step 2: Implementare metodi**

```php
public function getCapacityLoad(int $work_center_id, \DateTimeInterface $from, \DateTimeInterface $to): float;
public function getSchedule(int $company_id, \DateTimeInterface $from, \DateTimeInterface $to): Collection;
public function estimateCompletionDate(ProductionOrder $order): \DateTimeInterface;
public function checkOverload(int $work_center_id, \DateTimeInterface $at): bool;
public function rescheduleOperation(ProductionOrderOperation $operation, int $work_center_id, \DateTimeInterface $planned_start_at): ProductionOrderOperation;
```

- [ ] **Step 3: Commit**

---

### Task 12: Fermi macchina e OEE — T11

**Files:**
- Create: migration `mes_downtimes`, model, enum cause
- Create: `OeeCalculatorService`
- Create: `Modules/MES/tests/Feature/OeeCalculatorServiceTest.php`

- [ ] **Step 1: Test OEE in [0,1] con dati noti**

```php
// Availability=0.9, Performance=0.8, Quality=0.95 => OEE=0.684
expect($service->calculate($wc_id, $from, $to))->toBeBetween(0.0, 1.0);
```

- [ ] **Step 2: Downtime close calcola duration_minutes**

- [ ] **Step 3: Active downtime flag su WorkCenter per CapacityService**

- [ ] **Step 4: Commit**

---

### Task 13: Turni e operatori — T10

**Files:**
- Create: 4 migrations shifts*
- Create: models `Shift`, `ShiftInstance`, `OperatorLog`
- Create: `ShiftVerificationService`
- Modify: `ProductionOrderOperationService` per log automatico
- Create: `Modules/MES/tests/Feature/ShiftOperatorTest.php`

- [ ] **Step 1: Test OperatorLog on start/complete**

- [ ] **Step 2: ShiftInstance warning (non blocking) se assente**

- [ ] **Step 3: Efficienza media per operatore/turno**

- [ ] **Step 4: Commit**

---

### Task 14: API REST — T13/R13

> **⚠️ RIVISTO 2026-08-13 — leggere prima la sezione «API — architettura rivista».**
> Niente controller/resources/rotte custom: si usano le rotte generiche di Core (CRUD + domain-action registry). Il vero lavoro di questo task è: `MesDomainActionRegistrar`, `MesModelPolicy`, wiring in `MESServiceProvider::boot()`, seeding permessi domain in `MESDatabaseSeeder`, verifica esposizione entità. Il blocco «Files/Step» sottostante (controller REST) è **obsoleto** e va ignorato; resta come storia della stima originaria.

**Files (OBSOLETO — vedi sopra):**
- Modify: `Modules/MES/routes/api.php`
- Modify: `Modules/MES/app/Providers/RouteServiceProvider.php` (prefix `api/v1/mes`, middleware `auth:sanctum`, throttle)
- Modify: `Modules/MES/config/config.php` (uncomment `rate_limit`)
- Create: `Modules/MES/app/Http/Controllers/Api/V1/*.php` (11 controller)
- Create: `Modules/MES/app/Http/Resources/*.php`
- Create: `Modules/MES/app/Http/Requests/*Request.php` per write endpoints
- Create: `Modules/MES/tests/Feature/Api/WorkCenterApiTest.php` (+ uno per controller principale)

- [ ] **Step 1: Base JsonResource envelope**

```php
<?php

declare(strict_types=1);

namespace Modules\MES\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\MES\Models\WorkCenter */
final class WorkCenterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'capacity_per_hour' => $this->capacity_per_hour,
            'capacity_uom' => $this->capacity_uom,
            'is_active' => $this->is_active,
        ];
    }
}
```

- [ ] **Step 2: Routes (estratto)**

```php
Route::prefix('v1/mes')->middleware(['auth:sanctum', 'throttle:mes'])->group(function (): void {
    Route::apiResource('work-centers', WorkCenterController::class);
    Route::post('work-centers/{work_center}/deactivate', [WorkCenterController::class, 'deactivate']);
    Route::apiResource('boms', BomController::class)->only(['index', 'store', 'show']);
    Route::get('boms/{bom}/explode', [BomController::class, 'explode']);
    // ... table design.md lines 787-823
});
```

- [ ] **Step 3: Test 401/403/422/200 per WorkCenter**

```php
it('returns 401 without token', function (): void {
    $this->getJson('/api/v1/mes/work-centers')->assertUnauthorized();
});
```

- [ ] **Step 4: Replicare pattern per tutti i controller design.md**

- [ ] **Step 5: Commit**

---

### Task 15: Pannello Filament — T14/R14

**Files:**
- Create: `Modules/MES/app/Filament/Resources/WorkCenters/WorkCenterResource.php` (+ Pages, Schemas, Tables)
- Create: resources per Bom, Routing, ProductionOrder, QualityCheck, NonConformance, Downtime, Shift
- Create: `Modules/MES/app/Filament/Widgets/ProductionDashboardWidget.php`
- Modify: `Modules/MES/app/Providers/MESServiceProvider.php` (registra panel namespace se richiesto dal pattern moduli)
- Create: `Modules/MES/tests/Feature/Filament/WorkCenterResourceTest.php`

- [ ] **Step 1: WorkCenterResource** — CRUD + repeater calendario inline (relation `calendar`)

Seguire struttura:

```
Modules/MES/app/Filament/Resources/WorkCenters/
  WorkCenterResource.php
  Pages/ListWorkCenters.php, CreateWorkCenter.php, EditWorkCenter.php
  Schemas/WorkCenterForm.php
  Tables/WorkCentersTable.php
```

- [ ] **Step 2: BomResource** — Select `item_id` da ERP Item, Repeater bom lines

- [ ] **Step 3: ProductionOrderResource** — View page con tabs (RelationManagers: Operations, MaterialConsumptions, QualityChecks, LotNumbers); actions Release/Complete/Cancel che chiamano `ProductionOrderService`

- [ ] **Step 4: ProductionDashboardWidget** — 4 stat cards da query aggregate

- [ ] **Step 5: Policy Core** — usare permessi tabella `mes_*` generati da Core seeder; altrimenti creare `MESDatabaseSeeder` permissions block

- [ ] **Step 6: Test render list page authenticated admin**

- [ ] **Step 7: Commit**

---

### Task 16: Test suite e quality gates — T16

**Files:**
- Create: factories per ogni modello MES mancante
- Create: `Modules/MES/tests/Integration/ProductionCycleEndToEndTest.php`
- Create: `Modules/MES/tests/Feature/Invariants/ProductionOrderInvariantsTest.php`
- Modify: `Modules/MES/composer.json` scripts se presenti

- [ ] **Step 1: Factory coverage checklist**

Ogni modello in `app/Models/` deve avere factory in `database/factories/`.

- [ ] **Step 2: Invariant tests (Pest datasets)**

```php
it('keeps bom snapshot immutable after release', function (int $i): void {
    // mutate bom $i times, assert snapshot unchanged
})->with(range(1, 5));
```

Coprire invarianti design.md: snapshot, OEE bounds, order number uniqueness, state coherence, capacity >= 0, lot trace symmetry.

- [ ] **Step 3: E2E ciclo produzione**

Flusso: create PO → release → start/complete operations → backflush → complete PO → stock in.

- [ ] **Step 4: Quality gates**

Run:

```bash
cd Modules/MES && vendor/bin/pint --dirty
cd /srv/http/laraplate && php artisan test Modules/MES/tests --compact
cd Modules/MES && composer test:type-coverage 2>/dev/null || echo "run if script exists"
cd Modules/MES && composer test:types 2>/dev/null || echo "run if script exists"
```

- [ ] **Step 5: Commit**

---

### Task 17: Documentazione — T17

**Files:**
- Verify: `Modules/MES/docs/GLOSSARY.md`, `Modules/MES/docs/rag/GLOSSARY.md`
- Create: `Modules/MES/docs/MES_GUIDA_SEMPLICE.md`
- Create: `Modules/MES/docs/rag/MODULE.md`
- Modify: `Modules/MES/README.md` (roadmap → current status)

- [ ] **Step 1: MES_GUIDA_SEMPLICE.md** — flussi utente: creare WC, BOM, routing, ordine, avanzamento, OEE (italiano, no tecnicismi)

- [ ] **Step 2: rag/MODULE.md** — scopo, entità, flussi, integrazione ERP/ERPBridge (breve, RAG-friendly)

- [ ] **Step 3: Allineare GLOSSARY con entità implementate**

- [ ] **Step 4: Commit**

---

## Self-Review (coverage)

| Area funzionale | Task |
|-----------------|------|
| Work Center | 3, 14, 15 |
| BOM | 4, 14, 15 |
| Routing | 5, 14, 15 |
| Production Order | 6, 7, 14, 15 |
| Operations | 7, 14 |
| Material consumption / backflush | 8, 14 |
| Lot traceability | 9, 14 |
| Quality | 10, 14, 15 |
| Scheduling | 11, 14 |
| Shifts | 13, 14, 15 |
| Downtime/OEE | 12, 14, 15 |
| ERP integration | 1, 2, 6, 8 |
| API | 14 |
| Filament | 15 |

**Gap note:** operazioni parallele (`is_parallel=true`) — in `ProductionOrderOperationService`, stessa `sequence` può avere più operazioni `in_progress` contemporaneamente.

**Backflush (D5):** `routing_operation_id` su `mes_bom_lines`; idempotenza job obbligatoria.

**Type consistency:** colonna FK consumi = `production_order_operation_id` (non `operation_id`).

---

## Final checklist per ogni task

1. `vendor/bin/pint --dirty` (root o `Modules/MES`)
2. `php artisan test Modules/MES/tests --compact` (o subset file)
3. Commit nel submodule MES (e ERP se toccato)
4. Aggiornare le checkbox di questo piano quando un task è completato

---

## Execution Handoff

**Plan complete and saved to `docs/superpowers/plans/2026-06-19-mes-module-full-implementation.md`. Two execution options:**

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
