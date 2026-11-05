<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario del personal. Tabla y columnas del framework en inglés; las de AlojaFlow, en español.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $idioma
 * @property string|null $zona_horaria
 * @property bool $activo
 * @property bool $es_administrador
 * @property bool $es_superadministrador
 * @property string|null $app_authentication_secret
 * @property array<string>|null $app_authentication_recovery_codes
 * @property \Illuminate\Support\Carbon|null $ultimo_acceso_en
 */
#[Fillable(['name', 'email', 'password', 'idioma', 'zona_horaria', 'activo', 'es_administrador', 'es_superadministrador'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected static function booted(): void
    {
        // Tiene acceso a todo: nunca es menos que administrador (D-070).
        static::saving(function (User $usuario): void {
            if ($usuario->es_superadministrador) {
                $usuario->es_administrador = true;
            }
        });

        // Quien pierde el acceso o cambia de contraseña no sigue dentro en otro navegador.
        static::updated(function (User $usuario): void {
            if (! $usuario->wasChanged('password') && ! ($usuario->wasChanged('activo') && ! $usuario->activo)) {
                return;
            }
            DB::table('sessions')->where('user_id', $usuario->id)->where('id', '!=', session()->getId())->delete();
            DispositivoRecordado::query()->where('user_id', $usuario->id)->delete();
            // Sin eventos: esto no es otro cambio del usuario.
            User::query()->whereKey($usuario->id)->toBase()->update(['remember_token' => null]);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'activo' => 'boolean',
            'es_administrador' => 'boolean',
            'es_superadministrador' => 'boolean',
            'ultimo_acceso_en' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->activo;
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * @return array<string>|null
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  array<string>|null  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
