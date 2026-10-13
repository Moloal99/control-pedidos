<?php

namespace App\Enums;

/** Forma de pago del pedido y de la nota de venta. */
enum FormaPago: string
{
    use ConEtiqueta;

    case Credito = 'Crédito';
    case Contado = 'Contado';
}
