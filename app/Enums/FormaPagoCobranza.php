<?php

namespace App\Enums;

enum FormaPagoCobranza: string
{
    use ConEtiqueta;

    case Efectivo = 'Efectivo';
    case Transferencia = 'Transferencia';
    case Deposito = 'Depósito bancario';

    public function exigeComprobante(): bool
    {
        return $this !== self::Efectivo;
    }

    /** «de la transferencia» / «del depósito bancario» (corrige la concordancia original). */
    public function conArticulo(): string
    {
        return match ($this) {
            self::Efectivo => 'del pago en efectivo',
            self::Transferencia => 'de la transferencia',
            self::Deposito => 'del depósito bancario',
        };
    }
}
