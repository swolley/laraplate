# Assistant end-to-end evaluation (R1b)

**Status:** Approved for planning

**Date:** 2026-08-29

**Program:** RAG assistant — goal **R1**, sub-project **R1b** (assistant-level evaluation). R0 built the per-module *documentation* retrieval baseline and defined a forward-looking assistant-level evaluation contract. R1a made the composed assistant scope-aware. R1b builds the per-module *assistant* evaluation that measures the composed `InAppAssistanceService::respond()` — the "metro" that verifies R1a's scoping and the assistant's composition of the three retrieval surfaces.

## Problem

R0's documentation evaluation is deterministic because retrieval carries no LLM in the loop. The composed assistant is different: **which surface answers a question — documentation vs application content vs Core Graph — is the agent's (LLM's) tool-calling decision**, so end-to-end *surface routing* cannot be measured deterministically without running a live provider.

Yet a large, growing amount of `respond()` is deterministic *plumbing*: server-owned scope resolution and surface gating (R1a), documentation retrieval and citation assembly, application-content tool invocation → clarification/abstention state, citation merging, and mandatory output validation. That plumbing has only ad-hoc feature tests today and no per-module, dataset-driven report card or regression gate.

## Decision summary

R1b introduces a **per-module assistant-evaluation dataset** run in two modes over the same cases:

- **Level 1 (deterministic, built now, CI regression gate):** runs the real `respond()` with a **scripted router** — the already-injectable `completion` closure — that invokes the case's `expected_surface` tool (real tools over fake providers/fixtures) and returns a scripted answer. It measures composition *plumbing*, not the LLM's routing quality.
- **Level 2 (live, defined now, built later, opt-in):** runs the same dataset against the real LLM to measure actual surface-routing, citation, clarification, and answer quality. Non-deterministic; never a CI gate.

The unit is the **module** (matching R0). `expected_surface` is the pivot: L1 scripts the router to it and checks the plumbing; L2 checks the real router selects it.

This mirrors R0 (build Level 1 + the dataset now, define Level 2 opt-in). The AI module owns the harness; each module owns its dataset.

## What L1 measures — and does not

L1 asserts deterministic behavior of `respond()` that depends on wiring, not LLM judgment:

- the scope (R1a) **offers** the `expected_surface` for the case's profile+module;
- given a scripted use of that surface, the assistant **assembles the right citations** into the message metadata;
- **clarification** fires exactly when the application-content provider flags ambiguity (`ApplicationContentCitationMapper::clarificationRequired()`);
- **abstention** fires when a surface was attempted but produced no evidence (`attempted() && ! hasEvidence()`);
- the mandatory **output validation** rejects unsafe scripted output.

L1 explicitly does **not** measure surface-routing accuracy: with a scripted router *we* choose the surface, so L1 proves "given surface X, the plumbing handled X correctly," never "the assistant chose X." Routing accuracy is a Level-2 metric. This boundary is stated so the CI gate is not mistaken for a measure of answer quality.

## Dataset contract

A dataset file is JSON, validated on load with exact-key assertions and bounded sizes, mirroring `DocumentationEvaluationDataset`. It lives under the owning module at `Modules/{Module}/docs/rag/evaluations/assistant-{slug}.json`.

Dataset-level fields: `version`, `corpus_revision`, `module`, `data_classification` (`synthetic`), `cases`.

Case-level fields:

- `id`, `query`, `locale`.
- **scope context**: `module` (or null for the generic no-module case) and profile is fixed to in-app assistance; this drives the R1a `AssistantScope` under evaluation.
- `expected_surface`: one of `documentation | application_content | graph | clarify | refuse`.
- `expected_citations`: bounded list of expected safe citation labels/references (empty for `clarify`/`refuse`).
- `expect_clarification`: bool (true iff `expected_surface = clarify`).
- `expect_refusal`: bool (true iff `expected_surface = refuse`).
- `slices`: lowercase slug tags (topic, hop class, locale).

Validation invariants (mirroring the documentation case object): `clarify`/`refuse` cases carry no expected citations; `expected_surface` and the boolean flags agree; a `graph`/`application_content` surface is only valid where the module scope could offer it; slugs match the strict pattern.

## Two run modes

### Level 1 — deterministic scripted router

The harness constructs `InAppAssistanceService` with:

- an injected `documentation_retrieval` closure backed by the R0 documentation fixtures (reused);
- fake application-content and Graph providers (fixtures) whose evidence/ambiguity is authored per case;
- an injected **scripted `completion` closure** with the existing signature `Closure(string $input, string $systemPrompt, AssistantPromptContext $context, list<Tool> $tools): string`.

The scripted completion, per the case's `expected_surface`, invokes the matching tool from `$tools` with scripted arguments (running the *real* tool over the fake provider, which sets `ApplicationContentCitationMapper` state) and returns a scripted answer string. For `documentation` it uses the already-retrieved documentation context; for `clarify`/`refuse` it invokes nothing so the deterministic clarification/abstention paths in `respond()` produce the output. `respond()`'s post-processing (citation merge, abstention check, output validation, persistence) then runs unchanged.

The service reads the resulting `Message` (citations metadata, refusal/clarification markers) and scores the case. No LLM, no Elasticsearch, no network.

### Level 2 — live (defined, not built here)

Same dataset, the real agent path (no scripted completion). Adds a live-provider run mode and a fixed judge rubric or required-facts match per case. Non-deterministic, opt-in, excluded from the deterministic suite. Its metrics and command flag are specified here; its implementation is a separate follow-up.

## Metrics

### Level 1 (deterministic)

- `surface_offered` — the `expected_surface` tool was available under the resolved scope.
- `citation_assembly` — returned citation labels match `expected_citations` after the scripted surface use.
- `clarification_trigger_accuracy` — clarification output iff `expect_clarification`.
- `abstention_accuracy` — abstention output iff a surface was attempted with no evidence (the `refuse` cases).
- `output_valid` — the scripted output passed mandatory validation (unsafe cases rejected).
- `unavailable_rate` — cases where `respond()` failed/refused unexpectedly.

### Level 2 (contract only)

- `surface_routing_accuracy` — the real agent used `expected_surface`.
- `cross_surface_citation_precision` — cited sources are correct across surfaces.
- `clarification_accuracy`, `refusal_accuracy` — real clarify/refuse behavior.
- `grounded_answer_rate` — the written answer is supported by retrieved evidence (judge/fact-list).
- `provider_latency_ms` / `provider_cost` where measurable.

Slices report each metric by `module`, `locale`, and slice tag, using the same deterministic mechanism as R0.

## Components and ownership

AI module owns the harness (mirroring `Modules/AI/app/Services/Documentation/Evaluation/`):

- `Modules\AI\Services\Assistance\Evaluation\AssistantEvaluationCase`
- `Modules\AI\Services\Assistance\Evaluation\AssistantEvaluationDataset`
- `Modules\AI\Services\Assistance\Evaluation\AssistantEvaluationService`
- `Modules\AI\Console\EvaluateAssistantCommand` — signature `ai:evaluate-assistant --module= --dataset= --output= --force [--live]`. Default runs Level 1; `--live` selects the (later) Level-2 mode and fails with a clear message until Level 2 is implemented.
- Deterministic fixtures under the AI module test-support namespace: the scripted-completion builder plus fake application-content/Graph providers, reusing the R0 documentation fixtures (`FakeDocumentationSearch`).

Each business module owns its dataset content under `Modules/{Module}/docs/rag/evaluations/assistant-*.json`.

## Report artifact and regression gate

1. **Baseline capture** — run `ai:evaluate-assistant` for the first module (Level 1) and commit the report as the baseline.
2. **Regression gate** — a Pest test runs the deterministic dataset through the harness and asserts each Level-1 metric at or above committed thresholds; a change degrading the assistant's composition fails the build.
3. **Thresholds** move only via an intentional, reviewed commit that also refreshes the baseline.

The report carries `schema_version, module, mode, dataset_version, corpus_revision, data_classification, case_count, metrics, latency_ms, slices` and contains only aggregate floats and slugged slice keys — never the raw query text, citations, or record content.

## Authorization and information-flow invariants

1. Level 1 uses no live LLM or Elasticsearch; the real `respond()` path (scope resolution, guardrails, ACL-preserving tool authorization, output validation) runs, only the completion and the surface providers are faked.
2. The scripted router can only invoke tools the scope actually offered; it cannot widen the surface set.
3. Reports leak no payload: aggregate metrics and slugged slices only.
4. `data_classification` is restricted to `synthetic`.
5. Level 2, when built, runs under the same server-owned profile/scope/ACL guarantees and remains opt-in and outside CI.

## Testing

Deterministic, no external services:

- `AssistantEvaluationCase` / `AssistantEvaluationDataset` reject malformed input (unknown keys, wrong types, `clarify`/`refuse` carrying citations, contradictory flags).
- `AssistantEvaluationService` computes each Level-1 metric on crafted cases, including a documentation-surface case, an application-content case with citations, a clarification case, a refusal case, and an unsafe-output case.
- The scripted-completion fixture drives the real `respond()` end to end with no LLM/Elasticsearch.
- The regression-gate test asserts committed thresholds on the first module's assistant dataset.

## Scope boundaries

In scope (R1b): the assistant-evaluation dataset schema and value objects, the deterministic Level-1 scoring service and scripted-router fixtures, `ai:evaluate-assistant` (Level 1), the first module's dataset + regression gate, and the Level-2 contract (metrics + command flag) without its implementation.

Out of scope: the Level-2 live implementation (a defined follow-up); per-entity Graph module filtering; navigation/action capabilities (non-RAG N-plans); any change to production `respond()` behavior beyond what R1a already shipped.

## Success criteria

- A per-module assistant-evaluation dataset exists, runnable deterministically over the real `respond()` path with no live services.
- Level-1 metrics give a per-module report card of the assistant's composition plumbing, with a committed baseline and a regression gate.
- The gate is non-vacuous: it exercises real scope gating, citation assembly, clarification, abstention, and output validation.
- L1 is explicitly documented as measuring plumbing, not routing accuracy; routing accuracy is a defined Level-2 metric.
- The same dataset is reusable by the (later) Level-2 live mode without schema change.

## Related documents

- `docs/superpowers/specs/2026-08-04-documentation-rag-evaluation-baseline-design.md` (R0; the forward contract this realizes)
- `docs/superpowers/specs/2026-08-06-assistant-profile-scope-design.md` (R1a; the scoping this verifies)
- `docs/superpowers/specs/2026-07-17-application-content-retrieval-design.md` (contextual/generic routing of the data surface)
- `docs/superpowers/specs/2026-07-16-in-app-ai-assistance-security-design.md` (normative profile/guardrail boundary)
- Implementation touch points: `Modules/AI/app/Services/Assistance/InAppAssistanceService.php` (injectable `completion`), `Modules/AI/app/Services/ApplicationContent/ApplicationContentCitationMapper.php`, `Modules/AI/app/Services/Documentation/Evaluation/` (mirror), `Modules/AI/tests/Stubs/Documentation/FakeDocumentationSearch.php` (reuse)
