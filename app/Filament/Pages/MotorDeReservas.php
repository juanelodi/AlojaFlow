<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Demo\ModoDemo;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\AjustesMotor;
use App\Motor\Pasarelas;
use App\Seguridad\Acceso;
use BackedEnum;
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
                    TextInput::make('anticipo_porcentaje')->label(__('motor.panel.anticipo'))->helperText(__('motor.panel.anticipo_ayuda'))->numeric()->minValue(0)->maxValue(100)->required(),
                    TextInput::make('minutos_retencion')->label(__('motor.panel.retencion'))->helperText(__('motor.panel.retencion_ayuda'))->numeric()->minValue(5)->maxValue(10080)->required(),
                    Toggle::make('confirmacion_inmediata')->label(__('motor.panel.confirmacion_inmediata'))->helperText(__('motor.panel.confirmacion_inmediata_ayuda')),
                    TextInput::make('horas_revision')->label(__('motor.panel.horas_revision'))->helperText(__('motor.panel.horas_revision_ayuda'))->numeric()->minValue(0)->maxValue(720)->required(),
                    TextInput::make('dias_antelacion')->label(__('motor.panel.antelacion'))->numeric()->minValue(0)->maxValue(365)->required(),
                    Textarea::make('instrucciones')->label(__('motor.panel.instrucciones'))->helperText(__('motor.panel.instrucciones_ayuda'))->rows(3)->maxLength(3000)->columnSpanFull(),
                    Toggle::make('acepta_comprobantes')->label(__('grupos.comprobante.acepta'))->helperText(__('grupos.comprobante.acepta_ayuda'))->columnSpanFull(),
                    Textarea::make('condiciones')->label(__('motor.panel.condiciones'))->rows(3)->maxLength(5000)->columnSpanFull(),
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
                Section::make(__('motor.panel.mercadopago'))->description(__('motor.panel.mercadopago_ayuda'))->hidden(fn (): bool => ModoDemo::activo() || ! Modulos::activo(Modulo::Pagos))->collapsible()->columns(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('mercadopago_token')->label(__('motor.panel.mercadopago_token'))->password()->revealable()->autocomplete('new-password')->maxLength(300)
                        ->helperText(fn (): string => $ayudaLlave('mercadopago_token')),
                    TextInput::make('mercadopago_webhook')->label(__('motor.panel.mercadopago_webhook'))->password()->revealable()->autocomplete('new-password')->maxLength(300)
                        ->helperText(fn (): string => $ayudaLlave('mercadopago_webhook')),
                    Toggle::make('mercadopago_quitar')->label(__('motor.panel.quitar_llaves')),
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
            'anticipo_porcentaje' => (int) $datos['anticipo_porcentaje'],
            'minutos_retencion' => (int) $datos['minutos_retencion'],
            'confirmacion_inmediata' => (bool) ($datos['confirmacion_inmediata'] ?? false),
            'horas_revision' => (int) $datos['horas_revision'],
            'dias_antelacion' => (int) $datos['dias_antelacion'],
            'instrucciones' => $datos['instrucciones'] ?? null,
            'acepta_comprobantes' => (bool) ($datos['acepta_comprobantes'] ?? false),
            'stripe_oxxo' => (bool) ($datos['stripe_oxxo'] ?? false),
            'stripe_oxxo_dias' => (int) ($datos['stripe_oxxo_dias'] ?? 3),
            'stripe_meses' => (bool) ($datos['stripe_meses'] ?? false),
            'stripe_spei' => (bool) ($datos['stripe_spei'] ?? false),
            'condiciones' => $datos['condiciones'] ?? null,
        ]);
        foreach (ModoDemo::activo() ? [] : ['stripe' => ['stripe_secreta', 'stripe_webhook'], 'mercadopago' => ['mercadopago_token', 'mercadopago_webhook']] as $pasarela => $llaves) {
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
            'confirmacion_inmediata' => (bool) ($motor['confirmacion_inmediata'] ?? false),
            'horas_revision' => $motor['horas_revision'] ?? 48,
            'dias_antelacion' => $motor['dias_antelacion'] ?? 0,
            'instrucciones' => $motor['instrucciones'] ?? null,
            'acepta_comprobantes' => (bool) ($motor['acepta_comprobantes'] ?? true),
            'stripe_oxxo' => (bool) ($motor['stripe_oxxo'] ?? false),
            'stripe_oxxo_dias' => (int) ($motor['stripe_oxxo_dias'] ?? 3),
            'stripe_meses' => (bool) ($motor['stripe_meses'] ?? false),
            'stripe_spei' => (bool) ($motor['stripe_spei'] ?? false),
            'condiciones' => $motor['condiciones'] ?? null,
        ]);
    }
}
