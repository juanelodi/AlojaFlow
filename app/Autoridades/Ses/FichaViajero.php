<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Autoridades\Ses;

use App\Enums\EstadoRegistroLlegada;
use App\Enums\TipoContacto;
use App\Models\EnvioAutoridad;
use App\Models\Huesped;
use App\Models\PersonaLlegada;
use App\Models\RegistroLlegada;
use App\Models\Reserva;
use App\Paises\CatalogoMunicipios;
use App\Paises\CatalogoPaises;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Una persona de la reserva como la pide SES.Hospedajes, de donde se sepa: lo que mandó en su registro de llegada o, si no, su ficha. Los códigos salen del
 * catálogo del servicio (TIPO_DOCUMENTO, SEXO, TIPO_PARENTESCO).
 */
final class FichaViajero
{
    private const LETRAS_DNI = 'TRWAGMYFPDXBNJZSQVHLCKE';

    private const PARENTESCOS = [
        'abuelo' => 'AB', 'abuela' => 'AB', 'bisabuelo' => 'BA', 'bisabuela' => 'BA', 'bisnieto' => 'BN', 'bisnieta' => 'BN', 'cunado' => 'CD', 'cunada' => 'CD',
        'conyuge' => 'CY', 'esposo' => 'CY', 'esposa' => 'CY', 'marido' => 'CY', 'pareja' => 'CY', 'hijo' => 'HJ', 'hija' => 'HJ', 'hermano' => 'HR', 'hermana' => 'HR',
        'nieto' => 'NI', 'nieta' => 'NI', 'padre' => 'PM', 'madre' => 'PM', 'papa' => 'PM', 'mama' => 'PM', 'sobrino' => 'SB', 'sobrina' => 'SB', 'suegro' => 'SG',
        'suegra' => 'SG', 'tio' => 'TI', 'tia' => 'TI', 'yerno' => 'YN', 'nuera' => 'YN', 'tutor' => 'TU', 'tutora' => 'TU', 'otro' => 'OT', 'otra' => 'OT',
    ];

    private const CODIGOS_PARENTESCO = ['AB', 'BA', 'BN', 'CD', 'CY', 'HJ', 'HR', 'NI', 'PM', 'SB', 'SG', 'TI', 'YN', 'TU', 'OT'];

    /**
     * @param  array<string, string|null>  $datos
     */
    private function __construct(public readonly string $clave, public readonly bool $titular, private array $datos)
    {
    }

    /**
     * @param  array<string, array<string, string>>  $correcciones
     * @return list<self> el titular, primero
     */
    public static function deReserva(Reserva $reserva, array $correcciones = []): array
    {
        $registro = RegistroLlegada::query()->with('personas')->where('reserva_id', $reserva->id)
            ->whereIn('estado', [EstadoRegistroLlegada::Recibido->value, EstadoRegistroLlegada::Revisado->value])->orderByDesc('id')->first();
        $fichas = $registro !== null && $registro->personas->isNotEmpty()
            ? $registro->personas->map(fn (PersonaLlegada $persona): self => self::deLlegada($persona))->all()
            : self::deHuespedes($reserva);
        usort($fichas, fn (self $a, self $b): int => (int) $b->titular <=> (int) $a->titular);

        // El formulario y las fichas de los acompañantes no suelen traer domicilio ni contacto, y el servicio los pide de todos: los de quien reservó.
        $titular = $fichas[0] ?? null;
        foreach ($fichas as $ficha) {
            if ($titular !== null && blank($ficha->datos['direccion'] ?? null)) {
                foreach (['direccion', 'ciudad', 'municipio', 'codigo_postal', 'pais'] as $campo) {
                    $ficha->datos[$campo] = $titular->datos[$campo] ?? null;
                }
            }
            if ($titular !== null && blank($ficha->datos['telefono'] ?? null) && blank($ficha->datos['correo'] ?? null)) {
                $ficha->datos['telefono'] = $titular->datos['telefono'] ?? null;
                $ficha->datos['correo'] = $titular->datos['correo'] ?? null;
            }
            foreach ($correcciones[$ficha->clave] ?? [] as $campo => $valor) {
                if ($campo === 'contacto') {
                    $ficha->datos[str_contains($valor, '@') ? 'correo' : 'telefono'] = $valor;
                } else {
                    $ficha->datos[$campo] = $valor;
                }
            }
        }

        return $fichas;
    }

    private static function deLlegada(PersonaLlegada $persona): self
    {
        $extras = (array) $persona->datos_pais;

        return new self('p'.$persona->id, $persona->es_titular, [
            'nombre' => $persona->nombre,
            'apellido1' => $persona->apellido1,
            'apellido2' => $persona->apellido2,
            'documento_tipo' => $persona->documento_tipo,
            'documento_numero' => $persona->documento_numero,
            'soporte' => is_string($extras['soporte'] ?? null) ? $extras['soporte'] : null,
            'parentesco' => is_string($extras['parentesco'] ?? null) ? $extras['parentesco'] : null,
            'fecha_nacimiento' => $persona->fecha_nacimiento?->format('Y-m-d'),
            'nacionalidad' => $persona->nacionalidad_texto,
            'sexo' => $persona->sexo,
            'direccion' => $persona->domicilio_calle,
            'ciudad' => $persona->domicilio_ciudad,
            'codigo_postal' => $persona->domicilio_codigo_postal,
            'pais' => $persona->domicilio_pais_texto,
            'telefono' => $persona->telefono,
            'correo' => $persona->correo,
        ]);
    }

    /**
     * @return list<self>
     */
    private static function deHuespedes(Reserva $reserva): array
    {
        $titular = $reserva->titular;
        $otros = $reserva->huespedes()->with(['contactos', 'campos'])->get()->filter(fn (Huesped $huesped): bool => $huesped->id !== $titular?->id);
        $fichas = $titular !== null ? [self::deHuesped($titular, true)] : [];
        foreach ($otros as $huesped) {
            $fichas[] = self::deHuesped($huesped, false);
        }

        return $fichas;
    }

    private static function deHuesped(Huesped $huesped, bool $titular): self
    {
        $campo = fn (string $nombre): ?string => $huesped->campos->firstWhere('campo', $nombre)?->valor;
        $contacto = fn (TipoContacto $tipo): ?string => $huesped->contactos->firstWhere('tipo', $tipo)?->valor;

        return new self('h'.$huesped->id, $titular, [
            'nombre' => $huesped->nombre,
            'apellido1' => $huesped->apellido1,
            'apellido2' => $huesped->apellido2,
            'documento_tipo' => $huesped->documento_tipo_texto,
            'documento_numero' => $huesped->documento_numero,
            'soporte' => $campo('soporte'),
            'parentesco' => $campo('parentesco'),
            'fecha_nacimiento' => $huesped->fecha_nacimiento?->format('Y-m-d'),
            'nacionalidad' => $huesped->nacionalidad_texto,
            'sexo' => $huesped->getAttribute('sexo'),
            'direccion' => trim($huesped->domicilio_calle.' '.$huesped->domicilio_numero) ?: null,
            'ciudad' => $huesped->domicilio_ciudad,
            'codigo_postal' => $huesped->domicilio_codigo_postal,
            'pais' => $huesped->domicilio_pais_texto,
            'telefono' => $contacto(TipoContacto::Telefono),
            'correo' => $contacto(TipoContacto::Correo),
        ]);
    }

    public function nombreVisible(): string
    {
        return trim(($this->datos['nombre'] ?? '').' '.($this->datos['apellido1'] ?? ''));
    }

    public function valor(string $campo): ?string
    {
        return $this->datos[$campo] ?? null;
    }

    /**
     * Lo que le falta para poderse comunicar, según el apartado 3.1.1 del documento del servicio.
     *
     * @return list<string>
     */
    public function faltan(string $tipo, Carbon $llegada): array
    {
        $faltan = [];
        foreach (['nombre', 'apellido1'] as $campo) {
            if (blank($this->datos[$campo] ?? null)) {
                $faltan[] = $campo;
            }
        }
        if (blank($this->telefono()) && blank($this->correo())) {
            $faltan[] = 'contacto';
        }
        if ($tipo === EnvioAutoridad::RESERVA) {
            return $faltan;
        }

        if (blank($this->datos['fecha_nacimiento'] ?? null)) {
            $faltan[] = 'fecha_nacimiento';
        }
        $menor = $this->esMenor($llegada);
        $documento = $this->tipoDocumento();
        if (! $menor && $documento === null) {
            $faltan[] = 'documento_tipo';
        }
        if (! $menor && (blank($this->numeroDocumento()) || ! $this->documentoValido())) {
            $faltan[] = 'documento_numero';
        }
        if (in_array($documento, ['NIF', 'NIE'], true) && blank($this->datos['soporte'] ?? null)) {
            $faltan[] = 'soporte';
        }
        if ($documento === 'NIF' && blank($this->datos['apellido2'] ?? null)) {
            $faltan[] = 'apellido2';
        }
        if ($menor && $this->parentesco() === null) {
            $faltan[] = 'parentesco';
        }
        foreach (['direccion' => 'direccion', 'codigo_postal' => 'codigo_postal'] as $campo => $nombre) {
            if (blank($this->datos[$campo] ?? null)) {
                $faltan[] = $nombre;
            }
        }
        $pais = $this->pais();
        if ($pais === null) {
            $faltan[] = 'pais';
        } elseif ($pais === 'ESP' && $this->codigoMunicipio() === null) {
            $faltan[] = 'municipio';
        } elseif ($pais !== 'ESP' && blank($this->datos['ciudad'] ?? null)) {
            $faltan[] = 'ciudad';
        }

        return $faltan;
    }

    /**
     * @return array<string, mixed> en el orden del esquema, que es parte de lo que se valida
     */
    public function paraMensaje(string $tipo, ?Carbon $llegada = null): array
    {
        $parte = $tipo === EnvioAutoridad::PARTE;
        $conDocumento = $this->numeroDocumento() !== null && $this->documentoValido();

        return array_filter([
            'rol' => ! $parte && $this->titular ? 'TI' : 'VI',
            'nombre' => $this->corto('nombre', 50),
            'apellido1' => $this->corto('apellido1', 50),
            'apellido2' => $this->corto('apellido2', 50),
            'tipoDocumento' => $conDocumento ? $this->tipoDocumento() : null,
            'numeroDocumento' => $conDocumento ? mb_substr((string) $this->numeroDocumento(), 0, 15) : null,
            'soporteDocumento' => $parte && $conDocumento && in_array($this->tipoDocumento(), ['NIF', 'NIE'], true) ? $this->corto('soporte', 9) : null,
            'fechaNacimiento' => $this->datos['fecha_nacimiento'] ?? null,
            'nacionalidad' => CatalogoPaises::alfa3De($this->datos['nacionalidad'] ?? null),
            'sexo' => $this->sexo(),
            'direccion' => $this->direccion(),
            'telefono' => $this->telefono(),
            'correo' => $this->correo(),
            'parentesco' => $parte && $llegada !== null && $this->esMenor($llegada) ? $this->parentesco() : null,
        ], fn (mixed $valor): bool => $valor !== null && $valor !== '');
    }

    /**
     * @return array<string, string>|null
     */
    private function direccion(): ?array
    {
        $pais = $this->pais();
        if (blank($this->datos['direccion'] ?? null) || blank($this->datos['codigo_postal'] ?? null) || $pais === null) {
            return null;
        }
        $municipio = $pais === 'ESP' ? $this->codigoMunicipio() : null;
        if ($pais === 'ESP' ? $municipio === null : blank($this->datos['ciudad'] ?? null)) {
            return null;
        }

        return array_filter([
            'direccion' => $this->corto('direccion', 100),
            'codigoMunicipio' => $municipio,
            'nombreMunicipio' => $pais === 'ESP' ? null : $this->corto('ciudad', 100),
            'codigoPostal' => $this->corto('codigo_postal', 20),
            'pais' => $pais,
        ], fn (?string $valor): bool => $valor !== null);
    }

    public function tipoDocumento(): ?string
    {
        $texto = strtolower(Str::ascii(trim((string) ($this->datos['documento_tipo'] ?? ''))));
        if ($texto === '') {
            $numero = (string) $this->numeroDocumento();

            return match (true) {
                preg_match('/^[0-9]{8}[A-Z]$/', $numero) === 1 => 'NIF',
                preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $numero) === 1 => 'NIE',
                default => null,
            };
        }

        return match ($texto) {
            'dni', 'nif' => 'NIF',
            'nie' => 'NIE',
            'pasaporte', 'pas', 'passport' => 'PAS',
            default => 'OTRO',
        };
    }

    public function numeroDocumento(): ?string
    {
        $numero = strtoupper((string) preg_replace('/[\s.\-]/', '', (string) ($this->datos['documento_numero'] ?? '')));

        return $numero === '' ? null : $numero;
    }

    /**
     * El servicio rechaza un DNI o NIE con el formato o la letra mal: mejor saberlo antes que un día después, por correo.
     */
    private function documentoValido(): bool
    {
        $numero = (string) $this->numeroDocumento();

        return match ($this->tipoDocumento()) {
            'NIF' => preg_match('/^([0-9]{8})([A-Z])$/', $numero, $partes) === 1 && self::LETRAS_DNI[(int) $partes[1] % 23] === $partes[2],
            'NIE' => preg_match('/^([XYZ])([0-9]{7})([A-Z])$/', $numero, $partes) === 1 && self::LETRAS_DNI[(int) (strpos('XYZ', $partes[1]).$partes[2]) % 23] === $partes[3],
            default => mb_strlen($numero) <= 15,
        };
    }

    private function esMenor(Carbon $llegada): bool
    {
        $nacimiento = $this->datos['fecha_nacimiento'] ?? null;

        return filled($nacimiento) && Carbon::parse((string) $nacimiento)->addYears(18)->greaterThan($llegada);
    }

    private function parentesco(): ?string
    {
        $texto = trim((string) ($this->datos['parentesco'] ?? ''));
        if ($texto === '') {
            return null;
        }
        if (in_array(strtoupper($texto), self::CODIGOS_PARENTESCO, true)) {
            return strtoupper($texto);
        }

        return self::PARENTESCOS[strtolower(Str::ascii($texto))] ?? 'OT';
    }

    private function sexo(): ?string
    {
        return match (strtolower(Str::ascii(trim((string) ($this->datos['sexo'] ?? ''))))) {
            'h', 'm', 'o' => strtoupper(trim((string) $this->datos['sexo'])),
            'hombre', 'masculino', 'male' => 'H',
            'f', 'mujer', 'femenino', 'female' => 'M',
            'x', 'otro', 'other' => 'O',
            default => null,
        };
    }

    private function pais(): ?string
    {
        return CatalogoPaises::alfa3De($this->datos['pais'] ?? null);
    }

    private function codigoMunicipio(): ?string
    {
        $indicado = trim((string) ($this->datos['municipio'] ?? ''));
        if (preg_match('/^[0-9]{5}$/', $indicado) === 1) {
            return $indicado;
        }

        return CatalogoMunicipios::codigo($indicado !== '' ? $indicado : ($this->datos['ciudad'] ?? null), $this->datos['codigo_postal'] ?? null);
    }

    private function telefono(): ?string
    {
        return $this->corto('telefono', 20);
    }

    /**
     * El esquema exige la forma de un correo; uno mal escrito tumbaría el lote entero al validarlo.
     */
    private function correo(): ?string
    {
        $correo = trim((string) ($this->datos['correo'] ?? ''));

        return preg_match('/^[^@\s]+@[^.\s]+\..+$/', $correo) === 1 ? mb_substr($correo, 0, 250) : null;
    }

    private function corto(string $campo, int $largo): ?string
    {
        $valor = trim((string) ($this->datos[$campo] ?? ''));

        return $valor === '' ? null : mb_substr($valor, 0, $largo);
    }
}
