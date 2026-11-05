<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Demo\ModoDemo;
use App\Models\RegistroAuditoria;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Seguridad\Acceso;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Las preguntas de la primera entrada: deciden qué módulos se prenden (D-069). Se pueden saltar, y entonces
 * queda todo prendido, y se pueden repetir desde Sistema → Módulos.
 */
class PrimerasPreguntas extends Page
{
    /** Lo que se marca de entrada la primera vez: no se usan siempre, pero casi nadie se arrepiente. */
    private const RECOMENDADOS = [Modulo::Limpieza, Modulo::Mensajes, Modulo::Informes];

    public const PASOS = 5;

    protected string $view = 'filament.pages.primeras-preguntas';

    protected static ?string $slug = 'primeras-preguntas';

    protected static bool $shouldRegisterNavigation = false;

    /** Va del 1 a PASOS, y uno más es el resumen. */
    public int $paso = 1;

    public string $propiedades = 'una';

    public string $otroSistema = 'no';

    /** @var array<string, bool> */
    public array $elegidos = [];

    public static function canAccess(): bool
    {
        return Acceso::esSuper(Acceso::usuarioActual()) && ! ModoDemo::activo();
    }

    public function getTitle(): string|Htmlable
    {
        return __('modulos.preguntas.titulo');
    }

    public function mount(): void
    {
        $primeraVez = Modulos::faltanPreguntas();
        foreach (Modulo::cases() as $modulo) {
            $this->elegidos[$modulo->value] = $primeraVez ? in_array($modulo, self::RECOMENDADOS, true) : Modulos::activo($modulo);
        }
        if (! $primeraVez) {
            $this->propiedades = Modulos::activo(Modulo::VariasPropiedades) ? 'varias' : 'una';
            $this->otroSistema = Modulos::activo(Modulo::Subordinacion) ? 'principal' : 'no';
        }
    }

    public function siguiente(): void
    {
        $this->paso = min(self::PASOS + 1, $this->paso + 1);
    }

    public function atras(): void
    {
        $this->paso = max(1, $this->paso - 1);
    }

    public function updatedElegidos(mixed $valor, string $clave): void
    {
        $this->elegidos = Modulos::ajustarElegidos($this->elegidos, $clave);
    }

    /**
     * @return list<Modulo>
     */
    public function modulosDe(string ...$grupos): array
    {
        return array_values(array_filter($this->opcionales(), fn (Modulo $m): bool => in_array($m->grupo(), $grupos, true)));
    }

    /**
     * Lo que queda prendido según lo que respondió.
     *
     * @return list<Modulo>
     */
    public function resultado(): array
    {
        $elegidos = array_values(array_filter($this->opcionales(), fn (Modulo $m): bool => (bool) ($this->elegidos[$m->value] ?? false)));
        if ($this->propiedades === 'varias') {
            $elegidos[] = Modulo::VariasPropiedades;
        }
        if ($this->otroSistema !== 'no') {
            $elegidos[] = Modulo::Subordinacion;
        }

        return Modulos::conDependencias($elegidos);
    }

    /**
     * @return list<Modulo>
     */
    public function apagados(): array
    {
        return array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => ! in_array($m, $this->resultado(), true)));
    }

    public function terminar(): void
    {
        $this->cerrar($this->resultado(), 'preguntas');
        Notification::make()->success()->title(__('modulos.preguntas.listo'))->send();
    }

    public function saltar(): void
    {
        $this->cerrar(Modulo::cases(), 'saltadas');
    }

    /**
     * @param  list<Modulo>  $modulos
     */
    private function cerrar(array $modulos, string $como): void
    {
        Modulos::guardar($modulos);
        Modulos::marcarPreguntas($como);
        RegistroAuditoria::anotar('modulos.preguntas', null, ['como' => $como, 'activos' => array_map(fn (Modulo $m): string => $m->value, $modulos)]);
        $this->redirect(Resumen::getUrl());
    }

    /**
     * Los dos de la primera pantalla y la segunda no son casillas: salen de lo que respondió.
     *
     * @return list<Modulo>
     */
    private function opcionales(): array
    {
        return array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m->grupo() !== 'propiedades'));
    }
}
