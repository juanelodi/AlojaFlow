<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Facturacion;

use App\Archivos\GestorArchivos;
use App\Documentos\GeneradorPdf;
use App\Enums\BaseCargo;
use App\Enums\ClaseArchivo;
use App\Enums\EstadoFactura;
use App\Enums\MotivoCancelacionCfdi;
use App\Enums\TipoDocumentoFiscal;
use App\Models\Archivo;
use App\Models\EventoFiscal;
use App\Models\Factura;
use App\Models\Impuesto;
use App\Models\ReceptorFiscal;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\SerieFacturacion;
use App\Paises\Paises;
use App\Support\Dinero;
use App\Support\Formato;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Emite y cancela facturas y recibos. Una factura emitida no se edita: guarda copia de los datos del
 * emisor y del receptor, y su número se toma dentro de la misma transacción, sin huecos.
 */
final class GestorFacturas
{
    private const SEGUNDOS_CANDADO_TIMBRADO = 120;

    public function __construct(private readonly GestorArchivos $archivos)
    {
    }

    /**
     * @param  array{identificador_fiscal: string, nombre: string, regimen_fiscal?: string|null, codigo_postal?: string|null, uso?: string|null, correo?: string|null}  $receptor
     */
    public function emitir(Reserva $reserva, SerieFacturacion $serie, array $receptor, ?string $formaPago = null, ?string $metodoPago = null): Factura
    {
        $reserva->loadMissing(['propiedad', 'cargos', 'unidad']);
        $entidad = $serie->entidad;
        if ($entidad->propiedad_id !== $reserva->propiedad_id) {
            throw new ErrorFacturacion(__('dinero.facturas.error_entidad'));
        }
        if (! $serie->activa) {
            throw new ErrorFacturacion(__('dinero.facturas.error_serie'));
        }
        if ($reserva->total <= 0) {
            throw new ErrorFacturacion(__('dinero.facturas.error_total'));
        }
        if ($this->facturaVigenteDe($reserva) !== null) {
            throw new ErrorFacturacion(__('dinero.facturas.error_ya_facturada'));
        }
        $generador = $serie->tipo_documento === TipoDocumentoFiscal::Factura ? Paises::de($reserva->propiedad)->generadorFiscal() : null;
        if ($generador !== null) {
            $error = $generador->errorEnReceptor($receptor);
            if ($error !== null) {
                throw new ErrorFacturacion($error);
            }
        }

        $factura = DB::transaction(function () use ($reserva, $serie, $entidad, $receptor, $formaPago, $metodoPago, $generador): Factura {
            $bloqueada = SerieFacturacion::query()->whereKey($serie->id)->lockForUpdate()->firstOrFail();
            $numero = $bloqueada->siguiente_numero;
            $bloqueada->update(['siguiente_numero' => $numero + 1]);

            $lineas = $this->lineasDe($reserva, $generador);
            $factura = Factura::query()->create([
                'propiedad_id' => $reserva->propiedad_id,
                'entidad_fiscal_id' => $entidad->id,
                'serie_id' => $serie->id,
                'serie' => $serie->serie,
                'numero' => $numero,
                'tipo' => $serie->tipo_documento,
                'estado' => EstadoFactura::Emitida,
                'emisor' => ['nombre' => $entidad->nombre, 'identificador_fiscal' => $entidad->identificador_fiscal, 'regimen_fiscal' => $entidad->regimen_fiscal, 'codigo_postal' => $entidad->codigo_postal, 'domicilio' => $entidad->domicilio, 'pais' => $entidad->pais],
                'receptor' => $receptor,
                'moneda' => $reserva->moneda,
                'subtotal' => array_sum(array_column($lineas, 'importe')),
                'impuestos' => array_sum(array_map(fn (array $l): int => array_sum(array_column($l['impuestos'], 'importe')), $lineas)),
                'total' => $reserva->total,
                'emitida_en' => now('UTC'),
                'lugar_expedicion' => $entidad->codigo_postal,
                'forma_pago' => $formaPago,
                'metodo_pago' => $metodoPago,
            ]);
            foreach ($lineas as $i => $linea) {
                $fila = $factura->lineas()->create([
                    'orden' => $i,
                    'concepto' => mb_substr($linea['concepto'], 0, 255),
                    'clave_producto' => $linea['clave_producto'],
                    'clave_unidad' => $linea['clave_unidad'],
                    'cantidad' => 1,
                    'valor_unitario' => Dinero::aDecimal($linea['importe'], $reserva->moneda),
                    'importe' => $linea['importe'],
                ]);
                foreach ($linea['impuestos'] as $impuesto) {
                    $fila->impuestos()->create($impuesto + ['tipo' => 'traslado']);
                }
            }
            $factura->reservas()->attach($reserva->id);
            $this->guardarReceptor($reserva, $receptor);
            RegistroAuditoria::anotar('factura.emitir', $factura, ['folio' => $factura->folio(), 'total' => $factura->total], $reserva->propiedad_id);

            return $factura;
        });

        $this->generarArchivos($factura, $generador);

        return $factura->refresh();
    }

    /**
     * Pide al PAC que la timbre. Sin PAC, avisa y lo deja anotado.
     */
    public function timbrar(Factura $factura): void
    {
        $generador = Paises::de($factura->propiedad)->generadorFiscal();
        $original = $factura->xml;
        if ($generador === null || $original === null) {
            throw new ErrorFacturacion(__('dinero.facturas.no_se_timbra'));
        }
        // Dos clics serían dos timbres: dos CFDI vigentes ante el SAT, y uno sin dueño.
        $candado = Cache::lock('timbrado:factura:'.$factura->id, self::SEGUNDOS_CANDADO_TIMBRADO);
        if (! $candado->get()) {
            throw new ErrorFacturacion(__('dinero.pac.en_proceso'));
        }
        try {
            if ($factura->refresh()->uuid_fiscal === null) {
                $this->timbrarAhora($factura, $generador, $original);
            }
        } finally {
            $candado->release();
        }
    }

    private function timbrarAhora(Factura $factura, GeneradorFiscal $generador, Archivo $original): void
    {
        $pac = app(FabricaPac::class)->de($factura->entidad);
        try {
            $resultado = $pac->timbrar($this->archivos->contenido($original), $factura->entidad);
        } catch (ErrorFacturacion $e) {
            EventoFiscal::query()->create(['factura_id' => $factura->id, 'tipo' => 'timbrado', 'estado' => 'rechazado', 'respuesta' => ['mensaje' => $e->getMessage()]]);

            throw $e;
        }
        $xml = $this->archivos->guardar($resultado['xml'], $generador->nombreArchivo($factura), 'application/xml', ClaseArchivo::FacturaXml, $factura, $factura->propiedad_id);
        $qr = $resultado['qr'] ?? null;
        $factura->update([
            'estado' => EstadoFactura::Registrada,
            'uuid_fiscal' => $resultado['uuid'],
            'xml_archivo_id' => $xml->id,
            // Viene de fuera y va a un PDF: solo si de verdad es base64.
            'qr_timbrado' => is_string($qr) && preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $qr) === 1 ? $qr : null,
        ]);
        EventoFiscal::query()->create(['factura_id' => $factura->id, 'tipo' => 'timbrado', 'estado' => 'aceptado', 'respuesta' => ['uuid' => $resultado['uuid']]]);
        $this->rehacerPdf($factura->refresh());
    }

    /**
     * Una timbrada se cancela ante el SAT, con su clave de motivo (D-098). Ahí solo se pide: si hace falta que el receptor acepte, la decisión llega
     * después, y eso lo dice quien la cancela al confirmar.
     */
    public function cancelar(Factura $factura, string $motivo, ?MotivoCancelacionCfdi $clave = null, ?string $folioSustitucion = null): void
    {
        if ($factura->estado === EstadoFactura::Cancelada) {
            return;
        }
        if ($factura->uuid_fiscal !== null) {
            if ($clave === null) {
                throw new ErrorFacturacion(__('dinero.cancelacion.falta_motivo'));
            }
            if ($clave === MotivoCancelacionCfdi::ConErroresConRelacion && blank($folioSustitucion)) {
                throw new ErrorFacturacion(__('dinero.cancelacion.falta_sustituta'));
            }
            $respuesta = app(FabricaPac::class)->de($factura->entidad)->cancelar($factura->uuid_fiscal, $clave->value, $factura->entidad, $clave === MotivoCancelacionCfdi::ConErroresConRelacion ? $folioSustitucion : null);
            EventoFiscal::query()->create(['factura_id' => $factura->id, 'tipo' => 'cancelacion', 'estado' => 'aceptado', 'respuesta' => $respuesta + ['motivo' => $clave->value]]);
            $motivo = $clave->etiqueta();
        }
        $factura->update([
            'estado' => EstadoFactura::Cancelada,
            'cancelada_en' => now('UTC'),
            'motivo_cancelacion' => mb_substr($motivo, 0, 255),
            'motivo_cancelacion_clave' => $clave?->value,
            'folio_sustitucion' => $clave === MotivoCancelacionCfdi::ConErroresConRelacion ? $folioSustitucion : null,
        ]);
        RegistroAuditoria::anotar('factura.cancelar', $factura, ['folio' => $factura->folio()], $factura->propiedad_id);
        $this->rehacerPdf($factura->refresh());
    }

    public function facturaVigenteDe(Reserva $reserva): ?Factura
    {
        return Factura::query()->whereHas('reservas', fn ($q) => $q->where('reservas.id', $reserva->id))
            ->where('estado', '!=', EstadoFactura::Cancelada->value)->first();
    }

    /**
     * Los conceptos, con sus impuestos sacados del precio: el total de la reserva ya los lleva. Lo que
     * sobra por redondeo va al primero, para que la suma cuadre exactamente con el total.
     *
     * @return list<array{concepto: string, clave_producto: string|null, clave_unidad: string|null, importe: int, impuestos: list<array{nombre: string, codigo: string|null, base: int, tasa: string, importe: int}>}>
     */
    private function lineasDe(Reserva $reserva, ?GeneradorFiscal $generador): array
    {
        $claves = $generador?->clavesHospedaje() ?? ['producto' => null, 'unidad' => null];
        $tasas = Impuesto::query()->aplicables($reserva->propiedad_id, $reserva->llegada->format('Y-m-d'))->where('base', BaseCargo::PorcentajeAlojamiento->value)->orderBy('orden')->get();
        $sumaTasas = (float) $tasas->sum(fn (Impuesto $i): float => (float) $i->tasa) / 100;

        // Lo que es por persona y noche no es parte del precio del hospedaje ni lleva IVA encima: va en su línea, con unidades y tarifa (Ley 5/2017 de Cataluña, art. 35).
        $porEstancia = $reserva->impuestos()->whereNotNull('importe_unitario')->where('incluido', false)->where('cobrado_por_canal', false)->where('importe', '>', 0)->get();
        $cargos = (int) $reserva->cargos->sum('importe') + (int) $porEstancia->sum('importe');
        $brutos = [[__('dinero.facturas.concepto_hospedaje', ['unidad' => $reserva->unidad->nombre ?? '', 'noches' => $reserva->llegada->diffInDays($reserva->salida), 'numero' => $reserva->numero], $reserva->propiedad->idioma), $reserva->total - $cargos]];
        foreach ($reserva->cargos as $cargo) {
            $brutos[] = [$cargo->nombre, $cargo->importe];
        }

        $lineas = [];
        foreach ($brutos as [$concepto, $bruto]) {
            if ($bruto <= 0) {
                continue;
            }
            $base = (int) round($bruto / (1 + $sumaTasas));
            $impuestos = [];
            $sumados = 0;
            foreach ($tasas as $tasa) {
                $importe = (int) round($base * (float) $tasa->tasa / 100);
                $impuestos[] = ['nombre' => $tasa->nombre, 'codigo' => $tasa->codigo, 'base' => $base, 'tasa' => (string) $tasa->tasa, 'importe' => $importe];
                $sumados += $importe;
            }
            // base + impuestos debe dar exactamente el importe cobrado.
            if ($impuestos !== []) {
                $impuestos[0]['importe'] += $bruto - $base - $sumados;
            } else {
                $base = $bruto;
            }
            $lineas[] = ['concepto' => $concepto, 'clave_producto' => $claves['producto'], 'clave_unidad' => $claves['unidad'], 'importe' => $base, 'impuestos' => $impuestos];
        }
        foreach ($porEstancia as $impuesto) {
            $concepto = $impuesto->unidades > 0
                ? __('dinero.facturas.concepto_estancias', ['impuesto' => $impuesto->nombre, 'unidades' => $impuesto->unidades, 'tarifa' => Formato::dinero((int) $impuesto->importe_unitario, $reserva->moneda)], $reserva->propiedad->idioma)
                : $impuesto->nombre;
            $lineas[] = ['concepto' => $concepto, 'clave_producto' => $claves['producto'], 'clave_unidad' => $claves['unidad'], 'importe' => $impuesto->importe, 'impuestos' => []];
        }

        return $lineas;
    }

    /**
     * El que se guardó al emitir ya no dice la verdad: «sin timbrar», o vigente. Lo fiscal ya pasó ante el SAT: si esto falla, se anota y la acción sigue siendo un éxito.
     */
    private function rehacerPdf(Factura $factura): void
    {
        try {
            $anterior = $factura->pdf;
            $factura->update(['pdf_archivo_id' => $this->archivos->guardar(app(GeneradorPdf::class)->factura($factura), $factura->folio().'.pdf', 'application/pdf', ClaseArchivo::FacturaPdf, $factura, $factura->propiedad_id)->id]);
            if ($anterior !== null) {
                $this->archivos->eliminar($anterior);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $receptor
     */
    private function guardarReceptor(Reserva $reserva, array $receptor): void
    {
        ReceptorFiscal::query()->updateOrCreate(
            ['propiedad_id' => $reserva->propiedad_id, 'identificador_fiscal' => mb_strtoupper((string) $receptor['identificador_fiscal'])],
            ['huesped_id' => $reserva->titular_id, 'nombre' => $receptor['nombre'], 'regimen_fiscal' => $receptor['regimen_fiscal'] ?? null, 'codigo_postal' => $receptor['codigo_postal'] ?? null, 'uso' => $receptor['uso'] ?? null, 'correo' => $receptor['correo'] ?? null],
        );
    }

    private function generarArchivos(Factura $factura, ?GeneradorFiscal $generador): void
    {
        $factura->load(['lineas.impuestos', 'propiedad', 'reservas']);
        $cambios = [];
        if ($generador !== null) {
            $cambios['xml_archivo_id'] = $this->archivos->guardar($generador->generar($factura), $generador->nombreArchivo($factura), 'application/xml', ClaseArchivo::FacturaXml, $factura, $factura->propiedad_id)->id;
        }
        $cambios['pdf_archivo_id'] = $this->archivos->guardar(app(GeneradorPdf::class)->factura($factura), $factura->folio().'.pdf', 'application/pdf', ClaseArchivo::FacturaPdf, $factura, $factura->propiedad_id)->id;
        $factura->update($cambios);
    }
}
