# AlojaFlow

Sistema de gestión de alojamientos (PMS) de código abierto, para alojamientos pequeños y
propietarios con varias propiedades. Licencia **AGPL-3.0-or-later**.

> **Estado:** versión de prueba 1.0.3. Cada propiedad puede trabajar de dos formas:
> - **Independiente:** AlojaFlow es su sistema, con unidades, tarifas por temporada, huéspedes,
>   reservas, bloqueos, entrada y salida, y pagos.
> - **Subordinada:** AlojaFlow lee los datos de una instalación de **HotelDruid** o
>   **AlojaFlow 1.2.x**, sin escribir nunca en ella.
>
> En las dos formas ofrece:
> - un resumen de todas las propiedades;
> - la pantalla «Hoy»;
> - el calendario por habitaciones;
> - la búsqueda de disponibilidad;
> - indicadores (ocupación, ADR, RevPAR);
> - usuarios con roles por propiedad.
>
> Trae una demo con datos ficticios para probarlo, y se actualiza desde el propio panel, subiendo
> el zip firmado de cada versión.

- **Probarlo:** [docs/guia-pruebas.md](docs/guia-pruebas.md)
- **Instalar y actualizar:** [docs/especificacion/instalacion-actualizaciones.md](docs/especificacion/instalacion-actualizaciones.md)
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
