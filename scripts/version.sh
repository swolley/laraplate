#!/bin/bash

# function to increment the version
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

# function to update composer.json
update_composer_version() {
    local new_version=$1
    
    # use jq to update the version while keeping the file formatting
    if command -v jq >/dev/null 2>&1; then
        # if jq is installed
        tmp=$(mktemp)
        jq --arg version "$new_version" '.version = $version' composer.json > "$tmp" && mv "$tmp" composer.json
    else
        # fallback to sed if jq is not available
        sed -i "s/\"version\": \".*\"/\"version\": \"$new_version\"/" composer.json
    fi
    
    # commit the changes to the composer.json
    git add composer.json
    git commit -m "chore: bump version to $new_version"
}

# function to update the version in the current repository
update_version() {
    local position=$1
    local current_version=$(get_latest_version)
    local new_version=$(increment_version "$current_version" "$position")
    
    echo "Updating version from $current_version to $new_version"
    
    # update composer.json
    update_composer_version "$new_version"
    
    # create and push the tag
    git tag -a "$new_version" -m "Release $new_version"
    git push && git push origin "$new_version"
}

if [ -n "$2" ] && [ -d "Modules/$2" ]; then
    cd Modules/$2 && update_version $1
else
    case $1 in
        "major"|"minor"|"patch")
            update_version $1
            ;;
        *)
            echo "Usage: $0 {major|minor|patch}"
            exit 1
            ;;
    esac 
fi
