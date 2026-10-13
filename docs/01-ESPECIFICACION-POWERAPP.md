# Especificación funcional — "Tomador de pedidos – Extrusión"

**Empresa:** Plásticos Carmen S.R.L. (Bolivia)
**Origen:** aplicación canvas de Microsoft Power Apps sobre listas de SharePoint y Office 365 Outlook
**Destino:** reimplementación en Laravel 13
**Fuentes analizadas:** `App_OnStart.txt`, `PantallaVisita_1.yaml`, `PantallaPedido_1.yaml`, `PantallaCobranza_1.yaml`, `PantallaCompetencia_1.yaml`, `PantallaDetalles.yaml` (y los `.ext.txt` como índice). El YAML es la referencia autorizada.
**Moneda:** bolivianos (Bs). Formato numérico de pantalla y correo: `#,##0.00`. Fechas: `dd/mm/yyyy` y `dd/mm/yyyy hh:mm` (24 h).

> Convenciones de este documento
> - Los mensajes al usuario se citan **literalmente** (incluido el voseo original: "Elegí", "Escribí", "Tenés"). Se recomienda conservarlos tal cual para no alterar la experiencia de los usuarios.
> - "Usuario real" = la persona que tiene la sesión iniciada (`gUsrCod`/`gUsrNom`). "Vendedor atribuido" = a quien se le carga la venta (`gVendSel`/`gVendCod`/`gVendNom`).
> - Las notificaciones de Power Apps (`Notify`) equivalen a mensajes flash/toast: **Error** (rojo), **Warning** (ámbar), **Success** (verde).
> - "Ticket" es el nombre que la propia app usa para un pedido en la pantalla de Detalles (el correo de cobranzas dice "Entrá a Tickets › Cobranzas").

---

## Índice

1. Visión general y mapa de pantallas
2. Identidad, roles y capacidades
3. Esquema de datos inferido (todas las listas)
4. Formatos de números y claves
5. Máquinas de estado
6. Pantalla Visita
7. Pantalla Pedido
8. Pantalla Cobranza
9. Pantalla Competencia
10. Pantalla Detalles ("Tickets"): pestañas Pedidos y Cobranzas
11. Flujos de negocio de punta a punta
12. Correos electrónicos
13. Defectos, rarezas y problemas de delegación detectados
14. Recomendaciones para la reimplementación en Laravel

---

## 1. Visión general y mapa de pantallas

La app la usan vendedores en campo, almacenes, caja/cobranzas y cobradores para:

1. Registrar una **visita** o llamada a un cliente (motivo Rutina, Cobranza o Pedido).
2. Cargar un **pedido** (cabecera + líneas). Al guardar, el sistema crea automáticamente **una proforma por almacén** de origen de los productos.
3. El almacén (o el vendedor habilitado) **numera las proformas** (número manual tomado del ERP) y **despacha** parcial o totalmente creando **notas de venta** (número manual + comprobante adjunto obligatorio).
4. Las notas de venta pueden **anularse** con motivo (las cantidades vuelven a quedar pendientes).
5. Sobre las notas de venta con saldo se registran **cobranzas** (con o sin pago). Cada cobranza con pago queda "Pendiente de confirmación" hasta que el rol **cobranzas** la confirma con el número de nota de cobranza del ERP; recién ahí se descuenta del saldo de la nota.
6. Los pedidos pueden **editarse** (forma de pago, días, productos) mientras sus proformas no tengan notas activas; se envía un correo de "Proforma modificada" con comparación antes/después.
7. Relevamiento de **precios de la competencia**.

### Pantallas

| Pantalla | Propósito | Quién llega |
|---|---|---|
| `PantallaVisita_1` | Registrar visita/llamada; elegir "cargar a nombre de" | Pantalla inicial de admin y vendedor |
| `PantallaPedido_1` | Cargar productos y guardar el pedido | Desde la barra inferior |
| `PantallaCobranza_1` | Registrar cobro (o visita sin pago) sobre una nota de venta | Pantalla inicial del rol **cobrador**; barra inferior |
| `PantallaCompetencia_1` | Relevamiento de competencia | Barra inferior |
| `PantallaDetalles` | "Tickets": lista de pedidos (proformas, despacho, notas, edición) y de cobranzas (confirmación) | Pantalla inicial de **almacen** y **cobranzas**; resto vía encabezado |

### Pantalla inicial (StartScreen)

Se determina por `dim_VENDEDORES.VENDEDOR_ROL` del registro cuyo `VENDEDOR_EMAIL` (en minúsculas) coincide con el correo del usuario:

| Rol | Pantalla inicial |
|---|---|
| `almacen` | PantallaDetalles |
| `cobranzas` | PantallaDetalles |
| `cobrador` | PantallaCobranza_1 |
| cualquier otro (`admin`, `user`, vacío, usuario no registrado) | PantallaVisita_1 |

### Componentes compartidos

- **`cmpEncabezado`** (barra superior azul, 56 px): presente en todas las pantallas. Su definición no está en los archivos exportados; por el texto de los correos y por la lógica de roles se infiere que muestra el usuario (iniciales `gIniciales` = primera letra de las dos primeras palabras de `User().FullName`) y da acceso a la pantalla Detalles ("Tickets"). **Debe confirmarse con el equipo** al reimplementar.
- **`cmpPantalla`** (barra de navegación inferior): 4 botones → Visita, Pedido, Cobranza, Competencia. **Visible solo si `gVeOtrasPantallas`** (admin o vendedor). Por eso almacén, cobranzas y cobrador quedan "encerrados" en su pantalla inicial.

---

## 2. Identidad, roles y capacidades

### 2.1 Carga de identidad (App.OnStart)

```
gUsuario       = User().FullName
gUsuarioEmail  = Lower(User().Email)
gVendedor      = primer registro de dim_VENDEDORES con Lower(VENDEDOR_EMAIL) = gUsuarioEmail
gVendedorOk    = gVendedor no vacío
```

**Identidad fija (usuario real, nunca cambia):**
- `gUsrCod = gVendedor.VENDEDOR_CODIGO`
- `gUsrNom = gVendedor.VENDEDOR_NOMBRE`

**Identidad atribuida (a quién se carga la venta), arranca igual que la real:**
- `gVendSel = gVendedor`, `gVendCod = gUsrCod`, `gVendNom = gUsrNom`

### 2.2 Rol

`gRol = Lower(Text(gVendedor.VENDEDOR_ROL.Value))` (columna de opción).

| Variable | Regla exacta |
|---|---|
| `gEsAdmin` | `gRol = "admin"` |
| `gEsAlmacen` | `gRol = "almacen"` |
| `gEsCobranzas` | `gRol = "cobranzas"` |
| `gEsCobrador` | `gRol = "cobrador"` |
| `gEsVendedor` | ninguno de los cuatro anteriores (incluye `"user"`, rol vacío y usuario no registrado) |

### 2.3 Capacidades globales (los controles usan estas, nunca el rol directamente)

| Capacidad | Regla exacta | Uso |
|---|---|---|
| `gPuedeElegirVendedor` (supervisor) | `gVendedor.VENDEDOR_CARGA_POR_OTROS = true` | Combo "CARGAR A NOMBRE DE" en Visita. **No** incluye al admin a propósito. |
| `gVeTabPedidos` | `gEsAdmin Or gEsAlmacen Or gEsVendedor` | Pestaña Pedidos en Detalles; tarjeta de despacho |
| `gVeTabCobranzas` | `gEsAdmin Or gEsCobranzas` | Pestaña Cobranzas en Detalles |
| `gVeTodosLosPedidos` | `gEsAdmin Or gEsAlmacen Or gEsCobranzas Or gPuedeElegirVendedor` | Lista "TODOS LOS PEDIDOS" vs "MIS PEDIDOS"; botón "Sin proforma" |
| `gVeTodasLasCobranzas` | `gEsAdmin Or gEsCobranzas` | Título de la lista, columna vendedor, botón "Pendientes" |
| `gVeTodasLasNotas` | `gEsAdmin Or gEsCobrador Or gPuedeElegirVendedor` | En Cobranza: ver notas de todos los vendedores |
| `gVeOtrasPantallas` | `gEsAdmin Or gEsVendedor` | Barra de navegación inferior |
| `gVendedorDespacha` | `gVendedor.VENDEDOR_DESPACHA = true` | Vendedor que numera proformas, despacha y anula **solo sus propios pedidos**; además, el correo de pedido nuevo no se envía al rol almacén |
| `gPuedeProformar` | `gEsAdmin Or gEsAlmacen Or gVendedorDespacha` | (definida, **no usada** en pantallas) |
| `gPuedeDespachar` | `gEsAdmin Or gEsAlmacen Or gVendedorDespacha` | Visibilidad del formulario de adjunto de la nota (`frmNota`) |
| `gPuedeEditarPedido` | `gEsAdmin Or gEsAlmacen Or gEsVendedor` | (definida, **no usada**; se usa la versión por ticket) |
| `gPuedeConfirmar` | `gEsAdmin Or gEsCobranzas` | Confirmar cobranzas, corregir N° de cobranza, botón "Pendientes" |

### 2.4 Capacidades por ticket (recalculadas al seleccionar un pedido en Detalles)

Se comparan **siempre contra la identidad real** (`gUsrCod`/`gUsrNom`), nunca contra la atribuida:

| Variable | Regla |
|---|---|
| `gTicketEsMio` | `pedido.VENDEDOR_CODIGO = gUsrCod` |
| `gTicketCargadoPorMi` | `Coalesce(pedido.CARGADO_POR, "") = gUsrNom` |
| `gProformarTicket` | `gEsAdmin Or gEsAlmacen Or (gVendedorDespacha And gTicketEsMio)` |
| `gDespacharTicket` | `gEsAdmin Or gEsAlmacen Or (gVendedorDespacha And gTicketEsMio)` |
| `gEditarTicket` | `gEsAdmin Or gEsAlmacen Or gTicketEsMio Or gTicketCargadoPorMi` |

Al entrar a Detalles (OnVisible) todas valen `false` hasta que se selecciona un pedido.

### 2.5 "Cargar a nombre de" (supervisor)

- Solo visible si `gPuedeElegirVendedor`.
- Opciones (`colVendedoresCarga`, cargada en OnStart): `dim_VENDEDORES` con `Lower(Text(VENDEDOR_ROL.Value)) = "user"` **y** `Not(Coalesce(VENDEDOR_CARGA_POR_OTROS, false))`, ordenado por `VENDEDOR_NOMBRE`. Es decir: solo vendedores comunes, nunca otros supervisores, admins, almacén, etc.
- Al elegir un vendedor: `gVendSel = seleccionado`, `gVendCod = su código`, `gVendNom = su nombre`. Al vaciar el combo: vuelve a la identidad real.
- Al pulsar "Nueva visita" la atribución se reinicia a la identidad real (para que la próxima visita no herede la selección).
- Efectos de la atribución:
  - Visita, Pedido y Proformas se graban con `VENDEDOR_CODIGO/NOMBRE` y `dim_VENDEDORES_ID` del vendedor atribuido.
  - `CARGADO_POR` = `gUsrNom` si `gVendCod <> gUsrCod`, si no cadena vacía.
  - En Pedido aparece el aviso "⚠  Estás cargando a nombre de {gVendNom}".
  - Notificación de visita: "Visita registrada a nombre de {gVendNom} — ya podés cargar pedido".
  - El correo de pedido nuevo va al vendedor atribuido **y** al usuario real.
  - El relevamiento de competencia también usa `gVendCod/gVendNom` (ver defectos).
- En Detalles, el supervisor ve todos los pedidos (`gVeTodosLosPedidos`), pero solo puede **editar** los propios o los que él cargó (`CARGADO_POR = su nombre`). No puede proformar ni despachar salvo que también sea admin/almacén o tenga `VENDEDOR_DESPACHA` y el pedido sea suyo.

### 2.6 VENDEDOR_DESPACHA

Columna Sí/No en `dim_VENDEDORES`. Un vendedor con este indicador:
- Puede escribir el número de proforma, crear notas de venta y anular notas **solo en pedidos cuyo `VENDEDOR_CODIGO` sea el suyo** (identidad real).
- Al guardar un pedido, **el correo de "Nuevo pedido" NO se envía a la casilla del rol almacén** (`gMailAlmPedido = ""`), porque él mismo despacha. Nota: la regla se evalúa sobre el **usuario real**, no sobre el vendedor atribuido.
- OnStart construye `colVendDespachan` (códigos de vendedores que despachan) "para decidir a quién NO se le manda el correo", pero **esa colección no se usa en ninguna pantalla**.

### 2.7 Matriz resumida por rol

| Función | admin | almacen | cobranzas | cobrador | vendedor (user) | supervisor (user + CARGA_POR_OTROS) | vendedor + DESPACHA |
|---|---|---|---|---|---|---|---|
| Pantalla inicial | Visita | Detalles | Detalles | Cobranza | Visita | Visita | Visita |
| Barra inferior (Visita/Pedido/Cobranza/Competencia) | Sí | No | No | No | Sí | Sí | Sí |
| Cargar a nombre de otro | Solo si tiene el flag | — | — | — | No | Sí | según flag |
| Pestaña Pedidos | Sí | Sí | No | No* | Sí | Sí | Sí |
| Ver todos los pedidos | Sí | Sí | (Sí, sin pestaña) | — | No (solo propios) | Sí | No |
| Numerar proforma / despachar / anular nota | Todos | Todos | No | No | No | No | Solo propios |
| Editar pedido | Todos | Todos | No | No | Propios | Propios + cargados por él | Propios |
| Pestaña Cobranzas | Sí | No | Sí | No | No | No | No |
| Confirmar cobranza / corregir N° | Sí | No | Sí | No | No | No | No |
| Ver todas las notas en Cobranza | Sí | — | — | Sí | Solo propias | Sí | Solo propias |

\* El cobrador no tiene navegación a Detalles por barra inferior; si el encabezado la ofreciera, no vería ninguna pestaña.

---

## 3. Esquema de datos inferido

Todas las listas tienen además las columnas de sistema de SharePoint (`ID` autonumérico, `Title`, `Created`, `Modified`, `Author`, `Editor`). Las columnas de **búsqueda (lookup)** se escriben con `{Id, Value}`. Las de **opción (choice)** se escriben con `{Value: "..."}`. Abreviaturas de pantallas: VIS = Visita, PED = Pedido, COB = Cobranza, COM = Competencia, DET = Detalles, ON = App.OnStart. L = lee, E = escribe.

### 3.1 `dim_CLIENTES` (maestro, solo lectura)

| Columna | Tipo | Notas | Uso |
|---|---|---|---|
| ID | número | | L: VIS, PED, COM, DET |
| CLIENTE_CODIGO | texto | Clave de negocio. Se busca por `Trim(texto) = CLIENTE_CODIGO`. | L: VIS, PED, COM, DET |
| CLIENTE_NOMBRE | texto | | L: VIS, PED, COM, DET |
| CLIENTE_DIRECCION | texto | El valor `"0"` se trata como vacío en el correo de pedido nuevo | L: PED (correo), DET (correo de modificación) |
| CLIENTE_TELEFONO | texto | Ídem `"0"` = vacío | L: PED, DET |

### 3.2 `dim_PRODUCTOS` (maestro, solo lectura)

| Columna | Tipo | Notas |
|---|---|---|
| ID | número | Lookup desde `fact_PEDIDO_LINEAS.dim_PRODUCTOS_ID` |
| PRODUCTO_TERMINADO_CODIGO | texto | El **prefijo** define el almacén (ver 4.1) |
| PRODUCTO_DESCRIPCION | texto | |
| PRODUCTO_UNIDAD | texto | Ej. `PZA` (piezas → solo enteros), otras (KG, etc.). Se compara con `Upper(Trim())` en PED/DET-edición; en despacho se compara sin normalizar (`= "PZA"`) |
| PRODUCTO_MICRONAJE | número/texto | |
| PRODUCTO_PESO_PROMEDIO | número | kg por unidad de venta; base del cálculo de kg |
| PRODUCTO_BOLSAS_POR_PAQUETE | número | |
| PRODUCTO_PAQUETES_POR_JABA | número | |
| PRODUCTO_JABITAS_POR_JABA | número | |
| PRODUCTO_JABAS_POR_BULTO | número | |
| PRODUCTO_PESO_POR_BOLSA | número | |
| PRODUCTO_PESO_POR_BULTO | número | |
| PRODUCTO_PESO_POR_PAQUETE | número | |
| PRODUCTO_PESO_POR_JABA | número | |

Uso: L en PED (combo y ficha), DET (edición: combo y LookUp por código), COM (`Distinct(PRODUCTO_UNIDAD)` para el desplegable de unidades).

### 3.3 `dim_VENDEDORES` (maestro de usuarios; solo lectura)

| Columna | Tipo | Notas |
|---|---|---|
| ID | número | Lookup desde VISITAS/PEDIDOS `dim_VENDEDORES_ID` |
| VENDEDOR_CODIGO | texto | Se usa en `PEDIDO_NUMERO`, filtros "mis pedidos/notas" |
| VENDEDOR_NOMBRE | texto | Se compara con `CARGADO_POR` |
| VENDEDOR_EMAIL | texto | Identifica al usuario (comparación en minúsculas); destinatario de correos |
| VENDEDOR_ROL | opción | Valores: `admin`, `almacen`, `cobranzas`, `cobrador`, `user` (se normaliza a minúsculas) |
| VENDEDOR_CARGA_POR_OTROS | Sí/No | Supervisor |
| VENDEDOR_DESPACHA | Sí/No | Vendedor que despacha sus pedidos |

Uso: ON (identidad, `colVendDespachan`, `colVendedoresCarga`), DET (LookUp de e-mail del vendedor para correos de anulación, confirmación, corrección y modificación), PED (`gVendSel.VENDEDOR_EMAIL`).

### 3.4 `fact_VISITAS`

| Columna | Tipo | Valor escrito (VIS) |
|---|---|---|
| VISITA_TITULO | texto (probablemente `Title` renombrado o columna aparte) | `CLIENTE_NOMBRE & " - " & dd/mm/yyyy hh:mm` |
| dim_CLIENTES_ID | lookup → dim_CLIENTES | `{Id: cliente.ID, Value: cliente.CLIENTE_NOMBRE}` |
| CLIENTE_CODIGO | texto | |
| CLIENTE_NOMBRE | texto | |
| dim_VENDEDORES_ID | lookup → dim_VENDEDORES | vendedor atribuido |
| VENDEDOR_CODIGO | texto | `gVendCod` |
| VENDEDOR_NOMBRE | texto | `gVendNom` |
| CARGADO_POR | texto | `gUsrNom` si carga por otro; si no `""` |
| VISITA_MOTIVO | opción | `Rutina`, `Cobranza`, `Pedido` |
| VISITA_TIPO_CONTACTO | opción | `Llamada`, `Visita` |
| VISITA_FORMA_PAGO | opción | `Crédito`, `Contado`; solo si motivo = Pedido; si no, vacío |
| VISITA_DIAS_CREDITO | número | Solo si Crédito; si no, vacío |
| VISITA_COMENTARIO | texto multilínea | `Trim(comentario)` |
| VISITA_FECHA | fecha/hora | `Now()` |

Escribe solo VIS (alta). Lee: ninguna pantalla vuelve a leerla (solo se referencia por lookup desde el pedido).

### 3.5 `fact_PEDIDOS` (cabecera)

| Columna | Tipo | Escritura | Lectura |
|---|---|---|---|
| PEDIDO_NUMERO | texto, único de hecho | PED (alta) | PED, DET |
| fact_VISITAS_ID | lookup → fact_VISITAS | PED: `{Id: visita.ID, Value: VISITA_TITULO}` | — |
| dim_CLIENTES_ID | lookup → dim_CLIENTES | PED | DET (se copia a la nota) |
| CLIENTE_CODIGO, CLIENTE_NOMBRE | texto | PED | PED (último pedido), DET |
| dim_VENDEDORES_ID | lookup → dim_VENDEDORES | PED (atribuido) | — |
| VENDEDOR_CODIGO, VENDEDOR_NOMBRE | texto | PED | DET (filtro, permisos) |
| CARGADO_POR | texto | PED | DET (`gTicketCargadoPorMi`) |
| PEDIDO_TIPO_CONTACTO | opción (`Llamada`/`Visita`) | PED | — |
| PEDIDO_FORMA_PAGO | opción (`Crédito`/`Contado`) | PED, DET (edición) | DET, copia a nota |
| PEDIDO_DIAS_CREDITO | número | PED (vacío si contado), DET (0 si contado) | DET, copia a nota |
| PEDIDO_FECHA | fecha/hora | PED `Now()` | DET (orden, ventana 90 días) |
| PEDIDO_FECHA_VENCIMIENTO | fecha/hora | PED: `Now()+días` si Crédito, si no `Now()`; DET edición: `PEDIDO_FECHA + días` | DET, copia a nota |
| PEDIDO_TOTAL | número | PED; DET edición | DET |
| PEDIDO_TOTAL_PAGADO | número | PED `0` (nunca más se actualiza) | DET |
| PEDIDO_SALDO_PENDIENTE | número | PED `= total`; DET edición `total − pagado` | DET |
| PEDIDO_ESTADO_PAGO | opción `Pendiente`/`Parcial`/`Pagado` | PED `Pendiente`; DET edición | DET (etiqueta de color) |
| PEDIDO_ESTADO_DESPACHO | opción `Sin proforma`/`Proformado`/`Despachado parcial`/`Despachado total` | **No se escribe al crear** (se presume valor por defecto `Sin proforma` en SharePoint); DET al numerar proforma, crear nota y anular nota | DET (filtro y etiqueta) |
| PEDIDO_MODIFICADO_POR | texto | DET edición (`gUsuario`, nombre completo de Office) | DET |
| PEDIDO_FECHA_MODIFICACION | fecha/hora | DET edición | DET |
| PEDIDO_PROFORMA_NUMERO | texto | **Columna heredada**: solo la lee un control oculto (`Visible: false`) | — |

### 3.6 `fact_PEDIDO_LINEAS`

| Columna | Tipo | Escritura | Notas |
|---|---|---|---|
| PEDIDO_NUMERO | texto | PED, DET | Vincula con la cabecera (no hay lookup) |
| PRODUCTO_DESCRIPCION | texto | PED, DET | |
| dim_PRODUCTOS_ID | lookup → dim_PRODUCTOS | PED, DET | |
| PRODUCTO_TERMINADO_CODIGO | texto | PED, DET | Clave para calcular despachado |
| LINEA_ALMACEN | texto | PED, DET | Nombre largo del almacén (4.1) |
| PROFORMA_CLAVE | texto | PED, DET | `PEDIDO_NUMERO-Pxx` |
| LINEA_CANTIDAD | número | PED; DET edición | |
| LINEA_PRECIO_UNITARIO | número | PED; DET edición | |
| LINEA_CANTIDAD_DESPACHADA | número | Siempre `0`; **nunca se actualiza** | Columna muerta |
| PRODUCTO_UNIDAD, PRODUCTO_MICRONAJE, PRODUCTO_PESO_PROMEDIO, PRODUCTO_BOLSAS_POR_PAQUETE, PRODUCTO_PAQUETES_POR_JABA, PRODUCTO_JABITAS_POR_JABA, PRODUCTO_JABAS_POR_BULTO, PRODUCTO_PESO_POR_BOLSA, PRODUCTO_PESO_POR_BULTO, PRODUCTO_PESO_POR_PAQUETE, PRODUCTO_PESO_POR_JABA | texto/número | PED, DET | Copia ("foto") del producto al momento de la venta |

Borrado: PED (rollback), DET (edición: líneas eliminadas).

### 3.7 `fact_PROFORMAS`

| Columna | Tipo | Escritura | Notas |
|---|---|---|---|
| PROFORMA_TITULO | texto | PED, DET | `= PROFORMA_CLAVE` |
| PROFORMA_CLAVE | texto | PED, DET | `PEDIDO_NUMERO-Pxx` |
| PEDIDO_NUMERO | texto | PED, DET | |
| dim_CLIENTES_ID | lookup → dim_CLIENTES | PED, DET | |
| CLIENTE_CODIGO, CLIENTE_NOMBRE | texto | PED, DET | |
| VENDEDOR_CODIGO, VENDEDOR_NOMBRE | texto | PED, DET | (no tiene lookup de vendedor) |
| PROFORMA_ALMACEN | texto | PED, DET | Nombre largo del almacén |
| PROFORMA_FECHA | fecha/hora | PED, DET | `Now()` |
| PROFORMA_TOTAL | número | PED; DET edición (recalcula) | Σ cantidad × precio de sus líneas |
| PROFORMA_TOTAL_DESPACHADO | número | Siempre `0`; **nunca se actualiza** | Columna muerta |
| PROFORMA_ESTADO | opción `Sin número`/`Pendiente`/`Parcial`/`Despachada` | PED/DET `Sin número`; DET al guardar el número | Solo se actualiza al guardar el número (ver defectos) |
| PROFORMA_NUMERO | texto | DET (manual) | Número de proforma del ERP |

Borrado: PED (rollback), DET (edición: proformas sin número que quedaron sin líneas).

### 3.8 `fact_NOTAS_VENTA`

| Columna | Tipo | Valor escrito (DET, al crear) |
|---|---|---|
| Title | texto | número de nota |
| NOTA_NUMERO | texto | Manual (`Trim`), ej. `00027520` |
| PROFORMA_CLAVE | texto | de la proforma seleccionada |
| PROFORMA_NUMERO | texto | de la proforma |
| PEDIDO_NUMERO | texto | del pedido |
| dim_CLIENTES_ID | lookup → dim_CLIENTES | copiado del pedido |
| CLIENTE_CODIGO, CLIENTE_NOMBRE | texto | del pedido |
| VENDEDOR_CODIGO, VENDEDOR_NOMBRE | texto | del pedido |
| NOTA_ALMACEN | texto | `PROFORMA_ALMACEN` |
| NOTA_FECHA | fecha/hora | `Now()` |
| NOTA_TOTAL | número | Σ (a despachar × precio unitario) |
| NOTA_TOTAL_PAGADO | número | `0`; luego DET al confirmar cobranza |
| NOTA_SALDO_PENDIENTE | número | `= NOTA_TOTAL`; luego DET al confirmar |
| NOTA_ESTADO_PAGO | opción `Pendiente`/`Parcial`/`Pagado` | `Pendiente`; luego DET al confirmar |
| NOTA_FORMA_PAGO | opción (`Crédito`/`Contado`) | `= PEDIDO_FORMA_PAGO` |
| NOTA_DIAS_CREDITO | número | `= PEDIDO_DIAS_CREDITO` |
| NOTA_FECHA_VENCIMIENTO | fecha/hora | `= PEDIDO_FECHA_VENCIMIENTO` |
| NOTA_ANULADA | Sí/No | `false`; DET al anular `true` |
| NOTA_ANULADA_MOTIVO | texto multilínea | DET al anular |
| NOTA_ANULADA_POR | texto | `gUsuario` |
| NOTA_ANULADA_FECHA | fecha/hora | `Now()` |
| Datos adjuntos (Attachments) | adjuntos | Comprobante de la nota, **obligatorio**, máximo 1 archivo, 50 MB |

Lectura: COB (lista de notas con saldo), DET. Borrado: DET (rollback de creación fallida).

### 3.9 `fact_NOTA_LINEAS`

| Columna | Tipo | Valor |
|---|---|---|
| Title | texto | número de nota |
| NOTA_NUMERO | texto | vincula con la nota (no hay lookup) |
| PROFORMA_CLAVE | texto | vincula con proforma/pedido (`StartsWith(PROFORMA_CLAVE, PEDIDO_NUMERO)`) |
| PRODUCTO_TERMINADO_CODIGO | texto | |
| PRODUCTO_DESCRIPCION | texto | |
| PRODUCTO_UNIDAD | texto | |
| LINEA_ALMACEN | texto | |
| LINEA_CANTIDAD | número | cantidad despachada en esta nota |
| LINEA_PRECIO_UNITARIO | número | precio de la línea del pedido |
| PRODUCTO_PESO_PROMEDIO | número | |

No guarda `PEDIDO_NUMERO` ni ID de la línea del pedido. Escritura/borrado: DET. Lectura: COB (detalle de la nota), DET. **Al anular una nota sus líneas NO se borran**; se ignoran por estar la nota anulada.

### 3.10 `fact_COBRANZAS`

| Columna | Tipo | Escritura |
|---|---|---|
| COBRANZA_TITULO | texto | COB: `NOTA_NUMERO` |
| NOTA_NUMERO, PROFORMA_CLAVE, PEDIDO_NUMERO | texto | COB, copiados de la nota |
| dim_CLIENTES_ID | lookup → dim_CLIENTES | COB, de la nota |
| CLIENTE_CODIGO, CLIENTE_NOMBRE | texto | COB |
| VENDEDOR_CODIGO, VENDEDOR_NOMBRE | texto | COB, **vendedor de la nota** (no quien cobra) |
| CARGADO_POR | texto | COB: `gUsrNom` si el vendedor de la nota ≠ usuario real; si no `""` |
| COBRANZA_FECHA | fecha/hora | COB `Now()` |
| COBRANZA_HUBO_PAGO | Sí/No | COB |
| COBRANZA_TIPO_PAGO | opción `Completo`/`Parcial`/`Anticipo` (vacío si no hubo pago) | COB |
| COBRANZA_MONTO | número | COB (0 si no hubo pago) |
| COBRANZA_FORMA_PAGO | opción `Efectivo`/`Transferencia`/`Depósito bancario` | COB |
| COBRANZA_FECHA_PROXIMO_PAGO | fecha | COB (obligatoria si no hubo pago; opcional en Parcial/Anticipo) |
| NOTA_TOTAL | número | COB (foto) |
| COBRANZA_SALDO_ANTES | número | COB: saldo de la nota al registrar |
| COBRANZA_SALDO_DESPUES | número | COB: saldo antes − monto (proyectado) |
| COBRANZA_ESTADO | opción `Pendiente de confirmación`/`Confirmada` | COB: `Pendiente de confirmación`; DET: `Confirmada` |
| COBRANZA_NUMERO | texto, único | DET al confirmar (N° de nota de cobranza del ERP, ej. `B00012345` o `V00012345`) |
| COBRANZA_CONFIRMADA_POR | texto | DET: `gVendNom` |
| COBRANZA_FECHA_CONFIRMACION | fecha/hora | DET `Now()` |
| COBRANZA_NUMERO_ANTERIOR | texto | DET al corregir número |
| COBRANZA_NUMERO_MOTIVO | texto | DET al corregir número |
| COBRANZA_NUMERO_MODIF_POR | texto | DET `gUsuario` |
| COBRANZA_FECHA_MODIF_NUMERO | fecha/hora | DET `Now()` |
| Datos adjuntos | adjuntos | COB: comprobante (obligatorio salvo efectivo) |

Borrado: COB (rollback de alta incompleta); DET tiene un botón de eliminar **oculto** (`Visible: false`).

### 3.11 `fact_COMPETENCIA`

| Columna | Tipo | Valor (COM) |
|---|---|---|
| Title | texto | `REL-{gVendCod}-{yyyymmdd-hhmmss}` |
| RELEVAMIENTO_FECHA | fecha/hora | `Now()` |
| VENDEDOR_CODIGO, VENDEDOR_NOMBRE | texto | `gVendCod`, `gVendNom` |
| dim_CLIENTES_ID | lookup → dim_CLIENTES | cliente elegido |
| CLIENTE_CODIGO, CLIENTE_NOMBRE | texto | |
| COMPETIDOR | texto | normalizado: primera letra mayúscula, resto minúsculas |
| COMP_TIPO | opción `Bolsas`/`Bobinas`/`Bandejas`*/`Vasos`/`Rafia`/`Otro` | *ver defecto: "Bandejas" graba "Bobinas" |
| COMP_DESCRIPCION | texto | |
| COMP_MEDIDA | texto | opcional, ej. `90 X 110` |
| COMP_UNIDAD | texto | de las unidades distintas de `dim_PRODUCTOS` |
| COMP_PRECIO | número | |
| COMP_CANTIDAD | número | |
| COMP_OBSERVACION | texto multilínea | opcional |

Lectura y borrado: COM.

### 3.12 Diagrama de relaciones (lógico)

```
dim_CLIENTES 1─* fact_VISITAS 1─* fact_PEDIDOS 1─* fact_PEDIDO_LINEAS
dim_VENDEDORES 1─* fact_VISITAS / fact_PEDIDOS
fact_PEDIDOS 1─* fact_PROFORMAS (por PEDIDO_NUMERO; 1 por almacén)
fact_PROFORMAS 1─* fact_PEDIDO_LINEAS (por PROFORMA_CLAVE)
fact_PROFORMAS 1─* fact_NOTAS_VENTA (por PROFORMA_CLAVE)
fact_NOTAS_VENTA 1─* fact_NOTA_LINEAS (por NOTA_NUMERO)
fact_NOTAS_VENTA 1─* fact_COBRANZAS (por NOTA_NUMERO)
dim_CLIENTES 1─* fact_COMPETENCIA
```

Salvo los lookups indicados, todas las relaciones son por **texto** (números/claves de negocio), sin integridad referencial.

---

## 4. Formatos de números y claves

| Clave | Construcción | Ejemplo |
|---|---|---|
| `PEDIDO_NUMERO` | `"PED-" & gVendCod & "-" & Text(Now(), "yyyymmdd-hhmmss")` (vendedor **atribuido**, hora local 24 h) | `PED-V07-20250314-153012` |
| `PROFORMA_CLAVE` | `PEDIDO_NUMERO & "-" & código de almacén` (P11…P15 o `SIN`) | `PED-V07-20250314-153012-P11` |
| `PROFORMA_TITULO` | igual a `PROFORMA_CLAVE` | |
| `PROFORMA_NUMERO` | **manual** (texto libre, `Trim`) — número del ERP | (sin formato validado) |
| `NOTA_NUMERO` | **manual** (`Trim`) — número del ERP; pista "Ej.: 00027520" | `00027520` |
| `COBRANZA_NUMERO` | **manual** (`Trim`), mínimo 3 caracteres; único en toda la lista; pista "Ej.: B00012345 o V00012345" | `B00012345` |
| `COBRANZA_TITULO` | `= NOTA_NUMERO` | |
| `VISITA_TITULO` | `CLIENTE_NOMBRE & " - " & Text(Now(), "dd/mm/yyyy hh:mm")` | `FERRETERÍA X - 14/03/2025 15:30` |
| Título de relevamiento | `"REL-" & gVendCod & "-" & Text(Now(), "yyyymmdd-hhmmss")` | `REL-V07-20250314-153512` |

### 4.1 Almacén según prefijo del código de producto

| Prefijo de `PRODUCTO_TERMINADO_CODIGO` | Nombre de almacén (texto exacto guardado) | Código |
|---|---|---|
| `5T201` o `5T202` | `ALMACÉN P11 — EXTRUSIÓN` | `P11` |
| `5T203` | `ALMACÉN P12 — TERMOFORMADO` | `P12` |
| `5T204` | `ALMACÉN P13 — EXPANDIDO` | `P13` |
| `5T205` | `ALMACÉN P14 — INYECCIÓN` | `P14` |
| `5T206` | `ALMACÉN P15 — RAFIA` | `P15` |
| cualquier otro | `SIN ALMACÉN ASIGNADO` | `SIN` |

(El separador es una raya larga "—" y los nombres llevan tilde: ALMACÉN, EXTRUSIÓN, INYECCIÓN.) Productos sin almacén **sí se aceptan** y generan una proforma `…-SIN`.

---

## 5. Máquinas de estado

### 5.1 Pedido — estado de despacho (`PEDIDO_ESTADO_DESPACHO`)

```
(alta, valor por defecto de la lista) ─► Sin proforma
Sin proforma ──(se guarda el N° de cualquier proforma)──► Proformado
* ──(se crea una nota)──► Despachado parcial | Despachado total
* ──(se anula una nota)──► recalculado: Despachado total | Despachado parcial | Proformado | Sin proforma
```

- Al **crear una nota**: `Despachado total` si Σ cantidades de todas las líneas del pedido − Σ cantidades de líneas de notas activas ≤ 0; si no, `Despachado parcial`.
- Al **anular una nota**: `desp` = Σ cantidades de líneas de notas no anuladas; `tot` = Σ cantidades de líneas del pedido. `Despachado total` si desp ≥ tot; `Despachado parcial` si desp > 0; `Proformado` si alguna proforma tiene número; si no `Sin proforma`.
- Al **numerar una proforma** solo transiciona si el estado actual es exactamente `Sin proforma` → `Proformado`.
- La **edición** del pedido **no** recalcula este estado.
- Solo se escribe en SharePoint si cambia. Etiqueta y color en la lista: `Despachado total`→"Despachado" (verde), `Despachado parcial`→"Parcial" (naranja), `Proformado`→"Proformado" (azul), otro→"Sin proforma" (ámbar).

### 5.2 Pedido — estado de pago (`PEDIDO_ESTADO_PAGO`)

- Alta: `Pendiente` (también para contado: "Todo pedido nace sin cobrar, también el contado").
- Edición: `Pagado` si total − pagado ≤ 0; `Parcial` si pagado > 0; si no `Pendiente`.
- **Nunca se actualiza por cobranzas** (las cobranzas actúan sobre la nota de venta). En la práctica `PEDIDO_TOTAL_PAGADO` queda siempre en 0.

### 5.3 Proforma (`PROFORMA_ESTADO`)

Valor almacenado: `Sin número` al crearse; al guardar el número se calcula: `Despachada` si despachado ≥ total; `Parcial` si despachado > 0; si no `Pendiente` (en la práctica siempre `Pendiente`, porque el número solo se puede editar mientras no haya notas activas).

Valor **mostrado** (calculado en pantalla, no almacenado): `Sin número` si no tiene número; si no, `Despachada` / `Parcial` / `Pendiente` según cantidades despachadas (ver defecto 13.1.8: incluye notas anuladas al recargar).

### 5.4 Nota de venta

- `NOTA_ESTADO_PAGO`: `Pendiente` → (`Parcial` | `Pagado`) al confirmar cobranzas. `Pagado` si el nuevo saldo ≤ 0.
- `NOTA_ANULADA`: false → true (irreversible desde la app). Solo se puede anular si no tiene **ninguna** cobranza registrada (de cualquier estado, con o sin pago).
- Etiqueta: "Anulada" (rojo) si anulada; si no, el estado de pago (verde si Pagado, naranja en otro caso).

### 5.5 Cobranza

```
(alta) ─► Pendiente de confirmación ──(rol cobranzas ingresa N° de cobranza)──► Confirmada
Confirmada ──(corrección de N°, con motivo)──► Confirmada (N° cambiado, se guarda el anterior)
```

- Las cobranzas **sin pago** (`COBRANZA_HUBO_PAGO = false`) también nacen "Pendiente de confirmación", pero **no aparecen** en ninguna lista de confirmación (los filtros exigen `COBRANZA_HUBO_PAGO = true`), por lo que quedan en ese estado para siempre (funcionan como registro de visita de cobro sin pago).
- Etiqueta en lista: "Confirmada" o "Esperando aprobación".

---

## 6. Pantalla Visita (`PantallaVisita_1`)

**Propósito:** registrar el contacto con el cliente; es requisito previo para cargar pedidos.

**OnVisible:** `gConfNuevaVisita = false`.

### 6.1 Campos

| Control | Comportamiento |
|---|---|
| "Código cliente" (texto) | Valor por defecto = `varCodCliente`. Al cambiar: busca `dim_CLIENTES` con `CLIENTE_CODIGO = Trim(texto)`; si existe y el texto no está vacío, fija `varCodCliente` y `varCliente` y reinicia el combo de nombre. |
| "Nombre cliente" (combo) | Ítems `dim_CLIENTES`, muestra `CLIENTE_NOMBRE`, `CLIENTE_CODIGO`; busca en ambos campos; marcador "Buscá por nombre o código". Al cambiar fija `varCliente` y `varCodCliente` y reinicia el campo código. Ambos campos están sincronizados. |
| "Motivo" (3 botones) | `Rutina`, `Cobranza`: fijan `gMotivo` y **limpian** forma de pago y días de crédito. `Pedido`: fija `gMotivo = "Pedido"`. |
| "Forma de pago" (visible si motivo = Pedido) | Botones `Crédito` / `Contado` (`Contado` limpia el campo días). |
| "Días de crédito" (visible si Crédito) | Numérico, máximo 2 dígitos (0–99), pista "Número de días...". |
| "¿Qué pasó en la visita y/o llamada?" (multilínea) | Pista: "Escribí lo relevante de la visita… En caso de que sea un pedido, Coloca la dirección del pedido aquí..." |
| Tipo de contacto | Botones `Llamada` / `Visita` (`gTipoContacto`). |
| "VENDEDOR" (etiqueta) | Si `gVendedorOk`: `gVendCod & " — " & gVendNom`; si no: "⚠ Tu usuario no está registrado como vendedor". |
| "CARGAR A NOMBRE DE" (combo) | Ver 2.5. Marcador "Elegí el vendedor". |

Todos los campos se reinician con `gResetVisita`.

### 6.2 Botón principal (texto dinámico)

Texto: "Registrar visita" si no hay visita registrada en la sesión (`gVisita` vacío); "Nueva visita" si ya hay una.

**A) Modo "Registrar visita"** — validaciones en este orden (la primera que falla muestra error y se detiene):

1. Sin cliente → "Falta seleccionar el cliente"
2. `gVendCod` vacío → "Tu usuario no está registrado como vendedor — avisá a sistemas"
3. Sin tipo de contacto → "Falta elegir el tipo de contacto (Visita o Llamada)"
4. Sin motivo → "Falta elegir el motivo de la visita"
5. Motivo Pedido sin forma de pago → "Falta elegir la forma de pago (Crédito o Contado)"
6. Motivo Pedido + Crédito + días ≤ 0 → "Falta indicar los días de crédito"
7. Comentario vacío (tras Trim) → "Falta escribir el comentario de la visita"

Si todo es válido:
1. `gDiasCredito` = días si Crédito; si no, vacío.
2. `gComentario` = comentario recortado.
3. Alta en `fact_VISITAS` (ver 3.4) y se guarda el registro en `gVisita`.
4. `gVisitaRef` = referencia `{Id, Value: VISITA_TITULO}` para el pedido.
5. Notificación de éxito: "Visita registrada — ya podés cargar pedido" o, si se cargó a nombre de otro, "Visita registrada a nombre de {gVendNom} — ya podés cargar pedido".

**B) Modo "Nueva visita"** (limpieza):
- Si hay productos en el carrito (`colPedido`) y no se confirmó: fija `gConfNuevaVisita = true` y avisa (Warning): "Tenés {n} producto(s) cargados sin guardar. Si seguís, se pierden. Volvé a tocar 'Nueva visita' para descartarlos, o andá a Pedido y guardá primero." No borra nada.
- En otro caso (carrito vacío o segundo toque): limpia visita, cliente, motivo, tipo de contacto, forma de pago, días, comentario, carrito, `gPedidoGuardado = false`, resumen `{Lineas:0, Total:0}`, **restablece la atribución al usuario real** y reinicia los controles.

> Una misma visita admite **varios pedidos** (tras guardar uno, "Agregar más productos" permite otro pedido sobre la misma visita).

---

## 7. Pantalla Pedido (`PantallaPedido_1`)

**Propósito:** armar el carrito y guardar el pedido completo (cabecera, líneas y proformas).

### 7.1 OnVisible — "Último pedido" del cliente

`colUltimoPed` = líneas (`fact_PEDIDO_LINEAS`) del pedido más reciente (`PEDIDO_FECHA` desc.) del cliente actual (`CLIENTE_CODIGO = varCliente.CLIENTE_CODIGO`). La tarjeta "Último pedido — N producto(s)" es visible si hay cliente y al menos una línea. Cada fila: descripción, `cantidad unidad`, `Bs cantidad × precio`.

### 7.2 Aviso de atribución

"⚠  Estás cargando a nombre de {gVendNom}" visible si `gVendCod <> gUsrCod`.

### 7.3 Agregar producto (visible mientras `gPedidoGuardado = false`)

Campos: Producto (combo de `dim_PRODUCTOS`, búsqueda por descripción y código, marcador "Buscar productos..."), "Cantidad" (la etiqueta cambia a "Cantidad en {unidad}"), "Precio (Bs)" — ambos numéricos con pista "0".

Ficha informativa del producto seleccionado (muestra "-" si no hay): Bolsas por paquete, Paquetes por jaba, Jabitas por jaba, Jabas por bulto, Peso Promedio, Micronaje (**defecto**: muestra `PRODUCTO_JABITAS_POR_JABA` en vez de `PRODUCTO_MICRONAJE`).

**Botón "+ Agregar al pedido":**
1. Calcula la unidad (`Upper(Trim(unidad))`) y el almacén por prefijo (4.1).
2. Validaciones en orden:
   - Sin visita → "Primero registrá la visita"
   - Sin producto → "Falta elegir el producto"
   - Cantidad < 1 → "La cantidad tiene que ser 1 o más"
   - Unidad `PZA` con decimales → "Las piezas se venden enteras: la cantidad no puede tener decimales"
   - Precio ≤ 0 → "El precio unitario tiene que ser mayor a cero"
3. Agrega al carrito en memoria (`colPedido`) un ítem con: producto completo, descripción, código, almacén, unidad, micronaje, peso promedio, **PesoLinea = cantidad × peso promedio**, los 8 campos de empaque/peso, cantidad y precio.
4. Limpia los campos y fija `gPedidoGuardado = false`.

> No se valida producto duplicado en el carrito (sí se valida en la edición; ver defectos).

### 7.4 Lista "PEDIDO"

Por línea: descripción, "`cantidad unidad × Bs precio`", subtotal "Bs cantidad×precio", ícono de papelera (quita del carrito). Total: "Bs Σ(cantidad×precio)".

### 7.5 Botón "Guardar pedido"

- Visible mientras `gPedidoGuardado = false`. Deshabilitado si no hay visita o el carrito está vacío.
- Validaciones:
  - Sin visita → "Primero registrá la visita"
  - Carrito vacío → "El pedido no tiene productos cargados"
  - Sin forma de pago → "El pedido no tiene forma de pago — volvé a la visita y elegí Crédito o Contado"

**Lógica transaccional (en orden):**

1. Calcula `gNroPedido` (4), `gTotalPedido = Σ cantidad×precio`, `gPesoTotal = Σ PesoLinea`, `gEsContado`, `gCantAlmacenes = cantidad de almacenes distintos del carrito`.
2. **Cabecera:** alta en `fact_PEDIDOS` con: número, lookup a visita, lookups de cliente y vendedor atribuido, códigos/nombres, `CARGADO_POR`, `PEDIDO_TIPO_CONTACTO`, `PEDIDO_FORMA_PAGO`, `PEDIDO_DIAS_CREDITO = gDiasCredito`, `PEDIDO_FECHA = Now()`, `PEDIDO_FECHA_VENCIMIENTO = Now() + días` (Crédito) o `Now()` (Contado), `PEDIDO_TOTAL`, `PEDIDO_TOTAL_PAGADO = 0`, `PEDIDO_SALDO_PENDIENTE = total`, `PEDIDO_ESTADO_PAGO = "Pendiente"`.
   - Si falla: "No se pudo crear el pedido: {mensaje del error}" y termina.
3. **Líneas:** un alta en `fact_PEDIDO_LINEAS` por ítem del carrito (ver 3.6), con `PROFORMA_CLAVE = número-Pxx` y `LINEA_CANTIDAD_DESPACHADA = 0`.
   - Éxito = sin errores **y** cantidad de líneas creadas con ID = cantidad de ítems.
   - **Si falla (rollback):** borra las líneas creadas (por ID), luego un barrido de toda línea con ese `PEDIDO_NUMERO`, luego la cabecera. Mensaje: "No se pudo guardar el pedido completo: se grabaron {k} de {n} productos. No se creó nada y tus productos siguen cargados. Volvé a intentar." + " ({mensaje})" si hay error.
4. **Proformas:** una por almacén distinto (ordenados alfabéticamente), con `PROFORMA_TITULO = PROFORMA_CLAVE = número-Pxx`, `PEDIDO_NUMERO`, cliente (lookup + código + nombre), vendedor atribuido (código y nombre), `PROFORMA_ALMACEN`, `PROFORMA_FECHA = Now()`, `PROFORMA_TOTAL = Σ cantidad×precio del almacén`, `PROFORMA_TOTAL_DESPACHADO = 0`, `PROFORMA_ESTADO = "Sin número"`.
   - Éxito = sin errores **y** cantidad creada con ID = `gCantAlmacenes`.
   - **Si falla (rollback total):** (1) borra proformas creadas por ID; (2) barrido de proformas con ese `PEDIDO_NUMERO`; (3) barrido de líneas; (4) cabecera. Mensaje: "El pedido tiene {n} almacén(es) y solo se crearon {k} proforma(s). No se guardó nada y tus productos siguen cargados. Volvé a intentar." + " ({mensaje})".
5. **Todo OK:**
   - `gPedidoResumen = {Lineas: n, Total}`.
   - Arma y envía el correo **Nuevo pedido** (ver 12.1). Si el envío falla: Warning "El pedido se guardó pero el correo NO se envió a: {destinatarios}. Avisales a mano."
   - `gPedidoGuardado = true`, vacía el carrito, recarga "Último pedido".
   - Éxito: "Pedido guardado: {n} productos · {k} almacén(es) · Bs {total}".
6. Con `gPedidoGuardado = true` se muestra "✓ Pedido guardado: {n} líneas · Bs {total}" y el botón "Agregar más productos" (vuelve a `gPedidoGuardado = false` para cargar **otro pedido** sobre la misma visita).

---

## 8. Pantalla Cobranza (`PantallaCobranza_1`)

**Propósito:** registrar un cobro (o una gestión de cobro sin pago) sobre una nota de venta con saldo.

### 8.1 OnVisible

Reinicia `gHuboPago=false`, `gTipoPago=""`, `gFormaPago=""`, monto 0, `gSaldoDisp=0`, vacía detalle de nota. Carga `colCobrPend` = (NOTA_NUMERO, COBRANZA_MONTO) de **todas** las cobranzas con `COBRANZA_HUBO_PAGO = true` y estado `Pendiente de confirmación` (de cualquier vendedor).

### 8.2 Lista "NOTAS DE VENTA PENDIENTES · {n}"

- Buscador: "Buscar por nota, cliente o vendedor" (con retardo de salida). Coincidencia **por prefijo** (`StartsWith`) en `NOTA_NUMERO`, `CLIENTE_NOMBRE` o `VENDEDOR_NOMBRE`.
- Origen: `fact_NOTAS_VENTA` con `NOTA_SALDO_PENDIENTE > 0` y `NOTA_ANULADA <> true`; si **no** `gVeTodasLasNotas`, además `VENDEDOR_CODIGO = gVendCod`.
- Orden: `NOTA_FECHA` descendente.
- Fila: "Nota {número}  ·  {dd/mm}", cliente, vendedor (solo si `gVeTodasLasNotas`), "Bs {NOTA_TOTAL}", "Saldo: Bs {saldo − pendiente}" + " ⏳" y color ámbar si hay cobranzas pendientes de confirmación para esa nota.
- Vacío: "No hay notas de venta con saldo pendiente".
- **Al seleccionar:** `gNotaCbr = nota`; **`gSaldoDisp = NOTA_SALDO_PENDIENTE − Σ montos pendientes de confirmación de esa nota`**; carga sus líneas (`fact_NOTA_LINEAS` por `NOTA_NUMERO`); limpia el monto.

### 8.3 "Detalle de la nota" (visible con nota seleccionada)

Líneas: descripción, "`cantidad unidad × Bs precio`", subtotal. Debajo: "Saldo pendiente: Bs {saldo}" y, si `gSaldoDisp` < saldo, una segunda línea "Disponible para cobrar: Bs {gSaldoDisp}".

### 8.4 "DATOS DEL COBRO" (visible con nota seleccionada)

| Control | Regla |
|---|---|
| "¿Hubo pago?" Sí / No | Sí → `gHuboPago = true`. No → `gHuboPago=false`, tipo y monto en blanco. |
| "Tipo de pago" (visible si hubo pago) | `Completo` (monto = saldo; campo de solo lectura con `gSaldoDisp`), `Parcial`, `Anticipo` (monto libre). |
| "Monto cobrado · máximo Bs {gSaldoDisp}" (visible si hubo pago y hay tipo) | Numérico, pista "0.00". |
| "Fecha de próximo pago" (visible si NO hubo pago, o tipo Parcial/Anticipo) | Fecha, marcador "dd/mm/aaaa". |
| "Forma de pago" (visible si hubo pago) | `Efectivo`, `Transferencia`, `Banco` (guarda **"Depósito bancario"**). |
| "Comprobante" (adjunto, visible si hubo pago) | Botón "Adjuntar archivo". Formulario `frmCobranza` en modo Nuevo sobre `fact_COBRANZAS`. |

### 8.5 Botón "Guardar cobranza"

Primero fija `gCobranzaMonto = gSaldoDisp` si Completo; si no, el valor tecleado. Validaciones en orden:

1. Sin nota → "Seleccioná una nota de venta"
2. Hubo pago y `gSaldoDisp ≤ 0` → "Esta nota ya tiene cargado todo su saldo esperando aprobación. No se puede agregar otra cobranza hasta que el equipo de cobranza la revise."
3. Hubo pago sin tipo → "Indicá el tipo de pago"
4. Hubo pago y monto ≤ 0 → "El monto debe ser mayor a cero"
5. Hubo pago y monto > `gSaldoDisp` → "El monto no puede superar lo disponible para cobrar (Bs {gSaldoDisp}" + (si hay pendientes: ". El saldo de la nota es Bs {saldo}, pero ya hay pagos cargados esperando aprobación") + ")"
6. Hubo pago sin forma de pago → "Indicá la forma de pago"
7. Hubo pago, forma ≠ Efectivo y sin adjunto → "Adjuntá el comprobante de la {forma en minúsculas}" (p. ej. "…de la transferencia", "…de la depósito bancario")
8. Sin pago y sin fecha de próximo pago → "Indicá la fecha del próximo pago"

Si todo es válido: `SubmitForm(frmCobranza)` crea una fila (solo con el adjunto). Luego:

**OnFailure:** "No se pudo guardar la cobranza. Intentá de nuevo."

**OnSuccess (dos pasos con compensación):**
1. Busca la fila recién creada por `LastSubmit.ID`. Si no la encuentra: "No se encontró la cobranza recién creada. No se completaron los datos." (no hace Patch para no crear otra fila).
2. Completa la fila con: `COBRANZA_TITULO = NOTA_NUMERO`, datos de nota/proforma/pedido/cliente/vendedor de la nota, `CARGADO_POR`, `COBRANZA_FECHA = Now()`, `COBRANZA_HUBO_PAGO`, `COBRANZA_TIPO_PAGO`, `COBRANZA_MONTO` (0 si no hubo pago), `COBRANZA_FORMA_PAGO`, `COBRANZA_FECHA_PROXIMO_PAGO`, `NOTA_TOTAL`, `COBRANZA_SALDO_ANTES = saldo de la nota`, `COBRANZA_SALDO_DESPUES = saldo − monto`, `COBRANZA_ESTADO = "Pendiente de confirmación"`. Si no hay respuesta: "No llegó la respuesta del servidor al completar la cobranza."
3. Si la lista reporta errores: intenta borrar la fila (si no puede: Warning "Quedó una cobranza incompleta en el sistema (ID {id}). Avisá para que la borren a mano."), reinicia el formulario y muestra "La cobranza NO se registró y se deshizo lo que se había creado. Los datos del cobro siguen cargados: volvé a adjuntar el comprobante y guardá de nuevo."
4. Si todo OK:
   - **Solo si hubo pago:** envía correo "Cobranza pendiente" (12.2). Si falla: Warning "La cobranza se registró pero el aviso NO se envió a: {dest}. Avisales a mano."
   - Éxito: "Cobranza registrada por Bs {monto}. Queda pendiente de confirmación." o "Visita sin pago registrada."
   - Limpia formulario, nota seleccionada, detalle, `gHuboPago`, tipo, forma y monto.

> **Importante:** registrar una cobranza **no modifica** la nota de venta. El saldo solo baja cuando el rol cobranzas la confirma (sección 10.6).

---

## 9. Pantalla Competencia (`PantallaCompetencia_1`)

**Propósito:** registrar precios observados de competidores en un cliente.

### 9.1 OnVisible

Limpia tipo y cliente, reinicia controles. `colCompHoy` = relevamientos de `fact_COMPETENCIA` con `VENDEDOR_CODIGO = gVendCod` y `RELEVAMIENTO_FECHA >= Today()`. `colCompSugeridos` = los 5 primeros competidores distintos en orden alfabético (se muestran como "Ya cargados: A  ·  B  ·  …").

### 9.2 Campos

| Campo | Regla |
|---|---|
| CLIENTE (combo `dim_CLIENTES`) | Busca por nombre o código. Etiqueta: "Código {cód}   ·   {gVendCod} — {gVendNom}" |
| COMPETIDOR (texto) | "Nombre de la marca o empresa" |
| TIPO DE PRODUCTO (botones) | Bolsas, Bobinas, Bandejas (**graba "Bobinas"**, defecto), Vasos, Rafia, Otro |
| DESCRIPCIÓN | "Lo que dice el envase" |
| MEDIDA (opcional) | ej. "90 X 110" |
| UNIDAD (desplegable) | Unidades distintas de `dim_PRODUCTOS`, ordenadas |
| PRECIO Bs | numérico (valor por defecto erróneo "Text input") |
| CANTIDAD | numérico (valor por defecto erróneo "Text input") |
| OBSERVACIÓN | opcional, multilínea |

### 9.3 Botón "Guardar relevamiento"

Normaliza competidor (`Upper(primera letra) & Lower(resto)` tras Trim). Validaciones en orden:
1. "Elegí el cliente"
2. "Escribí el competidor"
3. "Elegí el tipo de producto"
4. "Escribí la descripción del producto"
5. "Elegí la unidad"
6. Precio ≤ 0 → "El precio tiene que ser mayor a cero"
7. Cantidad < 1 → "La cantidad tiene que ser 1 o más"
8. Unidad PZA con decimales → "Las piezas se cuentan enteras"

Alta en `fact_COMPETENCIA` (3.11). Si no devuelve ID: "No se pudo guardar el relevamiento. Volvé a intentar." Si OK: agrega a "CARGADOS HOY", "Guardado · {competidor} · {descripción}", limpia tipo, descripción, medida, unidad, precio, cantidad y observación (**conserva** cliente y competidor para cargar varios productos seguidos).

### 9.4 "CARGADOS HOY · {n}"

Fila: "{competidor}  ·  {descripción} {medida}", "Bs {precio}  ·  {cantidad} {unidad}", ícono eliminar → **borrado físico** en `fact_COMPETENCIA`; "Relevamiento eliminado" o "No se pudo eliminar". Vacío: "Todavía no cargaste ningún relevamiento".

---

## 10. Pantalla Detalles ("Tickets")

### 10.1 OnVisible

Pestaña inicial: "Cobranzas" si rol cobranzas, si no "Pedidos". Reinicia: filtro sin proforma, modo edición, pedido seleccionado, cobranza seleccionada, permisos por ticket (todos false), proforma seleccionada, colecciones de líneas/proformas/notas, buscador, formulario de nota.

### 10.2 Barra superior

| Control | Visible | Acción |
|---|---|---|
| Pestaña "Pedidos" | `gVeTabPedidos` | `gTabDetalles = "Pedidos"` |
| Pestaña "Cobranzas" | `gVeTabCobranzas` (deshabilitada en edición) | `gTabDetalles = "Cobranzas"` |
| "Sin proforma" (alterna) | `gVeTodosLosPedidos` y pestaña Pedidos (deshabilitada en edición) | Filtra pedidos con estado `Sin proforma` |
| "Pendientes" (alterna) | Pestaña Cobranzas y (`gVeTodasLasCobranzas` o `gPuedeConfirmar`) | Filtra cobranzas `Pendiente de confirmación` (el estado del filtro **no** se reinicia al entrar) |
| Desde / Hasta (fechas, es-BO) | Pestaña Cobranzas | Rango de `COBRANZA_FECHA` |
| Ícono limpiar fechas | Si hay alguna fecha | Reinicia ambas |
| Buscador | Fuera de edición | Pista: "Buscar por nota, cliente o vendedor" (Cobranzas) / "Buscar por cliente o vendedor" (Pedidos) |

Título de lista: Cobranzas → "COBRANZAS POR CONFIRMAR" (rol cobranzas) / "TODAS LAS COBRANZAS" / "MIS COBRANZAS"; Pedidos → "TODOS LOS PEDIDOS" / "MIS PEDIDOS".

### 10.3 Pestaña Pedidos — lista

- Origen: `fact_PEDIDOS` con `PEDIDO_FECHA >= hoy − 90 días`; si no `gVeTodosLosPedidos`, además `VENDEDOR_CODIGO = gVendCod`.
- Filtro "Sin proforma": `PEDIDO_ESTADO_DESPACHO = "Sin proforma"`.
- Búsqueda por prefijo en `CLIENTE_NOMBRE` o `VENDEDOR_NOMBRE`.
- Orden: `PEDIDO_FECHA` desc.
- Fila: número, cliente, fecha (+ "  ·  vendedor" solo para admin), "Bs total", chip de estado de pago (Pagado verde / Parcial ámbar / otro rojo), chip de estado de despacho (5.1).
- Vacío: "No hay pedidos sin proforma" / "No hay pedidos para mostrar".
- Deshabilitada en modo edición.

**Al seleccionar un pedido:**
1. Cierra edición, limpia proforma seleccionada y anulación en curso.
2. Carga en paralelo: líneas del pedido; proformas del pedido; notas del pedido (`PEDIDO_NUMERO`); líneas de notas (`StartsWith(PROFORMA_CLAVE, PEDIDO_NUMERO)`).
3. `colLineasActivas` = líneas de notas cuya nota **no** está anulada.
4. `gProductosEditables` = ninguna proforma tiene líneas activas.
5. Recalcula permisos por ticket (2.4).

### 10.4 Detalle del pedido (pestaña Pedidos, fuera de edición)

- Título: "PEDIDO {número}" o "Elegí un pedido de la lista".
- Cabecera: Cliente, Fecha, Vendedor, Forma de pago (+ "  ·  N días" si Crédito), Vence (o "—"), Proforma (números de proforma separados por "  ·  " o "Sin proforma").
- **PRODUCTOS** (por línea): descripción; "`cant unidad × Bs precio`" + "  ·  despachado X / pendiente Y" (calculado con líneas **activas**, por `PROFORMA_CLAVE` + `PRODUCTO_TERMINADO_CODIGO`); otra etiqueta con "  ·  despachado X" calculado con **todas** las líneas de notas (incluye anuladas — inconsistencia).
- Totales: Total, Pagado, Saldo, Estado (de la cabecera).
- "Sin modificaciones desde que se cargó" o "Modificado por {PEDIDO_MODIFICADO_POR} el dd/mm/yyyy a las hh:mm".
- **PROFORMAS** (galería): almacén; estado calculado (5.3) con color; "Bs {PROFORMA_TOTAL}  ·  {n} producto(s) pendiente(s)"; campo "N° de proforma".

### 10.5 Numeración de proforma

- Campo "N° de proforma": editable si `gProformarTicket` **y** la proforma no tiene líneas de notas activas; si no, solo lectura.
- Ícono guardar (mismas condiciones de visibilidad):
  1. Vacío → "Escribí el número de la proforma antes de guardar".
  2. Calcula estado (Despachada/Parcial/Pendiente) y actualiza `PROFORMA_NUMERO` y `PROFORMA_ESTADO` en SharePoint (error: "No se pudo guardar la proforma: {mensaje}") y en memoria; "Proforma {nro} guardada".
  3. Selecciona esa proforma, recalcula editabilidad, líneas para despacho y notas de la proforma.
  4. Si el pedido estaba en `Sin proforma` → lo pasa a `Proformado` (error: Warning "El estado del pedido no se pudo actualizar").
- No valida unicidad del número de proforma. El número puede cambiarse mientras no haya notas activas.

**Al seleccionar una proforma** (`gProfSel`): arma `colProfLineas` (por cada línea del pedido de esa proforma: Despachado = Σ líneas activas del mismo código y proforma; Pendiente = cantidad − despachado; ADespachar = 0), las notas de la proforma y sus líneas.

### 10.6 Despacho: crear nota de venta (tarjeta "DESPACHAR · {almacén}")

Visible si `gVeTabPedidos`, pestaña Pedidos, fuera de edición y con proforma seleccionada. Las partes de despacho requieren `gDespacharTicket`.

- Aviso: "Cargá primero el número de esta proforma para poder despachar." o "Proforma {nro} · indicá cuánto se despacha de cada producto."
- Por línea: producto, "Pendiente: {n} {unidad}", campo cantidad a despachar (habilitado si pendiente > 0; al cambiar se guarda `Min(valor, pendiente)` — se recorta silenciosamente al pendiente).
- "NÚMERO DE NOTA DE VENTA" (pista "Ej.: 00027520"), "Total de esta nota: Bs {Σ a despachar × precio}", adjunto del comprobante (máx. 1 archivo, 50 MB), botón "+ Crear nota de venta" (solo si la proforma tiene número).

**Validaciones del botón (en orden):**
1. Número vacío → "Escribí el número de la nota de venta"
2. Σ a despachar ≤ 0 → "Cargá al menos una cantidad a despachar"
3. Alguna negativa → "No se admiten cantidades negativas"
4. Alguna > pendiente → "Hay productos donde la cantidad supera lo pendiente"
5. Unidad `PZA` con decimales → "Las unidades PZA deben ser números enteros"
6. Número ya existe **entre las notas de este pedido** → "Ya existe una nota de venta con ese número"
7. Sin adjunto → "Adjuntá el comprobante de la nota de venta"

**Proceso:**
1. Guarda número, líneas a enviar (a despachar > 0), total y cantidad de líneas; `SubmitForm(frmNota)` crea la nota con el número y el adjunto.
2. OnSuccess: relee la nota; si **no tiene adjunto** → la borra y "El comprobante no se guardó. La nota no se creó, volvé a intentar."
3. Completa la nota (3.8). Error: "No se pudieron guardar los datos de la nota: {mensaje}" (y **continúa**, ver defectos).
4. Crea una `fact_NOTA_LINEAS` por línea enviada (3.9).
5. Verificación: si la cantidad creada ≠ esperada o alguna sin ID → refresca, borra todas las líneas con ese `NOTA_NUMERO`, borra la nota: "La nota no se guardó completa. Se deshizo todo."
6. OK: agrega nota y líneas a memoria (activas), recalcula líneas de la proforma, notas y estado del pedido (5.1) y lo guarda si cambió. "Nota de venta {nro} creada".
7. Reinicia el formulario.

> No hay correo al crear la nota. No se actualiza `PROFORMA_ESTADO`, `PROFORMA_TOTAL_DESPACHADO` ni `LINEA_CANTIDAD_DESPACHADA`: el "despachado" siempre se **calcula** desde `fact_NOTA_LINEAS`.

### 10.7 Notas de la proforma y anulación

"NOTAS DE VENTA DE ESTA PROFORMA" (visible si hay): "Nota {nro}", estado (5.4), "dd/mm/yyyy  ·  Bs total  ·  saldo Bs X" o "  ·  ANULADA", productos (`cant unidad  ·  descripción`, uno por renglón, solo si no anulada), ícono "ver comprobante" (abre el primer adjunto; si no hay: "Esta nota no tiene comprobante adjunto"), y si anulada: "Motivo: …", "Anulada por {nombre} el dd/mm/yyyy hh:mm".

**Ícono anular** (visible si `gDespacharTicket` y nota no anulada):
- Si la nota tiene cualquier registro en `fact_COBRANZAS` → "Esta nota tiene cobranzas registradas. No se puede anular."
- Si ya anulada → "Esta nota ya está anulada" (Warning).
- Si no, abre el panel "ANULAR NOTA {nro}" con motivo (pista "Motivo de la anulación (obligatorio)").

**"Confirmar anulación":**
1. Motivo vacío → "Escribí el motivo de la anulación"; < 10 caracteres → "El motivo tiene que ser más específico (al menos 10 caracteres)".
2. Actualiza la nota: `NOTA_ANULADA = true`, motivo, `NOTA_ANULADA_POR = gUsuario`, `NOTA_ANULADA_FECHA = Now()`. Error: "No se pudo anular: {mensaje}" y además "No llegó la respuesta del servidor. Puede que el cambio SÍ se haya guardado. Salí y volvé a entrar a esta pantalla para verificar antes de reintentar."
3. En memoria: marca anulada, quita sus líneas de las líneas de notas, recalcula pendientes de la proforma, notas, y estado del pedido (5.1) — lo guarda si cambió.
4. Envía correo **Nota de venta anulada** (12.3). Si falla: Warning "La nota se anuló pero el correo NO se envió a: {mails}. Avisales a mano."
5. Cierra el panel; "Nota {nro} anulada".

"Cancelar" cierra el panel. **No existe borrado físico de notas desde la interfaz**: el único borrado de notas/líneas es el rollback de una creación fallida (las variables `gNvId`, `gNvCant`, `colNotaBorrar` son restos de una versión anterior con borrado).

### 10.8 Edición de pedido

Botón inferior "Editar pedido" / "Guardar cambios", visible si `gEditarTicket`, pestaña Pedidos y pedido seleccionado. **No** depende del estado del pedido (se puede editar un pedido despachado en sus partes no bloqueadas).

**Entrar a edición ("Editar pedido"):** recarga líneas y proformas desde SharePoint, vacía `colTicketBorradas`, reinicia campos, `gModoEdicion = true` (se ocultan buscador, lista y tarjetas de detalle/despacho; se deshabilitan pestaña Cobranzas y filtros).

**Panel "EDITANDO {número}":**
- Aviso: "Podés cambiar la forma de pago, los días de crédito, la proforma y los productos." o "Los almacenes con 🔒 ya tienen notas de venta: esos productos están bloqueados. Los demás almacenes se pueden editar, y podés agregar productos nuevos de almacenes libres."
- FORMA DE PAGO: combo `Crédito` / `Contado`.
- DÍAS DE CRÉDITO: habilitado solo si Crédito; la etiqueta muestra "  ·  vence dd/mm/yyyy" (= `PEDIDO_FECHA + días`).
- (Número de proforma a nivel pedido: **oculto**, heredado).
- PRODUCTOS: por línea, descripción (+ "  🔒" si su proforma tiene líneas activas), cantidad y precio editables solo si no bloqueada, subtotal, papelera (solo si no bloqueada; si la línea existe en BD la agrega a `colTicketBorradas`).
- AGREGAR PRODUCTO: combo, cantidad ("Cantidad en {unidad}"), precio ("Precio unitario Bs"), "+ Agregar al pedido". Validaciones:
  - "Falta elegir el producto"
  - "Ese producto ya está en el pedido: cambiá la cantidad en su línea"
  - "El {almacén} ya tiene notas de venta: no se le pueden agregar productos"
  - "La cantidad tiene que ser 1 o más"
  - "Las piezas se venden enteras: la cantidad no puede tener decimales"
  - "El precio unitario tiene que ser mayor a cero"
- "Nuevo total: Bs X" / "Pagado: Bs Y  ·  Saldo: Bs X−Y".
- "Cancelar": sale de edición y recarga las líneas desde SharePoint.

**"Guardar cambios" — validaciones (en orden):**
1. "El pedido tiene que tener al menos un producto"
2. "Hay productos con cantidad menor a 1"
3. "Las piezas se venden enteras: hay una cantidad con decimales"
4. "Hay productos con precio cero o vacío"
5. "Falta la forma de pago"
6. Crédito y días < 1 → "Los días de crédito tienen que ser 1 o más"

**Proceso:**
1. Foto "antes": cabecera (`gTicketAntes`) y líneas actuales en BD (`colLineasAntes`).
2. `gHuboCambiosPedido` = cambió forma de pago, o días, o hay borradas, o hay nuevas, o alguna existente cambió cantidad o precio.
3. Actualiza cabecera: forma de pago, días (0 si Contado), `PEDIDO_FECHA_VENCIMIENTO = PEDIDO_FECHA + días`, `PEDIDO_TOTAL = Σ`, `PEDIDO_SALDO_PENDIENTE = total − pagado`, `PEDIDO_ESTADO_PAGO` (5.2), `PEDIDO_MODIFICADO_POR = gUsuario`, `PEDIDO_FECHA_MODIFICACION = Now()`. (Se guarda **aunque no haya cambios**.)
4. Si la cabecera se guardó: borra líneas eliminadas; actualiza cantidad y precio de las existentes (todas, cambien o no); crea las nuevas (mismo formato que en PED, con lookup al producto por código).
5. Si cabecera y líneas OK:
   - Relee cabecera y líneas.
   - Para cada `PROFORMA_CLAVE` presente: si no existe la proforma, la crea (`Sin número`, total calculado, cliente buscado por código); si existe, actualiza `PROFORMA_TOTAL`.
   - Borra proformas **sin número** que quedaron sin líneas.
   - Recarga proformas y editabilidad.
   - Si hubo cambios: envía un correo **Proforma modificada por cada proforma afectada** (12.6).
   - Sale de edición: "Pedido {número} actualizado".
6. Si falló: "No se pudo guardar: {error cabecera}{error líneas}" (sin rollback).

### 10.9 Pestaña Cobranzas

**Lista:**
- Rango: desde = fecha "Desde" o hoy − 90 días; hasta = fecha "Hasta" (o hoy) + 1 día (exclusivo).
- Origen: `fact_COBRANZAS` en el rango; si no es admin ni cobranzas, además `VENDEDOR_CODIGO = gVendCod` (rama inalcanzable, solo esos roles ven la pestaña).
- Siempre `COBRANZA_HUBO_PAGO = true`; con "Pendientes", solo `Pendiente de confirmación`.
- Búsqueda por prefijo en nota, cliente o vendedor. Orden `COBRANZA_FECHA` desc.
- Fila: "Nota {nro}", cliente, fecha y hora, "Bs monto", chip con forma de pago (color por tipo: Completo verde, Parcial ámbar, otro azul), "Confirmada"/"Esperando aprobación", vendedor (si `gVeTodasLasCobranzas`).
- Al seleccionar: relee la cobranza desde SharePoint (`gCobranzaSel`) y limpia el campo de confirmación.

**Detalle "COBRANZA · Nota {nro}"** (+ "  ·  cerrada" si confirmada): Cliente, Vendedor, Fecha, Monto, Forma de pago, Tipo de pago, Saldo antes, Saldo después, Estado, N° de cobranza (o "—"), y si fue corregido "N° anterior: {ant}  ·  {motivo}". Botón "Ver comprobante" (o "Sin comprobante adjunto", deshabilitado).

**Confirmar cobro** (visible si `gPuedeConfirmar`, hay cobranza y no está confirmada; habilitado si el N° tiene ≥ 3 caracteres). Campo "N° DE NOTA DE COBRANZA".
1. Relee la cobranza (`gCobLive`).
2. Validaciones: "Seleccioná una cobranza"; "Esa cobranza ya no existe. Refrescá la lista."; si ya confirmada → Warning "Esta cobranza ya fue confirmada con el N° {nro}. No se volvió a sumar." (idempotencia); N° vacío → "Escribí el número de nota de cobranza que dio el sistema"; N° repetido en toda la lista → "Ya existe una cobranza registrada con ese número".
3. Paso 1: actualiza cobranza: `COBRANZA_NUMERO`, `COBRANZA_ESTADO = "Confirmada"`, `COBRANZA_CONFIRMADA_POR = gVendNom`, `COBRANZA_FECHA_CONFIRMACION = Now()`. Error: "No se pudo confirmar: {mensaje}" + "No llegó la respuesta del servidor. Salí y volvé a entrar antes de reintentar: si la cobranza ya quedó confirmada, la app te lo va a avisar y no va a sumar dos veces."
4. Paso 2: busca la nota por `NOTA_NUMERO` (si no existe: "No encontré la nota {nro} para actualizar el saldo."). Calcula `pagadoNuevo = NOTA_TOTAL_PAGADO + monto`; `saldoNuevo = Max(NOTA_TOTAL − pagadoNuevo, 0)`; actualiza `NOTA_TOTAL_PAGADO`, `NOTA_SALDO_PENDIENTE`, `NOTA_ESTADO_PAGO = Pagado si saldo ≤ 0, si no Parcial`. Éxito: "Cobro {nro} confirmado. Nota {nota}: pagado Bs X, saldo Bs Y". Error: "El saldo de la nota NO se actualizó: {mensaje}" y "La cobranza quedó confirmada con el N° {nro} pero el saldo de la nota no se actualizó. Avisá para corregirlo a mano. No se mandó el correo."
5. Paso 3 (solo si el saldo se actualizó): correo **Cobro confirmado** (12.4). Si falla: Warning "El cobro se confirmó pero el correo NO se envió a: {dest}. Avisales a mano."
6. Relee la cobranza y limpia el campo.

> El saldo y el pagado de la **cabecera del pedido no se actualizan**.

**Corregir N° de cobranza** (ícono visible si `gPuedeConfirmar` y la cobranza está confirmada): panel "CORREGIR N° DE COBRANZA  ·  actual: {nro}" con "Número correcto" y "Motivo de la corrección (mínimo 10 caracteres)". Validaciones: "Seleccioná una cobranza"; "Escribí el número correcto"; "El número nuevo es igual al actual"; "Ya existe otra cobranza con ese número"; "Escribí el motivo de la corrección"; "El motivo tiene que ser más específico (al menos 10 caracteres)". Actualiza `COBRANZA_NUMERO`, `COBRANZA_NUMERO_ANTERIOR`, `COBRANZA_NUMERO_MOTIVO`, `COBRANZA_NUMERO_MODIF_POR = gUsuario`, `COBRANZA_FECHA_MODIF_NUMERO = Now()`. Error: "No se pudo corregir: {mensaje}" y "El número no se corrigió. Nada cambió.". OK: correo **Corrección de N° de cobranza** (12.5; si falla: Warning "El número se corrigió pero el correo no se envió."), "Número corregido: {antes} → {nuevo}". Solo conserva el **último** número anterior (no hay historial).

**Eliminar cobranza:** existe la lógica pero el ícono está **oculto** (`Visible: false`). Reglas: no confirmada; solo admin o propias (`VENDEDOR_CODIGO = gVendCod`); mensajes "Esta cobranza ya fue confirmada. No se puede eliminar.", "Solo podés eliminar tus propias cobranzas", "Cobranza eliminada. Podés volver a cargarla desde la pantalla de Cobranza.", "La cobranza no se eliminó. Intentá de nuevo.". Se recomienda decidir con negocio si se habilita.


---

## 11. Flujos de negocio de punta a punta

### 11.1 Visita → Pedido → Proformas

1. El vendedor (o el supervisor a nombre de otro) registra la **visita** con motivo "Pedido", forma de pago y, si es crédito, días (1–99).
2. En Pedido agrega productos; cada producto queda asignado a un almacén por el prefijo de su código (4.1).
3. "Guardar pedido" ejecuta, como **unidad atómica**:
   - Cabecera `fact_PEDIDOS` (estado de pago `Pendiente`, saldo = total, pagado 0; estado de despacho por defecto `Sin proforma`).
   - N líneas `fact_PEDIDO_LINEAS` (cada una con su `PROFORMA_CLAVE`).
   - K proformas `fact_PROFORMAS` (K = almacenes distintos), estado `Sin número`, total = Σ de sus líneas.
   - Si falla cualquier paso se borra todo lo creado (compensación en orden inverso) y el carrito se conserva para reintentar.
4. Correo "Nuevo pedido" (12.1).
5. Se puede cargar otro pedido sobre la misma visita ("Agregar más productos").

### 11.2 Numeración de proformas

El almacén (o el vendedor con `VENDEDOR_DESPACHA`, solo en sus pedidos) abre el pedido en Detalles y escribe en cada proforma el número generado en el ERP. El pedido pasa de `Sin proforma` a `Proformado`. El número se puede corregir mientras la proforma no tenga notas activas. El filtro "Sin proforma" funciona como bandeja de trabajo del almacén.

### 11.3 Despacho parcial/total → Nota de venta

1. Seleccionar una proforma **numerada**.
2. Para cada producto se muestra `Pendiente = LINEA_CANTIDAD − Σ LINEA_CANTIDAD de fact_NOTA_LINEAS activas (misma PROFORMA_CLAVE y mismo PRODUCTO_TERMINADO_CODIGO)`.
3. Indicar "a despachar" (≤ pendiente; enteros para PZA), número de nota del ERP y adjuntar el comprobante.
4. Se crea `fact_NOTAS_VENTA` (total = Σ a despachar × precio; saldo = total; estado `Pendiente`; forma de pago, días y vencimiento heredados del pedido) y sus `fact_NOTA_LINEAS`. Atómico con compensación.
5. Estado del pedido: `Despachado total` si ya no queda nada pendiente en **todo** el pedido; si no, `Despachado parcial`.
6. Una proforma admite varias notas (despachos parciales sucesivos) hasta agotar lo pendiente.

Fórmulas clave:
- `Despachado(línea) = Σ nota_lineas.LINEA_CANTIDAD donde PROFORMA_CLAVE = línea.PROFORMA_CLAVE y PRODUCTO_TERMINADO_CODIGO = línea.PRODUCTO_TERMINADO_CODIGO y la nota no está anulada`
- `Pendiente(línea) = LINEA_CANTIDAD − Despachado(línea)`
- `Productos pendientes de la proforma = cantidad de líneas con LINEA_CANTIDAD > Despachado`
- `Kg (correos) = Σ cantidad × PRODUCTO_PESO_PROMEDIO`
- `Total de nota = Σ (a despachar × LINEA_PRECIO_UNITARIO)`

### 11.4 Anulación de nota

Requisitos: permiso de despacho sobre el ticket, nota no anulada, **ninguna cobranza registrada** para ese `NOTA_NUMERO` (de cualquier estado, con o sin pago) y motivo de al menos 10 caracteres. Efectos: la nota queda marcada como anulada (sus líneas permanecen en la BD pero dejan de contar), las cantidades vuelven a estar pendientes, se recalcula el estado del pedido y se envía el correo a los destinatarios fijos, al almacén, a los 5 encargados, al vendedor y al usuario (12.3). La nota anulada deja de aparecer en la pantalla Cobranza.

### 11.5 Cobranza → Confirmación

1. El vendedor o cobrador elige una nota con saldo (> 0, no anulada).
2. Disponible = saldo de la nota − cobranzas con pago aún pendientes de confirmación para esa nota.
3. Registra: hubo pago (Completo = todo el disponible; Parcial/Anticipo = monto libre ≤ disponible), forma de pago, comprobante (obligatorio salvo efectivo) y fecha de próximo pago (obligatoria si no hubo pago). O bien registra "sin pago" con fecha de próximo pago.
4. Se crea la cobranza en `Pendiente de confirmación` (saldo antes/después solo informativos). Si hubo pago, se envía correo a caja (12.2).
5. El rol cobranzas (o admin), en Detalles › Cobranzas, revisa el comprobante, ingresa el N° de nota de cobranza del ERP (único) y confirma.
6. Al confirmar se suma el monto a `NOTA_TOTAL_PAGADO`, se recalcula `NOTA_SALDO_PENDIENTE` (mínimo 0) y `NOTA_ESTADO_PAGO`. Correo al vendedor (12.4).
7. Si el N° se cargó mal, se corrige con motivo (12.5).

### 11.6 Edición de pedido con comparación antes/después

1. Solo las líneas de proformas **sin notas activas** son editables o eliminables; se pueden agregar productos de almacenes sin notas activas (si el almacén es nuevo en el pedido, su proforma se crea `Sin número` al guardar).
2. Se guarda la cabecera, luego las líneas (eliminadas, modificadas, nuevas); después se recalculan los totales de proformas y se eliminan las proformas sin número que quedaron vacías.
3. Comparación (por ID de línea contra la foto previa): **NUEVO** (sin ID previo), **CAMBIO** (cantidad o precio distintos; se muestra tachado el valor anterior), **ELIMINADO** (líneas en `colTicketBorradas`). Cambios de cabecera: forma de pago y días/vencimiento.
4. Se envía un correo por cada proforma con algún cambio de líneas, o **a todas** si cambió la cabecera (12.6).

### 11.7 Relevamiento de competencia

Independiente de visitas y pedidos: cliente + competidor + tipo + descripción + unidad + precio + cantidad (+ medida y observación opcionales). Se listan los del día del vendedor y pueden borrarse.

---

## 12. Correos electrónicos

Todos se envían con `Office365Outlook.SendEmailV2(destinatarios, asunto, cuerpoHTML)`; el remitente es la cuenta de la conexión (**reportesbi@plasticoscarmen.com**). Los destinatarios se concatenan con ";". Si el envío falla, la operación de negocio **no** se revierte: solo se muestra un aviso (Warning) con la lista de destinatarios para avisar manualmente.

### 12.0 Direcciones configuradas (App.OnStart: "único lugar donde se editan")

| Variable / grupo | Valor | Recibe |
|---|---|---|
| `gMailRolAlmacen` | agonzales@plasticoscarmen.com | Pedido nuevo, proforma modificada, nota anulada |
| `gMailRolCobranzas` | caja@plasticoscarmen.com | Cobranza registrada (pendiente de confirmación) |
| `gMailAnulacionFijos` | acuellar@plasticoscarmen.com; fquenta@plasticoscarmen.com; jmontenegro@plasticoscarmen.com; almacenbolsa@plasticoscarmen.com | Nota anulada (siempre) |
| Encargados P11 — EXTRUSIÓN | mrendon@plasticoscarmen.com; dpaz@plasticoscarmen.com | Nota anulada |
| Encargados P12 — TERMOFORMADO | njustiniano@plasticoscarmen.com; ggutierrez@plasticoscarmen.com | Nota anulada |
| Encargados P13 — EXPANDIDO | njustiniano@plasticoscarmen.com; tjustiniano@plasticoscarmen.com | Nota anulada |
| Encargados P14 — INYECCIÓN | tjustiniano@plasticoscarmen.com | Nota anulada |
| Encargados P15 — RAFIA | faguilar@plasticoscarmen.com; ecardenas@plasticoscarmen.com | Nota anulada |
| Fijo en el código (no en OnStart) | samuel.jara@analytico.com.py | **Todos** los correos (consultor externo; ver 13.1.32) |

> Los encargados de almacén reciben **los cinco grupos juntos** cualquier anulación, sin importar el almacén de la nota (no hay ruteo por almacén). Hay direcciones repetidas (njustiniano, tjustiniano) que aparecen duplicadas en la cadena.

### 12.1 Nuevo pedido (Pedido, al guardar con éxito)

- **Destinatarios:** agonzales@ (rol almacén) **salvo** que el usuario real tenga `VENDEDOR_DESPACHA`; + correo del vendedor atribuido (`gVendSel.VENDEDOR_EMAIL`), si existe; + correo del usuario real si cargó a nombre de otro; + samuel.jara@analytico.com.py.
- **Asunto:** `Nuevo pedido {PEDIDO_NUMERO} — {CLIENTE_NOMBRE}`
- **Cuerpo (HTML, 720 px de ancho, cabecera azul #0B3B82):**
  - Título "Plásticos Carmen — Nuevo pedido"; subtítulo "{número} · dd/mm/yyyy hh:mm".
  - Tabla de datos: Código cliente, Cliente, Dirección ("—" si vacía o "0"), Teléfono (ídem), Vendedor ("código — nombre" del atribuido), Tipo de contacto, Forma de pago, Días de crédito ("No aplica" si es contado; si no, "N días"), Comentario (de la visita; "—" si vacío).
  - Tabla de productos (Código, Descripción, Cant., Unitario, Total) **agrupada por almacén** (orden alfabético), con una fila de encabezado por almacén que muestra el nombre y, a la derecha, "X kg" (Σ PesoLinea del almacén).
  - Pie: "Peso total X kg" y "TOTAL Bs X".

### 12.2 Cobranza pendiente de confirmación (Cobranza, solo si hubo pago)

- **Destinatarios:** caja@plasticoscarmen.com; samuel.jara@analytico.com.py.
- **Asunto:** `Cobranza pendiente · Nota {NOTA_NUMERO} · {CLIENTE_NOMBRE} · Bs {monto}`
- **Cuerpo:** cabecera #0B2E59 "Cobranza pendiente de confirmación", "Nota {nro} · fecha hora". Tabla: Cliente, Nota de venta, Pedido, Vendedor de la nota, Registró el cobro (`gUsuario`), separador, Monto cobrado (destacado), Forma de pago, Tipo de pago, Saldo antes, Saldo después de confirmar. Recuadro ámbar: "El saldo de la nota **no cambió todavía**. Entrá a Tickets › Cobranzas, revisá el comprobante y confirmá el cobro con su número."

### 12.3 Nota de venta anulada (Detalles, al anular)

- **Destinatarios:** correo del vendedor del pedido (búsqueda en `dim_VENDEDORES` por código) + usuario que anula + samuel.jara@analytico.com.py + `gMailAnulacionFijos` (4 direcciones) + agonzales@ + los 5 grupos de encargados.
- **Asunto:** `Nota de venta anulada · {NOTA_NUMERO} · {CLIENTE_NOMBRE}`
- **Cuerpo:** título rojo "Nota de venta anulada"; Nota, Pedido, Cliente, Almacén (de la proforma); recuadro rojo con "Motivo:" y el texto; "Anulada por {gUsuario} el dd/mm/yyyy hh:mm."; nota gris "Las cantidades volvieron a quedar pendientes de despacho."

### 12.4 Cobro confirmado (Detalles › Cobranzas, tras actualizar el saldo)

- **Destinatarios:** correo del vendedor de la cobranza (búsqueda por código) + samuel.jara@analytico.com.py. (Si el vendedor no tiene correo, la cadena queda ";samuel…" con separador inicial.)
- **Asunto:** `Cobro confirmado · Nota {NOTA_NUMERO} · {CLIENTE_NOMBRE}`
- **Cuerpo:** título "Cobro confirmado"; Nota de venta, Cliente, Vendedor; tabla con Monto cobrado, Forma de pago, Total de la nota, Pagado acumulado, Saldo pendiente; "Confirmado por {gVendNom} el dd/mm/yyyy hh:mm."; si el saldo ≤ 0: "La nota quedó totalmente pagada." (en verde). **No incluye el N° de cobranza.**

### 12.5 Corrección de N° de cobranza

- **Destinatarios:** vendedor de la cobranza + usuario que corrige + samuel.jara@analytico.com.py.
- **Asunto:** `Corrección de N° de cobranza · Nota {NOTA_NUMERO} · {CLIENTE_NOMBRE}`
- **Cuerpo:** cabecera "PLÁSTICOS CARMEN / Corrección de número de cobranza"; recuadro "NÚMERO DE COBRANZA" con el anterior tachado → el nuevo; tabla con Nota de venta, Cliente, Monto, Vendedor; bloque "JUSTIFICATIVO" con el motivo; "Corregido por {gUsuario} el … El monto y el saldo de la nota no cambiaron."

### 12.6 Proforma modificada (Detalles, al guardar una edición con cambios)

- **Disparo:** `gHuboCambiosPedido` verdadero y, por cada proforma (claves de líneas actuales ∪ eliminadas, orden alfabético), si tiene líneas nuevas/modificadas/eliminadas **o** si cambió la cabecera (forma de pago/días). Un correo por proforma.
- **Destinatarios (iguales para todas):** agonzales@ (rol almacén; **no** se excluye aunque el vendedor despache) + correo del vendedor del pedido + usuario que editó + samuel.jara@analytico.com.py.
- **Asunto:** `Proforma modificada · {almacén} · N° {nro}` (o ` · sin número`) ` · {PEDIDO_NUMERO} · {CLIENTE_NOMBRE}`
- **Cuerpo:**
  - Cabecera "PLÁSTICOS CARMEN · PROFORMA MODIFICADA", almacén en grande, "Proforma {nro|sin número} · pedido {número}".
  - Franja ámbar "Modificado por {gUsuario} el dd/mm/yyyy hh:mm".
  - "QUÉ CAMBIÓ EN ESTA PROFORMA" (lista): cambios de cabecera ("Forma de pago: A → B"; "Días de crédito: A → B · vence F1 → F2"), que se repiten en todos los correos; y por línea: etiqueta verde **NUEVO** (descripción — cant unidad × Bs precio = Bs subtotal), ámbar **CAMBIO** (cantidad ~~anterior~~ → nueva, precio ~~anterior~~ → nuevo, subtotal ~~anterior~~ → nuevo) o roja **ELIMINADO** (~~descripción~~ — eran cant unidad × Bs precio).
  - "Subtotal de la proforma: ~~Bs antes~~ → Bs ahora" (o "(sin cambio)") · "X kg".
  - "DATOS": Cliente (código), Dirección, Teléfono, Vendedor, Fecha del pedido, Forma de pago (· N días · vence F).
  - "PRODUCTOS DE ESTA PROFORMA": tabla Código, Producto, Cantidad, Precio Bs, Subtotal Bs (filas nuevas o modificadas resaltadas en #FFFBEF) y "SUBTOTAL PROFORMA Bs".
  - "Total del pedido completo: Bs X · este correo cubre solo {almacén}."
  - Pie: "Correo automático de la app Tomador de pedidos."
- Si falla: Warning "El pedido se guardó pero el correo de {almacén} NO se envió a: {dest}. Avisales a mano."

### 12.7 Eventos que NO envían correo

Registro de visita, numeración de proforma, creación de nota de venta, registro de cobranza sin pago y relevamiento de competencia.

---

## 13. Defectos, rarezas y problemas de delegación detectados

### 13.1 Defectos funcionales

1. **Micronaje mal mostrado** (Pedido): la etiqueta "Micronaje" muestra `PRODUCTO_JABITAS_POR_JABA` en lugar de `PRODUCTO_MICRONAJE`. (La línea guardada sí lleva el micronaje correcto.)
2. **Botón "Bandejas"** (Competencia) graba `COMP_TIPO = "Bobinas"`.
3. **Precio y cantidad en Competencia** tienen como valor por defecto el texto literal "Text input".
4. **Producto duplicado en el carrito** (Pedido): no se valida. Dos líneas con el mismo código en la misma proforma comparten el cálculo de despachado (Σ por código), por lo que el pendiente de cada una queda mal (se puede despachar de más o bloquearse). La edición sí lo impide.
5. **`PEDIDO_ESTADO_DESPACHO` no se escribe al crear el pedido.** Depende de un valor por defecto `Sin proforma` en SharePoint; si no existe, el filtro "Sin proforma" no lo encuentra y, al numerar la proforma, no transiciona a `Proformado` (la condición exige `= "Sin proforma"`).
6. **El estado de pago del pedido nunca refleja las cobranzas:** las confirmaciones actualizan solo la nota. `PEDIDO_TOTAL_PAGADO` queda en 0 y `PEDIDO_ESTADO_PAGO` en `Pendiente` para siempre, aunque las notas estén pagadas; el chip de pago de la lista de pedidos es engañoso.
7. **Columnas muertas:** `LINEA_CANTIDAD_DESPACHADA` y `PROFORMA_TOTAL_DESPACHADO` (siempre 0); `PEDIDO_PROFORMA_NUMERO` (control oculto). `PROFORMA_ESTADO` almacenado solo se actualiza al guardar el número (queda `Pendiente` aunque se despache todo), mientras la pantalla calcula otro valor: el color de fondo usa el valor almacenado y el texto el calculado.
8. **Notas anuladas cuentan como despachadas al recargar:** al reabrir el pedido, `colTicketNotaLineas` incluye las líneas de notas anuladas; el texto de estado de la proforma, "producto(s) pendiente(s)" y la etiqueta "despachado X" de productos las suman (el pendiente para despachar sí usa solo las activas). Justo después de anular, `colLineasActivas` **no** se actualiza, por lo que los candados 🔒, la editabilidad del N° de proforma y `gProductosEditables` quedan desactualizados hasta volver a seleccionar el pedido.
9. **Unicidad de `NOTA_NUMERO` solo dentro del pedido.** Dos pedidos pueden tener notas con el mismo número; como notas, líneas y cobranzas se vinculan por `NOTA_NUMERO`, el detalle o el cobro podrían aplicarse a la nota equivocada (la confirmación usa `LookUp` por número y toma la primera).
10. **La creación de nota continúa aunque falle el Patch de datos** de la nota: igual crea las líneas y la nota queda sin total/estado. `frmNota` no tiene `OnFailure` (falla silenciosa).
11. **Número de proforma:** sin validación de unicidad; si el Patch falla, igual se actualiza la memoria y se muestra "Proforma … guardada".
12. **Cobranza – pendientes desactualizados:** `colCobrPend` se carga solo al entrar a la pantalla; tras guardar una cobranza, el disponible de esa nota no descuenta lo recién cargado. Se puede registrar dos veces el saldo completo hasta salir y volver a entrar.
13. **Cobranza – "No" (no hubo pago) no limpia `gFormaPago`:** si antes se eligió Transferencia, se graba esa forma en una cobranza sin pago.
14. **Cobranzas sin pago** quedan para siempre en `Pendiente de confirmación`, no aparecen en ninguna lista y además **bloquean la anulación** de la nota ("tiene cobranzas registradas").
15. **"Completo":** al pulsarlo fija el monto en el saldo total de la nota, pero el guardado usa `gSaldoDisp` (correcto); confuso pero consistente al guardar.
16. **Mensaje "Adjuntá el comprobante de la depósito bancario":** concordancia gramatical incorrecta.
17. **`COBRANZA_CONFIRMADA_POR = gVendNom`** (identidad atribuida) en lugar de la real. En la práctica coinciden para el rol cobranzas, pero es conceptualmente incorrecto; el correo de confirmación también usa `gVendNom`.
18. **Edición sin rollback:** si la cabecera se guarda y las líneas fallan, el pedido queda con el total nuevo y las líneas viejas. La cabecera se reescribe (con "Modificado por") aunque no haya cambios reales.
19. **La edición no recalcula `PEDIDO_ESTADO_DESPACHO`:** si a un pedido "Despachado total" se le agrega un producto de un almacén nuevo, sigue figurando "Despachado total" y no aparece en "Sin proforma".
20. **Proformas numeradas que quedan sin líneas** tras una edición no se borran ni se actualiza su total (queda el total anterior).
21. **El cambio de forma de pago/días en la edición no se propaga** a las notas ya emitidas (`NOTA_FORMA_PAGO`, `NOTA_DIAS_CREDITO`, `NOTA_FECHA_VENCIMIENTO`).
22. **Vencimiento inconsistente:** al crear = `Now() + días` (o `Now()` en contado); al editar = `PEDIDO_FECHA + días`; el contado en edición guarda días = 0 (el alta guarda vacío).
23. **Visita y pedido desacoplados:** después de registrar la visita se puede cambiar el cliente, el motivo o la forma de pago en pantalla; el pedido se graba con los valores en memoria (`varCliente`, `gVisitaFormaPago`) y no con los de la visita. Si se cambia a Crédito después de registrar, `gDiasCredito` queda vacío y el vencimiento = fecha del pedido.
24. **Regla del correo de pedido nuevo con `VENDEDOR_DESPACHA`** evaluada sobre el usuario real, no sobre el vendedor atribuido.
25. **Competencia usa la identidad atribuida** (`gVendCod`) sin registrar `CARGADO_POR`; si un supervisor dejó seleccionado otro vendedor en Visita, el relevamiento queda a nombre de ese vendedor.
26. **`PEDIDO_NUMERO` puede colisionar** si se guardan dos pedidos del mismo vendedor en el mismo segundo (p. ej., supervisor y vendedor simultáneos). No hay restricción de unicidad.
27. **Proforma `-SIN`:** los productos sin prefijo conocido generan una proforma "SIN ALMACÉN ASIGNADO" sin encargado ni ruteo.
28. **Botón "Pendientes"** no se reinicia al entrar; el título "COBRANZAS POR CONFIRMAR" del rol cobranzas aparece aunque el filtro esté apagado (también se ven las confirmadas).
29. **Dirección en el correo de modificación** no trata "0" como vacío (el de pedido nuevo sí).
30. **Correo de confirmación** con vendedor sin correo genera destinatarios ";samuel…".
31. **Código muerto o heredado:** `gPuedeProformar`, `gPuedeEditarPedido`, `colVendDespachan`, `gMailAlmTicket`, `gMailModDest`, `gMailModCuerpo`, `gMailModFilas`, `gMailModElim`, `gBorrarPedidoConf`, `gProfEditId`, `gNvId`, `gNvCant`; ícono de borrar cobranza oculto; campo de proforma a nivel pedido oculto.
32. **Destinatario externo fijo** `samuel.jara@analytico.com.py` en **todos** los correos (datos de clientes y montos). Debe validarse con la empresa si corresponde mantenerlo.
33. **Despacho: recorte silencioso** de la cantidad a despachar al pendiente (`Min`), por lo que la validación "supera lo pendiente" casi nunca se dispara y el usuario puede no notar el recorte.
34. **Rol por omisión:** cualquier usuario de Microsoft 365 **no registrado** en `dim_VENDEDORES` es tratado como vendedor (`gEsVendedor = true`), entra a Visita y ve la barra de navegación; solo lo detiene la validación "Tu usuario no está registrado como vendedor — avisá a sistemas".
35. **Almacén y cobranzas no tienen barra de navegación** (dependen del encabezado); el rol **cobrador** queda limitado a Cobranza.

### 13.2 Delegación (límites de SharePoint en Power Apps, típicamente 500/2000 filas)

- `Distinct(fact_COMPETENCIA, COMPETIDOR)` y `Distinct(dim_PRODUCTOS, PRODUCTO_UNIDAD)`: no delegables → resultados incompletos al crecer las listas.
- `Filter(fact_NOTAS_VENTA, … NOTA_ANULADA <> true)`: `<>` sobre Sí/No no es delegable en SharePoint → la lista de notas pendientes de Cobranza puede omitir notas.
- `Filter(dim_VENDEDORES, Lower(Text(VENDEDOR_ROL.Value)) …)`: no delegable (tabla pequeña; aceptado).
- Búsquedas con `StartsWith` combinadas con `Or` e `IsBlank(s)`: delegación parcial; además solo buscan por **prefijo**.
- Edición: `Filter(fact_PROFORMAS …)` con `CountRows(Filter(colTicketLineas …))` anidado se evalúa localmente.
- `colCobrPend` carga **todas** las cobranzas pendientes de la empresa cada vez que se entra a Cobranza.
- Ventana de 90 días (`gDesde`) para pedidos y cobranzas: los pedidos más antiguos no aparecen en Detalles aunque tengan pendientes de despacho.

### 13.3 Concurrencia y consistencia

- No hay bloqueos ni control de versión: dos usuarios pueden despachar la misma proforma a la vez y exceder la cantidad (el pendiente se calcula con datos en memoria cargados al seleccionar el pedido).
- Confirmación de cobranza: lectura–suma–escritura de `NOTA_TOTAL_PAGADO` sin bloqueo; dos confirmaciones simultáneas sobre la misma nota pueden perder una suma. Sí existe protección contra la doble confirmación de la **misma** cobranza (relectura del estado).
- Las "transacciones" son compensaciones manuales; un corte de red a mitad de camino puede dejar registros huérfanos (los propios mensajes lo advierten).

---

## 14. Recomendaciones para la reimplementación en Laravel 13

### 14.1 Modelo de datos

- Tablas normalizadas con **claves foráneas reales**: `clientes`, `productos`, `almacenes` (P11–P15 con nombre, prefijos de producto y correos de encargados), `users` (rol + indicadores `carga_por_otros` y `despacha`), `visitas`, `pedidos`, `pedido_lineas`, `proformas`, `notas_venta`, `nota_lineas` (con FK a `pedido_lineas.id`, no por código), `cobranzas`, `cobranza_numero_historial`, `relevamientos_competencia` y adjuntos (p. ej., Spatie Media Library).
- Restricciones **UNIQUE**: `pedidos.numero`, `proformas.clave`, `notas_venta.numero` (global), `cobranzas.numero`; evaluar con negocio `proformas.numero`.
- Guardar `cargado_por_id` (usuario real) y `vendedor_id` (atribuido) en visitas, pedidos, cobranzas y relevamientos.
- Montos en `DECIMAL(14,2)`; cantidades en `DECIMAL(14,3)` con la regla de enteros para unidad `PZA`.
- Conservar las columnas "foto" del producto en `pedido_lineas` (descripción, unidad, micronaje, pesos, empaques).
- Enumeraciones PHP (backed enums) para todos los estados y opciones de las secciones 3 y 5.

### 14.2 Lógica

- `DB::transaction()` para: guardar pedido (cabecera + líneas + proformas), crear nota (nota + líneas + adjunto), anular nota, confirmar cobranza (cobranza + nota) y editar pedido. Esto reemplaza toda la compensación manual.
- Bloqueo pesimista (`lockForUpdate`) de la proforma al despachar y de la nota al confirmar cobranzas.
- Calcular despachado/pendiente con consultas agregadas sobre `nota_lineas` de notas **no anuladas**, vinculadas por `pedido_linea_id` (o un campo cacheado actualizado dentro de la transacción).
- Un único servicio que recalcule `estado_despacho` del pedido y `estado` de la proforma, invocado tras crear nota, anular nota, numerar proforma y **editar pedido**.
- Definir con negocio si el pedido debe reflejar los pagos (Σ de sus notas) para corregir 13.1.6, o eliminar los campos de pago de la cabecera.
- `PEDIDO_NUMERO`: conservar el formato `PED-{código}-{yyyymmdd-hhmmss}` por compatibilidad, garantizando unicidad (índice único + reintento/sufijo).
- Disponible para cobrar calculado en el servidor al validar (no en caché del cliente).
- Form Requests con los **mensajes literales** de este documento.
- Policies/Gates que repliquen exactamente 2.3 y 2.4 (ver todos los pedidos, proformar/despachar/editar ticket, confirmar cobranza, etc.), siempre contra el usuario autenticado real.
- Autenticación con Microsoft Entra ID (Socialite) para mantener la identidad por correo; rechazar usuarios no registrados en lugar de tratarlos como vendedores.
- Estado de "visita activa" y "carrito" en el servidor (sesión o Livewire), con la misma confirmación al descartar.

### 14.3 Correos

- Mailables con plantillas Blade que reproduzcan los 6 correos de la sección 12; envío en **cola** (`ShouldQueue`) con reintentos y registro de fallos (en lugar de "Avisales a mano").
- Remitente configurable (`reportesbi@plasticoscarmen.com`) vía Microsoft Graph o SMTP de Office 365.
- Destinatarios en configuración o tabla editable por evento y por almacén; evaluar el ruteo de anulaciones **solo** al encargado del almacén de la nota y deduplicar direcciones.
- Revisar con la empresa la copia a samuel.jara@analytico.com.py.

### 14.4 Interfaz

- Diseño mobile-first (la app original es de teléfono), con navegación según rol (corrige la falta de navegación de almacén, cobranzas y cobrador).
- Búsquedas por "contiene" y paginación del lado del servidor en lugar de la ventana fija de 90 días (mantenerla como filtro por defecto, con opción de ampliar).
- Corregir los defectos de 13.1 (micronaje, Bandejas, valores por defecto, duplicados, gramática del mensaje de comprobante).

### 14.5 Migración de datos

- Importar las listas de SharePoint resolviendo relaciones por claves de texto (`PEDIDO_NUMERO`, `PROFORMA_CLAVE`, `NOTA_NUMERO`); reportar números de nota duplicados, líneas de nota que no casan con líneas de pedido por código, pedidos con estado de despacho vacío y cobranzas sin pago en estado pendiente.
- Recalcular los estados de proforma y pedido tras la importación con el nuevo servicio.
- Migrar los adjuntos ("Datos adjuntos") de notas y cobranzas.
