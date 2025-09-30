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
    
    echo "Analyzing commits since last tag:"
    echo "$commit_messages"
    echo "---"
    
    if is_already_tagged; then
        echo "Commit is already tagged, skipping version bump"
        echo "null"
        return
    fi
    
    # Determine the maximum importance among all commit messages
    local max_importance=$(determine_max_importance "$commit_messages")
    echo "Maximum importance found: $max_importance"
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
update_composer_version() {
    local new_version=$1
    
    cd "$(git rev-parse --show-toplevel)"
    
    if command -v jq >/dev/null 2>&1; then
        # if jq is installed - modifica la proprietà version alla root
        tmp=$(mktemp)
        jq --arg version "$new_version" '.version = $version' composer.json > "$tmp" && mv "$tmp" composer.json
    else
        # fallback to sed if jq is not available
        sed -i "s/^    \"version\": \".*\",$/    \"version\": \"$new_version\",/" composer.json
    fi
    
    # add the file to git
    git add composer.json
    amend_or_commit "chore: bump version to $new_version"
}

# Function to update the changelog
# Arguments:
#   $1: New version string
# Returns:
#   None
update_changelog() {
    local new_version=$1
    
    # update the changelog
    git cliff --output CHANGELOG.md
    
    # add the file to git
    git add CHANGELOG.md
    amend_or_commit "chore: update changelog for version $new_version"
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

    # Check for uncommitted changes
    if [ -n "$(git status --porcelain)" ]; then
        echo "Error: There are uncommitted changes in the working directory."
        echo "Please commit or stash your changes before updating the version."
        exit 1
    fi

    local current_version=$(get_latest_version)
    local new_version=$(increment_version "$current_version" "$position")
    
    if [ $current_version == $new_version ]; then
        echo "Version is already up to date"
        exit 0
    fi
    
    if [ "$silent" = true ]; then
        echo "Silent mode: should update version from $current_version to $new_version"
    else
        echo "Updating version from $current_version to $new_version"
        
        # update composer.json
        update_composer_version "$new_version"
        
        # update the changelog
        update_changelog "$new_version"
        
        # create and push the tag
        git tag -a "$new_version" -m "Release $new_version"
        git push && git push origin "$new_version"
    fi
}

SILENT=false
if [[ "$*" == *"--silent"* ]]; then
    SILENT=true
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
            echo "Usage: $0 {major|minor|patch} [--nointeractive] [--silent]"
            exit 1
            ;;
    esac
fi
