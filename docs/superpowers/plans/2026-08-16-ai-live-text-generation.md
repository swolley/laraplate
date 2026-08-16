# AI Live Text Generation Plan

> REQUIRED SUB-SKILL: superpowers:executing-plans. Checkbox (`- [ ]`) steps.

**Goal:** make the optional live LLM text-generation path production-ready behind its feature flag, without changing the event contract or any consumer. Spec: `docs/superpowers/specs/2026-08-16-ai-live-text-generation-design.md`.

## Global Constraints

- `declare(strict_types=1);`, `final`, explicit types, `#[Override]`; reuse the AI module's `ProviderFactory`/`ChatAgent` — no new provider/SDK.
- Do not change Core's `AiTextGenerationRequested` event or any SAO consumer.
- Any failure leaves the event **unfulfilled** so the requester falls back. Per task: minimal tests (mocked provider); Pint before commit. Commit per task; bump parent at the end.
- Offline-first: all behaviour covered with mocks; the only live test is gated and skipped by default.

## Task 1: config + provider preflight

- Extend `ai.features.text_generation` (`model`, `timeout_seconds`, `max_output_chars`, `rate_limit`, `cache_ttl_seconds`, overridable `system_prompt`). Add a preflight in the listener: enabled **and** provider buildable, else log-once and no-op. Document the new env vars in the AI README.
- [ ] Red → green (mocked provider-unconfigured path); Pint + commit (`feat(ai): text-generation config and provider preflight`).

## Task 2: cost controls

- Timeout wrapper, `max_output_chars` truncation (word boundary), per-`purpose` `RateLimiter`, optional `Cache` keyed by `sha256(purpose|prompt)` with TTL.
- [ ] Red → green (timeout, rate-limit exhaustion, cache hit/miss, truncation); Pint + commit (`feat(ai): bound text-generation cost`).

## Task 3: output hardening + observability

- Sanitize output (trim, strip control chars, collapse whitespace, length cap); config-overridable system prompt with the safe default. One structured log line per attempt with `outcome` + latency (+ tokens when available), no secret/body logging.
- [ ] Red → green (sanitization; outcome logging asserted via Log fake); Pint + commit (`feat(ai): harden and observe text generation`).

## Task 4: gated live smoke test + docs

- A single live round-trip test, skipped unless `AI_LIVE_TESTS=1` + provider creds; asserts a non-empty, name-preserving rewrite. Update AI RAG docs (feature, flags, how to run the live test) and reconcile spec/plan indexes.
- [ ] Green offline (skips cleanly); Pint + commit (`test(ai): gated live text-generation smoke`) + docs commit + bump parent.

## Exit criteria

- With `AI_TEXT_GENERATION_ENABLED=1` and a configured provider, an ownership suggestion is phrased by the live model; with the feature off, a misconfig, a timeout, a rate-limit, or an error, the caller silently gets the deterministic text. All non-live behaviour is green in CI; the live smoke passes locally against a real provider.

## Known gaps / out of scope

- Streaming, multi-turn, tool use; a new provider/SDK; auto-applying AI output to domain state (D14 stands).
- Live provider credentials/quotas are an environment concern, not code.
