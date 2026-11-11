<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Comunicacion;

use App\Enums\AccionAutomatizacion;
use App\Enums\DisparadorAutomatizacion;
use App\Enums\TipoEnlace;
use App\Models\Automatizacion;
use App\Models\EnlacePublico;
use App\Models\Opinion;
use App\Models\Plantilla;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Support\Ajustes;
use Illuminate\Support\Facades\Lang;

/**
 * La encuesta tras la estancia (D-087). A todos se les pregunta lo mismo y a todos se les ofrece el enlace público de reseñas, contesten lo que
 * contesten: mandar solo a los contentos falsea las reseñas, y los portales lo prohíben.
 */
final class Opiniones
{
    public const CODIGO_PLANTILLA = 'opinion';

    private const DIAS_VIGENCIA = 90;

    public static function enlacePublicoDe(int $propiedadId): ?string
    {
        $url = Ajustes::obtener('opiniones.enlace.'.$propiedadId);

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function urlDe(Reserva $reserva, string $idioma): string
    {
        $existente = EnlacePublico::query()->where('objeto_type', $reserva->getMorphClass())->where('objeto_id', $reserva->id)
            ->where('tipo', TipoEnlace::Opinion->value)->whereNull('revocado_en')->where('expira_en', '>', now('UTC'))->first();
        $enlace = $existente ?? EnlacePublico::nuevo($reserva->propiedad_id, TipoEnlace::Opinion, $reserva, $reserva->salida->addDays(self::DIAS_VIGENCIA));

        return rtrim((string) config('app.url'), '/').route('opinion.ver', ['token' => $enlace->token(), 'idioma' => $idioma], false);
    }

    public function reservaDe(string $token): ?Reserva
    {
        $enlace = EnlacePublico::buscar(TipoEnlace::Opinion, $token);
        $reserva = $enlace?->objeto;

        return $reserva instanceof Reserva ? $reserva : null;
    }

    /**
     * Se puede corregir mientras el enlace valga: se guarda lo último.
     */
    public function guardar(Reserva $reserva, int $calificacion, ?string $comentario): Opinion
    {
        return Opinion::query()->updateOrCreate(
            ['reserva_id' => $reserva->id],
            [
                'propiedad_id' => $reserva->propiedad_id,
                'calificacion' => min(5, max(1, $calificacion)),
                'comentario' => filled($comentario) ? mb_substr((string) $comentario, 0, 2000) : null,
                'respondida_en' => now('UTC'),
            ],
        );
    }

    /**
     * Deja lista la plantilla y una automatización apagada, para que la revise quien la va a usar.
     */
    public function prepararMensaje(Propiedad $propiedad): Automatizacion
    {
        $asunto = [];
        $cuerpo = [];
        foreach ((array) config('alojaflow.idiomas') as $idioma) {
            if (Lang::has('operacion.opiniones.plantilla_cuerpo', (string) $idioma, false)) {
                $asunto[$idioma] = __('operacion.opiniones.plantilla_asunto', [], (string) $idioma);
                $cuerpo[$idioma] = __('operacion.opiniones.plantilla_cuerpo', [], (string) $idioma);
            }
        }
        $plantilla = Plantilla::query()->firstOrCreate(
            ['propiedad_id' => $propiedad->id, 'codigo' => self::CODIGO_PLANTILLA],
            ['nombre' => __('operacion.opiniones.plantilla_nombre'), 'asunto' => $asunto, 'cuerpo' => $cuerpo],
        );

        return Automatizacion::query()->firstOrCreate(
            ['propiedad_id' => $propiedad->id, 'plantilla_id' => $plantilla->id],
            ['nombre' => __('operacion.opiniones.plantilla_nombre'), 'disparador' => DisparadorAutomatizacion::DespuesSalida, 'dias' => 2, 'accion' => AccionAutomatizacion::CorreoHuesped, 'activa' => false],
        );
    }
}
