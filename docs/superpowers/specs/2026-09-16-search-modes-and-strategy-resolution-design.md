# Search modes and strategy resolution

**Status:** draft for review

**Date:** 2026-09-16

**Modules:** Core (owns the contract and the cheap path), AI (overlays the expensive one).

## Problem

Searching through `CrudService` is slow, and nobody asked for it to be.

`AdvancedSearchService::search()` performs this on **every** call:

```php
$intent = $this->intent_parser->parse($query);   // LlmQueryIntentParser  -> LLM call
$plan   = $this->planner->safePlan($query);      // SearchOrchestratorAgent -> LLM call
$vector = $this->resolveVector($query, $plan);   // ITextEmbedder -> embedding call
return $this->ensemble_search->search(...);      // IReranker -> CrossEncoderService, Python microservice
```

Two LLM round trips, one embedding and one external microservice call, synchronously, before the first row comes back. Whether this happens is decided by a single global boolean:

```php
if (! config('ai.features.search_orchestration.enabled', true)) {
    return;
}
$this->app->singleton(IReranker::class, CrossEncoderService::class);
$this->app->singleton(ISearchPlanner::class, SearchOrchestratorAgent::class);
$this->app->singleton(IQueryIntentParser::class, LlmQueryIntentParser::class);
$this->app->singleton(ITextEmbedder::class, SearchEmbedder::class);
```

These are singletons registered at boot, replacing Core's implementations for the whole application. So it is all searchers or none, decided once, in configuration, by nobody in particular.

The contract-based overlay is the right mechanism: Core defines `ISearchPlanner` and `IReranker` and registers cheap defaults with `singletonIf`, AI substitutes better ones. What is wrong is that the substitution is **global and permanent**, which turns an optional enhancement into a tax every caller pays. A grid that lists records should not wait on a language model.

## Decision summary

The caller declares how much it is willing to spend, per request. Configuration stops deciding *whether AI runs* and goes back to deciding *whether the capability exists at all*.

- A `mode` parameter on search: `fast` (default) and `deep`.
- Core resolves a **strategy** for the requested mode through one contract it owns. Its own resolver ignores the mode and always returns the cheap implementations.
- AI replaces that single resolver, and nothing else. It stops overriding `ISearchPlanner`, `IReranker`, `IQueryIntentParser` and `ITextEmbedder` globally.
- Anything that prevents the expensive path degrades to the cheap one and **says so in the response**. It never fails the search.

## The mode vocabulary

| Mode | What the caller is accepting | Default |
|---|---|---|
| `fast` | no external call: lexical retrieval with Core's heuristics | yes |
| `deep` | LLM planning and intent parsing, embedding, cross-encoder reranking, optional retries; seconds, not milliseconds, and real money | no |

Three rules about the naming, each of which has bitten somebody before:

1. **The mode names the bargain, not the technology.** Not `mode=ai`. The day the expensive path stops using an LLM, or uses something else, `ai` is a lie and every client is pinned to it. `deep` describes what the caller gets and accepts; the implementation is free to change underneath.
2. **`fast` is nameable, not just the absence of `deep`.** A client that wants the cheap path must be able to say so explicitly, rather than depending on what the default happens to be this year.
3. **A string, not a boolean.** Measurement may well show that the two LLM calls dominate while the single embedding is cheap, in which case a middle tier belongs between the two: lexical plus vector, no LLM. A third value is additive and old clients ignore it. A boolean would have to be replaced.

`advanced` was considered and rejected as a name: in this codebase it already means something else (`AdvancedSearchService` is the service, in both modes), and two meanings for one word inside one perimeter is how documentation starts lying.

## Strategy resolution

### The contract, owned by Core

```php
interface ISearchStrategyResolver
{
    public function resolve(string $mode): SearchStrategy;
}
```

```php
final readonly class SearchStrategy
{
    public function __construct(
        public string $applied_mode,
        public ISearchPlanner $planner,
        public IReranker $reranker,
        public IQueryIntentParser $intent_parser,
        public ?ITextEmbedder $embedder,   // null: no vector stage
        public int $max_retries,
        public ?string $degraded_reason,
    ) {}
}
```

**One object, not four lookups.** A mode is a coherent combination, not four independent choices, and returning them together makes incoherent mixtures (an expensive reranker behind a trivial planner) unrepresentable rather than merely discouraged. It also carries what the caller needs to report: which mode actually applied, and why it differs from the one requested.

### Core's resolver ignores the mode, on purpose

Core's implementation returns its own cheap components whatever it is asked for, and records that it could not honour anything else. This is not a stub: it is the correct behaviour for a Laraplate installed without the AI module, which is a supported configuration.

### AI decorates rather than replaces

The AI resolver takes Core's as a fallback and delegates to it for everything that is not `deep`, or when the kill switch is off. Depending on Core is the correct direction and already the norm.

Three consequences worth stating, because they are the point of the design:

- `mode=fast` **with AI installed executes Core's code path**, not an imitation of it. "As if the AI module were not installed" is literal.
- The kill switch stops being a special case. Off means the `deep` branch is never taken, which is the same line of code as AI not being installed.
- **The expensive objects are constructed only when `deep` is asked for.** Today `AdvancedSearchService` receives `ISearchPlanner` in its constructor, so the LLM implementation is wired in before any parameter can be read. Taking the resolver instead, and asking it per call, is what makes a per-request decision possible at all. This is the substantive refactor; the query parameter is the easy half.

### Why the container and not an event

Substituting an implementation through the container is not a new mechanism here: `singletonIf` already does exactly this. What changes is *what* is substituted (one resolver rather than four implementations) and *when* it is consulted (per request rather than once at boot).

An event would be the wrong shape. An event is a notification: zero listeners or ten, order-dependent, nobody obliged to answer. Strategy resolution must return exactly one answer, synchronously and deterministically, on the request path. Via events you inherit three problems at once: what to do when two listeners answer, what to do when none does, and a system whose behaviour depends on provider registration order.

## Degradation is part of the contract

There are four reasons the expensive path may not run. Three are known when the strategy is resolved: the AI module is absent, the kill switch is off, or the mode is not offered. All three produce the same outcome through the same code: the cheap strategy, with a reason.

The fourth is failure while the search is running: provider down, cross-encoder unreachable, timeout. It cannot be seen at resolution time, so it is handled inside the expensive implementations, which degrade to the cheap behaviour and report it. `ISearchPlanner::safePlan()` is already named for this; the reranker and the embedder need the same treatment. `AdvancedSearchService` must not learn that language models exist in order to defend itself against them.

**A search never returns an error because an LLM was unavailable.** It returns results, plus:

```
meta.search: {
  mode_requested: "deep",
  mode_applied:   "fast",
  degraded_reason: "planner_timeout",
  retries_used:    0
}
```

The interface can then say "shown with standard search" rather than letting a user believe they paid for a depth they did not get.

## Retries

`retry=N` on the request selects how many times the expensive path may re-plan and try again when result quality is poor, bounded by a maximum held in Settings. `0` disables it; the value reaches the search path as `SearchStrategy::$max_retries`.

Retries belong to `deep` only. In `fast` the cap is irrelevant because there is no quality evaluation to trigger one, and the field is `0`.

## Cost control is authorization, not validation

A cap in Settings stops `retry=9999`. It does nothing about a client sending `mode=deep&retry=3` in a loop, which is where the money goes.

- **A named rate limiter keyed on the user**, applied to deep searches specifically and more tightly than to ordinary ones. Per user, not per route: the cost follows the person, not the endpoint.
- **A permission for `deep`**, following the existing permission conventions, so the expensive path can be granted to a role and withheld from another. It is included from the start deliberately: retrofitting an authorization axis onto a parameter that shipped without one is the same mistake the MCP design avoids by keeping `mcp:write` in the token model from day one, even while refusing to mint it.

Without the permission, a caller lacking it gets `fast` with `degraded_reason: not_authorized`, on the same degradation path as everything else. It is not an error, because whether the caller may spend money is not the caller's business to handle as an exception.

## `IntelligentSearchAction` is retired, and its idea kept

`Modules/AI/app/Actions/IntelligentSearchAction.php` was written on 16 April 2026 (`2cfa728 feat: implement AI-powered search orchestration and related services`) as the entry point of the AI search pipeline: plan, intent, embed, ensemble, evaluate, retry, cache, paginate. It has no production caller and never had one. The services it composes went live individually through Core's contracts; the orchestrator on top never received a surface. No spec or plan was ever written for it.

With modes, it has no role left. A second search surface would mean two places to authorize, rate limit, degrade and document, for the same question asked twice.

What survives, and must be salvaged rather than rewritten:

- **`evaluateResults()` and `shouldRetry()`**, the quality judgement that makes a retry meaningful. Moves into the search path, driven by `SearchStrategy::$max_retries`.

What is dropped, with reasons:

- **Pagination**, already handled by the CRUD layer.
- **Its cache**, keyed on query plus index with a 600 second TTL. Wrong on two counts for this use: results depend on the caller's ACL, so that key would let two users with different visibility trade results, and a grid where a record was just edited needs to see it. A cache for deep searches is a later decision with its own key design, including the authorization context.

## What `fast` gives up, stated plainly

`ITextEmbedder` has no Core default: only AI binds it. So "as if AI were not installed" also means **no vector stage**, and `fast` is lexical retrieval.

Today, with the global override on, every CRUD search gets semantic matching. Making `fast` the default removes it unless `deep` is requested. That is not only a latency win: it is a **relevance regression** on ordinary search, and users notice it differently from slowness, more slowly, which is worse.

This is the strongest argument for measuring before building, and for the mode being a string. If the embedding turns out to be a small fraction of the cost while the two LLM calls dominate, the right cut is three tiers rather than two, and the middle one keeps semantic matching on the default path.

## Prerequisite: measure before implementing

The code explains why the path would be slow. It does not say which of the four steps dominates, and that answer decides whether there are two modes or three.

Instrument the four stages (intent parse, plan, vector, ensemble and rerank) and get numbers with the benchmarks already in the repo: `perf:crud` benchmarks the CRUD list engine across entities, `perf:profile` ranks the hottest functions for an endpoint. If the cross-encoder microservice dominates, a score cache may recover most of the latency without any of this restructuring; if the LLM calls dominate, this design is the right one and the planner should leave the synchronous path regardless of mode.

This is a gate on implementation, not on the decision: the strategy resolver is worth doing either way, because a global boolean deciding a per-request trade-off is wrong independently of the numbers.

## Consumers

- **CRUD search** is the first, and gains `fast` as its default.
- **MCP**, whose `search` tool is described in its own design as the highest-value tool of the set, passes `mode=deep`: an external agent tolerates seconds where a grid does not. The MCP rate limiting section already anticipates the per-call embedding cost.
- **An explicit deep search in the UI**, later, where a person chooses to wait. The UI itself belongs to `laraplate-ui`.

## Scope boundaries

In scope: the `mode` parameter down to the search path, `ISearchStrategyResolver` and `SearchStrategy` in Core, Core's resolver, AI's decorating resolver, removal of the four global overrides, the degradation contract and its meta, retries bounded by Settings, the rate limiter and permission, retirement of `IntelligentSearchAction` with its quality judgement salvaged.

Out of scope: a third mode (recorded as the likely outcome of measurement, not as a commitment); a cache for deep results; changes to ranking parameters, which belong to `2026-09-15-measured-retrieval-tuning-l1-design.md`; the UI affordance; moving the planner out of the synchronous path, which measurement may justify separately.

## Related

- `Modules/Core/app/Search/Services/AdvancedSearchService.php`, `EnsembleSearchService.php`, `FallbackSearchPlanner.php`, `HeuristicReranker.php`, `SimpleQueryIntentParser.php`
- `Modules/Core/app/Providers/SearchServiceProvider.php` (`singletonIf` defaults), `Modules/AI/app/Providers/AIServiceProvider.php` (`registerSearchBindings`, the overrides this spec removes)
- `docs/superpowers/specs/2026-09-12-mcp-server-design.md` (the `search` tool, rate limiting, and the same reasoning about keeping an authorization axis from day one)
- `docs/superpowers/specs/2026-09-15-measured-retrieval-tuning-l1-design.md` (ranking quality, a different question from cost)
- `Modules/AI/docs/rag/MODULE.md`, section *Perimeters* (why search belongs to Core and AI only overlays it)
- `docs/superpowers/plans/2026-07-16-rag-retrieval-strategy.md` (the *documentation* corpus retriever, NeuronAI `RetrievalInterface` — a different path from this one; its hybrid and reranking tasks remain open and are not delivered by the work here)
