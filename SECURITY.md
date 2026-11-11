# Seguridad

## Cómo avisar de un fallo

**No abras un issue público.** Avísalo en privado desde
[Report a vulnerability](https://github.com/juanelodi/AlojaFlow/security/advisories/new). Si no tienes
cuenta de GitHub, escribe a <hola@alojaflow.com> con «Seguridad» en el asunto.

Cuenta:

- la versión de AlojaFlow y si la propiedad es independiente o subordinada;
- qué permite el fallo y quién podría aprovecharlo: un huésped, alguien del equipo del alojamiento o
  cualquiera en internet;
- los pasos para reproducirlo.

No mandes datos reales de huéspedes. Si hace falta un ejemplo, usa datos inventados.

Pruébalo solo en una instalación tuya. No lo intentes contra instalaciones ajenas ni contra
demo.alojaflow.com, ni trates de leer datos de otras personas.

## Qué versiones se corrigen

Solo la más reciente. La corrección sale en una versión nueva, y cada instalación la recibe desde
*Sistema → Actualizaciones*.

## Qué pasa después

1. Te respondo para confirmar que llegó y, después de revisarlo, si es un fallo de seguridad.
2. Mientras no haya una versión corregida, el fallo no se publica.
3. Cuando sale, se publica el aviso en GitHub y la nota en [CAMBIOS.md](CAMBIOS.md), con tu nombre
   si lo quieres.

## Qué entra

- Todo el código de este repositorio: el panel, el motor de reservas y las páginas públicas, el
  instalador, las actualizaciones firmadas, la lectura de la copia de HotelDruid y la mejora continua (lo
  que una instalación manda a dev.alojaflow.com).
- Cómo usa AlojaFlow los servicios de terceros (Stripe, Mercado Pago, el correo, iCal).

No entran la configuración del servidor de cada instalación, los servicios de terceros en sí,
HotelDruid ni AlojaFlow 1.2.x.

Un error que no es de seguridad va en un [issue](https://github.com/juanelodi/AlojaFlow/issues/new/choose)
o, desde AlojaFlow, en «Reportar o sugerir».
