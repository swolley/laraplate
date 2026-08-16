# SAO phase 5 — VCS & releases capabilities design

**Date:** 2026-08-16
**Module:** `Modules/SAO`
**Parent spec:** `docs/superpowers/specs/2026-07-31-sao-module-design.md` (phase 5)
**Builds on:** `docs/superpowers/specs/2026-08-15-sao-phase-3a-driver-framework-foundation-design.md` (capability contracts)
**Status:** Design proposed.

---

## 1. Purpose

Phase 3 shaped the `vcs` and `releases` capability contracts but shipped no
implementation ("a real driver implements it in phase 5"). This slice implements
both capabilities on the three Git-hosting drivers — **GitHub, GitLab,
Bitbucket** — so SAO can read commits, compare ranges, read a file at a ref,
open a pull/merge request, list tags, and find the first tag containing a commit.
It is the mechanical groundwork the later code-to-work correlation and version
census build on; those higher-level features are **out of scope here**.

Redmine and Jira are issue trackers with no VCS/releases surface and gain nothing.

---

## 2. Locked decisions

| # | Decision | Reason |
|---|----------|--------|
| P1 | Only the Git-host drivers implement `vcs`/`releases`. Their `capabilities()` grows to include them. | The capability set advertises what a driver can actually do. |
| P2 | A new `VcsConformance` battery joins the existing `ReleasesConformance`; each Git-host driver test runs both over an `Http::fake()`. | A driver is done when it passes conformance, not when it merely works (spec §12). |
| P3 | `firstTagContaining(sha)` is a **bounded, best-effort** scan of the first page of tags, returning the first tag whose comparison shows the commit is an ancestor. Providers expose no single "tags containing commit" endpoint. | Correct within recent tags without an unbounded crawl; the bound is documented, not silent. |
| P4 | `commits`/`tags` normalize to `{sha,...}` / `{tag,...}` items (matching the conformance batteries); `compare` and `openPullRequest` return the provider payload verbatim under stable top-level keys. | The batteries key on `sha`/`tag`; higher layers consume the rest. |
| P5 | Live-instance verification and webhook/push ingest stay follow-ups, as for the issues drivers. | No live credentials here; offline conformance is the gate. |

## 3. Per-provider endpoints (documented APIs)

- **GitHub**: commits `GET /repos/{o}/{r}/commits?sha=&per_page=&page=` (Link pagination); compare `GET /repos/{o}/{r}/compare/{base}...{head}`; file `GET /repos/{o}/{r}/contents/{path}?ref=` (base64 `content`); PR `POST /repos/{o}/{r}/pulls`; tags `GET /repos/{o}/{r}/tags`.
- **GitLab**: commits `GET /projects/{id}/repository/commits?ref_name=&per_page=&page=` (`X-Next-Page`); compare `GET /projects/{id}/repository/compare?from=&to=`; file `GET /projects/{id}/repository/files/{path}?ref=` (base64 `content`); MR `POST /projects/{id}/merge_requests`; tags `GET /projects/{id}/repository/tags`.
- **Bitbucket**: commits `GET /repositories/{ws}/{r}/commits/{ref}` (`next`); compare via `GET /repositories/{ws}/{r}/diffstat/{head}..{base}`; file `GET /repositories/{ws}/{r}/src/{ref}/{path}`; PR `POST /repositories/{ws}/{r}/pullrequests`; tags `GET /repositories/{ws}/{r}/refs/tags`.

## 4. Testing

- Add `tests/Support/Conformance/VcsConformance.php`: `commits` paginates beyond one page with `sha` items; `compare` returns an array; `fileAtRef` returns a string for a known path and null for a missing one; `openPullRequest` returns an array carrying a `remote_id` or `url`.
- Each Git-host driver test gains a stateful `Http::fake()` for the VCS/releases endpoints and runs `VcsConformance` + `ReleasesConformance`.

## 5. Non-goals

- Code-to-work reference extraction, version census, deploy tracking (later phases).
- Issue-tracker drivers gaining VCS (they have none).
- Live-instance verification and push ingest.
