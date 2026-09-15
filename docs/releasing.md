# Releasing

The application and each module under `Modules/` are released with one script, `scripts/version.sh`. It bumps `composer.json`, regenerates `CHANGELOG.md` with git-cliff, creates a `chore(release): vX.Y.Z` commit, an annotated `vX.Y.Z` tag, and pushes both. Design record: `docs/superpowers/specs/2026-08-30-release-tooling-design.md`.

## Requirements

`git`, `git-cliff` and `jq` on `PATH`. Every repository to release must be on a branch with an upstream and a clean working tree.

## Everyday commands

| Command | Effect |
|---|---|
| `composer run version:dry [Module...]` | Print the plan, write nothing |
| `composer run version:module [Module...]` | Interactive release of the application, or of the named modules |
| `composer run version:patch [Module...]` | Release with a forced level (also `version:minor`, `version:major`) |
| `composer run version:all` | Every module with pending commits, then the application with the new module pointers |
| `composer run version [Module...]` | Non-interactive release with the inferred level |
| `composer run changelog [Module...]` | Regenerate `CHANGELOG.md` only |
| `composer run changelog:check` | Verify all seven changelogs against history |
| `composer run version:test` | Run the release tooling tests |

Flags cannot be passed through `composer run` (Composer consumes them). For combinations not listed, call the script directly, for example `./scripts/version.sh Core CMS --no-push`. Run `./scripts/version.sh --help` for every option.

## How the version is chosen

git-cliff reads the commits since the last `vX.Y.Z` tag through `cliff.toml`: a `!` or `BREAKING CHANGE` means major, `feat` means minor, anything else that is not skipped means patch. Commits matched by a skip rule (merges, `chore(release)`) never cause a release. With only skipped commits, or none, the target has nothing to release and the script exits `10`.

Interactively, each target shows the inferred version and accepts `major`, `minor`, `patch`, an explicit version, `skip`, or Enter to keep it. One confirmation follows; nothing is written before it.

## Changelogs

`CHANGELOG.md` is regenerated in full from history on every release. Do not edit it by hand: the next release discards the edit. Anything a release note should say belongs in the commit body. `composer run changelog:check` fails when a released section no longer matches history.

## `--all`

Modules are released first, Core before the others. The application then gets one commit recording the released modules, typed after the highest module level (`feat(modules)!:` when a module released a major, `feat(modules):` for a minor, `chore(modules):` when all are patches), and is released at least at that level, so its version and changelog show what kind of update the modules brought. If any module fails a precondition, nothing is written anywhere. Releasing a named module never touches the application.

## Exit codes

`0` done, `10` nothing to release, `2` usage error, `3` precondition failed, `4` changelog diverged, `1` failure. After a failure the script prints what it left behind and the commands to undo it.
