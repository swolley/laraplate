# SAO phase 5b — code-to-work, releases & deploy census design

**Date:** 2026-08-16
**Module:** `Modules/SAO`
**Parent spec:** `docs/superpowers/specs/2026-07-31-sao-module-design.md` (§4, §8, §9; D12/D15/D17/D18)
**Builds on:** the phase 5 `vcs`/`releases` driver capabilities (already shipped).
**Status:** Design proposed.

---

## 1. Purpose

The `vcs`/`releases` capabilities gave SAO the *reads*. Phase 5b adds the
*correlation data*: `ChangeRef` (a code artefact linked to a ticket), `Release`
/ `ReleaseTag` / `TicketRelease` (a product version, the tags realizing it, and
which tickets it carries), and `Environment` with a **deploy census** (what runs
where, with honest staleness). This is the product gate — "what runs where" and
"which release carries this fix" — built on data, not guesses. Offline and
mock-verified.

---

## 2. Locked decisions

| # | Decision | Source |
|---|----------|--------|
| R1 | `ChangeRef` links a code artefact (`commit`/`pull_request`/`tag`) to a ticket, recording the source that produced it. | §8, glossary ChangeRef |
| R2 | `Release` is a **product version** named as its stable label with status `announced` or `shipped`. `ReleaseTag` is a concrete VCS tag realizing it, `stable` or `candidate` (an RC keeps the testable reference for staging). | §4, §9.1, D15 |
| R3 | `TicketRelease` attributes a ticket to a release as `promised` (announced / via RC) or `shipped` (a stable tag containing the fix exists). It **does not require** the ticket workflow status to be resolved (D17). | §4, D17 |
| R4 | `Environment` is a deployed instance with a free-form name (technical or customer-oriented, D18), a `current_version`, and a **liveness** `last_seen_at`. | §4, D12, D18 |
| R5 | Deploy census has two feeds: **passive** (ingest observes a version) and **active** (a scheduled version probe). A version older than the sync SLA is reported **unconfirmed / stale**, never as truth. | §4 "Dual exemplar", D12, §14 |
| R6 | `group_key` comparability and one-ticket-per-project are unchanged; releases and environments are per project. | D13 |

## 3. Components

- Enums: `ChangeRefType` (`commit`/`pull_request`/`tag`), `ReleaseStatus` (`announced`/`shipped`), `ReleaseTagKind` (`stable`/`candidate`), `TicketReleaseState` (`promised`/`shipped`).
- `ChangeRef` (`sao_change_refs`): ticket_id, type, identifier, url, source. `Ticket::changeRefs()`.
- `Release` (`sao_releases`): project_id, version, status, released_at. `ReleaseTag` (`sao_release_tags`): release_id, tag, kind. `TicketRelease` (`sao_ticket_releases`): ticket_id, release_id, state (unique pair).
- `Environment` (`sao_environments`): project_id, name (unique per project), current_version, last_seen_at.
- `DeployCensusService`: `observe(Environment, version)` (passive), `recordProbe(Environment, version)` (active), `isStale(Environment, ttlMinutes)` and a `census(Project)` read model with per-environment freshness.

## 4. Testing

- `ChangeRef` links to a ticket and resolves by type; `Ticket::changeRefs()` lists them.
- `Release` carries tags (stable + candidate); a `TicketRelease` pair is unique and its state is independent of the ticket status.
- Deploy census: `observe`/`recordProbe` set `current_version` + `last_seen_at`; `isStale` flips past the TTL; `census` reports each environment's version and freshness.

## 5. Non-goals

- Fix propagation and evidence-based closure (phase 6).
- Ownership-suggestion UI, time-to-truth (phase 6).
- Filament surfaces for releases/environments (a later UI slice).
- Live probe transport (the active feed is exercised through the service with injected versions).
