#!/bin/bash

# Backup del composer.lock root
cp composer.lock composer.lock.backup

# Aggiorna il composer.json root
composer update

# Aggiorna solo i composer.json dei moduli usando il composer.lock root
for module in Modules/*/composer.json; do
    module_dir=$(dirname "$module")
    module_name=$(basename "$module_dir")
    echo "Aggiornando composer.json in $module_name"
    
    # Copia temporaneamente il composer.lock nella directory del modulo
    cp composer.lock "$module_dir/composer.lock"
    
    # Esegue l'update rispettando i constraint del lock file
    (cd "$module_dir" && composer update --no-install --lock)
    
    # Rimuove il composer.lock temporaneo
    rm "$module_dir/composer.lock"
done

# Ripristina il composer.lock originale e fa l'update finale
mv composer.lock.backup composer.lock 