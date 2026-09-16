# Persistent User Memory Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (or executing-plans). Steps use checkbox (`- [ ]`) tracking.

**Goal:** Give the assistant cross-session memory of the user: a per-user store of durable facts, extracted at the existing summarize hook, deduped/superseded/expired, and injected (bounded) into the assistant prompt on later conversations.

**Architecture:** New `ai_memory_facts` table + `MemoryFact` model (user-scoped, supersession + TTL columns, mirroring existing AI model/migration conventions). A `UserMemoryService` owns write (dedup/supersede/secret-filter) and read (bounded active facts). Extraction stays where it is (`MemoryService::extractFacts` at `createSummarySnapshot`); its facts are promoted into the store for the conversation's user. Injection extends the existing memory-context block.

**Tech Stack:** PHP 8.5, Laravel 12, Pest. No new dependencies.

**Spec:** `docs/superpowers/specs/2026-09-16-persistent-user-memory-design.md`

## Global Constraints

- Every PHP file `declare(strict_types=1);`; braces on all control structures; explicit param + return types; `final`; `#[Override]` where overriding; constructor property promotion. Models extend `Modules\Core\Overrides\Model`; casts in a `casts()` method.
- Migrations use `Modules\Core\Helpers\MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true)` and named FK/IDX (`{table}_{col}_FK` / `_IDX`), table name from the `AITables` enum. FK to users via `Modules\Core\Enums\CoreTables::Users->value` (see the conversations migration).
- Scope everything by `user_id` (isolation key; no tenant column — AI tables have none today). Reads/writes filtered by `user_id`, fail-closed.
- Facts are data, never instructions (guardrail-consistent). No secrets stored.
- `config()` outside config files, never `env()`; new keys under `ai.features`, read via `ai_config_int/bool`.
- Tests: Pest, module `tests/`; factories for models; no classes declared in test files. Run `vendor/bin/pint --dirty --format agent` before finalizing. Run `php artisan test --compact <path>` for the narrow set.
- Commit inside the `Modules/AI` submodule (branch `master`). Touch only files for this task.

**Verified touch points (read before coding):**
- `Modules/AI/app/Enums/AITables.php` (backed string enum; add a case).
- `Modules/AI/database/migrations/2026_01_25_130000_create_ai_conversation_summaries_table.php` (migration pattern to mirror).
- `Modules/AI/app/Models/ConversationSummary.php` (model pattern), `Modules/AI/app/Models/Conversation.php` (`user_id` FK, `user()`).
- `Modules/AI/app/Services/MemoryService.php`: `extractFacts(Conversation): list<string>`, `createSummarySnapshot(Conversation): ConversationSummary` (already stores facts in `ConversationSummary.facts`), `getContextForNewMessage(Conversation): ?string`.
- `Modules/AI/app/Services/ChatService.php`: memory injection at the `getMemoryService()->getContextForNewMessage($conversation)` call (~line 222, appended to `$system_prompt`); `checkAndCreateSummaryIfNeeded()` (~line 309) calls `createSummarySnapshot`.

---

### Task 1: `ai_memory_facts` table + `MemoryFact` model + enum case

**Files:**
- Modify: `Modules/AI/app/Enums/AITables.php` (add `case MemoryFacts = 'ai_memory_facts';`)
- Create: `Modules/AI/database/migrations/2026_09_16_000000_create_ai_memory_facts_table.php`
- Create: `Modules/AI/app/Models/MemoryFact.php`
- Create: `Modules/AI/database/factories/MemoryFactFactory.php`
- Test: `Modules/AI/tests/Unit/Models/MemoryFactTest.php`

**Interfaces:**
- Produces: `MemoryFact` with columns `id, user_id, fact, source_message_id?, confidence?, learned_at, expires_at?, superseded_by?, superseded_at?` + timestamps/soft-delete; a query scope `scopeActiveForUser(Builder, int $userId): Builder` returning not-soft-deleted, `superseded_by` null, `expires_at` null-or-future, ordered `learned_at desc`.

- [ ] **Step 1: Migration** (mirror the summaries migration exactly for helpers/naming):

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\AI\Enums\AITables;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = AITables::MemoryFacts->value;

        Schema::create($table_name, function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('user_id')->constrained(CoreTables::Users->value, 'id', "{$table_name}_user_id_FK")->cascadeOnDelete();
            $table->text('fact')->comment('Durable fact about the user');
            $table->foreignId('source_message_id')->nullable()->constrained(AITables::Messages->value, 'id', "{$table_name}_source_message_id_FK")->nullOnDelete();
            $table->float('confidence')->nullable()->comment('Normalized extraction confidence when available');
            $table->timestamp('learned_at')->comment('When the fact was first extracted');
            $table->timestamp('expires_at')->nullable()->comment('TTL; null = no expiry');
            $table->foreignId('superseded_by')->nullable()->constrained($table_name, 'id', "{$table_name}_superseded_by_FK")->nullOnDelete();
            $table->timestamp('superseded_at')->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->index(['user_id', 'superseded_by', 'expires_at'], "{$table_name}_active_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(AITables::MemoryFacts->value);
    }
};
```

- [ ] **Step 2: Enum** — add `case MemoryFacts = 'ai_memory_facts';` to `AITables` (alongside the others).

- [ ] **Step 3: Model** (`MemoryFact.php`), mirroring `ConversationSummary`:

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AI\Enums\AITables;
use Modules\Core\Models\User;
use Modules\Core\Overrides\Model;
use Override;

/**
 * @property int|null $id
 * @property int $user_id
 * @property string $fact
 * @property int|null $source_message_id
 * @property float|null $confidence
 * @property \Illuminate\Support\Carbon $learned_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property int|null $superseded_by
 * @property \Illuminate\Support\Carbon|null $superseded_at
 */
final class MemoryFact extends Model
{
    /** @use HasFactory<\Modules\AI\Database\Factories\MemoryFactFactory> */
    use HasFactory;

    #[Override]
    protected $table = AITables::MemoryFacts->value;

    #[Override]
    protected $fillable = [
        'user_id', 'fact', 'source_message_id', 'confidence',
        'learned_at', 'expires_at', 'superseded_by', 'superseded_at',
    ];

    /**
     * @param  Builder<MemoryFact>  $query
     * @return Builder<MemoryFact>
     */
    public function scopeActiveForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId)
            ->whereNull('superseded_by')
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('learned_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'learned_at' => 'datetime',
            'expires_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }
}
```

- [ ] **Step 4: Factory** (`MemoryFactFactory.php`) — user via `User::factory()`, `fact` a sentence, `learned_at` now, `expires_at` null; states `expired()` (`expires_at` = past) and `superseded()` (`superseded_at` = now, `superseded_by` = another fact id). Follow an existing AI/Core factory for structure.

- [ ] **Step 5: Test** (`MemoryFactTest.php`):

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AI\Models\MemoryFact;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

it('active scope returns only the user own non-expired non-superseded facts', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $active = MemoryFact::factory()->for($user)->create(['fact' => 'prefers dark mode']);
    MemoryFact::factory()->for($user)->expired()->create(['fact' => 'old']);
    MemoryFact::factory()->for($user)->superseded()->create(['fact' => 'stale']);
    MemoryFact::factory()->for($other)->create(['fact' => 'not mine']);

    $ids = MemoryFact::query()->activeForUser($user->id)->pluck('id')->all();

    expect($ids)->toBe([$active->id]);
});
```

- [ ] **Step 6:** `php artisan test --compact Modules/AI/tests/Unit/Models/MemoryFactTest.php` (PASS), `vendor/bin/pint --dirty --format agent`, commit inside `Modules/AI`: `feat(ai): ai_memory_facts table + MemoryFact model`.

---

### Task 2: `UserMemoryService` (write + read)

**Files:**
- Create: `Modules/AI/app/Services/UserMemoryService.php`
- Test: `Modules/AI/tests/Unit/Services/UserMemoryServiceTest.php`

**Interfaces:**
- Consumes: `MemoryFact` (Task 1), `Modules\Core\Models\User`.
- Produces: `final readonly class UserMemoryService` with:
  - `rememberFacts(User $user, list<string> $facts, ?int $sourceMessageId = null): void` — for each fact: skip empty/secret-like; skip if an active fact with identical normalized text already exists (dedup); else create with `learned_at = now()`, `expires_at` from config TTL. (v1: no semantic contradiction — exact/normalized dedup only.)
  - `activeFactsForUser(User $user, ?int $limit = null): list<string>` — `MemoryFact::activeForUser` limited by config, returns the `fact` strings.

- [ ] **Step 1: Test**

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AI\Models\MemoryFact;
use Modules\AI\Services\UserMemoryService;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('ai.features.memory.fact_ttl_days', 0); // 0 = no expiry
    config()->set('ai.features.memory.max_injected_facts', 20);
});

it('persists new facts and dedups exact/normalized duplicates', function (): void {
    $user = User::factory()->create();
    $svc = new UserMemoryService;

    $svc->rememberFacts($user, ['Prefers dark mode', 'Works in ERP'], sourceMessageId: null);
    $svc->rememberFacts($user, ['prefers dark mode  ']); // duplicate (normalized)

    expect(MemoryFact::query()->activeForUser($user->id)->count())->toBe(2);
});

it('drops secret-like facts', function (): void {
    $user = User::factory()->create();
    (new UserMemoryService)->rememberFacts($user, ['password is hunter2', 'likes short replies']);

    $facts = (new UserMemoryService)->activeFactsForUser($user);
    expect($facts)->toContain('likes short replies')
        ->and($facts)->not->toContain('password is hunter2');
});

it('reads active facts bounded by config limit', function (): void {
    $user = User::factory()->create();
    MemoryFact::factory()->for($user)->count(30)->create();
    config()->set('ai.features.memory.max_injected_facts', 5);

    expect((new UserMemoryService)->activeFactsForUser($user))->toHaveCount(5);
});
```

- [ ] **Step 2: Implement.** Normalization = `mb_strtolower(mb_trim(preg_replace('/\s+/', ' ', $fact)))` for dedup comparison against active facts. Secret filter = drop a fact matching `/\b(password|secret|api[_ ]?key|token|credential|otp|pin)\b/i` (conservative; err on dropping). TTL: `ai_config_int('ai.features.memory.fact_ttl_days', 0)`; `0` → `expires_at = null`, else `now()->addDays($days)`. Limit: `ai_config_int('ai.features.memory.max_injected_facts', 20)`. Use `MemoryFact::query()->activeForUser($user->id)` for both dedup lookup and read.

- [ ] **Step 3:** run the test (PASS), pint, commit: `feat(ai): UserMemoryService (persist + read user facts)`.

---

### Task 3: promote extracted facts at the summarize hook

**Files:**
- Modify: `Modules/AI/app/Services/MemoryService.php` (`createSummarySnapshot`)
- Test: `Modules/AI/tests/Feature/UserMemoryPersistenceTest.php`

**Interfaces:**
- Consumes: `UserMemoryService` (Task 2); `Conversation` (`user`, `user_id`), the `ConversationSummary` it already creates (with `facts`).

- [ ] **Step 1: Test** — after a conversation crosses the summary threshold and `createSummarySnapshot` runs, its extracted facts are promoted to `MemoryFact` rows for the conversation's user; a second conversation of the SAME user reads them via `UserMemoryService::activeFactsForUser`; another user's memory is unaffected. Build with the AI test setup (user + `Conversation` + `Message` factories; call `app(MemoryService::class)->createSummarySnapshot($conversation)` directly to avoid depending on live LLM — inject a fake facts source: `createSummarySnapshot` calls `extractFacts`, which uses a chat agent; use the existing `chatAgentFactory` closure injection point in `MemoryService` to return canned facts, mirroring how other AI tests fake the agent).

- [ ] **Step 2: Implement.** In `createSummarySnapshot`, after the `ConversationSummary` is created, call `app(UserMemoryService::class)->rememberFacts($conversation->user, $facts, sourceMessageId: null)` (the `$facts` already computed in that method). Keep everything else unchanged. Guard: only when `$conversation->user` is present.

- [ ] **Step 3:** run the test (PASS), pint, commit: `feat(ai): promote conversation facts into persistent user memory`.

---

### Task 4: inject active user facts into the assistant prompt

**Files:**
- Modify: `Modules/AI/app/Services/MemoryService.php` (`getContextForNewMessage`) OR `ChatService` injection point — pick the one that keeps `getContextForNewMessage` the single injection source.
- Test: `Modules/AI/tests/Feature/UserMemoryInjectionTest.php`

**Interfaces:**
- Consumes: `UserMemoryService::activeFactsForUser` (Task 2).

- [ ] **Step 1: Test** — a new conversation for a user with active facts produces a memory context that includes a bounded "user memory" block listing those facts; a user with none gets no such block; another user's facts never appear. Assert on the string returned by `getContextForNewMessage` (fake/seed `MemoryFact` rows directly, no LLM needed).

- [ ] **Step 2: Implement.** Extend `getContextForNewMessage($conversation)` to prepend a labeled, bounded block built from `UserMemoryService::activeFactsForUser($conversation->user)` (respecting `max_injected_facts`) to the existing summary context. Keep it clearly delimited (e.g. a "Known facts about the user:" header) and gated by `memory_enabled` + the existing summary config; empty facts → no block. Facts are inserted as plain context, never as instructions.

- [ ] **Step 3:** run the test (PASS), pint, commit: `feat(ai): inject active user facts into assistant memory context`.

---

### Task 5: config, erasure, and RAG docs

**Files:**
- Modify: `Modules/AI/config/config.php` (add `ai.features.memory` keys)
- Verify: user delete cascades facts (FK `cascadeOnDelete` from Task 1); conversation delete leaves user facts intact (they are user-scoped, not conversation-scoped) — add a feature assertion.
- Modify: `Modules/AI/docs/rag/MODULE.md` (+ create `Modules/AI/docs/rag/USER_MEMORY.md`)
- Test: `Modules/AI/tests/Feature/UserMemoryErasureTest.php`

- [ ] **Step 1: Config** — add under `ai.features`:
```php
'memory' => [
    'persistent_user_facts' => env('AI_MEMORY_PERSISTENT_FACTS', true),
    'fact_ttl_days' => env('AI_MEMORY_FACT_TTL_DAYS', 0), // 0 = no expiry
    'max_injected_facts' => env('AI_MEMORY_MAX_INJECTED_FACTS', 20),
],
```
Gate Task 3's promotion and Task 4's injection behind `ai_config_bool('ai.features.memory.persistent_user_facts', true)`. Document the three env vars in `Modules/AI/README.md` (AGENTS rule: new env vars → module README).

- [ ] **Step 2: Erasure test** — deleting the user removes their facts (cascade); deleting a conversation does NOT remove the user's facts. Assert both.

- [ ] **Step 3: Docs** — `USER_MEMORY.md` (RAG section model): what persistent user memory is (cross-session facts, user-scoped), how facts are extracted/promoted/deduped/expired, how they are injected (bounded, as data not instructions), config keys, isolation + no-secrets guarantees, erasure. One-line pointer from `MODULE.md`. Note the Core Graph upgrade path and the memory-recall eval (deferred) per the spec.

- [ ] **Step 4:** run the erasure test (PASS), pint, commit: `feat(ai): user-memory config, erasure guarantees, docs`. Add the plan's `**Documented in:**` line naming `USER_MEMORY.md` (AGENTS closed-plan rule).

---

## Final verification
- [ ] `php artisan test --compact Modules/AI/tests/Unit/Models/MemoryFactTest.php Modules/AI/tests/Unit/Services/UserMemoryServiceTest.php Modules/AI/tests/Feature/UserMemory*Test.php`
- [ ] `vendor/bin/pint --dirty --format agent` clean.

## Out of scope (per spec)
LLM-assisted semantic contradiction reconciliation; Core Graph representation (upgrade path); multimodal/connector ingestion; cross-user sharing; the memory-recall eval slice (defined in the spec, built with/after R1b Level-2).

## Notes for the executor
- Facts are already extracted and stored in `ConversationSummary.facts` today; Task 3 only *promotes* them to the per-user store, it does not add a new extraction call.
- No tenant column: `user_id` is the isolation key (AI tables are Global-scope). If AI tenant scoping ever lands, add a tenant column to `ai_memory_facts` and to `scopeActiveForUser`.
