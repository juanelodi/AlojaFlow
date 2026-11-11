<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

/**
 * Entrega la copia línea por línea, sin cargarla entera (subordinacion.md B9). El archivo solo se abre para leerlo
 * como texto: nunca se incluye ni se ejecuta (regla 11).
 */
final class LineasCopia
{
    private const PEDAZO = 65536;

    /** @var resource */
    private $archivo;

    public function __construct(string $ruta, private readonly int $bytesMaximosPorLinea)
    {
        $archivo = fopen($ruta, 'rb');
        if ($archivo === false) {
            throw new ErrorSincronizacion(CodigoError::ErrorInterno, 'leer');
        }
        $this->archivo = $archivo;
    }

    /**
     * La línea siguiente, sin su salto ni el retorno de carro de antes; nulo cuando ya no hay más.
     *
     * @phpstan-impure
     */
    public function siguiente(): ?string
    {
        $linea = '';
        do {
            $pedazo = fgets($this->archivo, self::PEDAZO);
            if ($pedazo === false) {
                if ($linea === '') {
                    return null;
                }
                // La última línea, si el archivo no acaba en salto.
                break;
            }
            $linea .= $pedazo;
            // Una línea sin fin llenaría la memoria.
            if (strlen($linea) > $this->bytesMaximosPorLinea) {
                throw new ErrorSincronizacion(CodigoError::FormatoInvalido, 'leer');
            }
        } while (! str_ends_with($pedazo, "\n"));

        if (str_ends_with($linea, "\n")) {
            $linea = substr($linea, 0, -1);
        }

        return str_ends_with($linea, "\r") ? substr($linea, 0, -1) : $linea;
    }

    public function cerrar(): void
    {
        if (is_resource($this->archivo)) {
            fclose($this->archivo);
        }
    }
}
