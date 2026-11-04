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
| Impuesto sobre hospedaje | La tasa cambia por estado; se da de alta en **Impuestos** sin código `002`, para que vaya como impuesto local | Contador |
| Código de IVA | Para que un impuesto salga como IVA federal, su campo **Código** debe ser `002` | Contador |
| Plazos de conservación | 30 días para las fotos de identificaciones, 1 año para el registro y 5 años para las aceptaciones son una propuesta | Abogado |

## Para timbrar facturas

El XML está preparado pero **no tiene validez fiscal** hasta que un PAC lo selle y lo timbre, y el PDF lo
dice en grande. Hace falta:

1. Contratar un PAC y tener el certificado de sello digital (CSD) del emisor.
2. Programar su conector: implementar `App\Facturacion\ConectorPac` y registrarlo en
   `config('alojaflow.pac')`.
3. Validar el XML generado contra el XSD del SAT, y hacer una factura de prueba en el ambiente de pruebas
   del PAC. **Esto todavía no se ha hecho:** el XML solo se ha revisado con pruebas propias de estructura.

## Fuera por ahora

Factura global a público en general (nodo `InformacionGlobal`), complementos de pago para método PPD, notas de
crédito y retenciones.
