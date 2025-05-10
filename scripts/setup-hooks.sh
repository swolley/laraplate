#!/bin/bash

# Copy the post-commit hook to the main repository
cp scripts/post-commit .git/hooks/
chmod +x .git/hooks/post-commit

# Run the setup script for each submodule
git submodule foreach 'cp scripts/post-commit "$(git rev-parse --git-dir)/hooks/" && chmod +x "$(git rev-parse --git-dir)/hooks/post-commit"'