#!/usr/bin/env bash
#
# Tests for scripts/plan-status.php against throwaway workspaces.
# Usage: bash scripts/tests/plan-status-test.sh [name-filter]

set -uo pipefail

SCRIPTS_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
PLAN_STATUS="$SCRIPTS_DIR/plan-status.php"

WORK_DIR=$(mktemp -d)
trap 'rm -rf "$WORK_DIR"' EXIT

OUTPUT=""
STATUS=0

# Writes a plan at $1 with title $2 and one unflagged task.
make_plan() {
    local file=$1 title=$2
    mkdir -p "$(dirname "$file")"
    cat > "$file" <<PLAN
# $title

## Task 1: do the thing

- [ ] first step
- [ ] second step
PLAN
}

# Writes a source file at $1 carrying a TODO marker.
make_todo() {
    mkdir -p "$(dirname "$1")"
    printf '<?php\n// TODO: %s\n' "${2:-something}" > "$1"
}

# Runs plan-status.php over the workspace at $1; sets OUTPUT and STATUS.
run_plan_status() {
    local root=$1
    shift
    OUTPUT=$(php "$PLAN_STATUS" --root="$root" "$@" 2>&1 </dev/null)
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

assert_eq() {
    [ "$1" = "$2" ] || fail "${3:-value}: expected '$2', got '$1'"
}

# A workspace whose root holds plans of its own, two repositories inside it, and a
# folder that holds no plans at all.
make_workspace() {
    local ws=$1
    make_plan "$ws/docs/superpowers/plans/2026-01-01-root-plan.md" "Root plan"
    make_plan "$ws/child-alpha/docs/superpowers/plans/2026-01-02-alpha-plan.md" "Alpha plan"
    make_plan "$ws/child-beta/.cursor/plans/beta.plan.md" "Beta plan"
    mkdir -p "$ws/no-plans-here"
    printf 'nothing to see\n' > "$ws/no-plans-here/README.md"
}

# --- tests -------------------------------------------------------------------------------------

test_discovery_finds_the_root_and_every_repository_holding_plans() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    make_workspace "$ws"
    run_plan_status "$ws" --repos
    assert_status 0
    assert_output_contains "child-alpha"
    assert_output_contains "child-beta"
    assert_output_contains "$(basename "$ws")"
}

test_discovery_ignores_a_folder_with_no_plans() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    make_workspace "$ws"
    run_plan_status "$ws" --repos
    assert_status 0
    assert_output_lacks "no-plans-here"
}

test_discovery_finds_plans_under_modules() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    make_plan "$ws/backend/Modules/SAO/docs/plans/2026-01-03-module-plan.md" "Module plan"
    run_plan_status "$ws" --repos
    assert_status 0
    assert_output_contains "backend"
}

# The root alone is a workspace: pointed at a single repository the tool reports it
# and nothing else, which is what `composer run plan-status` relies on.
test_a_lone_repository_is_its_own_workspace() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    make_plan "$ws/docs/superpowers/plans/2026-01-01-only-plan.md" "Only plan"
    run_plan_status "$ws" --repos
    assert_status 0
    assert_eq "$(printf '%s\n' "$OUTPUT" | grep -c .)" "1" "repositories found"
}

test_the_default_root_is_the_repository_holding_the_script() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    mkdir -p "$ws/scripts"
    cp "$PLAN_STATUS" "$ws/scripts/plan-status.php"
    make_plan "$ws/docs/superpowers/plans/2026-01-01-local-plan.md" "Local plan"
    make_plan "$ws/../outside-plan-dir/docs/superpowers/plans/2026-01-01-outside.md" "Outside plan"
    OUTPUT=$(php "$ws/scripts/plan-status.php" --repos 2>&1 </dev/null)
    STATUS=$?
    assert_status 0
    assert_output_contains "$(basename "$ws")"
    assert_output_lacks "outside-plan-dir"
}

test_repo_filter_accepts_a_unique_substring() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    make_workspace "$ws"
    run_plan_status "$ws" --repo=alpha --summary
    assert_status 0
    assert_output_contains "2026-01-02-alpha-plan.md"
    assert_output_lacks "beta.plan.md"
}

test_repo_filter_rejects_an_ambiguous_substring() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    make_workspace "$ws"
    run_plan_status "$ws" --repo=child --summary
    assert_status 1
    assert_output_contains "Ambiguous"
}

test_repo_filter_rejects_an_unknown_name_and_names_what_it_found() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    make_workspace "$ws"
    run_plan_status "$ws" --repo=nowhere --summary
    assert_status 1
    assert_output_contains "Unknown --repo=nowhere"
    assert_output_contains "child-alpha"
}

test_an_exact_name_wins_over_a_substring_of_another_repository() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    make_plan "$ws/app/docs/superpowers/plans/2026-01-01-app-plan.md" "App plan"
    make_plan "$ws/app-extras/docs/superpowers/plans/2026-01-01-extras-plan.md" "Extras plan"
    run_plan_status "$ws" --repo=app --summary
    assert_status 0
    assert_output_contains "2026-01-01-app-plan.md"
    assert_output_lacks "2026-01-01-extras-plan.md"
}

test_a_spec_declaring_no_plan_is_not_listed_as_unplanned() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    make_plan "$ws/docs/superpowers/plans/2026-01-01-some-plan.md" "Some plan"
    mkdir -p "$ws/docs/superpowers/specs"
    printf '# Reference spec\n\n**Plan:** not required (reference document)\n' > "$ws/docs/superpowers/specs/2026-01-01-reference-design.md"
    printf '# Pending spec\n\nNo marker here.\n' > "$ws/docs/superpowers/specs/2026-01-02-pending-design.md"
    run_plan_status "$ws" --no-evidence
    assert_status 0
    assert_output_contains "Pending spec"
    assert_output_lacks "Reference spec"
}

test_a_missing_root_is_reported() {
    run_plan_status "$WORK_DIR/does-not-exist" --repos
    assert_status 1
    assert_output_contains "Invalid --root"
}

test_a_root_with_no_plans_at_all_is_reported() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    mkdir -p "$ws"
    run_plan_status "$ws" --repos
    assert_status 1
    assert_output_contains "No plans or specs found"
}

test_todo_scan_skips_vendor_and_node_modules() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    make_workspace "$ws"
    make_todo "$ws/child-alpha/vendor/library.php" "vendored marker"
    make_todo "$ws/child-alpha/node_modules/package.js" "packaged marker"
    make_todo "$ws/child-alpha/app/Real.php" "genuine marker"
    run_plan_status "$ws" --kind=todos
    assert_status 0
    assert_output_contains "genuine marker"
    assert_output_lacks "vendored marker"
    assert_output_lacks "packaged marker"
}

# `bootstrap/cache` carries a slash, and the exclusion list used to be compared
# against the last path segment alone, so it never matched.
test_todo_scan_skips_a_composite_excluded_path() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    make_workspace "$ws"
    make_todo "$ws/child-alpha/bootstrap/cache/compiled.php" "cached marker"
    make_todo "$ws/child-alpha/bootstrap/app.php" "bootstrap marker"
    run_plan_status "$ws" --kind=todos
    assert_status 0
    assert_output_contains "bootstrap marker"
    assert_output_lacks "cached marker"
}

# The root contains the repositories nested in it, so without pruning the same
# marker is reported once for the root and once for its owner.
test_a_marker_inside_a_repository_is_reported_once() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    make_workspace "$ws"
    make_todo "$ws/child-alpha/app/Real.php" "single marker"
    run_plan_status "$ws" --kind=todos
    assert_status 0
    assert_eq "$(printf '%s\n' "$OUTPUT" | grep -c 'single marker')" "1" "occurrences of the marker"
}

test_a_marker_in_the_root_itself_is_still_reported() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    make_workspace "$ws"
    make_todo "$ws/tools/helper.php" "root marker"
    run_plan_status "$ws" --kind=todos
    assert_status 0
    assert_output_contains "root marker"
}

test_only_uppercase_markers_inside_comments_are_reported() {
    local ws=$WORK_DIR/case-comments
    make_plan "$ws/child-alpha/docs/superpowers/plans/2026-01-01-plan.md" "Plan"
    mkdir -p "$ws/child-alpha/app"
    cat > "$ws/child-alpha/app/Board.php" <<'SRC'
<?php
// TODO: a real marker
/* FIXME: another real one */
# XXX: a hash comment
$todo = 'To do';
$columns = ['todo' => $todo, 'label' => 'TODO list'];
/** @todo document this */
/**
 * The scope is an unimplemented TODO elsewhere, so this prose is not a marker.
 */
SRC
    run_plan_status "$ws" --repo=child-alpha --kind=todos
    assert_status 0
    assert_output_contains "a real marker"
    assert_output_contains "another real one"
    assert_output_contains "a hash comment"
    assert_output_contains "document this"
    assert_output_lacks "Board.php:5"
    assert_output_lacks "Board.php:6"
    assert_output_lacks "Board.php:9"
}

test_the_scanner_does_not_report_its_own_source() {
    local ws=$WORK_DIR/self-scan
    make_plan "$ws/child-alpha/docs/superpowers/plans/2026-01-01-plan.md" "Plan"
    mkdir -p "$ws/child-alpha/scripts/tests"
    cp "$PLAN_STATUS" "$ws/child-alpha/scripts/plan-status.php"
    cp "$SCRIPTS_DIR/tests/plan-status-test.sh" "$ws/child-alpha/scripts/tests/plan-status-test.sh"
    make_todo "$ws/child-alpha/app/Real.php" "genuine marker"
    run_plan_status "$ws" --repo=child-alpha --kind=todos
    assert_status 0
    assert_output_contains "genuine marker"
    assert_output_lacks "scripts/plan-status.php:"
    assert_output_lacks "scripts/tests/plan-status-test.sh:"
}

test_tasks_are_reported_per_plan() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    make_workspace "$ws"
    run_plan_status "$ws" --format=json
    assert_status 0
    assert_output_contains '"tasks_open": 3'
}

# Checkboxes under a plain "##" section after the last task belong to that section, not to
# the task above it: a finished task must not turn open because a checklist follows it.
test_a_section_after_the_last_task_does_not_leak_into_it() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    mkdir -p "$ws/docs/superpowers/plans"
    cat > "$ws/docs/superpowers/plans/2026-01-01-leak.md" <<'PLAN'
# Leak

## Task 1: finished work

- [x] first step
- [x] second step

## Release gates

- [ ] run the suite
PLAN
    run_plan_status "$ws"
    assert_status 0
    [[ "$OUTPUT" =~ Task\ 1:\ finished\ work\ +2/2 ]] || fail "Task 1 should read 2/2"
    [[ "$OUTPUT" =~ Release\ gates\ +0/1 ]] || fail "Release gates should read 0/1 on its own"
}

# A task that only modifies files proves nothing by their presence: they existed before the
# task did. Only a file the task creates (or a migration, or a new test) is evidence of it.
test_a_modify_only_task_is_not_flagged_as_probably_done() {
    local ws="$WORK_DIR/${FUNCNAME[0]}"
    mkdir -p "$ws/docs/superpowers/plans" "$ws/src"
    printf '<?php\n' > "$ws/src/existing.php"
    printf '<?php\n' > "$ws/src/created.php"
    cat > "$ws/docs/superpowers/plans/2026-01-01-evidence.md" <<'PLAN'
# Evidence

## Task 1: only edits what was already there

- Modify: `src/existing.php`

- [ ] change it

## Task 2: brings a file into existence

- Create: `src/created.php`

- [ ] add it
PLAN
    run_plan_status "$ws" --stale
    assert_status 0
    assert_output_contains "1 unflagged task(s) have every declared file on disk"
    assert_output_lacks "Task 1: only edits"
    assert_output_contains "Task 2: brings a file"
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
