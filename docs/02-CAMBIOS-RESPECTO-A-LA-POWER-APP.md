# Qué cambió respecto a la Power App

Los módulos son los mismos (Visita, Pedido, Cobranza, Competencia y Detalles/«Tickets»), con las mismas
listas de SharePoint, los mismos roles de `dim_VENDEDORES` y los mismos seis correos. Lo que cambia es
cómo se trabaja y una serie de defectos que se corrigieron. La especificación completa de la Power App
está en `01-ESPECIFICACION-POWERAPP.md` (sección 13: los 35 defectos detectados).

## Interfaz

| Antes | Ahora |
|---|---|
| Pantalla de teléfono fija, también en la PC | Diseño adaptable: barra inferior en el celular, menú lateral en la PC, modo oscuro, instalable como app |
| Almacén, cobranzas y cobrador sin navegación | Menú según capacidades para todos los roles |
| Pantalla inicial fija por rol | **Inicio** con lo pendiente de cada rol: bandeja de despacho, cobros por confirmar, notas vencidas, ventas del mes |
| Búsqueda solo por el comienzo del texto | Búsqueda por palabras, sin acentos («trebol» encuentra «TRÉBOL») |
| — | **Buscador global** (Ctrl + K): pedidos, clientes, notas y productos |
| — | **Ficha del cliente**: saldo, notas vencidas, productos habituales, visitas y competencia |
| — | **Repetir último pedido** y último precio por cliente al cargar productos |
| Lista de pedidos limitada a 90 días | 90 días por omisión, con rango de fechas y filtro por vendedor |
| «Avísales a mano» cuando falla un correo | Bandeja de correos con reintento automático y reenvío manual |
| Correos fijos en el código | Correos y almacenes editables en Administración › Configuración (cambios en bitácora) |

## Trazabilidad (nueva)

La misma lógica de central.plasticoscarmen.com: sesiones con duración, pantallas visitadas con
permanencia, latido del navegador y bitácora de acciones. Además:

- Cada acción se vincula al documento (pedido, nota, cobranza): el **Historial** del pedido muestra quién
  lo cargó, quién numeró cada proforma, quién despachó, anuló, cobró y confirmó, y qué correos se mandaron.
- Panel **por usuario**: tiempo de uso, pantallas, altas, despachos y cobros de cada persona; exportación a Excel.
- Se registran los intentos de ingreso de cuentas no habilitadas.

## Reglas corregidas

| # | Defecto en la Power App | Ahora |
|---|---|---|
| 1 | La ficha mostraba «jabitas por jaba» como micronaje | Muestra el micronaje |
| 2 | El botón «Bandejas» guardaba «Bobinas» | Guarda «Bandejas» |
| 3 | Precio y cantidad en Competencia arrancaban con «Text input» | Campos numéricos vacíos |
| 4 | Se podía cargar el mismo producto dos veces | Se suma a la línea existente |
| 5 | El estado de despacho dependía del valor por omisión de la lista | Se escribe «Sin proforma» al crear |
| 6 | El pedido nunca reflejaba lo cobrado | `PEDIDO_TOTAL_PAGADO`, saldo y estado de pago se calculan con las notas |
| 7 | `LINEA_CANTIDAD_DESPACHADA`, `PROFORMA_TOTAL_DESPACHADO` y `PROFORMA_ESTADO` quedaban en 0 o viejos | Se recalculan y escriben en cada movimiento (Power BI los ve bien) |
| 8 | Notas anuladas contaban como despachadas al reabrir | Solo cuentan notas activas, siempre |
| 9 | N° de nota único solo dentro del pedido | Único en toda la empresa |
| 10 | La nota se creaba aunque fallaran sus datos | Todo o nada |
| 11 | N° de proforma sin control de duplicados | No se repite entre proformas |
| 12 | Se podía cargar dos veces el saldo completo de una nota | El disponible se calcula en el servidor al guardar |
| 13 | Una gestión sin pago arrastraba la forma de pago anterior | Sin pago no hay forma de pago |
| 14 | Las gestiones sin pago bloqueaban la anulación | Solo bloquean los cobros con pago |
| 16 | «Adjuntá el comprobante de la depósito bancario» | «Adjunte el comprobante del depósito bancario» |
| 17 | Confirmaba con el nombre del vendedor atribuido | Con el usuario que confirma |
| 18 | La edición podía dejar la cabecera nueva con las líneas viejas | Todo o nada |
| 19 | Editar no recalculaba el estado de despacho | Se recalcula |
| 23 | El pedido tomaba cliente y forma de pago de la pantalla, no de la visita | Se toman de la visita registrada |
| 24 | La regla del correo a almacén miraba al usuario y no al vendedor atribuido | Mira al vendedor atribuido |
| 26 | Dos pedidos en el mismo segundo podían repetir número | Número único garantizado |
| 33 | La cantidad a despachar se recortaba sin avisar | Se avisa «supera lo pendiente» |
| 34 | Un usuario no registrado entraba como vendedor | No entra: debe estar en `dim_VENDEDORES` |
| — | Dos personas podían despachar la misma proforma a la vez | Se bloquea la proforma mientras se despacha |
| — | Confirmación en dos pasos (podía quedar confirmada sin descontar) | Un solo paso atómico |

Todos los textos están en español formal (usted), sin voseo.

## Decisiones que la empresa debe confirmar

1. **Copia externa de los correos.** La Power App copiaba todos los avisos (con clientes y montos) a
   `samuel.jara@analytico.com.py`. Aquí la copia está **desactivada**; se activa en Configuración si la empresa lo autoriza.
2. **Aviso de anulación a encargados.** Se mantiene como la Power App (avisa a los cinco almacenes). Se puede
   cambiar en Configuración para avisar solo al almacén de la nota.
3. **Cambio de forma de pago en un pedido con notas emitidas.** No se propaga a las notas ya emitidas
   (son documentos del ERP); confirmar si así se prefiere.
