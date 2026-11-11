<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Demo\ModoDemo;
use App\MejoraContinua\ArmadorLatido;
use App\MejoraContinua\ClienteReceptor;
use App\MejoraContinua\EnvioMejora;
use App\MejoraContinua\ErrorReceptor;
use App\MejoraContinua\Permiso;
use App\Models\RegistroAuditoria;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sistema → Mejora continua (D-150): si esta instalación manda datos de diagnóstico y uso, si se
 * identifica, qué se manda exactamente y «olvidar mi instalación». Solo el administrador.
 *
 * @property-read Schema $form
 */
class MejoraContinua extends Page
{
    protected string $view = 'filament.pages.mejora-continua';

    protected static ?string $slug = 'mejora-continua';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 85;

    /** @var array<string, mixed>|null */
    public ?array $datos = [];

    public static function canAccess(): bool
    {
        return (bool) Acceso::usuarioActual()?->es_administrador && ! ModoDemo::activo();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('mejora.nav');
    }

    public static function getNavigationLabel(): string
    {
        return __('mejora.nav_ajustes');
    }

    public function getTitle(): string|Htmlable
    {
        return __('mejora.titulo');
    }

    public function mount(): void
    {
        $identificacion = Permiso::identificacion();
        $this->form->fill([
            // Sin decidir, el interruptor se ofrece prendido, pero no cuenta hasta que se guarda.
            'permiso' => Permiso::decidido() ? Permiso::concedido() : true,
            'identificar' => $identificacion !== null,
            'nombre' => $identificacion['nombre'] ?? '',
            'dominio' => $identificacion['dominio'] ?? (string) parse_url((string) config('app.url'), PHP_URL_HOST),
            'correo' => $identificacion['correo'] ?? (string) Acceso::usuarioActual()?->email,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('datos')->components([
            Section::make()->visible(fn (): bool => ! Permiso::porContrato())->schema([
                Toggle::make('permiso')->label(__('mejora.permiso'))
                    ->helperText(fn (): string => __('mejora.permiso_ayuda', ['receptor' => (string) parse_url((string) config('alojaflow.mejora_continua.url'), PHP_URL_HOST)]))
                    ->live(),
                Toggle::make('identificar')->label(__('mejora.identificar'))->helperText(__('mejora.identificar_ayuda'))
                    ->visible(fn (Get $get): bool => (bool) $get('permiso'))->live(),
                TextInput::make('nombre')->label(__('mejora.nombre'))->maxLength(150)
                    ->visible(fn (Get $get): bool => (bool) $get('permiso') && (bool) $get('identificar'))->required(fn (Get $get): bool => (bool) $get('identificar')),
                TextInput::make('dominio')->label(__('mejora.dominio'))->maxLength(190)
                    ->visible(fn (Get $get): bool => (bool) $get('permiso') && (bool) $get('identificar')),
                TextInput::make('correo')->label(__('mejora.correo'))->email()->maxLength(190)
                    ->visible(fn (Get $get): bool => (bool) $get('permiso') && (bool) $get('identificar')),
            ]),
        ]);
    }

    public function guardar(): void
    {
        abort_if(Permiso::porContrato(), 403);
        $datos = $this->form->getState();
        $permiso = (bool) ($datos['permiso'] ?? false);
        Permiso::guardar($permiso);
        Permiso::guardarIdentificacion($permiso && (bool) ($datos['identificar'] ?? false) ? [
            'nombre' => trim((string) ($datos['nombre'] ?? '')),
            'dominio' => strtolower(trim((string) ($datos['dominio'] ?? ''))),
            'correo' => trim((string) ($datos['correo'] ?? '')),
        ] : null);
        if (! $permiso) {
            // Lo que se había juntado y no se mandó, ya no se manda.
            DB::table('mejora_errores')->delete();
            DB::table('mejora_pantallas')->delete();
        }
        RegistroAuditoria::anotar('mejora.permiso', null, ['permiso' => $permiso, 'identificada' => Permiso::identificacion() !== null]);
        Notification::make()->success()->title(__('mejora.guardado'))->send();
    }

    /**
     * @return array{decidido: bool, activa: bool, receptor: bool, ultimo: ?string, fallo: ?string, uuid: ?string}
     */
    public function estado(): array
    {
        $ultimo = (array) Ajustes::obtener(EnvioMejora::AJUSTE_ULTIMO_LATIDO, []);
        $bueno = is_string($ultimo['bueno_en'] ?? null) ? Formato::momento(Carbon::parse($ultimo['bueno_en'])) : null;
        $fallo = ($ultimo['ok'] ?? true) === false ? (string) ($ultimo['motivo'] ?? '') : null;
        $uuid = app(ClienteReceptor::class)->uuid();

        return [
            'decidido' => Permiso::decidido(),
            'activa' => Permiso::activa(),
            'receptor' => Permiso::hayReceptor(),
            'ultimo' => $bueno,
            'fallo' => $fallo,
            'uuid' => $uuid === null ? null : substr($uuid, 0, 8).'…',
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('reportar')
                ->label(__('mejora.reportar.nav'))
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->url(fn (): string => ReportarSugerir::getUrl())
                ->visible(fn (): bool => ReportarSugerir::canAccess()),
            Action::make('ver')
                ->label(__('mejora.ver'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->modalHeading(__('mejora.ver'))
                ->modalDescription(__('mejora.ver_ayuda'))
                ->modalContent(fn (): View => view('filament.partes.mejora-json', [
                    'json' => (string) json_encode(app(ArmadorLatido::class)->armar(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('ui.comun.cerrar')),
            Action::make('enviar')
                ->label(__('mejora.enviar_ahora'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->visible(fn (): bool => Permiso::activa())
                ->action(function (EnvioMejora $envio): void {
                    if ($envio->enviarLatido()) {
                        Notification::make()->success()->title(__('mejora.enviado'))->send();

                        return;
                    }
                    $ultimo = (array) Ajustes::obtener(EnvioMejora::AJUSTE_ULTIMO_LATIDO, []);
                    Notification::make()->warning()->title(__('mejora.no_enviado', ['motivo' => (string) ($ultimo['motivo'] ?? '—')]))->send();
                }),
            Action::make('olvidar')
                ->label(__('mejora.olvidar'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->outlined()
                ->requiresConfirmation()
                ->modalDescription(__('mejora.olvidar_ayuda'))
                ->visible(fn (): bool => app(ClienteReceptor::class)->registrado() && ! Permiso::porContrato())
                ->action(function (ClienteReceptor $cliente): void {
                    try {
                        $cliente->olvidar();
                    } catch (ErrorReceptor $error) {
                        Notification::make()->danger()->title(__('mejora.no_enviado', ['motivo' => $error->motivo]))->send();

                        return;
                    }
                    Permiso::guardar(false);
                    Permiso::guardarIdentificacion(null);
                    DB::table('mejora_errores')->delete();
                    DB::table('mejora_pantallas')->delete();
                    Ajustes::borrar(EnvioMejora::AJUSTE_ULTIMO_LATIDO);
                    Ajustes::borrar(EnvioMejora::AJUSTE_ULTIMO_ENVIADO);
                    RegistroAuditoria::anotar('mejora.olvidar', null);
                    Notification::make()->success()->title(__('mejora.olvidada'))->send();
                    $this->mount();
                }),
        ];
    }
}
