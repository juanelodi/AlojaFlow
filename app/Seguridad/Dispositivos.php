<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Seguridad;

use App\Models\DispositivoRecordado;
use App\Models\User;
use App\Support\Ajustes;
use Illuminate\Support\Facades\Cookie;
use SensitiveParameter;

/**
 * Mantener la sesión en un dispositivo (D-076), en vez del «recordarme» de Laravel, que es un solo token para todos y no caduca. La lógica
 * viene de la 1.2.3, reescrita: cada uso cambia el validador. Una cookie anterior vale dos minutos, porque al restaurar el navegador llegan
 * varias pestañas a la vez; pasado ese margen, se anula el dispositivo, porque pudo copiarse.
 */
final class Dispositivos
{
    public const COOKIE = 'alojaflow_dispositivo';

    public const AJUSTE_DIAS = 'sesiones.dias_recordar';

    private const DIAS_POR_OMISION = 30;

    private const SEGUNDOS_DE_MARGEN = 120;

    public static function diasRecordar(): int
    {
        $dias = Ajustes::obtener(self::AJUSTE_DIAS);

        return is_numeric($dias) ? max(0, (int) $dias) : self::DIAS_POR_OMISION;
    }

    public function recordar(User $usuario, ?string $agente, ?string $ip): void
    {
        $dias = self::diasRecordar();
        if ($dias === 0) {
            return;
        }
        $selector = bin2hex(random_bytes(9));
        $validador = bin2hex(random_bytes(32));
        DispositivoRecordado::query()->create([
            'user_id' => $usuario->id,
            'selector' => $selector,
            'validador_hash' => hash('sha256', $validador),
            'huella_contrasena' => self::huella($usuario),
            'agente' => $agente === null ? null : mb_substr($agente, 0, 255),
            'ip' => $ip,
            'ultimo_uso_en' => now('UTC'),
            'expira_en' => now('UTC')->addDays($dias),
        ]);
        $this->ponerCookie($selector, $validador, $dias);
    }

    /**
     * El usuario de esa cookie, si sigue valiendo.
     */
    public function usar(#[SensitiveParameter] string $cookie, ?string $agente, ?string $ip): ?User
    {
        [$selector, $validador] = array_pad(explode('.', $cookie, 2), 2, '');
        $dispositivo = $selector === '' ? null : DispositivoRecordado::query()->with('usuario')->where('selector', $selector)->first();
        if ($dispositivo === null || $validador === '') {
            return null;
        }
        $huella = hash('sha256', $validador);
        $usuario = $dispositivo->usuario;

        if ($dispositivo->expira_en->isPast() || ! $usuario->activo || ! hash_equals($dispositivo->huella_contrasena, self::huella($usuario))) {
            $dispositivo->delete();

            return null;
        }
        if ($dispositivo->validador_anterior_hash !== null && hash_equals($dispositivo->validador_anterior_hash, $huella)) {
            if ($dispositivo->rotado_en !== null && $dispositivo->rotado_en->gt(now()->subSeconds(self::SEGUNDOS_DE_MARGEN))) {
                return $usuario;
            }
            $dispositivo->delete();

            return null;
        }
        if (! hash_equals($dispositivo->validador_hash, $huella)) {
            return null;
        }

        $nuevo = bin2hex(random_bytes(32));
        $dias = self::diasRecordar();
        // Con la condición, de dos pestañas a la vez solo una rota: la otra se queda con lo que reciba esa.
        $rotadas = DispositivoRecordado::query()->whereKey($dispositivo->id)->where('validador_hash', $huella)->update([
            'validador_anterior_hash' => $huella,
            'validador_hash' => hash('sha256', $nuevo),
            'rotado_en' => now('UTC'),
            'ultimo_uso_en' => now('UTC'),
            'agente' => $agente === null ? null : mb_substr($agente, 0, 255),
            'ip' => $ip,
            'expira_en' => now('UTC')->addDays(max(1, $dias)),
        ]);
        if ($rotadas > 0) {
            $this->ponerCookie($selector, $nuevo, max(1, $dias));
        }

        return $usuario;
    }

    /**
     * Al salir se olvida este dispositivo: quien cierra sesión no espera seguir dentro.
     */
    public function olvidarCookie(?string $cookie): void
    {
        if ($cookie !== null) {
            DispositivoRecordado::query()->where('selector', explode('.', $cookie, 2)[0])->delete();
        }
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /**
     * Para que una persona reconozca su equipo en la lista. No identifica a nadie: solo navegador y sistema.
     */
    public static function describir(?string $agente): string
    {
        $agente = (string) $agente;
        $navegador = match (true) {
            str_contains($agente, 'Edg/') => 'Edge',
            str_contains($agente, 'OPR/') => 'Opera',
            str_contains($agente, 'Chrome/') => 'Chrome',
            str_contains($agente, 'Firefox/') => 'Firefox',
            str_contains($agente, 'Safari/') => 'Safari',
            default => '',
        };
        $sistema = match (true) {
            str_contains($agente, 'Android') => 'Android',
            str_contains($agente, 'iPhone'), str_contains($agente, 'iPad') => 'iOS',
            str_contains($agente, 'Windows') => 'Windows',
            str_contains($agente, 'Mac OS') => 'macOS',
            str_contains($agente, 'Linux') => 'Linux',
            default => '',
        };

        return trim($navegador.' · '.$sistema, ' ·') ?: '—';
    }

    private function ponerCookie(string $selector, #[SensitiveParameter] string $validador, int $dias): void
    {
        Cookie::queue(Cookie::make(self::COOKIE, $selector.'.'.$validador, $dias * 1440, null, null, null, true, false, 'lax'));
    }

    private static function huella(User $usuario): string
    {
        return hash('sha256', 'recordar|'.$usuario->getAuthPassword());
    }
}
