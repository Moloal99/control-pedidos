<?php

namespace App\Correo;

use App\SharePoint\TokenMicrosoft;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Envio de correo por Microsoft Graph (/users/{remitente}/sendMail), con el
 * mismo buzon que usaba el conector Office365Outlook de la Power App.
 * MAIL_MAILER=graph
 */
class TransporteGraph extends AbstractTransport
{
    public function __construct(private readonly TokenMicrosoft $token)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $mensaje): void
    {
        $email = MessageConverter::toEmail($mensaje->getOriginalMessage());
        $remitente = config('microsoft.correo.remitente');

        $direcciones = fn (array $lista) => array_map(fn (Address $a) => [
            'emailAddress' => array_filter(['address' => $a->getAddress(), 'name' => $a->getName() ?: null]),
        ], $lista);

        $cuerpo = [
            'message' => array_filter([
                'subject' => (string) $email->getSubject(),
                'body' => [
                    'contentType' => $email->getHtmlBody() !== null ? 'HTML' : 'Text',
                    'content' => (string) ($email->getHtmlBody() ?? $email->getTextBody()),
                ],
                'toRecipients' => $direcciones($email->getTo()),
                'ccRecipients' => $direcciones($email->getCc()),
                'bccRecipients' => $direcciones($email->getBcc()),
                'replyTo' => $direcciones($email->getReplyTo()),
                'attachments' => $this->adjuntos($email),
            ]),
            'saveToSentItems' => true,
        ];

        $respuesta = Http::withToken($this->token->graph())
            ->timeout(30)
            ->post("https://graph.microsoft.com/v1.0/users/{$remitente}/sendMail", $cuerpo);

        if (! $respuesta->successful()) {
            throw new RuntimeException('Graph rechazó el correo: '.($respuesta->json('error.message') ?? $respuesta->body()));
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private function adjuntos(Email $email): array
    {
        $salida = [];

        foreach ($email->getAttachments() as $adjunto) {
            $salida[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => (string) ($adjunto->getFilename() ?? 'adjunto'),
                'contentType' => $adjunto->getMediaType().'/'.$adjunto->getMediaSubtype(),
                'contentBytes' => base64_encode($adjunto->getBody()),
            ];
        }

        return $salida;
    }

    public function __toString(): string
    {
        return 'graph';
    }
}
