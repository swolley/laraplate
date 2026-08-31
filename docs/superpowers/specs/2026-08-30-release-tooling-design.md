# Release tooling — versioning, changelog, and tagging

**Status:** Approved for planning

**Date:** 2026-08-30

**Scope:** `laraplate` (root) and its six module submodules (`Modules/{Core,CMS,AI,ERP,MES,SAO}`). `laraplate-ui` and `laraplate-importers` are explicitly out of scope.

## Problem

Release tooling is currently spread over 22 shell scripts across seven repositories: `scripts/version.sh` (7 byte-identical copies), `scripts/setup-hooks.sh` (2 variants), `scripts/hooks/post-commit` (3 divergent variants), plus `scripts/test-hooks.sh`. None of the hooks is installed anywhere — every hook directory under `.git/modules/**/hooks` is empty — so the automation is inert and every release is manual.

The following defects were verified against the live repositories, not inferred.

### Confirmed defects

| # | Defect | Evidence |
|---|---|---|
| 1 | `update_changelog()` runs `git cliff` **before** the tag exists, so the release being cut is written under `## [unreleased]` | All seven `CHANGELOG.md` files have the latest tag missing and its content under `[unreleased]`. Reproduced live during design: `v1.14.0` was cut mid-discussion and landed under `[unreleased]` again. |
| 2 | `amend_or_commit()` runs `git commit --amend` from a `post-commit` hook. The amend re-fires `post-commit`, the message is unchanged so the `^chore: bump version` guard never matches, and the tag is created only *after* the amend — unbounded recursion | Code path in `scripts/version.sh:150-161` combined with `scripts/hooks/post-commit:9`. |
| 3 | `determine_release_type()` prints **two lines** when the commit is already tagged (`"Commit is already tagged..."` + `"null"`). The caller tests `[ "$position" != "null" ]`, which is true, so `update_version` is invoked with a garbage position | `scripts/version.sh:85-97` and `:298-306`. Survives only by accident via the "already up to date" branch. |
| 4 | Root `scripts/setup-hooks.sh` assumes `$APP_DIR/.git` is a directory. `laraplate` is a submodule of the stack, so `.git` is a **file** — `mkdir -p` and `ln -sf` both fail | `laraplate/.git` contains `gitdir: ../.git/modules/laraplate`. |
| 5 | `Modules/*/scripts/setup-hooks.sh:14` uses `[ ! -f "$GIT_DIR" \|\| ! -f ... ]` — `\|\|` inside `[ ]` is a runtime `missing ']'` error. `bash -n` does not catch it | Fallback branch only; still dead code that would fail when reached. |
| 6 | Module `post-commit` "Method 1" tests a hardcoded `Modules/Cms` path (wrong case, and wrong module for every module but CMS) then assigns a *different* path; "Method 2" points at `scripts/scripts/version.sh`, which never exists. It works only by falling through to "Method 3" | ERP and SAO carry unintended divergent copies of this file. |
| 7 | `get_latest_version()` uses `git rev-list --tags --max-count=1`, which returns the most recent tag **by commit date**, not the highest semver. It also does not filter the tag namespace | The root repository carries nine `backup/v1.11.x`–`backup/v1.13.x` tags alongside `v*`. |
| 8 | `git push 2>/dev/null` with no refspec: real errors are hidden behind a generic warning, and which branch is pushed depends on `push.default` | `scripts/version.sh:276-281`. |
| 9 | Flags are parsed by substring match on `"$*"`; unknown flags are silently ignored; there is no `--help` | `scripts/version.sh:287-295`. |
| 10 | `cliff.toml` contains `{ body = "$^", skip = true }`, which **drops every commit with an empty body**. It sits above the group parsers, so it wins | See below — the single most damaging line in the system. |

### The `body = "$^"` rule

Most commits in these repositories are single-line, so this rule silently deletes them from the changelog. Measured on `Modules/CMS`:

| | with the rule | without it |
|---|---|---|
| changelog sections | 66 | **100** (exactly the tag count) |
| changelog entries | 142 | **319** (of 351 commits) |

Regenerating today with the committed config reproduces the in-tree file exactly, so the changelogs are not stale — they are **incomplete by construction**, and have been for roughly a hundred releases.

The same rule also neuters version computation. On the root repository with four commits after the tag:

```
git cliff --bumped-version, current config   ->  v1.13.8   (no bump)
git cliff --bumped-version, rule removed     ->  v1.13.9
```

Any future `--bump auto` built on this configuration would report "nothing to release" on real work.

### What is missing

- No real interactive mode: today "interactive" means passing `major|minor|patch` positionally. No preview, no confirmation, no per-target choice.
- No explicit version override, no `--no-push`, no working `--dry-run` for the full flow.
- No orchestration: the submodule loop in the root `post-commit` is entirely commented out, and consolidating submodule pointers in the root is a manual `chore: update submodule references` commit.
- Bump inference and changelog generation use **two different grammars** — hand-written regexes in `version.sh` and `commit_parsers` in `cliff.toml` — which can diverge silently.
- The release process is undocumented.

## Decision summary

1. **One script, in the root repository only.** `laraplate/scripts/release.sh` handles both a single repository and the orchestrated sequence. The six module copies are deleted. Verified safe: no module has any CI workflow, so the only callers are their own `composer.json` scripts.
2. **Releases are a deliberate manual gesture.** No hook creates tags. The bump is computed over `last-tag..HEAD`, so many commits collapse into one release by construction.
3. **git-cliff stays.** The tool was never at fault; the configuration was. git-cliff also replaces the hand-written bump inference via `--bumped-version`, making `cliff.toml` the single source of truth for both changelog and version.
4. **Changelogs are fully regenerated, never appended.** This makes `CHANGELOG.md` a pure function of `(history, cliff.toml)` and therefore verifiable in CI.
5. **The engine is designed to be automatable, but no automation is built now.** A future GitHub Action can call the same command unchanged.
6. **One hook survives:** a `commit-msg` validator, because automatic bump inference depends on conventional commit messages.

## Non-goals

- Building any CI release pipeline. The trigger choice (`pull_request: closed`, `workflow_dispatch`, or `push: tags`) stays open and reversible.
- Versioning `laraplate-ui` (pnpm workspace) or `laraplate-importers`.
- Removing the `version` key from `composer.json`. It is currently in sync everywhere and changing that convention is separate work.
- Rewriting git history or deleting the `backup/*` tags.

## Architecture

A single file, `laraplate/scripts/release.sh`, named for what it does. Composer script names stay in the existing `version:*` namespace to preserve muscle memory.

| Function | Responsibility |
|---|---|
| `resolve_targets` | Argument parsing, target-name validation, `--all` expansion |
| `plan_target <path>` | Read-only state of one repository: current version, commits since last tag, proposed bump (`git cliff --bumped-version`), verdict |
| `render_plan` | The table shown in `--dry-run` and before every confirmation |
| `release_target <path> <version>` | The write transaction for one repository |
| `consolidate_pointers` | Stage and commit submodule pointers in the root; `--all` only |

`release_target` is **identical for the root and for any module** — only the `-C` path differs. There is no separate code path for modules, and no separate code path for interactive versus automated use. The three divergent `post-commit` variants are the failure mode this rule exists to prevent.

If the file exceeds roughly 300 lines it is split into `scripts/lib/`, not before.

**Deleted:** `scripts/version.sh` (×7), `scripts/setup-hooks.sh` (×7), `scripts/hooks/post-commit` (×7), `scripts/test-hooks.sh`.
**Kept or new:** `scripts/release.sh` (×1), `scripts/hooks/commit-msg` (×1), `scripts/install-hooks.sh` (×1, using `git rev-parse --git-path hooks`).

## CLI surface

```
release.sh [target ...] [options]

  (no target)          release laraplate
  Core                 release only Core
  Core CMS             release Core and CMS
  --all                every module with pending commits, then laraplate

  --bump <level>       major|minor|patch|auto   (auto = inferred from conventional commits)
  --set-version <v>    explicit version; single target only
  -y, --yes            non-interactive: no prompts, inferred bump, skip when nothing to release
  -n, --dry-run        print the full plan, write nothing
  --no-push            create commit and tag locally, do not push
  --allow-dirty        proceed with a dirty working tree
  -h, --help
```

Targets are positional rather than flag-carried because Composer forwards bare words but intercepts flags. Verified on Composer 2.10.3:

```
composer run <script> Core        ->  ARGS=[Core]        ok
composer run <script> -- -m Core  ->  ARGS=[-m Core]     needs the --
composer run <script> --all       ->  ARGS=[]            Composer swallows it
```

The third case would silently release the root instead of everything, so **flags are never passed through Composer**: each flag combination gets its own named script.

```
composer run version                laraplate, inferred bump, interactive
composer run version:major|minor|patch [targets...]
composer run version:all            release.sh --all
composer run version:dry            release.sh --dry-run
composer run changelog              regenerate CHANGELOG.md
composer run changelog:check        regenerate to a temp file and diff
composer run setup:hooks            install the commit-msg hook
composer run test:release           run the test harness
```

Versions are normalised to the existing `vX.Y.Z` form on input and output, so `--set-version 2.0.0` and `--set-version v2.0.0` are equivalent.

Target names are validated case-insensitively against `Modules/*`. An unknown target is a hard error listing the valid names — never a silent fallback to the root. `--all` combined with an explicit target is an error, not a precedence rule.

## Preconditions and exit codes

Checked per target before anything is written:

- `git`, `git-cliff` and `jq` are available, with an actionable message naming what to install.
- HEAD is on a branch, not detached. Modules are on `master` today, but `.gitmodules` declares no `branch`, so a clean `git submodule update` leaves them detached; `branch = master` is added to `.gitmodules` as hardening.
- The working tree is clean, unless `--allow-dirty`.
- An upstream is configured, when pushing.
- HEAD does not already carry a matching tag.
- Tags are filtered by pattern `v[0-9]*`, and `cliff.toml` sets a matching `tag_pattern`, so the `backup/*` tags cannot be picked up.

```
0    released
10   nothing to release     (success for a pipeline, not a failure)
2    misuse / unknown target
3    precondition violated
1    failure
```

## Interactive flow

Default when a TTY is present. The command prints the plan for every target, then asks per target — `major / minor / patch / explicit version / skip` — defaulting to the inferred bump. Skipping a module is an ordinary choice, not a special case. A final summary is followed by **one confirmation**; nothing is written anywhere before it.

The per-repository transaction, in order:

1. `jq` updates the root `version` key in `composer.json`.
2. `git cliff --tag <new> --output CHANGELOG.md` — the fix for defect 1.
3. `git add composer.json CHANGELOG.md`
4. `git commit -m "chore(release): <new>"` — never `--amend`, which removes defect 2.
5. `git tag -a <new> -m "Release <new>"`
6. `git push origin HEAD:<branch>` then `git push origin <new>`, with errors surfaced rather than discarded.

On mid-transaction failure the command stops, prints exactly what exists and the commands to undo it. There is no automatic rollback. Under `--all`, a failing module aborts the whole run, so the root is never released with inconsistent pointers.

`^chore\(release\)` is added to the `cliff.toml` skip rules so release mechanics stop appearing in the next changelog; today's `chore: bump version to vX` commits do appear.

## Non-interactive flow

Selected by `-y` or by the absence of a TTY. Bump comes from `git cliff --bumped-version`; targets with no releasable commits are skipped and reported; no prompt is ever issued and no `read` is executed. `--bump` forces a level, `--set-version` applies to a single target, `--dry-run` and `--no-push` are honoured. Output is line-oriented and parseable.

This is the requirement that keeps future automation cheap: any trigger becomes a few lines of YAML calling the same command.

## Orchestration order for `--all`

1. Every module with pending commits, Core first, then alphabetical.
2. In the root: `git add Modules/<released...>` and a commit `chore(modules): bump Core v1.73.5, CMS v1.42.4`. This is a real commit, so it appears in the root changelog and contributes to the root's own bump.
3. The root release.

## Changelog

`cliff.toml` changes:

- Remove `{ body = "$^", skip = true }`.
- Review the remaining `skip` rules individually — `chore(deps.*)`, `chore(pr)`, `chore(pull)` and the merge patterns each need a deliberate decision.
- Add a skip for `^chore\(release\)`.
- Set `tag_pattern = "v[0-9]*"`.

A **single `cliff.toml` in the root** is used for every target via `--config`; the six module copies are deleted. This is coherent with having already removed the scripts from the modules, and removes another seven-way drift class.

Full regeneration is the only mode. `CHANGELOG.md` becomes a pure function of `(history, cliff.toml)`, which makes `composer run changelog:check` possible: regenerate to a temporary file, diff, fail on divergence. That check turns the defect class found here — releases silently vanishing from the changelog — into a test that fails the same day.

The cost is that hand edits to `CHANGELOG.md` are discarded. Release prose belongs in the commit body, which git-cliff renders, consistent with the `commit-msg` discipline.

## Hooks

No hook creates a release. `scripts/hooks/post-commit`, `scripts/setup-hooks.sh` and `scripts/test-hooks.sh` are deleted from all seven repositories; none is installed, so nothing breaks.

One hook is installed: **`commit-msg`**, rejecting messages that are not conventional commits. Automatic bump inference depends on the message format; without a gate upstream, inference downstream is a silent heuristic. Two of the commits released as `v1.14.0` during this design discussion (`Refactor code structure for improved readability`, `Add plans for Octane readiness`) are not conventional and would have contributed nothing to an inferred bump.

`install-hooks.sh` does not `chmod` tracked files, unlike today's `setup-hooks.sh`, which flips the executable bit on every run and dirties the working tree.

`scripts/install-hooks.sh` resolves the hooks directory with `git rev-parse --git-path hooks`, which is correct whether `.git` is a directory or a file — the fix for defect 4.

## Testing

`bats`, `shellcheck` and `shfmt` are not installed, and no new dependency is introduced. The harness is plain bash: it builds throwaway git repositories with synthetic histories under a temporary directory and asserts on

- inferred bump per commit mix, including breaking changes;
- changelog content, specifically that single-line commits appear;
- the tag created, and that it is not created in `--dry-run`;
- per-target skip;
- every exit code, especially `10`;
- the `--all` sequence with synthetic submodules, including pointer consolidation;
- target validation and the `--all` plus explicit target conflict.

Run with `composer run test:release`.

## Migration

1. Fix `cliff.toml` and fully regenerate the changelogs in all seven repositories, one commit each. This recovers the lost releases and closes every `[unreleased]` section, including the one created by `v1.14.0`.
2. Write `release.sh` and the test harness in the root.
3. Delete the scripts from the six modules and clean their `composer.json` — six commits in six repositories, and the first real exercise of the new command.
4. Add the Composer scripts in the root.
5. Add `commit-msg` and `install-hooks.sh`.
6. Add `branch = master` to `.gitmodules`.
7. Document the release process; no documentation exists today.

## Adjacent scripts

Three scripts in `laraplate/scripts/` are not release tooling but were audited alongside it and carry defects:

- `update_app.sh` exits `0` **before** `composer install`, `migrate` and `optimize` precisely when the pull brought a new tag, which inverts the intended deploy behaviour. It is also mode `644`, so it cannot be executed directly. Intent to be confirmed before changing it.
- `test-hooks.sh` is mode `644` and uses paths relative to the caller's working directory. It is deleted with the rest of the hook machinery.
- `fix-submodule-pushurl.sh` and `update-composer.sh` are unaffected and stay as they are.

## Deferred

- **CI release automation.** If added later, the sane triggers are `pull_request: closed` with `merged == true` (true "per merge, not per commit", and it works with squash merges), `workflow_dispatch`, or `push: tags`. A local `post-merge` hook is not among them: it fires on fast-forward merges and on `git pull`, and under `git merge --squash` it fires *before* the commit exists and never again.
- Versioning for `laraplate-ui` and `laraplate-importers`.
- Whether to keep the `version` key in `composer.json` at all.
