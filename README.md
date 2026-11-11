# AlojaFlow

Sistema de gestión de alojamientos (PMS) de código visible, para alojamientos pequeños y
propietarios con varias propiedades. Licencia **FSL-1.1-ALv2** (Functional Source License 1.1, que
pasa a Apache 2.0 a los dos años).

> **Estado:** versión de prueba 1.0.10. Cada propiedad puede trabajar de dos formas:
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
> - políticas, recibos en PDF e informes con CSV;
> - usuarios con roles por propiedad.
>
> **Por verificar antes de usarlo en real:** los cobros con llaves reales de Stripe y Mercado Pago
> y los textos legales con un abogado ([docs/paises/](docs/paises/mexico.md)).
>
> Trae una demo con datos ficticios para probarlo, y se actualiza desde el propio panel, subiendo
> el zip firmado de cada versión.

- **Probarlo:** [docs/guia-pruebas.md](docs/guia-pruebas.md)
- **Instalar:** sube `instalar-alojaflow-x.y.z.php` a tu hosting y ábrelo en el navegador. Baja lo demás él solo. Si tu servidor no
  sale a internet, usa `instalar-alojaflow-x.y.z-completo.php`, que lo lleva dentro.
- **Instalar y actualizar (detalle):** [docs/especificacion/instalacion-actualizaciones.md](docs/especificacion/instalacion-actualizaciones.md)
- **Producto y fases:** [docs/producto.md](docs/producto.md)
- **Especificación:** [docs/especificacion/README.md](docs/especificacion/README.md)
- **Decisiones:** [docs/decisiones.md](docs/decisiones.md)
- **Reglas para contribuir y para Claude:** [CLAUDE.md](CLAUDE.md)

## Tecnología

PHP 8.3, Laravel 13, Filament 5 y Livewire, MySQL 8.4 o MariaDB 11.4. Sin CDN ni paso de
compilación para usarlo. Pruebas con Pest, análisis con Larastan y formato con Pint.

## Licencia y créditos

Copyright (C) 2026 Juan Galeana Durán. AlojaFlow se publica bajo la
[Functional Source License 1.1, con Apache 2.0 como licencia futura](LICENSE) (FSL-1.1-ALv2):

- **Puedes** usarlo en tu alojamiento o en los que administras, estudiarlo, modificarlo y
  compartirlo, y cobrar por instalarlo, adaptarlo o darle soporte a quien lo usa.
- **No puedes** ofrecer con él un producto o servicio comercial que compita con AlojaFlow, como un
  servicio alojado para terceros. Para eso hace falta una licencia comercial: escribe a <hola@alojaflow.com>.
- **A los dos años** de publicada, cada versión queda además bajo la licencia Apache 2.0.

La FSL no es una licencia de «código abierto» ni de «software libre» en el sentido de la OSI o de
la FSF: es código visible (*Fair Source*). Las versiones **1.0.44 y anteriores** se publicaron bajo
la AGPL-3.0-or-later y lo siguen estando: quien las recibió así conserva esos derechos sobre ellas.
El cambio rige desde la 1.0.45 ([D-174](docs/decisiones.md)).

El código de la versión instalada se sigue pudiendo descargar desde la propia aplicación.

AlojaFlow es compatible con HotelDruid, de Marco M. F. De Santis, y no es un producto oficial de
HotelDruid. Avisos de terceros: [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).

Para contribuir hace falta aceptar el acuerdo de [CONTRIBUTING.md](CONTRIBUTING.md) (CLA).
