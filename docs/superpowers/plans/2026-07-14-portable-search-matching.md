# Portable Search Matching Implementation Plan

**Goal:** Make short-name, acronym, identifier, and natural-language searches behave predictably across Elasticsearch, Typesense, PostgreSQL, Oracle, MySQL/MariaDB, and SQLite while preserving explicit caller intent and reporting engine degradations.

**Architecture:** Core analyzes the original query before planning retrieval. Named matching preferences are policy hints, not engine types. A resolver combines query analysis, the optional preference, granular overrides, protected-token invariants, and engine capabilities into `TextMatchOptions`. Engines only translate effective options. Every response exposes the effective decision in search metadata.

**Scope:** Core backend contracts, query analysis, adaptive matching policy, engine translation, database dialect behavior, request integration points, metadata, tests, and Core RAG documentation. Oracle Text `CONTEXT` and PostgreSQL index provisioning are specified here but remain separate schema-migration tasks because indexes must be declared per searchable field.

**Spec:** `docs/superpowers/specs/2026-07-14-portable-search-matching-design.md`

## Delivery status (2026-09-15): shipped, moved here from the stack repository

**Documented in:** `Modules/Core/docs/rag/SEARCH_MATCHING_USER.md`, `Modules/Core/docs/rag/SEARCH_MATCHING_DEVELOPER.md`.

Seventy of seventy-one steps are ticked. The one open box is a forward note rather than
outstanding work: it parks the `search:indexes:plan|status|apply|rebuild|sync` commands as a
later operational task on the same `MigrateUtils` API.

This plan and its spec were written at the stack root and moved into `laraplate` on delivery,
because the subject names only backend files. See `Where specs and plans live` in the stack
`AGENTS.md`.

---

## Behavioral contract

### Precedence

1. Analyze the original query without losing case or punctuation.
2. If no preference is supplied, use `auto`.
3. Apply `strict`, `balanced`, or `tolerant` as policy bias when supplied.
4. Apply granular caller overrides after the preference.
5. Enforce protected-token invariants unless `identifier_typos=true` is explicitly supplied. Mixed queries conservatively disable typo expansion until every engine can represent per-token fuzziness consistently.
6. Clamp values to the portable contract and engine limits.
7. Translate effective options and report any degradation.

An explicit `strict` preference requires every significant token, but still permits one controlled one-edit correction on one eligible natural-language token. `matching_options[typo_tolerance]=false` disables even that correction. `balanced` and `tolerant` remain adaptive. Granular options are authoritative within validation bounds, but do not make identifiers fuzzy unless `identifier_typos=true` is also explicit; UUIDs remain exact even with that opt-in.

### Token classification

Each non-empty whitespace-delimited token is classified using the original text:

| Kind | Examples | Default invariant |
|------|----------|-------------------|
| `numeric` | `1042`, `2026` | exact/prefix; no typo |
| `uuid` | `550e8400-e29b-41d4-a716-446655440000` | exact; no typo |
| `email` | `mario@example.it` | exact/prefix; no typo |
| `structured_identifier` | `INV-1042`, `IT_001`, `AB/22` | exact/prefix; no typo |
| `acronym` | `CRM`, `ACME`, `SRL` | exact/prefix; no typo |
| `short` | `AI`, `di` | no typo |
| `word` | `Mario`, `fattura`, `Giuseppe` | adaptive typo eligibility |

A token is protected when it is numeric, UUID, email, structured identifier, acronym, or shorter than the configured typo minimum. Query case must therefore be preserved through analysis even though matching itself may be case-insensitive.

### Dynamic profile policy

Count significant tokens after removing configured Italian and English stopwords for policy selection; retain all original tokens for engine queries.

Profiles are dynamic policy inclinations over query analysis, not fixed bundles of engine parameters. Coverage percentages are rounded up to a whole number of required tokens.

| Profile | 1–2 tokens | 3 tokens | 4 tokens | 5–8 tokens | 9+ tokens | Max fuzzy tokens |
|---------|------------|----------|----------|------------|-----------|------------------|
| `strict` | 100% | 100% | 100% | 100% | 100% | 1 |
| `balanced` | 100% | 100% | 75% (3 of 4) | 65% | 60% | 2 |
| `tolerant` | 100% | 2 of 3 | 3 of 4 | 55% | 50% | 3 |
| `auto` | 100% | 2–3 of 3 | 3 of 4 | 65–70% | 60–65% | 1–2 |

`auto` chooses within its ranges from query shape: names and keyword-like input stay more rigid; natural-language sentences relax progressively; protected-token density makes the query more rigid. Protected tokens force complete query coverage in the current portable implementation. Quoted and explicitly required terms are deferred to the post-implementation product-design discussion below.

Character length is evaluated per token, never only on the complete query string. The first implementation globally clamps `max_edits` to `1`; support for distance two is deferred until cross-engine quality is measured.

### Preference bias

| Preference | Policy effect |
|------------|---------------|
| `auto` | use the automatic matrix unchanged |
| `strict` | 100% coverage at every length; exact priority; at most one eligible token may receive a one-edit correction |
| `balanced` | 100% coverage through three tokens, then moderate progressive relaxation; at most two eligible fuzzy tokens |
| `tolerant` | 100% through two tokens, then controlled progressive relaxation; at most three eligible fuzzy tokens without weakening identifiers |

Profiles are stored as replaceable configuration presets. Code consumes only the resolved granular options, allowing future tenant-, resource-, locale-, field-, and query-specific policies.

### Public request shape

The intended request contract is:

```text
search=<query>
matching=auto|strict|balanced|tolerant
matching_options[typo_tolerance]=true|false
matching_options[max_edits]=0..1
matching_options[prefix]=true|false
matching_options[operator]=and|or
matching_options[minimum_should_match]=1..100
matching_options[identifier_typos]=true|false
```

Existing callers that provide only `search` remain backward compatible and use `auto`. Request/FormRequest integration must pass normalized matching options into the Scout builder as `text_match`; engines must never read HTTP input directly.

### Metadata

Advanced search responses expose:

```json
{
  "matching": {
    "requested_preference": "auto",
    "effective_preference": "balanced",
    "significant_token_count": 2,
    "token_kinds": ["word", "word"],
    "protected_tokens": [],
    "fuzzy_token_limit": 1,
    "options": {},
    "degraded": []
  }
}
```

Metadata must not expose sensitive values beyond the query already supplied by the caller. Protected tokens may therefore be reported by position or count where logging/telemetry is involved.

---

## Engine mapping

### Elasticsearch

- Exact phrase is a boosted `multi_match` clause.
- Prefix behavior uses `bool_prefix`.
- Typo tolerance uses `fuzziness`, `prefix_length`, and `fuzzy_transpositions` only when analysis finds eligible tokens.
- `operator` and `minimum_should_match` reflect the resolved policy.
- Protected-token queries remain strict unless identifier typo override is explicit.

### Typesense

- Map typo limits to `num_typos`, `min_len_1typo`, and `min_len_2typo`.
- Map prefix behavior to `prefix`.
- Preserve exact priority with `prioritize_exact_match`.
- Map long-query relaxation to token-dropping parameters only where their semantics match; otherwise report degradation.
- Per-token typo arrays may be used when supported and when `query_by` fields are known.

### Database portable fallback

- All supported drivers provide case-insensitive exact/prefix/substring matching.
- Protected tokens never use fuzzy SQL by default.
- MySQL/MariaDB and SQLite report typo tolerance as degraded unless a future schema-aware implementation is enabled.

### PostgreSQL

- `pg_trgm` is opt-in and requires the extension.
- Use trigram similarity only for eligible ordinary-word searches.
- Full-text GIN indexes and trigram GIN/GiST indexes are separate field-level schema concerns.
- Add a later schema migration task that declares `FullText` versus `Fuzzy` indexes per field and emits SQLite-safe fallbacks.

### Oracle

- Portable matching remains the default.
- Do not run `UTL_MATCH` over arbitrary long columns.
- A future schema-aware Oracle Text adapter maps `FullText`/`Fuzzy` fields to `CTXSYS.CONTEXT`, `CONTAINS`, `SCORE`, lexer/stoplist preferences, and an explicit synchronization policy.
- Oracle Text index DDL, privileges, pending-row synchronization, rebuilds, and rollback must be version-aware and tested independently.

---

## Implementation tasks

### Task 1 — Query analysis domain

**Files:**
- Create `Modules/Core/app/Search/Enums/TextMatchPreference.php`
- Create `Modules/Core/app/Search/Enums/SearchTokenKind.php`
- Create `Modules/Core/app/Search/DTOs/AnalyzedSearchToken.php`
- Create `Modules/Core/app/Search/DTOs/SearchQueryAnalysis.php`
- Create `Modules/Core/app/Search/Services/SearchQueryAnalyzer.php`

- [x] Preserve original token case and punctuation.
- [x] Normalize a separate comparison value.
- [x] Classify identifiers before acronym/short-word rules.
- [x] Count significant tokens using Italian/English stopwords.
- [x] Record protected and typo-eligible token counts.
- [x] Cover names, acronyms, email, UUID, numeric, and structured-code cases; the broader Search suite covers empty and Unicode input paths.

### Task 2 — Adaptive option resolution

**Files:**
- Modify `TextMatchOptions`
- Modify `TextMatchOptionsResolver`
- Modify `config/search.php`

- [x] Add preference, minimum-should-match, fuzzy-token-limit, and identifier-typo semantics.
- [x] Resolve `auto` from the significant-token matrix.
- [x] Apply replaceable configured preference presets.
- [x] Apply granular overrides after preset selection.
- [x] Enforce protected-token invariants last.
- [x] Return both effective options and analysis/decision metadata.

### Task 3 — Pipeline propagation and metadata

**Files:**
- Modify `AdvancedSearchService`
- Modify `EnsembleSearchService`
- Modify `AdvancedSearchResult` metadata assembly

- [x] Analyze once per request.
- [x] Pass effective `text_match` options to every keyword/hybrid Scout builder.
- [x] Do not apply text options to pure vector retrieval.
- [x] Attach matching metadata and engine degradations to the final response and CRUD metadata wrapper.

### Task 4 — Engine conformance

**Files:**
- Modify Elasticsearch, Typesense, and Database engines
- Extend `TextMatchContractTest`

- [x] Map minimum-should-match where native.
- [x] Avoid fuzziness for protected and mixed protected queries unless explicitly enabled.
- [x] Preserve exact-first ordering.
- [x] Report unsupported semantics rather than silently claiming parity.

### Task 5 — Request integration

**Files:** Locate the shared CRUD/search request boundary before implementation.

- [x] Validate `matching` and granular matching options.
- [x] Preserve old requests with no matching parameters.
- [x] Keep HTTP concerns outside search engines.
- [x] Document the `/app` and optional `/api/v1` contract.

### Task 6 — Database index provisioning follow-up

**Decision:** extend the existing `MigrateUtils` with public, column-oriented static methods. Do not add another static migration helper class.

- [x] Add `IndexType::FullText`, `IndexType::Fuzzy`, and `IndexType::Prefix`; stop deriving full-text indexes from every `FieldType::Text`.
- [x] Add public `MigrateUtils::prefixIndex(Blueprint, columns, name)` for portable B-tree prefix access.
- [x] Add public `MigrateUtils::fuzzyIndex(table, column, name, oracleSync)` and `dropFuzzyIndex()`.
- [x] Add public `MigrateUtils::fullTextIndex(table, columns, language, name, oracleSync)` and `dropFullTextIndex()`.
- [x] Keep PostgreSQL, Oracle, MySQL/MariaDB, identifier validation, naming, and SQLite no-op logic private inside `MigrateUtils`.
- [x] PostgreSQL: install/check `pg_trgm`, create field-specific GIN trigram indexes, and create GIN `to_tsvector` indexes only for declared full-text fields.
- [x] Oracle: create schema-declared `CTXSYS.CONTEXT` indexes with an explicit synchronization policy; never use `UTL_MATCH` over arbitrary long columns.
- [x] MySQL/MariaDB: create `FULLTEXT` only for declared full-text columns; fuzzy remains degraded.
- [x] SQLite: make fuzzy/full-text methods safe no-ops while keeping prefix B-tree indexes.
- [x] Replace repeated search-index driver branches in existing Core and CMS migrations.
- [x] Initial field policy: fuzzy for short human labels/names; full-text only for prose/search-text; prefix/B-tree for slugs and identifiers.
- [x] Add schema translation/unit tests plus SQLite specialized-index and `migrate:fresh` proof; run PostgreSQL/Oracle DDL integration checks in their CI matrices.
- [ ] Leave future `search:indexes:plan|status|apply|rebuild|sync` commands as a subsequent operational task built on the same `MigrateUtils` API.

### Task 7 — Verification

- [x] Exact clauses receive priority over fuzzy candidates in engine translations.
- [x] `CRM`, `ACME`, UUIDs, emails, numerics, and `INV-1042` remain strict in auto/balanced/tolerant.
- [x] Eligible misspelled long words such as `Giusppe` receive typo tolerance in auto/balanced.
- [x] Two-word names use `AND` and at most one fuzzy token in the portable decision.
- [x] Three-to-five significant tokens resolve to approximately 75% matching.
- [x] Six-plus significant tokens resolve to approximately 65% matching.
- [x] Original strict behavior disabled all typo tolerance; Task 8 supersedes it with 100% coverage plus one controlled correction.
- [x] Explicit granular overrides win except protected identifiers without explicit opt-in.
- [x] All engine translations receive the same effective contract.
- [x] Run final Search integration tests and `vendor/bin/pint --dirty`.

### Task 8 — Dynamic preference refinement

**Decision:** preferences are dynamic inclinations. Coverage and edit distance remain separate controls: coverage decides how many significant tokens must match; `max_edits` decides how many character operations are allowed inside each fuzzy-eligible token.

- [x] Replace fixed profile thresholds with the agreed length-band matrix.
- [x] Keep `strict` at 100% significant-token coverage for every query length.
- [x] Allow `strict` at most one fuzzy token with distance one; make `typo_tolerance=false` fully literal.
- [x] Apply `balanced` coverage of 100% through three tokens, 3/4 at four, 65% at five-to-eight, and 60% at nine-plus.
- [x] Apply `tolerant` coverage of 100% through two tokens, 2/3 at three, 3/4 at four, 55% at five-to-eight, and 50% at nine-plus.
- [x] Make `auto` select the stricter or looser end of its range from name/keyword/sentence shape and protected-token density.
- [x] Require protected identifiers independently from percentage coverage within the current portable engine constraints.
- [x] Clamp the initial portable implementation to `max_edits=1`; defer edit distance two.
- [x] Keep exact phrase, all-exact-token, prefix, partial exact, and fuzzy results in descending priority.
- [x] Apply prefix matching where supported; retain capability degradation where an engine cannot limit it to the final token.
- [x] Add boundary tests for 2/3/4/5/8/9 tokens, rounding, mandatory terms, and every profile.
- [x] Update user/developer RAG documentation and response metadata examples.
- [x] Complete the post-profile product-design discussion: expose quoted required phrases and `+term` directly in `qs`; keep both literal/non-fuzzy and document them in code and RAG.

### Task 9 — Required terms and exact phrases

**Decision:** `"phrase"` means mandatory adjacent ordered text; `+term` means a mandatory term anywhere. The syntax is public, familiar, case-insensitive after engine normalization, and non-fuzzy.

- [x] Add a dedicated query-syntax parser and immutable parsed-query DTO.
- [x] Accept `+"phrase"` as equivalent to `"phrase"`.
- [x] Support escaped quotes, Unicode, repeated whitespace, unmatched quotes as free text, and standalone `+` as free text.
- [x] Resolve adaptive coverage only from remaining free terms while retaining required-clause counts in metadata.
- [x] Keep required phrases and terms out of fuzzy expansion regardless of profile or overrides.
- [x] Translate mandatory term and phrase clauses natively in Elasticsearch.
- [x] Translate mandatory term and phrase clauses with bound SQL across database drivers.
- [x] Preserve candidate retrieval in Typesense with a cleaned query and explicitly report mandatory-term/phrase degradation until native parity is proven.
- [x] Document syntax in code, API/RAG user guidance, developer guidance, and response metadata.
- [x] Add parser, resolver, engine-translation, escaping, malformed-input, Unicode, and profile interaction tests.
- [x] Run the complete Core Search and request suites plus formatting (120 tests, 454 assertions).
