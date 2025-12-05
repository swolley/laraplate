#!/bin/bash

# Script per sincronizzare i pushurl SSH dai .gitmodules ai submodule
# Questo risolve il problema quando Git chiede username/password per i push

ICON_CHECKMARK="\033[32m✓\033[0m"
ICON_CROSS="\033[31m✗\033[0m"

# Directory principale del repository
REPO_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
GITMODULES_FILE="$REPO_DIR/.gitmodules"

if [ ! -f "$GITMODULES_FILE" ]; then
    echo -e "$ICON_CROSS File .gitmodules non trovato in $REPO_DIR"
    exit 1
fi

echo "Sincronizzazione pushurl SSH per i submodule..."
echo ""

# Leggi tutti i pushurl dal file .gitmodules
git config --file "$GITMODULES_FILE" --get-regexp 'submodule\..*\.pushurl' | while read key value; do
    # Estrai il nome del submodule dal key
    submodule=$(echo "$key" | sed 's/submodule\.\(.*\)\.pushurl/\1/')
    submodule_path="$REPO_DIR/$submodule"
    
    if [ ! -d "$submodule_path" ]; then
        echo -e "   $ICON_CROSS Submodule $submodule non trovato in $submodule_path"
        continue
    fi
    
    # Verifica se il pushurl è già configurato correttamente
    current_pushurl=$(git -C "$submodule_path" config --get remote.origin.pushurl 2>/dev/null)
    
    if [ "$current_pushurl" = "$value" ]; then
        echo -e "   $ICON_CHECKMARK $submodule: pushurl già configurato correttamente"
    else
        # Configura il pushurl
        if git -C "$submodule_path" config remote.origin.pushurl "$value" 2>/dev/null; then
            echo -e "   $ICON_CHECKMARK $submodule: pushurl configurato -> $value"
        else
            echo -e "   $ICON_CROSS $submodule: errore nella configurazione"
        fi
    fi
done

echo ""
echo "Configurazione completata!"

