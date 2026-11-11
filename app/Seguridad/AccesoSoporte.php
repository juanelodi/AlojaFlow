<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Seguridad;

use App\Comunicacion\AjustesCorreo;
use App\Comunicacion\MensajeTexto;
use App\Instalacion\EnlaceEntrada;
use App\Instalacion\Instancia;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Support\Ajustes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * El equipo de AlojaFlow dentro de una instancia del servicio alojado (D-166). Por omisión nadie entra: el cliente
 * lo permite desde su panel durante 24 horas, y entonces AlojaFlow App pide un acceso con alojaflow:acceso-soporte.
 * Quien atiende entra como «Soporte AlojaFlow — su nombre», con un enlace de un solo uso, y todo lo que hace queda
 * en la auditoría a ese nombre. Al vencer, el usuario se desactiva. En una emergencia se entra sin permiso, con el
 * motivo escrito, y el cliente recibe un correo en ese momento.
 */
final class AccesoSoporte
{
    public const AJUSTE_PERMISO = 'soporte.permiso';

    public const HORAS_PERMISO = 24;

    public const MINUTOS_ENLACE = 5;

    public const MINUTOS_MAXIMOS = 1440;

    private const DOMINIO = 'soporte.alojaflow.invalid';

    /**
     * Solo en el servicio alojado: en una instalación propia, el equipo de AlojaFlow no tiene cómo entrar.
     */
    public static function disponible(): bool
    {
        return Instancia::activa();
    }

    public function permitir(User $quien): CarbonImmutable
    {
        $hasta = CarbonImmutable::now('UTC')->addHours(self::HORAS_PERMISO);
        Ajustes::guardar(self::AJUSTE_PERMISO, ['hasta' => $hasta->toIso8601String(), 'por' => $quien->id]);
        RegistroAuditoria::anotar('soporte.permitir', null, ['hasta' => $hasta->toIso8601String()]);

        return $hasta;
    }

    /**
     * Retirar el permiso saca también a quien esté dentro.
     */
    public function retirar(): void
    {
        Ajustes::borrar(self::AJUSTE_PERMISO);
        RegistroAuditoria::anotar('soporte.retirar', null);
        $this->vencer(todos: true);
    }

    public function permitidoHasta(): ?CarbonImmutable
    {
        $permiso = Ajustes::obtener(self::AJUSTE_PERMISO);
        if (! is_array($permiso) || ! is_string($permiso['hasta'] ?? null)) {
            return null;
        }
        $hasta = CarbonImmutable::parse($permiso['hasta']);

        return $hasta->isFuture() ? $hasta : null;
    }

    /**
     * @return array{usuario: User, enlace: string, hasta: CarbonImmutable, aviso_enviado: bool|null}
     *
     * @throws RuntimeException si no se puede entrar
     */
    public function abrir(string $nombre, int $minutos, ?string $emergencia = null): array
    {
        if (! self::disponible()) {
            throw new RuntimeException(__('soporte.solo_instancia'));
        }
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
        $emergencia = filled($emergencia) ? trim((string) $emergencia) : null;
        if ($nombre === '' || mb_strlen($nombre) > 80 || Str::slug($nombre) === '') {
            throw new RuntimeException(__('soporte.nombre_invalido'));
        }
        if ($minutos < 1 || $minutos > self::MINUTOS_MAXIMOS) {
            throw new RuntimeException(__('soporte.minutos_invalidos', ['maximo' => self::MINUTOS_MAXIMOS]));
        }
        $hasta = CarbonImmutable::now('UTC')->addMinutes($minutos);
        $permitido = $this->permitidoHasta();
        if ($permitido === null && $emergencia === null) {
            throw new RuntimeException(__('soporte.sin_permiso'));
        }
        // Con permiso, nunca más allá de lo que dio el cliente.
        if ($permitido !== null && $emergencia === null && $hasta->gt($permitido)) {
            $hasta = $permitido;
        }

        // Una cuenta por nombre exacto: «José» y «Jose» darían el mismo slug, y uno heredaría la cuenta (y la
        // auditoría) del otro.
        $huella = substr(hash('sha256', mb_strtolower($nombre)), 0, 10);
        $usuario = User::query()->firstOrNew(['email' => 'soporte-'.Str::slug($nombre).'-'.$huella.'@'.self::DOMINIO]);
        $usuario->forceFill([
            'name' => __('soporte.usuario', ['nombre' => $nombre], 'es'),
            'password' => Str::password(48),
            'idioma' => $usuario->exists ? $usuario->idioma : 'es',
            'activo' => true,
            'es_administrador' => true,
            'es_superadministrador' => false,
            'es_soporte' => true,
            'soporte_hasta' => $hasta,
            'debe_crear_contrasena' => false,
            // El enlace no la pide: si alguien la activó en este usuario, no serviría.
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ])->save();

        $enlace = EnlaceEntrada::crear($usuario, self::MINUTOS_ENLACE, EnlaceEntrada::USO_SOPORTE);
        RegistroAuditoria::anotar('soporte.acceso', $usuario, array_filter([
            'nombre' => $nombre,
            'hasta' => $hasta->toIso8601String(),
            'emergencia' => $emergencia,
        ]));
        $aviso = $emergencia === null ? null : $this->avisarEmergencia($nombre, $emergencia, $hasta);

        return ['usuario' => $usuario, 'enlace' => $enlace, 'hasta' => $hasta, 'aviso_enviado' => $aviso];
    }

    /**
     * Desactiva a quien se le acabó el tiempo (o a todos, al retirar el permiso).
     *
     * @return int cuántos
     */
    public function vencer(bool $todos = false): int
    {
        $consulta = User::query()->where('es_soporte', true)->where('activo', true);
        if (! $todos) {
            $consulta->where(fn ($query) => $query->whereNull('soporte_hasta')->orWhere('soporte_hasta', '<=', now('UTC')));
        }
        $vencidos = 0;
        foreach ($consulta->get() as $usuario) {
            $usuario->forceFill(['activo' => false])->save();
            RegistroAuditoria::anotar('soporte.vencido', $usuario);
            $vencidos++;
        }

        return $vencidos;
    }

    /**
     * El correo al cliente cuando se entra sin su permiso. Sin correo configurado, queda solo en la auditoría.
     */
    private function avisarEmergencia(string $nombre, string $motivo, CarbonImmutable $hasta): bool
    {
        $correo = app(AjustesCorreo::class);
        if (! $correo->configurado()) {
            return false;
        }
        $enviados = 0;
        foreach (User::query()->where('es_superadministrador', true)->where('activo', true)->where('es_soporte', false)->get() as $dueno) {
            $idioma = in_array($dueno->idioma, (array) config('alojaflow.idiomas'), true) ? $dueno->idioma : 'es';
            try {
                $correo->mailer()->to($dueno->email)->send(new MensajeTexto(
                    __('soporte.emergencia_asunto', [], $idioma),
                    __('soporte.emergencia_cuerpo', ['nombre' => $nombre, 'motivo' => $motivo, 'hasta' => $hasta->setTimezone((string) ($dueno->zona_horaria ?: config('app.timezone')))->locale($idioma)->isoFormat('LLL')], $idioma),
                    $correo->remitente(),
                    $correo->nombreRemitente(),
                ));
                $enviados++;
            } catch (Throwable) {
                // Que falle el correo no impide atender la emergencia: queda en la auditoría.
            }
        }

        return $enviados > 0;
    }
}
