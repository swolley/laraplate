# SAO Phase 5 — VCS & Releases Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the `vcs` and `releases` capabilities on the GitHub, GitLab and Bitbucket drivers, each passing a conformance battery over an `Http::fake()`. Spec: `docs/superpowers/specs/2026-08-16-sao-phase-5-vcs-releases-design.md`.

**Tech Stack:** PHP 8.5, Laravel 12 (`Http`), Pest 4, Pint, PHPStan.

## Global Constraints

- `declare(strict_types=1);`; `final`; explicit types; `#[Override]`; no new dependencies.
- Drivers operate on `BindingContext`/`ConnectionContext` and the `Http` client only.
- `commits`→`{sha,...}`, `tags`→`{tag,...}`; `firstTagContaining` is a bounded first-page scan (document the bound).
- Tests in `Modules/SAO/tests/`; conformance batteries in `tests/Support/Conformance/`.
- Per step: minimal relevant tests; Pint on touched files before each commit.

---

## Task 1: GitHub vcs + releases

- Create: `tests/Support/Conformance/VcsConformance.php` (commits pagination + sha items; compare array; fileAtRef string/null; openPullRequest array with remote_id/url).
- Edit: `GitHubDriver` — add `VcsCapability`, `ReleasesCapability`; grow `capabilities()`; implement commits/compare/fileAtRef/openPullRequest/tags/firstTagContaining.
- Edit: `GitHubDriverTest` — extend the fake; run `VcsConformance` + `ReleasesConformance`.
- [ ] Red → implement → green; Pint + commit (`feat(sao): github vcs and releases capabilities`).

## Task 2: GitLab vcs + releases

- Edit: `GitLabDriver` + `GitLabDriverTest` (same shape, GitLab endpoints/pagination).
- [ ] Red → implement → green; Pint + commit (`feat(sao): gitlab vcs and releases capabilities`).

## Task 3: Bitbucket vcs + releases

- Edit: `BitbucketDriver` + `BitbucketDriverTest` (Bitbucket endpoints; diffstat compare; src file).
- [ ] Red → implement → green; Pint + commit (`feat(sao): bitbucket vcs and releases capabilities`).

## Task 4: Docs + gate + parent bump

- Update the module RAG docs/glossary (Git hosts now serve issues+vcs+releases) and the spec/plan indexes.
- Full SAO suite green; Pint clean. Commit (module) + parent bump.
- [ ] `docs(sao): document vcs/releases on the git-host drivers` + `chore: bump SAO with phase 5 vcs/releases`.

## Exit criteria

- GitHub/GitLab/Bitbucket advertise and implement `issues`, `vcs`, `releases`; each passes all three conformance batteries over an `Http::fake()`.
- Full SAO suite green; Pint clean.

## Known gaps carried forward

- Live-instance verification, webhook/push ingest, code-to-work correlation and version census.
