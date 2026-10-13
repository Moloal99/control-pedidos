<?php

namespace App\Enums;

/** COMP_TIPO. En la Power App el boton «Bandejas» grababa «Bobinas»: aqui no. */
enum TipoCompetencia: string
{
    use ConEtiqueta;

    case Bolsas = 'Bolsas';
    case Bobinas = 'Bobinas';
    case Bandejas = 'Bandejas';
    case Vasos = 'Vasos';
    case Rafia = 'Rafia';
    case Otro = 'Otro';
}
