<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Privacidad;

use App\Archivos\GestorArchivos;
use App\Enums\ClaseArchivo;
use App\Enums\TipoSolicitudPrivacidad;
use App\Models\Aceptacion;
use App\Models\Archivo;
use App\Models\ContactoHuesped;
use App\Models\Envio;
use App\Models\Huesped;
use App\Models\PersonaLlegada;
use App\Models\RegistroAuditoria;
use App\Models\RegistroLlegada;
use App\Models\Reserva;
use App\Models\SolicitudPrivacidad;
use Illuminate\Support\Facades\DB;

/**
 * Derechos del huésped (M10): exportar sus datos, anonimizarlos, y plazos de conservación.
 * Lo que viene de una principal se anonimiza allá: desde aquí solo se tocan los datos propios.
 */
final class GestorPrivacidad
{
    private const DIAS_EXPORTACION = 7;

    private const DIAS_ENVIO = 365;

    private const DIAS_EVENTO = 365;

    public function __construct(private readonly GestorArchivos $archivos)
    {
    }

    public function exportar(Huesped $huesped): Archivo
    {
        $huesped->load(['contactos', 'campos']);
        $reservas = Reserva::query()->where('titular_id', $huesped->id)->orWhereHas('huespedes', fn ($q) => $q->where('huespedes.id', $huesped->id))->with(['unidad', 'pagos', 'cargos'])->get();
        $idsReservas = $reservas->modelKeys();
        $registros = RegistroLlegada::query()->with('personas')->whereIn('reserva_id', $idsReservas)->get();

        $datos = [
            'generado_en' => now('UTC')->toIso8601String(),
            'propiedad' => $huesped->propiedad->nombre,
            'ficha' => $huesped->makeVisible('documento_numero')->attributesToArray(),
            'contactos' => $huesped->contactos->map(fn (ContactoHuesped $c): array => ['tipo' => $c->tipo->value, 'valor' => $c->valor])->all(),
            'campos' => $huesped->campos->pluck('valor', 'campo')->all(),
            'reservas' => $reservas->map(fn (Reserva $r): array => [
                'numero' => $r->numero, 'estado' => $r->estado->value, 'llegada' => $r->llegada->format('Y-m-d'), 'salida' => $r->salida->format('Y-m-d'), 'unidad' => $r->unidad->nombre ?? null,
                'total' => $r->total, 'pagado' => $r->pagado, 'moneda' => $r->moneda, 'comentario' => $r->comentario,
                'pagos' => $r->pagos->map(fn ($p): array => ['fecha' => $p->fecha->format('Y-m-d'), 'importe' => $p->importe, 'metodo' => $p->metodo->value])->all(),
            ])->all(),
            'registros_llegada' => $registros->map(fn (RegistroLlegada $r): array => [
                'estado' => $r->estado->value,
                'personas' => $r->personas->map(fn (PersonaLlegada $p): array => $p->makeVisible('documento_numero')->attributesToArray())->all(),
            ])->all(),
            'aceptaciones' => Aceptacion::query()->with('documento')->whereIn('reserva_id', $idsReservas)->get()->map(fn (Aceptacion $a): array => [
                'documento' => $a->documento->titulo, 'version' => $a->documento->version, 'huella' => $a->huella_documento, 'fecha' => $a->aceptada_en->toIso8601String(), 'verificacion' => $a->verificacion->value,
            ])->all(),
            'mensajes' => Envio::query()->where('huesped_id', $huesped->id)->get(['creado_en', 'canal', 'destino', 'asunto', 'estado'])->toArray(),
        ];

        $archivo = $this->archivos->guardar(
            (string) json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'huesped-'.$huesped->id.'.json',
            'application/json',
            ClaseArchivo::Exportacion,
            $huesped,
            $huesped->propiedad_id,
            cifrar: true,
            conservarHasta: now()->addDays(self::DIAS_EXPORTACION),
        );
        SolicitudPrivacidad::query()->create([
            'propiedad_id' => $huesped->propiedad_id, 'huesped_id' => $huesped->id, 'tipo' => TipoSolicitudPrivacidad::Exportacion,
            'solicitada_por' => auth()->id(), 'estado' => 'completada', 'completada_en' => now('UTC'), 'archivo_id' => $archivo->id,
        ]);
        RegistroAuditoria::anotar('privacidad.exportar', $huesped, [], $huesped->propiedad_id);

        return $archivo;
    }

    /**
     * Sustituye los datos personales propios por «Anonimizado #id» y borra sus archivos. Importes y
     * fechas se conservan para los informes. Una ficha que viene de la principal no se toca.
     *
     * @return bool si la ficha se pudo anonimizar (false: es de la principal)
     */
    public function anonimizar(Huesped $huesped): bool
    {
        return DB::transaction(function () use ($huesped): bool {
            $marca = __('llegada.anonimizado', ['id' => $huesped->id]);
            $reservas = Reserva::query()->where('titular_id', $huesped->id)->pluck('id');

            foreach (RegistroLlegada::query()->with('personas')->whereIn('reserva_id', $reservas)->get() as $registro) {
                $registro->update(['notas_huesped' => null, 'hora_llegada_estimada' => null]);
                foreach ($registro->personas as $persona) {
                    $persona->update($this->camposPersonaVacios() + ['nombre' => $marca]);
                }
                foreach ($registro->archivos as $archivo) {
                    $this->archivos->eliminar($archivo);
                }
            }
            Aceptacion::query()->whereIn('reserva_id', $reservas)->update(['firmante_nombre' => $marca, 'ip' => null, 'agente' => null]);
            Envio::query()->where('huesped_id', $huesped->id)->update(['destino' => null, 'asunto' => null, 'cuerpo' => null]);
            foreach (Archivo::query()->where('objeto_type', $huesped->getMorphClass())->where('objeto_id', $huesped->id)->get() as $archivo) {
                $this->archivos->eliminar($archivo);
            }

            $anonimizada = ! $huesped->sincronizada;
            if ($anonimizada) {
                DB::table('reservas')->where('titular_id', $huesped->id)->where('sincronizada', false)->update(['comentario' => null]);
                $huesped->contactos()->delete();
                $huesped->campos()->delete();
                $huesped->forceFill($this->camposHuespedVacios() + ['nombre' => $marca])->save();
            }

            SolicitudPrivacidad::query()->create([
                'propiedad_id' => $huesped->propiedad_id, 'huesped_id' => $huesped->id, 'tipo' => TipoSolicitudPrivacidad::Anonimizacion,
                'solicitada_por' => auth()->id(), 'estado' => 'completada', 'completada_en' => now('UTC'),
            ]);
            RegistroAuditoria::anotar('privacidad.anonimizar', $huesped, ['ficha' => $anonimizada], $huesped->propiedad_id);

            return $anonimizada;
        });
    }

    /**
     * Borra lo vencido. Corre cada noche con `alojaflow:limpiar` (modelo-datos.md §8).
     *
     * @return array<string, int> cuánto se borró de cada cosa
     */
    public function aplicarPlazos(): array
    {
        $hoy = now('UTC')->format('Y-m-d');
        $resultado = ['archivos' => 0, 'registros' => 0, 'aceptaciones' => 0, 'envios' => 0, 'eventos' => 0, 'codigos' => 0];

        Archivo::query()->whereNotNull('conservar_hasta')->where('conservar_hasta', '<', $hoy)->whereIn('clase', [ClaseArchivo::DocumentoIdentidad->value, ClaseArchivo::Exportacion->value, ClaseArchivo::Firma->value])
            ->each(function (Archivo $archivo) use (&$resultado): void {
                $this->archivos->eliminar($archivo);
                $resultado['archivos']++;
            });

        foreach (RegistroLlegada::query()->whereNotNull('conservar_hasta')->where('conservar_hasta', '<', $hoy)->get() as $registro) {
            foreach ($registro->archivos as $archivo) {
                $this->archivos->eliminar($archivo);
            }
            $registro->delete();
            $resultado['registros']++;
        }

        foreach (Aceptacion::query()->whereNotNull('conservar_hasta')->where('conservar_hasta', '<', $hoy)->whereNotNull('ip')->get() as $aceptacion) {
            $aceptacion->update(['firmante_nombre' => __('llegada.anonimizado', ['id' => $aceptacion->id]), 'ip' => null, 'agente' => null]);
            if ($aceptacion->copia !== null) {
                $this->archivos->eliminar($aceptacion->copia);
            }
            $resultado['aceptaciones']++;
        }

        $resultado['envios'] = Envio::query()->whereNull('cuerpo')->where('creado_en', '<', now()->subDays(self::DIAS_ENVIO))->delete();
        $resultado['eventos'] = (int) DB::table('eventos_reserva')->where('ocurrido_en', '<', now()->subDays(self::DIAS_EVENTO))->whereNotNull('procesado_en')->delete();
        $resultado['codigos'] = (int) DB::table('codigos_otp')->where('expira_en', '<', now()->subDay())->delete();

        return $resultado;
    }

    /**
     * @return array<string, null>
     */
    private function camposPersonaVacios(): array
    {
        return array_fill_keys(['apellido1', 'apellido2', 'sexo', 'fecha_nacimiento', 'nacionalidad_texto', 'documento_tipo', 'documento_numero', 'documento_vencimiento', 'domicilio_calle', 'domicilio_ciudad', 'domicilio_region', 'domicilio_codigo_postal', 'domicilio_pais_texto', 'correo', 'telefono', 'datos_pais'], null);
    }

    /**
     * @return array<string, null>
     */
    private function camposHuespedVacios(): array
    {
        return array_fill_keys(['apellido1', 'apellido2', 'apodo', 'tratamiento', 'sexo', 'fecha_nacimiento', 'lugar_nacimiento', 'pais_nacimiento_texto', 'nacionalidad', 'nacionalidad_texto', 'documento_tipo_texto', 'documento_numero', 'documento_vencimiento', 'documento_expedicion', 'documento_pais_texto', 'domicilio_calle', 'domicilio_numero', 'domicilio_ciudad', 'domicilio_region', 'domicilio_codigo_postal', 'domicilio_pais_texto', 'id_fiscal_personal', 'id_fiscal_empresa', 'notas'], null);
    }
}
