# Release tooling: versioning, changelog, and tagging

**Status:** Implemented (2026-09-15)

**Date:** 2026-08-30

**Updated:** 2026-09-15, aligned with the consolidation already shipped (`97ef076` in the application, "the module carries functionality, not the toolchain" in each module).

**Scope:** `laraplate` (the application) and its six module submodules (`Modules/{Core,CMS,AI,ERP,MES,SAO}`). `laraplate-ui` and `laraplate-importers` are out of scope.

## Problem

Release tooling was spread over 22 shell scripts across seven repositories: `scripts/version.sh` (7 byte-identical copies), `scripts/setup-hooks.sh` (2 variants), `scripts/hooks/post-commit` (3 divergent variants), plus `scripts/test-hooks.sh`. No hook was ever installed (every hook directory under `.git/modules/**/hooks` is empty), so every release is manual.

### State at 2026-09-15

Done:

- The six modules no longer carry `scripts/`, `cliff.toml` or any `version*`/`setup:hooks` Composer script.
- `laraplate/scripts/version.sh` is the only copy. It takes an optional target (`./scripts/version.sh Core patch`), `cd`s into it, reads `composer.json` from the target instead of from its own location, and passes the application's `cliff.toml` to git-cliff through `--config`.

How it got there. The consolidation was first planned inside the module testing strategy work and has been moved to this spec. `version.sh` was in active use (`chore: bump version to vX.Y.Z` commits as of 2026-09-15: Core 58, CMS 50, AI 14, ERP 9), so versioning a module was a capability to keep while its seven copies went. The copies could not simply be deleted: the script resolved `composer.json` from its own location (`BASH_SOURCE`) while every git call acted on the current directory, so the application's copy run inside a module would have written the application's version and tagged the module. Making the target explicit came first, then the copies were removed. The hook installer needed no such care: no hook was ever installed in any module or in the application, and bumps have always been run by hand.

What stays with a module is its `composer.json` `version` field, its `CHANGELOG.md` and its tags. Those are facts about the module; the programs that read and write them live in the application.

Still present in the application: `scripts/setup-hooks.sh`, `scripts/hooks/post-commit`, `scripts/test-hooks.sh`, the Composer script `setup:hooks`, and every defect below not marked resolved.

### Confirmed defects

| # | Defect | Evidence | Status |
|---|---|---|---|
| 1 | `update_changelog()` runs `git cliff` **before** the tag exists, so the release being cut is written under `## [unreleased]` | All seven `CHANGELOG.md` files carry an `[unreleased]` section holding their latest release (rechecked 2026-09-15). Reproduced live when `v1.14.0` was cut during design. | Open |
| 2 | `amend_or_commit()` runs `git commit --amend` when there are unpushed commits. From a `post-commit` hook this recursed without bound; run manually it folds the release into the user's last commit | `scripts/version.sh`, `amend_or_commit` | Open |
| 3 | `determine_release_type()` prints two lines when HEAD is already tagged (`"Commit is already tagged..."` then `"null"`). The caller tests `[ "$position" != "null" ]`, which is true, so `update_version` runs with a garbage position | Survives only through the "already up to date" branch | Open |
| 4 | `scripts/setup-hooks.sh` assumes `$APP_DIR/.git` is a directory. `laraplate` is a submodule of the stack, so `.git` is a file and both `mkdir -p` and `ln -sf` fail | `laraplate/.git` contains `gitdir: ../.git/modules/laraplate` | Resolved by deletion |
| 5 | Module `setup-hooks.sh:14` used `[ ! -f "$GIT_DIR" \|\| ! -f ... ]`, a runtime `missing ']'` error | Module copies | Resolved by deletion |
| 6 | Module `post-commit` resolved `version.sh` through a hardcoded `Modules/Cms` path and a path that never existed, working only by falling through | Module copies | Resolved by deletion |
| 7 | `get_latest_version()` uses `git rev-list --tags --max-count=1`: most recent tag by commit date, not highest semver, and no filter on the tag namespace | The application carries nine `backup/v1.11.x` to `backup/v1.13.x` tags alongside `v*` | Open |
| 8 | `git push 2>/dev/null` with no refspec hides real errors, and the pushed branch depends on `push.default` | `update_version` | Open |
| 9 | Flags are parsed by substring match on `"$*"`, unknown flags are ignored, there is no `--help` | Bottom of `version.sh` | Open |
| 10 | `cliff.toml` contains `{ body = "$^", skip = true }`, which drops every commit with an empty body. It sits above the group parsers, so it wins | See below. Since `cliff.toml` is now shared, it applies to all seven repositories | Open |
| 11 | A bump keyword placed before the target silently versions the application. `version.sh minor Core --dry-run` prints `would update the application from v1.14.0 to v1.15.0`, while `version.sh Core minor --dry-run` correctly targets Core. `composer run version:minor Core` expands to the first form | Verified 2026-09-15 | Open |
| 12 | `scripts/setup-hooks.sh` still runs `Modules/*/scripts/setup-hooks.sh` for every module, and those files no longer exist | Application `scripts/setup-hooks.sh`, module loop | Resolved by deletion |

### The `body = "$^"` rule

Most commits in these repositories are single-line, so this rule deletes them from the changelog. Measured on `Modules/CMS`:

| | with the rule | without it |
|---|---|---|
| changelog sections | 66 | **100** (exactly the tag count) |
| changelog entries | 142 | **319** (of 351 commits) |

Regenerating with the committed config reproduced the in-tree file exactly, so the changelogs are not stale: they are incomplete by construction.

The rule also neuters version computation. On the application, with four commits after the tag:

```
git cliff --bumped-version, current config   ->  v1.13.8   (no bump)
git cliff --bumped-version, rule removed     ->  v1.13.9
```

### What is missing

- No real interactive mode: no preview, no confirmation, no per-target choice.
- No explicit version override, no `--no-push`, no orchestration across modules. Consolidating submodule pointers in the application is a manual commit.
- Bump inference (regexes in `version.sh`) and changelog grouping (`commit_parsers` in `cliff.toml`) are two grammars that can diverge silently.
- The release process is undocumented.

## Decision summary

1. **One script, in the application only: `scripts/version.sh`.** It releases a single repository or runs the orchestrated sequence. The module copies are already gone; no module has a CI workflow, so nothing depended on them.
2. **Releases are a deliberate manual gesture.** No hook creates tags. The bump is computed over `last-tag..HEAD`, so many commits collapse into one release by construction.
3. **git-cliff stays.** The configuration was at fault, not the tool. git-cliff also replaces the hand-written bump inference through `--bumped-version`, so `cliff.toml` is the single grammar for changelog and version.
4. **Changelogs are fully regenerated, never appended.** `CHANGELOG.md` is a pure function of `(history, cliff.toml)` and therefore verifiable.
5. **The engine is automatable, but no automation is built now.** A future GitHub Action calls the same command unchanged.
6. **No git hooks.** Nothing in git triggers a release, a changelog regeneration or a message check. Bump inference relies on commit discipline: a message that is not a conventional commit cannot signal a feature or a breaking change.

## Non-goals

- Building a CI release pipeline. The trigger choice stays open (see Deferred).
- Versioning `laraplate-ui` or `laraplate-importers`.
- Removing the `version` key from `composer.json`. The module commit kept it deliberately as a fact about the module.
- Rewriting git history or deleting the `backup/*` tags.

## Architecture

A single file, `laraplate/scripts/version.sh`, organised as functions over one code path:

| Function | Responsibility |
|---|---|
| `parse_args` | Classify every argument as flag, bump keyword or target; validate targets; expand `--all` |
| `plan_target <path>` | Read-only state of one repository: current version, commits since last tag, proposed version (`git cliff --bumped-version`), verdict |
| `render_plan` | The table shown by `--dry-run` and before every confirmation |
| `release_target <path> <version>` | The write transaction for one repository |
| `consolidate_pointers` | Stage and commit released submodule pointers in the application; `--all` only |

`release_target` is identical for the application and for any module; only the working directory differs. There is no separate path for modules, and none for interactive versus automated use.

The script stays a single file. The implementation plan measured the complete script at about 580 lines, including the help text; splitting it into sourced libraries would add path resolution to a script reached through Composer and symlinks, for no second consumer. The unit of reuse is the function, and every function is exercised by the harness.

**To delete:** `scripts/setup-hooks.sh`, `scripts/hooks/post-commit`, `scripts/test-hooks.sh`.
**To add:** `scripts/tests/version-test.sh`.

## CLI surface

```
version.sh [target ...] [major|minor|patch] [options]

  (no target)          the application
  Core                 only Core
  Core CMS             Core and CMS
  --all                every module with pending commits, then the application

  major|minor|patch    force the bump level; without it the bump is inferred
  --set-version <v>    explicit version; single target only
  --nointeractive      no prompts; skip targets with nothing to release
  -n, --dry-run        print the plan, write nothing
  --no-push            commit and tag locally, do not push
  --allow-dirty        proceed with a dirty working tree
  --changelog          regenerate CHANGELOG.md for the targets, nothing else
  --changelog-check    regenerate to a temporary file and diff, for all seven repositories
  -h, --help
```

`--silent` is removed: its only use was a debug print of the computed version, which `--dry-run` supersedes.

Every argument is exactly one of: a flag, a bump keyword, a target. `major`, `minor` and `patch` are reserved and recognised in any position, which fixes defect 11. A target is a module name, matched case-insensitively against `Modules/*`, or a `Modules/<Name>` path. Anything else is a hard error listing the valid names; there is no fallback to the application. `--all` together with an explicit target is an error.

Targets are positional because Composer forwards bare words but intercepts flags. Verified on Composer 2.10.3:

```
composer run <script> Core        ->  ARGS=[Core]        ok
composer run <script> -- -m Core  ->  ARGS=[-m Core]     needs the --
composer run <script> --all       ->  ARGS=[]            Composer swallows it
```

Flags are therefore never passed through Composer; each flag combination has its own named script:

```
composer run version                    version.sh --nointeractive       application, inferred (unchanged)
composer run version:major [targets]    version.sh major
composer run version:minor [targets]    version.sh minor
composer run version:patch [targets]    version.sh patch
composer run version:module <targets>   version.sh                       interactive (unchanged name)
composer run version:all                version.sh --all
composer run version:dry [targets]      version.sh --dry-run
composer run version:test               scripts/tests/version-test.sh
composer run changelog [targets]        version.sh --changelog
composer run changelog:check            version.sh --changelog-check
```

`version:silent` is removed with `--silent`.

Versions are normalised to `vX.Y.Z`, so `--set-version 2.0.0` and `--set-version v2.0.0` are equivalent.

## Preconditions and exit codes

Checked per target before anything is written:

- `git`, `git-cliff` and `jq` are available, with a message naming what to install.
- HEAD is on a branch, not detached. `.gitmodules` declares no `branch`, so a clean `git submodule update` leaves modules detached; `branch = master` is added as hardening.
- The working tree is clean, unless `--allow-dirty`.
- An upstream is configured, when pushing.
- HEAD does not already carry a `v*` tag.
- Tags are filtered by `^v?[0-9]+\.[0-9]+\.[0-9]+$`, compared without the prefix and sorted by version, and `cliff.toml` sets the same anchored `tag_pattern`. `tag_pattern` is an unanchored regex: `v[0-9]*` was verified to select `backup/v9.9.9`, so the anchors are required. The `v` is optional because the application, Core and CMS carry early unprefixed tags (`1.0.0` to `1.6.4` in the application, 22 in Core, 19 in CMS); excluding them drops their changelog sections. New tags are always written as `vX.Y.Z`.

```
0    released (or changelog regenerated / check passed)
10   nothing to release     success for a pipeline, not a failure
2    misuse / unknown target
3    precondition violated
4    changelog check found divergence
1    failure
```

## Interactive flow

Active when a controlling terminal exists and `--nointeractive` is absent. Prompts are read from and written to `/dev/tty` rather than stdin, because `composer run` does not guarantee a terminal on stdin; Composer scripts that can prompt disable Composer's process timeout (`Composer\\Config::disableProcessTimeout`) so a pending prompt is not killed after 300 seconds. The command prints the plan for every target. Without a bump keyword it asks per target (`major / minor / patch / explicit version / skip`), defaulting to the inferred bump; skipping is an ordinary choice. With a keyword the level is fixed and no per-target question is asked. In both cases a summary is followed by **one confirmation**, and nothing is written before it.

The per-repository transaction, in order:

1. `jq` updates the root `version` key in `composer.json`.
2. `git cliff --config <application>/cliff.toml --tag <new> --output CHANGELOG.md` (fixes defect 1).
3. `git add composer.json CHANGELOG.md`
4. `git commit -m "chore(release): <new>"`, never `--amend` (fixes defect 2).
5. `git tag -a <new> -m "Release <new>"`
6. `git push origin HEAD:<branch>`, then `git push origin <new>`, errors surfaced (fixes defect 8).

On failure mid-transaction the command stops and prints what exists and the exact commands to undo it. There is no automatic rollback. Under `--all`, a failing module aborts the run, so the application is never released with inconsistent pointers.

## Non-interactive flow

Selected by `--nointeractive` or by the absence of a TTY. The bump comes from `git cliff --bumped-version` unless a keyword forces it; targets with nothing releasable are skipped and reported; no prompt is issued and no `read` runs. `--set-version`, `--dry-run` and `--no-push` are honoured. Output is line-oriented and parseable.

## Orchestration order for `--all`

1. Every module with pending commits, Core first, then alphabetical.
2. In the application: `git add Modules/<released...>` and one pointer commit whose type carries the highest level among the released modules: `feat(modules)!: bump Core v2.0.0, CMS v1.42.4` when any module released a major, `feat(modules): bump ...` for a minor, `chore(modules): bump ...` when every module released a patch. It is a real commit, so git-cliff files it under the matching changelog group and infers the application's level from it. A plain `chore(modules)` would count as a patch whatever the modules released, which is how a module feature or breaking change used to reach the application as a patch.
3. The application release, at least at the highest module level; a forced `major|minor|patch` still wins. Interactively the application's level can be lowered when a module change does not reach the application; the pointer commit keeps the modules' level either way.

Releasing named modules without `--all` does not touch the application.

## Changelog

The shared `cliff.toml` (already the only copy, in the application) changes as follows:

- Remove `{ body = "$^", skip = true }` (fixes defect 10).
- Fix the merge skip: `^\\(Merge branch ` requires a literal `(` and never matches a real merge message. Verified: a `Merge branch 'side'` commit bumped the version with the current rule and did not with `^Merge branch `.
- Remove the `chore(deps.*)` skip: dependency and submodule-pointer updates are real changes of a release and must appear and count. `chore(pr)` and `chore(pull)` stay skipped.
- Verified with git-cliff 2.13.1: commits matched by a `skip` rule do not contribute to `--bumped-version`; with only skipped commits after the tag it returns the current tag. On a repository with no tags it returns `0.1.0` without the `v` prefix.
- Skip `^chore\(release\)` and the legacy `^chore: bump version to`, so release mechanics never appear as entries.
- Set `tag_pattern = "^v?[0-9]+\\.[0-9]+\\.[0-9]+$"` (anchored, optional prefix, see Preconditions).

Full regeneration is the only mode. `--changelog-check` regenerates each repository's changelog to a temporary file and diffs it against the committed one, **ignoring the `[unreleased]` section on both sides**, exiting `4` on divergence. The committed `[unreleased]` section is stale by nature after any commit, so comparing it would fail on every commit following a release; released sections never change unless history or `cliff.toml` does. That check turns the defect class found here, releases silently vanishing from changelogs, into a failure visible the same day.

`CHANGELOG.md` only ever lists released versions and changes only when a release is cut. `--changelog` writes the regenerated file without the `[unreleased]` section, so running it between releases leaves the file identical to what the last release wrote; it exists to repair released sections after a `cliff.toml` change. Work committed after the last tag appears in the changelog when it is released, under its version, never before.

Hand edits to `CHANGELOG.md` are discarded by design. Release prose belongs in the commit body, which git-cliff renders.

## Hooks

There are no git hooks. `scripts/hooks/post-commit`, `scripts/setup-hooks.sh`, `scripts/test-hooks.sh` and the `setup:hooks` Composer script are deleted; none was ever installed, and `setup-hooks.sh` was already broken by defects 4 and 12.

A `commit-msg` gate for conventional commits was built during implementation and removed on 2026-09-15 by the owner's decision, consistent with the module testing strategy work, which had already dropped the hooks. Bump inference therefore rests on commit discipline. Two of the commits released as `v1.14.0` (`Refactor code structure for improved readability`, `Add plans for Octane readiness`) are not conventional and show what that discipline has to catch.

## Testing

`bats`, `shellcheck` and `shfmt` are not installed and no dependency is added. Two environment variables exist only as test seams: `VERSION_ROOT_DIR` points the script at a synthetic application, and `VERSION_FORCE_INTERACTIVE` (`1` reads prompts from stdin, `0` never prompts) makes prompting deterministic regardless of the terminal the harness runs in. `scripts/tests/version-test.sh` is plain bash: it builds throwaway repositories with synthetic histories in a temporary directory and asserts on

- argument classification, including a keyword before the target (defect 11) and unknown targets;
- inferred bump per commit mix, including breaking changes;
- changelog content: the new version has its own section, no `[unreleased]` section remains, single-line commits appear;
- the tag is created, and is not created under `--dry-run`;
- no `--amend`: the user's last commit hash is unchanged after a release;
- `backup/*` tags are ignored when computing the current version;
- per-target skip and every exit code, especially `10` and `4`;
- the `--all` sequence with synthetic submodules, including pointer consolidation.

## Migration

Done on 2026-09-15: toolchain removed from the six modules (scripts, `cliff.toml`, Composer scripts), single `cliff.toml` read through `--config`, target argument in `version.sh`.

Remaining, in order:

1. **Stop the damage:** `--tag` in the changelog step, the `cliff.toml` changes above, and full regeneration of `CHANGELOG.md` in all seven repositories (one commit each). Simulated on 2026-09-15: sections per repository go from 34/138/68/30/31/2/5 to 35/152/98/36/49/2/5 (application/Core/CMS/AI/ERP/MES/SAO). Every repository has commits after its last tag, so a regenerated `[unreleased]` section remains and is now correct: it holds genuinely unreleased work instead of the last release. The only tags still without a section are releases whose sole commit is a legacy `chore: bump version to ...`, which is skipped: an empty release has nothing to list.
2. Test harness, before touching the engine further.
3. Argument parsing: classification, keyword in any position (defect 11), target validation, `--help`, exit codes, removal of `--silent` (defect 9).
4. Write-path correctness: plain commit (defect 2), tag detection and version lookup (defects 3 and 7), explicit push (defect 8).
5. New surface: plan rendering, interactive per-target choice, `--nointeractive` semantics, `--set-version`, `--no-push`, `--all` with pointer consolidation, `--changelog`, `--changelog-check`.
6. Hooks: delete `setup-hooks.sh`, `hooks/post-commit`, `test-hooks.sh` and the `setup:hooks` Composer script.
7. `branch = master` in `.gitmodules`.
8. Composer scripts as listed above.
9. Document the release process.

## Adjacent scripts

- `update_app.sh` asks for confirmation before the full update since `61a9f22` (2026-09-02); the inverted early exit found during design no longer applies. It is deploy tooling and stays as it is.
- `fix-submodule-pushurl.sh` and `update-composer.sh` are unaffected.

## Success criteria

- `./scripts/version.sh <Module> patch` versions that module from the application: the module's tags, `CHANGELOG.md` and `version` field change, and the application's HEAD does not.
- No module carries `scripts/version.sh`, `scripts/setup-hooks.sh`, `scripts/hooks/` or `cliff.toml`. `Modules/Core/scripts/bench/` holds benchmark fixtures and is not release tooling.
- `--changelog-check` passes on all seven repositories.
- `bash scripts/tests/version-test.sh` passes and covers every defect marked open in the table above.

## Deferred

- **CI release automation.** Viable triggers: `pull_request: closed` with `merged == true` (per merge rather than per commit, works with squash merges), `workflow_dispatch`, or `push: tags`. A local `post-merge` hook is not viable: it fires on fast-forward merges and on `git pull`, and under `git merge --squash` it fires before the commit exists and never again.
- Versioning for `laraplate-ui` and `laraplate-importers`.
- Whether to keep the `version` key in `composer.json` at all.
