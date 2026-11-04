<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Enums\ModoPropiedad;
use App\Models\Propiedad;
use App\Seguridad\Acceso;
use Livewire\Attributes\Session;

/**
 * Selector «todas / una propiedad» de las pantallas operativas, limitado a las propiedades en
 * las que el usuario tiene el permiso de la página. Se recuerda en la sesión.
 */
trait ConPropiedadSeleccionada
{
    #[Session(key: 'propiedad_seleccionada')]
    public ?int $propiedadId = null;

    abstract protected static function permiso(): string;

    /**
     * ?propiedad=ID en la dirección elige la propiedad (enlaces desde el resumen).
     */
    public function mountConPropiedadSeleccionada(): void
    {
        $elegida = request()->integer('propiedad');
        if ($elegida > 0 && in_array($elegida, $this->propiedadesVisibles(), true)) {
            $this->propiedadId = $elegida;
        }
    }

    public static function canAccess(): bool
    {
        return Acceso::puede(Acceso::usuarioActual(), static::permiso());
    }

    /**
     * @return list<int>
     */
    public function propiedadesVisibles(): array
    {
        return Acceso::propiedades(Acceso::usuarioActual(), static::permiso());
    }

    /**
     * Propiedades a mostrar: la elegida (si el usuario la puede ver) o todas las visibles.
     *
     * @return list<int>
     */
    public function propiedadesElegidas(): array
    {
        $visibles = $this->propiedadesVisibles();

        return $this->propiedadId !== null && in_array($this->propiedadId, $visibles, true) ? [$this->propiedadId] : $visibles;
    }

    /**
     * Propiedades elegidas en las que el usuario puede crear y cambiar reservas.
     *
     * @return list<int>
     */
    public function propiedadesGestionables(): array
    {
        return array_values(array_intersect($this->propiedadesElegidas(), Acceso::propiedadesIndependientes(Acceso::usuarioActual(), Acceso::RESERVAS)));
    }

    /**
     * Si alguna de las propiedades elegidas depende de una principal, para avisar dónde se cambian.
     */
    public function haySubordinadas(): bool
    {
        return Propiedad::query()->whereIn('id', $this->propiedadesElegidas())->where('modo', ModoPropiedad::Subordinada->value)->exists();
    }

    /**
     * @return array<int, string>
     */
    public function opcionesPropiedad(): array
    {
        return Propiedad::query()->whereIn('id', $this->propiedadesVisibles())->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    public function propiedadUnica(): ?Propiedad
    {
        $elegidas = $this->propiedadesElegidas();

        return count($elegidas) === 1 ? Propiedad::query()->find($elegidas[0]) : null;
    }
}
