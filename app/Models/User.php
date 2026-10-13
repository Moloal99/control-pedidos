<?php

namespace App\Models;

use App\Enums\Rol;
use App\SharePoint\EnEspejo;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuario = fila de dim_VENDEDORES. Las capacidades replican al pie de la
 * letra las variables del OnStart de la Power App (gVeTodosLosPedidos,
 * gPuedeConfirmar, ...). Las pantallas preguntan por capacidades, nunca por rol.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use EnEspejo, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sp_id', 'codigo', 'name', 'email', 'rol', 'carga_por_otros',
        'despacha', 'activo', 'ms_oid', 'tema', 'ultimo_acceso_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return [
            'rol' => Rol::class,
            'carga_por_otros' => 'boolean',
            'despacha' => 'boolean',
            'activo' => 'boolean',
            'ultimo_acceso_at' => 'datetime',
        ];
    }

    public static function mapaSharePoint(): array
    {
        return [
            'lista' => 'dim_VENDEDORES',
            'soloLectura' => true,
            'campos' => [
                'codigo' => 'VENDEDOR_CODIGO',
                'name' => 'VENDEDOR_NOMBRE',
                'email' => 'VENDEDOR_EMAIL',
                'rol' => 'VENDEDOR_ROL',
                'carga_por_otros' => 'VENDEDOR_CARGA_POR_OTROS',
                'despacha' => 'VENDEDOR_DESPACHA',
            ],
        ];
    }

    public function pedidosVendidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'vendedor_id');
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(Sesion::class);
    }

    // ---------------------------------------------------------------- rol

    public function esAdmin(): bool
    {
        return $this->rol === Rol::Admin;
    }

    public function esAlmacen(): bool
    {
        return $this->rol === Rol::Almacen;
    }

    public function esCobranzas(): bool
    {
        return $this->rol === Rol::Cobranzas;
    }

    public function esCobrador(): bool
    {
        return $this->rol === Rol::Cobrador;
    }

    /** gEsVendedor: ninguno de los otros cuatro roles. */
    public function esVendedor(): bool
    {
        return ! $this->esAdmin() && ! $this->esAlmacen() && ! $this->esCobranzas() && ! $this->esCobrador();
    }

    // ---------------------------------------------------------- capacidades

    /** gPuedeElegirVendedor: supervisor que carga a nombre de otros. */
    public function puedeElegirVendedor(): bool
    {
        return (bool) $this->carga_por_otros;
    }

    public function veTabPedidos(): bool
    {
        return $this->esAdmin() || $this->esAlmacen() || $this->esVendedor();
    }

    public function veTabCobranzas(): bool
    {
        return $this->esAdmin() || $this->esCobranzas();
    }

    public function veTodosLosPedidos(): bool
    {
        return $this->esAdmin() || $this->esAlmacen() || $this->esCobranzas() || $this->puedeElegirVendedor();
    }

    public function veTodasLasCobranzas(): bool
    {
        return $this->esAdmin() || $this->esCobranzas();
    }

    public function veTodasLasNotas(): bool
    {
        return $this->esAdmin() || $this->esCobrador() || $this->puedeElegirVendedor();
    }

    /** gVeOtrasPantallas: Visita, Pedido y Competencia. */
    public function puedeVender(): bool
    {
        return $this->esAdmin() || $this->esVendedor();
    }

    /** Cobranza de campo: vendedores, admin y cobrador. */
    public function puedeCobrar(): bool
    {
        return $this->puedeVender() || $this->esCobrador();
    }

    public function vendedorDespacha(): bool
    {
        return (bool) $this->despacha;
    }

    public function puedeConfirmarCobranzas(): bool
    {
        return $this->esAdmin() || $this->esCobranzas();
    }

    public function puedeAdministrar(): bool
    {
        return $this->esAdmin();
    }

    // ------------------------------------------------------ por pedido (ticket)

    public function esDuenoDe(Pedido $pedido): bool
    {
        return $pedido->vendedor_codigo !== null && $pedido->vendedor_codigo === $this->codigo;
    }

    public function cargo(Pedido $pedido): bool
    {
        return trim((string) $pedido->cargado_por) !== '' && $pedido->cargado_por === $this->name;
    }

    /** gProformarTicket / gDespacharTicket. */
    public function puedeDespachar(Pedido $pedido): bool
    {
        return $this->esAdmin() || $this->esAlmacen() || ($this->vendedorDespacha() && $this->esDuenoDe($pedido));
    }

    /** gEditarTicket. */
    public function puedeEditar(Pedido $pedido): bool
    {
        return $this->esAdmin() || $this->esAlmacen() || $this->esDuenoDe($pedido) || $this->cargo($pedido);
    }

    public function puedeVerPedido(Pedido $pedido): bool
    {
        return $this->veTodosLosPedidos() || $this->esDuenoDe($pedido) || $this->cargo($pedido);
    }

    // ---------------------------------------------------------- presentacion

    public function iniciales(): string
    {
        $partes = preg_split('/\s+/', trim($this->name)) ?: [];

        return mb_strtoupper(collect($partes)->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    }

    public function nombreCorto(): string
    {
        $partes = preg_split('/\s+/', trim($this->name)) ?: [];

        return implode(' ', array_slice($partes, 0, 2));
    }

    /** Pantalla inicial segun rol (StartScreen de la Power App, mas el tablero). */
    public function rutaInicio(): string
    {
        return 'inicio';
    }

    /**
     * Vendedores a los que un supervisor puede cargar una venta: solo «user»
     * que no sean a su vez supervisores (colVendedoresCarga).
     */
    public function scopeCargables(Builder $consulta): Builder
    {
        return $consulta->where('rol', Rol::Vendedor->value)
            ->where('carga_por_otros', false)
            ->where('activo', true)
            ->orderBy('name');
    }
}
