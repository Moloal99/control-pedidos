<?php

namespace App\Jobs;

use App\Enums\AccionBitacora;
use App\Models\Correo;
use App\Support\Rastro;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envia un aviso registrado en `correos`. Reintenta solo; si se agotan los
 * intentos queda «fallido» en la bandeja de la administracion, que puede
 * reenviarlo. Reemplaza el «Avísales a mano» de la Power App.
 */
class EnviarAviso implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public Correo $correo) {}

    public function handle(): void
    {
        $this->correo->increment('intentos');

        /** @var Mailable $mailable */
        $mailable = unserialize(base64_decode((string) $this->correo->carga));

        Mail::send($mailable);

        $this->correo->forceFill(['estado' => 'enviado', 'enviado_at' => now(), 'error' => null])->save();
    }

    public function failed(?Throwable $e): void
    {
        $this->correo->forceFill([
            'estado' => 'fallido',
            'error' => mb_substr((string) $e?->getMessage(), 0, 2000),
        ])->save();

        Rastro::registrar(
            AccionBitacora::Correo,
            "No se pudo enviar «{$this->correo->asunto}»",
            'Correo', $this->correo->getKey(),
            ['destinatarios' => $this->correo->destinatarios, 'error' => $e?->getMessage()],
            $this->correo->referencia,
        );
    }
}
