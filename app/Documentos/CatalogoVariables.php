<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Documentos;

use App\Comunicacion\VariablesMensaje;
use App\Enums\EntidadCampo;
use App\Models\CampoPropio;
use App\Modulos\Modulo;
use App\Modulos\Modulos;

/**
 * La lista de variables que se ofrece al escribir un documento o una plantilla, cada una con lo que hace. Una prueba comprueba que todas se
 * sustituyen de verdad: lo que está aquí funciona.
 *
 * @phpstan-type Grupo array{clave: string, titulo: string, nota: ?string, variables: list<array{nombre: string, descripcion: string}>}
 */
final class CatalogoVariables
{
    private const DOCUMENTO = [
        'estancia' => ['numero', 'codigo', 'llegada', 'llegada_corta', 'salida', 'salida_corta', 'noches', 'personas', 'ninos', 'unidad', 'tarifa', 'origen', 'comentario'],
        'titular' => ['huesped', 'nombre', 'huesped_telefono', 'huesped_correo', 'huesped_nacimiento', 'huesped_nacionalidad', 'huesped_documento', 'huesped_domicilio'],
        'dinero' => ['total', 'pagado', 'pendiente', 'deposito', 'cuota_importe', 'cuota_vence', 'cuota_periodo'],
        'propiedad' => ['propiedad', 'domicilio_propiedad', 'telefono_propiedad', 'correo_propiedad', 'pagina_propiedad', 'whatsapp_propiedad', 'mapa_propiedad', 'hoy'],
        'huespedes' => ['h_numero', 'h_nombre', 'h_nacimiento', 'h_nacionalidad', 'h_documento_tipo', 'h_documento', 'h_domicilio', 'h_parentesco'],
        'cargos' => ['c_nombre', 'c_cantidad', 'c_importe'],
        'pagos' => ['p_fecha', 'p_metodo', 'p_referencia', 'p_importe'],
        'periodo' => ['desde', 'hasta', 'cuantas'],
    ];

    private const MENSAJE = [
        'estancia' => ['numero', 'codigo', 'llegada', 'salida', 'noches', 'personas', 'unidad'],
        'titular' => ['huesped', 'nombre'],
        'dinero' => ['total', 'pagado', 'pendiente', 'cuota_importe', 'cuota_vence', 'cuota_periodo'],
        'propiedad' => ['propiedad', 'telefono_propiedad', 'correo_propiedad', 'pagina_propiedad', 'whatsapp_propiedad', 'mapa_propiedad'],
        'enlaces' => ['enlace_reserva', 'enlace_llegada', 'enlace_mi_reserva', 'enlace_opinion'],
    ];

    /**
     * @return list<Grupo>
     */
    public static function paraDocumento(?int $propiedadId): array
    {
        return [...self::grupos(self::DOCUMENTO), ...self::propias($propiedadId, conHuespedes: true)];
    }

    /**
     * @return list<Grupo>
     */
    public static function paraMensaje(?int $propiedadId): array
    {
        return [...self::grupos(self::MENSAJE), ...self::propias($propiedadId, conHuespedes: false)];
    }

    /**
     * @param  array<string, list<string>>  $definicion
     * @return list<Grupo>
     */
    private static function grupos(array $definicion): array
    {
        $grupos = [];
        foreach ($definicion as $clave => $nombres) {
            $grupos[] = [
                'clave' => $clave,
                'titulo' => __('variables.grupos.'.$clave),
                'nota' => in_array($clave, ['huespedes', 'cargos', 'pagos', 'periodo'], true) ? __('variables.notas.'.$clave) : null,
                'variables' => array_map(fn (string $nombre): array => ['nombre' => $nombre, 'descripcion' => __('variables.lista.'.$nombre)], $nombres),
            ];
        }

        return $grupos;
    }

    /**
     * Las que puso el alojamiento: sus campos propios y sus variables de los mensajes.
     *
     * @return list<Grupo>
     */
    private static function propias(?int $propiedadId, bool $conHuespedes): array
    {
        if ($propiedadId === null) {
            return [];
        }
        $variables = [];
        if (Modulos::activo(Modulo::Documentos)) {
            foreach (CampoPropio::query()->where('propiedad_id', $propiedadId)->orderBy('orden')->orderBy('nombre')->get() as $campo) {
                $variables[] = ['nombre' => 'campo_'.$campo->clave, 'descripcion' => __('variables.campo', ['campo' => $campo->nombre])];
                if ($conHuespedes && $campo->entidad === EntidadCampo::Huesped) {
                    $variables[] = ['nombre' => 'h_campo_'.$campo->clave, 'descripcion' => __('variables.campo_huesped', ['campo' => $campo->nombre])];
                }
            }
        }
        foreach (VariablesMensaje::propias($propiedadId) as $nombre => $valor) {
            $variables[] = ['nombre' => (string) $nombre, 'descripcion' => $valor === '' ? __('variables.propia') : __('variables.propia_valor', ['valor' => mb_strimwidth($valor, 0, 60, '…')])];
        }

        return $variables === [] ? [] : [['clave' => 'propias', 'titulo' => __('variables.grupos.propias'), 'nota' => null, 'variables' => $variables]];
    }
}
