<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad (misma logica que central.plasticoscarmen.com): quien entro,
 * cuanto duro su sesion, por que pantallas paso, cuanto estuvo en cada una y
 * que hizo. Las tablas llevan prefijo `traza_` porque «visitas» ya es una
 * entidad del negocio (visita a cliente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traza_sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_id', 100)->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->string('navegador', 80)->nullable();
            $table->string('plataforma', 40)->nullable();
            $table->string('agente', 500)->nullable();
            $table->timestamp('iniciada_at');
            $table->timestamp('ultima_at');
            $table->timestamp('cerrada_at')->nullable();
            // salida (cerro sesion), expirada (se le vencio), null = abierta
            $table->string('motivo_cierre', 20)->nullable();
            $table->unsignedInteger('segundos')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'iniciada_at']);
        });

        Schema::create('traza_paginas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->nullable()->constrained('traza_sesiones')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ruta', 120)->nullable()->index();
            $table->string('url', 500);
            $table->string('titulo', 160);
            $table->timestamp('entrada_at');
            $table->timestamp('salida_at')->nullable();
            $table->unsignedInteger('segundos')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'entrada_at']);
        });

        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->nullable()->constrained('traza_sesiones')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Se guarda el nombre por si la cuenta se elimina despues
            $table->string('usuario_nombre', 120)->nullable();
            $table->string('accion', 20)->index();
            $table->string('entidad', 60)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            // Documento de negocio legible (PED-..., nota 00027520) para la linea de tiempo
            $table->string('referencia', 80)->nullable()->index();
            $table->string('descripcion', 300);
            $table->json('datos')->nullable();
            $table->string('ruta', 120)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['created_at']);
            $table->index(['entidad', 'entidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
        Schema::dropIfExists('traza_paginas');
        Schema::dropIfExists('traza_sesiones');
    }
};
