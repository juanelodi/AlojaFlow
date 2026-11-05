<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\FaseEstadoCuenta;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lo que le toca a un dueño de un mes. Una foto: guarda lo calculado y no cambia si después cambia una reserva, salvo que se vuelva a abrir (D-115).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int $propietario_id
 * @property CarbonImmutable $desde
 * @property CarbonImmutable $hasta
 * @property FaseEstadoCuenta $fase
 * @property string $moneda
 * @property int $ingresos
 * @property int $comision_portales
 * @property int $comision
 * @property int $gastos
 * @property int $cargo_fijo
 * @property int $neto
 * @property array{reservas?: list<array<string, mixed>>, estancias?: list<array<string, mixed>>, gastos?: list<array<string, mixed>>, noches_ocupadas?: int, noches_posibles?: int}|null $detalle
 * @property Carbon|null $enviado_en
 * @property CarbonImmutable|null $pagado_en
 * @property string|null $referencia_pago
 * @property-read Propiedad $propiedad
 * @property-read Propietario $propietario
 */
class EstadoCuenta extends ModeloBase
{
    protected $table = 'estados_cuenta';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'desde' => 'immutable_date',
            'hasta' => 'immutable_date',
            'fase' => FaseEstadoCuenta::class,
            'ingresos' => 'integer',
            'comision_portales' => 'integer',
            'comision' => 'integer',
            'gastos' => 'integer',
            'cargo_fijo' => 'integer',
            'neto' => 'integer',
            'detalle' => 'array',
            'enviado_en' => 'datetime',
            'pagado_en' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<Propietario, $this> */
    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class);
    }

    /**
     * El mes, en el idioma de quien mira.
     */
    public function periodo(): string
    {
        return $this->desde->locale(app()->getLocale())->isoFormat('MMMM YYYY');
    }
}
