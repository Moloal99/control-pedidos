<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestros: espejo de dim_CLIENTES y dim_PRODUCTOS, y la configuracion de
 * almacenes (antes fija en el OnStart de la Power App).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sp_id')->nullable()->unique();
            $table->string('codigo', 40)->index();
            $table->string('nombre')->index();
            $table->string('direccion', 500)->nullable();
            $table->string('telefono', 80)->nullable();
            $table->timestamps();
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sp_id')->nullable()->unique();
            $table->string('codigo', 40)->index();
            $table->string('descripcion')->index();
            $table->string('unidad', 20)->nullable();
            $table->string('micronaje', 30)->nullable();
            $table->decimal('peso_promedio', 14, 4)->default(0);
            $table->decimal('bolsas_por_paquete', 14, 2)->nullable();
            $table->decimal('paquetes_por_jaba', 14, 2)->nullable();
            $table->decimal('jabitas_por_jaba', 14, 2)->nullable();
            $table->decimal('jabas_por_bulto', 14, 2)->nullable();
            $table->decimal('peso_por_bolsa', 14, 4)->nullable();
            $table->decimal('peso_por_bulto', 14, 4)->nullable();
            $table->decimal('peso_por_paquete', 14, 4)->nullable();
            $table->decimal('peso_por_jaba', 14, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('almacenes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            // Texto exacto que se guarda en SharePoint: "ALMACÉN P11 — EXTRUSIÓN"
            $table->string('nombre', 80);
            $table->string('area', 40);
            // Prefijos de PRODUCTO_TERMINADO_CODIGO, separados por coma
            $table->string('prefijos', 120)->default('');
            // Encargados: reciben el aviso de nota anulada de SU almacen
            $table->string('correos_encargados', 500)->default('');
            $table->string('color', 9)->default('#063381');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        // Ajustes editables desde la administracion (correos por rol, etc.)
        Schema::create('ajustes', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
        Schema::dropIfExists('almacenes');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('clientes');
    }
};
