<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Support;

use App\Models\Ajuste;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use SensitiveParameter;
use Throwable;

/**
 * Lee y guarda los ajustes de la instalación. Se recuerdan durante la petición para no repetir
 * consultas; los secretos (contraseña del correo, llaves de pago) se guardan cifrados.
 */
final class Ajustes
{
    /** @var array<string, mixed>|null */
    private static ?array $memoria = null;

    public static function obtener(string $clave, mixed $porOmision = null): mixed
    {
        $todos = self::todos();

        return array_key_exists($clave, $todos) ? $todos[$clave] : $porOmision;
    }

    public static function guardar(string $clave, mixed $valor): void
    {
        Ajuste::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        self::$memoria = null;
    }

    public static function borrar(string $clave): void
    {
        Ajuste::query()->where('clave', $clave)->delete();
        self::$memoria = null;
    }

    public static function guardarSecreto(string $clave, #[SensitiveParameter] ?string $valor): void
    {
        if ($valor === null || $valor === '') {
            self::borrar($clave);

            return;
        }
        self::guardar($clave, Crypt::encryptString($valor));
    }

    public static function secreto(string $clave): ?string
    {
        $cifrado = self::obtener($clave);
        if (! is_string($cifrado) || $cifrado === '') {
            return null;
        }
        try {
            return Crypt::decryptString($cifrado);
        } catch (Throwable) {
            return null; // Se cambió APP_KEY: hay que volver a capturarlo.
        }
    }

    public static function olvidar(): void
    {
        self::$memoria = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function todos(): array
    {
        if (self::$memoria !== null) {
            return self::$memoria;
        }
        try {
            if (! Schema::hasTable('ajustes')) {
                return [];
            }
            $filas = Ajuste::query()->get(['clave', 'valor']);
        } catch (Throwable) {
            return [];
        }

        return self::$memoria = $filas->mapWithKeys(fn (Ajuste $a): array => [$a->clave => $a->valor])->all();
    }
}
