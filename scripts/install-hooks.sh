#!/usr/bin/env bash
#
# Links every hook in scripts/hooks into the application and each module under Modules/.
# Hook directories are resolved by git, so this works whether .git is a directory or a file.

set -uo pipefail

ROOT_DIR="${VERSION_ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
HOOKS_SOURCE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/hooks"

install_into() {
    local repo=$1 label=$2 hooks_dir hook
    hooks_dir=$(git -C "$repo" rev-parse --path-format=absolute --git-path hooks) || return 1
    mkdir -p "$hooks_dir" || return 1
    for hook in "$HOOKS_SOURCE"/*; do
        ln -sfn "$hook" "$hooks_dir/${hook##*/}" || return 1
        printf 'Installed %s in %s\n' "${hook##*/}" "$label"
    done
}

status=0
install_into "$ROOT_DIR" "application" || status=1
for module in "$ROOT_DIR"/Modules/*/; do
    module=${module%/}
    if [ -e "$module/.git" ]; then
        install_into "$module" "${module##*/}" || status=1
    fi
done
exit "$status"
