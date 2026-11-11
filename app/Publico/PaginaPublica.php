<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Publico;

use App\Comunicacion\VariablesMensaje;
use App\Models\Foto;
use App\Models\Propiedad;
use Illuminate\Support\Collection;

/**
 * Lo que el alojamiento le cuenta al mundo: descripción, dónde está, qué ofrece y sus fotos (D-104). Un texto sin traducir sale en el idioma de la propiedad, no vacío.
 */
final class PaginaPublica
{
    /** @var array<string, mixed> */
    private array $datos;

    public function __construct(private readonly Propiedad $propiedad)
    {
        $this->datos = (array) $propiedad->pagina_publica;
    }

    public function descripcion(?string $idioma = null): ?string
    {
        return $this->texto('descripcion', $idioma);
    }

    public function ubicacion(?string $idioma = null): ?string
    {
        return $this->texto('ubicacion', $idioma);
    }

    public function servicios(?string $idioma = null): ?string
    {
        return $this->texto('servicios', $idioma);
    }

    /**
     * Solo https: va tal cual en un enlace.
     */
    public function mapaUrl(): ?string
    {
        $url = $this->datos['mapa_url'] ?? null;

        return is_string($url) && str_starts_with($url, 'https://') ? $url : null;
    }

    public function whatsapp(): ?string
    {
        $numero = $this->datos['whatsapp'] ?? null;

        return is_string($numero) && $numero !== '' ? VariablesMensaje::enlaceWhatsapp($numero, __('portal.whatsapp_hola', ['propiedad' => $this->propiedad->nombre]), $this->propiedad->pais) : null;
    }

    public function mostrarPoliticas(): bool
    {
        return (bool) ($this->datos['mostrar_politicas'] ?? true);
    }

    public function huespedPuedeCancelar(): bool
    {
        return (bool) ($this->datos['huesped_cancela'] ?? false);
    }

    /**
     * Lo que los buscadores entienden de un alojamiento. Con JSON_HEX_TAG, un texto del alojamiento no puede cerrar el script.
     */
    public function datosEstructurados(): string
    {
        return (string) json_encode(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LodgingBusiness',
            'name' => $this->propiedad->nombre,
            'description' => $this->descripcion(),
            'telephone' => $this->propiedad->telefono,
            'email' => $this->propiedad->correo,
            'address' => $this->propiedad->getAttribute('domicilio'),
            'image' => $this->fotos()->map(fn (Foto $foto): string => $foto->url())->all() ?: null,
            'url' => route('motor.buscar', ['codigo' => $this->propiedad->codigo]),
        ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    }

    /**
     * @return Collection<int, Foto>
     */
    public function fotos(?int $tarifaId = null): Collection
    {
        return Foto::query()->where('propiedad_id', $this->propiedad->id)->where('tarifa_id', $tarifaId)->orderBy('orden')->orderBy('id')->get();
    }

    private function texto(string $clave, ?string $idioma): ?string
    {
        return Textos::elegir((array) ($this->datos[$clave] ?? []), $idioma ?? app()->getLocale(), $this->propiedad->idioma);
    }
}
