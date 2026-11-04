# Avisos de terceros

AlojaFlow se distribuye bajo la licencia **AGPL-3.0-or-later** (ver `LICENSE`). Usa o se basa en
estos trabajos de terceros, cada uno con su licencia.

## Lógica derivada

### HotelDruid

- **Autor:** Marco Maria Francesco De Santis — <https://www.hoteldruid.com>
- **Licencia:** GNU Affero General Public License, versión 3, «o cualquier versión posterior
  aceptada por Marco Maria Francesco De Santis, que actúa como apoderado según la sección 14 de
  la versión 3 de la licencia».
- **Qué se aprovecha:** solo la lógica, reescrita sin copiar código (regla 4 del `CLAUDE.md`):
  - el protocolo de `crea_backup.php`;
  - el formato del archivo de copia, con sus escapes;
  - el significado de sus tablas: periodos, reservas, costos de limpieza…
- **Archivos afectados:** `app/Sincronizacion/HotelDruid/` (sobre todo `ClientePrincipal.php`,
  `LectorCopia.php` y `Proyector.php`) y `tests/Support/ConstructorCopia.php`.
- AlojaFlow es compatible con HotelDruid, pero **no es un producto oficial de HotelDruid** ni usa
  su logo.

### Esqueleto de Laravel

`laravel/laravel` (licencia MIT, © Taylor Otwell) es la base de la estructura del proyecto:
`bootstrap/`, `config/`, `public/index.php` y las migraciones iniciales.

## Dependencias principales

Se instalan con Composer y npm, y no se incluyen en el código fuente que descarga la
aplicación. Sus versiones exactas están en `composer.lock` y `package.json`.

| Componente | Licencia |
|---|---|
| Laravel Framework | MIT |
| Filament | MIT |
| Livewire | MIT |
| spatie/laravel-permission | MIT |
| Tailwind CSS (a través de Filament) | MIT |
| Heroicons (a través de Filament) | MIT |
| Fuente Inter (a través de Filament) | SIL Open Font License 1.1 |
| Pest, Larastan, Laravel Pint (desarrollo) | MIT |
| nette/* (a través de Laravel) | BSD-3-Clause (opción elegida) |
| chillerlan/php-qrcode (a través de Filament) | MIT o Apache-2.0 |

Antes de cada versión se revisan todas las licencias con `composer licenses` y
`npx license-checker --summary` (regla 3 del `CLAUDE.md`).

## Generación de PDF

- [dompdf/dompdf](https://github.com/dompdf/dompdf) — LGPL-2.1, con `dompdf/php-font-lib` (LGPL-2.1-or-later) y `dompdf/php-svg-lib` (LGPL-3.0-or-later).
- [barryvdh/laravel-dompdf](https://github.com/barryvdh/laravel-dompdf) — MIT.

## Calendarios iCal

- [sabre/vobject](https://github.com/sabre-io/vobject) — BSD-3-Clause.
