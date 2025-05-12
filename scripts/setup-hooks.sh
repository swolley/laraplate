#!/bin/bash

# Directory of the script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
APP_DIR="$(dirname "$SCRIPT_DIR")"
# Regular git repository
GIT_DIR="$APP_DIR/.git"
ICON_CHECKMARK="\033[32m✓\033[0m"
ICON_CROSS="\033[31m✗\033[0m"

echo "Installing git hooks in $APP_DIR"

# Create hooks directory if it doesn't exist
HOOKS_DIR="$GIT_DIR/hooks"
if [ ! -d "$HOOKS_DIR" ]; then
    mkdir -p "$HOOKS_DIR"
fi

# Make all hook scripts executable
chmod +x "$SCRIPT_DIR"/*.sh

# Check if hooks directory exists and contains files
if [ ! -d "$SCRIPT_DIR/hooks" ] || [ -z "$(ls -A "$SCRIPT_DIR/hooks")" ]; then
    echo "  No hooks found"
else
    # Create symlinks for each hook
    for hook in "$SCRIPT_DIR"/hooks/*; do
        hook_name=$(basename "$hook" .sh)
        if [ "$hook_name" != "install" ]; then
            if [ -L "$HOOKS_DIR/$hook_name" ] && [ "$(readlink "$HOOKS_DIR/$hook_name")" = "$hook" ]; then
                echo -e "   $ICON_CHECKMARK Hook already set: $hook_name"
            else
                ln -sf "$hook" "$HOOKS_DIR/$hook_name" && echo -e " $ICON_CHECKMARK Installed $hook_name hook" || echo -e " $ICON_CROSS Failed to install $hook_name hook"
            fi
        fi
    done
fi

for submodule in "$APP_DIR/Modules"/*; do
    if [ -d "$submodule" ] && [ -f "$submodule/.git" ]; then
        echo ""
        bash "$submodule/scripts/setup-hooks.sh"
    fi
done
