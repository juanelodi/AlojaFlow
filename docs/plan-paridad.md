# Plan: lo de la 1.2.3 y las funciones nuevas

El usuario lo aprobó el 2026-11-04. Trae a AlojaFlow lo que hacía el sistema anterior
([FUNCIONES-DETALLE.md](FUNCIONES-DETALLE.md)), con las 17 funciones elegidas del informe
([funciones-nuevas.md](funciones-nuevas.md)). Una versión por bloque, publicada en GitHub. Las decisiones de diseño van
en [decisiones.md](decisiones.md), de la D-069 en adelante.

| Versión | Contenido |
|---|---|
| 1.0.11 | Base modular: módulos, Sistema → Módulos, instalador chico, super administrador, preguntas al primer ingreso y primeros pasos. Subida de actualizaciones por partes, con pasos y mensaje de espera |
| 1.0.12 | Subordinar otra instalación de AlojaFlow nueva |
| 1.0.13 | Aviso de entradas y salidas, área de clientes omitidos con casillas, días de limpieza, prefijo de WhatsApp por país, listas de comprobación y mantenimiento recurrente |
| 1.0.14 | Permisos por rol y usuario con sus límites, roles editables, sesiones y dispositivos, quitar la verificación a otro usuario, auditoría completa |
| 1.0.15 | Caja de la principal, informes completos, gráficas, reportes nuevos y exportaciones; catálogo de ISH y reglas de impuesto; caja por turno y bitácora; empresas y agencias |
| 1.0.16 | Hoy y cierre del día, mensajes por periodo, depósitos y no-shows, bloquear fechas y cierres de la principal, importar precios, avisos multipropiedad; restricciones de llegada y salida, enlaces de pago, automatizaciones con condiciones, reseñas con encuesta |
| 1.0.17 | Exportar facturas, detector de duplicados, tipo de cliente en independientes, anonimizar en lote, días y franja de sincronización, base que se actualiza sola, instalable en el móvil, tema, regiones, número de registro |
| 1.0.18 | Grupos completos; comprobante de transferencia en la reserva |
| 1.0.19 | México: PAC para timbrar; OXXO, meses sin intereses y SPEI con Stripe |
| 1.0.20 | España: SES.Hospedajes, tasa turística, Redsys y Bizum, exportación para VeriFactu. El registro encadenado espera al BOE y a un asesor |
| 1.0.21 | Página pública con fotos, «Mi reserva» y guía digital |
| 1.0.22 | Estancias largas y mensualidades |
| 1.0.23 | Campings: atributos y datos del equipo, elegir lugar en el mapa, lecturas con cargo, temporada y residentes, extras con cupo. Sin modo sin conexión |
| 1.0.24 | Propietarios: dueños y comisión, gastos, estados de cuenta y liquidaciones, portal, estancias del dueño |

Al terminar, se le entrega al usuario la lista completa del núcleo heredado de HotelDruid para que decida qué entra.

**Sin cuentas de prueba todavía.** Cobros y PAC se prueban con respuestas simuladas, y cada versión lo dice.

## Lo que el usuario respondió

- **Permisos:** rol por propiedad, casillas para ajustar a cada usuario y pantalla para editar roles y crear otros.
- **Límites por usuario:** solo sus propias reservas y huéspedes, no modificar reservas ya iniciadas, horas límite para
  cambiar o cancelar después de crear la reserva.
- **Super administrador:** acceso a todo, y es quien instala. El instalador lo explica con un texto.
- **Omitir clientes:** casillas para elegir dónde se omite cada cliente, en un área especial que se explica en los
  avisos. Además del aviso, también en reportes y más.
- **Grupos:** completo, con página pública del coordinador, contrato, comprobantes y mensajes.
- **Módulos:** por instalación y solo los cambia el super administrador. Las preguntas salen en la primera entrada, se
  pueden repetir y se pueden saltar; si se saltan, todo queda prendido. Después de instalar se puede activar cualquiera.
- **Subordinar otra instalación nueva:** cuanto antes.
- **Sí venderá en España, y sí tendrá clientes que administran propiedades de terceros y campings.**
- **Descartado en este plan:** channel manager propio, almacenes, punto de venta completo y feed de Google.
