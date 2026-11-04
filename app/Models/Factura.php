<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoFactura;
use App\Enums\TipoDocumentoFiscal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una factura o recibo. Al emitirse no cambia: guarda copia de emisor y receptor.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int $entidad_fiscal_id
 * @property int $serie_id
 * @property string $serie
 * @property int $numero
 * @property TipoDocumentoFiscal $tipo
 * @property EstadoFactura $estado
 * @property array<string, mixed> $emisor
 * @property array<string, mixed> $receptor
 * @property string $moneda
 * @property int $subtotal
 * @property int $descuento
 * @property int $impuestos
 * @property int $total
 * @property Carbon $emitida_en
 * @property string|null $lugar_expedicion
 * @property string|null $metodo_pago
 * @property string|null $forma_pago
 * @property string|null $uuid_fiscal
 * @property int|null $xml_archivo_id
 * @property int|null $pdf_archivo_id
 * @property Carbon|null $cancelada_en
 * @property string|null $motivo_cancelacion
 * @property Propiedad $propiedad
 * @property EntidadFiscal $entidad
 * @property \Illuminate\Database\Eloquent\Collection<int, FacturaLinea> $lineas
 */
class Factura extends ModeloBase
{
    protected $table = 'facturas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoFiscal::class,
            'estado' => EstadoFactura::class,
            'emisor' => 'array',
            'receptor' => 'array',
            'subtotal' => 'integer',
            'descuento' => 'integer',
            'impuestos' => 'integer',
            'total' => 'integer',
            'emitida_en' => 'datetime',
            'cancelada_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<EntidadFiscal, $this> */
    public function entidad(): BelongsTo
    {
        return $this->belongsTo(EntidadFiscal::class, 'entidad_fiscal_id');
    }

    /** @return BelongsTo<Archivo, $this> */
    public function xml(): BelongsTo
    {
        return $this->belongsTo(Archivo::class, 'xml_archivo_id');
    }

    /** @return BelongsTo<Archivo, $this> */
    public function pdf(): BelongsTo
    {
        return $this->belongsTo(Archivo::class, 'pdf_archivo_id');
    }

    /** @return HasMany<FacturaLinea, $this> */
    public function lineas(): HasMany
    {
        return $this->hasMany(FacturaLinea::class)->orderBy('orden');
    }

    /** @return BelongsToMany<Reserva, $this> */
    public function reservas(): BelongsToMany
    {
        return $this->belongsToMany(Reserva::class, 'facturas_reservas');
    }

    /** @return HasMany<EventoFiscal, $this> */
    public function eventos(): HasMany
    {
        return $this->hasMany(EventoFiscal::class);
    }

    public function folio(): string
    {
        return $this->serie.'-'.$this->numero;
    }
}
