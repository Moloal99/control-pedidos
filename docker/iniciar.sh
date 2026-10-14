#!/bin/sh
# Arranque del contenedor: clave, cache de configuracion, migraciones,
# datos iniciales, programador de tareas y servidor web.
set -e
cd /app

# Render entrega la URL publica en RENDER_EXTERNAL_URL
if [ -z "$APP_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

# APP_KEY estable derivada de APP_SECRETO (Render lo genera solo; nadie lo teclea)
if [ -z "$APP_KEY" ] && [ -n "$APP_SECRETO" ]; then
    APP_KEY=$(php -r 'echo "base64:".base64_encode(hash("sha256", getenv("APP_SECRETO"), true));')
    export APP_KEY
fi

# SQLite: el archivo se crea si no existe
if [ "$DB_CONNECTION" = "sqlite" ] && [ -n "$DB_DATABASE" ]; then
    mkdir -p "$(dirname "$DB_DATABASE")"
    # Base vacia + modo demo: se parte de la demostracion armada al compilar
    if [ ! -s "$DB_DATABASE" ] && [ "$PEDIDOS_MODO" = "demo" ] && [ -s /app/database/demo.sqlite ]; then
        cp /app/database/demo.sqlite "$DB_DATABASE"
    fi
    touch "$DB_DATABASE"
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# La base puede tardar unos segundos en aceptar conexiones
intentos=0
until php artisan migrate --force; do
    intentos=$((intentos + 1))
    if [ "$intentos" -ge 10 ]; then
        echo "No se pudo migrar la base de datos." >&2
        exit 1
    fi
    sleep 5
done

php artisan pedidos:preparar

# Sincronizacion con SharePoint, correos en cola y cierre de sesiones inactivas
php artisan schedule:work > /dev/stdout 2>&1 &

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}" --no-reload
