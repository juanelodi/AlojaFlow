<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Dinero;

use App\Models\Propiedad;
use App\Support\Dinero;
use App\Support\Formato;

/**
 * La segunda moneda de una propiedad, con un tipo de cambio que se escribe a mano (D-157): pesos y dólares en la costa, euros y
 * libras en otra. Todo se cobra y se suma en la moneda de la propiedad; la segunda sirve para mostrar lo que sería y para recibir
 * efectivo en ella. El tipo dice cuántas unidades de la moneda de la propiedad vale una de la segunda (1 USD = 18.50 MXN).
 */
final class SegundaMoneda
{
    public const AJUSTE = 'segunda_moneda';

    public function __construct(private readonly Propiedad $propiedad)
    {
    }

    public function moneda(): ?string
    {
        $moneda = $this->ajuste()['moneda'] ?? null;

        return is_string($moneda) && preg_match('/^[A-Z]{3}$/', $moneda) === 1 && $moneda !== $this->propiedad->moneda && $this->tipo() !== null ? $moneda : null;
    }

    /**
     * Como texto decimal, el que se escribió.
     */
    public function tipo(): ?string
    {
        $tipo = $this->ajuste()['tipo'] ?? null;

        return is_string($tipo) && is_numeric($tipo) && (float) $tipo > 0 ? $tipo : null;
    }

    public function activa(): bool
    {
        return $this->moneda() !== null;
    }

    /**
     * Un importe de la propiedad en la segunda moneda, en su unidad menor. Null si no hay segunda moneda.
     */
    public function equivalente(int $importe): ?int
    {
        $moneda = $this->moneda();

        return $moneda === null ? null : self::convertir($importe, $this->propiedad->moneda, $moneda, 1 / (float) $this->tipo());
    }

    /**
     * «≈ US$162.16», para poner junto al importe. Null si no hay segunda moneda.
     */
    public function texto(int $importe): ?string
    {
        $equivalente = $this->equivalente($importe);

        return $equivalente === null ? null : '≈ '.Formato::dinero($equivalente, (string) $this->moneda());
    }

    /**
     * Lo recibido en la segunda moneda, en la de la propiedad, con el tipo de cambio que se use ese día.
     */
    public function aPrincipal(int $importe, string $tipo): int
    {
        return self::convertir($importe, (string) $this->moneda(), $this->propiedad->moneda, (float) $tipo);
    }

    /**
     * Vacía la quita.
     */
    public function guardar(?string $moneda, ?string $tipo): void
    {
        $ajustes = (array) $this->propiedad->ajustes;
        $moneda = strtoupper(trim((string) $moneda));
        if ($moneda === '' || ! is_numeric($tipo) || (float) $tipo <= 0) {
            unset($ajustes[self::AJUSTE]);
        } else {
            $ajustes[self::AJUSTE] = ['moneda' => $moneda, 'tipo' => rtrim(rtrim(number_format((float) $tipo, 6, '.', ''), '0'), '.')];
        }
        $this->propiedad->ajustes = $ajustes;
        $this->propiedad->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function ajuste(): array
    {
        return (array) (((array) $this->propiedad->ajustes)[self::AJUSTE] ?? []);
    }

    private static function convertir(int $importe, string $de, string $a, float $factor): int
    {
        $valor = $importe / (10 ** Dinero::decimales($de)) * $factor;

        return (int) round($valor * (10 ** Dinero::decimales($a)), 0, PHP_ROUND_HALF_UP);
    }
}
