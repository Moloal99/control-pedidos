<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operacion comercial: espejo local de las listas fact_ de SharePoint.
 *
 * Cada tabla guarda `sp_id` (ID del elemento en la lista) y conserva las
 * columnas de texto que SharePoint usa como vinculo (PEDIDO_NUMERO,
 * PROFORMA_CLAVE, NOTA_NUMERO) para que la sincronizacion sea de ida y vuelta.
 * Ademas se agregan claves foraneas reales para que aqui las relaciones no
 * dependan de un texto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sp_id')->nullable()->unique();
            $table->string('titulo');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_codigo', 40)->nullable();
            $table->string('cliente_nombre')->nullable();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('vendedor_codigo', 30)->nullable()->index();
            $table->string('vendedor_nombre')->nullable();
            $table->foreignId('cargado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cargado_por')->nullable();
            $table->string('motivo', 20);
            $table->string('tipo_contacto', 20);
            $table->string('forma_pago', 20)->nullable();
            $table->unsignedSmallInteger('dias_credito')->nullable();
            $table->text('comentario')->nullable();
            $table->dateTime('fecha')->index();
            $table->timestamps();
        });

        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sp_id')->nullable()->unique();
            $table->string('numero', 60)->unique();
            $table->foreignId('visita_id')->nullable()->constrained('visitas')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_codigo', 40)->nullable()->index();
            $table->string('cliente_nombre')->nullable();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('vendedor_codigo', 30)->nullable()->index();
            $table->string('vendedor_nombre')->nullable();
            $table->foreignId('cargado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cargado_por')->nullable();
            $table->string('tipo_contacto', 20)->nullable();
            $table->string('forma_pago', 20)->nullable();
            $table->unsignedSmallInteger('dias_credito')->nullable();
            $table->dateTime('fecha')->index();
            $table->dateTime('fecha_vencimiento')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('total_pagado', 14, 2)->default(0);
            $table->decimal('saldo_pendiente', 14, 2)->default(0);
            $table->string('estado_pago', 20)->default('Pendiente');
            $table->string('estado_despacho', 30)->default('Sin proforma')->index();
            $table->string('modificado_por')->nullable();
            $table->dateTime('fecha_modificacion')->nullable();
            $table->timestamps();
        });

        Schema::create('proformas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sp_id')->nullable()->unique();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->string('pedido_numero', 60)->index();
            $table->string('clave', 70)->unique();
            $table->string('almacen_codigo', 10)->index();
            $table->string('almacen', 80);
            $table->string('numero', 40)->nullable()->index();
            $table->dateTime('fecha');
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('total_despachado', 14, 2)->default(0);
            $table->string('estado', 20)->default('Sin número');
            $table->timestamps();
        });

        Schema::create('pedido_lineas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sp_id')->nullable()->unique();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('proforma_id')->nullable()->constrained('proformas')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('pedido_numero', 60)->index();
            $table->string('proforma_clave', 70)->index();
            $table->string('almacen', 80);
            // Foto del producto al momento de la venta
            $table->string('producto_codigo', 40)->index();
            $table->string('producto_descripcion');
            $table->string('producto_unidad', 20)->nullable();
            $table->string('producto_micronaje', 30)->nullable();
            $table->decimal('producto_peso_promedio', 14, 4)->default(0);
            $table->decimal('producto_bolsas_por_paquete', 14, 2)->nullable();
            $table->decimal('producto_paquetes_por_jaba', 14, 2)->nullable();
            $table->decimal('producto_jabitas_por_jaba', 14, 2)->nullable();
            $table->decimal('producto_jabas_por_bulto', 14, 2)->nullable();
            $table->decimal('producto_peso_por_bolsa', 14, 4)->nullable();
            $table->decimal('producto_peso_por_bulto', 14, 4)->nullable();
            $table->decimal('producto_peso_por_paquete', 14, 4)->nullable();
            $table->decimal('producto_peso_por_jaba', 14, 4)->nullable();
            $table->decimal('cantidad', 14, 3);
            $table->decimal('precio_unitario', 14, 4);
            $table->decimal('cantidad_despachada', 14, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('notas_venta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sp_id')->nullable()->unique();
            $table->string('numero', 40)->index();
            $table->foreignId('proforma_id')->nullable()->constrained('proformas')->nullOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->string('proforma_clave', 70)->index();
            $table->string('proforma_numero', 40)->nullable();
            $table->string('pedido_numero', 60)->index();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_codigo', 40)->nullable();
            $table->string('cliente_nombre')->nullable();
            $table->string('vendedor_codigo', 30)->nullable()->index();
            $table->string('vendedor_nombre')->nullable();
            $table->string('almacen', 80)->nullable();
            $table->dateTime('fecha')->index();
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('total_pagado', 14, 2)->default(0);
            $table->decimal('saldo_pendiente', 14, 2)->default(0);
            $table->string('estado_pago', 20)->default('Pendiente');
            $table->string('forma_pago', 20)->nullable();
            $table->unsignedSmallInteger('dias_credito')->nullable();
            $table->dateTime('fecha_vencimiento')->nullable();
            $table->boolean('anulada')->default(false)->index();
            $table->text('anulada_motivo')->nullable();
            $table->string('anulada_por')->nullable();
            $table->dateTime('anulada_fecha')->nullable();
            $table->string('comprobante_nombre')->nullable();
            $table->string('comprobante_ruta')->nullable();
            $table->string('creado_por')->nullable();
            $table->timestamps();
        });

        Schema::create('nota_lineas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sp_id')->nullable()->unique();
            $table->foreignId('nota_id')->constrained('notas_venta')->cascadeOnDelete();
            $table->foreignId('pedido_linea_id')->nullable()->constrained('pedido_lineas')->nullOnDelete();
            $table->string('nota_numero', 40)->index();
            $table->string('proforma_clave', 70)->index();
            $table->string('producto_codigo', 40);
            $table->string('producto_descripcion');
            $table->string('producto_unidad', 20)->nullable();
            $table->string('almacen', 80)->nullable();
            $table->decimal('cantidad', 14, 3);
            $table->decimal('precio_unitario', 14, 4);
            $table->decimal('producto_peso_promedio', 14, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('cobranzas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sp_id')->nullable()->unique();
            $table->foreignId('nota_id')->nullable()->constrained('notas_venta')->nullOnDelete();
            $table->string('nota_numero', 40)->index();
            $table->string('proforma_clave', 70)->nullable();
            $table->string('pedido_numero', 60)->nullable()->index();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_codigo', 40)->nullable();
            $table->string('cliente_nombre')->nullable();
            $table->string('vendedor_codigo', 30)->nullable()->index();
            $table->string('vendedor_nombre')->nullable();
            $table->foreignId('cargado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cargado_por')->nullable();
            $table->dateTime('fecha')->index();
            $table->boolean('hubo_pago')->default(false);
            $table->string('tipo_pago', 20)->nullable();
            $table->decimal('monto', 14, 2)->default(0);
            $table->string('forma_pago', 30)->nullable();
            $table->date('fecha_proximo_pago')->nullable();
            $table->decimal('nota_total', 14, 2)->default(0);
            $table->decimal('saldo_antes', 14, 2)->default(0);
            $table->decimal('saldo_despues', 14, 2)->default(0);
            $table->string('estado', 40)->default('Pendiente de confirmación')->index();
            $table->string('numero', 40)->nullable()->index();
            $table->string('confirmada_por')->nullable();
            $table->dateTime('fecha_confirmacion')->nullable();
            $table->string('numero_anterior', 40)->nullable();
            $table->string('numero_motivo', 500)->nullable();
            $table->string('numero_modif_por')->nullable();
            $table->dateTime('fecha_modif_numero')->nullable();
            $table->string('comprobante_nombre')->nullable();
            $table->string('comprobante_ruta')->nullable();
            $table->timestamps();
        });

        Schema::create('relevamientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sp_id')->nullable()->unique();
            $table->string('titulo', 80);
            $table->dateTime('fecha')->index();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('vendedor_codigo', 30)->nullable()->index();
            $table->string('vendedor_nombre')->nullable();
            $table->foreignId('cargado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_codigo', 40)->nullable();
            $table->string('cliente_nombre')->nullable();
            $table->string('competidor', 120)->index();
            $table->string('tipo', 20);
            $table->string('descripcion');
            $table->string('medida', 60)->nullable();
            $table->string('unidad', 20)->nullable();
            $table->decimal('precio', 14, 2);
            $table->decimal('cantidad', 14, 3);
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['relevamientos', 'cobranzas', 'nota_lineas', 'notas_venta', 'pedido_lineas', 'proformas', 'pedidos', 'visitas'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
