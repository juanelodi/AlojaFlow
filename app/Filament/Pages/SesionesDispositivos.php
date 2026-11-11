<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Demo\ModoDemo;
use App\Models\DispositivoRecordado;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Seguridad\Acceso;
use App\Seguridad\Dispositivos;
use App\Support\Ajustes;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

/**
 * Dónde está abierta la cuenta (D-076). Cada quien ve lo suyo, y quien administra, lo de todos.
 */
class SesionesDispositivos extends Page
{
    protected string $view = 'filament.pages.sesiones-dispositivos';

    protected static ?string $slug = 'sesiones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?int $navigationSort = 65;

    public ?int $diasRecordar = null;

    public static function canAccess(): bool
    {
        return Acceso::usuarioActual() !== null && ! ModoDemo::activo();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) Acceso::usuarioActual()?->es_administrador;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.sistema');
    }

    public static function getNavigationLabel(): string
    {
        return __('permisos.sesiones.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('permisos.sesiones.titulo');
    }

    public function mount(): void
    {
        $this->diasRecordar = Dispositivos::diasRecordar();
    }

    public function veTodos(): bool
    {
        return (bool) Acceso::usuarioActual()?->es_administrador;
    }

    /**
     * El identificador de la sesión no sale de aquí: con él se podría entrar como esa persona.
     *
     * @return list<array{clave: string, usuario: string, dispositivo: string, ip: string, ultimo: CarbonImmutable, actual: bool}>
     */
    public function sesiones(): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }
        $actual = session()->getId();

        return $this->sesionesPermitidas()->orderByDesc('s.last_activity')->limit(200)
            ->get(['s.id', 's.ip_address', 's.user_agent', 's.last_activity', 'u.name'])
            ->map(fn (object $s): array => [
                'clave' => hash('sha256', (string) $s->id),
                'usuario' => (string) $s->name,
                'dispositivo' => Dispositivos::describir($s->user_agent),
                'ip' => (string) $s->ip_address,
                'ultimo' => CarbonImmutable::createFromTimestampUTC((int) $s->last_activity),
                'actual' => $s->id === $actual,
            ])->values()->all();
    }

    /**
     * @return list<array{id: int, usuario: string, dispositivo: string, ip: string, ultimo: CarbonImmutable|null, expira: CarbonImmutable}>
     */
    public function dispositivos(): array
    {
        return DispositivoRecordado::query()->with('usuario')
            ->when(! $this->veTodos(), fn ($q) => $q->where('user_id', auth()->id()))
            ->orderByDesc('ultimo_uso_en')->limit(200)->get()
            ->map(fn (DispositivoRecordado $d): array => [
                'id' => $d->id,
                'usuario' => $d->usuario->name,
                'dispositivo' => Dispositivos::describir($d->agente),
                'ip' => (string) $d->ip,
                'ultimo' => $d->ultimo_uso_en?->toImmutable(),
                'expira' => $d->expira_en->toImmutable(),
            ])->values()->all();
    }

    public function cerrarSesion(string $clave): void
    {
        foreach ($this->sesionesPermitidas()->pluck('s.id') as $id) {
            if (hash_equals(hash('sha256', (string) $id), $clave) && $id !== session()->getId()) {
                DB::table('sessions')->where('id', $id)->delete();
                RegistroAuditoria::anotar('sesion.cerrar');
            }
        }
    }

    public function olvidarDispositivo(int $id): void
    {
        if (DispositivoRecordado::query()->whereKey($id)->when(! $this->veTodos(), fn ($q) => $q->where('user_id', auth()->id()))->delete() > 0) {
            RegistroAuditoria::anotar('dispositivo.olvidar');
        }
    }

    public function cerrarLasDemas(): void
    {
        DB::table('sessions')->where('user_id', auth()->id())->where('id', '!=', session()->getId())->delete();
        // Este se queda solo si es el que está usando.
        $cookie = request()->cookie(Dispositivos::COOKIE);
        $selector = is_string($cookie) ? explode('.', $cookie, 2)[0] : '';
        DispositivoRecordado::query()->where('user_id', auth()->id())->where('selector', '!=', $selector)->delete();
        RegistroAuditoria::anotar('sesion.cerrar_demas');
        Notification::make()->success()->title(__('permisos.sesiones.cerradas'))->send();
    }

    public function guardarDias(): void
    {
        if (! $this->veTodos()) {
            return;
        }
        $this->validate(['diasRecordar' => ['required', 'integer', 'min:0', 'max:365']], [], ['diasRecordar' => __('permisos.sesiones.dias')]);
        Ajustes::guardar(Dispositivos::AJUSTE_DIAS, $this->diasRecordar);
        if ($this->diasRecordar === 0) {
            DispositivoRecordado::query()->delete();
        }
        RegistroAuditoria::anotar('sesion.dias_recordar', null, ['dias' => $this->diasRecordar]);
        Notification::make()->success()->title(__('permisos.sesiones.guardado'))->send();
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function sesionesPermitidas()
    {
        return DB::table('sessions as s')->join((new User())->getTable().' as u', 'u.id', '=', 's.user_id')
            ->when(! $this->veTodos(), fn ($q) => $q->where('s.user_id', auth()->id()));
    }
}
