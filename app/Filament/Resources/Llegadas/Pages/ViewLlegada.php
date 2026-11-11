<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Llegadas\Pages;

use App\Documentos\GeneradorPdf;
use App\Enums\EstadoRegistroLlegada;
use App\Filament\Resources\Llegadas\RegistroLlegadaResource;
use App\Llegada\GestorLlegadas;
use App\Models\RegistroLlegada;
use App\Paises\Paises;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lo que mandó el huésped.
 */
class ViewLlegada extends Page
{
    use InteractsWithRecord;

    protected static string $resource = RegistroLlegadaResource::class;

    protected string $view = 'filament.pages.llegada';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(RegistroLlegadaResource::canView($this->record), 403);
    }

    public function registro(): RegistroLlegada
    {
        /** @var RegistroLlegada $registro */
        $registro = $this->getRecord();

        return $registro->loadMissing(['personas', 'reserva.unidad', 'reserva.titular', 'propiedad', 'archivos', 'aceptaciones.documento']);
    }

    public function getTitle(): string|Htmlable
    {
        return __('llegada.titulo').' · '.$this->registro()->reserva->numero;
    }

    /**
     * @return array<string, string>
     */
    public function tiposDocumento(): array
    {
        return Paises::de($this->registro()->propiedad)->tiposDocumento();
    }

    /**
     * @return array<string, array{etiqueta: string, requerido: bool, solo_titular: bool}>
     */
    public function camposPais(): array
    {
        return Paises::de($this->registro()->propiedad)->camposLlegada();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revisar')
                ->label(__('llegada.panel.revisar'))
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (): bool => $this->registro()->estado === EstadoRegistroLlegada::Recibido)
                ->action(function (GestorLlegadas $llegadas): void {
                    $llegadas->marcarRevisado($this->registro());
                    Notification::make()->success()->title(__('llegada.panel.revisado_ok'))->send();
                }),
            Action::make('aFichas')
                ->label(__('llegada.panel.a_fichas'))
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('llegada.panel.a_fichas_ayuda'))
                ->visible(fn (): bool => $this->registro()->propiedad->esIndependiente())
                ->action(function (GestorLlegadas $llegadas): void {
                    Notification::make()->success()->title(__('llegada.panel.a_fichas_ok', ['n' => $llegadas->pasarAFichas($this->registro())]))->send();
                }),
            Action::make('pdf')
                ->label(__('llegada.panel.pdf'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->action(fn (): StreamedResponse => response()->streamDownload(function (): void {
                    echo app(GeneradorPdf::class)->registroLlegada($this->registro());
                }, 'registro-'.$this->registro()->reserva->numero.'.pdf', ['Content-Type' => 'application/pdf'])),
        ];
    }
}
