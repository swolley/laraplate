#!/bin/bash

if [[ "$PWD" == *"scripts"* ]]; then
   cd ..
fi

git submodule foreach 'git pull'

before=$(git describe --tags --abbrev=0 HEAD 2>/dev/null || echo "no tag")

git pull

after=$(git describe --tags --abbrev=0 HEAD 2>/dev/null || echo "no tag")

if [ "$before" != "$after" ]; then
    message="Updated from $before to $after"
    echo $message
    echo "[$(date +%Y-%m-%d\ %H:%M:%S)] $message" >> update_app.log
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
