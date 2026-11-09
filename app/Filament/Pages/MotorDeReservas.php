<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Demo\ModoDemo;
use App\Models\ConceptoCargo;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\AjustesMotor;
use App\Motor\Pasarelas;
use App\Seguridad\Acceso;
use BackedEnum;
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

/**
 * Ajustes del motor de reservas de cada propiedad independiente: si está abierto, el anticipo y
 * las llaves de las pasarelas (D-042). Las llaves se escriben, pero nunca se vuelven a mostrar.
 *
 * @property-read Schema $form
 */
class MotorDeReservas extends Page
{
    protected string $view = 'filament.pages.motor-de-reservas';

    protected static ?string $slug = 'motor-de-reservas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = 5;

    /** @var array<string, mixed>|null */
    public ?array $datos = [];

    public ?int $propiedadId = null;

    /**
     * @return list<int>
     */
    public static function propiedadesGestionables(): array
    {
        return Modulos::activo(Modulo::Motor) ? Acceso::propiedadesIndependientes(Acceso::usuarioActual(), Acceso::CATALOGO) : [];
    }

    public static function canAccess(): bool
    {
        return self::propiedadesGestionables() !== [];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.configuracion');
    }

    public static function getNavigationLabel(): string
    {
        return __('motor.panel.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('motor.panel.titulo');
    }

    public function mount(): void
    {
        $this->propiedadId = self::propiedadesGestionables()[0] ?? null;
        $this->cargar();
    }

    public function updatedPropiedadId(): void
    {
        $this->cargar();
    }

    public function propiedad(): ?Propiedad
    {
        return in_array($this->propiedadId, self::propiedadesGestionables(), true) ? Propiedad::query()->find($this->propiedadId) : null;
    }

    /**
     * @return array<int, string>
     */
    public function opcionesPropiedad(): array
    {
        return Propiedad::query()->whereIn('id', self::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    public function enlace(): ?string
    {
        $propiedad = $this->propiedad();

        return $propiedad === null ? null : route('motor.buscar', ['codigo' => $propiedad->codigo]);
    }

    public function urlAviso(string $pasarela): ?string
    {
        $propiedad = $this->propiedad();

        return $propiedad === null ? null : route('motor.aviso', ['codigo' => $propiedad->codigo, 'pasarela' => $pasarela]);
    }

    public function form(Schema $schema): Schema
    {
        $ayudaLlave = fn (string $llave): string => $this->propiedad() !== null && (new AjustesMotor($this->propiedad()))->tieneLlave($llave)
            ? __('motor.panel.llave_guardada')
            : '';

        return $schema
            ->statePath('datos')
            ->components([
                Section::make()->columns(['default' => 1, 'md' => 2])->schema([
                    Toggle::make('activo')->label(__('motor.panel.activo'))->columnSpanFull(),
                    Toggle::make('calendario_publico')->label(__('motor.panel.calendario'))->helperText(__('motor.panel.calendario_ayuda'))->columnSpanFull(),
                    TextInput::make('anticipo_porcentaje')->label(__('motor.panel.anticipo'))->helperText(__('motor.panel.anticipo_ayuda'))->numeric()->minValue(0)->maxValue(100)->required(),
                    TextInput::make('unidades_maximas')->label(__('motor.panel.unidades_maximas'))->helperText(__('motor.panel.unidades_maximas_ayuda'))->numeric()->integer()->minValue(1)->maxValue(10)->required(),
                    TextInput::make('minutos_retencion')->label(__('motor.panel.retencion'))->helperText(__('motor.panel.retencion_ayuda'))->numeric()->minValue(5)->maxValue(10080)->required(),
                    Toggle::make('confirmacion_inmediata')->label(__('motor.panel.confirmacion_inmediata'))->helperText(__('motor.panel.confirmacion_inmediata_ayuda')),
                    TextInput::make('horas_revision')->label(__('motor.panel.horas_revision'))->helperText(__('motor.panel.horas_revision_ayuda'))->numeric()->minValue(0)->maxValue(720)->required(),
                    TextInput::make('dias_antelacion')->label(__('motor.panel.antelacion'))->numeric()->minValue(0)->maxValue(365)->required(),
                    Textarea::make('instrucciones')->label(__('motor.panel.instrucciones'))->helperText(__('motor.panel.instrucciones_ayuda'))->rows(3)->maxLength(3000)->columnSpanFull(),
                    Toggle::make('acepta_comprobantes')->label(__('grupos.comprobante.acepta'))->helperText(__('grupos.comprobante.acepta_ayuda'))->columnSpanFull(),
                    Textarea::make('condiciones')->label(__('motor.panel.condiciones'))->rows(3)->maxLength(5000)->columnSpanFull(),
                ]),
                Section::make(__('camping.panel.seccion'))->description(__('camping.panel.seccion_ayuda'))->columns(['default' => 1, 'md' => 2])->visible(fn (): bool => Modulos::activo(Modulo::Camping))->schema([
                    Toggle::make('pide_equipo')->label(__('camping.panel.pide_equipo'))->helperText(__('camping.panel.pide_equipo_ayuda'))->columnSpanFull(),
                    Toggle::make('elegir_lugar')->label(__('camping.panel.elegir_lugar'))->helperText(__('camping.panel.elegir_lugar_ayuda'))->disabled(fn (): bool => ! Modulos::activo(Modulo::Plano))->columnSpanFull(),
                    Select::make('cargo_lugar_id')->label(__('camping.panel.cargo_lugar'))->helperText(__('camping.panel.cargo_lugar_ayuda'))->native(false)
                        ->options(fn (): array => ConceptoCargo::query()->where('propiedad_id', $this->propiedad()?->id)->where('obligatorio', false)->orderBy('nombre')->pluck('nombre', 'id')->all()),
                ]),
                Section::make(__('motor.panel.stripe'))->description(__('motor.panel.stripe_ayuda'))->hidden(fn (): bool => ModoDemo::activo() || ! Modulos::activo(Modulo::Pagos))->collapsible()->columns(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('stripe_secreta')->label(__('motor.panel.stripe_secreta'))->password()->revealable()->autocomplete('new-password')->maxLength(300)
                        ->helperText(fn (): string => $ayudaLlave('stripe_secreta')),
                    TextInput::make('stripe_webhook')->label(__('motor.panel.stripe_webhook'))->password()->revealable()->autocomplete('new-password')->maxLength(300)
                        ->helperText(fn (): string => $ayudaLlave('stripe_webhook')),
                    Toggle::make('stripe_quitar')->label(__('motor.panel.quitar_llaves')),
                    Section::make(__('motor.panel.stripe_mexico'))->description(__('motor.panel.stripe_mexico_ayuda'))->visible(fn (): bool => $this->propiedad()?->moneda === 'MXN')->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                        Toggle::make('stripe_oxxo')->label(__('motor.panel.stripe_oxxo'))->helperText(__('motor.panel.stripe_oxxo_ayuda'))->live(),
                        TextInput::make('stripe_oxxo_dias')->label(__('motor.panel.stripe_oxxo_dias'))->numeric()->integer()->minValue(1)->maxValue(7)->visible(fn (Get $get): bool => (bool) $get('stripe_oxxo')),
                        Toggle::make('stripe_meses')->label(__('motor.panel.stripe_meses'))->helperText(__('motor.panel.stripe_meses_ayuda')),
                        Toggle::make('stripe_spei')->label(__('motor.panel.stripe_spei'))->helperText(__('motor.panel.stripe_spei_ayuda')),
                    ]),
                ]),
                Section::make(__('motor.panel.redsys'))->description(__('motor.panel.redsys_ayuda'))->hidden(fn (): bool => ModoDemo::activo() || ! Modulos::activo(Modulo::Pagos) || $this->propiedad()?->moneda !== 'EUR')->collapsible()->columns(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('redsys_comercio')->label(__('motor.panel.redsys_comercio'))->helperText(__('motor.panel.redsys_comercio_ayuda'))->regex('/^[0-9]{1,9}$/')->maxLength(9),
                    TextInput::make('redsys_terminal')->label(__('motor.panel.redsys_terminal'))->regex('/^[0-9]{1,3}$/')->maxLength(3),
                    TextInput::make('redsys_clave')->label(__('motor.panel.redsys_clave'))->password()->revealable()->autocomplete('new-password')->maxLength(100)->regex('/^[A-Za-z0-9+\/]{32}$/')
                        ->helperText(fn (): string => $ayudaLlave('redsys_clave')),
                    Toggle::make('redsys_pruebas')->label(__('motor.panel.redsys_pruebas'))->helperText(__('motor.panel.redsys_pruebas_ayuda')),
                    Toggle::make('redsys_bizum')->label(__('motor.panel.redsys_bizum'))->helperText(__('motor.panel.redsys_bizum_ayuda')),
                    Toggle::make('redsys_quitar')->label(__('motor.panel.quitar_llaves')),
                ]),
                Section::make(__('motor.panel.mercadopago'))->description(__('motor.panel.mercadopago_ayuda'))->hidden(fn (): bool => ModoDemo::activo() || ! Modulos::activo(Modulo::Pagos))->collapsible()->columns(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('mercadopago_token')->label(__('motor.panel.mercadopago_token'))->password()->revealable()->autocomplete('new-password')->maxLength(300)
                        ->helperText(fn (): string => $ayudaLlave('mercadopago_token')),
                    TextInput::make('mercadopago_webhook')->label(__('motor.panel.mercadopago_webhook'))->password()->revealable()->autocomplete('new-password')->maxLength(300)
                        ->helperText(fn (): string => $ayudaLlave('mercadopago_webhook')),
                    Toggle::make('mercadopago_quitar')->label(__('motor.panel.quitar_llaves')),
                ]),
                // PayPal (D-159): la aplicación REST de su panel de desarrolladores.
                Section::make(__('motor.panel.paypal'))->description(__('motor.panel.paypal_ayuda'))->hidden(fn (): bool => ModoDemo::activo() || ! Modulos::activo(Modulo::Pagos))->collapsible()->columns(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('paypal_cliente')->label(__('motor.panel.paypal_cliente'))->maxLength(200)->regex('/^[A-Za-z0-9_\-]+$/'),
                    TextInput::make('paypal_secreto')->label(__('motor.panel.paypal_secreto'))->password()->revealable()->autocomplete('new-password')->maxLength(200)
                        ->helperText(fn (): string => $ayudaLlave('paypal_secreto')),
                    TextInput::make('paypal_webhook')->label(__('motor.panel.paypal_webhook'))->helperText(__('motor.panel.paypal_webhook_ayuda'))->maxLength(100)->regex('/^[A-Za-z0-9]+$/'),
                    Toggle::make('paypal_pruebas')->label(__('motor.panel.paypal_pruebas'))->helperText(__('motor.panel.paypal_pruebas_ayuda')),
                    Toggle::make('paypal_quitar')->label(__('motor.panel.quitar_llaves')),
                ]),
            ]);
    }

    public function guardar(): void
    {
        $propiedad = $this->propiedad();
        abort_if($propiedad === null, 403);
        $datos = $this->form->getState();
        $ajustes = new AjustesMotor($propiedad);
        $ajustes->guardar([
            'activo' => (bool) ($datos['activo'] ?? false),
            'calendario_publico' => (bool) ($datos['calendario_publico'] ?? false),
            'anticipo_porcentaje' => (int) $datos['anticipo_porcentaje'],
            'minutos_retencion' => (int) $datos['minutos_retencion'],
            'unidades_maximas' => (int) $datos['unidades_maximas'],
            'confirmacion_inmediata' => (bool) ($datos['confirmacion_inmediata'] ?? false),
            'horas_revision' => (int) $datos['horas_revision'],
            'dias_antelacion' => (int) $datos['dias_antelacion'],
            'instrucciones' => $datos['instrucciones'] ?? null,
            'acepta_comprobantes' => (bool) ($datos['acepta_comprobantes'] ?? false),
            'pide_equipo' => (bool) ($datos['pide_equipo'] ?? false),
            'elegir_lugar' => (bool) ($datos['elegir_lugar'] ?? false),
            'cargo_lugar_id' => blank($datos['cargo_lugar_id'] ?? null) ? null : (int) $datos['cargo_lugar_id'],
            'stripe_oxxo' => (bool) ($datos['stripe_oxxo'] ?? false),
            'stripe_oxxo_dias' => (int) ($datos['stripe_oxxo_dias'] ?? 3),
            'stripe_meses' => (bool) ($datos['stripe_meses'] ?? false),
            'stripe_spei' => (bool) ($datos['stripe_spei'] ?? false),
            'redsys_comercio' => trim((string) ($datos['redsys_comercio'] ?? '')),
            'redsys_terminal' => trim((string) ($datos['redsys_terminal'] ?? '')),
            'redsys_pruebas' => (bool) ($datos['redsys_pruebas'] ?? true),
            'redsys_bizum' => (bool) ($datos['redsys_bizum'] ?? false),
            'paypal_cliente' => ! empty($datos['paypal_quitar']) ? '' : trim((string) ($datos['paypal_cliente'] ?? '')),
            'paypal_pruebas' => (bool) ($datos['paypal_pruebas'] ?? true),
            'condiciones' => $datos['condiciones'] ?? null,
        ]);
        foreach (ModoDemo::activo() ? [] : ['stripe' => ['stripe_secreta', 'stripe_webhook'], 'mercadopago' => ['mercadopago_token', 'mercadopago_webhook'], 'redsys' => ['redsys_clave'], 'paypal' => ['paypal_secreto', 'paypal_webhook']] as $pasarela => $llaves) {
            foreach ($llaves as $llave) {
                if (! empty($datos[$pasarela.'_quitar'])) {
                    $ajustes->guardarLlave($llave, null);
                } elseif (filled($datos[$llave] ?? null)) {
                    $ajustes->guardarLlave($llave, (string) $datos[$llave]);
                }
            }
        }
        // Nunca las llaves en la auditoría.
        RegistroAuditoria::anotar('motor.ajustes', $propiedad, ['activo' => (bool) ($datos['activo'] ?? false), 'pasarelas' => array_keys((new Pasarelas($propiedad->refresh()))->disponibles())], $propiedad->id);
        $this->cargar();
        Notification::make()->success()->title(__('motor.panel.guardado'))->send();
    }

    private function cargar(): void
    {
        $propiedad = $this->propiedad();
        if ($propiedad === null) {
            return;
        }
        $motor = (array) (((array) $propiedad->ajustes)['motor'] ?? []);
        $this->form->fill([
            'activo' => (bool) ($motor['activo'] ?? false),
            'anticipo_porcentaje' => $motor['anticipo_porcentaje'] ?? 30,
            'minutos_retencion' => $motor['minutos_retencion'] ?? 30,
            'unidades_maximas' => $motor['unidades_maximas'] ?? 3,
            'confirmacion_inmediata' => (bool) ($motor['confirmacion_inmediata'] ?? false),
            'horas_revision' => $motor['horas_revision'] ?? 48,
            'dias_antelacion' => $motor['dias_antelacion'] ?? 0,
            'instrucciones' => $motor['instrucciones'] ?? null,
            'acepta_comprobantes' => (bool) ($motor['acepta_comprobantes'] ?? true),
            'pide_equipo' => (bool) ($motor['pide_equipo'] ?? false),
            'elegir_lugar' => (bool) ($motor['elegir_lugar'] ?? false),
            'cargo_lugar_id' => $motor['cargo_lugar_id'] ?? null,
            'stripe_oxxo' => (bool) ($motor['stripe_oxxo'] ?? false),
            'stripe_oxxo_dias' => (int) ($motor['stripe_oxxo_dias'] ?? 3),
            'stripe_meses' => (bool) ($motor['stripe_meses'] ?? false),
            'stripe_spei' => (bool) ($motor['stripe_spei'] ?? false),
            'redsys_comercio' => $motor['redsys_comercio'] ?? null,
            'redsys_terminal' => $motor['redsys_terminal'] ?? '1',
            'redsys_pruebas' => (bool) ($motor['redsys_pruebas'] ?? true),
            'redsys_bizum' => (bool) ($motor['redsys_bizum'] ?? false),
            'paypal_cliente' => $motor['paypal_cliente'] ?? null,
            'paypal_pruebas' => (bool) ($motor['paypal_pruebas'] ?? true),
            'condiciones' => $motor['condiciones'] ?? null,
        ]);
    }
}
