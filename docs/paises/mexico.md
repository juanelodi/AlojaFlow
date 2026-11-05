# Paquete de México

Es el paquete principal de AlojaFlow. **Nada de esto es asesoría legal ni fiscal:** son textos y formatos
de partida. Antes de usarlos con huéspedes y facturas reales, revísalos con un abogado y un contador.

## Qué trae

- **Identificaciones:** INE, pasaporte, licencia, forma migratoria (FMM) y otra.
- **Tarjeta de registro:** nombre, nacionalidad, domicilio, identificación, ciudad de procedencia, motivo
  del viaje, placas y RFC. No se conoce un registro nacional de viajeros: es la tarjeta del propio hotel.
- **Textos de partida:** aviso de privacidad, condiciones de hospedaje, reglamento y política de cancelación.
- **Facturación:** CFDI 4.0 en XML, con IVA en traslados e ISH en el complemento de impuestos locales.

## Por revisar antes de usarlo en real

| Tema | Qué revisar | Con quién |
|---|---|---|
| Aviso de privacidad | Sigue el esquema de la LFPDPPP (responsable, datos, finalidades, ARCO). En 2025 se publicó una ley nueva: hay que confirmar los requisitos vigentes | Abogado |
| Condiciones de hospedaje | Requisitos de la Ley Federal de Protección al Consumidor y de PROFECO para contratos de hospedaje | Abogado |
| Registro de huéspedes | Requisitos del estado o municipio del alojamiento | Abogado o asociación hotelera |
| Claves del SAT | `ClaveProdServ` 90111800 y `ClaveUnidad` E48 son una propuesta. Confirmar también el régimen y el uso de CFDI | Contador |
| Impuesto sobre hospedaje | La tasa cambia por estado; se da de alta en **Impuestos** con otro código, por ejemplo `ISH`, para que vaya como impuesto local | Contador |
| Código de IVA | Para que un impuesto salga como IVA federal, su campo **Código** debe ser `002` o `IVA` | Contador |
| Plazos de conservación | 30 días para las fotos de identificaciones, 1 año para el registro y 5 años para las aceptaciones son una propuesta | Abogado |

## Para timbrar facturas

El XML **no tiene validez fiscal** hasta que un PAC lo selle y lo timbre, y el PDF lo dice en grande. Hace falta:

1. Crear una cuenta en [SW Sapien](https://sw.com.mx/) y subir ahí el certificado de sello digital (CSD) del emisor.
   AlojaFlow no recibe ni guarda el certificado: usa su servicio de emisión, que sella con el que tiene la cuenta.
2. En **Datos fiscales → Conectar PAC**, pegar el token de la cuenta. Solo se guarda si funciona. El **modo** de la
   entidad decide si habla con el ambiente de pruebas de SW o con el real.
3. Hacer una factura de prueba en el ambiente de pruebas.

Para otro PAC: implementar `App\Facturacion\ConectorPac` y agregarlo a `FabricaPac`.

**Lo que está comprobado (2026-11-05):** un CFDI generado valida contra `cfdv40.xsd` e `implocal.xsd` del SAT, salvo
`NoCertificado`, que pone el PAC. Las claves 90111800 y 90111802 existen las dos en el catálogo `c_ClaveProdServ`:
cuál corresponde sigue siendo pregunta para el contador. Sin token, las rutas de timbrar, cancelar y saldo de SW contestan en sus dos ambientes con el error de
credenciales y el formato esperados. **Lo que no:** nunca se ha timbrado de verdad: la conexión con SW se programó con
su SDK público y se probó con respuestas simuladas. El XSD no revisa las reglas de negocio del SAT
(la matriz de errores), y esas solo se ven al timbrar.

**Cosas del SAT y de SW que conviene tener presentes:** un CFDI debe timbrarse dentro de las 72 horas de su fecha: después el PAC lo rechaza y hay que emitir otra. Si se corta la conexión justo al timbrar, la factura puede haber quedado timbrada en SW aunque aquí no se vea: revisar en su portal antes de repetirlo.

## Cobros en pesos

Con Stripe, en **Motor de reservas**, y además activados en el panel de Stripe: **OXXO**, **meses sin intereses** (cuentas mexicanas, tarjetas de crédito de consumidor, con comisión aparte) y **transferencia SPEI** (Stripe da una CLABE para cada cliente, con su nombre y correo). Mercado Pago los ofrece en su propia pantalla, sin ajustes aquí.

Quien paga en una tienda o desde su banco queda **en espera**: no es un pago, pero sus noches siguen apartadas lo que dure la ficha (72 horas si es transferencia), y nunca después del día de llegada. Se confirma sola cuando la pasarela avisa, así que **hace falta el aviso (webhook) configurado**. Stripe tarda hasta un día hábil en avisar un pago de OXXO, según su documentación.

**Lo que está comprobado:** con respuestas simuladas. Ningún cobro real ni de prueba con cuenta verdadera.

## Fuera por ahora

Factura global a público en general (nodo `InformacionGlobal`), complementos de pago para método PPD, notas de
crédito, retenciones y consultar en el SAT si una cancelación ya se aceptó.
