# Especificación de AlojaFlow — índice

**Estado:** propuesta para aprobación · 2026-11-03
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
| 02 | `subordinacion.md` | Protocolo con la principal, **formato de la copia** y correspondencia de tablas (regla 10) | 1 | high | pendiente |
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

## 02 · `subordinacion.md` (detalle)

### Parte A — Protocolo

1. **Alcance.** Principales admitidas: HotelDruid 3.0.8 y AlojaFlow 1.2.x, esquema **3.081**
   (congelado, D-011). La versión se lee de `<versione>` en la copia; una versión desconocida se
   rechaza sin cargar nada.
2. **Configuración de una principal:** URL base (solo `https://`), usuario y contraseña
   (cifrados), compresión, frecuencia (15 min a 1 día), zona horaria y moneda de la propiedad.
3. **Llamadas HTTP** **[visto: `interconnessioni.php`, `plugins/multipropiedad/funciones.php`]**.
   Todas a `crea_backup.php` con `nome_utente_phpr` y `password_phpr`:

   | Paso | Parámetros |
   |---|---|
   | Comprobar acceso | solo las credenciales |
   | Crear la copia | `anno=AAAA&azione=SI&crea_backup=SI` |
   | Descargar | `anno=AAAA&azione=SI&mostra_header=NO&salva_backup=SI&compresso=SI\|NO` |
   | Borrar la copia | `anno=AAAA&azione=SI&cancella_backup=SI` |

   Para cada paso: método, respuesta correcta, respuestas de error **[por verificar]**
   (credenciales, espera de 5 s tras un inicio de sesión fallido, falta de privilegios, año
   inexistente, copia en curso), tiempo de espera y tamaño máximo. Qué significa `anno` cuando
   la copia incluye varios años **[por verificar]**.
4. **Seguridad.** La principal recibe la contraseña en la URL: queda en los registros del
   servidor y de proxies. Debe usarse HTTPS con certificado válido y sin seguir redirecciones a
   otro host; comprobar si la principal acepta los mismos parámetros por POST **[por verificar]**;
   recomendar un usuario dedicado con los mínimos privilegios. Nunca se llama a
   `mostra_sorgente.php`.
5. **Ciclo de sincronización:** programación, bloqueo por principal (una sola a la vez),
   descarga a un archivo temporal, verificaciones (completa, versión, tamaño), lectura por
   partes, carga del espejo en una transacción o por tabla sombra con intercambio, proyección al
   modelo propio, detección de eventos, borrado de la copia en la principal, registro de la
   ejecución (duración, filas, errores) y limpieza de temporales.
6. **Errores y recuperación:** principal caída, credenciales malas, copia truncada o corrupta,
   versión desconocida, disco lleno, tiempo agotado. Siempre se conserva la última carga buena;
   los avisos llegan al administrador.

### Parte B — Formato de la copia

1. **Estructura general** **[visto: copia de demostración 3.081]**: empieza con
   `<?php exit(); ?>` (para que no se pueda leer por web), sigue con comentarios con la fecha y
   una raíz `<backup>` con `<versione>`, `<log>`, bloques `<file>` (`<nomefile>`, `<contenuto>`)
   con archivos de configuración de `dati/` escritos en PHP, y bloques `<tabella>` con
   `<righetabella>` y `<riga>`.
2. **No es XML válido** **[por verificar]**: el contenido trae PHP y HTML sin escapar. Se lee con
   un analizador propio por partes; **nunca se incluye ni se ejecuta** (regla 11).
3. **Tablas:** globales y por año, columnas, nulos, tipos, prefijo de tablas.
4. **Escapes y codificación:** UTF-8, caracteres especiales, saltos de línea, separadores.
5. **Compresión** (`compresso=SI`): algoritmo **[por verificar]**.
6. **Valores de configuración** que se sacan de los `<file>` (idioma, nombre de la unidad,
   moneda…) y cómo se leen sin ejecutar PHP.
7. **Copia de documentos** (`hoteld_doc_backup.php`, contratos): si hace falta y cómo.
8. **Copias sintéticas** para las pruebas versionadas.

### Parte C — Correspondencia HotelDruid 3.081 → modelo propio

1. **Tablas globales** **[visto: `docs/architecture/database.md` de 1.2.x]**: `appartamenti` →
   unidades; `clienti`, `relclienti` → huéspedes; `nazioni`, `regioni`, `citta`, `parentele`,
   `documentiid` → catálogos; `personalizza` → configuración de la propiedad; `casse`,
   `transazioni` → caja (lectura); `contratti`, `descrizioni`; `anni`, `versioni`.
   No se importan: `utenti`, `privilegi`, `sessioni`, `cache`, `interconnessioni`,
   `transazioniweb`.
2. **Tablas por año** **[visto: `creadb.php`]**: `prenota` → reservas; `prenotacanc` →
   cancelaciones; `rclientiprenota` → huéspedes de la reserva; `costi`, `costiprenota` →
   cargos (de aquí salen las limpiezas); `soldi` → pagos; `periodi`, `ntariffe`, `regole` →
   tarifas y reglas (solo lectura en la fase 1).
3. **Claves estables:** el identificador de reserva es por año → clave (propiedad, año, id).
   Una reserva que aparece en dos tablas de año cuenta una vez **[visto: plugin `kpi`]**.
   Qué pasa si la principal reutiliza un identificador borrado **[por verificar]**.
4. **Casos especiales:** campos personalizados de cliente (tipo de cliente), costo «Limpieza»
   (`associasett_ca`, `numsett_ca`), bloqueos iCal creados por el plugin de la principal
   (cliente «Bloqueo iCal», comentario `[ical]`), reservas de grupo de 1.2.x.
5. **Conversiones:** fechas y periodos, importes decimales → enteros en centavos, moneda, zona
   horaria.

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
