# AlojaFlow

Sistema de gestión de alojamientos (PMS) de código abierto, para alojamientos pequeños y
propietarios con varias propiedades. Licencia **AGPL-3.0-or-later**.

> **Estado:** versión de prueba 1.0.5. Cada propiedad puede trabajar de dos formas:
> - **Independiente:** AlojaFlow es su sistema: tarifas, cargos, impuestos, huéspedes, reservas, pagos, caja,
>   grupos, cupones y un motor de reservas con Stripe y Mercado Pago.
> - **Subordinada:** AlojaFlow lee los datos de una instalación de **HotelDruid** o
>   **AlojaFlow 1.2.x**, sin escribir nunca en ella. Se puede pasar a independiente cuando quieras.
>
> En las dos formas ofrece:
> - resumen, «Hoy», calendario por habitaciones, plano, disponibilidad y cierre del día;
> - limpieza y mantenimiento desde el teléfono;
> - mensajes automáticos por correo, WhatsApp con texto listo y avisos a otros sistemas;
> - calendarios iCal con Airbnb, Booking.com y Vrbo, con aviso de choques;
> - llegada en línea con documentos legales, y herramientas de privacidad;
> - políticas, facturas y recibos (CFDI 4.0 preparado en México), e informes con CSV;
> - usuarios con roles por propiedad.
>
> **Por verificar antes de usarlo en real:** los cobros con llaves reales de Stripe y Mercado Pago, el timbrado
> de CFDI con un PAC y los textos legales con un abogado ([docs/paises/](docs/paises/mexico.md)).
>
> Trae una demo con datos ficticios para probarlo, y se actualiza desde el propio panel, subiendo
> el zip firmado de cada versión.

- **Probarlo:** [docs/guia-pruebas.md](docs/guia-pruebas.md)
- **Instalar:** sube `instalar-alojaflow-x.y.z.php` a tu hosting y ábrelo en el navegador.
- **Instalar y actualizar (detalle):** [docs/especificacion/instalacion-actualizaciones.md](docs/especificacion/instalacion-actualizaciones.md)
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
