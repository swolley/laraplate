# Release Tooling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn `scripts/version.sh` into a single, tested release tool for the application and its six modules: correct changelogs, safe tagging, interactive and non-interactive use, `--all` orchestration.

**Architecture:** One bash script in the application (`laraplate/scripts/version.sh`) organised as small functions over one code path; git-cliff with the shared `cliff.toml` computes both the changelog and the inferred version. A dependency-free bash harness (`scripts/tests/version-test.sh`) builds throwaway repositories, including submodules, and drives the script through two test seams.

**Tech Stack:** bash 5.3, git 2.55, git-cliff 2.13.1, jq 1.8, GNU coreutils (`sort -V`), Composer 2.10 scripts.

**Spec:** `docs/superpowers/specs/2026-08-30-release-tooling-design.md` (updated 2026-09-15). Read it before starting; the defect numbers (#1 to #12) used below refer to its table.

## Global Constraints

- All paths are relative to `/srv/http/laraplate-stack/laraplate` unless stated otherwise.
- Code, comments and docs in English. No em-dashes and no emojis in new text.
- No new dependencies: no bats, shellcheck, shfmt, npm packages or Composer packages.
- Exactly one release script: `scripts/version.sh`. Modules carry no scripts and no `cliff.toml` (already true).
- Version tags match `^v?[0-9]+\.[0-9]+\.[0-9]+$`; new tags are always written as `vX.Y.Z`.
- Release commit message: `chore(release): vX.Y.Z`. Pointer commit message: `<type>: bump <Name> vX.Y.Z[, <Name> vX.Y.Z...]`, where `<type>` is `feat(modules)!`, `feat(modules)` or `chore(modules)` after the highest module level (Task 10).
- Never `git commit --amend`. Never `git push` without an explicit remote and refspec. Never discard stderr of a failing step.
- Exit codes: `0` done, `1` failure, `2` usage, `3` precondition, `4` changelog diverged, `10` nothing to release.
- Test seams, used only by the harness: `VERSION_ROOT_DIR` (application root), `VERSION_FORCE_INTERACTIVE` (`1` prompts read stdin, `0` never prompt).
- Every commit ends with the trailer `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>`.
- Do not push any repository. The user pushes.
- The full Laravel test suite is not relevant here and must not be run (it exceeds an hour). Run only `bash scripts/tests/version-test.sh`.

## File Structure

| File | Responsibility | Tasks |
|---|---|---|
| `cliff.toml` | Shared git-cliff grammar for changelog and version inference | 1 |
| `scripts/version.sh` | The release tool | 1, 3, 4, 5, 6, 7 |
| `scripts/tests/version-test.sh` | Harness: fixtures, assertions, tests | 3 to 8 |
| `scripts/setup-hooks.sh`, `scripts/hooks/post-commit`, `scripts/test-hooks.sh` | Deleted | 8 |
| `CHANGELOG.md`, `Modules/*/CHANGELOG.md` | Regenerated | 2 |
| `composer.json` | Composer scripts | 4, 8, 9 |
| `.gitmodules` | `branch = master` per module | 9 |
| `docs/releasing.md`, `docs/README.md` | Operator documentation | 9 |

---

## Delivered before this plan

The first half of this work shipped on 2026-09-15 as Tasks 4b and 4c of
`2026-05-21-module-testing-strategy.md`, a plan about the test toolchain where it did not belong. Both
tasks are moved here as the record, with the commands as they were run. The one step they left open,
a real release of a module, is now Task 9 Step 8: it must run with the rewritten script, because the
script it was written against folds the release into the last unpushed commit (defect #2).

### Delivered: Remove the git hook installer from the modules (was Task 4b)

`setup-hooks.sh` installs a `post-commit` hook that runs `version.sh` after every commit. It has
never been installed: no module has a hook in its git directory, and neither does the application.
The version bumps have always been run by hand, which is how `version.sh` is meant to be used
anyway.

**Files:**
- Delete: `Modules/{Core,CMS,AI,ERP,MES,SAO}/scripts/setup-hooks.sh`
- Delete: `Modules/{Core,CMS,AI,ERP,MES,SAO}/scripts/hooks/`
- Modify: `Modules/{Core,CMS,AI,ERP,MES,SAO}/composer.json` (drop `setup:hooks`)

- [x] **Step 1: Confirm no hook is installed anywhere**

Run:

```bash
for m in Core CMS AI ERP MES SAO; do echo -n "$m: "; rtk git -C Modules/$m rev-parse --git-dir; done
rtk ls "$(git rev-parse --git-dir)/hooks" | rtk rg -v sample
```

Expected: no non-sample hook in the application's git directory, and none in any module's. If a
hook turns up in a module, stop: someone installed it and this task needs their input first.

- [x] **Step 2: Confirm `version.sh` is the part that is actually used**

Run:

```bash
for m in Core CMS AI ERP MES SAO; do echo -n "$m bumps: "; rtk git -C Modules/$m log --oneline -i --grep='bump version' | wc -l; done
```

Expected: a non-zero count in most modules (Core 58, CMS 50, AI 14, ERP 9 as of 2026-09-15), in the
`chore: bump version to vX.Y.Z` form `version.sh` produces. This is the evidence that separates the
script being deleted from the one being kept.

- [x] **Step 3: Delete the hook machinery**

Run:

```bash
rtk git rm -r Modules/Core/scripts/hooks Modules/CMS/scripts/hooks Modules/AI/scripts/hooks Modules/ERP/scripts/hooks Modules/MES/scripts/hooks Modules/SAO/scripts/hooks
rtk git rm Modules/Core/scripts/setup-hooks.sh Modules/CMS/scripts/setup-hooks.sh Modules/AI/scripts/setup-hooks.sh Modules/ERP/scripts/setup-hooks.sh Modules/MES/scripts/setup-hooks.sh Modules/SAO/scripts/setup-hooks.sh
```

Then remove the `"setup:hooks"` entry from each module's `composer.json` `scripts`.

- [x] **Step 4: Confirm versioning still works**

Run:

```bash
rtk ls Modules/*/scripts/version.sh
rtk rg -n '"version"|"version:patch"' Modules/Core/composer.json
```

Expected: `version.sh` present in all six modules and the `version*` scripts intact. Do not run a
bump to test this; it tags and pushes.

- [x] **Step 5: Check the documentation**

Run:

```bash
rtk rg -n 'setup-hooks|setup:hooks' --glob '!vendor' .
```

Expected: after this task, hits only in the application's own `scripts/` and `composer.json`. Any
module README or RAG doc describing the hook installation is corrected or removed. The application
kept its copy at the time, even though nothing had installed it either; Task 8 of this plan
deletes it.

- [x] **Step 6: Commit**

Run:

```bash
rtk git add Modules
rtk git commit -m "chore: drop the unused git hook installer from the modules"
```

Expected: commit succeeds. Each module is a submodule: commit inside the module first, then record
the pointer.

---

### Delivered: Centralize versioning in one script (was Task 4c)

`version.sh` is byte-identical in the root and all six modules, and so is `cliff.toml`. Seven
copies of each. The obvious move is to keep one and pass it a module name, and that is the right
move, but the script cannot do it today and the reason matters.

It resolves the package to bump from its own location:

```bash
version_script_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
composer_file="$(cd "$version_script_dir/.." && pwd)/composer.json"
```

while every Git operation in it (`git describe`, `git tag`, `git add`, `git commit`, `git push`)
acts on the repository of the *current directory*. The two halves disagree the moment they are not
the same place: running the root copy from inside `Modules/Core` would write the version into the
application's `composer.json` and tag Core. Its argument parsing has no target either, only
`{major|minor|patch}` plus `--nointeractive`, `--silent`, `--dry-run` and `--allow-dirty`.

So: teach it the target, prove it works, then delete the copies.

**Files:**
- Modify: `scripts/version.sh`
- Modify: `composer.json`
- Delete: `Modules/{Core,CMS,AI,ERP,MES,SAO}/scripts/version.sh`
- Delete: `Modules/{Core,CMS,AI,ERP,MES,SAO}/cliff.toml`
- Modify: `Modules/{Core,CMS,AI,ERP,MES,SAO}/composer.json` (drop `version`, `version:*`)

- [x] **Step 1: Give the script a target directory**

In `scripts/version.sh`, accept an optional first argument naming a module (`Core`) or a path
(`Modules/Core`), defaulting to the application itself. Resolve it to an absolute `TARGET_DIR`,
fail with a clear message if it is not a Git repository, and `cd` into it before anything else
runs.

Then make the two halves agree: `update_composer_version` must read `"$TARGET_DIR/composer.json"`
instead of deriving the path from `BASH_SOURCE`. That single substitution is what currently makes
the script un-relocatable.

- [x] **Step 2: Point `git cliff` at the shared configuration**

`git cliff --output CHANGELOG.md` reads `cliff.toml` from the working directory, which is why every
module carries its own identical copy. Pass the root one explicitly:

```bash
git cliff --config "$ROOT_DIR/cliff.toml" --output CHANGELOG.md
```

where `ROOT_DIR` is the directory holding the script. `CHANGELOG.md` still lands in `TARGET_DIR`,
which is correct: the changelog belongs to the repository being versioned.

- [x] **Step 3: Prove it on a module without tagging anything**

Run:

```bash
rtk ./scripts/version.sh Core patch --dry-run
rtk ./scripts/version.sh Modules/CMS --nointeractive --dry-run
rtk ./scripts/version.sh patch --dry-run
```

Expected: the first two report the next version for that module, computed from *that module's*
tags and commits, and name that module's `composer.json`. The third still targets the application.
Nothing is tagged, committed or pushed.

Then confirm nothing was touched:

```bash
rtk git status --short
rtk git -C Modules/Core status --short
```

Expected: no change in either. `--dry-run` exists in the script already; if it turns out not to
cover the composer write, stop and fix that before going further, because the next step deletes the
fallback.

- [x] **Step 4: Add the root Composer entry points**

In the root `composer.json`:

```json
"version:module": "./scripts/version.sh"
```

Used as `composer version:module Core patch`. Keep the existing application-level `version` and
`version:*` scripts as they are.

- [x] **Step 5: Delete the copies**

Run:

```bash
rtk git rm Modules/Core/scripts/version.sh Modules/CMS/scripts/version.sh Modules/AI/scripts/version.sh Modules/ERP/scripts/version.sh Modules/MES/scripts/version.sh Modules/SAO/scripts/version.sh
rtk git rm Modules/Core/cliff.toml Modules/CMS/cliff.toml Modules/AI/cliff.toml Modules/ERP/cliff.toml Modules/MES/cliff.toml Modules/SAO/cliff.toml
```

Then remove `version`, `version:silent`, `version:major`, `version:minor` and `version:patch` from
each module's `composer.json` `scripts`. The `version` *field* stays: it is data about the module,
and the root script writes it.

At this point `Modules/*/scripts/` is empty in every module and the directory goes too.

- [x] **Step 6: Update the documentation**

Run:

```bash
rtk rg -n 'composer version|scripts/version.sh|cliff.toml' --glob '*.md' --glob '!vendor' .
```

Expected: every module document describing `composer version:patch` now describes
`composer version:module <Module> patch`, run from the application.

- [x] **Step 7: Commit**

Run:

```bash
rtk git add scripts/version.sh composer.json Modules
rtk git commit -m "chore: one versioning script for the whole stack"
```

Expected: commit succeeds.

---

### Task 1: Stop the damage in `cliff.toml` and the changelog step

Fixes defects #1 and #10 in place, before any rewrite, because every release made with the current files writes a wrong changelog in all seven repositories.

**Files:**
- Modify: `cliff.toml` (`[git]` table and `commit_parsers`)
- Modify: `scripts/version.sh` (`update_changelog`)

**Interfaces:**
- Consumes: nothing.
- Produces: the corrected `cliff.toml` that every later task and the harness copy verbatim.

- [ ] **Step 1: Record the baseline**

Run:

```bash
cd /srv/http/laraplate-stack/laraplate
git status --porcelain -- cliff.toml scripts/version.sh
grep -n 'body = "\$\^"\|tag_pattern\|Merge branch\|chore\\\\(release\|chore\\\\(deps' cliff.toml
```

Expected: no output from `git status`; the grep shows `{ body = "$^", skip = true }`, `^\\(Merge branch `, `^chore\\(release\\): prepare for`, `^chore\\(deps.*\\)`, and no `tag_pattern`. If `git status` shows changes, stop and ask the user.

- [ ] **Step 2: Edit `cliff.toml`**

In the `[git]` table, directly under the line `[git]`, add:

```toml
# only plain semantic version tags are releases; backup/* and other tags are ignored
tag_pattern = "^v?[0-9]+\\.[0-9]+\\.[0-9]+$"
```

Replace the whole `commit_parsers = [ ... ]` array with:

```toml
commit_parsers = [
  { message = "^Merge branch ", skip = true },
  { message = "^\\(merge conflict\\)$", skip = true },
  { message = "^\\(merge divergent conflict\\)$", skip = true },
  { message = "^[Ii]nitial commit$", skip = true },
  { message = "^chore\\(release\\)", skip = true },
  { message = "^chore: bump version to", skip = true },
  { message = "^feat", group = "<!-- 0 -->🚀 Features" },
  { message = "^fix", group = "<!-- 1 -->🐛 Bug Fixes" },
  { message = "^doc", group = "<!-- 3 -->📚 Documentation" },
  { message = "^perf", group = "<!-- 4 -->⚡ Performance" },
  { message = "^refactor", group = "<!-- 2 -->🚜 Refactor" },
  { message = "^style", group = "<!-- 5 -->🎨 Styling" },
  { message = "^test", group = "<!-- 6 -->🧪 Testing" },
  { message = "^chore\\(pr\\)", skip = true },
  { message = "^chore\\(pull\\)", skip = true },
  { message = "^chore|^ci", group = "<!-- 7 -->⚙️ Miscellaneous Tasks" },
  { body = ".*security", group = "<!-- 8 -->🛡️ Security" },
  { message = "^revert", group = "<!-- 9 -->◀️ Revert" },
  { message = ".*", group = "<!-- 10 -->💼 Other" },
]
```

The group names keep their existing emoji because they are rendered into the existing changelogs; changing them would rewrite every section heading. What changed: the empty-body skip is gone (#10), the merge skip no longer requires a literal `(`, release mechanics (`chore(release)`, legacy `chore: bump version to`) are skipped before any group rule, and `chore(deps...)` is no longer skipped.

- [ ] **Step 3: Edit `update_changelog` in `scripts/version.sh`**

Replace the line

```bash
    git cliff --config "$ROOT_DIR/cliff.toml" --output CHANGELOG.md
```

with

```bash
    git cliff --config "$ROOT_DIR/cliff.toml" --tag "$new_version" --output CHANGELOG.md
```

- [ ] **Step 4: Verify the configuration against all seven repositories without writing any changelog**

Run:

```bash
cd /srv/http/laraplate-stack/laraplate
APP=$(pwd)
OUT=$(mktemp -d)
for repo in . Modules/Core Modules/CMS Modules/AI Modules/ERP Modules/MES Modules/SAO; do
    name=$(basename "$(cd "$repo" && pwd)")
    (cd "$repo" && git cliff --config "$APP/cliff.toml" --output "$OUT/$name.md" 2>/dev/null)
    problems=0
    prev=""
    for tag in $(git -C "$repo" tag --list | grep -E '^v?[0-9]+\.[0-9]+\.[0-9]+$' | awk '{ v = $0; sub(/^v/, "", v); print v "\t" $0 }' | sort -t$'\t' -k1,1V | cut -f2); do
        if [ -n "$prev" ] && ! grep -q "^## \[${tag#v}\]" "$OUT/$name.md"; then
            if git -C "$repo" log --format=%s "$prev..$tag" | grep -vq '^chore: bump version to'; then
                echo "$name: $tag has no section but carries real commits"
                problems=1
            fi
        fi
        prev=$tag
    done
    echo "$name sections=$(grep -c '^## \[[0-9]' "$OUT/$name.md") unreleased=$(grep -c '^## \[unreleased\]' "$OUT/$name.md") problems=$problems"
done
rm -rf "$OUT"
```

Expected: seven lines, every one with `problems=0` and `unreleased=0` or `unreleased=1`. On 2026-09-15 the section counts were laraplate 35, Core 152, CMS 98, AI 36, ERP 49, MES 2, SAO 5; they may only have grown since. Any line starting with `<name>: <tag> has no section` is a failure: stop and report it.

- [ ] **Step 5: Verify the tag pattern ignores backup tags**

Run:

```bash
cd /srv/http/laraplate-stack/laraplate
git cliff --config cliff.toml --bumped-version 2>/dev/null
```

Expected: a `v`-prefixed version, never a `backup/...` value. On 2026-09-15 it was `v2.0.0`, because `8322ea0 feat(modules)!: record locking overhaul` is a breaking change after `v1.14.0`.

- [ ] **Step 6: Commit**

```bash
cd /srv/http/laraplate-stack/laraplate
git add cliff.toml scripts/version.sh
git commit -m "fix(release): keep single-line commits and tag the release being written" -m "cliff.toml skipped every commit with an empty body, which dropped most commits from all seven changelogs and hid them from git cliff --bumped-version. The merge skip required a literal parenthesis and never matched. The changelog was generated before the tag existed, so each release landed under [unreleased]." -m "Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 2: Regenerate the seven changelogs

**Files:**
- Modify: `CHANGELOG.md`, `Modules/{Core,CMS,AI,ERP,MES,SAO}/CHANGELOG.md`

**Interfaces:**
- Consumes: `cliff.toml` from Task 1.
- Produces: changelogs that match history, which Task 7's `--changelog-check` relies on.

- [ ] **Step 1: Check that every repository is clean**

Run:

```bash
cd /srv/http/laraplate-stack/laraplate
for repo in . Modules/Core Modules/CMS Modules/AI Modules/ERP Modules/MES Modules/SAO; do
    echo "== $repo: $(git -C "$repo" status --porcelain --ignore-submodules=all | wc -l) changed files"
done
```

Expected: `0 changed files` for every repository. If any repository has changes, stop and ask the user; do not stash or commit their work.

- [ ] **Step 2: Regenerate**

```bash
cd /srv/http/laraplate-stack/laraplate
APP=$(pwd)
for repo in Modules/Core Modules/CMS Modules/AI Modules/ERP Modules/MES Modules/SAO .; do
    (cd "$repo" && git cliff --config "$APP/cliff.toml" --output CHANGELOG.md) || echo "FAILED: $repo"
done
```

Expected: no `FAILED` line.

- [ ] **Step 3: Sanity-check the diffs**

```bash
cd /srv/http/laraplate-stack/laraplate
for repo in . Modules/Core Modules/CMS Modules/AI Modules/ERP Modules/MES Modules/SAO; do
    echo "== $repo $(git -C "$repo" diff --shortstat -- CHANGELOG.md)"
    grep -m 3 '^## \[' "$repo/CHANGELOG.md"
done
```

Expected: every repository changed only `CHANGELOG.md`; the first heading is `## [unreleased]` (every repository has commits after its last tag) followed by the latest tag's version, for example `## [1.14.0]` in the application.

- [ ] **Step 4: Commit each module**

```bash
cd /srv/http/laraplate-stack/laraplate
for module in Core CMS AI ERP MES SAO; do
    git -C "Modules/$module" add CHANGELOG.md
    git -C "Modules/$module" commit -m "docs(changelog): regenerate with the corrected git-cliff configuration" -m "Single-line commits were dropped by an empty-body skip rule and the latest release sat under [unreleased]." -m "Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
done
```

- [ ] **Step 5: Commit the application changelog, then the module pointers**

Before staging pointers, review what each pointer will record:

```bash
cd /srv/http/laraplate-stack/laraplate
git diff --submodule=log -- Modules
```

Expected: each module lists its new `docs(changelog)` commit, plus any earlier module commits not yet recorded in the application. Recording them is intended; if the log shows anything surprising, stop and ask the user.

```bash
cd /srv/http/laraplate-stack/laraplate
git add CHANGELOG.md
git commit -m "docs(changelog): regenerate with the corrected git-cliff configuration" -m "Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
git add Modules/Core Modules/CMS Modules/AI Modules/ERP Modules/MES Modules/SAO
git commit -m "chore(modules): record the regenerated module changelogs" -m "Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 3: Test harness pinning today's correct behaviour

**Files:**
- Modify: `scripts/version.sh` (the `ROOT_DIR=` assignment)
- Create: `scripts/tests/version-test.sh`

**Interfaces:**
- Consumes: `cliff.toml` (copied into every fixture).
- Produces, used by every later task:
  - `make_repo <dir> <version>`: bare origin `<dir>.origin.git`, clone at `<dir>` on `master` with upstream, `composer.json` at `<version>`, tag `<version>` unless `v0.0.0`.
  - `make_app <dir> [Module...]`: application fixture with the real `cliff.toml`, tag `v1.0.0`, bare origin `<dir>.origin.git`, and one submodule per name at `Modules/<Name>` (its own bare origin `<dir>.modules/<Name>.origin.git`, tag `v1.0.0`).
  - `commit <repo> <message>`: one new file, one commit.
  - `run_version <app> [args...]`: sets `OUTPUT` (stdout and stderr) and `STATUS`; never prompts.
  - `run_version_answering <app> <answers> [args...]`: same, with prompts read from `<answers>`.
  - `fail`, `assert_status <code>`, `assert_output_contains <text>`, `assert_output_lacks <text>`, `assert_tag <repo> <tag>`, `assert_no_tag <repo> <tag>`, `assert_file_contains <file> <text>`, `assert_file_lacks <file> <text>`, `assert_eq <actual> <expected> [label]`.
  - Each test is a function named `test_*`, run in its own subshell; a failed assertion ends only that test. Tests are appended above the last line `run_tests "${1:-}"`.

- [ ] **Step 1: Add the root seam to `scripts/version.sh`**

Replace

```bash
ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
```

with

```bash
ROOT_DIR="${VERSION_ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
```

- [ ] **Step 2: Create `scripts/tests/version-test.sh`**

```bash
#!/usr/bin/env bash
#
# Tests for scripts/version.sh against throwaway repositories.
# Usage: bash scripts/tests/version-test.sh [name-filter]

set -uo pipefail

SCRIPTS_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
APP_DIR=$(dirname "$SCRIPTS_DIR")
VERSION_SH="$SCRIPTS_DIR/version.sh"

WORK_DIR=$(mktemp -d)
trap 'rm -rf "$WORK_DIR"' EXIT

export GIT_CONFIG_GLOBAL="$WORK_DIR/gitconfig"
export GIT_CONFIG_NOSYSTEM=1
git config --global user.name "Version Test"
git config --global user.email "version-test@example.invalid"
git config --global init.defaultBranch master
git config --global protocol.file.allow always
git config --global advice.detachedHead false

OUTPUT=""
STATUS=0

# Creates a bare origin and a clone at $1 whose composer.json is at version $2 (tagged unless v0.0.0).
make_repo() {
    local dir=$1 version=$2
    mkdir -p "$(dirname "$dir")"
    git init --quiet --bare "$dir.origin.git"
    git clone --quiet "$dir.origin.git" "$dir" 2>/dev/null
    printf '{\n  "name": "test/%s",\n  "version": "%s"\n}\n' "${dir##*/}" "$version" > "$dir/composer.json"
    git -C "$dir" add composer.json
    git -C "$dir" commit --quiet -m "chore: initial commit"
    if [ "$version" != "v0.0.0" ]; then
        git -C "$dir" tag -a "$version" -m "Release $version"
    fi
    git -C "$dir" push --quiet -u origin master --tags 2>/dev/null
}

# Creates an application at $1 with the real cliff.toml and one submodule per remaining argument.
make_app() {
    local app=$1 module
    shift
    mkdir -p "$(dirname "$app")"
    git init --quiet --bare "$app.origin.git"
    git clone --quiet "$app.origin.git" "$app" 2>/dev/null
    printf '{\n  "name": "test/app",\n  "version": "v1.0.0"\n}\n' > "$app/composer.json"
    cp "$APP_DIR/cliff.toml" "$app/cliff.toml"
    for module in "$@"; do
        make_repo "$app.modules/$module" v1.0.0
        git -C "$app" submodule add --quiet -b master "$app.modules/$module.origin.git" "Modules/$module" >/dev/null 2>&1
    done
    git -C "$app" add -A
    git -C "$app" commit --quiet -m "chore: initial commit"
    git -C "$app" tag -a v1.0.0 -m "Release v1.0.0"
    git -C "$app" push --quiet -u origin master --tags 2>/dev/null
}

# Commits a new file in repository $1 with message $2.
commit() {
    local file="$1/change-$RANDOM$RANDOM.txt"
    printf '%s\n' "$2" > "$file"
    git -C "$1" add "$file"
    git -C "$1" commit --quiet -m "$2"
}

# Runs version.sh for application $1 without prompts; sets OUTPUT and STATUS.
run_version() {
    local app=$1
    shift
    OUTPUT=$(VERSION_ROOT_DIR="$app" VERSION_FORCE_INTERACTIVE=0 bash "$VERSION_SH" "$@" 2>&1 </dev/null)
    STATUS=$?
}

# Runs version.sh for application $1 answering prompts with the lines of $2; sets OUTPUT and STATUS.
run_version_answering() {
    local app=$1 answers=$2
    shift 2
    OUTPUT=$(VERSION_ROOT_DIR="$app" VERSION_FORCE_INTERACTIVE=1 bash "$VERSION_SH" "$@" 2>&1 <<< "$answers")
    STATUS=$?
}

fail() {
    printf '    %s\n' "$@" >&2
    printf '%s\n' "$OUTPUT" | sed 's/^/      | /' >&2
    exit 1
}

assert_status() {
    [ "$STATUS" -eq "$1" ] || fail "expected exit $1, got $STATUS"
}

assert_output_contains() {
    [[ "$OUTPUT" == *"$1"* ]] || fail "output does not contain: $1"
}

assert_output_lacks() {
    [[ "$OUTPUT" != *"$1"* ]] || fail "output unexpectedly contains: $1"
}

assert_tag() {
    git -C "$1" rev-parse -q --verify "refs/tags/$2" >/dev/null || fail "$1: missing tag $2"
}

assert_no_tag() {
    ! git -C "$1" rev-parse -q --verify "refs/tags/$2" >/dev/null || fail "$1: unexpected tag $2"
}

assert_file_contains() {
    grep -qF -- "$2" "$1" || fail "$1 does not contain: $2"
}

assert_file_lacks() {
    ! grep -qF -- "$2" "$1" || fail "$1 unexpectedly contains: $2"
}

assert_eq() {
    [ "$1" = "$2" ] || fail "${3:-value}: expected '$2', got '$1'"
}

# --- tests -------------------------------------------------------------------------------------

test_named_module_dry_run_does_not_tag() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core
    commit "$app/Modules/Core" "fix: repair the core"
    run_version "$app" Core patch --dry-run
    assert_status 0
    assert_output_contains "v1.0.1"
    assert_no_tag "$app/Modules/Core" v1.0.1
}

test_release_gives_the_new_version_its_own_changelog_section() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "feat: add a feature"
    run_version "$app" minor
    assert_status 0
    assert_tag "$app" v1.1.0
    assert_file_contains "$app/CHANGELOG.md" "## [1.1.0]"
    assert_file_lacks "$app/CHANGELOG.md" "## [unreleased]"
    assert_file_contains "$app/CHANGELOG.md" "Add a feature"
    assert_eq "$(jq -r .version "$app/composer.json")" v1.1.0 "composer.json version"
}

# --- runner ------------------------------------------------------------------------------------

run_tests() {
    local filter=$1 name passed=0 failed=0
    while IFS= read -r name; do
        [[ "$name" == *"$filter"* ]] || continue
        if ( cd "$WORK_DIR" && "$name" ); then
            passed=$((passed + 1))
            printf 'ok   %s\n' "$name"
        else
            failed=$((failed + 1))
            printf 'FAIL %s\n' "$name"
        fi
    done < <(declare -F | awk '{ print $3 }' | grep '^test_')
    printf '\n%d passed, %d failed\n' "$passed" "$failed"
    [ "$failed" -eq 0 ]
}

run_tests "${1:-}"
```

- [ ] **Step 3: Run the harness**

Run: `chmod +x scripts/tests/version-test.sh && bash scripts/tests/version-test.sh`

Expected:

```
ok   test_named_module_dry_run_does_not_tag
ok   test_release_gives_the_new_version_its_own_changelog_section

2 passed, 0 failed
```

If `test_release_gives_the_new_version_its_own_changelog_section` fails on `## [unreleased]` or on `Add a feature`, Task 1 was not applied.

- [ ] **Step 4: Prove the harness can fail**

Temporarily change `assert_tag "$app" v1.1.0` to `assert_tag "$app" v9.9.9`, run `bash scripts/tests/version-test.sh changelog_section`, expect `FAIL test_release_gives_the_new_version_its_own_changelog_section` and exit status 1, then revert the change and rerun to `1 passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
cd /srv/http/laraplate-stack/laraplate
git add scripts/version.sh scripts/tests/version-test.sh
git commit -m "test(release): add a harness that drives version.sh against throwaway repositories" -m "Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 4: Engine rewrite: arguments, plan, non-interactive release

Replaces `scripts/version.sh` wholesale. Fixes #2, #3, #7, #8, #9 and #11, removes `--silent`, adds `--set-version`, `--no-push` and the exit codes. `--all`, prompts and the changelog modes are parsed here but implemented in Tasks 5 to 7.

**Files:**
- Replace: `scripts/version.sh`
- Modify: `scripts/tests/version-test.sh` (append tests)
- Modify: `composer.json` (remove `version:silent`)

**Interfaces:**
- Consumes: harness from Task 3.
- Produces (global state and functions later tasks call or replace):
  - Globals: `ROOT_DIR`, `CLIFF_CONFIG`, `TARGETS` (array of absolute repository paths), `BUMP` (`""|major|minor|patch`), `SET_VERSION` (`""|vX.Y.Z`), `ALL`, `NONINTERACTIVE`, `DRY_RUN`, `PUSH`, `ALLOW_DIRTY` (`true|false`), `MODE` (`release|changelog|changelog-check`); maps `PLAN_CURRENT`, `PLAN_NEXT`, `PLAN_COMMITS`, `PLAN_VERDICT` keyed by repository path, verdict one of `release|nothing|tagged|skipped`.
  - `die <code> <message...>`; `module_paths` prints module paths, Core first; `display_name <path>` prints `application` or the module name; `normalize_version <v>` prints `vX.Y.Z` or returns 1; `version_gt <a> <b>`; `increment_version <vX.Y.Z> <level>`; `current_version <path>`; `plan_target <path>`; `render_plan <path...>`; `check_preconditions <path>`; `regenerate_changelog <path> <output> [tag]`; `release_target <path> <version>`; `run_release`; `main`.

- [ ] **Step 1: Append the failing tests**

Insert above the line `# --- runner ---...` in `scripts/tests/version-test.sh`:

```bash
test_bump_keyword_before_target_versions_the_module() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core
    commit "$app/Modules/Core" "fix: repair the core"
    run_version "$app" patch Core --dry-run
    assert_status 0
    assert_output_contains "Core"
    assert_output_contains "v1.0.1"
    assert_output_lacks "application"
}

test_unknown_target_is_a_usage_error() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core
    run_version "$app" Nope --dry-run
    assert_status 2
    assert_output_contains "Valid targets: Core"
}

test_all_with_explicit_target_is_a_usage_error() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core
    run_version "$app" --all Core
    assert_status 2
}

test_unknown_option_is_a_usage_error() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    run_version "$app" --silent
    assert_status 2
}

test_nothing_to_release_exits_10() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    run_version "$app" --nointeractive
    assert_status 10
    assert_no_tag "$app" v1.0.1
}

test_skipped_commits_alone_are_nothing_to_release() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "chore(release): v1.0.1"
    run_version "$app" --nointeractive
    assert_status 10
}

test_backup_tags_are_ignored() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "fix: repair something"
    git -C "$app" tag backup/v9.9.9
    run_version "$app" --nointeractive --dry-run
    assert_status 0
    assert_output_contains "v1.0.1"
    assert_output_lacks "9.9."
}

test_unprefixed_tags_are_recognised() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "fix: first"
    git -C "$app" tag -a 1.4.0 -m "Release 1.4.0"
    commit "$app" "fix: second"
    run_version "$app" --nointeractive --dry-run
    assert_status 0
    assert_output_contains "v1.4.1"
}

test_inferred_bump_follows_breaking_changes() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "feat(api)!: drop the old endpoint"
    run_version "$app" --nointeractive
    assert_status 0
    assert_tag "$app" v2.0.0
}

test_release_does_not_amend_the_last_commit() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "feat: add a feature"
    local before
    before=$(git -C "$app" rev-parse HEAD)
    run_version "$app" --nointeractive
    assert_status 0
    assert_eq "$(git -C "$app" rev-parse HEAD~1)" "$before" "parent of the release commit"
    assert_eq "$(git -C "$app" log -1 --pretty=%s)" "chore(release): v1.1.0" "release commit subject"
}

test_release_pushes_commit_and_tag() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "fix: repair something"
    run_version "$app" --nointeractive
    assert_status 0
    assert_tag "$app.origin.git" v1.0.1
    assert_eq "$(git -C "$app.origin.git" rev-parse master)" "$(git -C "$app" rev-parse HEAD)" "origin master"
}

test_no_push_keeps_the_release_local() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "fix: repair something"
    run_version "$app" --nointeractive --no-push
    assert_status 0
    assert_tag "$app" v1.0.1
    assert_no_tag "$app.origin.git" v1.0.1
}

test_dirty_tree_is_a_precondition_error() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "fix: repair something"
    printf 'work in progress\n' > "$app/untracked.txt"
    run_version "$app" --nointeractive
    assert_status 3
    assert_no_tag "$app" v1.0.1
}

test_detached_head_is_a_precondition_error() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "fix: repair something"
    git -C "$app" checkout --quiet --detach
    run_version "$app" --nointeractive
    assert_status 3
    assert_output_contains "detached"
}

test_missing_upstream_requires_no_push() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "fix: repair something"
    git -C "$app" branch --quiet --unset-upstream
    run_version "$app" --nointeractive
    assert_status 3
    run_version "$app" --nointeractive --no-push
    assert_status 0
    assert_tag "$app" v1.0.1
}

test_several_targets_release_each() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core CMS
    commit "$app/Modules/Core" "fix: repair the core"
    commit "$app/Modules/CMS" "feat: add a content feature"
    run_version "$app" core Modules/CMS --nointeractive
    assert_status 0
    assert_tag "$app/Modules/Core" v1.0.1
    assert_tag "$app/Modules/CMS" v1.1.0
    assert_no_tag "$app" v1.0.1
}

test_set_version_releases_that_version() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "fix: repair something"
    run_version "$app" --nointeractive --set-version 2.5.0
    assert_status 0
    assert_tag "$app" v2.5.0
}

test_set_version_must_be_greater_than_current() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "fix: repair something"
    run_version "$app" --nointeractive --set-version v0.9.0
    assert_status 2
}

test_set_version_with_several_targets_is_a_usage_error() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core CMS
    run_version "$app" Core CMS --set-version 2.0.0
    assert_status 2
}
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `bash scripts/tests/version-test.sh`

Expected: `FAIL` for at least `test_bump_keyword_before_target_versions_the_module`, `test_unknown_target_is_a_usage_error`, `test_all_with_explicit_target_is_a_usage_error`, `test_unknown_option_is_a_usage_error`, `test_nothing_to_release_exits_10`, `test_skipped_commits_alone_are_nothing_to_release`, `test_backup_tags_are_ignored`, `test_release_does_not_amend_the_last_commit`, `test_no_push_keeps_the_release_local`, `test_dirty_tree_is_a_precondition_error`, `test_detached_head_is_a_precondition_error`, `test_missing_upstream_requires_no_push`, `test_several_targets_release_each`, `test_set_version_releases_that_version`, `test_set_version_must_be_greater_than_current`, `test_set_version_with_several_targets_is_a_usage_error`; the two Task 3 tests still `ok`.

- [ ] **Step 3: Replace `scripts/version.sh`**

```bash
#!/usr/bin/env bash
#
# Versions the application and its modules: for each target, bump composer.json, regenerate
# CHANGELOG.md with git-cliff, commit, tag and push. Run with --help for usage.

set -uo pipefail

export LC_COLLATE=C

readonly EXIT_OK=0
readonly EXIT_FAILURE=1
readonly EXIT_USAGE=2
readonly EXIT_PRECONDITION=3
readonly EXIT_CHANGELOG_DIVERGED=4
readonly EXIT_NOTHING_TO_RELEASE=10

readonly VERSION_TAG_PATTERN='^v?[0-9]+\.[0-9]+\.[0-9]+$'

ROOT_DIR="${VERSION_ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
readonly ROOT_DIR
readonly CLIFF_CONFIG="$ROOT_DIR/cliff.toml"

TARGETS=()
BUMP=""
SET_VERSION=""
ALL=false
NONINTERACTIVE=false
DRY_RUN=false
PUSH=true
ALLOW_DIRTY=false
MODE="release"

declare -A PLAN_CURRENT=() PLAN_NEXT=() PLAN_COMMITS=() PLAN_VERDICT=()

die() {
    local code=$1
    shift
    printf 'Error: %s\n' "$*" >&2
    exit "$code"
}

usage() {
    cat <<'USAGE'
Usage: version.sh [target ...] [major|minor|patch] [options]

Targets:
  (none)               the application
  <Module>             a module under Modules/, case-insensitive (Core, cms, ...)
  Modules/<Module>     the same module, by path
  --all                every module with pending commits, then the application

Bump:
  major|minor|patch    force the level; without it git-cliff infers the level

Options:
  --set-version <v>    explicit version, single target only
  --nointeractive      never prompt; skip targets with nothing to release
  -n, --dry-run        print the plan and write nothing
  --no-push            commit and tag locally, do not push
  --allow-dirty        proceed with a dirty working tree
  --changelog          regenerate CHANGELOG.md for the targets and stop
  --changelog-check    verify every CHANGELOG.md against a fresh regeneration
  -h, --help           show this help

Exit codes: 0 done, 10 nothing to release, 2 usage, 3 precondition, 4 changelog diverged, 1 failure.
USAGE
}

# Prints the absolute path of every module repository under Modules/, Core first, then by name.
module_paths() {
    local dir
    if [ -e "$ROOT_DIR/Modules/Core/.git" ]; then
        printf '%s\n' "$ROOT_DIR/Modules/Core"
    fi
    for dir in "$ROOT_DIR"/Modules/*/; do
        dir=${dir%/}
        if [ "${dir##*/}" != "Core" ] && [ -e "$dir/.git" ]; then
            printf '%s\n' "$dir"
        fi
    done
}

# Prints "application" for the application root, the module name otherwise.
display_name() {
    if [ "$1" = "$ROOT_DIR" ]; then
        printf 'application\n'
    else
        printf '%s\n' "${1##*/}"
    fi
}

# Appends the module named by $1 (Core, cms, Modules/Core) to TARGETS once, or exits with usage.
add_target() {
    local wanted=${1#Modules/}
    wanted=${wanted%/}
    wanted=${wanted,,}
    local path name existing paths=() names=()
    mapfile -t paths < <(module_paths)
    for path in "${paths[@]}"; do
        name=${path##*/}
        names+=("$name")
        if [ "${name,,}" = "$wanted" ]; then
            for existing in "${TARGETS[@]}"; do
                if [ "$existing" = "$path" ]; then
                    return 0
                fi
            done
            TARGETS+=("$path")
            return 0
        fi
    done
    die "$EXIT_USAGE" "unknown target '$1'. Valid targets: ${names[*]:-none}"
}

# Prints $1 as vX.Y.Z, or returns 1 when it is not a plain semantic version.
normalize_version() {
    if [[ ! "$1" =~ $VERSION_TAG_PATTERN ]]; then
        printf 'Error: not a version: %s\n' "$1" >&2
        return 1
    fi
    printf 'v%s\n' "${1#v}"
}

# True when version $1 is strictly greater than version $2.
version_gt() {
    [ "${1#v}" != "${2#v}" ] && [ "$(printf '%s\n%s\n' "${1#v}" "${2#v}" | sort -V | tail -n 1)" = "${1#v}" ]
}

# Prints version $1 incremented at level $2 (major, minor or patch).
increment_version() {
    local major minor patch
    IFS=. read -r major minor patch <<< "${1#v}"
    case "$2" in
        major) printf 'v%d.0.0\n' "$((major + 1))" ;;
        minor) printf 'v%d.%d.0\n' "$major" "$((minor + 1))" ;;
        patch) printf 'v%d.%d.%d\n' "$major" "$minor" "$((patch + 1))" ;;
    esac
}

parse_args() {
    local value
    while [ "$#" -gt 0 ]; do
        case "$1" in
            major|minor|patch)
                if [ -n "$BUMP" ] && [ "$BUMP" != "$1" ]; then
                    die "$EXIT_USAGE" "conflicting bump levels: $BUMP and $1"
                fi
                BUMP=$1
                ;;
            --set-version|--set-version=*)
                if [ "$1" = "--set-version" ]; then
                    [ "$#" -ge 2 ] || die "$EXIT_USAGE" "--set-version needs a value"
                    value=$2
                    shift
                else
                    value=${1#*=}
                fi
                SET_VERSION=$(normalize_version "$value") || exit "$EXIT_USAGE"
                ;;
            --all) ALL=true ;;
            --nointeractive) NONINTERACTIVE=true ;;
            -n|--dry-run) DRY_RUN=true ;;
            --no-push) PUSH=false ;;
            --allow-dirty) ALLOW_DIRTY=true ;;
            --changelog) MODE="changelog" ;;
            --changelog-check) MODE="changelog-check" ;;
            -h|--help)
                usage
                exit "$EXIT_OK"
                ;;
            -*) die "$EXIT_USAGE" "unknown option '$1' (see --help)" ;;
            *) add_target "$1" ;;
        esac
        shift
    done

    if [ "$ALL" = true ] && [ "${#TARGETS[@]}" -gt 0 ]; then
        die "$EXIT_USAGE" "--all cannot be combined with explicit targets"
    fi
    if [ -n "$SET_VERSION" ] && [ -n "$BUMP" ]; then
        die "$EXIT_USAGE" "--set-version cannot be combined with $BUMP"
    fi
    if [ -n "$SET_VERSION" ] && { [ "$ALL" = true ] || [ "${#TARGETS[@]}" -gt 1 ]; }; then
        die "$EXIT_USAGE" "--set-version applies to a single target"
    fi
}

require_tools() {
    local tool missing=()
    for tool in git git-cliff jq; do
        command -v "$tool" >/dev/null 2>&1 || missing+=("$tool")
    done
    if [ "${#missing[@]}" -gt 0 ]; then
        die "$EXIT_PRECONDITION" "missing required tools: ${missing[*]}"
    fi
}

# Prints the name of the highest version tag of repository $1, or nothing when there is none.
latest_tag() {
    git -C "$1" tag --list \
        | grep -E "$VERSION_TAG_PATTERN" \
        | awk '{ v = $0; sub(/^v/, "", v); print v "\t" $0 }' \
        | sort -t$'\t' -k1,1V \
        | tail -n 1 \
        | cut -f2
}

current_version() {
    local tag
    tag=$(latest_tag "$1")
    if [ -z "$tag" ]; then
        printf 'v0.0.0\n'
    else
        printf 'v%s\n' "${tag#v}"
    fi
}

commits_since() {
    local tag
    tag=$(latest_tag "$1")
    if [ -z "$tag" ]; then
        git -C "$1" rev-list --count HEAD
    else
        git -C "$1" rev-list --count "$tag..HEAD"
    fi
}

# Prints the version git-cliff infers for repository $1 from the commits after its last tag.
inferred_version() {
    local inferred
    inferred=$(cd "$1" && git cliff --config "$CLIFF_CONFIG" --bumped-version 2>/dev/null) || return 1
    normalize_version "$inferred"
}

head_has_version_tag() {
    git -C "$1" tag --points-at HEAD | grep -Eq "$VERSION_TAG_PATTERN"
}

# Fills the PLAN_* maps for repository $1. Reads only.
plan_target() {
    local path=$1 current inferred
    current=$(current_version "$path")
    inferred=$(inferred_version "$path") || die "$EXIT_FAILURE" "$(display_name "$path"): git cliff could not infer a version"
    PLAN_CURRENT[$path]=$current
    PLAN_COMMITS[$path]=$(commits_since "$path")
    PLAN_NEXT[$path]="-"

    if head_has_version_tag "$path"; then
        PLAN_VERDICT[$path]="tagged"
    elif [ "$inferred" = "$current" ]; then
        PLAN_VERDICT[$path]="nothing"
    else
        PLAN_VERDICT[$path]="release"
        if [ -n "$SET_VERSION" ]; then
            version_gt "$SET_VERSION" "$current" || die "$EXIT_USAGE" "$SET_VERSION is not greater than $current"
            PLAN_NEXT[$path]=$SET_VERSION
        elif [ -n "$BUMP" ]; then
            PLAN_NEXT[$path]=$(increment_version "$current" "$BUMP")
        else
            PLAN_NEXT[$path]=$inferred
        fi
    fi
}

render_plan() {
    local path next
    printf '%-12s %-10s %-10s %7s  %s\n' TARGET CURRENT NEXT COMMITS ACTION
    for path in "$@"; do
        next=${PLAN_NEXT[$path]}
        if [ "${PLAN_VERDICT[$path]}" != "release" ]; then
            next="-"
        fi
        printf '%-12s %-10s %-10s %7s  %s\n' "$(display_name "$path")" "${PLAN_CURRENT[$path]}" "$next" "${PLAN_COMMITS[$path]}" "${PLAN_VERDICT[$path]}"
    done
}

# Exits with a precondition error when repository $1 cannot be released safely.
check_preconditions() {
    local path=$1 name branch
    name=$(display_name "$path")
    branch=$(git -C "$path" symbolic-ref --short -q HEAD) || die "$EXIT_PRECONDITION" "$name: HEAD is detached, check out a branch first"
    if [ "$ALLOW_DIRTY" != true ] && [ -n "$(git -C "$path" status --porcelain --ignore-submodules=all)" ]; then
        die "$EXIT_PRECONDITION" "$name: working tree has uncommitted changes (commit or stash them, or use --allow-dirty)"
    fi
    if [ "$PUSH" = true ] && [ -z "$(git -C "$path" config "branch.$branch.remote")" ]; then
        die "$EXIT_PRECONDITION" "$name: branch $branch has no upstream (set one, or use --no-push)"
    fi
    if git -C "$path" rev-parse -q --verify "refs/tags/${PLAN_NEXT[$path]}" >/dev/null; then
        die "$EXIT_PRECONDITION" "$name: tag ${PLAN_NEXT[$path]} already exists"
    fi
}

update_composer_version() {
    local file="$1/composer.json" tmp
    [ -f "$file" ] || return 1
    tmp=$(mktemp) || return 1
    if jq --arg version "$2" '.version = $version' "$file" > "$tmp"; then
        mv "$tmp" "$file"
    else
        rm -f "$tmp"
        return 1
    fi
}

# Writes the changelog of repository $1 to $2. With $3, unreleased commits are released as $3.
regenerate_changelog() {
    local path=$1 output=$2 tag=${3:-} log
    local args=(--config "$CLIFF_CONFIG" --output "$output")
    if [ -n "$tag" ]; then
        args+=(--tag "$tag")
    fi
    log=$(mktemp) || return 1
    if (cd "$path" && git cliff "${args[@]}" 2>"$log"); then
        rm -f "$log"
        return 0
    fi
    cat "$log" >&2
    rm -f "$log"
    return 1
}

push_target() {
    local path=$1 branch=$2 version=$3 remote merge
    remote=$(git -C "$path" config "branch.$branch.remote")
    merge=$(git -C "$path" config "branch.$branch.merge")
    git -C "$path" push --quiet "$remote" "HEAD:${merge:-refs/heads/$branch}" \
        && git -C "$path" push --quiet "$remote" "refs/tags/$version"
}

# Reports what a failed release of repository $1 left behind, then exits.
fail_release() {
    local path=$1 version=$2 step=$3
    {
        printf 'Error: %s: release %s failed at: %s\n' "$(display_name "$path")" "$version" "$step"
        printf 'Inspect before retrying: git -C %q status\n' "$path"
        if git -C "$path" rev-parse -q --verify "refs/tags/$version" >/dev/null; then
            printf 'Local tag %s exists. Remove it with: git -C %q tag -d %s\n' "$version" "$path" "$version"
        fi
        if [ "$(git -C "$path" log -1 --pretty=%s)" = "chore(release): $version" ]; then
            printf 'Release commit exists. Undo it with: git -C %q reset --keep HEAD~1\n' "$path"
        else
            printf 'Staged changes may exist. Undo them with: git -C %q restore --staged --worktree composer.json CHANGELOG.md\n' "$path"
        fi
        if [ "$step" = "push" ]; then
            printf 'Or keep the release and push by hand: git -C %q push <remote> HEAD:<branch> refs/tags/%s\n' "$path" "$version"
        fi
    } >&2
    exit "$EXIT_FAILURE"
}

release_target() {
    local path=$1 version=$2 branch
    branch=$(git -C "$path" symbolic-ref --short HEAD)
    printf 'Releasing %s %s\n' "$(display_name "$path")" "$version"

    update_composer_version "$path" "$version" || fail_release "$path" "$version" "composer.json"
    regenerate_changelog "$path" "$path/CHANGELOG.md" "$version" || fail_release "$path" "$version" "changelog"
    git -C "$path" add composer.json CHANGELOG.md || fail_release "$path" "$version" "stage"
    git -C "$path" commit --quiet -m "chore(release): $version" || fail_release "$path" "$version" "commit"
    git -C "$path" tag -a "$version" -m "Release $version" || fail_release "$path" "$version" "tag"
    if [ "$PUSH" = true ]; then
        push_target "$path" "$branch" "$version" || fail_release "$path" "$version" "push"
    fi
}

run_release() {
    local path pending=()

    if [ "$ALL" = true ]; then
        die "$EXIT_USAGE" "--all is not implemented yet"
    fi
    if [ "${#TARGETS[@]}" -eq 0 ]; then
        TARGETS=("$ROOT_DIR")
    fi

    for path in "${TARGETS[@]}"; do
        plan_target "$path"
        if [ "${PLAN_VERDICT[$path]}" = "release" ]; then
            pending+=("$path")
        fi
    done

    render_plan "${TARGETS[@]}"

    if [ "${#pending[@]}" -eq 0 ]; then
        printf 'Nothing to release.\n'
        exit "$EXIT_NOTHING_TO_RELEASE"
    fi
    if [ "$DRY_RUN" = true ]; then
        exit "$EXIT_OK"
    fi

    for path in "${pending[@]}"; do
        check_preconditions "$path"
    done
    for path in "${pending[@]}"; do
        release_target "$path" "${PLAN_NEXT[$path]}"
    done
    exit "$EXIT_OK"
}

main() {
    parse_args "$@"
    require_tools
    case "$MODE" in
        release) run_release ;;
        *) die "$EXIT_USAGE" "--$MODE is not implemented yet" ;;
    esac
}

main "$@"
```

- [ ] **Step 4: Run the tests to see them pass**

Run: `bash scripts/tests/version-test.sh`

Expected: every test `ok`, final line `21 passed, 0 failed`.

- [ ] **Step 5: Smoke-test against the real repositories without writing**

Run:

```bash
cd /srv/http/laraplate-stack/laraplate
./scripts/version.sh --dry-run; echo "exit=$?"
./scripts/version.sh minor Core --dry-run; echo "exit=$?"
./scripts/version.sh Nope; echo "exit=$?"
git status --porcelain --ignore-submodules=all
```

Expected: a plan table for `application`, then a table whose only row is `Core` with `NEXT` one minor above its current version, then `Error: unknown target 'Nope'. Valid targets: Core AI CMS ERP MES SAO` with `exit=2`, and no changed files.

- [ ] **Step 6: Remove `version:silent` from `composer.json`**

```bash
cd /srv/http/laraplate-stack/laraplate
tmp=$(mktemp) && jq 'del(.scripts["version:silent"])' composer.json > "$tmp" && mv "$tmp" composer.json
git diff --stat composer.json
```

Expected: `composer.json | 1 -`.

- [ ] **Step 7: Commit**

```bash
cd /srv/http/laraplate-stack/laraplate
git add scripts/version.sh scripts/tests/version-test.sh composer.json
git commit -m "fix(release): rewrite version.sh around a plan and a plain release commit" -m "A bump keyword before the target versioned the application, the release was amended into the last commit, the latest tag was chosen by date and could be a backup tag, push errors were discarded and unknown flags were ignored. Targets and keywords are now classified in any order, git-cliff infers the version, and nothing to release exits 10." -m "Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 5: Interactive flow

**Files:**
- Modify: `scripts/version.sh` (add `is_interactive`, `prompt`, `choose_version`, `confirm`; replace `run_release`)
- Modify: `scripts/tests/version-test.sh` (append tests)

**Interfaces:**
- Consumes: everything produced by Task 4.
- Produces: `is_interactive`; `prompt <question>` prints the answer line; `choose_version <path>` updates `PLAN_NEXT`/`PLAN_VERDICT`; `confirm`. `run_release` is replaced again in Task 6.

- [ ] **Step 1: Append the failing tests**

Insert above `# --- runner ---...`:

```bash
test_interactive_skip_leaves_a_target_untouched() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core CMS
    commit "$app/Modules/Core" "fix: repair the core"
    commit "$app/Modules/CMS" "fix: repair the content"
    run_version_answering "$app" $'skip\n\ny' Core CMS
    assert_status 0
    assert_no_tag "$app/Modules/Core" v1.0.1
    assert_tag "$app/Modules/CMS" v1.0.1
}

test_interactive_level_choice_overrides_inference() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "fix: repair something"
    run_version_answering "$app" $'minor\ny'
    assert_status 0
    assert_tag "$app" v1.1.0
}

test_interactive_explicit_version_is_accepted() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "fix: repair something"
    run_version_answering "$app" $'3.0.0\ny'
    assert_status 0
    assert_tag "$app" v3.0.0
}

test_interactive_decline_writes_nothing() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "fix: repair something"
    local before
    before=$(git -C "$app" rev-parse HEAD)
    run_version_answering "$app" $'\nn'
    assert_status 1
    assert_no_tag "$app" v1.0.1
    assert_eq "$(git -C "$app" rev-parse HEAD)" "$before" "HEAD"
    assert_output_contains "Aborted"
}

test_forced_level_asks_only_for_confirmation() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "fix: repair something"
    run_version_answering "$app" 'y' patch
    assert_status 0
    assert_tag "$app" v1.0.1
    assert_output_lacks "[major/minor/patch"
}

test_dry_run_never_prompts() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app"
    commit "$app" "fix: repair something"
    run_version_answering "$app" '' --dry-run
    assert_status 0
    assert_output_contains "TARGET"
    assert_output_lacks "Proceed?"
    assert_no_tag "$app" v1.0.1
}
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `bash scripts/tests/version-test.sh`

Expected: exactly four failures, `test_interactive_decline_writes_nothing`, `test_interactive_explicit_version_is_accepted`, `test_interactive_level_choice_overrides_inference` and `test_interactive_skip_leaves_a_target_untouched`, with `23 passed, 4 failed`. `test_forced_level_asks_only_for_confirmation` and `test_dry_run_never_prompts` already pass: they pin behaviour the prompts must not break.

- [ ] **Step 3: Add the prompt functions**

Insert directly above `run_release()` in `scripts/version.sh`:

```bash
# True when prompts can be shown. VERSION_FORCE_INTERACTIVE=1 or 0 overrides terminal detection.
is_interactive() {
    if [ "$NONINTERACTIVE" = true ]; then
        return 1
    fi
    case "${VERSION_FORCE_INTERACTIVE:-}" in
        1) return 0 ;;
        0) return 1 ;;
    esac
    { : </dev/tty; } 2>/dev/null
}

# Asks $1 and prints the answer. Uses /dev/tty, which works under composer run, or stdin when forced.
prompt() {
    local answer=""
    if [ "${VERSION_FORCE_INTERACTIVE:-}" = 1 ]; then
        printf '%s' "$1" >&2
        IFS= read -r answer || answer=""
    else
        printf '%s' "$1" >/dev/tty
        IFS= read -r answer </dev/tty || answer=""
    fi
    printf '%s\n' "$answer"
}

# Asks which version repository $1 gets. Enter keeps the planned version.
choose_version() {
    local path=$1 answer version name
    name=$(display_name "$path")
    while true; do
        answer=$(prompt "$name ${PLAN_CURRENT[$path]} -> ${PLAN_NEXT[$path]} [major/minor/patch/<version>/skip, enter keeps ${PLAN_NEXT[$path]}]: ")
        case "$answer" in
            "")
                return 0
                ;;
            major|minor|patch)
                PLAN_NEXT[$path]=$(increment_version "${PLAN_CURRENT[$path]}" "$answer")
                return 0
                ;;
            skip)
                PLAN_VERDICT[$path]="skipped"
                return 0
                ;;
            *)
                if version=$(normalize_version "$answer" 2>/dev/null) && version_gt "$version" "${PLAN_CURRENT[$path]}"; then
                    PLAN_NEXT[$path]=$version
                    return 0
                fi
                printf 'Not a valid choice: %s\n' "$answer" >&2
                ;;
        esac
    done
}

confirm() {
    local answer
    answer=$(prompt "Proceed? [y/N]: ")
    [[ "$answer" =~ ^[Yy]$ ]]
}
```

- [ ] **Step 4: Replace `run_release`**

```bash
run_release() {
    local path pending=()

    if [ "$ALL" = true ]; then
        die "$EXIT_USAGE" "--all is not implemented yet"
    fi
    if [ "${#TARGETS[@]}" -eq 0 ]; then
        TARGETS=("$ROOT_DIR")
    fi

    for path in "${TARGETS[@]}"; do
        plan_target "$path"
    done

    if [ "$DRY_RUN" != true ] && is_interactive && [ -z "$BUMP" ] && [ -z "$SET_VERSION" ]; then
        render_plan "${TARGETS[@]}"
        for path in "${TARGETS[@]}"; do
            if [ "${PLAN_VERDICT[$path]}" = "release" ]; then
                choose_version "$path"
            fi
        done
    fi

    for path in "${TARGETS[@]}"; do
        if [ "${PLAN_VERDICT[$path]}" = "release" ]; then
            pending+=("$path")
        fi
    done

    render_plan "${TARGETS[@]}"

    if [ "${#pending[@]}" -eq 0 ]; then
        printf 'Nothing to release.\n'
        exit "$EXIT_NOTHING_TO_RELEASE"
    fi
    if [ "$DRY_RUN" = true ]; then
        exit "$EXIT_OK"
    fi

    for path in "${pending[@]}"; do
        check_preconditions "$path"
    done
    if is_interactive && ! confirm; then
        printf 'Aborted, nothing was written.\n'
        exit "$EXIT_FAILURE"
    fi
    for path in "${pending[@]}"; do
        release_target "$path" "${PLAN_NEXT[$path]}"
    done
    exit "$EXIT_OK"
}
```

- [ ] **Step 5: Run the tests to see them pass**

Run: `bash scripts/tests/version-test.sh`

Expected: final line `27 passed, 0 failed`.

- [ ] **Step 6: Commit**

```bash
cd /srv/http/laraplate-stack/laraplate
git add scripts/version.sh scripts/tests/version-test.sh
git commit -m "feat(release): choose, skip or confirm each target interactively" -m "Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 6: `--all` with submodule pointer consolidation

**Files:**
- Modify: `scripts/version.sh` (add `plan_application_after_modules`, `consolidate_pointers`; replace `run_release`)
- Modify: `scripts/tests/version-test.sh` (append tests)

**Interfaces:**
- Consumes: Tasks 4 and 5.
- Produces: final `run_release`; `plan_application_after_modules <module-path...>`; `consolidate_pointers <module-path...>`.

- [ ] **Step 1: Append the failing tests**

Insert above `# --- runner ---...`:

```bash
test_all_releases_modules_then_the_application() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core CMS
    commit "$app/Modules/Core" "feat: add a core feature"
    run_version "$app" --all --nointeractive
    assert_status 0
    assert_tag "$app/Modules/Core" v1.1.0
    assert_tag "$app.modules/Core.origin.git" v1.1.0
    assert_no_tag "$app/Modules/CMS" v1.0.1
    assert_eq "$(git -C "$app" log -1 --pretty=%s HEAD~1)" "chore(modules): bump Core v1.1.0" "pointer commit"
    assert_eq "$(git -C "$app" log -1 --pretty=%s)" "chore(release): v1.0.1" "application release commit"
    assert_tag "$app" v1.0.1
    assert_eq "$(git -C "$app" ls-tree HEAD Modules/Core | awk '{ print $3 }')" "$(git -C "$app/Modules/Core" rev-parse HEAD)" "recorded Core pointer"
}

test_all_releases_core_first() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" AI Core
    commit "$app/Modules/AI" "fix: repair the assistant"
    commit "$app/Modules/Core" "fix: repair the core"
    run_version "$app" --all --nointeractive
    assert_status 0
    local core_line ai_line
    core_line=$(printf '%s\n' "$OUTPUT" | grep -n '^Releasing Core ' | cut -d: -f1)
    ai_line=$(printf '%s\n' "$OUTPUT" | grep -n '^Releasing AI ' | cut -d: -f1)
    [ -n "$core_line" ] && [ -n "$ai_line" ] && [ "$core_line" -lt "$ai_line" ] || fail "Core must be released before AI"
}

test_all_aborts_before_writing_when_a_module_is_dirty() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core CMS
    commit "$app/Modules/Core" "feat: add a core feature"
    commit "$app/Modules/CMS" "fix: repair the content"
    printf 'work in progress\n' > "$app/Modules/CMS/untracked.txt"
    local before
    before=$(git -C "$app" rev-parse HEAD)
    run_version "$app" --all --nointeractive
    assert_status 3
    assert_no_tag "$app/Modules/Core" v1.1.0
    assert_eq "$(git -C "$app" rev-parse HEAD)" "$before" "application HEAD"
}

test_all_with_nothing_pending_exits_10() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core
    run_version "$app" --all --nointeractive
    assert_status 10
}

test_explicit_module_does_not_touch_the_application() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core
    commit "$app/Modules/Core" "fix: repair the core"
    local before
    before=$(git -C "$app" rev-parse HEAD)
    run_version "$app" Core --nointeractive
    assert_status 0
    assert_tag "$app/Modules/Core" v1.0.1
    assert_eq "$(git -C "$app" rev-parse HEAD)" "$before" "application HEAD"
}
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `bash scripts/tests/version-test.sh all_`

Expected: `FAIL` for `test_all_releases_modules_then_the_application`, `test_all_releases_core_first`, `test_all_aborts_before_writing_when_a_module_is_dirty` (all exit 2 with `--all is not implemented yet`) and `test_all_with_nothing_pending_exits_10`.

- [ ] **Step 3: Add the orchestration functions**

Insert directly above `run_release()`:

```bash
# Plans the application under --all. Releasing any module adds a pointer commit to the
# application, so the application is then released too, at least as a patch.
plan_application_after_modules() {
    local path any=false
    for path in "$@"; do
        if [ "${PLAN_VERDICT[$path]}" = "release" ]; then
            any=true
        fi
    done
    plan_target "$ROOT_DIR"
    if [ "$any" = true ] && [ "${PLAN_VERDICT[$ROOT_DIR]}" != "release" ]; then
        PLAN_VERDICT[$ROOT_DIR]="release"
        PLAN_NEXT[$ROOT_DIR]=$(increment_version "${PLAN_CURRENT[$ROOT_DIR]}" "${BUMP:-patch}")
    fi
}

# Records the new HEAD of every released module in the application with one commit.
consolidate_pointers() {
    local path joined summary=()
    for path in "$@"; do
        git -C "$ROOT_DIR" add -- "${path#"$ROOT_DIR"/}" || return 1
        summary+=("${path##*/} ${PLAN_NEXT[$path]}")
    done
    printf -v joined '%s, ' "${summary[@]}"
    git -C "$ROOT_DIR" commit --quiet -m "chore(modules): bump ${joined%, }"
}
```

- [ ] **Step 4: Replace `run_release`**

```bash
run_release() {
    local path choosing=false pending=() modules=() released_modules=()

    if [ "$ALL" = true ]; then
        mapfile -t modules < <(module_paths)
        TARGETS=("${modules[@]}")
    elif [ "${#TARGETS[@]}" -eq 0 ]; then
        TARGETS=("$ROOT_DIR")
    fi

    for path in "${TARGETS[@]}"; do
        plan_target "$path"
    done

    if [ "$DRY_RUN" != true ] && is_interactive && [ -z "$BUMP" ] && [ -z "$SET_VERSION" ]; then
        choosing=true
        render_plan "${TARGETS[@]}"
        for path in "${TARGETS[@]}"; do
            if [ "${PLAN_VERDICT[$path]}" = "release" ]; then
                choose_version "$path"
            fi
        done
    fi

    if [ "$ALL" = true ]; then
        plan_application_after_modules "${modules[@]}"
        if [ "$choosing" = true ] && [ "${PLAN_VERDICT[$ROOT_DIR]}" = "release" ]; then
            choose_version "$ROOT_DIR"
        fi
        TARGETS+=("$ROOT_DIR")
    fi

    for path in "${TARGETS[@]}"; do
        if [ "${PLAN_VERDICT[$path]}" = "release" ]; then
            pending+=("$path")
        fi
    done

    render_plan "${TARGETS[@]}"

    if [ "${#pending[@]}" -eq 0 ]; then
        printf 'Nothing to release.\n'
        exit "$EXIT_NOTHING_TO_RELEASE"
    fi
    if [ "$DRY_RUN" = true ]; then
        exit "$EXIT_OK"
    fi

    for path in "${pending[@]}"; do
        check_preconditions "$path"
    done
    if is_interactive && ! confirm; then
        printf 'Aborted, nothing was written.\n'
        exit "$EXIT_FAILURE"
    fi

    for path in "${pending[@]}"; do
        if [ "$path" = "$ROOT_DIR" ] && [ "$ALL" = true ] && [ "${#released_modules[@]}" -gt 0 ]; then
            consolidate_pointers "${released_modules[@]}" \
                || die "$EXIT_FAILURE" "application: could not commit the module pointers; the modules are already released"
        fi
        release_target "$path" "${PLAN_NEXT[$path]}"
        if [ "$path" != "$ROOT_DIR" ]; then
            released_modules+=("$path")
        fi
    done
    exit "$EXIT_OK"
}
```

- [ ] **Step 5: Run the tests to see them pass**

Run: `bash scripts/tests/version-test.sh`

Expected: final line `32 passed, 0 failed`.

- [ ] **Step 6: Smoke-test against the real repositories without writing**

Run: `cd /srv/http/laraplate-stack/laraplate && ./scripts/version.sh --all --dry-run; echo "exit=$?"`

Expected: a table with rows `Core`, `AI`, `CMS`, `ERP`, `MES`, `SAO`, `application` in that order, and `exit=0` or `exit=10`. No file changes (`git status --porcelain --ignore-submodules=all` prints nothing).

- [ ] **Step 7: Commit**

```bash
cd /srv/http/laraplate-stack/laraplate
git add scripts/version.sh scripts/tests/version-test.sh
git commit -m "feat(release): release every pending module, then the application, with --all" -m "Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 7: `--changelog` and `--changelog-check`

**Files:**
- Modify: `scripts/version.sh` (add `run_changelog`, `released_sections`, `run_changelog_check`; replace `main`)
- Modify: `scripts/tests/version-test.sh` (append tests)

**Interfaces:**
- Consumes: `regenerate_changelog`, `module_paths`, `display_name`, `TARGETS`, `ALL`.
- Produces: final `main`.

- [ ] **Step 1: Append the failing tests**

Insert above `# --- runner ---...`:

```bash
test_changelog_regenerates_only_the_target() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core
    commit "$app/Modules/Core" "fix: repair parsing"
    run_version "$app" --changelog Core
    assert_status 0
    assert_file_contains "$app/Modules/Core/CHANGELOG.md" "## [unreleased]"
    assert_file_contains "$app/Modules/Core/CHANGELOG.md" "Repair parsing"
    [ ! -e "$app/CHANGELOG.md" ] || fail "the application changelog must not be written"
}

test_changelog_check_ignores_unreleased_work() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core
    run_version "$app" --changelog --all
    assert_status 0
    commit "$app" "fix: later work"
    run_version "$app" --changelog-check
    assert_status 0
}

test_changelog_check_detects_a_missing_release() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core
    run_version "$app" --changelog --all
    sed -i '/^## \[1\.0\.0\]/d' "$app/CHANGELOG.md"
    run_version "$app" --changelog-check
    assert_status 4
    assert_output_contains "application"
}
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `bash scripts/tests/version-test.sh changelog`

Expected: `FAIL` for the three new tests (exit 2, `--changelog is not implemented yet`); `test_release_gives_the_new_version_its_own_changelog_section` stays `ok`.

- [ ] **Step 3: Add the changelog modes**

Insert directly above `main()`:

```bash
run_changelog() {
    local path
    if [ "$ALL" = true ]; then
        mapfile -t TARGETS < <(module_paths)
        TARGETS+=("$ROOT_DIR")
    elif [ "${#TARGETS[@]}" -eq 0 ]; then
        TARGETS=("$ROOT_DIR")
    fi
    for path in "${TARGETS[@]}"; do
        regenerate_changelog "$path" "$path/CHANGELOG.md" || die "$EXIT_FAILURE" "$(display_name "$path"): changelog regeneration failed"
        printf 'Regenerated %s\n' "$(display_name "$path")"
    done
    exit "$EXIT_OK"
}

# Prints changelog $1 without its [unreleased] section, which is stale after any commit by nature.
released_sections() {
    awk '
        /^## \[unreleased\]/ { skip = 1; next }
        /^## \[/ { skip = 0 }
        !skip { print }
    ' "$1"
}

run_changelog_check() {
    local path tmp repos=() diverged=()
    mapfile -t repos < <(module_paths)
    repos+=("$ROOT_DIR")
    for path in "${repos[@]}"; do
        tmp=$(mktemp) || die "$EXIT_FAILURE" "cannot create a temporary file"
        if ! regenerate_changelog "$path" "$tmp"; then
            rm -f "$tmp"
            die "$EXIT_FAILURE" "$(display_name "$path"): changelog regeneration failed"
        fi
        if [ ! -f "$path/CHANGELOG.md" ] || ! diff -q <(released_sections "$tmp") <(released_sections "$path/CHANGELOG.md") >/dev/null; then
            diverged+=("$(display_name "$path")")
        fi
        rm -f "$tmp"
    done
    if [ "${#diverged[@]}" -gt 0 ]; then
        printf 'Changelog diverges from history in: %s\n' "${diverged[*]}" >&2
        printf 'Regenerate with: ./scripts/version.sh --changelog --all\n' >&2
        exit "$EXIT_CHANGELOG_DIVERGED"
    fi
    printf 'All changelogs match history.\n'
    exit "$EXIT_OK"
}
```

- [ ] **Step 4: Replace `main`**

```bash
main() {
    parse_args "$@"
    require_tools
    case "$MODE" in
        release) run_release ;;
        changelog) run_changelog ;;
        changelog-check) run_changelog_check ;;
    esac
}
```

- [ ] **Step 5: Run the tests to see them pass**

Run: `bash scripts/tests/version-test.sh`

Expected: final line `35 passed, 0 failed`.

- [ ] **Step 6: Check the real repositories**

Run: `cd /srv/http/laraplate-stack/laraplate && ./scripts/version.sh --changelog-check; echo "exit=$?"`

Expected: `All changelogs match history.` and `exit=0`, because Task 2 regenerated every changelog with the same configuration. `exit=4` means a repository changed history or configuration after Task 2: report which one, do not regenerate without asking.

- [ ] **Step 7: Commit**

```bash
cd /srv/http/laraplate-stack/laraplate
git add scripts/version.sh scripts/tests/version-test.sh
git commit -m "feat(release): regenerate and verify changelogs against history" -m "Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 8: Delete the old hook machinery

No git hook is part of the release tooling. A `commit-msg` gate and its installer were first built in this task (`76e88b1`) and removed the same day by the owner's decision, recorded in the spec under Hooks. This task describes the final state.

**Files:**
- Delete: `scripts/hooks/`, `scripts/setup-hooks.sh`, `scripts/test-hooks.sh`, `scripts/install-hooks.sh`
- Modify: `composer.json` (remove `setup:hooks`)
- Modify: `Modules/Core/README.md`, `Modules/AI/README.md`, `Modules/ERP/README.md`

**Interfaces:** none.

- [x] **Step 1: Delete the files and the Composer script**

```bash
cd /srv/http/laraplate-stack/laraplate
git rm -r --quiet scripts/hooks scripts/setup-hooks.sh scripts/test-hooks.sh scripts/install-hooks.sh
tmp=$(mktemp) && jq 'del(.scripts["setup:hooks"])' composer.json > "$tmp" && mv "$tmp" composer.json
```

Expected: `git diff composer.json` removes only the `setup:hooks` line. `jq` adds a final newline the file does not have; remove it so the diff stays one line.

- [x] **Step 2: Point the module READMEs at the application**

The Core, AI and ERP READMEs still told the reader to run `composer version:*` and `composer setup:hooks` inside the module, where neither script exists any more. Replace both with one sentence saying releases run from the application, and the command to use there. Commit inside each module, then record the pointers in the application.

- [x] **Step 3: Confirm nothing references hooks any more**

```bash
cd /srv/http/laraplate-stack/laraplate
grep -rnE "install-hooks|setup:hooks|setup-hooks" scripts composer.json docs/releasing.md Modules/*/README.md || echo none
```

Expected: `none`.

- [x] **Step 4: Run the harness**

Run: `bash scripts/tests/version-test.sh`

Expected: `35 passed, 0 failed`.

### Task 9: Composer scripts, `.gitmodules` branch, documentation

**Files:**
- Modify: `composer.json` (`scripts`)
- Modify: `.gitmodules`
- Create: `docs/releasing.md`
- Modify: `docs/README.md`

**Interfaces:**
- Consumes: the complete `scripts/version.sh` and `scripts/tests/version-test.sh`.
- Produces: the operator surface.

- [ ] **Step 1: Verify how Composer passes arguments to an array script**

```bash
S=$(mktemp -d)
printf '#!/bin/bash\necho "ARGS=[$*]"\n' > "$S/t.sh" && chmod +x "$S/t.sh"
printf '{"scripts":{"probe":["Composer\\\\Config::disableProcessTimeout","./t.sh --all"]}}\n' > "$S/composer.json"
(cd "$S" && composer run probe Core 2>&1 | tail -1)
rm -rf "$S"
```

Expected: `ARGS=[--all Core]`. If the extra argument is missing, use plain string scripts (no `disableProcessTimeout`) in Step 2 and note in `docs/releasing.md` that an unanswered prompt is killed after Composer's 300 second process timeout.

- [ ] **Step 2: Set the Composer scripts**

```bash
cd /srv/http/laraplate-stack/laraplate
tmp=$(mktemp) && jq '
  .scripts["version"] = "./scripts/version.sh --nointeractive"
  | .scripts["version:module"] = ["Composer\\Config::disableProcessTimeout", "./scripts/version.sh"]
  | .scripts["version:major"] = ["Composer\\Config::disableProcessTimeout", "./scripts/version.sh major"]
  | .scripts["version:minor"] = ["Composer\\Config::disableProcessTimeout", "./scripts/version.sh minor"]
  | .scripts["version:patch"] = ["Composer\\Config::disableProcessTimeout", "./scripts/version.sh patch"]
  | .scripts["version:all"] = ["Composer\\Config::disableProcessTimeout", "./scripts/version.sh --all"]
  | .scripts["version:dry"] = "./scripts/version.sh --dry-run"
  | .scripts["version:test"] = "bash scripts/tests/version-test.sh"
  | .scripts["changelog"] = "./scripts/version.sh --changelog"
  | .scripts["changelog:check"] = "./scripts/version.sh --changelog-check"
' composer.json > "$tmp" && mv "$tmp" composer.json
composer validate --no-check-publish --no-check-lock 2>&1 | tail -2
composer run version:dry Core
```

Expected: `composer validate` reports the file as valid (warnings unrelated to `scripts` are acceptable); the last command prints a plan table whose only row is `Core`.

- [ ] **Step 3: Declare the tracked branch of every module**

```bash
cd /srv/http/laraplate-stack/laraplate
for module in Core CMS AI ERP MES SAO; do
    git config --file .gitmodules "submodule.Modules/$module.branch" master
done
git config --file .gitmodules --get-regexp branch
```

Expected: six lines `submodule.Modules/<Name>.branch master`.

- [ ] **Step 4: Create `docs/releasing.md`**

````markdown
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

Modules are released first, Core before the others. The application then gets one `chore(modules): bump ...` commit recording the released modules, and is released as well, at least as a patch. If any module fails a precondition, nothing is written anywhere. Releasing a named module never touches the application.

## Exit codes

`0` done, `10` nothing to release, `2` usage error, `3` precondition failed, `4` changelog diverged, `1` failure. After a failure the script prints what it left behind and the commands to undo it.
````

- [ ] **Step 5: Link it from `docs/README.md`**

Append at the end of `docs/README.md`:

```markdown

## Operations

- [Releasing the application and its modules](releasing.md)
```

- [x] **Step 5b: Fix the broken pipe found by `composer run version:dry Core`** (found during execution)

Composer runs scripts with SIGPIPE ignored. The first version of `add_target` returned from inside `while read ... done < <(module_paths)`, so `module_paths` kept writing into a closed pipe and bash printed `printf: write error: Broken pipe` on stderr. Measured with SIGPIPE ignored over 20 runs: Core 96 error lines, AI 40, SAO 0, an unknown target 0, which is the early return and nothing else. `add_target` in Task 4 above is already the corrected version: it reads the whole list with `mapfile` before matching. Regression test, appended above `# --- runner`:

```bash
# Composer runs scripts with SIGPIPE ignored, so a reader that stops early makes the writer report
# "write error: Broken pipe" instead of dying silently. Core is listed first, so its lookup stops early.
test_no_broken_pipe_when_sigpipe_is_ignored() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app" i
    make_app "$app" Core AI CMS ERP
    for i in 1 2 3 4 5; do
        OUTPUT=$( (trap '' PIPE; LC_ALL=C VERSION_ROOT_DIR="$app" VERSION_FORCE_INTERACTIVE=0 bash "$VERSION_SH" Core --dry-run) 2>&1 </dev/null)
        STATUS=$?
        assert_output_lacks "Broken pipe"
    done
}
```

- [ ] **Step 6: Run the harness one last time**

Run: `bash scripts/tests/version-test.sh`

Expected: `36 passed, 0 failed`.

- [ ] **Step 7: Commit**

```bash
cd /srv/http/laraplate-stack/laraplate
git add composer.json .gitmodules docs/releasing.md docs/README.md
git commit -m "docs(release): document the release process and expose it through Composer" -m "Modules now declare master as their tracked branch, so a fresh submodule update does not leave them detached for the release preconditions." -m "Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

- [ ] **Step 8: Verify a real module release end to end (ask the user first: it tags and pushes)**

First confirm no module carries release tooling any more and each keeps its version field:

```bash
cd /srv/http/laraplate-stack/laraplate
for module in Core CMS AI ERP MES SAO; do
    for path in scripts/version.sh scripts/setup-hooks.sh scripts/hooks cliff.toml; do
        if [ -e "Modules/$module/$path" ]; then echo "still present: Modules/$module/$path"; fi
    done
    printf '%s %s %s\n' "$module" "$(jq -r .version "Modules/$module/composer.json")" "$(jq -c '.scripts // {} | keys | map(select(startswith("version")))' "Modules/$module/composer.json")"
done
```

Expected: no `still present` line (`Modules/Core/scripts/bench/` holds benchmark fixtures and legitimately remains), and six lines, each with a `vX.Y.Z` version and `[]`.

Then, only after the user says yes, release the module with the least traffic:

```bash
cd /srv/http/laraplate-stack/laraplate
before=$(git rev-parse HEAD)
./scripts/version.sh MES patch --nointeractive; echo "exit=$?"
git -C Modules/MES log -1 --pretty=%s
git -C Modules/MES tag --points-at HEAD
if [ "$(git rev-parse HEAD)" = "$before" ]; then echo "application untouched"; fi
```

Expected: `exit=0`, `chore(release): vX.Y.Z` as the MES subject, the same `vX.Y.Z` as the tag on MES, and `application untouched`. The application then shows `Modules/MES` as modified in `git status`: recording that pointer is the user's call.

---

### Task 10: Carry the module release level into the application under `--all`

Found after Task 9, reviewing with the owner. Task 6 recorded released modules with a plain `chore(modules): bump ...` commit and released the application at least as a patch. For git-cliff a `chore` is a patch, so a feature or a breaking change in a module reached the application as a patch and landed under Miscellaneous in its changelog: the application's version could not show what kind of update the modules brought. This task supersedes `plan_application_after_modules` and `consolidate_pointers` from Task 6 and the pointer-commit assertion of `test_all_releases_modules_then_the_application`. Releasing named modules without `--all` still does not touch the application.

**Files:**
- Modify: `scripts/version.sh` (add `release_level`, `highest_module_level`; replace `plan_application_after_modules`, `consolidate_pointers`)
- Modify: `scripts/tests/version-test.sh`
- Modify: `docs/releasing.md`, spec section "Orchestration order for `--all`"

**Interfaces:**
- Consumes: `PLAN_CURRENT`, `PLAN_NEXT`, `PLAN_VERDICT`, `increment_version`, `version_gt`, `plan_target` (Task 4).
- Produces: `release_level <from> <to>` prints `major|minor|patch`; `highest_module_level <module-path...>` prints the highest level among modules planned for release (default `patch`).

- [x] **Step 1: Write the failing tests**

In `test_all_releases_modules_then_the_application`, the pointer commit becomes `feat(modules): bump Core v1.1.0`, the release commit `chore(release): v1.1.0` and the application tag `v1.1.0`. Add, above the SIGPIPE test:

```bash
test_all_carries_a_breaking_module_release_into_the_application() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core CMS
    commit "$app/Modules/Core" "feat(api)!: drop the old endpoint"
    commit "$app/Modules/CMS" "fix: repair the content"
    run_version "$app" --all --nointeractive
    assert_status 0
    assert_tag "$app/Modules/Core" v2.0.0
    assert_tag "$app/Modules/CMS" v1.0.1
    assert_eq "$(git -C "$app" log -1 --pretty=%s HEAD~1)" "feat(modules)!: bump Core v2.0.0, CMS v1.0.1" "pointer commit"
    assert_tag "$app" v2.0.0
    assert_file_contains "$app/CHANGELOG.md" "Bump Core v2.0.0, CMS v1.0.1"
}

test_all_keeps_a_patch_only_module_release_a_patch() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app"
    make_app "$app" Core
    commit "$app/Modules/Core" "fix: repair the core"
    run_version "$app" --all --nointeractive
    assert_status 0
    assert_eq "$(git -C "$app" log -1 --pretty=%s HEAD~1)" "chore(modules): bump Core v1.0.1" "pointer commit"
    assert_tag "$app" v1.0.1
}
```

Run: `bash scripts/tests/version-test.sh all_`

Expected: `FAIL` for `test_all_carries_a_breaking_module_release_into_the_application` and `test_all_releases_modules_then_the_application`; `test_all_keeps_a_patch_only_module_release_a_patch` already passes and pins the patch case.

- [x] **Step 2: Implement**

Add the two helpers above `plan_application_after_modules` and replace both functions:

```bash
# Prints the level (major, minor or patch) that separates version $1 from the higher version $2.
release_level() {
    local from_major from_minor to_major to_minor rest
    IFS=. read -r from_major from_minor rest <<< "${1#v}"
    IFS=. read -r to_major to_minor rest <<< "${2#v}"
    if [ "$to_major" != "$from_major" ]; then
        printf 'major\n'
    elif [ "$to_minor" != "$from_minor" ]; then
        printf 'minor\n'
    else
        printf 'patch\n'
    fi
}

# Prints the highest release level among the given module paths that are planned for release.
highest_module_level() {
    local path level highest=patch
    for path in "$@"; do
        if [ "${PLAN_VERDICT[$path]}" != "release" ]; then
            continue
        fi
        level=$(release_level "${PLAN_CURRENT[$path]}" "${PLAN_NEXT[$path]}")
        if [ "$level" = major ]; then
            highest=major
        elif [ "$level" = minor ] && [ "$highest" != major ]; then
            highest=minor
        fi
    done
    printf '%s\n' "$highest"
}

# Plans the application under --all. Releasing any module adds a pointer commit to the
# application, so the application is then released too, at least at the highest module level.
plan_application_after_modules() {
    local path any=false candidate
    for path in "$@"; do
        if [ "${PLAN_VERDICT[$path]}" = "release" ]; then
            any=true
        fi
    done
    plan_target "$ROOT_DIR"
    if [ "$any" != true ]; then
        return 0
    fi
    if [ -n "$BUMP" ]; then
        candidate=$(increment_version "${PLAN_CURRENT[$ROOT_DIR]}" "$BUMP")
    else
        candidate=$(increment_version "${PLAN_CURRENT[$ROOT_DIR]}" "$(highest_module_level "$@")")
    fi
    if [ "${PLAN_VERDICT[$ROOT_DIR]}" != "release" ]; then
        PLAN_VERDICT[$ROOT_DIR]="release"
        PLAN_NEXT[$ROOT_DIR]=$candidate
    elif version_gt "$candidate" "${PLAN_NEXT[$ROOT_DIR]}"; then
        PLAN_NEXT[$ROOT_DIR]=$candidate
    fi
}

# Records the new HEAD of every released module in the application with one commit, typed after
# the highest module level so git-cliff infers the application's level and changelog group from it.
consolidate_pointers() {
    local path joined type summary=()
    for path in "$@"; do
        git -C "$ROOT_DIR" add -- "${path#"$ROOT_DIR"/}" || return 1
        summary+=("${path##*/} ${PLAN_NEXT[$path]}")
    done
    case "$(highest_module_level "$@")" in
        major) type="feat(modules)!" ;;
        minor) type="feat(modules)" ;;
        *) type="chore(modules)" ;;
    esac
    printf -v joined '%s, ' "${summary[@]}"
    git -C "$ROOT_DIR" commit --quiet -m "$type: bump ${joined%, }"
}
```

- [x] **Step 3: Run the tests**

Run: `bash scripts/tests/version-test.sh`

Expected: `38 passed, 0 failed`. Then `./scripts/version.sh --all --dry-run` still lists Core, AI, CMS, ERP, MES, SAO and the application, writing nothing.

- [x] **Step 4: Commit**

```bash
cd /srv/http/laraplate-stack/laraplate
git add scripts/version.sh scripts/tests/version-test.sh docs/releasing.md docs/superpowers/specs/2026-08-30-release-tooling-design.md docs/superpowers/plans/2026-09-15-release-tooling.md
git commit -m "fix(release): carry the module release level into the application under --all" -m "Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 11: `CHANGELOG.md` lists released versions only

Found after Task 10, reviewing with the owner. `--changelog` rewrote the file with an `[unreleased]` section, so running it between releases (as Task 2 did once, mid-cycle) left a changelog that announced work not yet released and changed again at the next release. The rule is now that `CHANGELOG.md` changes only when a release is cut: a release writes it with `--tag`, which leaves no `[unreleased]` section, and `--changelog` writes only the released sections, with the same `released_sections` filter `--changelog-check` already applies. No changelog in the repositories is rewritten by this task: the owner's releases had already closed every module's `[unreleased]` section, and the application's closes at its next release.

**Files:**
- Modify: `scripts/version.sh` (`run_changelog`)
- Modify: `scripts/tests/version-test.sh` (replace `test_changelog_regenerates_only_the_target`)
- Modify: `docs/releasing.md`, spec section "Changelog"

**Interfaces:**
- Consumes: `regenerate_changelog`, `released_sections`, `module_paths`, `display_name` (Tasks 4 and 7).
- Produces: nothing new.

- [x] **Step 1: Replace the failing test**

Replace `test_changelog_regenerates_only_the_target`, which asserted the `[unreleased]` section, with:

```bash
test_changelog_never_writes_unreleased_work() {
    local app="$WORK_DIR/${FUNCNAME[0]}/app" released
    make_app "$app" Core
    run_version "$app" --changelog Core
    assert_status 0
    released=$(cat "$app/Modules/Core/CHANGELOG.md")
    commit "$app/Modules/Core" "fix: repair parsing"
    run_version "$app" --changelog Core
    assert_status 0
    assert_file_lacks "$app/Modules/Core/CHANGELOG.md" "## [unreleased]"
    assert_file_lacks "$app/Modules/Core/CHANGELOG.md" "Repair parsing"
    assert_eq "$(cat "$app/Modules/Core/CHANGELOG.md")" "$released" "changelog after an unreleased commit"
    [ ! -e "$app/CHANGELOG.md" ] || fail "the application changelog must not be written"
}
```

Run: `bash scripts/tests/version-test.sh changelog`

Expected: `FAIL test_changelog_never_writes_unreleased_work`, the other three changelog tests `ok`.

- [x] **Step 2: Implement**

Replace `run_changelog`:

```bash
# Rewrites CHANGELOG.md of each target with its released versions only; work after the last tag is
# left out until it is released, so the file stays what the last release wrote.
run_changelog() {
    local path tmp
    if [ "$ALL" = true ]; then
        mapfile -t TARGETS < <(module_paths)
        TARGETS+=("$ROOT_DIR")
    elif [ "${#TARGETS[@]}" -eq 0 ]; then
        TARGETS=("$ROOT_DIR")
    fi
    for path in "${TARGETS[@]}"; do
        tmp=$(mktemp) || die "$EXIT_FAILURE" "cannot create a temporary file"
        if ! regenerate_changelog "$path" "$tmp"; then
            rm -f "$tmp"
            die "$EXIT_FAILURE" "$(display_name "$path"): changelog regeneration failed"
        fi
        released_sections "$tmp" > "$path/CHANGELOG.md"
        rm -f "$tmp"
        printf 'Regenerated %s\n' "$(display_name "$path")"
    done
    exit "$EXIT_OK"
}
```

- [x] **Step 3: Run the tests**

Run: `bash scripts/tests/version-test.sh`

Expected: `38 passed, 0 failed`, and `git status` in the real repositories shows no `CHANGELOG.md` change.

- [x] **Step 4: Commit**

```bash
cd /srv/http/laraplate-stack/laraplate
git add scripts/version.sh scripts/tests/version-test.sh docs/releasing.md docs/superpowers/specs/2026-08-30-release-tooling-design.md docs/superpowers/plans/2026-09-15-release-tooling.md
git commit -m "fix(release): keep unreleased work out of CHANGELOG.md" -m "Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```
