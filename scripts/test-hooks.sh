#!/bin/bash
ICON_CHECKMARK="\033[32m✓\033[0m"
ICON_CROSS="\033[31m✗\033[0m"

echo "Testing git hooks system..."

# Test 1: Check if hooks are installed
echo "1. Checking if hooks are installed:"
if [ -L ".git/hooks/post-commit" ]; then
    echo -e "   $ICON_CHECKMARK post-commit hook is installed"
else
    echo -e "   $ICON_CROSS post-commit hook is NOT installed"
fi

# Test 2: Check if version script is executable
echo "2. Checking if version script is executable:"
if [ -x "scripts/version.sh" ]; then
    echo -e "   $ICON_CHECKMARK version.sh is executable"
else
    echo -e "   $ICON_CROSS version.sh is NOT executable"
fi

# Test 3: Test version script with a sample commit message
echo "3. Testing version script:"
echo "   Testing with '$(git log -1 --pretty=%B | head -1)'"
./scripts/version.sh --nointeractive --silent

echo "Done!"