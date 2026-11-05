<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Paises;

use App\Autoridades\RegistroViajeros;
use App\Enums\TipoDocumentoLegal;
use App\Facturacion\GeneradorFiscal;
use Illuminate\Support\Facades\Lang;

/**
 * Lo común. Los plazos son los propuestos en modelo-datos.md §8.
 */
abstract class PaisBase implements Pais
{
    abstract protected function clave(): string;

    public function sugerenciasImpuesto(): array
    {
        return [];
    }

    public function camposLlegada(): array
    {
        return [];
    }

    public function documentoDeTodos(): bool
    {
        return false;
    }

    public function diasConservarImagenes(): int
    {
        return 30;
    }

    public function diasConservarRegistro(): int
    {
        return 365;
    }

    public function legalRevisado(): bool
    {
        return false;
    }

    public function generadorFiscal(): ?GeneradorFiscal
    {
        return null;
    }

    public function registroViajeros(): ?RegistroViajeros
    {
        return null;
    }

    public function declaracionEstancias(): ?DeclaracionEstancias
    {
        return null;
    }

    public function fechaConMesPrimero(): bool
    {
        return false;
    }

    public function unidadLargo(): string
    {
        return 'm';
    }

    public function exportaLibroFiscal(): bool
    {
        return false;
    }

    public function textosBase(string $idioma): array
    {
        $textos = [];
        foreach (TipoDocumentoLegal::cases() as $tipo) {
            foreach ([$this->clave(), 'generico'] as $pais) {
                $clave = 'legal.'.$pais.'.'.$tipo->value;
                if (Lang::has($clave.'.cuerpo', $idioma, false)) {
                    $textos[$tipo->value] = ['titulo' => __($clave.'.titulo', [], $idioma), 'cuerpo' => __($clave.'.cuerpo', [], $idioma)];
                    break;
                }
            }
        }

        return $textos;
    }
}
