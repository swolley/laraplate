# AI live text generation — design

**Status:** Draft
**Modules:** AI (primary), Core (event, unchanged), SAO (consumer, unchanged)
**Builds on:** the optional-AI seam already shipped — Core's `AiTextGenerationRequested` event, SAO's `EventTextGenerator`/`AiSuggestionPhraser`, and AI's `HandleAiTextGenerationListener` (feature-flagged by `AI_TEXT_GENERATION_ENABLED`).

## 1. Why

The seam that lets a module ask an optional AI to generate one-shot text is in
place and exercised with mocks; the listener already calls a `ChatAgent` (a live
NeuronAI provider) when the feature is on. What is missing is everything that
makes that live path safe to switch on in production: provider/config
validation, prompt and output guardrails, bounded cost (timeout, length,
rate limit, cache), observability, and a *gated* live smoke test. This turns
"the wiring exists" into "the wiring is production-ready" without changing the
event contract or any consumer, and without adding a SAO↔AI dependency.

## 2. Requirements traceability

| Req | Statement | Design ref |
|-----|-----------|------------|
| R1 | The live generator answers `AiTextGenerationRequested` only when explicitly enabled, and degrades to silence (unfulfilled event → caller falls back) on any failure. | §3, §6 |
| R2 | The chosen provider/model is validated at boot/health-check; a misconfiguration disables the feature rather than throwing into a caller. | §3 |
| R3 | Cost is bounded: per-call timeout, max output length, a per-key rate limit, and an optional short-TTL cache keyed by (purpose, prompt hash). | §4 |
| R4 | Output is guarded: trimmed, length-capped, control-characters stripped; the existing D14 name-preservation guard stays the caller's responsibility (`AiSuggestionPhraser`). | §5 |
| R5 | Every live call is observable: structured log + metric (purpose, provider, latency, tokens if available, outcome), never logging secrets or full user data. | §7 |
| R6 | A live smoke test verifies a real round-trip, is skipped by default, and runs only when credentials + an opt-in env are present. Everything else is covered offline with mocked providers. | §8 |

## 3. Configuration & selection

- Extend `ai.features.text_generation`: `enabled`, `default_provider` (already
  present), plus `model`, `timeout_seconds`, `max_output_chars`,
  `rate_limit` (`{max, per_seconds}`), `cache_ttl_seconds` (0 = off).
- `HandleAiTextGenerationListener::shouldHandle()` gains a preflight: feature
  enabled **and** the resolved provider is configured (reuse the provider
  factory's config checks). A provider that cannot be built logs once and the
  event is left unfulfilled — never an exception into the dispatcher.
- No new provider abstraction: keep `ProviderFactory`/`ChatAgent`; the model is
  passed through from config.

## 4. Cost controls

- **Timeout**: wrap the provider call so a slow model aborts within
  `timeout_seconds` and leaves the event unfulfilled.
- **Max output**: hard-cap the returned text at `max_output_chars` (truncate on
  a word boundary); over-long generations are truncated, not rejected.
- **Rate limit**: a per-`purpose` limiter (Laravel `RateLimiter`) — when
  exhausted the listener no-ops (fallback), so a burst cannot run up cost.
- **Cache**: optional `Cache` read/write keyed by `sha256(purpose . '|' . prompt)`
  with `cache_ttl_seconds`; identical requests within the window reuse the text.
  Off by default (deterministic-suggestion prompts vary little, but caching a
  wrong-but-cheap answer is worse than regenerating).

## 5. Prompt & output hardening

- Keep the system prompt strict ("preserve facts and exact names; reply with the
  text only"); move it to config-overridable but with the safe default baked in.
- Sanitize output: `trim`, strip control chars, collapse whitespace, enforce the
  length cap. The **semantic** guard (the rewrite must keep the suggested owner's
  name, else fall back) already lives in `AiSuggestionPhraser` and is unchanged —
  the generator stays purpose-agnostic.

## 6. Failure semantics (unchanged contract)

- Any of: feature off, provider unconfigured, rate-limited, timeout, exception,
  empty/over-short output → the event stays unfulfilled. The requester
  (`EventTextGenerator` → `AiSuggestionPhraser`) already falls back to the
  deterministic text. The contract in Core's event does not change.

## 7. Observability

- One structured log line per attempt: `purpose`, `provider`, `model`,
  `latency_ms`, `outcome` (`fulfilled|empty|rate_limited|timeout|error|cached`),
  and token counts when the provider returns them. Never log the prompt's
  embedded user data verbatim beyond what is needed (log the prompt length, not
  the body, unless a debug flag is set).
- Optional counter metric on the same dimensions for dashboards.

## 8. Testing

- **Offline (default, CI):** mocked `ChatAgent`/provider — covers enable/disable,
  provider-unconfigured preflight, timeout path (a factory that sleeps/throws),
  rate-limit exhaustion, cache hit/miss, output truncation and sanitization,
  and the unfulfilled-on-failure guarantee. Extends the existing
  `HandleAiTextGenerationListenerTest`.
- **Live smoke (gated):** a single test tagged and `->skip()`-ed unless
  `AI_LIVE_TESTS=1` and real provider credentials are set; it sends one tiny
  prompt to the configured provider and asserts a non-empty, name-preserving
  rewrite. Never runs in normal CI; documents how to run it locally.

## 9. Non-goals

- Changing the event contract or any consumer (SAO stays untouched).
- Streaming, multi-turn, or tool use — this is one-shot text only.
- A new provider or SDK — reuse the AI module's existing provider stack.
- Auto-applying any AI output to domain state (D14 stands; phrasing only).
