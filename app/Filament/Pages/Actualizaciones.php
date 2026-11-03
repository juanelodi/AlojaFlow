<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actualizacion\Actualizador;
use App\Actualizacion\ErrorActualizacion;
use App\Enums\EstadoActualizacion;
use App\Models\Actualizacion;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Actualizar AlojaFlow subiendo el zip de una versión (instalacion-actualizaciones.md).
 * Solo el administrador; se confirma con la contraseña.
 *
 * @property-read Schema $form
 */
class Actualizaciones extends Page
{
    protected string $view = 'filament.pages.actualizaciones';

    protected static ?string $slug = 'actualizaciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?int $navigationSort = 80;

    /** @var array<string, mixed>|null */
    public ?array $subida = [];

    public ?string $paquete = null;

    public bool $reinstalar = false;

    /** @var array<string, mixed>|null */
    public ?array $revision = null;

    public static function canAccess(): bool
    {
        return (bool) Acceso::usuarioActual()?->es_administrador;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.sistema');
    }

    public static function getNavigationLabel(): string
    {
        return __('actualizaciones.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('actualizaciones.titulo');
    }

    public function mount(): void
    {
        $this->form->fill();
        $this->paquete = $this->actualizador()->paquetesDisponibles()[0] ?? null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('archivo')
                    ->label(__('actualizaciones.subir'))
                    ->helperText(__('actualizaciones.subir_ayuda', $this->limites()))
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'application/octet-stream'])
                    ->maxSize((int) ((int) config('alojaflow.actualizaciones.tamano_maximo_paquete') / 1024))
                    ->storeFiles(false)
                    ->required(),
            ])
            ->statePath('subida');
    }

    /**
     * El zip subido pasa a la carpeta de entrada del actualizador, la misma que la del FTP.
     */
    public function subir(): void
    {
        $archivo = $this->form->getState()['archivo'] ?? null;
        if (! $archivo instanceof TemporaryUploadedFile) {
            return;
        }
        $nombre = Actualizador::nombreSeguro($archivo->getClientOriginalName());
        $carpeta = $this->actualizador()->carpetaEntrada();
        File::ensureDirectoryExists($carpeta, 0700);
        $origen = $archivo->readStream();
        $destino = @fopen($carpeta.'/'.$nombre, 'wb');
        $copiado = is_resource($origen) && is_resource($destino) && stream_copy_to_stream($origen, $destino) !== false;
        if (is_resource($origen)) {
            fclose($origen);
        }
        if (is_resource($destino)) {
            fclose($destino);
        }
        $archivo->delete();
        $this->form->fill();
        if (! $copiado) {
            Notification::make()->danger()->title(__('actualizaciones.error.no_escribe', ['ruta' => $nombre]))->send();

            return;
        }

        $this->paquete = $nombre;
        $this->revision = null;
        Notification::make()->success()->title(__('actualizaciones.subido', ['paquete' => $nombre]))->send();
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
                $this->redirect(URL::temporarySignedRoute('actualizaciones.aplicar', now()->addMinutes(30), ['actualizacion' => $actualizacion->id]));
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

    /**
     * @return array{subida: string, envio: string}
     */
    public function limites(): array
    {
        return ['subida' => (string) ini_get('upload_max_filesize'), 'envio' => (string) ini_get('post_max_size')];
    }


    private function actualizador(): Actualizador
    {
        return app(Actualizador::class);
    }
}
