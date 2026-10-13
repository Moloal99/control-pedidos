<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integracion con Microsoft 365: estado de la sincronizacion de cada lista de
 * SharePoint y registro de correos (reemplaza el «Avisales a mano» de la Power
 * App por una bandeja con reintento).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sincronizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('lista', 60)->unique();
            $table->timestamp('ultima_at')->nullable();
            // Marca Modified mas reciente leida: la proxima pasada pide desde aqui
            $table->timestamp('marca_at')->nullable();
            $table->unsignedInteger('filas')->default(0);
            $table->string('estado', 20)->default('pendiente');
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('correos', function (Blueprint $table) {
            $table->id();
            $table->string('evento', 40)->index();
            $table->string('asunto', 300);
            $table->text('destinatarios');
            $table->string('referencia', 80)->nullable()->index();
            $table->string('estado', 20)->default('en_cola')->index();
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->text('error')->nullable();
            // Mailable serializado: permite reintentar desde la administracion
            $table->longText('carga')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correos');
        Schema::dropIfExists('sincronizaciones');
    }
};
