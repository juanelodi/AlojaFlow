<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Seguridad;

use App\Demo\ModoDemo;
use App\Models\AccesoUsuario;
use App\Models\Automatizacion;
use App\Models\Caja;
use App\Models\Canal;
use App\Models\CargoReserva;
use App\Models\ConceptoCargo;
use App\Models\ConexionCompartida;
use App\Models\ContactoHuesped;
use App\Models\Cupon;
use App\Models\EntidadFiscal;
use App\Models\Factura;
use App\Models\FuenteIcal;
use App\Models\Grupo;
use App\Models\Huesped;
use App\Models\Impuesto;
use App\Models\ListaComprobacion;
use App\Models\MantenimientoRecurrente;
use App\Models\Movimiento;
use App\Models\Omision;
use App\Models\Pago;
use App\Models\Plantilla;
use App\Models\Politica;
use App\Models\Principal;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\ReglaLimpieza;
use App\Models\Reserva;
use App\Models\Tarea;
use App\Models\Tarifa;
use App\Models\TemporadaTarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Webhook;
use App\Sincronizacion\ContextoSincronizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

/**
 * Antes y después de lo que el personal cambia (D-077), además de las acciones con nombre que ya se anotaban. Lo que hacen la sincronización,
 * las tareas programadas y los huéspedes no se anota: ahí no hay una persona del equipo que responda. **Los datos personales no se copian aquí:** el registro lo ve quien administra y dura un año. De ellos solo
 * queda qué campo cambió.
 */
final class AuditoriaModelos
{
    private const MODELOS = [
        AccesoUsuario::class, Automatizacion::class, Caja::class, Canal::class, CargoReserva::class, ConceptoCargo::class, ConexionCompartida::class, ContactoHuesped::class,
        Cupon::class, EntidadFiscal::class, Factura::class, FuenteIcal::class, Grupo::class, Huesped::class, Impuesto::class, ListaComprobacion::class, MantenimientoRecurrente::class,
        Movimiento::class, Omision::class, Pago::class, Plantilla::class, Politica::class, Principal::class, Propiedad::class, ReglaLimpieza::class, Reserva::class, Tarea::class, Tarifa::class,
        TemporadaTarifa::class, Unidad::class, User::class, Webhook::class,
    ];

    /** Nunca se anota su valor: secretos, o lo que puede decir quién es alguien. */
    private const SIN_VALOR = [
        'password', 'app_authentication_secret', 'app_authentication_recovery_codes', 'contrasena', 'usuario', 'token_hash', 'url', 'secreto', 'ajustes',
        'documento_numero', 'documento_numero_hmac', 'comentario', 'notas', 'valor', 'valor_normalizado', 'email', 'name', 'motivo', 'referencia', 'cuerpo', 'descripcion', 'receptor', 'emisor',
    ];

    /** Fichas de personas: de ellas ningún valor se copia. */
    private const SOLO_CAMPOS = [ContactoHuesped::class, EntidadFiscal::class, Factura::class, Grupo::class, Huesped::class];

    /** Cambian solos: no son algo que alguien haya decidido. */
    private const IGNORADOS = ['actualizado_en', 'updated_at', 'remember_token', 'ultimo_acceso_en', 'ultimo_uso_en', 'consecutivo_reserva', 'huella_datos', 'pagado', 'version'];

    private static int $pausa = 0;

    public static function registrar(): void
    {
        foreach (self::MODELOS as $modelo) {
            Event::listen('eloquent.created: '.$modelo, fn (Model $m) => self::anotar($m, 'cambio.crear'));
            Event::listen('eloquent.updated: '.$modelo, fn (Model $m) => self::anotar($m, 'cambio.editar'));
            Event::listen('eloquent.deleted: '.$modelo, fn (Model $m) => self::anotar($m, 'cambio.borrar'));
        }
    }

    /**
     * Para lo que crea miles de filas de una vez, como la demostración.
     *
     * @template T
     *
     * @param  callable(): T  $trabajo
     * @return T
     */
    public static function sinAnotar(callable $trabajo): mixed
    {
        self::$pausa++;
        try {
            return $trabajo();
        } finally {
            self::$pausa--;
        }
    }

    private static function anotar(Model $modelo, string $accion): void
    {
        if (self::$pausa > 0 || ! auth()->check() || ContextoSincronizacion::activo() || ModoDemo::activo()) {
            return;
        }
        $cambios = $accion === 'cambio.editar' ? self::diferencias($modelo) : [];
        if ($accion === 'cambio.editar' && $cambios === []) {
            return;
        }
        $propiedad = $modelo->getAttribute('propiedad_id');
        // Una propiedad recién borrada ya no se puede referenciar: queda solo su número en el registro.
        $delaPropiedad = $modelo instanceof Propiedad && $accion !== 'cambio.borrar' ? (int) $modelo->getKey() : null;
        RegistroAuditoria::anotar($accion, $modelo, $cambios, is_numeric($propiedad) ? (int) $propiedad : $delaPropiedad);
    }

    /**
     * @return array<string, array{antes: mixed, despues: mixed}|string>
     */
    private static function diferencias(Model $modelo): array
    {
        $soloCampos = in_array($modelo::class, self::SOLO_CAMPOS, true);
        $diferencias = [];
        foreach (array_keys($modelo->getChanges()) as $campo) {
            if (in_array($campo, self::IGNORADOS, true)) {
                continue;
            }
            $diferencias[$campo] = $soloCampos || in_array($campo, self::SIN_VALOR, true) || str_starts_with((string) ($modelo->getCasts()[$campo] ?? ''), 'encrypted')
                ? 'cambió'
                : ['antes' => self::recortar($modelo->getRawOriginal($campo)), 'despues' => self::recortar($modelo->getAttributes()[$campo] ?? null)];
        }

        return $diferencias;
    }

    private static function recortar(mixed $valor): mixed
    {
        return is_string($valor) ? mb_substr($valor, 0, 200) : $valor;
    }
}
