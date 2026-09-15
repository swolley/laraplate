# Portable Adaptive Search Matching Design

**Status:** Approved for implementation

**Date:** 2026-07-14

**Plan:** `docs/superpowers/plans/2026-07-14-portable-search-matching.md`

## Problem

Laraplate supports Elasticsearch, Typesense, and database search, but native defaults produce different typo, prefix, token, and ranking behavior. Short queries are especially ambiguous: one or two words often represent proper names, acronyms, SKUs, invoice numbers, emails, or other identifiers where aggressive fuzziness is harmful. Longer natural-language queries benefit from greater recall and may tolerate missing or misspelled terms.

The system needs one engine-independent decision before translation. The same requested semantics must reach every engine, and unsupported semantics must be reported as degraded instead of silently changing behavior.

## Decisions

### Granular effective contract

Engines consume `TextMatchOptions`, never profile names or HTTP parameters. Options include typo tolerance, maximum edits, prefix behavior, prefix length, minimum typo lengths, exact-match boost, boolean operator, minimum-should-match percentage, transpositions, similarity threshold, fuzzy-token limit, and identifier-typo permission.

### Preferences are policy bias

`auto`, `strict`, `balanced`, and `tolerant` are replaceable dynamic policy inclinations. They are not engine enums and do not directly map to Elasticsearch or Typesense parameters.

- `auto` selects behavior from query analysis.
- `strict` requires 100% significant-token coverage at every length and permits at most one controlled one-edit correction unless typo tolerance is explicitly disabled.
- `balanced` preserves auto safeguards and moderate recall.
- `tolerant` increases recall only for eligible ordinary words.

Tenant, resource, locale, field, and user-specific preset resolution may be added later without changing engine contracts.

### Analyze individual tokens

Whole-query character length is insufficient. The analyzer preserves original case and punctuation, produces a separate normalized form, removes stopwords only for policy counting, and classifies every token as numeric, UUID, email, structured identifier, acronym, short token, or ordinary word.

Numeric values, UUIDs, emails, structured identifiers, acronyms, and tokens below the typo minimum are protected. Protected tokens do not become fuzzy merely because the query is long or the preference is tolerant. Until every engine can express per-token typo rules consistently, a mixed query containing a protected token conservatively disables typo expansion for the complete keyword query unless identifier fuzziness is explicitly enabled.

### Adaptive auto behavior

- Zero significant tokens: strict.
- One protected token: strict with exact priority and safe prefix matching.
- One or two ordinary words: 100% coverage, exact priority, and controlled one-edit correction.
- Three or four keyword-like terms: use the balanced end of the dynamic range.
- Five or more natural-language terms: progressively relax coverage toward the configured auto range.
- A high protected-token density makes auto more rigid; protected tokens remain mandatory.

The agreed coverage matrix is: strict 100% at every length; balanced 100% through three terms, 3/4 at four, 65% at five-to-eight, and 60% at nine-plus; tolerant 100% through two, 2/3 at three, 3/4 at four, 55% at five-to-eight, and 50% at nine-plus. Auto selects between balanced and tolerant ranges from query shape. Relaxing a long query primarily means allowing fewer required tokens. The initial portable limit remains one edit per fuzzy token.

### Caller precedence

Resolution order is:

1. query analysis;
2. automatic policy;
3. explicit preference;
4. explicit granular overrides;
5. protected-token invariants;
6. validation/clamping;
7. engine capability degradation.

### Explicit required query syntax

The public `qs` string supports two familiar operators:

- `"Mario Rossi"` is a required literal phrase: tokens must remain adjacent and in order.
- `+Mario` is a required literal term: it may occur anywhere and in any order relative to other terms.

Quoted phrases are already mandatory, so `+"Mario Rossi"` is accepted as an equivalent, redundant spelling. Both constructs are case-insensitive under the active engine normalization but never fuzzy. Required clauses are satisfied before profile coverage is evaluated over remaining free terms. Exact complete matches retain ranking priority.

`\"` represents a literal quote. An unmatched quote is treated as ordinary free text rather than producing an HTTP validation error. A standalone `+`, or `+` followed by whitespace, remains ordinary free text. Operators are parsed only from `qs`, never from filters or other request parameters.

The parser exposes only counts in response metadata (`required_term_count`, `required_phrase_count`); it does not duplicate query values. Engine adapters consume a parsed portable contract. Adapters that cannot enforce adjacency or mandatory terms must report the corresponding degradation rather than silently claiming parity.

Granular overrides are authoritative within bounds. Identifier fuzziness still requires the explicit `identifier_typos=true` override. This prevents a generic tolerant preference from corrupting codes or proper identifiers.

### Exact-first invariant

Exact phrases and exact tokens must rank ahead of fuzzy matches whenever the engine supports relevance scoring. Engines that cannot represent a numeric exact boost must preserve exact priority where possible and report the degradation.

### Observable decisions

Advanced search responses include matching metadata: requested/effective preference, significant-token count, token kinds, protected-token count, fuzzy-token limit, effective options, and engine degradations. Logs and telemetry use counts/positions rather than repeating potentially sensitive token values.

## Engine behavior

### Elasticsearch

Use a boosted exact phrase plus `multi_match`. Prefix queries use `bool_prefix`; eligible typo queries use `fuzziness`, `prefix_length`, and transpositions. Apply `operator` and `minimum_should_match` from the resolved contract.

### Typesense

Use `num_typos`, `min_len_1typo`, `min_len_2typo`, `prefix`, and `prioritize_exact_match`. Use token-dropping controls only when they preserve the resolved minimum-match intent. Report numeric exact-boost and unsupported boolean semantics as degraded.

### Portable database

All drivers provide case-insensitive prefix or substring matching. MySQL/MariaDB, SQLite, and Oracle do not claim portable typo parity.

PostgreSQL can opt into `pg_trgm` for eligible ordinary words. Extension and indexes are database changes. Trigram and full-text indexes must be declared per field; they must not be applied indiscriminately to identifiers or short labels.

Oracle `UTL_MATCH` is not used over arbitrary long searchable columns. A schema-aware Oracle Text adapter may later map declared full-text/fuzzy fields to `CTXSYS.CONTEXT`, `CONTAINS`, and `SCORE`, with explicit lexer, stoplist, synchronization, rebuild, and privilege management.

## Request contract

Existing requests with only `search` use `auto`. New optional parameters are:

```text
matching=auto|strict|balanced|tolerant
matching_options[max_edits]=0..2
matching_options[prefix]=boolean
matching_options[operator]=and|or
matching_options[minimum_should_match]=1..100
matching_options[identifier_typos]=boolean
```

Form Requests normalize these inputs and pass them to the search service. Search engines remain HTTP-agnostic.

## Security and performance

- Validate and clamp all caller values.
- Never interpolate query text or field names into raw SQL; values remain bound and fields come from searchable schema/model metadata.
- Avoid fuzzy expansion for short tokens and identifiers by default.
- Bound engine expansions through maximum edits, minimum lengths, fuzzy-token limits, and engine-native expansion limits.
- Database fuzzy functionality is opt-in where it requires extensions or specialized indexes.
- Do not expose original protected values redundantly in response diagnostics or logs.

## Database migration API

Search index DDL remains centralized in the existing `Modules\Core\Helpers\MigrateUtils`; no additional static helper class is introduced. The public API is column-oriented:

```php
MigrateUtils::prefixIndex($table, 'slug');
MigrateUtils::fuzzyIndex($tableName, 'name');
MigrateUtils::fullTextIndex($tableName, ['search_text'], language: 'italian');
```

`prefixIndex()` operates on a `Blueprint` during table creation. `fuzzyIndex()` and `fullTextIndex()` run after `Schema::create()` because PostgreSQL specialized indexes, Oracle Text `CONTEXT`, and MySQL/MariaDB `FULLTEXT` require an existing table. Public `dropFuzzyIndex()` and `dropFullTextIndex()` support later incremental migrations and operator commands. Driver-specific DDL, identifier validation, deterministic index names, capability checks, and SQLite no-op behavior remain private implementation details of `MigrateUtils`.

Existing migrations are the source of truth during the current pre-production phase and may be edited directly for `migrate:fresh`. Repeated driver branches in Core and CMS migrations must be replaced with this API.

## Compatibility

- Existing callers remain valid and receive adaptive `auto` behavior.
- Existing engine-specific model hooks remain available, but portable options take precedence for semantics owned by Core.
- Direct Scout searches resolve configured defaults when no analyzed decision is supplied.
- Pure vector retrieval does not receive text matching parameters.

## Acceptance criteria

- Short acronyms, codes, UUIDs, emails, and numbers remain strict by default.
- One misspelled long name can match while the exact name ranks first.
- Two-word names require both terms and allow at most one fuzzy token.
- Longer natural-language queries relax token coverage rather than indiscriminately increasing edits.
- Explicit strict is strictly non-fuzzy.
- Explicit granular overrides are honored within bounds and identifier protection rules.
- All engines translate the same effective options and publish degradations.
- Matching decisions are visible in advanced-search metadata.
- User and developer/operator RAG documentation describe request semantics, engine differences, and database prerequisites.
