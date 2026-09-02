#!/bin/bash

if [[ "$PWD" == *"scripts"* ]]; then
   cd ..
fi

git submodule foreach --recursive '
    before=$(git describe --tags --abbrev=0 HEAD 2>/dev/null || echo "no tag") 
    git pull
    after=$(git describe --tags --abbrev=0 HEAD 2>/dev/null || echo "no tag")
    if [ "$before" != "$after" ]; then
        echo "from $before to $after"
    fi
    echo ""
'

echo "Back to Laraplate"
before=$(git describe --tags --abbrev=0 HEAD 2>/dev/null || echo "no tag")
git pull
after=$(git describe --tags --abbrev=0 HEAD 2>/dev/null || echo "no tag")
[ "$before" != "$after" ] && echo "from $before to $after" || echo""

read -p "Do you want to run the full update? (y/N): " choice

if [[ ! "$choice" =~ ^[Yy]$ ]]; then
    # echo "Update aborted."
    exit 0
fi

composer install

php artisan migrate
php artisan db:seed

php artisan optimize:clear
php artisan optimize

if command -v supervisorctl &> /dev/null; then
    supervisorctl reload
    php artisan horizon:restart
fi
