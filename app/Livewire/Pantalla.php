<?php

namespace App\Livewire;

use App\Models\User;
use App\SharePoint\ErrorSharePoint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Throwable;

/**
 * Base de las pantallas: usuario actual, avisos y una forma uniforme de
 * ejecutar operaciones de negocio mostrando el error correcto al usuario.
 */
abstract class Pantalla extends Component
{
    protected function usuario(): User
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        return $usuario;
    }

    protected function avisar(string $tipo, string $mensaje): void
    {
        $this->dispatch('aviso', tipo: $tipo, mensaje: $mensaje);
    }

    /**
     * Ejecuta una operacion. Si falla una regla de negocio, el mensaje se
     * muestra en el campo y como aviso; si falla SharePoint, se explica.
     *
     * @template T
     *
     * @param  callable(): T  $trabajo
     * @return T|null
     */
    protected function intentar(callable $trabajo, ?string $exito = null): mixed
    {
        $this->resetErrorBag();

        try {
            $resultado = $trabajo();

            if ($exito) {
                $this->avisar('exito', $exito);
            }

            return $resultado;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError($campo, $mensaje);
                }
            }

            $this->avisar('error', collect($e->errors())->flatten()->first() ?? 'Revise los datos.');
        } catch (ErrorSharePoint $e) {
            $this->avisar('error', 'No se guardó nada. '.$e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->avisar('error', 'Ocurrió un error inesperado y no se guardó nada. Si se repite, avise a Sistemas.');
        }

        return null;
    }
}
