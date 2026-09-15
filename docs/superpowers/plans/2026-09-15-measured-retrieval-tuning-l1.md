---
status: open
created_on: 2026-09-15
---
# Measured Retrieval Tuning (L1) — Implementation Plan

> **Moving on delivery.** This subject names backend files only, so this document and its counterpart belong in `laraplate/docs/superpowers/`. Keep working here: the move happens once the work is done, links and indexes repaired on both sides, so no reference is lost mid-flight. See `Where specs and plans live` in the stack `AGENTS.md`.

> **For agentic workers:** implement task by task, in order. Every task is test-first: write the
> failing test, run it, implement, run it green, `vendor/bin/pint --dirty`, commit inside the
> owning submodule.

**Goal:** make ranking parameters come from a committed, measured profile selected per query class,
switchable from Settings, with L0 as the exact fallback.

**Architecture:** a post-planner step in `AdvancedSearchService` merges a config profile into
`plan.ensemble` and `plan.ranking`; the fusion math is extracted so an offline tuner can re-fuse
recorded per-strategy orderings without re-querying the engine.

**Tech Stack:** PHP 8.5, Laravel 12, nwidart modules, Pest.

**Spec:** `docs/superpowers/specs/2026-09-15-measured-retrieval-tuning-l1-design.md` (this repository)

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`; braces always; explicit parameter and
  return types; PHPDoc over inline comments; English identifiers and comments.
- `Modules/{Core,AI,CMS,SAO}` are independent submodules on `master`: commit each task inside its
  own submodule. The spec and this plan live in the stack-root repo.
- **Tuning off must be byte-identical to today.** Any task that changes an emitted plan or a score
  while the setting is false is wrong, and Task 4's equality test exists to catch exactly that.
- The tuner writes its own report and nothing else. It never edits `config/search_tuning.php`.
- Run the narrowest tests: `php artisan test --compact <path>`; `vendor/bin/pint --dirty` before
  finishing each task.

---

### Task 1: `QueryClass` enum and derivation

**Files:**
- Create: `Modules/Core/app/Search/Enums/QueryClass.php`
- Modify: `Modules/Core/app/Search/Services/SearchQueryAnalyzer.php` (expose the derivation, or add
  `QueryClass::fromAnalysis(SearchQueryAnalysis $analysis)` reading the existing counts)
- Test: `Modules/Core/tests/Unit/Search/QueryClassTest.php`

**Interfaces:**
- Consumes: `SearchQueryAnalysis` (meaningful token count, protected token count, stopword signal).
- Produces: `QueryClass::{Identifier, ShortKeyword, MultiTerm, NaturalLanguage}`.

- [ ] **Step 1: Failing test.** One case per class, using the analyzer on real strings:
      `INV-1042 fattura` → `Identifier`; `Mario Rossi` → `ShortKeyword`;
      `fatture fornitori scadute` → `MultiTerm`;
      `come faccio ad annullare una fattura già inviata` → `NaturalLanguage`.
      Add the precedence case: a protected token wins even in a long query.
- [ ] **Step 2: Run, confirm fail** (enum absent).
- [ ] **Step 3: Implement** the enum with `fromAnalysis()`, in the precedence order of the spec.
      Do not add a second tokenizer: read what the analyzer already produced.
- [ ] **Step 4: Run, confirm pass.**
- [ ] **Step 5: Pint + commit** in `Modules/Core`.

---

### Task 2: `search_adaptive_tuning` setting

**Files:**
- Modify: `Modules/Core/database/seeders/CoreDatabaseSeeder.php` (seed the row, group `search`)
- Test: `Modules/Core/tests/Feature/Settings/SearchTuningSettingTest.php`

**Interfaces:**
- Produces: a `Setting` row `{name: 'search_adaptive_tuning', value: false, type: Boolean,
  group_name: 'search'}`, read via `PerModelSettingResolver::boolean('search_adaptive_tuning', false)`.

- [ ] **Step 1: Failing test.** After seeding, the resolver returns `false`; after flipping the row
      and clearing the group cache, it returns `true`.
- [ ] **Step 2: Run, confirm fail.**
- [ ] **Step 3: Implement** the seeder entry following the existing `seedSoftDeletedModel()` shape
      (name, value, encrypted false, choices null, `SettingTypeEnum::Boolean`, group, description).
- [ ] **Step 4: Run, confirm pass**, and confirm the seeder suite is still green.
- [ ] **Step 5: Pint + commit** in `Modules/Core`.

---

### Task 3: Profile config and `RetrievalTuningProfile`

**Files:**
- Create: `Modules/Core/config/search_tuning.php`
- Create: `Modules/Core/app/Search/Services/RetrievalTuningProfile.php`
- Test: `Modules/Core/tests/Unit/Search/RetrievalTuningProfileTest.php`

**Interfaces:**
- Consumes: `config('search_tuning')`, `QueryClass`, the setting from Task 2.
- Produces: `apply(array $plan, QueryClass $class): array` returning the plan with `ensemble` and
  `ranking` merged, plus `meta.tuning = {applied, profile_version, query_class}`.

- [ ] **Step 1: Failing test.** Cover: setting off → returned plan `===` input plan; setting on →
      only `ensemble`, `ranking` and `meta.tuning` differ, `retrieval` untouched; unknown class →
      `default` entry; invalid profile (weight 1.7) → input plan returned, warning logged once;
      missing config → input plan returned.
- [ ] **Step 2: Run, confirm fail.**
- [ ] **Step 3: Implement.** The config ships with the L0 values in `default` so the first commit
      is a no-op by construction; per-class entries start empty and are filled by Task 7's report.
      Validation exactly as the spec lists. Keep the class `final readonly` with the resolver and
      a config repository injected.
- [ ] **Step 4: Run, confirm pass.**
- [ ] **Step 5: Pint + commit** in `Modules/Core`.

---

### Task 4: Wire into `AdvancedSearchService`

**Files:**
- Modify: `Modules/Core/app/Search/Services/AdvancedSearchService.php` (after
  `applyEngineCapabilities()`, before `resolveVector()`)
- Modify: `Modules/Core/app/Search/Services/EnsembleSearchService.php` (carry `plan.meta.tuning`
  into `AdvancedSearchResult->meta['tuning']`)
- Test: `Modules/Core/tests/Integration/Search/AdvancedSearchTuningTest.php`

**Interfaces:**
- Produces: `AdvancedSearchResult->meta['tuning']`.

- [ ] **Step 1: Failing test.** With the setting off, assert the plan handed to a spy
      `EnsembleSearchService` is **equal to** the plan `FallbackSearchPlanner` produced (the
      anti-drift assertion). With it on, assert the ensemble weights match the profile and that
      `meta['tuning']['query_class']` is the expected class.
- [ ] **Step 2: Run, confirm fail.**
- [ ] **Step 3: Implement** the single call site plus the meta passthrough.
- [ ] **Step 4: Run, confirm pass**, and run the whole `tests/Integration/Search` directory: no
      existing assertion may change.
- [ ] **Step 5: Pint + commit** in `Modules/Core`.

---

### Task 5: Extract `RankFusion` (behaviour-preserving)

**Files:**
- Create: `Modules/Core/app/Search/Services/RankFusion.php`
- Modify: `Modules/Core/app/Search/Services/EnsembleSearchService.php` (delegate
  `fuseStrategies()`, `minMaxNormalizeScores()`, `renormalizeWeightsForExecutedStrategies()`)
- Test: `Modules/Core/tests/Unit/Search/RankFusionTest.php`

**Interfaces:**
- Produces: `RankFusion::fuse(array $perStrategy, array $weights, float $agreementBoost, int $rrfK,
  float $rrfWeight): list<array{id, score, ...}>` — pure, no model, no engine, no container.

- [ ] **Step 1: Failing test.** Hand-computed fusion for two strategies and three ids, including
      the agreement bonus and the weight renormalization when one strategy is absent.
- [ ] **Step 2: Run, confirm fail.**
- [ ] **Step 3: Implement** by lifting the existing methods verbatim. No formula change.
- [ ] **Step 4: Run** the new test **and** the full `EnsembleSearchServiceTest`: every pre-existing
      assertion must pass untouched. Then run both application-content baseline gates: the
      artifacts must stay byte-identical.
- [ ] **Step 5: Pint + commit** in `Modules/Core`.

---

### Task 6: Configurable rerank blend, and remove the dead key

**Files:**
- Modify: `Modules/Core/app/Search/Services/EnsembleSearchService.php` (`rerankTopK()` reads
  `ranking.rerank_blend`, default `config('search.reranker.weight')`, itself defaulting to 0.60)
- Modify: `Modules/Core/config/search.php` (document `reranker.weight` as the blend; delete
  `features.ensemble`)
- Modify: `Modules/Core/README.md` (env block: `SEARCH_RERANKER_WEIGHT` becomes real, the commented
  `SEARCH_ENSEMBLE_ENABLED` line goes away)
- Test: `Modules/Core/tests/Integration/Search/EnsembleSearchServiceTest.php` (extend)

**Interfaces:**
- Behaviour: blend `score = fused * (1 - blend) + rerank * max * blend`, where `blend` defaults to
  `0.60`, reproducing today's hardcoded `0.4 / 0.6` exactly.

- [ ] **Step 1: Failing test.** Default blend reproduces the current scores (assert against the
      existing expected values); an explicit `ranking.rerank_blend = 0.0` returns the fused order.
- [ ] **Step 2: Run, confirm fail.**
- [ ] **Step 3: Implement**, and delete `search.features.ensemble` together with every mention of
      `SEARCH_ENSEMBLE_ENABLED` (config, README, `SEARCH_MATCHING_DEVELOPER.md`,
      `SEARCH_RETRIEVAL_PIPELINE.md`).
- [ ] **Step 4: Run, confirm pass**; re-run both baseline gates, byte-identical.
- [ ] **Step 5: Pint + commit** in `Modules/Core`.

---

### Task 7: `ai:tune-retrieval`

**Files:**
- Create: `Modules/AI/app/Console/TuneRetrievalCommand.php`
- Create: `Modules/AI/app/Services/ApplicationContent/Evaluation/RetrievalTuningService.php`
- Test: `Modules/AI/tests/Feature/TuneRetrievalCommandTest.php`
- Test: `Modules/AI/tests/Unit/Services/ApplicationContent/Evaluation/RetrievalTuningServiceTest.php`

**Interfaces:**
- Consumes: `PerStrategyEngineRetrieverInterface` (existing seam), the curated datasets,
  `RankFusion` from Task 5, `IrMetrics` from R3 Phase 2.
- Produces: a JSON report `{version, source, dataset, metric, candidates: [{params, metrics,
  per_class_metrics, delta_vs_committed}], winner}` plus a printed PHP profile block.

- [ ] **Step 1: Failing unit test** for `RetrievalTuningService`: given canned per-strategy
      orderings for three cases and a two-point grid, assert the candidate ranking by `ndcg_at_5`
      and that each case was retrieved **once** (the grid re-fuses, it does not re-query).
- [ ] **Step 2: Run, confirm fail.**
- [ ] **Step 3: Implement the service.** Per case: retrieve once (reranker off) to get
      `meta['per_strategy']`; for every candidate parameter set call `RankFusion::fuse()`, then
      `IrMetrics::atK()` against `expected_hit_ids`; aggregate overall and per `QueryClass`.
      Reranking is scored separately with one reranker-on pass, since its blend cannot be
      re-computed offline from fused scores alone.
- [ ] **Step 4: Failing feature test** for the command: invalid `--source` fails; a run writes the
      report and prints a profile block; assert no other file was touched.
- [ ] **Step 5: Implement the command** — `ai:tune-retrieval {--source=} {--dataset=} {--grid=}
      {--metric=ndcg_at_5} {--output=} {--force}`, mirroring `EvaluateApplicationContentCommand`
      (strict dataset load, atomic write, `JSON_PRESERVE_ZERO_FRACTION`, no overwrite without
      `--force`).
- [ ] **Step 6: Run both tests, confirm pass.**
- [ ] **Step 7: Pint + commit** in `Modules/AI`.

---

### Task 8: Profile regression gate

**Files:**
- Create: `Modules/AI/tests/Feature/RetrievalTuningProfileGateTest.php`
- Test: reuses `Modules/CMS/tests/Fixtures/application-content/cms-contents.json` and the SAO fixture

**Interfaces:**
- Asserts: with the setting **on** and the committed profile applied, the deterministic fixture
  evaluation is **not worse** than the committed baseline on `ndcg_at_5` and `recall_at_5`, per
  source, within a stated tolerance; and with the setting **off**, the baseline artifacts stay
  byte-identical.

- [ ] **Step 1: Write the gate** driving the same seam the baseline tests use.
- [ ] **Step 2: Run it** against the shipped L0-equivalent profile: it must pass as a no-op.
- [ ] **Step 3: Document the regeneration procedure** in the test docblock, mirroring
      `APP_CONTENT_BASELINE_REGEN`.
- [ ] **Step 4: Pint + commit** in `Modules/AI`.

---

### Task 9: Documentation (RAG docs are part of the feature)

**Files:**
- Modify: `Modules/Core/docs/rag/SEARCH_RETRIEVAL_PIPELINE.md` (tuning step, `meta['tuning']`,
  updated configuration reality-check table)
- Modify: `Modules/Core/docs/rag/SEARCH_MATCHING_USER.md` (one line: tuning changes fusion, not the
  `matching` preference)
- Modify: `Modules/Core/README.md` (the new setting and the `search` settings group)
- Modify: `Modules/AI/docs/rag/MODULE.md` (`ai:tune-retrieval` next to the evaluate commands)
- Modify: `docs/ricerca-spiegazione-semplice.md` (L0/L1 section: what the switch now does)

- [ ] **Step 1: Update each file**, keeping the existing voice (English in the module docs, Italian
      in the stack explainer).
- [ ] **Step 2: Re-run** `php artisan ai:index-rag-docs` so the assistant corpus picks up the
      changes.
- [ ] **Step 3: Commit** in each owning submodule, then update this plan's front-matter to
      `status: completed`.

---

## Post-implementation: the actual tuning run (manual, not a code task)

The tasks above ship the mechanism with an L0-equivalent profile. Producing the real numbers is a
separate, deliberate act:

1. Seed and index the dev corpus, with Elasticsearch and embeddings available.
2. `php artisan ai:tune-retrieval --source=cms.contents --dataset=... --output=...`, same for
   `sao.tickets`.
3. Read the per-class table. Accept a candidate only if it wins **both** overall and on the class it
   targets; a candidate that trades one class against the total is overfitting.
4. Paste the winning profile into `config/search_tuning.php`, bump `version`, cite the report path.
5. Re-run Task 8's gate, then flip `search_adaptive_tuning` on in an environment and watch
   `meta['tuning']` on real responses before enabling it more widely.

## Self-Review notes

- The switch is reversible without a deploy, which is the point of using Settings.
- The anti-drift assertion in Task 4 is the load-bearing test of the whole plan: it is what lets
  this ship without risking the current behaviour.
- Task 5 is the only refactor of code that is currently working and measured; it is behaviour
  preserving by construction and guarded by two baseline gates.
- Nothing here reads user behaviour. L2 stays blocked on telemetry that does not exist yet, and the
  spec says so explicitly so the next reader does not mistake L1 for learning.
