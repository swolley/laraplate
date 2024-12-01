#!/bin/bash

git pull

php artisan migrate
php artisan db:seed

php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear

php artisan route:cache
php artisan view:cache
php artisan config:cache

if command -v supervisorctl &> /dev/null; then
    supervisorctl reload
fi