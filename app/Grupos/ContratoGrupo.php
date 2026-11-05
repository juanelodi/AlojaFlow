<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Grupos;

use App\Archivos\GestorArchivos;
use App\Documentos\GeneradorPdf;
use App\Enums\ClaseArchivo;
use App\Enums\EstadoReserva;
use App\Enums\TipoDocumentoLegal;
use App\Enums\VerificacionAceptacion;
use App\Models\Aceptacion;
use App\Models\DocumentoLegal;
use App\Models\Grupo;
use App\Models\RegistroAuditoria;
use App\Reservas\GestorGrupos;
use App\Support\Formato;
use Illuminate\Support\Facades\DB;

/**
 * Lo que tiene que aceptar el coordinador (D-096): el contrato del grupo, una plantilla con sus datos, y el aviso de privacidad. La huella es del texto ya
 * con los datos: si cambian las fechas o el precio, ya no es lo que se aceptó y hay que aceptarlo otra vez.
 */
final class ContratoGrupo
{
    private const ANIOS_CONSERVAR = 5;

    private const TIPOS = [TipoDocumentoLegal::ContratoGrupo, TipoDocumentoLegal::AvisoPrivacidad];

    public function __construct(private readonly GestorArchivos $archivos)
    {
    }

    /**
     * @return list<array{documento: DocumentoLegal, texto: string, huella: string}>
     */
    public function documentos(Grupo $grupo, string $idioma): array
    {
        $vigentes = DocumentoLegal::query()->where('propiedad_id', $grupo->propiedad_id)->whereIn('tipo', array_map(fn (TipoDocumentoLegal $t): string => $t->value, self::TIPOS))
            ->whereNotNull('publicado_en')->whereNull('retirado_en')->orderBy('tipo')->get();
        $aceptadas = Aceptacion::query()->with('documento')->where('grupo_id', $grupo->id)->latest('id')->get();

        $lista = [];
        foreach ($vigentes->groupBy(fn (DocumentoLegal $d): string => $d->tipo->value) as $deUnTipo) {
            // Cambiar de idioma en la página no pide aceptar otra vez lo mismo: se sigue el idioma en que se aceptó.
            $yaAceptado = $aceptadas->first(fn (Aceptacion $a): bool => $a->documento->tipo === $deUnTipo->first()?->tipo);
            $documento = $deUnTipo->firstWhere('idioma', $yaAceptado?->documento->idioma ?? $idioma)
                ?? $deUnTipo->firstWhere('idioma', $idioma)
                ?? $deUnTipo->firstWhere('idioma', $grupo->propiedad->idioma)
                ?? $deUnTipo->firstOrFail();
            $texto = $this->texto($grupo, $documento);
            $lista[] = ['documento' => $documento, 'texto' => $texto, 'huella' => hash('sha256', $texto)];
        }

        return $lista;
    }

    /**
     * @return list<array{documento: DocumentoLegal, texto: string, huella: string}>
     */
    public function pendientes(Grupo $grupo, string $idioma): array
    {
        return array_values(array_filter($this->documentos($grupo, $idioma), fn (array $d): bool => ! Aceptacion::query()->where('grupo_id', $grupo->id)
            ->where('documento_legal_id', $d['documento']->id)->where('huella_documento', $d['huella'])->exists()));
    }

    public function aceptar(Grupo $grupo, string $firmante, string $idioma, ?string $ip, ?string $agente): void
    {
        $salida = $grupo->ultimaSalida() ?? $grupo->propiedad->hoy();
        DB::transaction(function () use ($grupo, $firmante, $idioma, $ip, $agente, $salida): void {
            foreach ($this->pendientes($grupo, $idioma) as $pendiente) {
                $aceptacion = Aceptacion::query()->create([
                    'documento_legal_id' => $pendiente['documento']->id,
                    'propiedad_id' => $grupo->propiedad_id,
                    'grupo_id' => $grupo->id,
                    'firmante_nombre' => mb_substr($firmante, 0, 200),
                    'huella_documento' => $pendiente['huella'],
                    'aceptada_en' => now('UTC'),
                    'ip' => $ip,
                    'agente' => $agente === null ? null : mb_substr($agente, 0, 255),
                    'verificacion' => VerificacionAceptacion::Casilla,
                    'conservar_hasta' => $salida->addYears(self::ANIOS_CONSERVAR),
                ]);
                $copia = $this->archivos->guardar(
                    app(GeneradorPdf::class)->copiaAceptacionGrupo($aceptacion, $pendiente['documento'], $pendiente['texto'], $grupo),
                    'aceptacion-'.$aceptacion->id.'.pdf',
                    'application/pdf',
                    ClaseArchivo::CopiaAceptacion,
                    $aceptacion,
                    $grupo->propiedad_id,
                    cifrar: true,
                    conservarHasta: $aceptacion->conservar_hasta,
                );
                $aceptacion->update(['copia_archivo_id' => $copia->id]);
                RegistroAuditoria::anotar('grupo.aceptacion', $grupo, ['documento' => $pendiente['documento']->id, 'version' => $pendiente['documento']->version], $grupo->propiedad_id);
            }
        });
    }

    /**
     * Las fechas y el dinero, en el idioma del documento: el texto y su huella no pueden depender de quién lo esté viendo.
     */
    private function texto(Grupo $grupo, DocumentoLegal $documento): string
    {
        $anterior = app()->getLocale();
        app()->setLocale($documento->idioma);
        try {
            $saldo = app(GestorGrupos::class)->saldo($grupo);
            $habitaciones = $grupo->reservas()->where('estado', '!=', EstadoReserva::Cancelada->value)->count();
            $pagos = [];
            foreach ($grupo->cuotas()->orderBy('vence_en')->orderBy('id')->get() as $cuota) {
                $pagos[] = $cuota->concepto.': '.Formato::dinero($cuota->importe, $saldo['moneda']).' · '.Formato::fecha($cuota->vence_en);
            }

            return strtr($documento->cuerpo, [
                '{propiedad}' => $grupo->propiedad->nombre,
                '{grupo}' => $grupo->nombre,
                '{codigo}' => $grupo->codigo,
                '{coordinador}' => (string) $grupo->coordinador?->nombreCompleto(),
                '{llegada}' => Formato::fecha($grupo->primeraLlegada()),
                '{salida}' => Formato::fecha($grupo->ultimaSalida()),
                '{habitaciones}' => (string) $habitaciones,
                '{total}' => Formato::dinero($saldo['total'], $saldo['moneda']),
                '{pagos}' => implode("\n", $pagos),
                '{fecha_limite}' => Formato::fecha($grupo->fecha_limite_lista),
            ]);
        } finally {
            app()->setLocale($anterior);
        }
    }
}
