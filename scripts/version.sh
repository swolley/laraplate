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
    local path name existing names=()
    while IFS= read -r path; do
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
    done < <(module_paths)
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

main() {
    parse_args "$@"
    require_tools
    case "$MODE" in
        release) run_release ;;
        changelog) run_changelog ;;
        changelog-check) run_changelog_check ;;
    esac
}

main "$@"
