# Avisos de terceros

AlojaFlow se distribuye bajo la licencia **FSL-1.1-ALv2** (ver `LICENSE`) desde la versión 1.0.45;
las versiones anteriores, bajo la AGPL-3.0-or-later (D-174). Usa estos trabajos de terceros, cada
uno con su licencia, que no cambia.

## Compatibilidad con HotelDruid

AlojaFlow es compatible con HotelDruid, de Marco Maria Francesco De Santis
(<https://www.hoteldruid.com>): puede copiar, solo para leerlos, los datos de una instalación de
HotelDruid 3.0.8 o de AlojaFlow 1.2.x. Para eso interopera con su protocolo de copias
(`crea_backup.php`) y con el formato de su archivo de copia, que están documentados como hechos
en `docs/especificacion/subordinacion.md`.

- **Sin código ni lógica derivados de HotelDruid** desde el commit `b3c938d` («Sala limpia: se
  retira el módulo de HotelDruid para reescribirlo»). `app/Sincronizacion/HotelDruid/` y
  `tests/Support/ConstructorCopia.php` se escribieron de nuevo a partir de esa especificación, sin
  ver el código de HotelDruid ni el del módulo anterior. El procedimiento y su registro están en
  `docs/sala-limpia/README.md` (decisión D-173).
- Hasta ese commit, esos archivos contenían lógica de HotelDruid reescrita sin copiar código.
  HotelDruid se distribuye bajo la GNU Affero General Public License, versión 3, «o cualquier
  versión posterior aceptada por Marco Maria Francesco De Santis, que actúa como apoderado según
  la sección 14 de la versión 3 de la licencia».
- AlojaFlow **no es un producto oficial de HotelDruid** ni usa su logo. «HotelDruid» se menciona
  solo para decir con qué es compatible.

## Estructura de partida

### Esqueleto de Laravel

`laravel/laravel` (licencia MIT, © Taylor Otwell) es la base de la estructura del proyecto:
`bootstrap/`, `config/`, `public/index.php` y las migraciones iniciales. Lo que esos archivos conservan
del esqueleto sigue bajo su licencia, que se reproduce aquí; la cabecera de AlojaFlow cubre lo añadido.

```
The MIT License (MIT)

Copyright (c) Taylor Otwell

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

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
`npx license-checker --summary` (regla 3 del `CLAUDE.md`). Ninguna dependencia es GPL ni AGPL: las
LGPL (dompdf) se usan como bibliotecas, sin modificar, y se pueden sustituir en `vendor/`.

## Generación de PDF

- [dompdf/dompdf](https://github.com/dompdf/dompdf) — LGPL-2.1, con `dompdf/php-font-lib` (LGPL-2.1-or-later) y `dompdf/php-svg-lib` (LGPL-3.0-or-later).
- [barryvdh/laravel-dompdf](https://github.com/barryvdh/laravel-dompdf) — MIT.

## Calendarios iCal

- [sabre/vobject](https://github.com/sabre-io/vobject) — BSD-3-Clause.

## Datos incluidos

- **`resources/datos/municipios-ine.json`**: relación de municipios y códigos a 1 de enero de 2026, del Instituto Nacional de Estadística de España (https://www.ine.es). Fuente: INE. Reutilización según su aviso legal (CC BY 4.0, Ley 37/2007 y RD 1495/2011). Se conservaron el código y el nombre; el INE no participa ni respalda este uso.
- **`resources/certificados/fnmt.pem`**: certificados públicos «AC RAIZ FNMT-RCM» y «AC Componentes Informáticos» de la FNMT-RCM (http://www.cert.fnmt.es/certs/), para comprobar el servidor de SES.Hospedajes.
- **Códigos ISO 3166-1** en `app/Paises/CatalogoPaises.php`: una lista de códigos del estándar.
