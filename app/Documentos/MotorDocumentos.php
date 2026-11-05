<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Documentos;

use App\Comunicacion\VariablesMensaje;
use App\Enums\EstadoReserva;
use App\Enums\LugarOmision;
use App\Enums\TipoContacto;
use App\Enums\TipoReserva;
use App\Limpieza\Omisiones;
use App\Models\CargoReserva;
use App\Models\ContactoHuesped;
use App\Models\DocumentoPropio;
use App\Models\Huesped;
use App\Models\Pago;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\User;
use App\Seguridad\Limites;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Convierte el texto de un documento propio en lo que se imprime (D-123). No hay lenguaje de plantillas: solo variables entre llaves, y lo que usa datos de una
 * lista se repite: la fila de una tabla, el punto de una lista o el párrafo. Nada de lo escrito se ejecuta, y lo que viene de los datos se escapa.
 */
final class MotorDocumentos
{
    /** Lo que se repite. Una fila antes que el párrafo que lleva dentro: gana el que abre primero. */
    private const BLOQUE = '#<(tr|li|p)\b[^>]*>.*?</\1>#si';

    /** En un documento de varias reservas, lo que dice de cuál se habla. */
    private const DE_RESERVA = '/\{(?:huesped(?:_[a-z]+)?|nombre|numero|codigo|llegada(?:_corta)?|salida(?:_corta)?|noches|personas|ninos|unidad|tarifa|origen|comentario|total|pagado|pendiente|deposito|campo_[a-z0-9_]+|h_[a-z0-9_]+|c_[a-z]+|p_[a-z]+)\}/';

    public const CRITERIOS = ['llegadas', 'salidas', 'en_casa'];

    /** Más que esto en un PDF ya no cabe en la memoria de un hosting compartido. */
    public const RESERVAS_MAXIMAS = 500;

    /**
     * @param  bool  $conIdentidad  si quien lo pide puede ver el número de documento de los huéspedes
     */
    public function deReserva(DocumentoPropio $documento, Reserva $reserva, bool $conIdentidad): string
    {
        $idioma = $reserva->idioma ?: $reserva->propiedad->idioma;

        return $this->enIdioma($idioma, fn (): string => $this->llenar(Str::sanitizeHtml($documento->texto($idioma)), $reserva, $idioma, $conIdentidad));
    }

    /**
     * @param  Collection<int, Reserva>  $reservas
     */
    public function dePeriodo(DocumentoPropio $documento, Propiedad $propiedad, CarbonImmutable $desde, CarbonImmutable $hasta, Collection $reservas, bool $conIdentidad): string
    {
        $idioma = $propiedad->idioma;

        return $this->enIdioma($idioma, function () use ($documento, $propiedad, $desde, $hasta, $reservas, $idioma, $conIdentidad): string {
            $html = Str::sanitizeHtml($documento->texto($idioma));
            $repetido = preg_replace_callback(self::BLOQUE, function (array $bloque) use ($reservas, $idioma, $conIdentidad): string {
                if (preg_match(self::DE_RESERVA, $bloque[0]) !== 1) {
                    return $bloque[0];
                }

                return $reservas->map(fn (Reserva $reserva): string => $this->llenar($bloque[0], $reserva, $idioma, $conIdentidad))->implode('');
            }, $html);

            return strtr($repetido ?? $html, $this->escapadas([
                'desde' => Formato::fecha($desde, 'LL'),
                'hasta' => Formato::fecha($hasta, 'LL'),
                'cuantas' => (string) $reservas->count(),
            ] + $this->deLaPropiedad($propiedad)));
        });
    }

    /**
     * Las reservas de un documento de varias: las que puede ver quien lo pide, sin las omitidas, por llegada.
     *
     * @return Collection<int, Reserva>
     */
    public function reservasDelPeriodo(Propiedad $propiedad, CarbonImmutable $desde, CarbonImmutable $hasta, string $criterio, ?User $usuario): Collection
    {
        $inicio = $desde->format('Y-m-d');
        $fin = $hasta->format('Y-m-d');
        $consulta = Reserva::query()->with(['propiedad', 'unidad', 'titular.contactos', 'huespedes', 'cargos', 'pagos'])
            ->where('propiedad_id', $propiedad->id)->where('tipo', TipoReserva::Reserva->value)->where('estado', '!=', EstadoReserva::Cancelada->value)->whereNull('eliminada_en_principal_en');
        match ($criterio) {
            'salidas' => $consulta->whereBetween('salida', [$inicio, $fin]),
            'en_casa' => $consulta->where('llegada', '<=', $fin)->where('salida', '>', $inicio),
            default => $consulta->whereBetween('llegada', [$inicio, $fin]),
        };
        Omisiones::excluir($consulta, 'titular_id', [$propiedad->id], LugarOmision::Informes);

        // Una más de las que caben, para saber que no cupieron.
        return Limites::reservasVisibles($consulta, $usuario)->orderBy('llegada')->orderBy('id')->limit(self::RESERVAS_MAXIMAS + 1)->get();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $accion
     * @return T
     */
    private function enIdioma(string $idioma, callable $accion): mixed
    {
        $anterior = app()->getLocale();
        app()->setLocale($idioma);
        try {
            return $accion();
        } finally {
            app()->setLocale($anterior);
        }
    }

    /**
     * Primero lo que se repite, y luego los datos de la reserva.
     */
    private function llenar(string $html, Reserva $reserva, string $idioma, bool $conIdentidad): string
    {
        $listas = [
            '{h_' => $this->huespedes($reserva, $conIdentidad),
            '{c_' => $reserva->cargos->map(fn (CargoReserva $c): array => [
                'c_nombre' => $c->nombre,
                'c_cantidad' => str_replace('.', Formato::separadorDecimal(), (string) (float) $c->cantidad),
                'c_importe' => Formato::dinero($c->importe, $reserva->moneda),
            ])->all(),
            '{p_' => $reserva->pagos->map(fn (Pago $p): array => [
                'p_fecha' => Formato::fecha($p->fecha, 'LL'),
                'p_metodo' => $p->metodo->etiqueta(),
                'p_referencia' => (string) $p->referencia,
                'p_importe' => Formato::dinero($p->importe, $reserva->moneda),
            ])->all(),
        ];
        $repetido = preg_replace_callback(self::BLOQUE, function (array $bloque) use ($listas): string {
            foreach ($listas as $prefijo => $filas) {
                if (str_contains($bloque[0], $prefijo)) {
                    return implode('', array_map(fn (array $fila): string => strtr($bloque[0], $this->escapadas($fila)), $filas));
                }
            }

            return $bloque[0];
        }, $html);

        return strtr($repetido ?? $html, $this->escapadas($this->deLaReserva($reserva, $idioma, $conIdentidad)));
    }

    /**
     * @return array<string, string>
     */
    private function deLaReserva(Reserva $reserva, string $idioma, bool $conIdentidad): array
    {
        $titular = $reserva->titular;

        return [
            'llegada_corta' => Formato::fechaCorta($reserva->llegada),
            'salida_corta' => Formato::fechaCorta($reserva->salida),
            'tarifa' => (string) $reserva->tarifa_nombre,
            'origen' => (string) $reserva->origen_texto,
            'comentario' => (string) $reserva->comentario,
            'ninos' => (string) (int) $reserva->ninos,
            'deposito' => Formato::dinero($reserva->deposito, $reserva->moneda),
            'huesped_telefono' => $this->contacto($titular, TipoContacto::Telefono),
            'huesped_correo' => $this->contacto($titular, TipoContacto::Correo),
            'huesped_nacimiento' => Formato::fecha($titular?->fecha_nacimiento, 'LL'),
            'huesped_nacionalidad' => (string) $titular?->nacionalidad_texto,
            'huesped_documento' => $titular === null ? '' : $this->identidad($titular, $conIdentidad),
            'huesped_domicilio' => $titular === null ? '' : $this->domicilio($titular),
        ] + $this->deLaPropiedad($reserva->propiedad) + VariablesMensaje::de($reserva, $idioma);
    }

    /**
     * @return array<string, string>
     */
    private function deLaPropiedad(Propiedad $propiedad): array
    {
        return [
            'hoy' => Formato::fecha($propiedad->hoy(), 'LL'),
            'propiedad' => $propiedad->nombre,
            'domicilio_propiedad' => (string) $propiedad->getAttribute('domicilio'),
            'telefono_propiedad' => (string) $propiedad->getAttribute('telefono'),
            'correo_propiedad' => (string) $propiedad->getAttribute('correo'),
        ] + VariablesMensaje::propias($propiedad->id);
    }

    /**
     * El titular primero, y cada persona una vez.
     *
     * @return list<array<string, string>>
     */
    private function huespedes(Reserva $reserva, bool $conIdentidad): array
    {
        $personas = $reserva->huespedes->keyBy('id');
        if ($reserva->titular !== null) {
            $personas = collect([$reserva->titular->id => $personas->get($reserva->titular->id) ?? $reserva->titular])->union($personas);
        }
        $campos = CamposPropios::deHuespedes($reserva->propiedad_id, $personas->keys()->all());
        $filas = [];
        foreach ($personas->values() as $i => $huesped) {
            /** @var Huesped $huesped */
            $filas[] = [
                'h_numero' => (string) ($i + 1),
                'h_nombre' => $huesped->nombreCompleto(),
                'h_nacimiento' => Formato::fecha($huesped->fecha_nacimiento, 'LL'),
                'h_nacionalidad' => (string) $huesped->nacionalidad_texto,
                'h_documento_tipo' => (string) $huesped->documento_tipo_texto,
                'h_documento' => $this->identidad($huesped, $conIdentidad),
                'h_domicilio' => $this->domicilio($huesped),
                'h_parentesco' => (string) data_get($huesped->getRelationValue('pivot'), 'parentesco_texto'),
            ] + ($campos[$huesped->id] ?? []);
        }

        return $filas;
    }

    /**
     * Sin permiso, los últimos cuatro: igual que en su ficha.
     */
    private function identidad(Huesped $huesped, bool $conIdentidad): string
    {
        $numero = (string) $huesped->documento_numero;

        return $conIdentidad || $numero === '' ? $numero : '•••• '.mb_substr($numero, -4);
    }

    private function domicilio(Huesped $huesped): string
    {
        return implode(', ', array_filter([trim($huesped->domicilio_calle.' '.$huesped->domicilio_numero), $huesped->domicilio_ciudad, $huesped->domicilio_region, $huesped->domicilio_codigo_postal, $huesped->domicilio_pais_texto], fn (?string $parte): bool => filled($parte)));
    }

    private function contacto(?Huesped $huesped, TipoContacto $tipo): string
    {
        $contacto = $huesped?->contactos->first(fn (ContactoHuesped $c): bool => $c->tipo === $tipo);

        return (string) $contacto?->valor;
    }

    /**
     * @param  array<string, string>  $variables
     * @return array<string, string>
     */
    private function escapadas(array $variables): array
    {
        $reemplazos = [];
        foreach ($variables as $clave => $valor) {
            $reemplazos['{'.$clave.'}'] = e($valor);
        }

        return $reemplazos;
    }
}
