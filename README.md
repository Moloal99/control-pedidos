# Control de Pedidos · Plásticos Carmen

Reemplazo en Laravel de la Power App **«Tomador de pedidos – Extrusión»**: visitas, pedidos con una
proforma por almacén, despachos con nota de venta, anulaciones, cobranzas con confirmación de caja y
relevamiento de competencia. SharePoint sigue siendo la fuente de verdad; se suma la **trazabilidad por
usuario** del portal central.plasticoscarmen.com.

**Stack:** Laravel 13 · PHP 8.4 · Livewire 4 (con Alpine) · Tailwind CSS 4 · MariaDB (SQLite en desarrollo) ·
Microsoft Graph (listas, correo e inicio de sesión) · PHPUnit 12.

## Arranque rápido (modo demostración)

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed      # almacenes + datos de demostración de 45 días
npm run build
php artisan serve --port=8020
```

Entre a <http://localhost:8020>: en modo demostración la pantalla de acceso permite elegir un usuario de
cada rol (administrador, vendedor, vendedor que despacha, supervisor, almacén, cobranzas, cobrador).
Nada sale del equipo: no hay SharePoint ni correo real (los correos se escriben en `storage/logs`).

```powershell
php artisan test                # 39 pruebas: flujo comercial, permisos, espejo SharePoint, correos
```

## Módulos

| Pantalla | Quién | Qué hace |
|---|---|---|
| Inicio | Todos | Lo pendiente de cada rol, indicadores, tendencia y actividad |
| Visita | Vendedor, supervisor, admin | Registra visita o llamada; «cargar a nombre de» para supervisores |
| Pedido | Vendedor, supervisor, admin | Carrito agrupado por almacén, repetir último pedido, último precio |
| Cobranza | Vendedor, cobrador, admin | Cobro con comprobante (foto desde el celular) o gestión sin pago |
| Competencia | Vendedor, admin | Precios de la competencia por cliente |
| Pedidos | Según rol | Bandeja por estado; detalle con proformas, despacho, notas, anulación, edición e historial |
| Cobranzas por confirmar | Cobranzas, admin | Revisión del comprobante, confirmación con N° del ERP y corrección |
| Ficha de cliente | Todos | Saldo, vencidos, productos habituales, visitas, competencia |
| Administración | Admin | Trazabilidad por usuario, usuarios, configuración de correos y almacenes, estado de Microsoft 365 |

Los permisos replican exactamente las capacidades de la Power App (`App\Models\User`):
`veTodosLosPedidos`, `puedeDespachar` (almacén, admin o vendedor con `VENDEDOR_DESPACHA` sobre lo suyo),
`puedeEditar`, `puedeConfirmarCobranzas`, etc.

## Arquitectura

```
app/
├─ SharePoint/      Contrato, conector Graph, modo demo, unidad de trabajo con compensación, sincronizador
├─ Servicios/       Una clase por operación de negocio (GuardarPedido, CrearNotaVenta, ConfirmarCobranza…)
├─ Correo/          Los seis avisos, transporte Graph y bandeja con reintentos
├─ Livewire/        Pantallas
├─ Support/         Rastro (trazabilidad), Trazas (métricas), Almacenes, VentaEnCurso, Formato
└─ Models/          Espejo local de las 11 listas + trazabilidad
```

- **Escritura:** cada operación corre en `Espejo::operacion()`: base local en una transacción y SharePoint
  elemento por elemento; si algo falla, se revierte la base y se compensa lo escrito en SharePoint.
- **Lectura:** las pantallas leen la base local (rápido, sin límites de delegación). El programador trae los
  cambios de SharePoint cada minuto, así conviven la Power App y Power BI durante la transición.
- **Columnas:** se nombran como en la Power App; el conector las traduce a nombres internos leyendo la lista.

## Documentación

| Archivo | Contenido |
|---|---|
| `docs/01-ESPECIFICACION-POWERAPP.md` | Relevamiento completo de la Power App (roles, listas, flujos, correos, 35 defectos) |
| `docs/02-CAMBIOS-RESPECTO-A-LA-POWER-APP.md` | Qué mejoró y qué decisiones debe confirmar la empresa |
| `docs/03-GUIA-ENTRA-ID.md` | Registrar la aplicación en Entra ID y conectar SharePoint y el correo |
| `docs/04-DESPLIEGUE.md` | Servidor Windows con IIS, MariaDB, tarea programada y tarjeta en el portal central |
| `docs/powerapps/` | Código fuente exportado de la Power App (YAML de cada pantalla) |

## Comandos

| Comando | Para qué |
|---|---|
| `php artisan sharepoint:sincronizar` | Trae los cambios recientes de las listas |
| `php artisan sharepoint:sincronizar --completa` | Relee todo y detecta borrados |
| `php artisan sharepoint:sincronizar --lista=fact_PEDIDOS` | Una sola lista |
| `php artisan trazabilidad:vencer` | Cierra sesiones inactivas |
| `php artisan schedule:run` | Lo ejecuta la tarea programada cada minuto |
