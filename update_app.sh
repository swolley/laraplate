#!/bin/bash

git pull

composer install

php artisan migrate
php artisan db:seed

php artisan optimize:clear
php artisan optimize

if command -v supervisorctl &> /dev/null; then
    supervisorctl reload
    php artisan horizon:restart
fi