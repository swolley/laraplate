# Search Modes and Strategy Resolution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (or executing-plans). Steps use checkbox (`- [ ]`) tracking.

**Goal:** Make the cost of a search a per-request decision by its caller instead of a global boolean decided at boot. `fast` is the default and executes Core's own cheap path; `deep` buys LLM planning, embedding, reranking and optional retries, and anything that prevents it degrades to `fast` with a stated reason rather than failing.

**Architecture:** Core gains `ISearchStrategyResolver` and a `SearchStrategy` value object, plus a resolver that ignores the mode and always returns its cheap implementations. AI replaces that one resolver and stops overriding `ISearchPlanner`, `IReranker`, `IQueryIntentParser` and `ITextEmbedder` globally. `AdvancedSearchService` takes the resolver rather than the components and asks per call.

**Tech Stack:** PHP 8.5, Laravel 12, Pest. No new dependencies.

**Spec:** `docs/superpowers/specs/2026-09-16-search-modes-and-strategy-resolution-design.md`

## Global Constraints

- Every PHP file `declare(strict_types=1);`; braces everywhere; explicit parameter and return types; `final` and `readonly` where the surrounding code uses them; constructor property promotion; `#[Override]` when overriding.
- **Core must never name an AI class.** The whole design fails the moment it does. Core knows one contract and its own implementations; who else implements it is the container's business.
- **A search never errors because a model was unavailable.** Every failure of the expensive path produces cheap results plus a `degraded_reason`. A test that asserts an exception reaching the caller is asserting a bug.
- `config()` outside config files, never `env()`. Caps that an operator should change at runtime go in Settings, not config.
- Tests: Pest, in the module that owns the code. Run `vendor/bin/pint --dirty --format agent` before finalising. Run the narrow set with `php artisan test --compact <path>`.
- Commits go inside the submodule that owns the change (`Modules/Core`, `Modules/AI`), never mixed.

**Verified touch points (read before coding):**
- `Modules/Core/app/Search/Services/AdvancedSearchService.php:40` — `search()`, whose first four statements are the four costly stages.
- `Modules/Core/app/Providers/SearchServiceProvider.php:51-54` — the `singletonIf` defaults (`HeuristicReranker`, `FallbackSearchPlanner`, `SimpleQueryIntentParser`). No Core default exists for `ITextEmbedder`.
- `Modules/AI/app/Providers/AIServiceProvider.php:110-118` — `registerSearchBindings()`, the four global overrides this plan removes.
- `Modules/Core/app/Services/Crud/CrudService.php:111` — `AdvancedSearchService` is an optional constructor dependency.
- `Modules/AI/app/Actions/IntelligentSearchAction.php` — `evaluateResults()` and `shouldRetry()` are what gets salvaged; the rest is retired.

---

### Task 0: measure, before writing any of the rest

**This is a gate, not a formality.** The design stands either way, but the number of modes depends on the numbers.

- [ ] **Step 1:** Instrument the four stages of `AdvancedSearchService::search()` with timings, emitted into the existing search meta behind a debug flag: intent parse, plan, vector, ensemble plus rerank.
- [ ] **Step 2:** Run `php artisan perf:crud` for a representative entity with the AI overlay on and off, and `php artisan perf:profile` on a search endpoint. Record the four numbers and the totals in the spec, under a new *Measurements* section with the date and the machine.
- [ ] **Step 3:** Decide from the numbers and write the decision in the spec:
  - if the cross-encoder dominates, evaluate a score cache first; it may recover most of the latency with none of this restructuring, in which case the rest of this plan is still worth doing but stops being urgent;
  - if the two LLM calls dominate, continue as planned, and record whether a third `balanced` mode (lexical plus vector, no LLM) should be built now rather than later, since that is what keeps semantic matching on the default path.

---

### Task 1: the contract and Core's resolver

**Files:**
- Create: `Modules/Core/app/Search/Contracts/ISearchStrategyResolver.php`
- Create: `Modules/Core/app/Search/DTOs/SearchStrategy.php`
- Create: `Modules/Core/app/Search/Services/CoreSearchStrategyResolver.php`
- Create: `Modules/Core/app/Search/Enums/SearchMode.php`
- Modify: `Modules/Core/app/Providers/SearchServiceProvider.php`
- Test: `Modules/Core/tests/Unit/Search/CoreSearchStrategyResolverTest.php`

- [ ] **Step 1: `SearchMode`** — a backed string enum, `Fast = 'fast'` and `Deep = 'deep'`, with `tryFrom()` used at the HTTP boundary so an unknown value degrades to `Fast` rather than throwing. An enum rather than a bare string so a third mode is added in one place.

- [ ] **Step 2: `SearchStrategy`** — `final readonly`, holding `applied_mode`, `planner`, `reranker`, `intent_parser`, nullable `embedder`, `max_retries`, nullable `degraded_reason`. One object, so an incoherent mixture cannot be constructed by accident, and so the caller has everything it needs to fill the response meta.

- [ ] **Step 3: `ISearchStrategyResolver`** — one method, `resolve(SearchMode $mode): SearchStrategy`.

- [ ] **Step 4: `CoreSearchStrategyResolver`** — takes Core's three implementations, returns them whatever it is asked for, `embedder: null`, `max_retries: 0`, and `degraded_reason: null` for `Fast` or `'mode_unavailable'` for anything else. Add a comment saying the mode is ignored deliberately: the next reader will otherwise assume it is a stub.

- [ ] **Step 5: Register** with `singletonIf` alongside the existing three, so a module may substitute it.

- [ ] **Step 6: Test** — asked for `Deep` with no AI installed, it returns Core's implementations, `applied_mode` `fast`, and `mode_unavailable`; asked for `Fast`, the same components and no reason.

- [ ] **Step 7:** run the test (PASS), pint, commit in `Modules/Core`: `feat(search): strategy resolver contract with a cheap default`.

---

### Task 2: `AdvancedSearchService` asks per call

**Files:**
- Modify: `Modules/Core/app/Search/Services/AdvancedSearchService.php`
- Modify: `Modules/Core/app/Search/DTOs/AdvancedSearchResult.php` (meta)
- Test: `Modules/Core/tests/Unit/Search/AdvancedSearchServiceModeTest.php`

**This is the substantive refactor.** The parameter is the easy half: what matters is that the service stops receiving `ISearchPlanner`, `IReranker`, `IQueryIntentParser` and `ITextEmbedder` in its constructor. While it does, the expensive implementations are wired in before any request parameter can be read, and no per-request decision is possible.

- [ ] **Step 1: Test first** — with a resolver double returning a cheap strategy for `Fast` and an expensive one for `Deep`, assert that `search(..., mode: Fast)` never touches the expensive doubles, and that the result meta carries `mode_requested`, `mode_applied`, `degraded_reason` and `retries_used`. The first assertion is the whole point of the task: write it and watch it fail.

- [ ] **Step 2: Implement.** Constructor takes `ISearchStrategyResolver`. `search()` gains `SearchMode $mode = SearchMode::Fast` and `?int $retries = null`, resolves the strategy first, and uses `$strategy->planner`, `$strategy->intent_parser`, `$strategy->reranker` and `$strategy->embedder` from there. When `$strategy->embedder` is null, skip the vector stage entirely rather than passing a null vector down.

- [ ] **Step 3: Meta.** `AdvancedSearchResult` carries a `search` meta block with the four keys from the spec. It is populated on every path, including the unsupported-driver early return, so a client never has to guess.

- [ ] **Step 4:** run the tests (PASS), pint, commit in `Modules/Core`: `feat(search): resolve the search strategy per request`.

---

### Task 3: AI decorates, and stops overriding

**Files:**
- Create: `Modules/AI/app/Search/AiSearchStrategyResolver.php`
- Modify: `Modules/AI/app/Providers/AIServiceProvider.php`
- Modify: `Modules/AI/app/Services/CrossEncoderService.php`, `Modules/AI/app/Services/SearchEmbedder.php`
- Test: `Modules/AI/tests/Integration/AiSearchStrategyResolverTest.php`

- [ ] **Step 1: The decorator.** `AiSearchStrategyResolver` takes `CoreSearchStrategyResolver` as its fallback and delegates to it for anything that is not `Deep`, and for `Deep` when `ai_config_bool('ai.features.search_orchestration.enabled', true)` is false, with `degraded_reason: 'search_orchestration_disabled'`. Only for an enabled `Deep` does it build the expensive set.

- [ ] **Step 2: Remove the four overrides** from `registerSearchBindings()` and register the resolver instead: `singleton(ISearchStrategyResolver::class, AiSearchStrategyResolver::class)`. **This is the line that stops every CRUD search paying for AI.** The expensive classes stay bound by their own names so the resolver can build them; what goes away is their substitution for the Core contracts.

- [ ] **Step 3: Runtime degradation.** `ISearchPlanner::safePlan()` is already named for not exploding. Give the reranker and the embedder the same property: on transport failure, timeout or a non-2xx from the cross-encoder microservice, fall back to the cheap behaviour (identity ordering for the reranker, no vector for the embedder) and surface a reason rather than throwing. `AdvancedSearchService` must not learn that language models exist in order to defend itself against them.

- [ ] **Step 4: Test** — `Fast` with AI installed returns Core's components, and asserts that the AI doubles were never constructed; `Deep` with the switch off degrades with the right reason; `Deep` with a cross-encoder that throws still returns results, with `degraded_reason` set.

- [ ] **Step 5:** run the tests (PASS), pint, commit in `Modules/AI`: `feat(ai): overlay search through one strategy resolver`.

---

### Task 4: retries, and retiring `IntelligentSearchAction`

**Found on 2026-09-17, while triaging PHPStan: the class is already broken, and has
been for months.** `runSearchPipeline()` calls `EnsembleSearchService::search()`
against a signature that no longer exists:

```php
// IntelligentSearchAction.php:93
return $this->ensemble->search($intent, $vector, $final_query, $plan, $index);

// EnsembleSearchService::search() as it is today
search(Model $model, string $query, array $plan, ?array $vector,
       int $page, int $perPage, …): AdvancedSearchResult
```

All five arguments are of the wrong type, two required parameters are missing, and
the return value is an object the caller indexes as `array{results, meta}`. With
`declare(strict_types=1)` that is a TypeError on the first line executed. It never
fires because nothing calls it: the class is referenced only by its own two tests,
and both assert `toBeInstanceOf` and nothing else.

This does not change the plan, it confirms it. Do not repair the call as part of
Task 4 — salvage `evaluateResults()` and `shouldRetry()` as Step 1 says, then delete
the rest. It does mean the class cannot be trusted as a reference for how search is
meant to work: read `AdvancedSearchService`, not this.

**Files:**
- Modify: `Modules/Core/app/Search/Services/AdvancedSearchService.php`
- Create: `Modules/Core/app/Search/Services/SearchQualityEvaluator.php`
- Delete: `Modules/AI/app/Actions/IntelligentSearchAction.php` and `Modules/AI/tests/Integration/IntelligentSearchActionTest.php`
- Test: `Modules/Core/tests/Unit/Search/SearchRetryTest.php`

- [ ] **Step 1: Salvage before deleting.** Port `evaluateResults()` and `shouldRetry()` out of `IntelligentSearchAction` into `SearchQualityEvaluator` in Core, keeping the behaviour and adding types. Read what the methods do; do not reimplement them from their names. This is the only part of that class worth keeping, and it is the part that makes a retry mean something instead of being a repeated identical query.

- [ ] **Step 2: Drive retries from the strategy.** When `$strategy->max_retries > 0` and the evaluator judges the results poor, re-plan and search again, up to the cap, recording `retries_used` in the meta. `Fast` has `max_retries: 0`, so the loop is not reachable there.

- [ ] **Step 3: Delete the Action and its test.** Deleting a test file needs approval under AGENTS; it is granted here by the spec, which retires the class. Record in the commit message that `evaluateResults`/`shouldRetry` moved to `SearchQualityEvaluator` rather than being lost, and say why the rest went: pagination is the CRUD layer's, and its cache was keyed on query plus index with no authorization context, which would let two users with different ACL trade results.

- [ ] **Step 4:** run the tests (PASS), pint, commit in both submodules separately: `feat(search): quality-driven retries` in Core, `refactor(ai): retire IntelligentSearchAction` in AI.

---

### Task 5: the HTTP surface, with its cost controls

**Files:**
- Modify: the search Form Request and the CRUD search controller path
- Modify: `Modules/Core/app/Services/Crud/CrudService.php`
- Modify: `Modules/Core/app/Providers/RouteServiceProvider.php` (named rate limiter)
- Test: `Modules/Core/tests/Feature/Search/SearchModeRequestTest.php`

- [ ] **Step 1: Parameters.** `mode` validated through `SearchMode::tryFrom()`, defaulting to `Fast` when absent or unknown. `retry` an integer, clamped to the maximum held in Settings, `0` when absent. Both travel to `AdvancedSearchService` as **explicit named parameters**, not inside an options bag: a value that changes latency and cost by an order of magnitude should be visible in every signature it passes through.

- [ ] **Step 2: The cap in Settings.** A Core setting for the maximum retries, read at request time, so an operator can lower it without a deploy. Config would need a release; this is exactly what the Settings table is for.

- [ ] **Step 3: Permission.** `Deep` requires a permission, following the existing permission conventions and seeded like the others. A caller without it gets `Fast` and `degraded_reason: 'not_authorized'` — **not** a 403. Whether the caller may spend money is not an error condition for the caller to handle, and the degradation path already exists.

- [ ] **Step 4: Rate limiter.** A named limiter keyed on the **user**, applied to deep searches specifically and tighter than ordinary reads. Per user rather than per route, because the cost follows the person. Exceeding it degrades to `Fast` with a reason, on the same path as everything else.

- [ ] **Step 5: Test** — absent parameter yields `fast`; an unknown mode yields `fast` without an error; `retry` above the Settings cap is clamped, not rejected; a user without the permission requesting `deep` gets results with `not_authorized`, status 200; the limiter degrades rather than refusing.

- [ ] **Step 6:** run the tests (PASS), pint, commit in `Modules/Core`: `feat(search): mode and retry parameters with their cost controls`.

---

### Task 6: documentation

**Files:**
- Modify: `Modules/Core/docs/rag/SEARCH_RETRIEVAL_PIPELINE.md`
- Modify: `Modules/AI/docs/rag/MODULE.md` (the *Perimeters* section describes the overlay this plan changes)
- Modify: `Modules/Core/README.md` and `Modules/AI/README.md` for any new env var
- Modify: `docs/superpowers/specs/2026-09-12-mcp-server-design.md` (the `search` tool passes `mode=deep`)

- [ ] **Step 1:** Document the modes as a contract for callers: what each buys, that the default is `fast`, that degradation is normal and reported in `meta.search`, and that a client should read `mode_applied` rather than assume it got what it asked for.
- [ ] **Step 2:** Update *Perimeters* in the AI module docs: search is still Core's, but AI now overlays exactly one contract instead of four, and only for `deep`.
- [ ] **Step 3:** Add the `**Documented in:**` line to this plan naming those documents, per the AGENTS closed-plan rule, and a `## Delivery status (date)` section recording anything deliberately not built, in particular whether a third mode was added.
- [ ] **Step 4:** pint, commit in each submodule that changed.

---

## Final verification
- [ ] `php artisan test --compact Modules/Core/tests/Unit/Search Modules/Core/tests/Feature/Search Modules/AI/tests/Integration/AiSearchStrategyResolverTest.php`
- [ ] `php artisan perf:crud` compared against the Task 0 baseline: the default path must be measurably faster than before, which is the entire point of the work.
- [ ] `vendor/bin/pint --dirty --format agent` clean in both submodules.

## Out of scope (per spec)
A third `balanced` mode unless Task 0 says to build it now; a cache for deep results; ranking parameter tuning, which belongs to the L1 design; the UI affordance, which is `laraplate-ui`; moving the planner off the synchronous path.

## Notes for the executor
- The single most valuable line in this plan is removing the four overrides in `registerSearchBindings()`. Everything else exists to make that removal safe rather than a feature regression.
- `fast` loses the vector stage, because `ITextEmbedder` has no Core default. That is a relevance regression on ordinary search, not only a latency win, and it is the reason Task 0 exists and the reason `mode` is a string rather than a boolean.
- If you find yourself writing `if ($module_ai_installed)` anywhere in Core, stop: the container already answers that question, and answering it again in Core is the design error this plan removes.
