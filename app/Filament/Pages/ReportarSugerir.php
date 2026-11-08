<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Demo\ModoDemo;
use App\MejoraContinua\Permiso;
use App\MejoraContinua\ReportesMejora;
use App\Models\RegistroAuditoria;
use App\Models\ReporteMejora;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

use function Illuminate\Support\defer;

use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Url;

/**
 * «Reportar o sugerir» (D-150): un error, una idea o una duda para el equipo de AlojaFlow, con hasta diez
 * imágenes, y la respuesta en «Mis reportes». Cualquiera del equipo del alojamiento puede mandarlo; el
 * administrador ve también los de los demás.
 *
 * @property-read Schema $form
 */
class ReportarSugerir extends Page
{
    protected string $view = 'filament.pages.reportar-sugerir';

    protected static ?string $slug = 'reportar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static ?int $navigationSort = 95;

    /** La pantalla desde la que se abrió (nombre de ruta). */
    #[Url]
    public ?string $desde = null;

    /** @var array<string, mixed>|null */
    public ?array $datos = [];

    public static function canAccess(): bool
    {
        return Acceso::usuarioActual()?->activo === true && Permiso::hayReceptor() && ! ModoDemo::activo();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('mejora.nav');
    }

    public static function getNavigationLabel(): string
    {
        return __('mejora.reportar.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('mejora.reportar.titulo');
    }

    public function mount(): void
    {
        $this->llenar();
    }

    public function form(Schema $schema): Schema
    {
        $maximo = (int) config('alojaflow.mejora_continua.imagenes_por_reporte');

        return $schema->statePath('datos')->components([
            Section::make()->description(__('mejora.reportar.aviso_datos'))->columns(['default' => 1, 'md' => 2])->schema([
                Select::make('tipo')->label(__('mejora.reportar.tipo'))->native(false)->required()->live()
                    ->options(collect(ReporteMejora::TIPOS)->mapWithKeys(fn (string $tipo): array => [$tipo => __('mejora.reportar.tipos.'.$tipo)])->all()),
                TextInput::make('titulo')->label(__('mejora.reportar.titulo_campo'))->required()->minLength(3)->maxLength(150),
                Textarea::make('descripcion')->label(__('mejora.reportar.descripcion'))->required()->minLength(3)->maxLength(10000)->rows(6)->columnSpanFull(),
                Textarea::make('pasos')->label(__('mejora.reportar.pasos'))->helperText(__('mejora.reportar.pasos_ayuda'))->maxLength(5000)->rows(4)->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('tipo') === 'error'),
                FileUpload::make('imagenes')->label(__('mejora.reportar.imagenes'))->helperText(__('mejora.reportar.imagenes_ayuda', ['cuantas' => (string) $maximo]))
                    // Solo JPG o PNG: aquí por su tipo, y al guardar por su contenido (ImagenSegura).
                    ->multiple()->maxFiles($maximo)->image()->acceptedFileTypes(['image/jpeg', 'image/png'])->maxSize((int) config('alojaflow.mejora_continua.kb_por_imagen'))
                    // Se reducen en el navegador antes de subirlas: en el teléfono las fotos pesan mucho.
                    ->imageResizeMode('contain')->imageResizeTargetWidth('2560')->imageResizeTargetHeight('2560')->imageResizeUpscale(false)
                    ->disk('local')->directory('mejora/reportes')->visibility('private')->preventFilePathTampering()
                    ->reorderable()->columnSpanFull(),
            ]),
            Section::make(__('mejora.reportar.contacto'))->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('contacto_nombre')->label(__('mejora.reportar.contacto_nombre'))->maxLength(150),
                TextInput::make('contacto_correo')->label(__('mejora.reportar.contacto_correo'))->email()->maxLength(190),
                Toggle::make('permite_contacto')->label(__('mejora.reportar.permite_contacto'))->columnSpanFull(),
                Toggle::make('adjuntar_diagnostico')->label(__('mejora.reportar.diagnostico'))->helperText(__('mejora.reportar.diagnostico_ayuda'))->columnSpanFull(),
                Hidden::make('resolucion'),
            ]),
        ]);
    }

    public function enviar(ReportesMejora $reportes): void
    {
        $usuario = Acceso::usuarioActual();
        abort_if($usuario === null, 403);
        // Para que nadie llene el receptor por error o a propósito: diez por hora y por persona.
        if (RateLimiter::tooManyAttempts('reportes-mejora:'.$usuario->id, 10)) {
            Notification::make()->danger()->title(__('mejora.reportar.demasiados'))->send();

            return;
        }
        $datos = $this->form->getState();
        RateLimiter::hit('reportes-mejora:'.$usuario->id, 3600);
        $imagenes = array_values(array_filter((array) ($datos['imagenes'] ?? []), 'is_string'));
        $reporte = $reportes->crear($usuario, ['pantalla' => $this->desde] + $datos, $imagenes);
        RegistroAuditoria::anotar('mejora.reporte', null, ['tipo' => $reporte->tipo, 'titulo' => $reporte->titulo]);
        $descartadas = count($imagenes) - count($reporte->imagenes ?? []);
        if ($descartadas > 0) {
            Notification::make()->warning()->persistent()->title(__('mejora.reportar.imagenes_descartadas', ['cuantas' => (string) $descartadas]))->send();
        }

        // Se manda después de responder: con diez imágenes y mala conexión tardaría más de lo que aguanta la petición.
        // Si no sale, el programador lo reintenta cada hora.
        defer(fn () => app(ReportesMejora::class)->enviarPendientes());
        Notification::make()->success()->title(__('mejora.reportar.enviado'))->send();
        $this->llenar();
    }

    /**
     * @return Collection<int, ReporteMejora>
     */
    public function reportes(): Collection
    {
        $usuario = Acceso::usuarioActual();

        return ReporteMejora::query()
            ->when(! (bool) $usuario?->es_administrador, fn ($query) => $query->where('user_id', $usuario?->id))
            ->with('usuario')->latest('id')->limit(100)->get();
    }

    private function llenar(): void
    {
        $usuario = Acceso::usuarioActual();
        $this->form->fill([
            'tipo' => 'sugerencia',
            'contacto_nombre' => $usuario?->name,
            'contacto_correo' => $usuario?->email,
            'permite_contacto' => true,
            'adjuntar_diagnostico' => Permiso::concedido(),
            'imagenes' => [],
        ]);
    }
}
