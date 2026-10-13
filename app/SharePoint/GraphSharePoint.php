<?php

namespace App\SharePoint;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Conector real con las listas de SharePoint via Microsoft Graph.
 *
 * Las columnas se reciben por nombre visible y se traducen al nombre interno
 * leyendo la definicion de la lista (se cachea una hora). Asi el codigo usa
 * los mismos nombres que la Power App aunque internamente SharePoint haya
 * guardado «Title» o «CLIENTE_x005f_CODIGO».
 */
class GraphSharePoint implements SharePoint
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    public function __construct(private readonly TokenMicrosoft $token) {}

    public function habilitado(): bool
    {
        return true;
    }

    public function crear(string $lista, array $campos): ?int
    {
        $respuesta = $this->graph()->post($this->rutaLista($lista).'/items', [
            'fields' => $this->aInternos($lista, $campos),
        ]);

        $this->exigir($respuesta, "crear un elemento en {$lista}");

        return (int) $respuesta->json('id');
    }

    public function actualizar(string $lista, int $id, array $campos): void
    {
        $internos = $this->aInternos($lista, $campos);

        if ($internos === []) {
            return;
        }

        $respuesta = $this->graph()->patch($this->rutaLista($lista)."/items/{$id}/fields", $internos);

        $this->exigir($respuesta, "actualizar el elemento {$id} de {$lista}");
    }

    public function eliminar(string $lista, int $id): void
    {
        $respuesta = $this->graph()->delete($this->rutaLista($lista)."/items/{$id}");

        if ($respuesta->status() === 404) {
            return;
        }

        $this->exigir($respuesta, "eliminar el elemento {$id} de {$lista}");
    }

    public function obtener(string $lista, int $id): ?array
    {
        $respuesta = $this->graph()->get($this->rutaLista($lista)."/items/{$id}", ['expand' => 'fields']);

        if ($respuesta->status() === 404) {
            return null;
        }

        $this->exigir($respuesta, "leer el elemento {$id} de {$lista}");

        return $this->aVisibles($lista, (array) $respuesta->json('fields'));
    }

    public function cambios(string $lista, ?CarbonInterface $desde = null): iterable
    {
        $parametros = ['expand' => 'fields', '$top' => 500];

        if ($desde !== null) {
            $parametros['$filter'] = "fields/Modified ge '".$desde->copy()->utc()->format('Y-m-d\TH:i:s\Z')."'";
        }

        $url = $this->rutaLista($lista).'/items?'.http_build_query($parametros);

        while ($url) {
            $respuesta = $this->graph()
                ->withHeaders(['Prefer' => 'HonorNonIndexedQueriesWarningMayFailRandomly'])
                ->get($url);

            $this->exigir($respuesta, "leer la lista {$lista}");

            foreach ((array) $respuesta->json('value') as $elemento) {
                yield $this->aVisibles($lista, (array) ($elemento['fields'] ?? []));
            }

            $url = $respuesta->json('@odata.nextLink');
        }
    }

    public function ids(string $lista): array
    {
        $ids = [];
        $url = $this->rutaLista($lista).'/items?$select=id&$top=999';

        while ($url) {
            $respuesta = $this->graph()->get($url);
            $this->exigir($respuesta, "leer la lista {$lista}");

            foreach ((array) $respuesta->json('value') as $elemento) {
                $ids[] = (int) $elemento['id'];
            }

            $url = $respuesta->json('@odata.nextLink');
        }

        return $ids;
    }

    public function adjuntar(string $lista, int $id, string $nombre, string $contenido): void
    {
        if (! $this->token->tieneCertificado()) {
            throw new ErrorSharePoint('Para guardar comprobantes en SharePoint se necesita el certificado de la aplicación (ver la guía de Entra ID).');
        }

        $nombre = str_replace("'", "''", $nombre);
        $url = $this->rutaRest($lista)."/items({$id})/AttachmentFiles/add(FileName='".rawurlencode($nombre)."')";

        $respuesta = Http::withToken($this->token->sharepoint())
            ->accept('application/json;odata=nometadata')
            ->withBody($contenido, 'application/octet-stream')
            ->timeout(120)
            ->post($url);

        $this->exigir($respuesta, "adjuntar el comprobante en {$lista}");
    }

    public function descargarAdjunto(string $lista, int $id): ?array
    {
        if (! $this->token->tieneCertificado()) {
            return null;
        }

        $cliente = Http::withToken($this->token->sharepoint())->accept('application/json;odata=nometadata')->timeout(60);
        $respuesta = $cliente->get($this->rutaRest($lista)."/items({$id})/AttachmentFiles");

        if (! $respuesta->successful() || empty($respuesta->json('value'))) {
            return null;
        }

        $archivo = $respuesta->json('value.0');
        $ruta = str_replace("'", "''", (string) $archivo['ServerRelativeUrl']);
        $base = 'https://'.config('microsoft.sharepoint.host').rtrim((string) config('microsoft.sharepoint.sitio'), '/');
        $contenido = $cliente->get($base."/_api/web/GetFileByServerRelativeUrl('".rawurlencode($ruta)."')/\$value");

        return $contenido->successful()
            ? ['nombre' => (string) $archivo['FileName'], 'contenido' => $contenido->body()]
            : null;
    }

    // ------------------------------------------------------------ internos

    private function graph(): PendingRequest
    {
        return Http::withToken($this->token->graph())
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 800, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException
                || ($e instanceof \Illuminate\Http\Client\RequestException && in_array($e->response->status(), [429, 503, 504], true)), throw: false);
    }

    private function sitioId(): string
    {
        return Cache::remember('sp:sitio', now()->addDay(), function () {
            $host = config('microsoft.sharepoint.host');
            $sitio = '/'.trim((string) config('microsoft.sharepoint.sitio'), '/');
            $respuesta = $this->graph()->get(self::GRAPH."/sites/{$host}:{$sitio}");
            $this->exigir($respuesta, 'ubicar el sitio de SharePoint');

            return (string) $respuesta->json('id');
        });
    }

    private function listaId(string $lista): string
    {
        return Cache::remember('sp:lista:'.$lista, now()->addDay(), function () use ($lista) {
            $respuesta = $this->graph()->get(self::GRAPH.'/sites/'.$this->sitioId().'/lists', [
                '$filter' => "displayName eq '".str_replace("'", "''", $lista)."'",
                '$select' => 'id,displayName',
            ]);
            $this->exigir($respuesta, "ubicar la lista {$lista}");

            $id = $respuesta->json('value.0.id');

            if (! $id) {
                throw new ErrorSharePoint("La lista «{$lista}» no existe en el sitio configurado.");
            }

            return (string) $id;
        });
    }

    private function rutaLista(string $lista): string
    {
        return self::GRAPH.'/sites/'.$this->sitioId().'/lists/'.$this->listaId($lista);
    }

    private function rutaRest(string $lista): string
    {
        $base = 'https://'.config('microsoft.sharepoint.host').rtrim((string) config('microsoft.sharepoint.sitio'), '/');

        return $base."/_api/web/lists/getbytitle('".rawurlencode(str_replace("'", "''", $lista))."')";
    }

    /**
     * Nombre visible => nombre interno (y lookups marcados).
     *
     * @return array{nombres: array<string,string>, lookups: array<string,bool>}
     */
    public function columnas(string $lista): array
    {
        return Cache::remember('sp:columnas:'.$lista, now()->addHour(), function () use ($lista) {
            $respuesta = $this->graph()->get($this->rutaLista($lista).'/columns');
            $this->exigir($respuesta, "leer las columnas de {$lista}");

            $nombres = [];
            $lookups = [];

            foreach ((array) $respuesta->json('value') as $columna) {
                $interno = (string) $columna['name'];
                $nombres[(string) $columna['displayName']] = $interno;
                $nombres[$interno] ??= $interno;

                if (isset($columna['lookup'])) {
                    $lookups[$interno] = true;
                }
            }

            return ['nombres' => $nombres, 'lookups' => $lookups];
        });
    }

    /**
     * @param  array<string, mixed>  $campos
     * @return array<string, mixed>
     */
    private function aInternos(string $lista, array $campos): array
    {
        $nombres = $this->columnas($lista)['nombres'];
        $salida = [];

        foreach ($campos as $columna => $valor) {
            $esLookup = str_starts_with($columna, '@lookup:');
            $visible = $esLookup ? substr($columna, 8) : $columna;
            $interno = $nombres[$visible] ?? null;

            if ($interno === null) {
                Log::warning("SharePoint: la columna «{$visible}» no existe en {$lista}; se omite.");

                continue;
            }

            if ($esLookup) {
                $salida[$interno.'LookupId'] = $valor === null ? null : (string) $valor;
            } else {
                $salida[$interno] = $valor;
            }
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $campos
     * @return array<string, mixed>
     */
    private function aVisibles(string $lista, array $campos): array
    {
        $columnas = $this->columnas($lista);
        $visibles = [];

        // El primer nombre visible gana (Title puede estar renombrado)
        foreach ($columnas['nombres'] as $visible => $interno) {
            $visibles[$interno] ??= $visible;
        }

        $salida = ['id' => isset($campos['id']) ? (int) $campos['id'] : null, 'Modified' => $campos['Modified'] ?? null];

        foreach ($campos as $interno => $valor) {
            if (str_ends_with($interno, 'LookupId')) {
                $base = substr($interno, 0, -8);
                $salida['@lookup:'.($visibles[$base] ?? $base)] = $valor !== null && $valor !== '' ? (int) $valor : null;

                continue;
            }

            $salida[$visibles[$interno] ?? $interno] = $valor;
            // Se conserva tambien el interno: algunos mapas usan «Title»
            $salida[$interno] ??= $valor;
        }

        return $salida;
    }

    private function exigir(Response $respuesta, string $operacion): void
    {
        if ($respuesta->successful()) {
            return;
        }

        $detalle = $respuesta->json('error.message') ?? $respuesta->json('error.message.value') ?? mb_substr($respuesta->body(), 0, 300);

        Log::error("SharePoint: no se pudo {$operacion}", ['estado' => $respuesta->status(), 'detalle' => $detalle]);

        throw new ErrorSharePoint("SharePoint no permitió {$operacion} ({$respuesta->status()}): {$detalle}");
    }
}
