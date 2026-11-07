<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Limpieza;

use App\Support\Ajustes;
use App\Support\Formato;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Cómo se ve el aviso de entradas y salidas: la fecha, los iconos, el separador y los textos. Se guarda por propiedad, para que quien lo
 * saque lo vea igual. Los iconos son emojis porque el aviso es texto para WhatsApp.
 */
final class FormatoAviso
{
    public const AJUSTE = 'aviso.formato.';

    public const FECHAS = ['dia_numerica', 'dia_numerica_larga', 'dia_abreviada', 'larga', 'media', 'numerica'];

    public const ICONOS_DIA = ['📌', '📅', '🗓️', '⭐', '▶️', '🔷', ''];

    public const ICONOS_SALEN = ['🔹', '🔵', '⬅️', '🚪', '🧳', '🛫', ''];

    public const ICONOS_LLEGAN = ['🔸', '🟠', '➡️', '🔑', '🙋', '🛬', ''];

    public const ICONOS_LIMPIEZA = ['✅', '🧹', '✨', '🧺', '✔️', ''];

    public const SEPARADORES = ['linea', 'gruesa', 'doble', 'guiones', 'puntos', 'blanco', 'ninguno'];

    public const UNIDADES = ['en_linea', 'renglones'];

    public const ORDENES = ['salen', 'llegan'];

    private const LARGO_TEXTO = 30;

    /**
     * @return array{fecha: string, icono_dia: string, icono_salen: string, icono_llegan: string, icono_limpieza: string, separador: string, unidades: string, orden: string, texto_salen: string, texto_llegan: string, texto_limpieza: string, negritas: bool}
     */
    public static function porOmision(): array
    {
        return [
            'fecha' => 'dia_numerica',
            'icono_dia' => '📌',
            'icono_salen' => '🔹',
            'icono_llegan' => '🔸',
            'icono_limpieza' => '✅',
            'separador' => 'linea',
            'unidades' => 'en_linea',
            'orden' => 'salen',
            'texto_salen' => '',
            'texto_llegan' => '',
            'texto_limpieza' => '',
            'negritas' => false,
        ];
    }

    /**
     * El guardado de la propiedad, con lo que le falte de la original.
     *
     * @return array{fecha: string, icono_dia: string, icono_salen: string, icono_llegan: string, icono_limpieza: string, separador: string, unidades: string, orden: string, texto_salen: string, texto_llegan: string, texto_limpieza: string, negritas: bool}
     */
    public static function de(int $propiedadId): array
    {
        $guardado = Ajustes::obtener(self::AJUSTE.$propiedadId);

        return self::limpiar(is_array($guardado) ? $guardado : []);
    }

    /**
     * @param  array<string, mixed>  $formato
     */
    public static function guardar(int $propiedadId, array $formato): void
    {
        Ajustes::guardar(self::AJUSTE.$propiedadId, self::limpiar($formato));
    }

    public static function restablecer(int $propiedadId): void
    {
        Ajustes::borrar(self::AJUSTE.$propiedadId);
    }

    /**
     * El navegador manda lo que quiera: solo pasa lo que está en las listas, y los textos, cortos y en un renglón.
     *
     * @param  array<string, mixed>  $formato
     * @return array{fecha: string, icono_dia: string, icono_salen: string, icono_llegan: string, icono_limpieza: string, separador: string, unidades: string, orden: string, texto_salen: string, texto_llegan: string, texto_limpieza: string, negritas: bool}
     */
    public static function limpiar(array $formato): array
    {
        $base = self::porOmision();
        $elegir = function (string $clave, array $permitidos) use ($formato, $base): string {
            $valor = $formato[$clave] ?? $base[$clave];

            return is_string($valor) && in_array($valor, $permitidos, true) ? $valor : $base[$clave];
        };
        $escrito = function (string $clave) use ($formato): string {
            $valor = $formato[$clave] ?? '';

            return is_string($valor) ? Str::limit(trim((string) preg_replace('/\s+/u', ' ', $valor)), self::LARGO_TEXTO, '') : '';
        };

        return [
            'fecha' => $elegir('fecha', self::FECHAS),
            'icono_dia' => $elegir('icono_dia', self::ICONOS_DIA),
            'icono_salen' => $elegir('icono_salen', self::ICONOS_SALEN),
            'icono_llegan' => $elegir('icono_llegan', self::ICONOS_LLEGAN),
            'icono_limpieza' => $elegir('icono_limpieza', self::ICONOS_LIMPIEZA),
            'separador' => $elegir('separador', self::SEPARADORES),
            'unidades' => $elegir('unidades', self::UNIDADES),
            'orden' => $elegir('orden', self::ORDENES),
            'texto_salen' => $escrito('texto_salen'),
            'texto_llegan' => $escrito('texto_llegan'),
            'texto_limpieza' => $escrito('texto_limpieza'),
            'negritas' => (bool) ($formato['negritas'] ?? false),
        ];
    }

    /**
     * La fecha de cada bloque. El orden de día y mes lo da el idioma: no es igual 06/11/26 en México que en Estados Unidos.
     */
    public static function fecha(CarbonInterface $dia, string $formato): string
    {
        $diaSemana = Str::ucfirst(Formato::fecha($dia, 'dddd'));

        return match ($formato) {
            'dia_numerica_larga' => $diaSemana.' '.Formato::fechaNumerica($dia, anioCompleto: true),
            'dia_abreviada' => Str::ucfirst(Formato::fecha($dia, 'ddd')).' '.Formato::fechaNumerica($dia, conAnio: false),
            'larga' => $diaSemana.' '.Formato::fecha($dia, 'LL'),
            'media' => Str::ucfirst(Formato::fecha($dia, 'ddd D MMM')),
            'numerica' => Formato::fechaNumerica($dia),
            default => $diaSemana.' '.Formato::fechaNumerica($dia),
        };
    }

    public static function separador(string $separador): string
    {
        return match ($separador) {
            'gruesa' => '━━━━━━━━━━━━━━━━',
            'doble' => '════════════════',
            'guiones' => '- - - - - - - - - - -',
            'puntos' => '• • • • • • • • • • •',
            'blanco', 'ninguno' => '',
            default => '────────────────',
        };
    }

    /**
     * Lo que va entre un día y el siguiente.
     */
    public static function union(string $separador): string
    {
        return match ($separador) {
            'blanco' => "\n\n",
            'ninguno' => "\n",
            default => "\n".self::separador($separador)."\n",
        };
    }
}
