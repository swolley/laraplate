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
