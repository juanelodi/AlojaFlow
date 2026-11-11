<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Copias\CifradoCopia;
use App\Copias\CopiaExterna;
use App\Copias\ErrorCopia;
use App\Copias\GestorCopias;
use App\Demo\ModoDemo;
use App\Instalacion\Instancia;
use App\Models\RegistroAuditoria;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
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
                    // Solo para una copia cifrada de las que salen del servidor (.afcopia).
                    TextInput::make('clave')->label(__('copias.externa.clave_para_subir'))->password()->revealable()->autocomplete('off'),
                ])
                ->action(function (array $data): void {
                    $archivo = $data['archivo'] ?? null;
                    if (! $archivo instanceof TemporaryUploadedFile) {
                        return;
                    }
                    $ruta = $archivo->getRealPath();
                    $descifrada = null;
                    try {
                        if (CifradoCopia::esCifrada($ruta)) {
                            if (! filled($data['clave'] ?? null)) {
                                throw new ErrorCopia(__('copias.externa.falta_clave'));
                            }
                            $descifrada = $ruta.'.zip';
                            CifradoCopia::descifrar($ruta, $descifrada, (string) $data['clave']);
                            $ruta = $descifrada;
                        }
                        $nombre = $this->gestor()->importar($ruta, $archivo->getClientOriginalName());
                        Notification::make()->success()->title(__('copias.subida', ['nombre' => $nombre]))->send();
                    } catch (ErrorCopia $e) {
                        Notification::make()->danger()->title($e->getMessage())->persistent()->send();
                    }
                    if ($descifrada !== null) {
                        @unlink($descifrada);
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
            Action::make('externa')
                ->label(__('copias.externa.titulo'))
                ->icon(Heroicon::OutlinedCloudArrowUp)
                ->color('gray')
                ->modalHeading(__('copias.externa.titulo'))
                ->modalDescription(__('copias.externa.ayuda'))
                ->fillForm(fn (): array => app(CopiaExterna::class)->configuracion())
                ->schema([
                    Select::make('tipo')->label(__('copias.externa.tipo'))->required()->native(false)->live()
                        ->options(['s3' => __('copias.externa.tipo_s3'), 'webdav' => __('copias.externa.tipo_webdav')]),
                    TextInput::make('endpoint')->label(__('copias.externa.endpoint'))->helperText(__('copias.externa.endpoint_ayuda'))->url()->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('tipo') === 's3')->required(fn (Get $get): bool => $get('tipo') === 's3'),
                    TextInput::make('region')->label(__('copias.externa.region'))->maxLength(50)->visible(fn (Get $get): bool => $get('tipo') === 's3'),
                    TextInput::make('bucket')->label(__('copias.externa.bucket'))->maxLength(100)->alphaDash()
                        ->visible(fn (Get $get): bool => $get('tipo') === 's3')->required(fn (Get $get): bool => $get('tipo') === 's3'),
                    TextInput::make('llave')->label(__('copias.externa.llave'))->maxLength(200)->autocomplete('off')
                        ->visible(fn (Get $get): bool => $get('tipo') === 's3')->required(fn (Get $get): bool => $get('tipo') === 's3'),
                    TextInput::make('url')->label(__('copias.externa.url'))->url()->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('tipo') === 'webdav')->required(fn (Get $get): bool => $get('tipo') === 'webdav'),
                    TextInput::make('usuario')->label(__('copias.externa.usuario'))->maxLength(200)->autocomplete('off')->visible(fn (Get $get): bool => $get('tipo') === 'webdav'),
                    TextInput::make('secreto')->label(__('copias.externa.secreto'))->password()->revealable()->autocomplete('new-password')->maxLength(500)
                        ->helperText(fn (): string => Ajustes::secreto(CopiaExterna::AJUSTE_SECRETO) !== null ? __('copias.externa.guardado_ya') : '')
                        ->required(fn (): bool => Ajustes::secreto(CopiaExterna::AJUSTE_SECRETO) === null),
                    TextInput::make('carpeta')->label(__('copias.externa.carpeta'))->maxLength(100)->regex('/^[A-Za-z0-9._\-\/]*$/'),
                    TextInput::make('clave')->label(__('copias.externa.clave'))->helperText(__('copias.externa.clave_ayuda'))->password()->revealable()->autocomplete('new-password')
                        ->minLength(12)->maxLength(200)->confirmed()
                        ->required(fn (): bool => Ajustes::secreto(CopiaExterna::AJUSTE_CLAVE) === null),
                    TextInput::make('clave_confirmation')->label(__('copias.externa.clave_repite'))->password()->autocomplete('new-password')
                        ->required(fn (Get $get): bool => filled($get('clave'))),
                ])
                ->action(function (array $data, Action $action): void {
                    try {
                        app(CopiaExterna::class)->guardar($data, $data['secreto'] ?? null, $data['clave'] ?? null);
                    } catch (ErrorCopia $e) {
                        Notification::make()->danger()->title($e->getMessage())->persistent()->send();
                        $action->halt();

                        return;
                    }
                    Notification::make()->success()->title(__('copias.externa.guardada'))->send();
                }),
            Action::make('subirExterna')
                ->label(__('copias.externa.subir_ahora'))
                ->icon(Heroicon::OutlinedArrowUpOnSquare)
                ->color('gray')
                ->visible(fn (): bool => app(CopiaExterna::class)->configurada() && $this->copias() !== [])
                ->action(function (): void {
                    $externa = app(CopiaExterna::class);
                    $externa->subir($this->gestor()->ruta($this->copias()[0]['nombre']))
                        ? Notification::make()->success()->title(__('copias.externa.subida'))->send()
                        : Notification::make()->danger()->persistent()->title(__('copias.externa.no_subida', ['motivo' => (string) ($externa->ultima()['error'] ?? '')]))->send();
                }),
            Action::make('quitarExterna')
                ->label(__('copias.externa.quitar'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->outlined()
                ->requiresConfirmation()
                ->modalDescription(__('copias.externa.quitar_ayuda'))
                ->visible(fn (): bool => app(CopiaExterna::class)->configuracion()['tipo'] !== '')
                ->action(fn () => app(CopiaExterna::class)->quitar()),
            Action::make('descargarEnv')
                ->label(__('copias.descargar_env'))
                ->icon(Heroicon::OutlinedKey)
                ->color('gray')
                // En el servicio alojado el .env lo administra AlojaFlow App y guarda los secretos de la plataforma (D-167).
                ->visible(fn (): bool => ! Instancia::activa())
                ->modalHeading(__('copias.descargar_env'))
                ->modalDescription(__('copias.descargar_env_ayuda'))
                ->schema([
                    TextInput::make('contrasena')->label(__('copias.contrasena'))->password()->required()->currentPassword(),
                ])
                ->action(function (): BinaryFileResponse {
                    abort_if(Instancia::activa(), 403);
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
