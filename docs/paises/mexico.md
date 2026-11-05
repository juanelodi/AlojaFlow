# Paquete de México

Es el paquete principal de AlojaFlow. **Nada de esto es asesoría legal ni fiscal:** son textos y formatos
de partida. Antes de usarlos con huéspedes reales, revísalos con un abogado y un contador.

## Qué trae

- **Identificaciones:** INE, pasaporte, licencia, forma migratoria (FMM) y otra.
- **Tarjeta de registro:** nombre, nacionalidad, domicilio, identificación, ciudad de procedencia, motivo
  del viaje y placas. No se conoce un registro nacional de viajeros: es la tarjeta del propio hotel.
- **Textos de partida:** aviso de privacidad, condiciones de hospedaje, reglamento y política de cancelación.
- **Recibos sin valor fiscal.** Por ahora no se hacen CFDI (D-131).

## Por revisar antes de usarlo en real

| Tema | Qué revisar | Con quién |
|---|---|---|
| Aviso de privacidad | Sigue el esquema de la LFPDPPP (responsable, datos, finalidades, ARCO). En 2025 se publicó una ley nueva: hay que confirmar los requisitos vigentes | Abogado |
| Condiciones de hospedaje | Requisitos de la Ley Federal de Protección al Consumidor y de PROFECO para contratos de hospedaje | Abogado |
| Registro de huéspedes | Requisitos del estado o municipio del alojamiento | Abogado o asociación hotelera |
| Impuesto sobre hospedaje | La tasa cambia por estado; se da de alta en **Impuestos** | Contador |
| Plazos de conservación | 30 días para las fotos de identificaciones, 1 año para el registro y 5 años para las aceptaciones son una propuesta | Abogado |

## Cobros en pesos

Con Stripe, en **Motor de reservas**, y además activados en el panel de Stripe: **OXXO**, **meses sin intereses** (cuentas mexicanas, tarjetas de crédito de consumidor, con comisión aparte) y **transferencia SPEI** (Stripe da una CLABE para cada cliente, con su nombre y correo). Mercado Pago los ofrece en su propia pantalla, sin ajustes aquí.

Quien paga en una tienda o desde su banco queda **en espera**: no es un pago, pero sus noches siguen apartadas lo que dure la ficha (72 horas si es transferencia), y nunca después del día de llegada. Se confirma sola cuando la pasarela avisa, así que **hace falta el aviso (webhook) configurado**. Stripe tarda hasta un día hábil en avisar un pago de OXXO, según su documentación.

**Lo que está comprobado:** con respuestas simuladas. Ningún cobro real ni de prueba con cuenta verdadera.
