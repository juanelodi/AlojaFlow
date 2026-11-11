<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoEnlace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Un enlace con token: iCal, llegada en línea, pago… (D-022).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property TipoEnlace $tipo
 * @property string $token_cifrado
 * @property string $token_hmac
 * @property Carbon|null $expira_en
 * @property Carbon|null $revocado_en
 * @property int $usos
 * @property Carbon|null $ultimo_uso_en
 * @property Propiedad $propiedad
 */
class EnlacePublico extends ModeloBase
{
    protected $table = 'enlaces_publicos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoEnlace::class,
            'expira_en' => 'datetime',
            'revocado_en' => 'datetime',
            'ultimo_uso_en' => 'datetime',
            'usos' => 'integer',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return MorphTo<\Illuminate\Database\Eloquent\Model, $this> */
    public function objeto(): MorphTo
    {
        return $this->morphTo();
    }

    public function token(): string
    {
        return Crypt::decryptString($this->token_cifrado);
    }

    public function vigente(): bool
    {
        return $this->revocado_en === null && ($this->expira_en === null || $this->expira_en->isFuture());
    }

    public static function huella(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    /**
     * Crea un enlace con un token nuevo. Hay que darle después el objeto, si tiene.
     */
    public static function nuevo(int $propiedadId, TipoEnlace $tipo, ?\Illuminate\Database\Eloquent\Model $objeto = null, ?CarbonInterface $expiraEn = null): self
    {
        $token = bin2hex(random_bytes(20));
        $enlace = new self([
            'propiedad_id' => $propiedadId,
            'tipo' => $tipo,
            'token_cifrado' => Crypt::encryptString($token),
            'token_hmac' => self::huella($token),
            'expira_en' => $expiraEn,
        ]);
        if ($objeto !== null) {
            $enlace->objeto()->associate($objeto);
        }
        $enlace->save();

        return $enlace;
    }

    public static function buscar(TipoEnlace $tipo, string $token): ?self
    {
        $enlace = self::query()->where('token_hmac', self::huella($token))->where('tipo', $tipo->value)->first();

        return $enlace !== null && $enlace->vigente() ? $enlace : null;
    }

    public function anotarUso(): void
    {
        $this->newQuery()->whereKey($this->id)->update(['usos' => $this->usos + 1, 'ultimo_uso_en' => now('UTC')]);
    }
}
