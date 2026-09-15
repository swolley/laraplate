#!/bin/bash

# function to get the last commit message
get_last_commit_message() {
    # git log -1 --pretty=%B
    local last_tag=$(git describe --tags --abbrev=0 HEAD 2>/dev/null)
    
    if [ -z "$last_tag" ]; then
        git log -1 --pretty=%B
    else
        git log "$last_tag..HEAD" --pretty=%B
    fi
}

# Function to determine the importance of a single commit message
get_commit_importance() {
    local commit_message="$1"
    
    # Check for breaking changes (major) - highest priority
    if [[ "$commit_message" =~ ^(feat|fix|perf|refactor)(\([a-z0-9-]+\))?! ]]; then
        echo "major"
        return
    fi
    
    # Check for features (minor) - medium priority
    if [[ "$commit_message" =~ ^feat(\([a-z0-9-]+\))?: ]]; then
        echo "minor"
        return
    fi
    
    # Check for other conventional commit types (patch) - low priority
    if [[ "$commit_message" =~ ^(fix|perf|refactor)(\([a-z0-9-]+\))?: ]]; then
        echo "patch"
        return
    fi
    
    # If no recognizable pattern is found, default to null
    echo "null"
}

is_already_tagged() {
    local last_commit_hash=$(git rev-parse HEAD)
    local tag_at_commit=$(git tag --points-at "$last_commit_hash")
    if [ -n "$tag_at_commit" ]; then
        return 0
    else
        return 1
    fi
}

# Function to determine the maximum importance among all commit messages
determine_max_importance() {
    local commit_messages="$1"
    local max_importance="null"
    
    # Read each line (each commit message)
    while IFS= read -r commit_message; do
        if [ -n "$commit_message" ]; then
            local importance=$(get_commit_importance "$commit_message")
            
            # Update the maximum importance
            case "$importance" in
                "major")
                    max_importance="major"
                    break  # Major is the maximum, we can stop
                    ;;
                "minor")
                    if [ "$max_importance" != "major" ]; then
                        max_importance="minor"
                    fi
                    ;;
                "patch")
                    if [ "$max_importance" = "null" ]; then
                        max_importance="patch"
                    fi
                    ;;
            esac
        fi
    done <<< "$commit_messages"
    
    echo "$max_importance"
}

# Determine the release type from the commit messages
determine_release_type() {
    local commit_messages=$(get_last_commit_message)

    if is_already_tagged; then
        echo "Commit is already tagged, skipping version bump"
        echo "null"
        return
    fi
    
    # Determine the maximum importance among all commit messages
    local max_importance=$(determine_max_importance "$commit_messages")
    echo "$max_importance"
}

# Function to increment the version
# Arguments:
#   $1: Version string
#   $2: Position to increment (major, minor, patch)
# Returns:
#   Incremented version string
increment_version() {
    local version=$1
    local position=$2

    # Remove the 'v' prefix if present
    version=${version#v}
    
    # split version in array
    IFS='.' read -ra VERSION_PARTS <<< "$version"
    
    # increment the specified part
    case $position in
        "major")
            ((VERSION_PARTS[0]++))
            VERSION_PARTS[1]=0
            VERSION_PARTS[2]=0
            ;;
        "minor")
            ((VERSION_PARTS[1]++))
            VERSION_PARTS[2]=0
            ;;
        "patch")
            ((VERSION_PARTS[2]++))
            ;;
    esac

    # rebuild version with prefix 'v'
    echo "v${VERSION_PARTS[0]}.${VERSION_PARTS[1]}.${VERSION_PARTS[2]}"
}

# function to get the latest version tag
get_latest_version() {
    local latest_tag=$(git describe --tags `git rev-list --tags --max-count=1` 2>/dev/null)
    if [ -z "$latest_tag" ]; then
        echo "v0.0.0"
    else
        echo "$latest_tag"
    fi
}

# Function to amend or commit
# Arguments:
#   $1: Commit message
# Returns:
#   None
amend_or_commit() {
    local message=$1
    
    local unpushed=$(git rev-list @{upstream}..HEAD 2>/dev/null)
    if [ -n "$unpushed" ]; then
        # if there are unpushed commits, amend the last one
        git commit --amend --no-edit
    else
        # if no unpushed commits and version has changed, create new commit
        git commit -m "$message"
    fi
}

# Function to update composer.json
# Arguments:
#   $1: New version string
# Returns:
#   None
#
# Resolves the package composer.json in the target repository (the application, or a module
# named on the command line). Only the root JSON key "version" is updated; never scripts.version
# (the composer script name).
update_composer_version() {
    local new_version=$1

    local composer_file
    composer_file="$TARGET_DIR/composer.json"

    if [ ! -f "$composer_file" ]; then
        echo "Error: composer.json not found at $composer_file"
        exit 1
    fi

    if command -v jq >/dev/null 2>&1; then
        local tmp
        tmp=$(mktemp)
        jq --arg version "$new_version" '.version = $version' "$composer_file" > "$tmp" && mv "$tmp" "$composer_file"
    elif command -v composer >/dev/null 2>&1; then
        if ! composer config --file "$composer_file" version "$new_version"; then
            echo "Error: could not set version via composer (invalid semver?). Install jq for reliable updates."
            exit 1
        fi
    else
        echo "Error: install jq or ensure composer is available to update composer.json safely (sed cannot target root version without breaking scripts.version)."
        exit 1
    fi

    git add "$composer_file"
}

# Function to update the changelog
# Arguments:
#   $1: New version string
# Returns:
#   None
update_changelog() {
    local new_version=$1
    
    # update the changelog; the configuration is shared, the output belongs to the target repository
    git cliff --config "$ROOT_DIR/cliff.toml" --tag "$new_version" --output CHANGELOG.md
    
    # add the file to git (but don't commit yet)
    git add CHANGELOG.md
}

# Function to update the version in the current repository
# Arguments:
#   $1: Position to increment (major, minor, patch)
#   $2: Silent mode
# Returns:
#   None
update_version() {
    local position=$1
    local silent=$2

    # Check for uncommitted changes unless allowed or dry-run
    if [ "$DRY_RUN" != true ] && [ "$ALLOW_DIRTY" != true ] && [ -n "$(git status --porcelain)" ]; then
        echo "Error: There are uncommitted changes in the working directory."
        echo "Please commit or stash your changes before updating the version, or re-run with --allow-dirty."
        exit 1
    fi
    if [ "$DRY_RUN" != true ] && [ "$ALLOW_DIRTY" = true ] && [ -n "$(git status --porcelain)" ]; then
        echo "Warning: proceeding with a dirty working tree; only composer.json and CHANGELOG.md will be staged."
    fi

    local current_version=$(get_latest_version)
    local new_version=$(increment_version "$current_version" "$position")

    if [ "$silent" = true ]; then
        echo "DEBUG: Current version: $current_version"
        echo "DEBUG: Position: $position" 
        echo "DEBUG: New version: $new_version"
        echo "DEBUG: Are they equal? $([ "$current_version" == "$new_version" ] && echo "YES" || echo "NO")"
    fi
    
    if [ $current_version == $new_version ]; then
        echo "Version is already up to date"
        exit 0
    fi
    
    if [ "$DRY_RUN" = true ]; then
        echo "Dry run: would update $TARGET_NAME from $current_version to $new_version"
        return
    fi

    if [ "$silent" = true ]; then
        echo "Silent mode: should update version from $current_version to $new_version"
        return
    fi

    echo "Updating $TARGET_NAME from $current_version to $new_version"
    
    # update composer.json (stages the file)
    update_composer_version "$new_version"
    
    # update the changelog (stages the file)
    update_changelog "$new_version"
    
    # create a single commit with all changes
    amend_or_commit "chore: bump version to $new_version"
    
    # create and push the tag
    git tag -a "$new_version" -m "Release $new_version"
    
    # try to push, but don't fail if credentials are not available
    if git push 2>/dev/null; then
        git push origin "$new_version" 2>/dev/null || echo "Warning: Could not push tag. You may need to push manually: git push origin $new_version"
    else
        echo "Warning: Could not push commits. You may need to push manually: git push"
        echo "Warning: Could not push tag. You may need to push manually: git push origin $new_version"
    fi
}

ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)

# Resolve the repository to version. With no target, or with a target that is not a directory,
# it is the application itself. A target may be a module name (Core) or a path (Modules/Core).
TARGET_DIR="$ROOT_DIR"
TARGET_NAME="the application"
case "$1" in
    "" | major | minor | patch | --*) ;;
    *)
        if [ -d "$ROOT_DIR/$1" ]; then
            TARGET_DIR=$(cd "$ROOT_DIR/$1" && pwd)
        elif [ -d "$ROOT_DIR/Modules/$1" ]; then
            TARGET_DIR=$(cd "$ROOT_DIR/Modules/$1" && pwd)
        else
            echo "Error: no such module or directory: $1"
            echo "Usage: $0 [<Module>|<path>] {major|minor|patch} [--nointeractive] [--silent] [--dry-run] [--allow-dirty]"
            exit 1
        fi
        TARGET_NAME="$1"
        shift
        ;;
esac

if [ ! -e "$TARGET_DIR/.git" ]; then
    echo "Error: $TARGET_DIR is not a git repository, cannot version it"
    exit 1
fi

if [ ! -f "$TARGET_DIR/composer.json" ]; then
    echo "Error: composer.json not found at $TARGET_DIR/composer.json"
    exit 1
fi

# Every git call below acts on the current repository, so the target must be the working directory.
cd "$TARGET_DIR" || exit 1

SILENT=false
DRY_RUN=false
ALLOW_DIRTY=false
if [[ "$*" == *"--silent"* ]]; then
    SILENT=true
fi
if [[ "$*" == *"--dry-run"* ]]; then
    DRY_RUN=true
fi
if [[ "$*" == *"--allow-dirty"* ]]; then
    ALLOW_DIRTY=true
fi

# Check if --nointeractive flag is present
if [[ "$*" == *"--nointeractive"* ]]; then
    # Determine release type from commit message
    position=$(determine_release_type)
    if [ "$position" != "null" ]; then
        update_version "$position" "$SILENT"
    else
        echo "No version change needed"
        exit 0
    fi
else
    # Interactive mode
    case $1 in
        "major"|"minor"|"patch")
            update_version $1 "$SILENT"
            ;;
        "null")
            echo "No version change detected"
            exit 0
            ;;
        *)
            echo "Usage: $0 [<Module>|<path>] {major|minor|patch} [--nointeractive] [--silent] [--dry-run] [--allow-dirty]"
            exit 1
            ;;
    esac
fi
