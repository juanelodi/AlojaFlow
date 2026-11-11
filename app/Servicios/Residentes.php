<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Comunicacion\Mensajero;
use App\Comunicacion\VariablesMensaje;
use App\Enums\TipoDocumentoLegal;
use App\Llegada\GestorLlegadas;
use App\Models\Aceptacion;
use App\Models\DocumentoLegal;
use App\Models\Reserva;
use App\Reservas\Cotizacion;
use App\Reservas\Mensualidades;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Quienes viven aquí una temporada (D-112): una estancia de un mes o más, que ya empezó o está por empezar.
 */
final class Residentes
{
    public const DIAS_ANTES = 30;

    private const FILAS_MAXIMAS = 300;

    /**
     * @param  list<int>  $propiedades
     * @return Builder<Reserva>
     */
    public static function consulta(array $propiedades): Builder
    {
        // Con un día de margen: cada propiedad tiene su zona, y aquí solo se acota.
        $hoy = CarbonImmutable::now('UTC');

        return Operacion::reservas($propiedades)
            ->where('salida', '>=', $hoy->subDay()->format('Y-m-d'))
            ->where('llegada', '<=', $hoy->addDays(self::DIAS_ANTES + 1)->format('Y-m-d'))
            ->whereRaw('DATEDIFF(salida, llegada) >= ?', [Cotizacion::NOCHES_MES])
            ->orderBy('unidad_id')->orderBy('llegada');
    }

    /**
     * @param  list<int>  $propiedades
     * @return list<array{reserva: Reserva, por_pagar: int, reglamento: string|null, whatsapp: string|null}>
     */
    public static function lista(array $propiedades): array
    {
        $reservas = self::consulta($propiedades)->with('cuotas')->limit(self::FILAS_MAXIMAS)->get();
        $firmadas = Aceptacion::query()->whereIn('reserva_id', $reservas->pluck('id'))->whereHas('documento', fn ($q) => $q->where('tipo', TipoDocumentoLegal::Reglamento->value))->pluck('reserva_id')->all();
        $conReglamento = DocumentoLegal::query()->whereIn('propiedad_id', $propiedades)->where('tipo', TipoDocumentoLegal::Reglamento->value)->whereNotNull('publicado_en')->whereNull('retirado_en')->pluck('propiedad_id')->all();

        $filas = [];
        foreach ($reservas as $reserva) {
            $hoy = $reserva->propiedad->hoy()->format('Y-m-d');
            if ($reserva->salida->format('Y-m-d') < $hoy) {
                continue;
            }
            $reglamento = match (true) {
                ! in_array($reserva->propiedad_id, $conReglamento, true) => null,
                in_array($reserva->id, $firmadas, true) => 'firmado',
                default => 'falta',
            };
            $filas[] = [
                'reserva' => $reserva,
                'por_pagar' => Mensualidades::porPagar($reserva),
                'reglamento' => $reglamento,
                'whatsapp' => $reglamento === 'falta' ? self::pedirFirma($reserva) : null,
            ];
        }

        return $filas;
    }

    private static function pedirFirma(Reserva $reserva): string
    {
        $idioma = Mensajero::idiomaDe($reserva);
        $texto = __('camping.residentes.pedir_firma', ['nombre' => (string) ($reserva->titular->nombre ?? ''), 'propiedad' => $reserva->propiedad->nombre, 'enlace' => app(GestorLlegadas::class)->urlDe($reserva, $idioma)], $idioma);

        return VariablesMensaje::enlaceWhatsapp(Mensajero::telefonoDe($reserva), $texto, $reserva->propiedad->pais);
    }
}
