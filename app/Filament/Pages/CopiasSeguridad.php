<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Copias\ErrorCopia;
use App\Copias\GestorCopias;
use App\Demo\ModoDemo;
use App\Models\RegistroAuditoria;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Crear, descargar y restaurar copias de seguridad completas (D-038). Solo el administrador; lo
 * delicado se confirma con la contraseña.
 */
class CopiasSeguridad extends Page
{
    protected string $view = 'filament.pages.copias-seguridad';

    protected static ?string $slug = 'copias-de-seguridad';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?int $navigationSort = 70;

    public static function canAccess(): bool
    {
        return (bool) Acceso::usuarioActual()?->es_administrador && ! ModoDemo::activo();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.sistema');
    }

    public static function getNavigationLabel(): string
    {
        return __('copias.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('copias.titulo');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function copias(): array
    {
        return $this->gestor()->listar();
    }

    public function automaticas(): bool
    {
        return (bool) Ajustes::obtener('copias.automaticas', true);
    }

    public function conservar(): int
    {
        return (int) Ajustes::obtener('copias.conservar', GestorCopias::CONSERVAR_POR_OMISION);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('crear')
                ->label(__('copias.crear'))
                ->icon(Heroicon::OutlinedPlus)
                ->action(function (): void {
                    try {
                        $copia = $this->gestor()->crear();
                        Notification::make()->success()->title(__('copias.creada', ['nombre' => $copia['nombre']]))->send();
                    } catch (ErrorCopia $e) {
                        Notification::make()->danger()->title($e->getMessage())->persistent()->send();
                    }
                }),
            Action::make('subir')
                ->label(__('copias.subir'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalDescription(__('copias.subir_ayuda'))
                ->schema([
                    FileUpload::make('archivo')->label(__('copias.archivo'))->required()->storeFiles(false)
                        ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'application/octet-stream'])
                        ->maxSize(1024 * 1024),
                ])
                ->action(function (array $data): void {
                    $archivo = $data['archivo'] ?? null;
                    if (! $archivo instanceof TemporaryUploadedFile) {
                        return;
                    }
                    try {
                        $nombre = $this->gestor()->importar($archivo->getRealPath(), $archivo->getClientOriginalName());
                        Notification::make()->success()->title(__('copias.subida', ['nombre' => $nombre]))->send();
                    } catch (ErrorCopia $e) {
                        Notification::make()->danger()->title($e->getMessage())->persistent()->send();
                    }
                    $archivo->delete();
                }),
            Action::make('ajustes')
                ->label(__('copias.ajustes'))
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->fillForm(fn (): array => ['automaticas' => $this->automaticas(), 'conservar' => $this->conservar()])
                ->schema([
                    Toggle::make('automaticas')->label(__('copias.automaticas'))->helperText(__('copias.automaticas_ayuda')),
                    TextInput::make('conservar')->label(__('copias.conservar'))->helperText(__('copias.conservar_ayuda'))->numeric()->required()->minValue(1)->maxValue(365),
                ])
                ->action(function (array $data): void {
                    Ajustes::guardar('copias.automaticas', (bool) $data['automaticas']);
                    Ajustes::guardar('copias.conservar', (int) $data['conservar']);
                    RegistroAuditoria::anotar('copia.ajustes', null, ['automaticas' => (bool) $data['automaticas'], 'conservar' => (int) $data['conservar']]);
                    Notification::make()->success()->title(__('copias.ajustes_guardados'))->send();
                }),
            Action::make('descargarEnv')
                ->label(__('copias.descargar_env'))
                ->icon(Heroicon::OutlinedKey)
                ->color('gray')
                ->modalHeading(__('copias.descargar_env'))
                ->modalDescription(__('copias.descargar_env_ayuda'))
                ->schema([
                    TextInput::make('contrasena')->label(__('copias.contrasena'))->password()->required()->currentPassword(),
                ])
                ->action(function (): BinaryFileResponse {
                    RegistroAuditoria::anotar('copia.descargar_env');

                    return response()->download(base_path('.env'), 'alojaflow.env', ['Content-Type' => 'text/plain']);
                }),
        ];
    }

    public function descargarAction(): Action
    {
        return Action::make('descargar')
            ->label(__('copias.descargar'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->size('sm')
            // Lleva todos los datos: una sesión olvidada abierta no debe bastar para llevársela.
            ->schema([
                TextInput::make('contrasena')->label(__('copias.contrasena'))->password()->required()->currentPassword(),
            ])
            ->action(function (array $arguments): BinaryFileResponse {
                $nombre = (string) ($arguments['nombre'] ?? '');
                $ruta = $this->gestor()->ruta($nombre);
                RegistroAuditoria::anotar('copia.descargar', null, ['copia' => $nombre]);

                return response()->download($ruta, $nombre, ['Content-Type' => 'application/zip']);
            });
    }

    public function restaurarAction(): Action
    {
        return Action::make('restaurar')
            ->label(__('copias.restaurar'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(__('copias.restaurar_titulo'))
            ->modalDescription(__('copias.restaurar_texto'))
            ->schema([
                TextInput::make('contrasena')->label(__('copias.contrasena'))->password()->required()->currentPassword(),
            ])
            ->action(function (array $arguments): void {
                try {
                    $this->gestor()->restaurar((string) ($arguments['nombre'] ?? ''));
                } catch (ErrorCopia $e) {
                    Notification::make()->danger()->title($e->getMessage())->persistent()->send();

                    return;
                }
                // La copia trae sus propias sesiones vacías: hay que volver a entrar.
                session()->flush();
                $this->redirect(filament()->getLoginUrl() ?? '/');
            });
    }

    public function eliminarAction(): Action
    {
        return Action::make('eliminar')
            ->label(__('copias.eliminar'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(__('copias.eliminar_titulo'))
            ->action(function (array $arguments): void {
                try {
                    $this->gestor()->eliminar((string) ($arguments['nombre'] ?? ''));
                    Notification::make()->success()->title(__('copias.eliminada'))->send();
                } catch (ErrorCopia $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }

    private function gestor(): GestorCopias
    {
        return app(GestorCopias::class);
    }
}
