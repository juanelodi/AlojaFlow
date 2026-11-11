# AlojaFlow — documento de producto

**Estado:** aprobado · versión 1.0 · 2026-11-03
Decisiones citadas como D-NNN: [decisiones.md](decisiones.md). Especificación:
[especificacion/README.md](especificacion/README.md).

---

## 1. Resumen

AlojaFlow es un sistema de gestión de alojamientos (PMS) web, de código visible (FSL-1.1-ALv2,
D-174), para alojamientos pequeños —casas de huéspedes, hoteles pequeños, cabañas,
campings, departamentos de renta vacacional— y para propietarios con varias propiedades.
Se instala en un hosting propio o se contrata como servicio alojado, sin cuota por habitación.
Funciona en cualquier país mediante **paquetes de país**; el idioma base es el español.

La **fase 1** se instala junto a una instalación existente de HotelDruid o AlojaFlow 1.2.x, lee
su copia de datos y ofrece encima las funciones nuevas, sin tocarla. La **fase 2** añade un núcleo
propio de reservas, tarifas y clientes para funcionar sin HotelDruid. Desde la 1.0.3 (D-035) ya hay
una primera parte de ese núcleo: al crear una propiedad se elige si es **independiente** o
**subordinada**.

## 2. Problema

- Los PMS comerciales (Little Hotelier, Cloudbeds, Smoobu, Lodgify…) cobran cuotas mensuales
  por habitación o propiedad, y los datos quedan en la nube del proveedor.
- HotelDruid y AlojaFlow 1.2.x tienen lógica probada (tarifas, disponibilidad, copias), pero una
  base técnica vieja: PHP sin framework, más de 50 pantallas heredadas, y plugins que guardan
  sus datos en archivos fuera de la base de datos y fuera de la copia de seguridad.
- Cambiar de sistema de golpe es arriesgado para un alojamiento que ya opera todos los días.
  Por eso la fase 1 convive con HotelDruid en lugar de sustituirlo.

## 3. Para quién

**Segmento:** alojamientos independientes pequeños (hasta unas decenas de unidades) y
propietarios o administradoras con varias propiedades. Mercados de la fase 1: México, España,
Estados Unidos y Latinoamérica (D-006).

| Usuario | Qué necesita | Dónde |
|---|---|---|
| Dueño / gerente | Ocupación, cobros e indicadores de todas sus propiedades; configurar | PC y móvil |
| Recepción | Llegadas, salidas, saldos, mensajes a huéspedes, cierre del día | PC del mostrador |
| Limpieza / mantenimiento | Qué habitaciones limpiar hoy, marcar estados, reportar incidencias | Móvil |
| Contador | Estados de cuenta y exportaciones | PC |
| Huésped | Llenar su registro antes de llegar, firmar el contrato, recibir mensajes en su idioma | Móvil |
| Operador del servicio | Instalar, actualizar, respaldar y dar soporte a cada cliente | Panel |

Uso repartido entre PC y móvil, más o menos a partes iguales.

## 4. Propuesta de valor y posicionamiento

- **Código a la vista y sin cuota por habitación.** Todo el código se publica bajo la FSL y pasa a
  Apache 2.0 a los dos años; quien lo instala en su alojamiento no paga licencia.
- **Los datos son del alojamiento.** Autoalojable en hosting compartido, exportable, y todo
  —también lo de cada módulo— dentro de la base de datos y de la copia de seguridad.
- **Se adopta sin migrar.** Quien ya usa HotelDruid instala AlojaFlow al lado y lo aprovecha
  desde el primer día; la migración completa llega con la fase 2.
- **Cumplimiento local.** Registro de viajeros, impuestos y privacidad por país. Por ahora no se factura (D-131).
- **En el idioma de cada quien.** Personal y huéspedes eligen su idioma por separado.

**Qué no es en la fase 1:** no crea ni modifica reservas, no es un channel manager por API, no
es un motor de reservas y no procesa pagos.

## 5. Modelo de negocio

FSL más servicios: servicio alojado (una instalación por cliente, D-005), instalación,
migración de datos, soporte, formación y personalizaciones, y licencias comerciales para quien
quiera ofrecer un servicio que compita (D-174). **Precios: pendiente.**

Costos de terceros que paga el alojamiento cuando los usa: envío de
correo, dominio y hosting; más adelante, channel manager y pasarela de pagos.

## 6. Principios de producto

1. **El turno de recepción manda.** Lo frecuente (llegadas, salidas, saldos, disponibilidad) a
   uno o dos clics y legible de un vistazo.
2. **Nada de sorpresas con el dinero ni con las reservas.** Estados, saldos y choques de
   reservas siempre visibles. En la fase 1, la **antigüedad de los datos** («actualizado hace
   7 min») se ve en todas las pantallas.
3. **Igual en el PC del mostrador que en el móvil.**
4. **Todo en la base de datos y en la copia de seguridad.** Ningún módulo guarda datos en
   archivos sueltos fuera del respaldo.
5. **Cumplimiento por paquete de país**, nunca con excepciones sueltas en el código.
6. **Accesible:** contraste AA, foco visible, objetivos táctiles cómodos y texto legible para
   personas no técnicas en jornadas largas.

## 7. Fases

| Fase | Qué es | Incluye |
|---|---|---|
| **1 — Subordinada** | AlojaFlow lee la copia de una o varias principales (HotelDruid 3.0.8 / AlojaFlow 1.2.x) y nunca escribe en ellas | Los 11 módulos de §8 |
| **2 — Núcleo propio** | AlojaFlow funciona sin HotelDruid | Reservas (alta, cambio, arrastrar y soltar), tarifas, temporadas y restricciones, clientes, cobros; grupos, cupones, fusionar clientes y anonimizar en origen (D-009); motor de reservas directas; pasar de subordinada a independiente. **Hecha en la 1.0.5** |
| **Posteriores** | Distribución | Channel manager por API (p. ej. Channex), feed de Google Hotel, PWA, más paquetes de país. Los pagos en línea (Stripe / Mercado Pago) se adelantaron a la 1.0.5, sin probar con cuentas reales |

## 8. Alcance de la fase 1

### 8.1 Cómo funciona

```
Principal (HotelDruid 3.0.8 / AlojaFlow 1.2.x)
   │  HTTPS, cada 15 min a 1 día: crear copia → descargar → borrar
   ▼
AlojaFlow
   ├─ Lector de la copia (texto, nunca se ejecuta) → tablas espejo      [solo la sincronización]
   ├─ Proyección al modelo propio: propiedades, unidades, reservas…    [solo lectura en fase 1]
   └─ Datos propios: limpieza, check-in, contratos, plantillas, bloqueos iCal…
                                                                       [la sincronización no los toca]
```

- Cada principal es una **propiedad**: varias principales = multipropiedad.
- Las reservas, tarifas y clientes se cambian **en la principal**; AlojaFlow enlaza a la
  reserva en la principal.
- Los datos propios se ligan a las reservas de la principal por una clave estable (propiedad,
  año e identificador), definida en la especificación.
- Al comparar una sincronización con la anterior, AlojaFlow detecta **eventos** (reserva nueva,
  modificada, cancelada, faltan N días para la llegada…) que alimentan la comunicación y las
  automatizaciones.

### 8.2 Módulos de la fase 1

Unifican el catálogo de [INICIO.md](INICIO.md) §5 con los 19 plugins de AlojaFlow 1.2.x (D-008).
Las funciones repetidas quedan en un solo módulo.

| # | Módulo | Qué hace | Unifica |
|---|---|---|---|
| M1 | **Propiedades y sincronización** | Alta de principales (URL, usuario, contraseña cifrada), sincronización programada y manual, estado, antigüedad de los datos y avisos de fallo | Subordinación, `multipropiedad` |
| M2 | **Calendario y disponibilidad** | Calendario por habitaciones en modo consulta, todas o una propiedad; color por estado y por tipo de cliente; bloqueos iCal; búsqueda de unidades libres por fechas y personas; plano gráfico de ocupación | Catálogo, `multipropiedad`, `tipo_cliente`, `mapa` |
| M3 | **Hoy y cierre del día** | Llegadas, salidas y alojados con teléfono, saldo y estado; lista de comprobación del cierre, caja del día y cierre guardado con notas | `hoy`, `cierre`, vista Hoy de `multipropiedad` |
| M4 | **Limpieza y mantenimiento** | Estado de cada unidad desde el móvil; rotaciones y asignación; limpiezas durante la estancia (costo «Limpieza» de la principal y reglas fijas propias); aviso de entradas, salidas y limpiezas para WhatsApp; incidencias y tareas de mantenimiento con fotos | Catálogo, `limpieza`, `aviso_limpieza` |
| M5 | **Huéspedes y llegada en línea** | Ficha del huésped (de la copia) con documentos, preferencias y notas propias; pre-check-in en línea con enlace firmado; contrato y aviso de privacidad con versión, huella y aceptación con OTP; registro de viajeros del país | Catálogo, `precheckin`, aceptación de contrato de `grupos` |
| M6 | **Comunicación y automatización** | Plantillas por idioma con variables; correos automáticos por evento; mensajes de WhatsApp con texto listo (enlace); webhooks firmados; historial de envíos | Catálogo, `mensajes`, `automatizacion` |
| M7 | **Canales iCal** | Un calendario iCal por unidad para los portales (sin datos personales); importación de los calendarios de Airbnb, Booking.com y Vrbo como bloqueos propios de AlojaFlow; aviso de choques con las reservas de la principal | Catálogo, `ical` (adaptado a solo lectura) |
| M8 | **Dinero** | Estado de cuenta de cada reserva (total, pagado, pendiente); políticas de cancelación, no-show y depósito (calculadora, depósitos pendientes, marca de no-show propia); recibo en PDF de cada pago (D-131) | Catálogo, `politicas`, `facturas_csv` |
| M9 | **Informes e indicadores** | Ocupación, ADR, RevPAR, ingresos por canal (netos de comisión), por unidad, contra el año anterior, reservas a futuro, cobros, estancia y anticipación, día de la semana, cancelaciones, limpieza, huéspedes; por propiedad y total; CSV | Catálogo, `kpi`, estadísticas de `multipropiedad` |
| M10 | **Privacidad** | Exportar todos los datos de un huésped; anonimizar o borrar los datos propios; plazos de conservación (sobre todo documentos de identidad); avisos de privacidad por país | `rgpd` (la parte que no escribe en la principal) |
| M11 | **Plataforma** | Instalador web; usuarios, roles y permisos por propiedad; 2FA; registro de auditoría; idiomas, zonas horarias y formatos; copias de seguridad y actualizaciones desde el panel; enlace «Código fuente»; «Acerca de» con créditos | Catálogo, `doble_factor`, `auditoria` |

### 8.3 Fuera de la fase 1

| Función | Por qué | Cuándo |
|---|---|---|
| Crear o cambiar reservas, arrastrar y soltar, tarifas, temporadas, restricciones | Escriben reservas | Fase 2 |
| Grupos, cupones, fusionar clientes, anonimizar en la principal, bloqueos iCal dentro de la principal | Escriben en HotelDruid (D-009). Mientras tanto se siguen usando los plugins de la principal 1.2.x | Fase 2 |
| Registrar cobros y pagos | Escriben en la caja de la principal | Fase 2 |
| Motor de reservas directas, pagos en línea | Necesitan el núcleo propio | Fase 2 / posterior |
| Channel manager por API, feed de Google Hotel | Distribución de pago o con requisitos propios | Posterior |
| Inventario, almacenes y punto de venta de HotelDruid | No están en el catálogo | **Fuera** (D-117) |

### 8.4 Paquetes de país de la fase 1 (D-006)

Resumen inicial. **Cada requisito se verifica con fuentes oficiales** en su documento de país
antes de programarlo.

| | México | España | Estados Unidos | Genérico |
|---|---|---|---|---|
| Registro de viajeros | Sin registro nacional conocido; revisar requisitos estatales | SES.Hospedajes (Ministerio del Interior) | Sin registro nacional; revisar ordenanzas locales | Ficha exportable |
| Impuestos al hospedaje | IVA e impuesto estatal sobre hospedaje (tasa por estado) | IVA de alojamiento y tasas turísticas autonómicas o municipales donde existan | Impuestos de ocupación y de ventas por estado, condado y ciudad, en capas | Capas configurables |
| Privacidad | LFPDPPP (verificar la versión vigente) | RGPD y LOPDGDD | CCPA/CPRA y leyes estatales | Aviso editable |
| Formatos | es-MX, MXN | es-ES, EUR | en-US, USD, fecha MM/DD/AAAA, reloj de 12 h | Por propiedad |

### 8.5 Idiomas (D-007)

Español (fuente), inglés, francés, italiano, portugués y **alemán** (sugerido: es uno de los
mercados emisores de turismo más grandes de Europa). Cada usuario elige el idioma de su
interfaz; cada huésped recibe páginas, contratos y mensajes en el suyo. Las traducciones las
revisa un hablante nativo antes de publicarse. Candidato posterior: catalán (España).

### 8.6 Convivencia con la principal

Algunas funciones existen también como plugins en AlojaFlow 1.2.x. Si están activos en la
principal y en AlojaFlow a la vez, el huésped recibiría dos correos o un portal vería dos
calendarios. La especificación define, función por función, cuál se apaga en la principal al
activarla en AlojaFlow (`mensajes`, `automatizacion`, `ical`, `precheckin`, `limpieza`…) y
cómo se reconocen los bloqueos iCal que ya creó la principal.

## 9. Entregas propuestas dentro de la fase 1

La fase 1 es grande. Se propone entregarla en cuatro partes, cada una instalable y útil por sí
misma. Las entregas tienen nombre y no número, para no confundirlas con las versiones (D-031).
Cada entrega sale en varias versiones 1.0.x (D-030).

| Entrega | Módulos | Resultado |
|---|---|---|
| **Base** | M11, M1, M2, M3, M9 básico (ocupación, ADR, RevPAR, ingresos por canal) | Ver todas las propiedades, su calendario, el día y los indicadores principales |
| **Operación** | M4, M6, M7 | Limpieza desde el móvil, mensajes y correos automáticos, iCal con aviso de choques |
| **Huéspedes** | M5, M10 | Llegada en línea, contrato con OTP, registro de viajeros, privacidad |
| **Dinero** | M8, M9 completo | Recibos, políticas, todos los informes |

## 10. Métricas de éxito (propuestas)

- **Datos al día:** más del 99 % de sincronizaciones correctas; antigüedad media por debajo de
  la frecuencia configurada.
- **Sin dobles reservas sin aviso:** todo choque entre un calendario iCal y la principal se
  avisa en la siguiente sincronización.
- **Llegada en línea:** porcentaje de llegadas con el pre-check-in completo (medirlo desde el
  primer día).
- **Instalación:** de cero a la primera sincronización en hosting compartido en menos de
  30 minutos.
- **Comunicación:** correos enviados frente a fallidos, por plantilla.

## 11. Restricciones

- FSL-1.1-ALv2 (D-174); solo dependencias permisivas (regla 3); nada que exija comprar licencias.
- Hosting compartido: PHP 8.3, MySQL 8.4 / MariaDB 11.4, un solo cron, sin Redis ni procesos
  permanentes, límites de memoria y de tiempo. La copia de una principal real pesa unos 10 MB
  y debe leerse por partes.
- Sin SQLite y sin CDN en tiempo de ejecución.
- La principal no se modifica: ni datos, ni código, ni configuración.

## 12. Riesgos

| Riesgo | Mitigación |
|---|---|
| Decidir con datos viejos (la copia se descarga cada cierto tiempo) | Antigüedad visible en todas las pantallas; avisos si la sincronización falla o se retrasa |
| El protocolo de HotelDruid manda usuario y contraseña en la URL (GET) | HTTPS obligatorio; comprobar si la principal acepta POST; usuario dedicado con los mínimos privilegios; credenciales cifradas |
| Copias grandes en hosting compartido | Lectura por partes, carga por lotes, límites de tamaño y tiempo |
| Reservas borradas o con identificador reutilizado en la principal dejan huérfanos los datos propios | Clave estable y reglas de conciliación en la especificación; nunca borrar datos propios en automático |
| Funciones duplicadas entre la principal 1.2.x y AlojaFlow | Guía de convivencia (§8.6) |
| Obligaciones legales por país (SES.Hospedajes, privacidad) | Documento por país con fuentes oficiales; revisión con un asesor antes de publicar cada paquete |
| iCal tiene retrasos (los portales leen cada cierto tiempo) | Avisar al usuario, sincronizar a menudo, detectar choques |
| Documentos de identidad de huéspedes | Cifrados en reposo, fuera de la carpeta pública, con plazo de conservación |
| Alcance grande de la fase 1 | Entregas de §9 |
| Calidad de las traducciones | Revisión nativa antes de publicar |

## 13. Pendiente de decidir

- Precios y modelo de venta.
- Titular del copyright en las cabeceras (nombre legal o razón social).
- Proveedor de correo y canal del OTP (correo; WhatsApp o SMS si hay proveedor).
- Inventario, almacenes y punto de venta: fase 2 o posterior.
