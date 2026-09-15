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
