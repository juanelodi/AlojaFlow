# AlojaFlow

Sistema de gestión de alojamientos (PMS) de código abierto, para alojamientos pequeños y
propietarios con varias propiedades. Licencia **AGPL-3.0-or-later**.

> **Estado:** versión de prueba 0.1.0 (fase 1, entrega 1.0 Base). AlojaFlow lee los datos de
> una instalación de **HotelDruid** o **AlojaFlow 1.2.x** y, sin escribir nunca en ella, ofrece
> encima:
> - un resumen de todas las propiedades;
> - la pantalla «Hoy»;
> - el calendario por habitaciones;
> - la búsqueda de disponibilidad;
> - indicadores (ocupación, ADR, RevPAR);
> - usuarios con roles por propiedad.

- **Probarlo:** [docs/guia-pruebas.md](docs/guia-pruebas.md)
- **Producto y fases:** [docs/producto.md](docs/producto.md)
- **Especificación:** [docs/especificacion/README.md](docs/especificacion/README.md)
- **Decisiones:** [docs/decisiones.md](docs/decisiones.md)
- **Reglas para contribuir y para Claude:** [CLAUDE.md](CLAUDE.md)

## Tecnología

PHP 8.3, Laravel 13, Filament 5 y Livewire, MySQL 8.4 o MariaDB 11.4. Sin CDN ni paso de
compilación para usarlo. Pruebas con Pest, análisis con Larastan y formato con Pint.

## Licencia y créditos

Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow. Software libre bajo la
[GNU Affero General Public License v3 o posterior](LICENSE): quien usa AlojaFlow, también por
internet, puede descargar su código fuente desde la propia aplicación.

AlojaFlow es compatible con HotelDruid, de Marco M. F. De Santis, y no es un producto oficial de
HotelDruid. Avisos de terceros: [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).

Las contribuciones se aceptan bajo la misma licencia, con `Signed-off-by` en cada commit (DCO).
