# SAO Application-Content Provider (R2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let the in-app assistant retrieve grounded, ACL-safe evidence from SAO tickets by registering a second application-content provider (`sao.tickets`), mirroring the CMS reference provider.

**Architecture:** Make `Ticket` Core-searchable over a denormalized document (title/description + related project/type/status/assignee/reporter/watchers/labels names). A `SaoApplicationContentRetrievalProvider` ranks candidates with `AdvancedSearchService`, then re-authorizes them through SAO's existing ACL gate `TicketQueryService::visible()` at database rehydration, and projects safe evidence via `SaoTicketEvidenceProjector`. SAO registers the provider; the AI module is untouched.

**Tech Stack:** PHP 8.5, Laravel 12, Laravel Scout (test driver `collection`), Pest, Core search framework (`AdvancedSearchService`, `Searchable` trait), the Core application-content contract.

**Spec:** `docs/superpowers/specs/2026-08-29-sao-application-content-provider-design.md`

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`. Braces on all control structures; explicit param + return types; `final` classes; `#[Override]` on interface methods; PHPDoc array shapes where useful.
- No new dependencies, no new base folders. Chat Italian, code/docs English.
- Never declare classes inside test files; test support under `Modules/SAO/tests/Stubs/` (PSR-4). Tests are Pest; `SCOUT_DRIVER=collection` in the test env means adopting `Searchable` does NOT hit a live engine.
- **ACL is the database gate**, not the engine: rehydrate candidate IDs through `TicketQueryService::visible()` (which applies `AuthorizationService::applyAclFiltersToQuery`); the search index holds NO ACL data. The engine ranks only.
- **Safe projection allowlist**: a hit exposes only title (label), excerpt (from description), status name, type name, project name, assignee name, ticket key, and canonical reference. Never comments, attachments, watchers, internal IDs beyond the record key, ACL/permission data, or engine payloads.
- Canonical reference format: `/app/sao/tickets/{ticket key}` (the same `/app/{module}/{entity}/{key}` convention the CMS provider uses; a stable reference string, not necessarily a live route).
- Everything lives in `Modules/SAO`; the AI module is not modified. Format with `vendor/bin/pint` on the changed files; commit inside the `Modules/SAO` submodule (branch `master`); touch nothing else.

**Mirror sources (read before starting — copy structure/idioms):**
- `Modules/CMS/app/ApplicationContent/CmsApplicationContentRetrievalProvider.php` (provider template)
- `Modules/CMS/app/ApplicationContent/CmsContentEvidenceProjector.php` (projector template)
- `Modules/CMS/app/Models/Content.php` (Searchable trait usage: `toSearchableWith`/`toSearchableArray`)
- `Modules/CMS/tests/Feature/ApplicationContent/CmsApplicationContentRetrievalProviderTest.php` (deterministic test: mocks the search layer, `scout.driver` set to a test value, real DB rehydration)
- `Modules/CMS/app/Providers/CMSServiceProvider.php:61-62` (provider registration)

**Verified code facts:**
- `TicketQueryService` (`Modules/SAO/app/Services/TicketQueryService.php`): `__construct(AuthorizationService)`, `visible(): Builder` applies ACL filters — the authoritative SAO authorization gate.
- `Ticket` (`Modules/SAO/app/Models/Ticket.php`): fields `project_id, ticket_type_id, ticket_status_id, priority, title, description, assignee_id, key, number, due_at`; relations `project()`, `type()`, `status()`, `assignee()`, `reporter()`, `watchers()` (BelongsToMany), `labels()` (BelongsToMany), `comments()`. Extends `Model implements MediaContract`.
- Core application-content DTOs: `ApplicationContentSourceDescriptor(string source, string module, string entity, array supportedLocales, array capabilities, array intentCategories)`; `ApplicationContentQuery` exposes `->query, ->locale, ->limit, ->source`; `ApplicationContentAuthorization` exposes `->permissionName, ->filters` (`?FiltersGroup`); `ApplicationContentResult(string source, array hits, string strategy, bool truncated)`; `ApplicationContentHit(string id, string source, string module, string entity, int|string recordKey, string excerpt, string label, string canonicalReference, string locale, string strategy, ?float score, ?string revision, bool truncated)`.
- `AdvancedSearchService::search(model: Model, query: string, page: int, perPage: int, filters: ?FiltersGroup): AdvancedSearchResult`; `AdvancedSearchResult->hits` is a list of `['id' => string, 'score' => float, 'source' => ['connection' => string]]`, `->meta['strategies']` a list.
- `Core\Search\Traits\Searchable`: provides `toSearchableArray()` and `getSearchMapping()`/`getSchemaDefinition()`; a model overrides `toSearchableWith(): array` (relations to eager-load) and `toSearchableArray(): array` (the document), as `Content` does.

---

### Task 1: Make `Ticket` Core-searchable

**Files:**
- Modify: `Modules/SAO/app/Models/Ticket.php`
- Test: `Modules/SAO/tests/Unit/Models/TicketSearchableTest.php`

**Interfaces:**
- Produces: `Ticket` uses `Modules\Core\Search\Traits\Searchable`; `toSearchableWith(): array` returns `['project', 'type', 'status', 'assignee', 'reporter', 'watchers', 'labels']`; `toSearchableArray(): array` returns a document with keys `title, description, priority, key, number, due_at, project, type, status, assignee, reporter, watchers, labels` (related entities as `{id, name}`-shaped arrays; `project`/`status` may carry extra `key`/`category`).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\SAO\Models\Ticket;

it('builds a denormalized searchable document with related names', function (): void {
    $ticket = Ticket::factory()->create(['title' => 'Deploy failed on staging', 'description' => 'pipeline red']);
    // attach/relate at least one label and set project/type/status/assignee via the factory or explicit relations

    $doc = $ticket->fresh(['project', 'type', 'status', 'assignee', 'reporter', 'watchers', 'labels'])->toSearchableArray();

    expect($doc)->toHaveKeys(['title', 'description', 'priority', 'key', 'project', 'type', 'status', 'assignee', 'labels'])
        ->and($doc['title'])->toBe('Deploy failed on staging')
        ->and($doc['project'])->toHaveKey('name')
        ->and($doc['status'])->toHaveKey('name')
        ->and($doc['labels'])->toBeArray();
});

it('eager-loads the relations it denormalizes', function (): void {
    expect((new Ticket)->toSearchableWith())
        ->toContain('project')->toContain('type')->toContain('status')
        ->toContain('assignee')->toContain('reporter')->toContain('watchers')->toContain('labels');
});
```

Adjust the factory setup to whatever `Ticket::factory()` and the SAO factories provide (read `Modules/SAO/database/factories`); create related project/type/status/assignee/label via their factories or states so the relations resolve.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/SAO/tests/Unit/Models/TicketSearchableTest.php`
Expected: FAIL (`toSearchableWith`/`toSearchableArray` not defined, or missing keys).

- [ ] **Step 3: Write minimal implementation**

In `Ticket.php`, add the trait and the two methods (mirror `Content`'s approach — the trait provides the default `toSearchableArray`; override it to add the denormalized relations):

```php
use Modules\Core\Search\Traits\Searchable;

// inside the class:
use Searchable {
    Searchable::toSearchableArray as private toSearchableArrayTrait;
}

/**
 * @return list<string>
 */
public function toSearchableWith(): array
{
    return ['project', 'type', 'status', 'assignee', 'reporter', 'watchers', 'labels'];
}

/**
 * @return array<string, mixed>
 */
public function toSearchableArray(): array
{
    $document = $this->toSearchableArrayTrait();

    $document['project'] = $this->project === null ? null
        : ['id' => $this->project->getKey(), 'name' => (string) $this->project->name, 'key' => (string) ($this->project->key ?? '')];
    $document['type'] = $this->type === null ? null
        : ['id' => $this->type->getKey(), 'name' => (string) $this->type->name];
    $document['status'] = $this->status === null ? null
        : ['id' => $this->status->getKey(), 'name' => (string) $this->status->name, 'category' => (string) ($this->status->category ?? '')];
    $document['assignee'] = $this->assignee === null ? null
        : ['id' => $this->assignee->getKey(), 'name' => (string) $this->assignee->name];
    $document['reporter'] = $this->reporter === null ? null
        : ['id' => $this->reporter->getKey(), 'name' => (string) $this->reporter->name];
    $document['watchers'] = $this->watchers
        ->map(static fn ($w): array => ['id' => $w->getKey(), 'name' => (string) $w->name])->values()->all();
    $document['labels'] = $this->labels
        ->map(static fn ($l): array => ['id' => $l->getKey(), 'name' => (string) $l->name])->values()->all();

    return $document;
}
```

Verify the actual attribute names on the related models (`project->name`/`key`, `status->name`/`category`, `type->name`, `assignee`/`reporter` are `User` with `name`, `label->name`) against their model files and adjust the accessors to the real columns. Keep the base document's `title`/`description`/`priority`/`key`/`number`/`due_at` from the trait (they are model attributes).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact Modules/SAO/tests/Unit/Models/TicketSearchableTest.php`
Expected: PASS. (`SCOUT_DRIVER=collection` in the SAO test env means no live engine is contacted.)

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint Modules/SAO/app/Models/Ticket.php Modules/SAO/tests/Unit/Models/TicketSearchableTest.php
cd /srv/http/laraplate-stack/laraplate/Modules/SAO
git add app/Models/Ticket.php tests/Unit/Models/TicketSearchableTest.php
git commit -m "feat(sao): make Ticket Core-searchable with denormalized relations"
```

---

### Task 2: `SaoTicketEvidenceProjector`

**Files:**
- Create: `Modules/SAO/app/ApplicationContent/SaoTicketEvidenceProjector.php`
- Test: `Modules/SAO/tests/Unit/ApplicationContent/SaoTicketEvidenceProjectorTest.php`

**Interfaces:**
- Consumes: `Ticket`; `Modules\Core\ApplicationContent\Data\ApplicationContentHit`.
- Produces: `final class SaoTicketEvidenceProjector` with `project(Ticket $ticket, string $requestedLocale, string $strategy, ?float $score): ?ApplicationContentHit`. Returns null when the ticket has no title. Hit id = `'sao.tickets:' . $ticket->key`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\Core\ApplicationContent\Data\ApplicationContentHit;
use Modules\SAO\ApplicationContent\SaoTicketEvidenceProjector;
use Modules\SAO\Models\Ticket;

it('projects a ticket to a safe hit', function (): void {
    $ticket = Ticket::factory()->create(['title' => 'Deploy failed', 'description' => str_repeat('x', 5000)]);

    $hit = (new SaoTicketEvidenceProjector)->project($ticket->fresh(['status', 'type', 'project', 'assignee']), 'en', 'lexical', 0.9);

    expect($hit)->toBeInstanceOf(ApplicationContentHit::class)
        ->and($hit->source)->toBe('sao.tickets')
        ->and($hit->module)->toBe('sao')
        ->and($hit->entity)->toBe('tickets')
        ->and($hit->label)->toBe('Deploy failed')
        ->and($hit->recordKey)->toBe($ticket->key)
        ->and($hit->canonicalReference)->toBe('/app/sao/tickets/' . $ticket->key)
        ->and($hit->id)->toBe('sao.tickets:' . $ticket->key)
        ->and(mb_strlen($hit->excerpt))->toBeLessThanOrEqual(1000);

    // safe projection: no comment/attachment/internal payloads leak
    $json = json_encode(get_object_vars($hit));
    expect($json)->not->toContain('assignee_id')->not->toContain('ticket_status_id');
});

it('returns null when the ticket has no title', function (): void {
    $ticket = Ticket::factory()->make(['title' => '']);
    expect((new SaoTicketEvidenceProjector)->project($ticket, 'en', 'lexical', null))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/SAO/tests/Unit/ApplicationContent/SaoTicketEvidenceProjectorTest.php`
Expected: FAIL (class not found).

- [ ] **Step 3: Write minimal implementation**

Mirror `CmsContentEvidenceProjector` but WITHOUT translations (tickets are single-locale). Build the excerpt from `description` (bounded, e.g. 1000 chars) and the label from `title` (bounded 200). Include only safe metadata in the hit via the `ApplicationContentHit` constructor:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\ApplicationContent;

use Illuminate\Support\Str;
use Modules\Core\ApplicationContent\Data\ApplicationContentHit;
use Modules\SAO\Models\Ticket;

final class SaoTicketEvidenceProjector
{
    public function project(Ticket $ticket, string $requestedLocale, string $strategy, ?float $score): ?ApplicationContentHit
    {
        $label = mb_trim((string) $ticket->title);

        if ($label === '') {
            return null;
        }

        $key = (string) $ticket->key;
        $description = (string) ($ticket->description ?? '');
        $excerpt = Str::limit($description, 1000, '');
        $truncated = mb_strlen($description) > mb_strlen($excerpt);

        return new ApplicationContentHit(
            id: 'sao.tickets:' . $key,
            source: 'sao.tickets',
            module: 'sao',
            entity: 'tickets',
            recordKey: $key,
            excerpt: $excerpt,
            label: Str::limit($label, 200, ''),
            canonicalReference: '/app/sao/tickets/' . $key,
            locale: $requestedLocale,
            strategy: $strategy,
            score: $score,
            revision: $ticket->updated_at?->toIso8601String(),
            truncated: $truncated,
        );
    }
}
```

Confirm the exact `ApplicationContentHit` constructor parameter names/order against `Modules/Core/app/ApplicationContent/Data/ApplicationContentHit.php` and adjust if they differ.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact Modules/SAO/tests/Unit/ApplicationContent/SaoTicketEvidenceProjectorTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint Modules/SAO/app/ApplicationContent/SaoTicketEvidenceProjector.php Modules/SAO/tests/Unit/ApplicationContent/SaoTicketEvidenceProjectorTest.php
cd /srv/http/laraplate-stack/laraplate/Modules/SAO
git add app/ApplicationContent/SaoTicketEvidenceProjector.php tests/Unit/ApplicationContent/SaoTicketEvidenceProjectorTest.php
git commit -m "feat(sao): ticket evidence projector for application content"
```

---

### Task 3: `SaoApplicationContentRetrievalProvider`

**Files:**
- Create: `Modules/SAO/app/ApplicationContent/SaoApplicationContentRetrievalProvider.php`
- Test: `Modules/SAO/tests/Feature/ApplicationContent/SaoApplicationContentRetrievalProviderTest.php`

**Interfaces:**
- Consumes: `AdvancedSearchService`, `TicketQueryService`, `QueryBuilder`, `SaoTicketEvidenceProjector` (Task 2), the Core application-content DTOs.
- Produces: `final class SaoApplicationContentRetrievalProvider implements ApplicationContentRetrievalProviderInterface` with `descriptor(): ApplicationContentSourceDescriptor` (source `sao.tickets`) and `retrieve(ApplicationContentQuery, ApplicationContentAuthorization): ApplicationContentResult`.

- [ ] **Step 1: Write the failing test**

Mirror `CmsApplicationContentRetrievalProviderTest`: build the provider with a real `AdvancedSearchService` whose engine/ensemble is mocked to return a canned `AdvancedSearchResult` of candidate ticket ids, a real `TicketQueryService`, `QueryBuilder`, and the projector; seed tickets in the DB; authenticate a user. Read the CMS test first and copy its mocking scaffold (the `ISearchEngine`/`EnsembleSearchService` mock + `config()->set('scout.driver', ...)`).

```php
// Core assertions (fill setup from the CMS test's pattern):
it('returns ACL-authorized ticket evidence ranked by the engine', function (): void {
    // seed visible ticket V (id known) + a ticket H the user cannot see
    // mock the search layer to return [V, H] as candidates
    $result = $provider->retrieve(
        new ApplicationContentQuery('sao.tickets', 'deploy', 'en', 5),
        $authorization, // permissionName 'sao.tickets.select' (or the SAO select permission), filters null
    );
    $keys = array_map(fn ($hit) => $hit->recordKey, $result->hits);
    expect($keys)->toContain(V->key)->not->toContain(H->key)   // ACL exclusion via visible()
        ->and($result->source)->toBe('sao.tickets');
});

it('falls back to a lexical DB search over visible() when the engine returns nothing', function (): void {
    // mock the search layer to return [] ; seed a visible ticket whose title matches the query
    $result = $provider->retrieve(new ApplicationContentQuery('sao.tickets', 'deploy', 'en', 5), $authorization);
    expect($result->hits)->not->toBe([])->and($result->strategy)->toBe('lexical');
});

it('never returns a ticket outside visible() even if the engine matches it', function (): void {
    // mock engine returns only the hidden ticket H ; assert hits === []
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/SAO/tests/Feature/ApplicationContent/SaoApplicationContentRetrievalProviderTest.php`
Expected: FAIL (class not found).

- [ ] **Step 3: Write minimal implementation**

Mirror `CmsApplicationContentRetrievalProvider` exactly, with these adaptations: model is `Ticket`; `authorizedQuery()` is `TicketQueryService::visible()` (SAO's ACL gate) with the optional `authorization->filters` applied via `QueryBuilder`; no translations relation; the lexical fallback does `LIKE` on `title`/`description` over `visible()`; project via `SaoTicketEvidenceProjector`.

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\ApplicationContent;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderInterface;
use Modules\Core\ApplicationContent\Data\ApplicationContentAuthorization;
use Modules\Core\ApplicationContent\Data\ApplicationContentQuery;
use Modules\Core\ApplicationContent\Data\ApplicationContentResult;
use Modules\Core\ApplicationContent\Data\ApplicationContentSourceDescriptor;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Search\DTOs\AdvancedSearchResult;
use Modules\Core\Search\Services\AdvancedSearchService;
use Modules\Core\Services\Crud\QueryBuilder;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\TicketQueryService;
use Override;
use Throwable;

final class SaoApplicationContentRetrievalProvider implements ApplicationContentRetrievalProviderInterface
{
    public function __construct(
        private readonly AdvancedSearchService $search,
        private readonly TicketQueryService $tickets,
        private readonly QueryBuilder $queryBuilder,
        private readonly SaoTicketEvidenceProjector $projector,
    ) {}

    #[Override]
    public function descriptor(): ApplicationContentSourceDescriptor
    {
        return new ApplicationContentSourceDescriptor(
            source: 'sao.tickets',
            module: 'sao',
            entity: 'tickets',
            supportedLocales: [(string) config('app.locale', 'en')],
            capabilities: ['hybrid', 'lexical', 'semantic'],
            intentCategories: ['application_content', 'sao', 'ticket', 'issue', 'task'],
        );
    }

    #[Override]
    public function retrieve(ApplicationContentQuery $query, ApplicationContentAuthorization $authorization): ApplicationContentResult
    {
        $connection = (new Ticket)->getConnectionName() ?: 'default';
        $window = min(50, $query->limit + 1);

        $search_result = $this->advancedSearch($query, $authorization->filters);
        $ranked = $this->rankedHits($search_result, $window, $connection);

        if ($ranked === []) {
            $search_result = $this->lexicalFallback($query, $authorization);
            $ranked = $this->rankedHits($search_result, $window, $connection);
        }

        $strategy = $this->strategy($search_result);
        $records = $this->rehydrate(array_keys($ranked), $authorization);
        $hits = [];

        foreach ($ranked as $record_id => $score) {
            $ticket = $records[$record_id] ?? null;

            if (! $ticket instanceof Ticket) {
                continue;
            }

            $hit = $this->projector->project($ticket, $query->locale, $strategy, $score);

            if ($hit !== null) {
                $hits[] = $hit;
            }
        }

        return new ApplicationContentResult(
            source: 'sao.tickets',
            hits: array_slice($hits, 0, $query->limit),
            strategy: $strategy,
            truncated: $query->limit < count($hits),
        );
    }

    private function advancedSearch(ApplicationContentQuery $query, ?FiltersGroup $filters): AdvancedSearchResult
    {
        try {
            return $this->search->search(new Ticket, $query->query, 1, min(50, $query->limit + 1), $filters);
        } catch (Throwable) {
            return AdvancedSearchResult::empty(1, min(50, $query->limit + 1), ['degraded' => ['lexical_fallback']]);
        }
    }

    private function lexicalFallback(ApplicationContentQuery $query, ApplicationContentAuthorization $authorization): AdvancedSearchResult
    {
        $needle = '%' . addcslashes($query->query, '\\%_') . '%';
        $db = $this->authorizedQuery($authorization)
            ->where(function (Builder $q) use ($needle): void {
                $q->where('title', 'like', $needle)->orWhere('description', 'like', $needle);
            });

        $ids = $db->orderBy((new Ticket)->qualifyColumn('id'))
            ->limit(min(50, $query->limit + 1))
            ->pluck((new Ticket)->qualifyColumn('id'))
            ->map(static fn (mixed $id): string => (string) $id)->values()->all();

        $hits = [];
        foreach ($ids as $position => $id) {
            $hits[] = ['id' => $id, 'score' => round(1 / ($position + 1), 6), 'source' => ['connection' => (new Ticket)->getConnectionName() ?: 'default']];
        }

        return new AdvancedSearchResult(
            hits: $hits, total: count($hits), page: 1, perPage: min(50, $query->limit + 1),
            totalPages: $hits === [] ? 0 : 1, meta: ['strategies' => ['keyword'], 'degraded' => ['lexical_fallback']],
        );
    }

    /**
     * @param  list<string>  $recordIds
     * @return array<string, Ticket>
     */
    private function rehydrate(array $recordIds, ApplicationContentAuthorization $authorization): array
    {
        if ($recordIds === []) {
            return [];
        }

        return $this->authorizedQuery($authorization)
            ->whereKey($recordIds)
            ->with(['status', 'type', 'project', 'assignee'])
            ->get()
            ->mapWithKeys(static fn (Ticket $ticket): array => [(string) $ticket->getKey() => $ticket])
            ->all();
    }

    /**
     * @return Builder<Ticket>
     */
    private function authorizedQuery(ApplicationContentAuthorization $authorization): Builder
    {
        $query = $this->tickets->visible();

        if ($authorization->filters instanceof FiltersGroup) {
            $this->queryBuilder->applyFilters($query, $authorization->filters);
        }

        return $query;
    }

    /**
     * @return array<string, float>
     */
    private function rankedHits(AdvancedSearchResult $result, int $limit, string $connection): array
    {
        $ranked = [];

        foreach ($result->hits as $hit) {
            $id = $hit['id'] ?? null;
            $source = is_array($hit['source'] ?? null) ? $hit['source'] : [];

            if (! is_string($id) || $id === '' || ($source['connection'] ?? null) !== $connection || isset($ranked[$id])) {
                continue;
            }

            $ranked[$id] = is_numeric($hit['score'] ?? null) ? max(0.0, min(1.0, (float) $hit['score'])) : 0.0;

            if ($limit <= count($ranked)) {
                break;
            }
        }

        return $ranked;
    }

    private function strategy(AdvancedSearchResult $result): string
    {
        $strategies = is_array($result->meta['strategies'] ?? null) ? $result->meta['strategies'] : [];

        if (in_array('hybrid', $strategies, true) || (in_array('keyword', $strategies, true) && in_array('vector', $strategies, true))) {
            return 'hybrid';
        }

        return in_array('vector', $strategies, true) ? 'semantic' : 'lexical';
    }
}
```

Confirm `TicketQueryService::visible()` returns a `Ticket` query on the right connection, and that `authorization->filters`/`QueryBuilder::applyFilters` apply cleanly to it. If `visible()` already imposes ordering incompatible with the lexical fallback's `orderBy`, adjust the fallback to reorder.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact Modules/SAO/tests/Feature/ApplicationContent/SaoApplicationContentRetrievalProviderTest.php`
Expected: PASS (authorized hit returned; hidden ticket excluded; lexical fallback works).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint Modules/SAO/app/ApplicationContent/SaoApplicationContentRetrievalProvider.php Modules/SAO/tests/Feature/ApplicationContent/SaoApplicationContentRetrievalProviderTest.php
cd /srv/http/laraplate-stack/laraplate/Modules/SAO
git add app/ApplicationContent/SaoApplicationContentRetrievalProvider.php tests/Feature/ApplicationContent/SaoApplicationContentRetrievalProviderTest.php
git commit -m "feat(sao): sao.tickets application-content retrieval provider"
```

---

### Task 4: Register the provider in `SAOServiceProvider`

**Files:**
- Modify: `Modules/SAO/app/Providers/SAOServiceProvider.php` (boot path, mirroring `CMSServiceProvider.php:61-62`)
- Test: `Modules/SAO/tests/Feature/ApplicationContent/SaoProviderRegistrationTest.php`

**Interfaces:**
- Consumes: `ApplicationContentRetrievalProviderRegistryInterface`, `SaoApplicationContentRetrievalProvider` (Task 3).
- Produces: the registry resolves `sao.tickets` to the provider; the provider's descriptor reports source `sao.tickets`, module `sao`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderRegistryInterface;

it('registers the sao.tickets application-content provider', function (): void {
    $registry = app(ApplicationContentRetrievalProviderRegistryInterface::class);
    $descriptor = $registry->descriptorFor('sao.tickets');

    expect($descriptor)->not->toBeNull()
        ->and($descriptor->module)->toBe('sao')
        ->and($descriptor->entity)->toBe('tickets');
});
```

Confirm the registry's lookup method name (`descriptorFor`/`providerFor`) against `Modules/Core/app/ApplicationContent/ApplicationContentRetrievalProviderRegistry.php` and use the real one.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/SAO/tests/Feature/ApplicationContent/SaoProviderRegistrationTest.php`
Expected: FAIL (source not registered).

- [ ] **Step 3: Write minimal implementation**

In `SAOServiceProvider::boot()` (mirror the CMS registration), add:

```php
$this->app->make(ApplicationContentRetrievalProviderRegistryInterface::class)
    ->register($this->app->make(SaoApplicationContentRetrievalProvider::class));
```

with the matching `use` imports. Place it alongside any existing SAO provider/registry registrations; guard consistently with how CMS does (only when the module is enabled — follow the CMS pattern).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact Modules/SAO/tests/Feature/ApplicationContent/SaoProviderRegistrationTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint Modules/SAO/app/Providers/SAOServiceProvider.php Modules/SAO/tests/Feature/ApplicationContent/SaoProviderRegistrationTest.php
cd /srv/http/laraplate-stack/laraplate/Modules/SAO
git add app/Providers/SAOServiceProvider.php tests/Feature/ApplicationContent/SaoProviderRegistrationTest.php
git commit -m "feat(sao): register sao.tickets application-content provider"
```

---

### Task 5: RAG documentation

**Files:**
- Modify: `Modules/SAO/docs/rag/MODULE.md` (add an "Application-content provider" subsection)

- [ ] **Step 1: Add the note**

Add a section to `Modules/SAO/docs/rag/MODULE.md` stating: SAO tickets are exposed to the in-app assistant as the `sao.tickets` application-content source; tickets are Core-searchable over a denormalized document (title/description + project/type/status/assignee/reporter/watchers/labels names); the provider ranks candidates with the search engine and re-authorizes them through `TicketQueryService::visible()` at rehydration (the index holds no ACL data); the safe projection exposes only title/excerpt/status/type/project/assignee/key/reference; comments, attachments, and global tags are out of scope. Reference the design spec `docs/superpowers/specs/2026-08-29-sao-application-content-provider-design.md`.

- [ ] **Step 2: Commit**

```bash
cd /srv/http/laraplate-stack/laraplate/Modules/SAO
git add docs/rag/MODULE.md
git commit -m "docs(sao): document the sao.tickets application-content provider"
```

---

## Final verification

- [ ] Run the whole R2 surface together:
```bash
php artisan test --compact \
  Modules/SAO/tests/Unit/Models/TicketSearchableTest.php \
  Modules/SAO/tests/Unit/ApplicationContent/SaoTicketEvidenceProjectorTest.php \
  Modules/SAO/tests/Feature/ApplicationContent/SaoApplicationContentRetrievalProviderTest.php \
  Modules/SAO/tests/Feature/ApplicationContent/SaoProviderRegistrationTest.php
```
- [ ] Confirm the broader SAO suite still passes (Ticket now Searchable): `php artisan test --compact Modules/SAO/tests` (or the ticket-related subset).
- [ ] `vendor/bin/pint` clean on changed files.

## Self-review notes (author)

- **Spec coverage:** searchable Ticket with denormalized relations (Task 1); safe projector (Task 2); provider with engine-rank + `visible()` ACL rehydration + lexical fallback (Task 3); registration, no AI-module change (Task 4); RAG docs (Task 5). Global tags, comments/attachments, SAO eval dataset, structured filters — intentionally out of scope per the spec.
- **ACL invariant:** authorization is `TicketQueryService::visible()` at rehydration for BOTH the engine path and the lexical fallback (`authorizedQuery()`); the index carries no ACL; Task 3's tests assert a hidden ticket never surfaces even when the engine matches it.
- **Type consistency:** `sao.tickets` source key, `ApplicationContentHit`/`Result`/`Descriptor` constructor shapes, and `visible()`/`AdvancedSearchService::search` signatures are used identically across tasks and match the CMS templates.
- **Known verifications for the implementer (flagged in-task, not placeholders):** exact related-model attribute columns (project/status/type/label names), the `ApplicationContentHit` constructor param order, the registry lookup method name, and the `SAOServiceProvider` registration guard — each task says to confirm against the named real file and adjust. These are cross-file confirmations, not undefined logic.
