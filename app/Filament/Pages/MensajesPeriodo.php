<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Comunicacion\Mensajero;
use App\Comunicacion\VariablesMensaje;
use App\Enums\EstadoEnvio;
use App\Enums\LugarOmision;
use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Limpieza\Omisiones;
use App\Models\Plantilla;
use App\Models\Reserva;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Seguridad\Acceso;
use App\Servicios\Operacion;
use App\Servicios\Residentes;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

/**
 * Escribirle a varios a la vez, uno por uno (D-083): se elige un periodo y una plantilla, y por reserva hay WhatsApp, copiar y correo. Nada se
 * manda solo, y por eso aquí no cuenta si el cliente está omitido de los mensajes automáticos.
 */
class MensajesPeriodo extends Page
{
    use ConPropiedadSeleccionada;

    private const FILAS_MAXIMAS = 100;

    protected string $view = 'filament.pages.mensajes-periodo';

    protected static ?string $slug = 'mensajes-por-periodo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 3;

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    #[Url]
    public string $por = 'llegada';

    public ?int $plantillaId = null;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Mensajes;
    }

    protected static function permiso(): string
    {
        return Acceso::MENSAJES;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.comunicacion');
    }

    public static function getNavigationLabel(): string
    {
        return __('operacion.mensajes.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('operacion.mensajes.titulo');
    }

    public function mount(): void
    {
        $hoy = Operacion::hoyDe($this->propiedadUnica())->format('Y-m-d');
        $this->desde = $this->desde !== '' ? $this->desde : $hoy;
        $this->hasta = $this->hasta !== '' ? $this->hasta : $hoy;
    }

    /**
     * Cada plantilla es de una propiedad, así que hace falta elegir una.
     *
     * @return array<int, string>
     */
    public function plantillas(): array
    {
        $propiedad = $this->propiedadUnica();

        return $propiedad === null ? [] : Plantilla::query()->where('propiedad_id', $propiedad->id)->where('activa', true)->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    /**
     * @return list<array{reserva: Reserva, texto: string, asunto: string, correo: string|null, whatsapp: string}>
     */
    public function filas(): array
    {
        $plantilla = $this->plantillaId === null ? null : Plantilla::query()->whereIn('propiedad_id', $this->propiedadesElegidas())->find($this->plantillaId);
        if ($plantilla === null || ! $this->fechasValidas()) {
            return [];
        }
        $columna = $this->por === 'salida' ? 'salida' : 'llegada';
        $reservas = ($this->por === 'residentes' && Modulos::activo(Modulo::Camping)
            ? Residentes::consulta([$plantilla->propiedad_id])
            : Omisiones::excluir(Operacion::reservas([$plantilla->propiedad_id]), 'titular_id', [$plantilla->propiedad_id], LugarOmision::Mensajes)->whereBetween($columna, [$this->desde, $this->hasta])->orderBy($columna)->orderBy('unidad_id'))
            ->limit(self::FILAS_MAXIMAS)->get();

        return $reservas->map(function (Reserva $reserva) use ($plantilla): array {
            $texto = Mensajero::textoDe($plantilla, $reserva, Mensajero::idiomaDe($reserva));

            return [
                'reserva' => $reserva,
                'texto' => $texto['cuerpo'],
                'asunto' => $texto['asunto'],
                'correo' => Mensajero::correoDe($reserva),
                'whatsapp' => VariablesMensaje::enlaceWhatsapp(Mensajero::telefonoDe($reserva), $texto['cuerpo'], $reserva->propiedad->pais),
            ];
        })->values()->all();
    }

    public function anotarWhatsapp(int $reservaId): void
    {
        $reserva = $this->reservaPermitida($reservaId);
        $texto = $this->textoPara($reserva);
        if ($texto !== null) {
            app(Mensajero::class)->anotarWhatsapp($reserva, Mensajero::telefonoDe($reserva), $texto['cuerpo'], Mensajero::idiomaDe($reserva));
        }
    }

    public function mandarCorreo(int $reservaId): void
    {
        $reserva = $this->reservaPermitida($reservaId);
        $destino = Mensajero::correoDe($reserva);
        $texto = $this->textoPara($reserva);
        if ($destino === null || $texto === null) {
            return;
        }
        $envio = app(Mensajero::class)->mandarCorreo($reserva, $destino, $texto['asunto'], $texto['cuerpo'], Mensajero::idiomaDe($reserva));
        match ($envio->estado) {
            EstadoEnvio::Enviado => Notification::make()->success()->title(__('mensajes.mandar.enviado'))->send(),
            EstadoEnvio::Manual => Notification::make()->warning()->persistent()->title(__('mensajes.mandar.manual'))->send(),
            default => Notification::make()->danger()->title(__('mensajes.mandar.fallo'))->send(),
        };
    }


    private function fechasValidas(): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->desde) === 1 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->hasta) === 1 && $this->hasta >= $this->desde;
    }

    private function reservaPermitida(int $reservaId): Reserva
    {
        return Operacion::reservas($this->propiedadesVisibles())->findOrFail($reservaId);
    }

    /**
     * @return array{asunto: string, cuerpo: string}|null
     */
    private function textoPara(Reserva $reserva): ?array
    {
        $plantilla = $this->plantillaId === null ? null : Plantilla::query()->where('propiedad_id', $reserva->propiedad_id)->find($this->plantillaId);

        return $plantilla === null ? null : Mensajero::textoDe($plantilla, $reserva, Mensajero::idiomaDe($reserva));
    }
}
