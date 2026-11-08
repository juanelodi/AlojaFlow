<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actualizacion\ActualizacionAutomatica;
use App\Actualizacion\Actualizador;
use App\Actualizacion\ErrorActualizacion;
use App\Actualizacion\UltimaVersion;
use App\Demo\ModoDemo;
use App\Enums\EstadoActualizacion;
use App\Models\Actualizacion;
use App\Models\RegistroAuditoria;
use App\Seguridad\Acceso;
use App\Support\Formato;
use BackedEnum;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Actualizar AlojaFlow bajando la versión nueva (D-140) o subiendo su zip (instalacion-actualizaciones.md).
 * Solo el super administrador; se confirma con la contraseña.
 */
class Actualizaciones extends Page
{
    protected string $view = 'filament.pages.actualizaciones';

    protected static ?string $slug = 'actualizaciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?int $navigationSort = 80;


    public ?string $paquete = null;

    public bool $reinstalar = false;

    public bool $avisoActivo = true;

    /** Instalar sola una versión nueva a las 3:00 (D-151). */
    public bool $automaticas = false;

    /** Instalar sola una actualización urgente que ordene el equipo de AlojaFlow (D-151). */
    public bool $urgentes = true;

    public string $zona = 'UTC';

    /** @var array<string, mixed>|null */
    public ?array $revision = null;

    public static function canAccess(): bool
    {
        return Acceso::puedeActualizar(Acceso::usuarioActual()) && ! ModoDemo::activo();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.sistema');
    }

    public static function getNavigationLabel(): string
    {
        return __('actualizaciones.titulo');
    }

    /**
     * El grupo Sistema empieza plegado: la versión también se anuncia en el inicio.
     */
    public static function getNavigationBadge(): ?string
    {
        return self::canAccess() ? app(UltimaVersion::class)->paraAvisar()['version'] ?? null : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('actualizaciones.titulo');
    }

    public function mount(): void
    {
        $this->paquete = $this->actualizador()->paquetesDisponibles()[0] ?? null;
        $this->avisoActivo = $this->ultimaVersion()->avisoActivo();
        $automatica = app(ActualizacionAutomatica::class);
        $this->automaticas = $automatica->automaticasActivas();
        $this->urgentes = $automatica->urgentesActivas();
        $this->zona = $automatica->zona();
    }

    public function guardarAutomaticas(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->validate(['zona' => ['required', 'timezone:all']]);
        app(ActualizacionAutomatica::class)->guardar($this->automaticas, $this->urgentes, $this->zona);
        RegistroAuditoria::anotar('actualizacion.automaticas', null, ['automaticas' => $this->automaticas, 'urgentes' => $this->urgentes, 'zona' => $this->zona]);
        Notification::make()->success()->title(__('actualizaciones.automaticas.guardado'))->send();
    }

    /**
     * @return list<string>
     */
    public function zonas(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    public function automatica(): ActualizacionAutomatica
    {
        return app(ActualizacionAutomatica::class);
    }

    public function updatedAvisoActivo(): void
    {
        $this->ultimaVersion()->cambiarAviso($this->avisoActivo);
        Notification::make()->success()->title(__($this->avisoActivo ? 'actualizaciones.aviso_prendido' : 'actualizaciones.aviso_apagado'))->send();
    }

    /**
     * Preguntar a mano vale aunque el aviso diario esté apagado: es algo que pidió quien pulsa.
     */
    public function buscarAhora(): void
    {
        if (! $this->ultimaVersion()->consultar()) {
            Notification::make()->warning()->title(__('actualizaciones.consulta_fallida_ahora'))->send();

            return;
        }
        $nueva = $this->ultimaVersion()->disponible();
        Notification::make()->success()->title($nueva !== null ? __('actualizaciones.hay_version', ['version' => $nueva['version']]) : __('actualizaciones.al_dia'))->send();
    }

    /**
     * @return array{version: string, notas: string, enlace: ?string}|null
     */
    public function versionDisponible(): ?array
    {
        return $this->ultimaVersion()->disponible();
    }

    /**
     * Cómo fue la última vez que se preguntó, para decirlo en la pantalla.
     */
    public function estadoConsulta(): string
    {
        $consulta = $this->ultimaVersion()->ultimaConsulta();
        $cuando = $consulta['consultada_en'] ?? null;
        if (! is_string($cuando)) {
            return __('actualizaciones.sin_consulta');
        }
        $fecha = Formato::momento(Carbon::parse($cuando));

        return __(($consulta['fallo'] ?? false) === true ? 'actualizaciones.consulta_fallida' : 'actualizaciones.ultima_consulta', ['fecha' => $fecha]);
    }

    /**
     * Terminó de subir: el JavaScript de la pantalla avisa, y se revisa sin pedir otro clic (lo primero que pidió
     * quien la usa: que no se quede sin decir nada entre el paso 1 y el 2).
     */
    public function subidaTerminada(string $paquete): void
    {
        if (! in_array($paquete, $this->actualizador()->paquetesDisponibles(), true)) {
            return;
        }
        $this->paquete = $paquete;
        $this->reinstalar = false;
        $this->revisar();
    }

    /**
     * En qué paso va, para la barra de arriba.
     */
    public function pasoActual(): int
    {
        return ($this->revision['valida'] ?? false) ? 3 : ($this->revision !== null || $this->paquete !== null ? 2 : 1);
    }

    public function revisar(): void
    {
        $this->revision = null;
        if ($this->paquete === null || ! in_array($this->paquete, $this->actualizador()->paquetesDisponibles(), true)) {
            Notification::make()->warning()->title(__('actualizaciones.elige_paquete'))->send();

            return;
        }
        try {
            $this->revision = $this->actualizador()->revisar($this->paquete, $this->reinstalar)->resumen();
        } catch (ErrorActualizacion $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    public function updatedPaquete(): void
    {
        $this->revision = null;
    }

    public function updatedReinstalar(): void
    {
        $this->revision = null;
    }

    public function actualizarAction(): Action
    {
        return Action::make('actualizar')
            ->label(fn (): string => __('actualizaciones.actualizar', ['version' => (string) ($this->revision['version'] ?? '')]))
            ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
            ->color('warning')
            ->visible(fn (): bool => (bool) ($this->revision['valida'] ?? false))
            ->requiresConfirmation()
            ->modalHeading(__('actualizaciones.confirmar_titulo'))
            ->modalDescription(__('actualizaciones.confirmar_texto'))
            ->schema([
                TextInput::make('contrasena')->label(__('actualizaciones.contrasena'))->password()->required()->currentPassword(),
            ])
            ->action(function (): void {
                if ($this->paquete === null) {
                    return;
                }
                try {
                    $actualizacion = $this->actualizador()->preparar($this->paquete, $this->reinstalar, Acceso::usuarioActual());
                } catch (ErrorActualizacion $e) {
                    Notification::make()->danger()->title($e->getMessage())->persistent()->send();

                    return;
                }
                $this->redirect(URL::temporarySignedRoute('actualizaciones.instalando', now()->addMinutes(30), ['actualizacion' => $actualizacion->id]));
            });
    }

    public function eliminarPaqueteAction(): Action
    {
        return Action::make('eliminarPaquete')
            ->label(__('actualizaciones.eliminar'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->outlined()
            ->requiresConfirmation()
            ->modalHeading(fn (): string => __('actualizaciones.eliminar_titulo', ['paquete' => (string) $this->paquete]))
            ->modalDescription(__('actualizaciones.eliminar_texto'))
            ->action(function (): void {
                if ($this->paquete === null) {
                    return;
                }
                try {
                    File::delete($this->actualizador()->rutaPaquete($this->paquete));
                    Notification::make()->success()->title(__('actualizaciones.eliminado', ['paquete' => $this->paquete]))->send();
                } catch (ErrorActualizacion $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
                $this->paquete = $this->actualizador()->paquetesDisponibles()[0] ?? null;
                $this->revision = null;
            });
    }

    public function versionInstalada(): string
    {
        return $this->actualizador()->versionInstalada();
    }

    public function bloqueadaPorGit(): bool
    {
        return $this->actualizador()->bloqueadaPorGit();
    }

    /**
     * @return list<string>
     */
    public function disponibles(): array
    {
        return $this->actualizador()->paquetesDisponibles();
    }

    public function carpetaEntrada(): string
    {
        return str_replace('\\', '/', Str::after($this->actualizador()->carpetaEntrada(), base_path().DIRECTORY_SEPARATOR));
    }

    /**
     * Una actualización que dejó los archivos puestos pero no terminó (por ejemplo, se cerró el
     * navegador): su enlace para terminarla.
     */
    public function enlaceParaTerminar(): ?string
    {
        $pendiente = $this->actualizador()->pendienteDeFinalizar();

        return $pendiente ? URL::temporarySignedRoute('actualizaciones.finalizar', now()->addHour(), ['actualizacion' => $pendiente->id]) : null;
    }

    /**
     * @return Collection<int, Actualizacion>
     */
    public function historial(): Collection
    {
        return Actualizacion::query()->with('usuario')->latest('id')->limit(20)->get();
    }

    public function tieneCopia(Actualizacion $actualizacion): bool
    {
        return is_file($this->actualizador()->carpetaDe($actualizacion).'/'.Actualizador::COPIA_BASE_DATOS)
            && $actualizacion->estado !== EstadoActualizacion::Preparada;
    }


    private function ultimaVersion(): UltimaVersion
    {
        return app(UltimaVersion::class);
    }

    private function actualizador(): Actualizador
    {
        return app(Actualizador::class);
    }
}
