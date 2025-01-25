#!/bin/bash

# Copia l'hook post-commit nel repository principale
cp scripts/post-commit .git/hooks/
chmod +x .git/hooks/post-commit

# Esegui lo script di setup in ogni submodule
# Esegui lo script di setup per ogni submodule
git submodule foreach 'cp scripts/post-commit "$(git rev-parse --git-dir)/hooks/" && chmod +x "$(git rev-parse --git-dir)/hooks/post-commit"'