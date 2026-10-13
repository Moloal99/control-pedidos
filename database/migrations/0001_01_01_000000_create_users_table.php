<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuarios del sistema = espejo de la lista dim_VENDEDORES de SharePoint.
 * El rol y los indicadores (carga por otros, despacha) vienen de allá; la
 * identidad se resuelve por correo al iniciar sesión con Microsoft 365.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sp_id')->nullable()->unique();
            $table->string('codigo', 30)->nullable()->index();
            $table->string('name');
            $table->string('email')->unique();
            // admin, almacen, cobranzas, cobrador, user
            $table->string('rol', 20)->default('user');
            $table->boolean('carga_por_otros')->default(false);
            $table->boolean('despacha')->default(false);
            $table->boolean('activo')->default(true);
            // Object id de Entra ID: se fija en el primer inicio de sesion
            $table->string('ms_oid', 64)->nullable()->unique();
            $table->string('tema', 10)->default('sistema');
            $table->timestamp('ultimo_acceso_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
