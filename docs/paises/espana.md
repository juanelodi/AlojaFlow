# Paquete de España

**Sin revisar por un asesor.** Lo que dice cada apartado se tomó de las fuentes oficiales que se citan, con fecha, y lo que no se pudo comprobar se dice. Antes de usarlo en real hay que probarlo con cuentas propias.

## Qué trae

- Identificaciones: DNI, NIE, pasaporte y otra, con número de soporte. Se pide el documento a todas las personas mayores de edad.
- Textos de partida genéricos: hay que adaptarlos al RGPD y a la LOPDGDD, y decir que los datos se comunican a las Fuerzas y Cuerpos de Seguridad.
- Recibos sin valor fiscal.
- **SES.Hospedajes**, **tasa turística**, **Redsys y Bizum**, y **exportación para un programa de facturación**: abajo.

## SES.Hospedajes

Obligación: [RD 933/2021](https://www.boe.es/buscar/act.php?id=BOE-A-2021-17461), consultado el 2026-11-05.

- Comunicar la reserva y su anulación, y el inicio del servicio, de forma inmediata y **en un máximo de 24 horas** (art. 6.3). Es por vía telemática salvo en el hospedaje no profesional (art. 6.4).
- Conservar el registro **tres años** desde que termina el servicio (art. 5.3). El hospedaje no profesional queda exento de esto, pero no de comunicar (art. 5.4).
- Faltar a ello es infracción grave; comunicar fuera de plazo o con irregularidades, leve (art. 8).

Cómo se comunica: documento [«Interfaz servicios externos, Servicio de Comunicación Hospedajes», versión 3.1.2](https://seshospedajes.es/wp-content/uploads/2024/12/MIR-HOSPE-DSI-WS-Servicio-de-Hospedajes-Comunicaciones-v3.1.2.pdf), copia de un tercero del documento del Ministerio. Existe una 3.1.3 de 2025.

**Para conectarse:** registrarse en la sede del Ministerio del Interior, pedir las credenciales del servicio web y escribirlas en **Operación → Registro de viajeros → Conectar**, con los códigos de arrendador y de establecimiento. Se empieza en el ambiente de pruebas, que no cuenta como comunicado.

**Qué hace AlojaFlow.** Cada 10 minutos compara las reservas con lo comunicado (D-100):

- Una reserva confirmada: comunicación de reserva (RH). Al registrar la entrada: parte de viajeros (PV).
- Si se cancela, anula lo comunicado. Si cambian las fechas, las personas o los documentos, anula y manda otra: el servicio no modifica.
- Lo que falte se pide en esa pantalla, por persona. Un DNI o NIE con la letra mal no se manda. Avisa de lo rechazado, de lo incompleto y de lo fuera de plazo.
- Lo anterior a conectarse no se manda.
- De los pagos manda el tipo y la fecha. El medio de pago, su titular y la caducidad son opcionales en el servicio y no se mandan: los números los tiene la pasarela, no AlojaFlow. **El anexo I del RD los incluye: pregúntale a tu asesor si con eso basta.**
- A los acompañantes sin domicilio o contacto propios se les pone los de quien reservó, porque el servicio los exige de todos. El formulario de llegada no se los pregunta.

**Certificado.** El servidor no manda el certificado intermedio de la FNMT, y OpenSSL no lo puede comprobar sin él: va en `resources/certificados/fnmt.pem` (AC Componentes Informáticos, vence en junio de 2028, y AC Raíz FNMT-RCM, enero de 2030). Si el Ministerio cambia de certificado, hay que actualizarlo en una versión nueva. Comprobado con `openssl s_client` contra los dos ambientes.

**Lo que está comprobado (2026-11-05):** los XML generados validan contra los XSD de la versión 3.1.3 (`altaParteHospedaje`, `altaReservaHospedaje`, `anularComunicacion`, `consultarComunicacion`), copiados de repositorios públicos que los traen del paquete del Ministerio. Los dos ambientes contestan 401 sin credenciales, y el de pruebas contesta 502 con un usuario inventado, después de medio minuto si es un POST: por eso un error así avisa de revisar la contraseña. PHP con OpenSSL comprueba su certificado con la cadena incluida, y falla con la lista normal de autoridades. **Lo que no:** nunca se ha mandado nada con credenciales de verdad: el servicio se simuló en las pruebas, con el formato de los ejemplos del documento. Los códigos de catálogo (`TIPO_PAGO`, `TIPO_DOCUMENTO`, `SEXO`, `TIPO_PARENTESCO`) no están en el documento 3.1.2, que remite a la operación `catalogo`: se tomaron de repositorios públicos que coinciden entre sí.

**Fuera:** Mossos d'Esquadra y Ertzaintza, que siguen sus propios sistemas según fuentes del sector. No se encontró su documentación técnica.

## Tasa turística

En **Impuestos → Proponer por lugar**. Se crean impuestos normales, que se pueden cambiar. Se suman al precio y van en una línea aparte de la factura, con unidades y tarifa, sin IVA encima.

**Cataluña, impuesto sobre las estancias (IEET).** [Ley 2/2026](https://www.boe.es/boe/dias/2026/03/23/pdfs/BOE-A-2026-6642.pdf), [Ley 5/2017](https://www.boe.es/buscar/doc.php?id=BOE-A-2017-7353) y [cuota de la ATC](https://atc.gencat.cat/es/tributs/ieet/quota-tributaria), consultadas el 2026-11-05:

| Tipo | Barcelona desde 01/04/2026 | Recargo de Barcelona 2026-27 / 2027-28 / 2028-29 | Resto, hasta 31/03/2027 | Resto, desde 01/04/2027 |
|---|---|---|---|---|
| Hotel 5★, gran lujo, camping de lujo | 7,00 | 5 / 6 / 7 | 4,50 | 6,00 |
| Hotel 4★ y 4★ superior | 3,40 | 5 / 6,40 / 7 | 1,80 | 2,40 |
| Vivienda de uso turístico | 4,50 | 5 / 6 / 7 | 1,75 | 2,50 |
| Albergue de juventud | 1,00 | 5 / 6 / 7 | 0,80 | 1,00 |
| Resto de campings y establecimientos | 2,00 | 5 / 6 / 7 | 0,90 | 1,20 |

Máximo 7 unidades por persona (art. 33.1), exentas las personas de hasta 16 años (art. 27.1.b). Con reserva anticipada vale la tarifa de ese día **si se cobran entonces la reserva y el impuesto** (art. 34.3): la opción «vale la tarifa del día en que se reserva» viene prendida, y hay que apagarla si no se cobra al reservar.

**Otros municipios catalanes** pueden aprobar recargo desde el 01/10/2026 (Castelldefels, Esplugues, L'Hospitalet, El Prat, Sabadell, Sant Cugat y Sant Just Desvern ya lo hicieron, según la ATC): se suma a mano. La tarifa especial de los centros recreativos con juego no está.

**Declaración.** El botón **Archivo para la ATC** genera el CSV de su [carga masiva](https://atc.gencat.cat/es/tributs/ieet/tramit-ieet/carrega-estades-csv/), con la cabecera de su plantilla: semestres de abril a septiembre (se presenta del 1 al 20 de octubre) y de octubre a marzo (del 1 al 20 de abril). **Se cuentan las estancias que empiezan en el semestre:** si una cruza, hay que repartirla a mano. La ATC no aclara qué año va en «Ejercicio» para octubre a marzo: se pone el año en que empieza.

**Illes Balears, impuesto sobre estancias turísticas (ITS).** [Ley 2/2016, texto consolidado](https://www.boe.es/buscar/pdf/2016/BOE-A-2016-4175-consolidado.pdf) con última modificación del 17/05/2025, arts. 5 y 13: de 1 a 4 euros según la clase, 75 % menos del 1 de noviembre al 30 de abril, 50 % menos desde el noveno día, y exentos los menores de 16 años. **El Govern presentó un cambio a cuatro tramos por estación que no se pudo confirmar como aprobado:** compruébalo en la ATIB antes de cobrar.

**Fuera:** los municipios gallegos y las forales vascas, que cambian por ordenanza y solo se encontraron en fuentes secundarias.

## Redsys y Bizum

En **Motor de reservas**, si la moneda es el euro. Con el manual de [redirección de Redsys 2.1](https://canales.redsys.es/ayuda/documentacion/Manual%20integracion%20para%20conexion%20por%20Redireccion.pdf) (2016) y la firma `HMAC_SHA256_V1`. Bizum es el mismo terminal con `DS_MERCHANT_PAYMETHODS = z`, según las guías de [BBVA](https://bbva.es/en/content/dam/public-web/bbvaes/documents/productos/tpv/manual-de-integracion-bizum.pdf) y [Cajamar](https://www.cajamar.es/storage/documents/6296/configura-bizum-en-tu-tpv-virtual-gcc.pdf). Tu banco tiene que tenerlo activado.

Hay que configurar la **notificación online** en su módulo de administración; el aviso llega a `/avisos-pago/<código>/redsys` (o `/bizum`).

**Lo que está comprobado (2026-11-05), con su entorno de pruebas real:** un cobro completo, con el comercio de pruebas que publican y su tarjeta de pruebas: del botón de la reserva a su página, autenticación simulada, autorización, vuelta con el resultado firmado, firma comprobada y reserva confirmada con el código de autorización que dio su página. Con Bizum, su entorno abre sin error, sin llegar a pagar. Una firma mala da SIS0042. **Lo que no:** su aviso (la notificación online) no llega a un equipo local y está simulado, y ningún cobro con un comercio de verdad.

## Facturación

AlojaFlow emite en España **recibos sin valor fiscal**. El documento fiscal lo emite un programa de facturación: **Facturas → Exportar → Para tu programa de facturación** da una fila por factura y tipo de impuesto (D-103).

**VeriFactu no está.** No se genera el registro encadenado ni el QR, y AlojaFlow no presenta declaración responsable. La ley fija el 1 de enero de 2027 para sociedades y el 1 de julio de 2027 para el resto (Real Decreto-ley 15/2025); [Hacienda anunció](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu.html) el 5 de octubre de 2026 llevarlo a octubre de 2028, y a la fecha de esta nota no se ha encontrado en el BOE. Se hará cuando esté publicado y lo revise un asesor. **Pregunta abierta para el asesor:** si un sistema que solo da recibos cuenta como sistema de facturación. TicketBAI, que sustituye a VeriFactu con domicilio fiscal vasco, tampoco está.
