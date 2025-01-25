#!/bin/bash

# Funzione per incrementare la versione
increment_version() {
    local version=$1
    local position=$2

    # Divide la versione in array
    IFS='.' read -ra VERSION_PARTS <<< "$version"
    
    # Incrementa la parte specificata
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

    # Ricompone la versione
    echo "${VERSION_PARTS[0]}.${VERSION_PARTS[1]}.${VERSION_PARTS[2]}"
}

# Funzione per ottenere l'ultima versione tag
get_latest_version() {
    local latest_tag=$(git describe --tags `git rev-list --tags --max-count=1` 2>/dev/null)
    if [ -z "$latest_tag" ]; then
        echo "0.0.0"
    else
        echo "$latest_tag"
    fi
}

# Funzione per aggiornare la versione nel repository corrente
update_version() {
    local position=$1
    local current_version=$(get_latest_version)
    local new_version=$(increment_version "$current_version" "$position")
    
    echo "Aggiornamento versione da $current_version a $new_version"
    git tag -a "$new_version" -m "Release $new_version"
    git push origin "$new_version"
}

# Gestione dei comandi
case $1 in
    "major"|"minor"|"patch")
        update_version $1
        ;;
    *)
        echo "Uso: $0 {major|minor|patch}"
        exit 1
        ;;
esac 