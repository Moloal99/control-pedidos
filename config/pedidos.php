<?php

/*
|--------------------------------------------------------------------------
| Control de Pedidos · Plásticos Carmen
|--------------------------------------------------------------------------
| Configuracion del negocio. Los valores de «ajustes» son los de omision:
| la administracion los puede cambiar en pantalla (tabla `ajustes`).
*/

return [

    // demo: datos locales, sin SharePoint ni correo real.
    // sharepoint: SharePoint es la fuente de verdad (Microsoft Graph).
    'modo' => env('PEDIDOS_MODO', 'demo'),

    // Clave para entrar a la demostracion publicada (Render). Vacia = sin clave (solo equipo local).
    'demo_clave' => env('PEDIDOS_DEMO_CLAVE'),

    // Enlace de regreso al portal central
    'central_url' => env('CENTRAL_URL', 'https://central.plasticoscarmen.com'),

    'ajustes' => [
        // Casilla del ROL ALMACEN: pedido nuevo, proforma modificada y nota anulada
        'correo_rol_almacen' => 'agonzales@plasticoscarmen.com',
        // Casilla del ROL COBRANZAS: aviso de cobranza para confirmar
        'correo_rol_cobranzas' => 'caja@plasticoscarmen.com',
        // Reciben SIEMPRE el aviso de nota de venta anulada
        'correos_anulacion_fijos' => 'acuellar@plasticoscarmen.com;fquenta@plasticoscarmen.com;jmontenegro@plasticoscarmen.com;almacenbolsa@plasticoscarmen.com',
        // Copia oculta en todos los avisos (en la Power App: samuel.jara@analytico.com.py).
        // Queda vacia hasta que la empresa confirme si se mantiene.
        'correo_copia_externa' => env('PEDIDOS_COPIA_EXTERNA', ''),
        // true: la anulacion avisa a los encargados de TODOS los almacenes (como la Power App)
        // false: solo a los del almacen de la nota
        'anulacion_avisa_todos_los_almacenes' => '1',
        // Ventana por omision de las listas de pedidos y cobranzas
        'ventana_dias' => '90',
    ],

    // Almacenes iniciales (el seeder los carga; despues se editan en pantalla)
    'almacenes' => [
        ['codigo' => 'P11', 'nombre' => 'ALMACÉN P11 — EXTRUSIÓN', 'area' => 'Extrusión', 'prefijos' => '5T201,5T202', 'correos_encargados' => 'mrendon@plasticoscarmen.com;dpaz@plasticoscarmen.com', 'color' => '#063381'],
        ['codigo' => 'P12', 'nombre' => 'ALMACÉN P12 — TERMOFORMADO', 'area' => 'Termoformado', 'prefijos' => '5T203', 'correos_encargados' => 'njustiniano@plasticoscarmen.com;ggutierrez@plasticoscarmen.com', 'color' => '#2b56a6'],
        ['codigo' => 'P13', 'nombre' => 'ALMACÉN P13 — EXPANDIDO', 'area' => 'Expandido', 'prefijos' => '5T204', 'correos_encargados' => 'njustiniano@plasticoscarmen.com;tjustiniano@plasticoscarmen.com', 'color' => '#0f766e'],
        ['codigo' => 'P14', 'nombre' => 'ALMACÉN P14 — INYECCIÓN', 'area' => 'Inyección', 'prefijos' => '5T205', 'correos_encargados' => 'tjustiniano@plasticoscarmen.com', 'color' => '#7c3aed'],
        ['codigo' => 'P15', 'nombre' => 'ALMACÉN P15 — RAFIA', 'area' => 'Rafia', 'prefijos' => '5T206', 'correos_encargados' => 'faguilar@plasticoscarmen.com;ecardenas@plasticoscarmen.com', 'color' => '#c2410c'],
        ['codigo' => 'SIN', 'nombre' => 'SIN ALMACÉN ASIGNADO', 'area' => 'Sin asignar', 'prefijos' => '', 'correos_encargados' => '', 'color' => '#6a7592'],
    ],

    'comprobantes' => [
        'disco' => env('PEDIDOS_DISCO_COMPROBANTES', 'local'),
        'max_kb' => 51200,
        'tipos' => 'pdf,jpg,jpeg,png,webp,heic',
    ],
];
