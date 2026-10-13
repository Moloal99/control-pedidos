<?php

namespace App\Correo;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Un aviso por correo. Lleva una «foto» de los datos (arreglos simples) para
 * que el correo refleje el momento del evento aunque se reenvie despues.
 */
class Aviso extends Mailable
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function __construct(
        public string $asunto,
        public string $vista,
        public array $datos,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
    }

    public function content(): Content
    {
        return new Content(view: 'correos.'.$this->vista, with: $this->datos + ['asunto' => $this->asunto]);
    }
}
