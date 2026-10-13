# Despliegue en el servidor de Plásticos Carmen

Mismo esquema que SGC Calidad: **Windows Server + IIS + PHP NTS (FastCGI) + URL Rewrite + MariaDB**.
La preparación de IIS, PHP y URL Rewrite está detallada en `C:\dev\sgc-calidad\DESPLIEGUE.md`
(secciones 1 a 3); aquí va solo lo propio de Control de Pedidos.

Dirección sugerida: **`https://pedidos.plasticoscarmen.com`**, enlazada como tarjeta en el portal central.

---

## 1. Requisitos

| Componente | Nota |
|---|---|
| PHP 8.4 NTS x64 | Extensiones: `curl fileinfo gd intl mbstring openssl pdo_mysql zip sodium` |
| `upload_max_filesize` / `post_max_size` | **60M** (los comprobantes admiten hasta 50 MB) |
| MariaDB 10.6+ o MySQL 8 | Intercalación `utf8mb4_unicode_ci` |
| IIS con CGI y **URL Rewrite 2.1** | `public/web.config` ya está en el repositorio |
| Composer 2 y Node 20+ | Node solo para compilar los estilos |
| Salida HTTPS hacia `login.microsoftonline.com`, `graph.microsoft.com` y `*.sharepoint.com` | Sin esto no hay inicio de sesión ni listas |

## 2. Base de datos

```sql
CREATE DATABASE control_pedidos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'control_pedidos'@'localhost' IDENTIFIED BY 'una-clave-larga';
GRANT ALL PRIVILEGES ON control_pedidos.* TO 'control_pedidos'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Instalación

```powershell
cd C:\inetpub
git clone <repositorio> control-pedidos
cd control-pedidos
composer install --no-dev --optimize-autoloader
npm ci
npm run build
copy .env.example .env
php artisan key:generate
```

En el `.env` de producción:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pedidos.plasticoscarmen.com
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_DATABASE=control_pedidos
DB_USERNAME=control_pedidos
DB_PASSWORD=una-clave-larga
QUEUE_CONNECTION=database
PEDIDOS_MODO=sharepoint
MAIL_MAILER=graph
# + las variables MS_* y SP_* de docs/03-GUIA-ENTRA-ID.md
```

```powershell
php artisan migrate --force
php artisan db:seed --force            # crea los almacenes P11–P15 (en modo sharepoint no carga datos de demo)
php artisan sharepoint:sincronizar --completa
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Permisos: la identidad del grupo de aplicaciones de IIS necesita **Modificar** sobre `storage\` y `bootstrap\cache\`.

## 4. Sitio en IIS

- Ruta física: `C:\inetpub\control-pedidos\public`
- Enlace HTTPS con el certificado del dominio.
- Grupo de aplicaciones: «Sin código administrado».

## 5. Tareas programadas (obligatorio)

Todo lo periódico corre con el programador de Laravel: sincronización con SharePoint cada minuto, pasada
completa a las 03:30, envío de correos en cola y cierre de sesiones inactivas. Basta **una** tarea de Windows:

```powershell
$accion = New-ScheduledTaskAction -Execute "C:\php\php.exe" -Argument "C:\inetpub\control-pedidos\artisan schedule:run" -WorkingDirectory "C:\inetpub\control-pedidos"
$disparo = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)
Register-ScheduledTask -TaskName "Control de Pedidos - programador" -Action $accion -Trigger $disparo -User "SYSTEM" -RunLevel Highest
```

## 6. Tarjeta en el portal central

En central.plasticoscarmen.com › **Administración › Tarjetas del menú** › nueva tarjeta:

| Campo | Valor |
|---|---|
| Nombre | Control de Pedidos |
| Descripción | Visitas, pedidos, despachos y cobranzas |
| URL | `https://pedidos.plasticoscarmen.com` |
| Texto del botón | Abrir |
| Tono | Azul |
| Abrir en pestaña nueva | Sí |

El clic queda en la bitácora del central («Abrió Control de Pedidos») y, dentro del sistema, cada sesión,
pantalla y acción queda en su propia trazabilidad (Administración › Trazabilidad).

## 7. Actualizar

```powershell
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci; npm run build
php artisan migrate --force
php artisan optimize
php artisan up
```

## 8. Respaldo

- Base `control_pedidos` (mysqldump diario). SharePoint sigue siendo la fuente de verdad: si la base se pierde,
  `php artisan sharepoint:sincronizar --completa` reconstruye la operación. Lo que **solo** vive aquí es la
  trazabilidad (sesiones, pantallas, bitácora), la configuración de correos y almacenes y la bandeja de correos.
- Carpeta `storage\app\private\comprobantes` (copia local de los comprobantes; el original queda en SharePoint).
