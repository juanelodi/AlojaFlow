# AlojaFlow 1.2.3: qué hace cada función y cómo lo hace

Documento de referencia para una IA que va a reconstruir estas funciones en el AlojaFlow nuevo
(PHP 8.3 + Laravel + MySQL/MariaDB). Describe **lo agregado sobre HotelDruid 3.0.8**, no el
núcleo heredado (reservas, tarifas, clientes, documentos…).

## Cómo leer este documento

- **Es una descripción del sistema viejo, no un diseño del nuevo.** Las rutas, funciones y
  archivos citados existen en `E:\HotelDruid\alojaflow\` y sirven para consultar el detalle.
- **No copies el código viejo.** Aprovecha la lógica y reescríbela según la regla 4 de
  `INICIO-NUEVA-SESION.md`.
- **Fiabilidad.** Las secciones marcadas **[código]** se contrastaron con el código al escribir
  este documento. Las marcadas **[wiki]** salen de la wiki y las notas de versión del proyecto:
  antes de reproducir un detalle, compruébalo en el plugin correspondiente.
- **"En el sistema nuevo"** son recomendaciones de quien escribe este documento, no decisiones
  tomadas por el dueño del proyecto.

---

## 1. Contexto técnico del sistema viejo

### 1.1 Arquitectura

PHP procedural sin framework. Cada pantalla es un archivo de la raíz que incluye `includes/`.
Nombres en italiano. Datos de ejecución en `dati/`. Plugins en `plugins/<id>/`.

### 1.2 Modelo de datos que usan todas las funciones **[código]**

Hay tablas generales y tablas **por año** (con el año como sufijo).

| Tabla | Contenido | Columnas importantes |
|---|---|---|
| `appartamenti` | Habitaciones | `idappartamenti` (texto, es el nombre), `numpiano`, `maxoccupanti`, `numcasa`, `app_vicini`, `priorita`, `priorita2`, `letto`, `commento` |
| `clienti` | Clientes | `idclienti`, `cognome`, `nome`, `telefono`, `email`, `nazione`, `regione`, `citta`, `documento`, `cod_fiscale`, `partita_iva`, `lingua`, `commento` |
| `relclienti` | Campos personalizados y relaciones del cliente | `idclienti`, `numero`, `tipo`, `testo1`…`testo8` |
| `utenti` | Usuarios | `idutenti`, `nome_utente`, `password`, `salt`, `tipo_pass` |
| `privilegi` | Permisos por usuario y año | `idutente`, `anno`, `priv_vedi_tab`, `priv_mod_prenota`, `priv_ins_clienti`, `priv_inventario`, `casse_consentite` (cadenas de banderas por posición) |
| `personalizza` | Configuración por usuario | `idpersonalizza`, `idutente`, `valpersonalizza` |
| `periodiAAAA` | Periodos y precios del año | `idperiodi`, `datainizio`, `datafine`, columnas de tarifa |
| `prenotaAAAA` | Reservas | `idprenota`, `idclienti`, `idappartamenti`, `iddatainizio`, `iddatafine`, `num_persone`, `tariffa`, `sconto`, `tariffa_tot`, `caparra`, `commissioni`, `pagato`, `metodo_pagamento`, `codice`, `origine`, `commento`, `conferma`, `checkin`, `checkout`, `data_modifica` |
| `prenotacancAAAA` | Reservas canceladas | Las mismas columnas que `prenota` |
| `costiprenotaAAAA` | Costes añadidos de cada reserva | `idprenota`, `tipo`, `nome`, `valore`, `associasett`, `settimane`, `moltiplica`, `idntariffe` |
| `ntariffeAAAA` | Nombres de tarifas y costes | |
| `regoleAAAA` | Reglas de asignación (la regla 1 cierra habitaciones) | |

**Fechas de una reserva** **[código]**: la reserva guarda ids de periodo, no fechas.

- Llegada = `periodi.datainizio` del periodo `iddatainizio`.
- Salida = `periodi.datafine` del periodo `iddatafine`.
- Noches = días entre ambas. Calcular a las 12:00 para evitar errores por cambio de horario.
- `idprenota <= 0` no es una reserva real y se ignora.
- `conferma` empieza por `S` si está confirmada.
- Pendiente = `max(0, tariffa_tot - pagato)`.
- Una reserva puede aparecer en dos tablas de año: al contar se deduplica por habitación +
  llegada + salida.

### 1.3 Datos fuera de la base **[wiki]**

Casi todo lo agregado guarda en archivos dentro de `dati/`, **fuera de la base y fuera del
backup**: `plugin_<id>.json`, `plugins_activos.json`, `doble_factor.php`,
`sesiones_recordadas.json`, `sub_auto.json`, `sub_edicion.json`, y las carpetas `cierres/`,
`auditoria/`, `precheckin/`, `grupos/`, `multipropiedad/`, `actualizacion/`.

**En el sistema nuevo:** guardar esto en tablas propias, para que entre en el backup y no se
dependa de archivos con bloqueo.

### 1.4 Permisos de plugins **[código]**

`hd_plugin_permisos` (`includes/funzioni_plugin.php`) traduce las banderas de `privilegi`.
El usuario con id 1 es administrador y tiene todo. Un empleado necesita fila en `privilegi` para
ese año. Los privilegios parciales (solo propias, solo su grupo) **no cuentan**.

| Permiso | Regla |
|---|---|
| `ver_reservas` | `priv_vedi_tab[1] == s` |
| `ver_telefonos` | `priv_ins_clienti[3] == s` |
| `mod_clientes` | `priv_ins_clienti[1] == s` |
| `mod_reservas` | `priv_mod_prenota[0] == s` |
| `inventario_hab` | `priv_inventario[6] == s` y `priv_inventario[8] == s` |
| `ver_cajas` | `priv_vedi_tab[2] == s` y `casse_consentite[0] != s` |

**En el sistema nuevo:** roles y permisos con nombre en lugar de cadenas por posición.

---

## 2. Infraestructura de plugins

### 2.1 Sistema de plugins **[código + wiki]**

**Qué hace:** añade pantallas y funciones sin tocar el núcleo.

**Cómo:**

- Cada plugin es una carpeta con `plugin.php` (manifiesto), `pagina.php` y, opcionalmente,
  `descarga.php` (respuestas sin HTML: CSV, JSON) y `publico.php` (página sin sesión).
- El manifiesto declara: `nombre`, `menu`, `seccion` (inicio, tablas, clientes, tarifas,
  configurar), `solo_admin`, `requiere` (permisos), `vars` (parámetros POST aceptados),
  `vars_get`, `descarga`, `publico`, `edicion_sub` e `init`.
- Se entra por `plugin.php?p=<id>`. Con `publico=1` no exige sesión; el plugin valida su token.
- Ganchos con `hd_registra_hook`: `inizio_menu` (HTML o tareas bajo el menú) y
  `tabella_prenota_stile` (CSS extra por reserva en la tabla del mes; recibe `idclienti`,
  `idprenota`, `anno`).
- Cada usuario solo ve los botones de lo que puede abrir.

### 2.2 Página de plugins **[wiki]**

**Qué hace:** el administrador activa y desactiva plugins (`gestione_plugin.php`).

**Cómo:**

- Lista en `dati/plugins_activos.json`. Lo escrito en `plugins/attivi.txt` queda fijado y no se
  puede desactivar desde la página. La línea `!solo_servidor` deja solo ese archivo.
- Cambios solo por POST. Solo se aceptan plugins que ya están en `plugins/`: no se sube código
  desde la web. Los desactivados no se ejecutan.
- Los plugins nunca se activan solos. Tras una actualización, el menú avisa de los nuevos y la
  página los marca como «nuevo» (`dati/plugins_vistos.json`).

### 2.3 Utilidades comunes **[código]** (`plugins/comun.php`)

- **Tokens:** `hd_pl_token` crea el secreto del plugin. `hd_pl_subtoken($plugin,$uso)` deriva uno
  por uso: los primeros 32 caracteres de `HMAC-SHA256(uso, secreto)`. Así, filtrar el token del
  feed no da acceso al cron. Se comparan con `hash_equals`.
- **Protección SSRF** (`hd_pl_url_externa_ok`): solo `http`/`https`, sin usuario ni contraseña en
  la URL. Resuelve el host y rechaza si **alguna** IP es privada o reservada. La opción
  `red_local` lo salta, solo para pruebas.
- **Bloqueo** (`hd_pl_bloquear`): evita dos ejecuciones a la vez; devuelve `null` si ya hay una.
- **Carpeta privada** (`hd_pl_dir_privado`), `hd_pl_csv`, `hd_pl_plantilla`, `hd_pl_reservas`.

**Decisión de diseño que conviene conservar:** tokens distintos por uso y SSRF en toda petición
saliente.

---

## 3. Plugins de operación diaria

### 3.1 Hoy (`hoy`) **[wiki]**

**Qué hace:** pantalla de recepción para un día (hoy por defecto; se puede cambiar).

**Cómo:** solo lee. Muestra llegan, salen y alojados. Por reserva: habitación, cliente, teléfono,
noches, pendiente y estado (sin confirmar, entrada hecha, pendiente de salida…). Añade el total
pendiente del día y las habitaciones por limpiar si se usa Limpieza. Cada reserva enlaza a su
ficha.

**Permisos:** `ver_reservas` + `ver_telefonos`.

### 3.2 Limpieza (`limpieza`) **[wiki]**

**Qué hace:** una tarjeta por habitación, pensada para el móvil, con su estado de limpieza.

**Cómo:**

- Reutiliza el inventario: una habitación está limpia si tiene el artículo `cleaning` con
  cantidad > 0. La primera vez, el administrador pulsa «Crear el artículo» y todas quedan limpias.
- Cada tarjeta dice limpia/por limpiar y libre/ocupada/llega/sale, con un botón para cambiar.
- Rotaciones de los próximos 7 días: cada salida, en rojo si entra otro huésped ese mismo día en
  esa habitación, con campo para asignar la limpiadora (`dati/plugin_limpieza.json`).

**Permisos:** `ver_reservas` + `inventario_hab`.

### 3.3 Aviso de Entradas y Salidas (`aviso_limpieza`) **[código + wiki]**

**Qué hace:** genera el parte diario para el equipo, listo para copiar o mandar por WhatsApp:

```text
📌 Sábado 31 de Octubre
🔹 Salen: Cabaña 2, Cabaña 5
🔸 Llegan: Cabaña 2
✅ Limpieza: Cabaña 4
```

**Cómo:**

- Rango de hasta 3 meses. Los días sin movimiento no salen.
- Casillas para añadir nombre, personas, origen, noches, teléfono o comentario.
- Casilla «Mostrar limpieza». El teléfono de destino lo recuerda el navegador.
- **Días de limpieza**, de dos fuentes:
  - El coste añadido «Limpieza» (nombre que empieza por `limpieza`, `cleaning` o `pulizi`, con
    `associasett = s`; los días están en `costiprenota.settimane`). Se crea desde la página.
  - En una subordinada, datos locales en `limpieza_local` dentro del JSON del plugin.
- **Limpieza fija semanal:** se guarda la regla `{"semana":[…]}`, no las fechas
  (`hd_al_es_regla`, `hd_al_dias_regla`). Así, si la reserva cambia de fechas, la limpieza se
  ajusta sola.
- El día de llegada y el de salida no llevan limpieza aparte.
- **Clientes omitidos:** buscador por JSON y lista guardada; también se omiten todos los clientes
  de un tipo (el campo de Tipo de cliente). Sirve para bloqueos con clientes internos.

**Permisos:** `ver_reservas`. Cambiar omitidos: administrador o `mod_reservas`.

### 3.4 Mapa (`mapa`) **[wiki]**

**Qué hace:** muestra la ocupación sobre un plano propio (camping, parcelas, bungalows).

**Cómo:** el administrador sube una imagen (PNG, JPG, GIF o WEBP, máximo 2 MB), elige una unidad y
hace clic en el plano. Al elegir una fecha, cada unidad sale con el color de su estado: verde libre,
rojo ocupada, naranja llega, azul sale. Datos en `dati/mapa_plugin.json` y `dati/mapa_plugin_img`.

### 3.5 Cierre del día (`cierre`) **[wiki]**

**Qué hace:** lista de comprobación del final del turno.

**Cómo:** comprueba llegadas sin entrada, salidas sin salida, salidas con saldo pendiente, sin
confirmar que llegan en 3 días, ocupación de la noche y caja del día por caja y método de pago.
«Guardar cierre» guarda el informe con notas y usuario en `dati/cierres/`.

**Permisos:** `ver_reservas` + `ver_cajas`.

---

## 4. Atención al huésped

### 4.1 Mensajes y WhatsApp (`mensajes`) **[wiki]**

**Qué hace:** plantillas con variables para escribir a cada huésped.

**Cómo:** variables `{nombre}`, `{llegada}`, `{salida}`, `{noches}`, `{habitacion}`, `{reserva}`,
`{total}`, `{pendiente}` y variables propias (`wifi=MiRed / clave123`). Se elige un periodo y, por
reserva, hay botones de WhatsApp, Copiar y Email. **Nada se envía solo.** Solo el administrador edita
plantillas.

### 4.2 WhatsApp en todo el sistema **[wiki]**

**Cómo:** nunca envía por sí mismo: abre un enlace de WhatsApp con el texto escrito. Los teléfonos
sin prefijo usan `C_PREFISSO_WHATSAPP` (52, México). Los documentos de tipo correo llevan botones
WhatsApp y Copiar. En la reserva, los documentos de texto o HTML se abren en un cuadro; el HTML pasa a
texto sin formato.

### 4.3 Pre-check-in (`precheckin`) **[código + wiki]**

**Qué hace:** el huésped rellena sus datos desde el móvil antes de llegar.

**Cómo:**

- Firma del enlace: los primeros 24 caracteres de
  `HMAC-SHA256("<año>-<idreserva>", token del plugin)` (`hd_pc_firma`). No se puede adivinar el de
  otra reserva.
- Pide nombre, documento, nacimiento, nacionalidad, dirección, email, teléfono, hora de llegada y
  acompañantes, con aceptación del tratamiento de datos.
- Recepción ve lo enviado y «Aplicar a la ficha» lo copia al cliente.
- Se guarda en `dati/precheckin/` y se **borra 30 días después de la salida**.

### 4.4 Cupones (`cupones`) **[wiki]**

**Qué hace:** códigos de descuento en porcentaje o importe, con fechas de llegada válidas y máximo de
usos.

**Cómo:** recepción lo aplica desde el plugin; se suma al descuento de la reserva y se resta del
total. Cada uso queda registrado y se puede deshacer.

**Limitación:** no está en la página pública de reservas. **En el sistema nuevo:** integrarlo en el
motor de reservas.

### 4.5 Políticas y depósitos (`politicas`) **[wiki]**

**Qué hace:** configura días de cancelación gratuita, % de penalización, % de no-show, y depósito
(porcentaje y plazo).

**Cómo:**

- Genera el texto de la política para copiar.
- Calculadora: cuánto cuesta cancelar hoy una reserva.
- Depósitos pendientes con recordatorio por WhatsApp; vencidos en rojo.
- No-shows de los últimos 7 días (llegada pasada, sin entrada). «Marcar no-show» anota la
  penalización al principio del comentario.

**No cancela ni cobra nada por sí solo.**

---

## 5. Grupos (`grupos`)

Es el plugin más grande. Datos por grupo en `dati/grupos/g<n>.json`; configuración en
`dati/plugin_grupos.json`.

### 5.1 Alta y reservas **[wiki]**

- Cliente coordinador, fechas y habitaciones (casillas o «Seleccionar todas las libres»). Las ocupadas
  salen sin casilla, con el cliente que las ocupa.
- Cada habitación es una **reserva normal** con origen «Grupo GRP-…», comentario `[grupo …]` y precio
  impuesto. En la tabla del mes lleva contorno discontinuo del color del grupo (gancho
  `tabella_prenota_stile`).
- Estados: tentativo, confirmado, cancelado. Cancelar el grupo cancela sus reservas; también se puede
  cancelar una habitación. Cada habitación puede tener sus fechas.
- Cambiar habitación o fechas rehace la reserva **solo si la nueva está libre**.
- No se crea el grupo si el año de sus fechas no está creado.
- Código aleatorio `GRP-XXXXXX`, sin caracteres confundibles
  (`ABCDEFGHJKMNPQRSTUVWXYZ23456789`) **[código]**.

### 5.2 Cálculo del precio **[código]** (`hd_grp_calcula`)

Solo cuentan las habitaciones con estado `activa`.

1. **Base por habitación**, según el modo:
   - `total`: el total se reparte en proporción a las noches de cada habitación. El resto del
     redondeo va a la última.
   - por tipo: `precio_noche` propio de la habitación si lo tiene; si no, el precio de su capacidad
     (`por_tipo[cap]`). Se multiplica por las noches.
2. **Habitación gratis cada N:** si `N > 1` y hay al menos N activas, se regalan
   `floor(activas / N)`, **las más baratas**. Su descuento es toda su base.
3. **Descuento en %** (limitado a 0–100) a las que no son gratis.
4. `neto = base - descuento`, redondeado a 2 decimales.
5. `total = subtotal - descuento + extras`.
6. `pagado` = suma de pagos con estado `confirmado`; los `por_confirmar` se suman aparte y **no**
   cuentan como pagados. `pendiente = max(0, total - pagado)`.
7. Las cuotas se ordenan por vencimiento y lo pagado se aplica a las primeras.

### 5.3 Pagos y listas **[wiki]**

- Anticipo en % o importe y el resto en cuotas hasta una fecha, o pagos a mano. Estados: pagado,
  parcial, pendiente, atrasado. Avisa si el calendario no suma el total.
- Los pagos se llevan **en el grupo**, no en la caja de cada reserva.
- Fecha límite de la lista; si se marca, libera las habitaciones sin responsable.
- CSV de huéspedes (descargar e importar), lista de llegada para imprimir, copiar el grupo a otras
  fechas, pre-check-in por habitación si está activo.
- Panel: pagos por confirmar, pagos que vencen o vencidos (recordatorio por WhatsApp), listas
  incompletas, llegadas de la semana y mensajes nuevos.
- Historial por grupo; con Auditoría activa, también queda ahí.

### 5.4 Página del coordinador (`publico.php`) **[wiki]**

- **Enlace secreto** de 24 caracteres por grupo (`&g=`). Sin él no aparece ni la entrada. Cambia al
  generar clave nueva.
- **Credenciales:** clave aleatoria que se muestra una vez, con envío por WhatsApp. Cambio obligatorio
  al entrar (configurable), mínimo 8 caracteres.
- **«Ver la clave»:** el administrador la ve mientras el coordinador no la cambie. Se guarda con
  AES-256-GCM (llave en `dati/grupos/clave_cifrado.key`). La que elige el coordinador nunca se
  guarda en claro.
- **Sesión propia:** cookie HttpOnly y SameSite=Strict, token CSRF en cada formulario.
- **Antifuerza bruta:** 5 errores bloquean la cuenta 15 minutos; 20 por conexión y hora bloquean la
  conexión. Mismo mensaje para usuario inexistente, clave mala o cuenta bloqueada.
- El acceso caduca unos días tras la salida (configurable) y se puede desactivar.
- **Funciones:** cuenta con barra de progreso, calendario, datos para pagar, datos de cada habitación
  (obligatorios: responsable y personas; no deja superar la capacidad), WhatsApp por habitación,
  registrar pagos con comprobante y mensajes con el hotel.

### 5.5 Contrato y aviso de privacidad **[wiki]**

- Contrato por grupo (plantilla con cláusulas) y aviso general, ambos con versión y huella SHA-256.
- Aceptación: casilla sin marcar, botón «Acepto» y nombre completo como firma. Se guardan fecha y hora
  del servidor, IP, navegador, versión y huella del texto exacto.
- Sin aceptar no se usa la página. Si cambia el texto, hay que aceptar otra vez.
- Copia para imprimir o guardar en PDF.
- **Pendiente:** código de confirmación por WhatsApp o correo al aceptar.

### 5.6 Comprobantes de pago **[wiki]**

1. Solo JPG, PNG o WebP: se comprueba el **tipo real**, no la extensión.
2. Máximo 5 MB; se comprueban los píxeles antes de abrirla.
3. Se **vuelve a generar** la imagen con GD (máximo 2000 px): elimina GPS y contenido oculto.
4. Nombre aleatorio en carpeta privada; solo la ven ese coordinador y el personal autorizado.
5. Límites: 20 por hora y 300 por grupo.
6. Se borran pasados los meses de conservación tras la salida (24 por defecto).
7. El pago queda «por confirmar» hasta que el hotel lo confirma o rechaza con motivo.

**Permisos:** `ver_reservas` + `mod_reservas` + `mod_clientes`. Configuración, borrar pagos y borrar
grupos cancelados: solo administrador.

---

## 6. Canales y automatización

### 6.1 Calendarios iCal (`ical`) **[código]**

**Exportar** (`hd_ical_feed`): un feed por habitación con sus reservas desde hoy. Cada evento lleva
`UID:res-<año>-<id>@alojaflow`, `DTSTART/DTEND;VALUE=DATE` y `SUMMARY:Reservado`, **sin datos del
huésped**.

**Importar** (`hd_ical_importar`), con bloqueo para una sola ejecución:

1. Por cada habitación y URL, la fuente es `md5(url)` truncado a 10 caracteres.
2. Descarga con protección SSRF. **Si falla, no se toca nada de esa fuente.**
3. Parser propio: desdobla líneas, lee `UID`, `SUMMARY`, `DTSTART`, `DTEND`. Si falta la salida o no
   es posterior, usa llegada + 1 día. Si falta el UID, usa un md5 de los datos.
4. Rechaza calendarios de más de 1500 eventos. Ignora eventos ya terminados.
5. Clave de cada evento: `fuente|uid`.
   - Ya importado e igual: no hace nada.
   - Cambió: borra el bloqueo y lo crea de nuevo.
   - Año no creado: lo anota y sigue.
6. Crear el bloqueo: reserva a nombre del cliente «Bloqueo iCal», precio 0, origen «iCal <portal>»,
   comentario `[ical]`. Si no se puede, el informe dice **«ATENCIÓN… Posible doble reserva»**.
7. Los importados que ya no están en el portal se borran, **solo si su fuente se descargó bien** y la
   estancia es futura.
8. Guarda el informe. Solo toca bloqueos propios y sin pagos: **si un bloqueo tiene un pago, deja de
   tocarse**.

**Cron:** `plugin.php?p=ical&publico=1&t=TOKEN&accion=importar`, cada 15–30 minutos.

**Limitaciones:** iCal no lleva precio ni huésped, los portales leen el calendario cada cierto tiempo
(hay margen de doble reserva) y no envía precios ni restricciones.

**Sin probar contra portales reales:** se probó con un portal simulado.

### 6.2 Automatización (`automatizacion`) **[código + wiki]**

**Qué hace:** reglas «cuando pase X, haz Y».

**Eventos:** reserva creada, modificada, cancelada, borrada; entrada; salida; N días antes de la
llegada; N días tras la salida.

**Cómo detecta cambios** (sin tocar el núcleo) (`hd_auto_tick`):

- Guarda el estado de cada reserva. La huella es el md5 de llegada, salida, habitación, personas,
  cliente, total, pagado, conferma y `data_modifica` (`hd_auto_huella`), más si tiene entrada y
  salida registradas.
- Compara con el estado anterior: clave nueva = creada; huella distinta = modificada; desaparecida =
  cancelada.
- **La primera comprobación solo guarda el estado**: no dispara nada.
- Se ejecuta con el botón, al abrir el menú principal (máximo cada 5 minutos) o por cron.

**Acciones:**

- **Webhook:** POST JSON solo a `https`, con cabeceras `X-AlojaFlow-Hora` y
  `X-AlojaFlow-Firma: sha256=<HMAC>`, calculado sobre `hora + "." + cuerpo`. El cuerpo es
  `{"evento":"reserva.creada","fecha":"…","reserva":{…}}`.
- **Email:** con plantilla, al huésped o a una dirección fija, usando el correo configurado.

Registro de las últimas 200 acciones.

**Sin probar con servicios reales:** se probó con un receptor simulado, sin Make real ni servidor SMTP.

**En el sistema nuevo:** usar eventos del modelo en lugar de comparar estados, y colas para los envíos.

---

## 7. Análisis y datos

### 7.1 Indicadores (`kpi`) **[wiki]**

**Solo administrador.** Pestañas con rango de fechas (máximo 2 años) y CSV: resumen del año, por
alojamiento, año anterior, reservas a futuro (6 meses frente al año pasado), ingreso neto por canal,
cobros, estancia y anticipación, día de la semana, cancelaciones, limpieza, huéspedes y grupos.

**Fórmulas que hay que conservar:**

- Ocupación = noches ocupadas / noches disponibles.
- ADR = ingresos / noches ocupadas **con importe**. Los bloqueos a 0 cuentan como ocupación pero no
  bajan el ADR.
- RevPAR = ingresos / noches disponibles.
- Ingresos = total de cada reserva (con costes añadidos) **repartido por noches**.
- Una reserva en dos tablas de año cuenta una vez.
- Países unificados: MX, Mx y México cuentan como México.
- Importes con el formato de moneda configurado.

### 7.2 Exportar facturas (`facturas_csv`) **[wiki]**

Solo lee. Una fila por factura guardada: documento, año, número, fecha, reservas, cliente, NIF/CIF
(`partita_iva` o, si está vacío, `cod_fiscale`), dirección, CP, ciudad, provincia, país, email, llegada,
salida, total, pagado, pendiente, archivo y aviso.

- Formatos: Excel en español (`;`, coma decimal, UTF-8 con BOM) o CSV estándar.
- **Seguridad:** las celdas que empiezan por `=`, `+`, `-` o `@` llevan `'` delante, para que la hoja
  de cálculo no las ejecute.
- La fecha es la de guardado del archivo. Los importes son los de las reservas enlazadas, no los del
  texto de la factura: pueden no coincidir.

### 7.3 Fusionar clientes (`fusionar`) **[wiki]**

Agrupa posibles duplicados por nombre y apellidos (sin acentos ni mayúsculas), email, teléfono (últimas
9 cifras) y documento. Al fusionar pasan al conservado las reservas de todos los años (también
canceladas), huéspedes, campos personalizados y acompañantes. Los datos vacíos se completan y los
comentarios se juntan. **No se puede deshacer.**

### 7.4 Tipo de cliente (`tipo_cliente`) **[wiki]**

Contorno de color en la tabla del mes según un campo personalizado del cliente. Se configura editando
`plugins/tipo_cliente/config.php` en el servidor. **En el sistema nuevo:** configurarlo desde la
interfaz.

### 7.5 RGPD (`rgpd`) **[wiki]**

- Exportar en JSON todos los datos de un cliente.
- Anonimizar: borra datos personales y campos; las reservas quedan a nombre de «Anonimizado #id», para
  que las estadísticas sigan cuadrando.
- Plazo: lista de clientes sin estancias en N años (5 por defecto) para anonimizar de una vez.
- No modifica documentos ya guardados.

---

## 8. Seguridad y sesiones

### 8.1 Verificación en dos pasos **[código]** (`includes/funzioni_2fa.php`)

TOTP según RFC 6238: HMAC-SHA1, 30 s, 6 cifras, secreto en Base32, ±1 paso de margen. **Un código solo
vale una vez:** se guarda el último paso usado. Un código erróneo cuenta como intento fallido. Cada
usuario la activa para sí. El administrador puede quitársela a otro. Si la pierde el administrador, se
borra `dati/doble_factor.php`. La comprobación sigue activa aunque se desactive el plugin.

### 8.2 Mantener la sesión en este dispositivo **[código]** (`includes/funzioni_recordar.php`)

Cookie `af_recordar` con formato `selector.validador` (18 y 64 caracteres hex). HttpOnly, SameSite=Lax,
Secure en HTTPS. 30 días por defecto.

Al usarla (`af_rec_usa`):

1. Solo si la petición viene de la misma web (`af_rec_misma_web`).
2. Busca el selector y compara `sha256(validador)` con `hash_equals`.
3. **Rotación:** en cada uso genera un validador nuevo y guarda el anterior.
4. **Margen de 2 minutos:** el validador anterior se acepta sin rotar, porque varias pestañas pueden
   llegar a la vez al restaurar el navegador.
5. **Cookie vieja fuera del margen:** se anula el dispositivo, porque pudo copiarse.
6. Comprueba que el usuario se llame igual y que la huella de contraseña no cambió
   (`sha256("af_recordar|password|salt")`). Si no, anula el dispositivo.

Salir olvida el dispositivo. Los datos están fuera de la base, así que sobreviven a la subordinación.

### 8.3 Página de sesiones (`sesiones.php`) **[wiki]**

Sesiones abiertas y dispositivos recordados, con usuario, dispositivo, IP y último uso. Botones Cerrar,
Olvidar y «Cerrar mis demás sesiones y dispositivos». Cada usuario ve las suyas; el administrador, las
de todos, y elige los días de recuerdo o desactiva la función. Una sesión normal caduca tras 90 minutos
sin uso.

### 8.4 Contraseñas **[wiki]**

- bcrypt con migración transparente: los hashes antiguos (MD5 o en claro) se convierten al entrar.
  Comparación estricta, para evitar los «magic hashes».
- En una subordinada, el hash que llega de la principal **no se reescribe**.
- Mínimo 8 caracteres en las nuevas.
- Campos `type=password` con Mostrar/Ocultar y `autocomplete` correcto. Las guardadas (SMTP, descarga
  de correo, API) no se escriben en la página: vacío = conservar.

### 8.5 Auditoría (`auditoria`) **[código + wiki]**

Registra toda escritura con fecha, usuario, IP y página.

**Cómo:** un gancho de una línea en las funciones de consulta llama a `hd_auditoria_registra`, que
reconoce `insert`, `update`, `delete`, `create`, `drop` y `alter` por expresión regular. Ignora las
tablas `sessioni`, `transazioni`, `transazioniweb`, `versioni` y `cache`. Sustituye los parámetros
recortados a 80 caracteres. No guarda contraseñas. Un archivo por mes en `dati/auditoria/AAAA-MM.log`,
conservado 12 meses (configurable). Búsqueda por fechas, usuario, tabla y texto, con CSV.

**En el sistema nuevo:** auditar a nivel de modelo con valores anteriores y nuevos.

### 8.6 Otras medidas **[wiki]**

- Anti-CSRF sin login: rechaza POST de otro sitio aunque falte el Referer (`Origin`, `Sec-Fetch-Site`);
  cookies SameSite=Lax.
- Cabeceras de seguridad, también en estáticos.
- Subidas con nombre y ruta validados.
- Reglas para bloquear `dati/` en Apache e IIS; regla manual para nginx.

**Laravel resuelve de serie** consultas preparadas, escape en plantillas y CSRF. Las correcciones
puntuales del código viejo no hay que trasladarlas, pero sí escanear el sistema nuevo con OWASP ZAP.

---

## 9. Subordinación a HotelDruid

### 9.1 Qué es **[wiki]**

Una instalación (subordinada) copia todos los datos de otra (principal) en cada actualización. Es de
solo lectura para reservas, clientes y pagos. Puede ser principal AlojaFlow 1.x o HotelDruid 3.0.8/3.08,
también el hosting oficial. Se entra con los usuarios de la principal.

### 9.2 Protocolo **[código]**

Todas son peticiones GET a la principal, con credenciales en la URL y **codificadas** con `rawurlencode`
(una clave con `#` o `+` impedía subordinar). Conviene un usuario con permiso solo para crear backups, y
HTTPS, porque la contraseña queda en la URL.

Parámetros comunes: `nome_utente_phpr`, `password_phpr`, `anno`, `azione=SI`.

| Paso | Petición a `crea_backup.php` |
|---|---|
| 1. Crear | `…&crea_backup=SI` |
| 2. Descargar | `…&mostra_header=NO&salva_backup=SI&compresso=SI` (o `NO`) |
| 3. Borrar de la principal | `…&cancella_backup=SI` |

Comprobaciones al descargar:

- Si empieza por `\x1f\x8b`, está en gzip: descomprimir.
- Si no contiene `<nometabella>`, no es un backup. Si contiene `password_phpr` o `login`, las
  credenciales están mal.
- HotelDruid hace esperar unos 5 segundos tras un inicio de sesión erróneo desde la misma IP.

**Nunca llames a `mostra_sorgente.php`.** Sirve para que la subordinada copie el código fuente de la
principal, y el sistema nuevo no debe recibir ni instalar ese código.

### 9.3 Formato del archivo de copia **[código]**

Texto por líneas, con esta estructura:

```text
<backup>
<versione>3.081</versione>   (se lee en los primeros 4 KB)
<database>
<tabella>
<nometabella>prenota2026</nometabella>
<colonnetabella>
<nomecolonna>idprenota</nomecolonna>
<tipocolonna>…</tipocolonna>
…
</colonnetabella>
<righetabella>
<riga><cmp>valor1</cmp><cmp>valor2</cmp>…</riga>
</righetabella>
</tabella>
…
<file>
<nomefile>./dati…</nomefile>
<contenuto>…</contenuto>
</file>
<modello>
<dirmodello>…</dirmodello>
<nomemodello>…</nomemodello>
<contenuto>…</contenuto>
</modello>
</database>
</backup>
```

Los bloques `<file>` y `<modello>` traen archivos de `dati/` y páginas web generadas. Para obtener
reservas, clientes y habitaciones basta con los bloques `<tabella>`.

**Escapes.** Al escribir un campo (en este orden):

1. `@%&@` → `@%&@@%&@`
2. `</cmp>` → `</cmp@%&@>`

Al escribir la fila completa:

3. `</righetabella>` → `</righetabella@%&@>`
4. `</riga>` → `</riga@%&@>`
5. `<riga>` → `<riga@%&@>`

**Al leer, en orden inverso:** primero los tres de la fila, después `</cmp@%&@>` → `</cmp>` y por último
`@%&@@%&@` → `@%&@`.

**Una fila puede ocupar varias líneas** si algún campo lleva saltos de línea: hay que acumular hasta ver
`</riga>`. Los valores se asignan por posición a `<nomecolonna>`.

Un archivo real puede pesar decenas de MB (el panel multipropiedad limita a 60 MB y espera hasta 180 s
por petición). **Lee por líneas con una máquina de estados, sin cargarlo entero.**

Antes de implementarlo, valida este formato con copias reales: el lector de referencia es
`hd_mp_lee_backup` (`plugins/multipropiedad/funciones.php`).

### 9.4 Dos formas de consumir la copia

- **Restaurar** (subordinación actual, `interconnessioni.php`): sustituye toda la base local y luego la
  actualiza a 3.081 si venía de 3.08. Conserva `dati/tema.php`, y los datos de plugins sobreviven.
- **Leer sin restaurar** (panel multipropiedad): extrae lo necesario a un JSON por propiedad. La base
  local no se toca.

**En el sistema nuevo:** usar la segunda idea: leer la copia y traducirla al modelo propio, en una
transacción, sin mezclar tablas con el modelo de HotelDruid.

### 9.5 Actualización automática **[código]**

`sub_auto.php?token=…` con configuración en `dati/sub_auto.json` (`hd_sub_auto_toca`):

- Token de 32 caracteres hex, comparado con `hash_equals`. Sin él: 403.
- Frecuencia de 15 minutos a 1 día, días de la semana y franja horaria. Admite franjas que cruzan la
  medianoche (22:00–06:00).
- Solo actúa si pasó el intervalo desde el último intento (con 30 s de margen). Un `flock` impide dos
  ejecuciones a la vez.
- Con cron, o en segundo plano al abrir el menú principal si ya toca.
- No cierra sesiones. Muestra la última actualización correcta y el último error. El token se puede
  regenerar.

### 9.6 Edición local en la subordinada **[wiki]**

El manifiesto declara `edicion_sub` y el administrador elige en *Interconexiones* qué se edita
localmente (`dati/sub_edicion.json`). Hoy: días de limpieza y Grupos. Los datos se conservan al
actualizar. Las reservas de un grupo siguen creándose en la principal.

**Esto encaja con la fase 1 del sistema nuevo:** datos propios en tablas que la sincronización no toca.

### 9.7 Detalles que causaron errores **[wiki]**

- El código de reserva salía vacío en la subordinada, porque dependía del permiso de regenerarlo.
- El tema del usuario se perdía al restaurar.
- La actualización automática se daba por fallida si la subordinación se había escrito en el mismo
  segundo: compara con una marca explícita, no con la fecha del archivo.

---

## 10. Panel multipropiedad (`multipropiedad`) **[código + wiki]**

**Qué hace:** una instalación central reúne de 2 a 10 cuentas de HotelDruid o AlojaFlow, en solo lectura.

**Alta:** nombre, color, dirección `https://`, usuario y contraseña (AES-256-GCM, llave en
`dati/multipropiedad/clave_cifrado.key`). Id aleatorio de 12 caracteres hex.

**Sincronización** (`hd_mp_sincroniza`), manual o por cron con código secreto, con un bloqueo por
propiedad:

1. Ejecuta el protocolo de 9.2, con compresión.
2. Guarda en un temporal y lee las tablas `appartamenti`, `periodi`, `clienti`, `prenota`, `prenotacanc`,
   `costiprenota` y `personalizza` (`valuta` y `stile_soldi` del usuario 1).
3. Construye una «foto» y la escribe de forma atómica (temporal + `rename`) en
   `dati/multipropiedad/<id>.json`.
4. Descarta reservas sin fechas o con salida ≤ llegada, y deduplica por habitación + llegada + salida.

**Vistas** (todas o una propiedad):

- **Resumen:** tarjeta por propiedad (llegan y salen hoy, alojados, llegadas en 7 días, pendiente,
  antigüedad de los datos), total y avisos.
- **Hoy:** con teléfono, pendiente y enlace a `modifica_prenota.php?id_prenota=…&anno=…` en su cuenta.
- **Entradas y salidas:** aviso para WhatsApp agrupado por propiedad.
- **Disponibilidad:** habitaciones libres por fechas y personas, según capacidad.
- **Calendario:** cada propiedad con su color, más claro si no está confirmada.
- **Estadísticas:** noches, ocupación, ingresos, ADR, RevPAR, reservas, estancia media, cobrado,
  pendiente y cancelaciones; por propiedad, origen y mes; CSV.

**Avisos:** sincronización fallida o vieja, llegadas en 3 días sin confirmar, salidas de la última semana
con saldo, reservas que se solapan. Si las monedas son distintas, avisa y suma tal cual.

---

## 11. Funciones del sistema fuera de los plugins

### 11.1 Actualizar desde la aplicación (`actualizar.php`) **[wiki + notas de versión]**

1. Sube el zip **por partes de 1 MB**: funciona aunque el hosting limite las subidas.
2. Revisa: firma, versión, archivos que cambian, los no escribibles y notas de la versión.
3. **Paquete firmado:** `MANIFIESTO.txt` (SHA-256 de cada archivo) y `FIRMA.txt` (RSA-3072/SHA-256).
   Solo se instala si la firma es válida con la clave pública incluida y cada archivo coincide. No
   instala versiones anteriores a la instalada.
4. **No toca** `dati/` (salvo sus archivos de protección) ni `plugins/attivi.txt`.
5. **Copia y vuelta atrás:** guarda lo sustituido en `dati/actualizacion/anterior-<versión>-<fecha>/` (las
   3 últimas). Si falla a medias, restaura. Registro en `registro.log`.
6. Al terminar, muestra novedades y plugins nuevos.
7. *Sin publicar todavía:* borra archivos obsoletos si siguen sin cambios, según
   `includes/obsoletos.txt`. Rechaza rutas con segmentos acabados en punto, y compara `dati/` y
   `plugins/attivi.txt` sin distinguir mayúsculas, por Windows.

La clave privada se guarda en `~/.alojaflow/`. **Guarda una copia:** sin ella, los zips nuevos no se pueden
instalar desde la aplicación.

**Requisitos:** extensiones `zip` y `openssl`, y que el servidor pueda escribir en la carpeta.

### 11.2 La base se actualiza sola **[notas de versión]**

Al abrir una página, si el esquema está desfasado, se actualiza y se continúa. Con GET vuelve a la misma
página; con un formulario va al menú, porque ese envío no se procesó. Si no se puede (por ejemplo, sin
permiso de escritura), muestra el aviso con la explicación.

**En el sistema nuevo:** usar migraciones de Laravel con este mismo comportamiento.

### 11.3 Bloquear fechas (`blocca_date.php`) **[wiki]**

Cierra habitaciones sin crear una reserva: fechas, habitaciones y motivo (máximo 30 caracteres). Salen en
gris en la tabla del mes y hay botón Desbloquear.

**Cómo:** usa la regla de asignación 1 con «cerrar». A diferencia de la página de reglas, **no mueve
reservas**: si ya hay una, avisa y no bloquea esa habitación. Avisa a las interconexiones.

### 11.4 Importar precios (`importa_tariffe.php`) **[wiki]**

Columnas: `tarifa` (nombre o número), `desde`, `hasta` (última noche **incluida**), `precio` y
`precio_por_persona` (opcional; vacío = no cambia).

**Cómo:** plantilla CSV con los precios de hoy; subir archivo (máximo 1 MB) o pegar desde Excel, con o sin
encabezado. Vista previa: solo se importan las filas OK. Sustituye los precios de esas fechas, se aplica a
todos los años creados aunque el rango cruce de año, respeta las tarifas derivadas y los privilegios del
empleado. Acepta coma o punto decimal, y otra codificación de Excel en Windows.

### 11.5 Restaurar copias **[notas de versión]**

- Acepta copias de la 3.08 y actualiza a 3.081 después, en otra petición.
- Errores legibles («formato incorrecto» explica la causa).
- En SQLite, restaurar era atómico y unas 80 veces más rápido. *No aplica: el sistema nuevo no usa SQLite.*

### 11.6 Tema moderno `flw` **[wiki]**

Es un requisito visual para el sistema nuevo:

- Claro y sobrio: hoja blanca sobre gris claro, barra superior blanca.
- Botones redondeados de 36 px (44 px en móvil), foco verde azulado.
- Tablas con cabecera tintada y cifras alineadas; iconos de línea propios.
- **Modo oscuro:** automático → oscuro → claro, recordado por el navegador. Al imprimir siempre claro.
- **Rojo solo para borrar**, con papelera. Botón principal en verde.
- Barra de acciones fija en insertar y modificar.
- Cabecera fija en listas largas; tablas anchas con desplazamiento dentro de su caja.
- Calendario emergente con teclado y Esc.
- En móvil: botones de 50 px, «Insertar reserva» de 64 px, grupos en tarjetas.
- Instalable en el móvil («Añadir a la pantalla de inicio»), con `manifest.json`.
- Los colores de estado de reserva los elige cada usuario.

**Errores que no hay que repetir:** encabezados ocultos para lectores de pantalla que alargaban la página
(zona gris al final), y tablas de plugins reducidas a una raya en el móvil.

Más detalle en `E:\HotelDruid\DESIGN.md`.

### 11.7 Menú reorganizado **[notas de versión]**

Menú principal con las tareas del día. El resto va por secciones: *Tarifas* (precios, reglas, políticas),
*Tablas* (estadísticas, KPI, documentos, exportar facturas), *Clientes* (fusionar, RGPD, tipo de cliente,
grupos), *Configurar* (plugins, actualizar, iCal, automatización, auditoría, doble factor).

### 11.8 Otros detalles **[notas de versión]**

- Globo de la reserva en el calendario con fechas y noches.
- Caducidad de artículos del inventario.
- Regiones de México, EE. UU., Canadá y Europa con códigos ISO.
- Coste «Limpieza» con «Marcar todos los [martes]», «Marcar todos» y «Quitar todos».
- Unas 500 correcciones de traducción al español.

---

## 12. Compatibilidad y calidad

**Motores probados en el sistema viejo:** MySQL 8.4 y MariaDB 11.4, con PHP 8.2, 8.3 y 8.4. PostgreSQL no
está soportado.

**Pruebas:**

- Pruebas de equivalencia («golden»): comparan el comportamiento antes y después de un cambio. La idea
  sirve para comprobar que la importación desde HotelDruid da los mismos resultados.
- `tools/` tiene pruebas por función (`test_grupos.py`, `test_multipropiedad.py`,
  `test_subordinazione_hd308.py`, `test_sub_auto.py`, `test_sesiones*.py`, `test_actualizar.py`…) que
  documentan los casos a cubrir, incluidos los ataques.
- `upstream/downloads` tiene un HotelDruid 3.0.8 original para probar contra una principal real.
- **Aviso:** varias pruebas borran datos. Nunca las ejecutes sobre una instalación con datos reales.

---

## 13. Pendientes y limitaciones conocidas

**Pendiente de hacer:**

- Código de confirmación (OTP) por WhatsApp o correo al aceptar el contrato de un grupo.
- Cobro por habitación.

**Limitaciones del sistema viejo que el nuevo debería resolver:**

| Limitación | Dónde |
|---|---|
| Datos de plugins fuera de la base y del backup | Todos los plugins |
| Cupones no integrados en la reserva pública | 4.4 |
| Tipo de cliente se configura editando un archivo | 7.4 |
| Detección de cambios comparando estados | 6.2 |
| iCal, Pre-check-in y Automatización sin probar con servicios reales | 6.1, 6.2 |
| Contraseña de la principal en la URL (protocolo de HotelDruid; no se puede cambiar con HotelDruid como principal) | 9.2 |
| Sincronización completa, no incremental | 9.3 |
| Permisos como cadenas de banderas por posición | 1.4 |
| Teléfonos sin prefijo tratados siempre como de México | 4.2 |
| Sin tokens CSRF por formulario con login activado | 8.6 |
