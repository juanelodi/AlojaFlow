# Especificación de AlojaFlow — índice

**Estado:** índice aprobado · 2026-11-03
Producto: [../producto.md](../producto.md) · decisiones: [../decisiones.md](../decisiones.md)

## Convenciones

- **«Debe»** es obligatorio; **«debería»** es recomendado; **«puede»** es opcional.
- Ejemplos solo con datos ficticios: nunca datos de copias reales (regla 12 del `CLAUDE.md`).
- Lo que se sabe por haber leído el código o las copias de HotelDruid se marca **[visto]**, con el
  archivo de origen; lo que falta comprobar, **[por verificar]**.
- Cada documento indica su estado: *pendiente*, *borrador* o *aprobado* (con fecha).

## Documentos

| # | Documento | Contenido | Fase | Esfuerzo | Estado |
|---|---|---|---|---|---|
| 00 | `glosario.md` | Términos (principal, subordinada, copia, espejo, proyección, propiedad, unidad, reserva, estancia…) y equivalencias italiano → español de HotelDruid | 1 | medium | pendiente |
| 01 | `arquitectura.md` | Módulos M1–M11 en Laravel, capas, paquetes de país, eventos, colas y programador en hosting compartido, archivos, correo | 1 | high | pendiente |
| 02 | [`subordinacion.md`](subordinacion.md) | Protocolo con la principal, **formato de la copia** y correspondencia de tablas (regla 10) | 1 | high | borrador 2026-11-03 |
| 03 | `modelo-datos.md` | Modelo de datos propio de las fases 1 y 2 | 1–2 | xhigh | pendiente |
| 04 | `pantallas-fase-1.md` | Mapa de navegación y ficha de cada pantalla de la fase 1 | 1 | medium | pendiente |
| 05 | `paises/` | `mexico.md`, `espana.md`, `estados-unidos.md`, `generico.md` | 1 | high | pendiente |
| 06 | `seguridad-privacidad.md` | Autenticación, 2FA, permisos por propiedad, auditoría, cifrado, retención de datos personales | 1 | high | pendiente |
| 07 | `idiomas-formatos.md` | Seis idiomas, traducción y revisión, formatos por propiedad, zonas horarias, monedas | 1 | medium | pendiente |
| 08 | `comunicacion-eventos.md` | Eventos derivados de la sincronización, plantillas, correo, WhatsApp, webhooks, OTP | 1 | high | pendiente |
| 09 | `ical.md` | Exportar e importar, bloqueos propios, choques, convivencia con el plugin `ical` de la principal | 1 | high | pendiente |
| 10 | `instalacion-actualizaciones.md` | Paquete para hosting compartido, instalador, cron, actualizaciones desde el panel, enlace «Código fuente» | 1 | high | pendiente |
| 11 | `pruebas.md` | Estrategia, base MySQL de pruebas, copias sintéticas, copias reales fuera del repositorio, CI | 1 | medium | pendiente |
| 12 | `convivencia-principal.md` | Qué plugin de la principal 1.2.x apagar al activar cada módulo de AlojaFlow | 1 | medium | pendiente |
| — | `reservas-tarifas-disponibilidad.md`, `motor-reservas.md`, `migracion-hoteldruid.md` | Núcleo propio | 2 | xhigh | más adelante |

**Orden de redacción propuesto:** 00 → 02 → 03 → 01 → 04, y después el resto. El protocolo y el
formato de la copia van antes del modelo de datos porque lo condicionan; la arquitectura se
cierra cuando el modelo está claro.

---

## 02 · `subordinacion.md`

Redactado: [subordinacion.md](subordinacion.md) (borrador 2026-11-03). Partes: A protocolo, B formato
de la copia, C correspondencia con el modelo propio, D pruebas, y la lista de preguntas abiertas
que se resuelven con una principal real antes de programar.

---

## 03 · `modelo-datos.md` (detalle)

1. **Principios:** claves foráneas e índices siempre; dinero en enteros (centavos) con moneda
   ISO 4217; noches como fechas (`date`); fecha y hora en UTC con la zona horaria de la
   propiedad; nombres en español (convenciones del `CLAUDE.md`); borrado lógico solo donde haga
   falta, no por costumbre.
2. **Tres capas de datos** (propuesta, D-013):
   - **Espejo:** tablas `hd_*` con los nombres de HotelDruid; solo las escribe y lee la
     sincronización.
   - **Proyección:** tablas del modelo propio (`propiedades`, `unidades`, `reservas`,
     `huespedes`…) con `origen = hoteldruid`; solo lectura en la fase 1. En la fase 2 se añaden
     filas con `origen = alojaflow` sin reescribir los módulos.
   - **Propios:** limpieza, check-in, contratos, facturas, plantillas, bloqueos iCal…; la
     sincronización nunca los toca. Se ligan a la proyección por la clave estable.
3. **Entidades por dominio:**
   - Plataforma: usuarios, roles y permisos por propiedad, auditoría, ajustes, idioma.
   - Propiedades: propiedad (en la fase 1, una por principal), tipo de unidad, unidad, plano.
   - Sincronización: principal, ejecución, espejo.
   - Reservas: reserva, noches, huéspedes de la reserva, estado, origen o canal, cancelación.
   - Huéspedes: huésped, documento de identidad, preferencias, consentimientos.
   - Llegada en línea y documentos legales: pre-check-in, plantilla legal (versión, huella),
     aceptación (fecha, IP, navegador, OTP).
   - Limpieza y mantenimiento: estado de la unidad, tarea, incidencia, regla de limpieza fija,
     asignación.
   - Comunicación: plantilla por idioma, regla de automatización, envío, webhook.
   - Canales: calendario exportado, fuente importada, bloqueo, choque.
   - Dinero: estado de cuenta (proyección), política, factura, serie y folio, impuesto, paquete
     de país.
   - **Fase 2** (se diseñan ahora, se construyen después): tarifa, temporada, plan tarifario,
     restricción, disponibilidad, cobro, grupo, cupón.
4. **Diagrama entidad-relación.**
5. **Integridad y concurrencia:** sobre todo disponibilidad y doble reserva, pensando ya en la
   fase 2.
6. **Retención y borrado** de datos personales por país.

---

## 04 · `pantallas-fase-1.md` (detalle)

Cada pantalla lleva: objetivo, quién la usa, datos (de la copia o propios), acciones, estados
(vacía, cargando, error, datos viejos), comportamiento en móvil, accesibilidad, y si es de
Filament o propia con Livewire (D-010).

| Grupo | Pantallas | Tecnología |
|---|---|---|
| Acceso | Inicio de sesión, 2FA, recuperar contraseña | Fortify + Livewire |
| Operación | Resumen de propiedades, Hoy, Cierre del día, Calendario por habitaciones, Disponibilidad, Plano de ocupación, Reserva (consulta con enlace a la principal y datos propios), Huésped, Limpieza (móvil), Mantenimiento, Aviso de entradas y salidas, Mensajes, Choques iCal, Facturas, Informes, Estado de la sincronización | Livewire propio |
| Administración | Propiedades y principales, Unidades (fotos, plano), Usuarios y roles, Plantillas, Automatizaciones y webhooks, Calendarios iCal, Políticas, País e impuestos, Facturación (certificados, PAC, series), Documentos legales, Idiomas, Copias de seguridad, Actualizaciones, Auditoría | Filament |
| Huésped (públicas) | Pre-check-in, Firma del contrato con OTP, Aviso de privacidad | Livewire propio |
| Comunes | «Acerca de» y «Código fuente», selector de propiedad, indicador de antigüedad de los datos | Ambas |

Incluye además el **mapa de navegación** por rol y los **tokens de diseño** de la marca
(`#0F766E`, `#F59E0B`, `#0B1F2A`) compartidos entre Filament y las pantallas propias.

---

## Resto de documentos (resumen)

- **01 Arquitectura:** un módulo de Laravel por cada M1–M11; interfaces de país
  (`Facturador`, `RegistroViajeros`, `CalculadoraImpuestos`, `AvisoPrivacidad`) con sus
  implementaciones; eventos de dominio que salen de la sincronización; colas `database` y un solo
  cron; archivos privados (documentos de identidad cifrados, fuera de `public/`); correo;
  configuración por propiedad.
- **05 Países:** por país, facturación, registro de viajeros, impuestos al hospedaje,
  privacidad, documentos de identidad, textos legales, formatos, proveedores externos
  necesarios, obligaciones del productor del software y fuentes oficiales con fecha de consulta.
- **06 Seguridad y privacidad:** amenazas principales (credenciales de la principal, enlaces
  públicos firmados, subida de documentos y fotos, webhooks, OTP), cifrado, límites de intentos,
  auditoría, conservación y borrado.
- **07 Idiomas y formatos:** archivos de traducción, idioma del usuario y del huésped, revisión
  nativa, formatos de fecha, número y moneda, zonas horarias.
- **08 Comunicación y eventos:** catálogo de eventos (reserva nueva, modificada, cancelada,
  faltan N días, pasaron N días, llegada, salida), cómo se detectan al comparar
  sincronizaciones y sus límites, plantillas y variables, envío y reintentos, webhooks con
  firma HMAC, OTP.
- **09 iCal:** exportación por unidad con token, importación por fuente, bloqueos propios,
  detección de choques con la principal, frecuencias, qué hacer si un portal no responde.
- **10 Instalación y actualizaciones:** contenido del paquete, instalador web, requisitos,
  cron, actualizaciones con copia previa y vuelta atrás, descarga del código de la versión
  instalada (AGPL).
- **11 Pruebas:** Pest contra MySQL/MariaDB, copias sintéticas versionadas, ruta de las copias
  reales por variable de entorno, pruebas de la lectura de la copia y de la proyección, CI en
  GitHub Actions.
- **12 Convivencia con la principal:** tabla plugin de 1.2.x ↔ módulo de AlojaFlow, qué apagar,
  cómo reconocer lo que ya hace la principal (bloqueos iCal, mensajes, limpieza).
