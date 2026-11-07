# Subordinación a HotelDruid

**Estado:** borrador · 2026-11-03 · fase 1 · esfuerzo high
Índice: [README.md](README.md) · regla 10 del [CLAUDE.md](../../CLAUDE.md)

Este documento define cómo AlojaFlow (la **subordinada**) obtiene los datos de una instalación
de HotelDruid o AlojaFlow 1.2.x (la **principal**): el protocolo HTTP (Parte A), el formato del
archivo de copia (Parte B) y la correspondencia con el modelo propio (Parte C).

**Fuentes.** Lo marcado **[visto]** se comprobó leyendo el código de `E:\HotelDruid\alojaflow\`
(AlojaFlow 1.2.3, esquema 3.081), la etiqueta `upstream-3.0.8` del mismo repositorio (HotelDruid
3.0.8 original) y la estructura —sin las filas— de la copia de demostración. Las rutas son
relativas a `alojaflow/`. Lo marcado **[por verificar]** se prueba contra una principal real
antes de programarlo. De ese código se toma **la lógica, no el código** (regla 4).

---

## Resumen

1. AlojaFlow hace tres peticiones a `crea_backup.php` de la principal: **crear** la copia,
   **descargarla** comprimida y **borrarla** del servidor de la principal.
2. La copia es un archivo de texto con apariencia de XML, pero no es XML válido. Contiene todas
   las tablas de la principal, también hashes de contraseñas, y empieza con `<?php exit(); ?>`.
   **Nunca se ejecuta.** Se lee línea por línea con un analizador propio.
3. AlojaFlow conserva solo las tablas que necesita, las carga en un **espejo** y desde ahí
   **proyecta** al modelo propio. Descarta el resto y borra el archivo descargado.
4. Hay cuatro trampas que el diseño evita:
   - **Bloqueo de la cuenta:** siete contraseñas erróneas bloquean la cuenta de la principal, así
     que tras un rechazo AlojaFlow no reintenta.
   - **Límite diario de copias** que puede tener la principal.
   - **Contraseña en la URL:** se manda por POST.
   - **Principales sin inicio de sesión:** cualquiera puede descargar su copia, y AlojaFlow avisa.

---

# Parte A — Protocolo

## A1. Principales admitidas

| Principal | Esquema (`<versione>`) | Quién puede crear copias | Sesiones en la copia |
|---|---|---|---|
| AlojaFlow 1.2.x | 3.081 | Solo el administrador, usuario 1 **[visto: `crea_backup.php:94-105`]** | No; `sessioni`, `transazioni` y `transazioniweb` van vacías **[visto: `crea_backup.php:330-332`]** |
| HotelDruid 3.0.8 | 3.081 **[por verificar]** | El administrador y los usuarios con el privilegio de copia (segundo carácter de `priv_mod_pers` = `s`) **[visto: `upstream-3.0.8`, `crea_backup.php:100`]** | **Sí:** la copia trae sesiones y transacciones **[visto: `upstream-3.0.8`, `crea_backup.php:330-332`]** |
| HotelDruid 3.08 (hosting oficial) | 3.08 | Como 3.0.8 | Como 3.0.8 |

- Se admiten las versiones **3.08 y 3.081**. Difieren en una columna: `relinventario.scadenza`
  no existe en 3.08 **[visto: `includes/funzioni_backup.php:63-69`]**.
- **Otra versión se rechaza sin cargar nada** (error SUB-011). El esquema queda congelado
  mientras dure la fase 1 (D-011).
- La versión se lee de `<versione>`, en las primeras líneas de la copia.

## A2. Configuración de una principal

La da de alta el administrador de AlojaFlow en la pantalla «Propiedades y principales»:

| Campo | Reglas |
|---|---|
| Nombre y color de la propiedad | Para las vistas multipropiedad |
| URL base | Debe terminar en `/` y apuntar a la carpeta de la instalación. Solo `https://`, salvo `localhost` en desarrollo. Ver A6 para redes locales |
| Usuario y contraseña | Se guardan cifrados (regla 13). En 1.2.x tiene que ser el administrador |
| Zona horaria de la principal | Obligatoria. Las fechas y horas de la copia no la indican (C2) |
| Moneda (ISO 4217) | Se propone a partir de `personalizza.valuta`; la confirma el administrador (C2) |
| Frecuencia | 15 min, 30 min, 1 h, 2 h, 6 h, 12 h o 24 h. Por omisión, 30 min |
| Compresión | Activada por omisión |
| Permitir red local | Desactivado por omisión (A6) |

Al guardar se hace una **prueba de conexión** (A3, paso 0) y se muestra el resultado en la misma
pantalla.

## A3. Llamadas HTTP

### Cómo lee la principal los parámetros

La principal lee los parámetros de `$_REQUEST`, que reúne GET y POST
**[visto: `includes/funzioni.php:46-55`]**. Por tanto acepta los mismos parámetros por **POST**,
con el cuerpo en `application/x-www-form-urlencoded`.

- La protección contra peticiones de otros sitios de 1.2.x solo rechaza un POST que trae las
  cabeceras `Origin` o `Sec-Fetch-Site` de otro sitio. Además, solo aplica cuando el inicio de
  sesión está desactivado **[visto: `includes/funzioni.php:641-657`]**. Un cliente HTTP de
  servidor no manda esas cabeceras.
- **AlojaFlow debe usar POST**, para que la contraseña no quede en las URL ni en los registros del
  servidor.
- **[por verificar]** que una principal real con un cortafuegos de aplicaciones del hosting no
  rechace el POST. Si lo rechazara, el administrador podría activar un «modo compatible» por GET;
  la pantalla avisaría del riesgo.

### Autenticación

Cada petición lleva `nome_utente_phpr` y `password_phpr`. En la principal tiene estos efectos:

- **Crea una sesión** en la tabla `sessioni` en cada inicio de sesión correcto
  **[visto: `includes/funzioni.php:796-803`]**. Se borran al caducar.
- **Espera de 5 segundos** después de un intento fallido del mismo usuario e IP. En ese tiempo
  responde con el formulario de acceso aunque la contraseña sea buena
  **[visto: `includes/funzioni.php:690-697`]**.
- **Bloqueo** con 7 intentos fallidos del mismo usuario e IP, o 56 del usuario en total, dentro
  del tiempo de sesión (`C_MIN_SESSIONE`) **[visto: `includes/funzioni.php:699-706`]**. Por eso
  AlojaFlow **nunca reintenta en automático tras un rechazo de credenciales** (A5).
- **Doble factor (1.2.x):** si el usuario tiene la 2FA activada, la contraseña sola no basta
  **[visto: `includes/funzioni.php:769-774`]**. El usuario de la sincronización no puede tener la
  2FA activa. En 1.2.x solo el administrador puede crear copias, así que el administrador de la
  principal no podría usar la 2FA. Queda como riesgo conocido de la fase 1 (A6).
- **Principal sin inicio de sesión:** si la principal no tiene el archivo `dati/abilita_login`,
  trata **cualquier petición como administrador**, sin credenciales
  **[visto: `includes/funzioni.php:641-657`]**. AlojaFlow lo detecta porque ese archivo falta
  entre los bloques `<file>` de la copia (B6). En ese caso muestra un aviso de seguridad
  permanente en la propiedad.
- **Usuario externo de copias:** existe un modo sin contraseña, `utente_backup` igual a
  `C_UTENTE_BACKUP_ESTERNO`. Exige editar el código de la principal y en 1.2.x está vacío
  **[visto: `crea_backup.php:88`, `includes/costanti.php:93`]**. **No se usa en la fase 1.**

### El parámetro `anno`

- Sirve para validar el inicio de sesión y los privilegios. **No limita los años de la copia:**
  sin `anni_backup`, la copia incluye todos los años **[visto: `crea_backup.php:165-183`, `:310`]**.
- Debe ser un año que exista en la principal. Si no existe, la respuesta es un aviso o una
  redirección, no una copia **[visto: `includes/funzioni.php:641-657`]**.
- AlojaFlow envía el año más reciente de la tabla `anni` de la última copia buena. La primera vez
  envía el año actual en la zona horaria de la principal y, si falla por el año, prueba una vez
  con el anterior. **[por verificar]** el mensaje exacto con el inicio de sesión activado.

### Pasos

Todos van a `<URL base>crea_backup.php`, por POST, con `nome_utente_phpr`, `password_phpr` y
`anno` en el cuerpo.

| Paso | Parámetros adicionales | Correcto si… | Errores reconocibles |
|---|---|---|---|
| **0. Prueba de conexión** (solo al configurar) | ninguno | La página trae el formulario de copias, con un botón de nombre `crea_backup`, o solo su cabecera (`--t1color`): un usuario con el privilegio de copia que no tiene activado el año no ve el formulario, pero las copias le funcionan **[visto: `upstream-3.0.8`, `crea_backup.php:96`, `:779`]** | Formulario de acceso (campo `password_phpr`) o respuesta vacía, sin el privilegio de copia → SUB-003; año → SUB-006; otro → SUB-099 |
| **1. Crear** | `azione=SI`, `crea_backup=SI` | La respuesta contiene el marcador `<!-- Backup_created -->` **[visto: `crea_backup.php:421-422`]** | Formulario de acceso → SUB-003; límite diario → SUB-005 **[visto: `:156`]**; sin permiso de escritura → SUB-007 **[visto: `:424`]**; otro → SUB-099 |
| **2. Descargar** | `azione=SI`, `mostra_header=NO`, `salva_backup=SI`, `compresso=SI` | Cuerpo gzip (empieza por `1F 8B`) o texto que empieza por `<?php exit(); ?>` y contiene `<versione>` **[visto: `crea_backup.php:526-585`]** | HTML sin `<versione>` → SUB-003 o SUB-010; tamaño excedido → SUB-008 |
| **3. Borrar** | `azione=SI`, `cancella_backup=SI` | Respuesta sin el texto de error de borrado **[visto: `crea_backup.php:589-594`]** | Error de borrado → SUB-013 (aviso, no falla la sincronización) |

- **El paso 3 se intenta siempre** que el paso 1 haya ido bien, aunque falle el 2. En la principal
  queda `dati/hoteld_backup.php` con todos los datos y los hashes de contraseñas.
- **Los mensajes de error de la principal salen en su idioma.** AlojaFlow se apoya en marcadores
  y estructuras: `<!-- Backup_created -->`, el campo `password_phpr`, `<versione>`, la firma gzip.
  Solo para clasificar SUB-005 y SUB-007 compara textos conocidos en italiano, español e inglés,
  sin distinguir acentos: HotelDruid 3.0.8 en español escribe «lìmite» **[visto: `includes/lang/es/crea_backup.php:86`]**. Lo que no reconoce se guarda como SUB-099, con un extracto
  del HTML sin etiquetas de 500 caracteres como máximo.
- **Reutilizar la sesión (opcional, «puede»):** el paso 1 podría devolver un `id_sessione` en los
  formularios de la página, y los pasos 2 y 3 lo usarían en lugar de la contraseña. La sesión
  exige la misma IP y el mismo navegador **[visto: `includes/funzioni.php:869`]**.
  **[por verificar]** antes de usarlo.

### Lista blanca de parámetros

El cliente de AlojaFlow **solo puede enviar** estos parámetros: `nome_utente_phpr`,
`password_phpr`, `anno`, `azione`, `crea_backup`, `mostra_header`, `salva_backup`, `compresso`,
`cancella_backup` e `id_sessione`. Los valores posibles están fijados en el código.

El mismo archivo de la principal acepta también `upload_backup`, `ripristina_backup`,
`copia_def_backup` y otros parámetros que **cambian la principal** **[visto: `crea_backup.php:27-60`]**.
El cliente no debe ser capaz de enviarlos: es la garantía técnica de que AlojaFlow nunca escribe
en la principal. Tampoco se llama a ningún otro archivo; en particular, nunca a
`mostra_sorgente.php`. El cliente original de HotelDruid descarga el código fuente de la principal
cuando las versiones no coinciden **[visto: `interconnessioni.php:291-299`]**. AlojaFlow no lo
hace.

### Efectos en la principal

| Efecto | Detalle | Consecuencia para AlojaFlow |
|---|---|---|
| Bloqueo de tablas | La creación bloquea todas las tablas mientras escribe la copia **[visto: `crea_backup.php:186-273`]** | La principal se congela unos segundos; la frecuencia mínima es de 15 min |
| Archivo único | Siempre escribe `dati/hoteld_backup.php` **[visto: `crea_backup.php:275`]** | Si otra subordinada, una central multipropiedad 1.2.x o el administrador hacen una copia a la vez, se pisan. AlojaFlow detecta la descarga fallida y reintenta una vez tras una espera aleatoria de 30 a 90 s |
| Límite diario | `C_LIMITE_BACKUP_GIORNALIERI`; en 1.2.x vale 0, sin límite **[visto: `includes/costanti.php:102`, `crea_backup.php:141-157`]** | Con límite, SUB-005 pausa la principal hasta el día siguiente y avisa para bajar la frecuencia |
| Sesiones | Una por petición con contraseña | Aceptable; se reduce reutilizando la sesión (opcional) |

## A4. Ciclo de sincronización

1. **Programación.** El programador de Laravel (un cron por minuto) busca las principales
   vencidas según su frecuencia y encola un trabajo por cada una. Las principales se procesan
   **de una en una**, porque el hosting compartido no da para más.
2. **Bloqueo.** Un candado por principal. Si ya hay una sincronización en marcha, se omite
   (SUB-014, sin aviso).
3. **Crear, descargar y borrar** (A3), con los tiempos y tamaños de A6.
4. **Descomprimir** a un archivo temporal privado, con límite de tamaño descomprimido.
5. **Leer y validar** la copia por partes (Parte B), solo las tablas de C1.
6. **Cargar el espejo.** Las filas nuevas se marcan con el identificador de la ejecución y se
   cargan por lotes. Una transacción corta cambia la «ejecución vigente» de la principal y
   después se borran las filas viejas. Así las pantallas siguen leyendo datos coherentes durante la
   carga, sin una transacción larga (detalle en `modelo-datos.md`).
7. **Proyectar** al modelo propio (Parte C) en una transacción.
8. **Detectar eventos** comparando la proyección nueva con la anterior: reserva nueva,
   modificada, cancelada o eliminada (detalle en `comunicacion-eventos.md`).
9. **Borrar el temporal**, siempre, también si hubo error.
10. **Registrar la ejecución:**
    - inicio, fin y duración;
    - estado y paso en que falló;
    - código SUB;
    - bytes descargados y descomprimidos;
    - versión, fecha de la copia y filas por tabla.

    El registro se conserva 90 días.

**Antigüedad de los datos.** La hora de la copia sale del comentario de cabecera
(`<!-- AAAA-MM-DD HH:MM:SS -->`), en la hora local de la principal **[visto: `crea_backup.php:282-285`]**.
Las pantallas muestran «Datos de la principal al …» con esa hora convertida a la zona del usuario.

## A5. Errores, reintentos y avisos

| Código | Causa | Qué hace AlojaFlow |
|---|---|---|
| SUB-001 | No conecta (DNS, conexión rechazada) | Reintenta en la siguiente vuelta; avisa tras 3 fallos seguidos |
| SUB-002 | Error de TLS o certificado | Igual que SUB-001; el aviso dice que es el certificado |
| SUB-003 | Credenciales rechazadas, falta de privilegios o 2FA | **Pausa la principal sin reintentar** y avisa enseguida. Se reanuda al guardar credenciales nuevas, con una prueba de conexión tras 10 s |
| SUB-005 | Límite diario de copias | Pausa hasta las 00:00 de la principal y propone bajar la frecuencia |
| SUB-006 | El año no existe en la principal | Un reintento con el año anterior; si falla, pausa y avisa |
| SUB-007 | La principal no puede escribir la copia | Avisa; reintenta en la siguiente vuelta |
| SUB-008 | Tamaño máximo excedido o descarga incompleta | Avisa; reintenta en la siguiente vuelta |
| SUB-009 | gzip dañado | Igual que SUB-008 |
| SUB-010 | No es una copia de HotelDruid o tiene un formato inválido | Avisa; reintenta |
| SUB-011 | Versión no admitida | **Pausa** y avisa |
| SUB-012 | Copia incompleta: falta una tabla obligatoria o el final `</backup>` | Avisa; reintenta |
| SUB-013 | No se pudo borrar la copia en la principal | Solo aviso: la copia queda en `dati/` de la principal |
| SUB-014 | Ya hay una sincronización en marcha | Nada |
| SUB-015 | Tiempo agotado | Como SUB-001 |
| SUB-016 | Dirección no permitida: http sin red local, IP privada, credenciales o consulta en la URL (D-029) | **Pausa** y avisa |
| SUB-098 | Falla de AlojaFlow al procesar lo recibido: la principal respondió bien | Avisa, se anota en el registro; reintenta |
| SUB-099 | Respuesta no reconocida | Avisa con el extracto, también en el resumen; reintenta |

- **Siempre se conserva la última carga buena.** Un fallo nunca vacía ni deja a medias el espejo
  ni la proyección.
- Los avisos aparecen en el panel de la propiedad y en el resumen. También se envían por correo
  a los administradores, una vez por incidencia; cuando se resuelve llega otro correo.

## A6. Seguridad

- **Transporte:** HTTPS con verificación del certificado y sin seguir redirecciones. Una
  redirección es SUB-099 con su destino en el aviso.
- **Límites:**
  - tiempo de conexión: 15 s;
  - tiempo por petición: 180 s;
  - tamaño descargado: 60 MB;
  - tamaño descomprimido: 300 MB.

  Todos se pueden configurar en la instalación.
- **SSRF:** antes de cada petición se resuelve el nombre de la principal y se rechazan las
  direcciones privadas, de enlace local o de bucle local. Solo se aceptan con «permitir red local»
  activado, pensado para una principal en la misma red del alojamiento.
- **Credenciales:** cifradas en la base de datos (cast `encrypted`). No aparecen en registros,
  excepciones ni avisos; los mensajes de error del cliente HTTP se depuran antes de guardarse.
- **Archivos:** el temporal de la copia vive en una carpeta privada (`storage/app/privado/`),
  nunca en `public/`, y se borra al terminar.
- **La copia trae secretos de la principal:**
  - `utenti` (hashes de contraseñas);
  - en 3.0.8, sesiones;
  - `contratti` con claves de API;
  - `interconnessioni`.

  Nunca se guardan (C1).
- **Agente de usuario:** `AlojaFlow/<versión> (subordinada; +https://alojaflow.com)`, para que el
  administrador de la principal reconozca las peticiones en sus registros.
- **Riesgos que quedan en la fase 1:**
  - En 1.2.x la sincronización necesita la contraseña del administrador, que además no puede usar
    la 2FA.
  - Con GET (modo compatible), la contraseña queda en los registros de la principal.
  - Mitigaciones: un usuario dedicado cuando la principal es HotelDruid 3.0.8 (privilegio de
    copia), POST por omisión, HTTPS y el aviso en la pantalla.

---

# Parte B — Formato de la copia

## B1. Estructura

Ejemplo sintético con datos inventados, reducido. Es la forma que tiene una copia 3.081
**[visto: `crea_backup.php:276-358`, `includes/funzioni_backup.php:132-228`]**:

```
<?php exit(); ?>

<!--             2026-11-03 10:15:00             -->

<!--  **    SAVE THIS FILE AS hoteld_backup.php     **  -->

<!--  **  SALVA QUESTO FILE COME hoteld_backup.php  **  -->


<backup>
<versione>3.081</versione>
<log>NO</log>
<file>
<nomefile>./dati/lingua.php</nomefile>
<contenuto>
<?php
$lingua[1] = "es";
?></contenuto>
</file>
<database>
<tabella>
<nometabella>anni</nometabella>
<colonnetabella>
<nomecolonna>idanni</nomecolonna>
<tipocolonna>int</tipocolonna>
<nomecolonna>tipo_periodi</nomecolonna>
<tipocolonna>string</tipocolonna>
</colonnetabella>
<righetabella>
<riga><cmp>2026</cmp><cmp>g</cmp></riga>
</righetabella>
</tabella>
<tabella>
<nometabella>prenota2026</nometabella>
…
<righetabella>
<riga><cmp>7</cmp><cmp>15</cmp><cmp>Cabaña 2</cmp>…<cmp>Llega tarde.
Pidió cuna</cmp>…</riga>
</righetabella>
</tabella>
</database>
</backup>
```

- **Orden:** cabecera, `<backup>`, `<versione>`, `<log>`, bloques `<file>`, `<database>` con las
  tablas globales y después las de cada año, `</database>`, `</backup>`
  **[visto: `crea_backup.php:318-358`]**.
- **Tablas por año:** `prenota`, `prenotacanc`, `costiprenota`, `rclientiprenota`, `periodi`,
  `ntariffe`, `regole`, `soldi` y `costi`, con el año pegado al nombre (`prenota2026`).
- **Modelos de documentos:** si se pide `includi_modelli=SI`, la copia añade modelos de
  documentos tras `</database>`. AlojaFlow no lo pide.

## B2. Reglas de lectura por líneas

El formato depende de los saltos de línea. Así lo genera la principal y así lo lee su propia
restauración **[visto: `includes/funzioni_backup.php:470-585`]**:

- Las etiquetas de estructura van solas en su línea: `<backup>`, `<database>`, `<tabella>`,
  `<colonnetabella>`, `<righetabella>`, sus cierres y `<file>`, `<contenuto>` (el cierre
  `</contenuto>` va pegado al final del contenido).
- `<versione>…</versione>`, `<log>…</log>`, `<nometabella>…</nometabella>`, `<nomecolonna>`,
  `<tipocolonna>` y `<nomefile>` ocupan una línea completa.
- **Fila:** empieza en una línea que comienza por `<riga>` y termina en la primera línea que
  acaba en `</riga>`. **Un valor puede ocupar varias líneas** (comentarios, direcciones…): las
  líneas intermedias se unen con `\n`.
- Dentro de la fila, los valores van en `<cmp>…</cmp>` consecutivos, en el orden de
  `<colonnetabella>`.

**No es XML válido:** los valores no se escapan como XML y los `<file>` traen PHP. **No se debe
usar un analizador XML.**

## B3. Escapes

**Al escribir** cada valor, la principal hace en este orden **[visto: `includes/funzioni_backup.php:177-195`]**:

1. `@%&@` → `@%&@@%&@`
2. `</cmp>` → `</cmp@%&@>`

Después, sobre la fila completa:

3. `</righetabella>` → `</righetabella@%&@>`
4. `</riga>` → `</riga@%&@>`
5. `<riga>` → `<riga@%&@>`

**Al leer**, AlojaFlow:

1. separa la fila por `</cmp><cmp>` (sin el `<riga><cmp>` inicial ni el `</cmp></riga>` final);
2. en cada valor deshace, en este orden: `</righetabella@%&@>`, `</riga@%&@>`, `<riga@%&@>`,
   `</cmp@%&@>` y por último `@%&@@%&@` → `@%&@`.

Es el mismo orden que usa la restauración de la principal
**[visto: `includes/funzioni_backup.php:579-583`]**.

- El número de valores debe ser igual al número de columnas. Si no, la copia está dañada
  (SUB-010).
- **Saltos de línea:** `\r\n` dentro de un valor se normaliza a `\n`.
- **Codificación:** UTF-8 **[visto en la copia de demostración; por verificar en instalaciones
  antiguas]**. Una secuencia inválida se sustituye por U+FFFD y se cuenta en el registro de la
  ejecución.

## B4. Vacíos y nulos

La principal convierte cada valor en texto, así que **un nulo y una cadena vacía se escriben
igual**: `<cmp></cmp>` **[visto: `includes/funzioni_backup.php:176`]**. Su restauración no
inserta los valores vacíos, que quedan como nulos **[visto: `:578-586`]**. AlojaFlow hace lo mismo:
**vacío = nulo** en todas las columnas.

## B5. Tipos

`<tipocolonna>` depende del motor de la principal: con SQLite sale `unknown` **[visto en la copia
de demostración]**. **No se usa.** Los tipos los fija la correspondencia de la Parte C, a partir
del esquema 3.081 (`creadb.php`).

## B6. Bloques `<file>`

Contienen archivos de configuración de `dati/` escritos en PHP **[visto: `crea_backup.php:297-316`]**:

- siempre: `lingua.php`, `unit.php`, `unit_single.php`, `tema.php`, `selectappartamenti.php` y
  `versione.php`;
- si existen: `abilita_login`, `parole_sost.php`, `dati_interconnessioni.php`, `log_utenti.php`
  y `selectperiodi*.php` / `selperiodimenu*.php` por año y usuario.

Reglas (regla 11):

- **Nunca se ejecuta su contenido.**
- De `lingua.php` se extrae el idioma de la principal con un patrón fijo (`$lingua[1] = "xx";`).
- La **presencia** de `abilita_login` indica que la principal tiene el inicio de sesión activado
  (A3). Su contenido no se lee.
- Todo lo demás se descarta sin guardarse. `dati_interconnessioni.php` puede traer credenciales
  de canales.

## B7. Compresión

Con `compresso=SI`, la principal comprime con gzip nivel 9 y responde con
`Content-Type: application/x-gzip` **[visto: `crea_backup.php:542-571`]**. AlojaFlow reconoce
gzip por la firma `1F 8B`, no por la cabecera, y descomprime en flujo con el límite de A6.

## B8. Validaciones

La copia se rechaza (SUB-010, SUB-011 o SUB-012) si:

- la primera línea no es `<?php exit(); ?>` o falta `<backup>`;
- `<versione>` no es 3.08 ni 3.081;
- un nombre de tabla no cumple `^[a-z]+([0-9]{4})?$`, o un nombre de columna no cumple
  `^[a-z0-9_]+$`;
- una fila no tiene tantos valores como columnas;
- falta `</database>` o `</backup>` (copia cortada);
- falta una tabla obligatoria: `anni`, `versioni`, `appartamenti`, `clienti`, `personalizza` y,
  por cada año de `anni`, sus nueve tablas anuales. Es la misma comprobación que hace la
  principal al restaurar **[visto: `includes/funzioni_backup.php:27-60`]**;
- hay tablas anuales de un año que no está en `anni`;
- un valor supera 16 MB o una línea supera 32 MB (protección de memoria).

## B9. Lectura por partes

- Se lee el archivo descomprimido línea a línea, sin cargarlo entero. Las filas de las tablas que
  no se conservan se saltan sin decodificar.
- La carga se hace por lotes de 500 filas.
- Objetivo: menos de 64 MB de memoria con una copia real de 10 MB, que tiene 6 habitaciones,
  unos 1600 clientes y 8 años.
- Se ejecuta desde la cola, lanzada por el cron por línea de comandos, así que no le afecta el
  tiempo máximo de las peticiones web.

---

# Parte C — Correspondencia HotelDruid 3.081 → modelo propio

Los nombres de destino son orientativos. Los fija `modelo-datos.md`.

## C1. Qué se conserva y qué se descarta

| Tabla de la copia | Trato | Destino |
|---|---|---|
| `anni`, `versioni` | Se conservan | Años, tipo de periodos y versión |
| `appartamenti` | Se conserva | Unidades |
| `clienti`, `relclienti` | Se conservan | Huéspedes y sus datos adicionales |
| `personalizza` | Solo las filas de `idutente = 1` y las claves de C8 | Ajustes de la propiedad |
| `nazioni`, `regioni`, `citta`, `documentiid`, `parentele` | Se conservan | Catálogos de la principal |
| `descrizioni` | Se conserva | Descripciones **[por verificar uso]** |
| `casse` | Se conserva | Cajas (lectura) |
| `beniinventario`, `magazzini`, `relinventario` | Se conservan | Inventario. Sirve para el estado inicial de limpieza de 1.2.x, el artículo `cleaning` |
| `prenota*`, `prenotacanc*` | Se conservan | Reservas y canceladas |
| `rclientiprenota*` | Se conserva | Huéspedes de la reserva |
| `costiprenota*` | Se conserva | Cargos de la reserva y días de limpieza |
| `soldi*`, `costi*` | Se conservan | Movimientos de caja (lectura) |
| `periodi*`, `ntariffe*`, `regole*` | Se conservan | Periodos, tarifas y reglas (lectura; base de la fase 2) |
| `utenti` | **Se descarta** | Trae hashes de contraseñas |
| `privilegi`, `gruppi`, `relutenti`, `relgruppi` | **Se descartan** | AlojaFlow tiene sus propios usuarios y permisos |
| `sessioni`, `transazioni`, `transazioniweb`, `cache` | **Se descartan** | Sesiones y datos temporales; en 3.0.8 permitirían suplantar usuarios |
| `contratti` | **Se descarta en la fase 1** | Plantillas de documentos y claves de API (filas `tipo = api`). Se estudia para la migración de la fase 2 |
| `interconnessioni`, `messaggi` | **Se descartan** | Conexiones con otras instalaciones y mensajes internos |

## C2. Fechas, periodos, zonas y dinero

- **Periodos.** Las reservas no guardan fechas, sino identificadores de periodo
  (`iddatainizio`, `iddatafine`) de la tabla `periodiAAAA` del mismo año
  **[visto: `plugins/multipropiedad/funciones.php:158-164`]**:
  - **llegada** = `datainizio` del periodo `iddatainizio`;
  - **salida** = `datafine` del periodo `iddatafine`.

  `anni.tipo_periodi` dice si los periodos son diarios (`g`) o semanales (`s`)
  **[visto: copia de demostración, `g` con periodos de un día]**. Con periodos semanales, las
  fechas son de semana completa **[por verificar]**.
- **Fechas y horas** (`datainserimento`, `data_modifica`, `checkin`, `checkout`): están en la hora
  local de la principal (hora del servidor más `C_DIFF_ORE`), sin zona
  **[visto: `crea_backup.php:282`]**. Se interpretan con la zona configurada en A2 y se guardan en
  UTC.
- **Dinero.** Los importes vienen como decimales (`float8`). Se convierten a enteros en centavos
  redondeando a 2 decimales, mitad hacia arriba. La moneda la confirma el administrador en A2:
  `personalizza.valuta` es un texto libre, un símbolo, no un código ISO.

## C3. Unidades — `appartamenti`

| Columna | Destino | Nota |
|---|---|---|
| `idappartamenti` | Código y nombre de la unidad | **Es el nombre** (texto de hasta 100 caracteres) y la clave a la vez |
| `numpiano`, `numcasa` | Piso y edificio | Texto |
| `maxoccupanti` | Capacidad | |
| `priorita`, `priorita2` | Orden | |
| `app_vicini` | Unidades vecinas | Lista **[por verificar formato]** |
| `letto` | **[por verificar]** | |
| `commento` | Notas | |

Si cambia el nombre de una unidad en la principal, para AlojaFlow es una unidad nueva y la vieja
desaparece. **[por verificar]** cómo lo maneja la principal con las reservas que ya existen.

## C4. Huéspedes — `clienti`, `relclienti`

| Columnas | Destino |
|---|---|
| `idclienti` | Clave (propiedad, `idclienti`) |
| `cognome`, `cognome2`, `nome`, `soprannome`, `titolo`, `sesso` | Apellidos, nombre, apodo, tratamiento, sexo |
| `lingua` | Idioma del huésped (para sus mensajes) |
| `datanascita`, `cittanascita`, `regionenascita`, `nazionenascita`, `nazionalita` | Nacimiento y nacionalidad |
| `documento`, `tipodoc`, `scadenzadoc`, `cittadoc`, `regionedoc`, `nazionedoc` | Documento de identidad |
| `nazione`, `regione`, `citta`, `via`, `numcivico`, `cap` | Domicilio |
| `telefono`…`telefono3`, `fax`, `email`…`email3` | Contacto |
| `cod_fiscale`, `partita_iva` | Identificación fiscal personal y de empresa (según país: CURP/RFC, NIF/CIF…) |
| `commento` | Notas |
| `idclienti_compagni`, `doc_inviati`, `max_num_ordine` | **[por verificar]** |
| `datainserimento` | Alta en la principal |
| `hostinserimento`, `utente_inserimento` | Se descartan |

`relclienti` guarda datos adicionales por `tipo`, entre ellos los campos personalizados que usa el
plugin `tipo_cliente` **[por verificar los valores de `tipo`]**.

## C5. Reservas — `prenotaAAAA`, `prenotacancAAAA`

| Columna | Destino | Nota |
|---|---|---|
| `idprenota` | Clave (propiedad, año, `idprenota`) | C9 |
| `idclienti` | Titular | |
| `idappartamenti` | Unidad | |
| `iddatainizio`, `iddatafine` | Llegada y salida | C2 |
| `num_persone`, `cat_persone` | Personas y reparto por categoría | Formato de `cat_persone` **[por verificar]** |
| `tariffa`, `tariffesettimanali` | Tarifa aplicada y precio por periodo | Formato **[por verificar]** |
| `sconto`, `tariffa_tot`, `caparra`, `commissioni`, `tasseperc`, `pagato` | Descuento, total, depósito, comisión, % de impuestos y pagado | Dinero (C2). Pendiente = total − pagado |
| `valuta`, `metodo_pagamento` | Moneda y método de pago | **[por verificar]** |
| `codice` | Código de la reserva | **[por verificar]** |
| `origine` | Origen o canal | Texto libre: «Booking», «iCal Airbnb», «Grupo GRP-n»… |
| `commento` | Comentario | Puede traer HTML: se guarda como texto |
| `conferma` | Confirmada | Empieza por `S` (sí) o `N` (no) **[visto en la copia de demostración]** |
| `checkin`, `checkout` | Fecha y hora de entrada y salida registradas | Vacío = todavía no **[visto en la copia de demostración]** |
| `assegnazioneapp`, `app_assegnabili` | Unidad fija o reasignable | **[por verificar]** |
| `idprenota_compagna` | Reservas vinculadas | **[por verificar]** |
| `id_anni_prec` | Identificador en la tabla del año anterior | C9 |
| `incompatibilita` | **[por verificar]** | |
| `datainserimento`, `data_modifica` | Alta y último cambio en la principal | C2 |
| `hostinserimento`, `utente_inserimento` | Se descartan | |

- **Canceladas:** `prenotacancAAAA` tiene las mismas columnas; sus filas se proyectan con estado
  «cancelada».
- **Bloqueos:** los que crea el plugin `ical` de una principal 1.2.x (cliente «Bloqueo iCal»,
  comentario con `[ical]`, importe 0) se proyectan como reservas de tipo «bloqueo»
  (ver `ical.md`).
- **Grupos:** las reservas de grupo de 1.2.x (origen «Grupo GRP-n», comentario `[grupo GRP-n]`)
  se marcan con su código de grupo. Los datos del grupo no viajan en la copia: viven en archivos
  JSON de la principal.

## C6. Huéspedes, cargos, limpiezas y caja

- **`rclientiprenotaAAAA`** → huéspedes de la reserva: `idprenota`, `idclienti`, `num_ordine` y
  `parentela` (referencia a `parentele`).
- **`costiprenotaAAAA`** → cargos de la reserva: `nome`, `tipo`, `valore`, `valore_perc`,
  `moltiplica`, `categoria`, `idntariffe` (definición en `ntariffeAAAA`).
  - **Días de limpieza:** son los cargos cuyo nombre empieza por «limpieza», «cleaning» o
    «pulizi» y que tienen `associasett = s`. `settimane` es la lista, separada por comas, de los
    periodos marcados. Se cuentan solo los días entre la llegada y la salida, sin incluirlas
    **[visto: `plugins/multipropiedad/funciones.php:151-154`, `:171-174`]**. El nombre del cargo se puede
    configurar por propiedad.
- **`soldiAAAA`** → cobros de una reserva, en `pagos` (D-078): `motivazione`, `metodo_pagamento`, `saldo_prenota`,
  `data_transazione`. **[visto: `visualizza_tabelle.php:897-899`]** `motivazione` es
  `idcliente;idinizio;idfine;idprenota`; la primera fila de cada año (`soldi_prenotazioni_cancellate`) no es de una
  reserva y se ignora. El método es texto libre: se reconoce lo evidente y el resto queda como «otro», con el texto en
  la referencia.
- **`costiAAAA`** → el libro de la caja (`nome_costo`, `val_costo`, `tipo_costo` `e`/`s`, `nome_cassa`,
  `provenienza_costo`, `metodo_pagamento`, `data_transazione`). **[visto: `visualizza_tabelle.php:949`, `costi.php:211`]**
  Lo de `provenienza_costo = p` viene de una reserva y ya está en `soldi`: solo dice en qué caja entró el cobro, y su
  concepto lleva el apellido del huésped, que no se guarda. Lo demás va a `movimientos`.

## C7. Tarifas y reglas (solo lectura)

- `periodiAAAA`: `idperiodi`, `datainizio`, `datafine` y el precio de cada tarifa en
  `tariffa1`…`tariffa12` (más las columnas `tariffaNp` **[por verificar]**).
- `ntariffeAAAA`: nombres de las tarifas y definición de los costos adicionales (`*_ca`).
- `regoleAAAA`: reglas por periodo (asignación de unidades, cierres, comisiones)
  **[por verificar]**.

En la fase 1 solo se muestran: precio de la noche en el calendario y tarifa de cada reserva.
Son la base de la migración de la fase 2.

## C8. Configuración

- **`personalizza`** (filas de `idutente = 1`): `valuta` (símbolo de moneda), `stile_soldi`
  (formato de números) **[visto: `plugins/multipropiedad/funciones.php:147`]**. Más claves
  **[por verificar]**: nombre del alojamiento, campos personalizados.
- **`lingua.php`:** idioma de la principal (B6).

## C9. Claves estables e identidad

- **Clave de una reserva:** (propiedad, año de la tabla, `idprenota`). Los datos propios
  (limpieza, check-in, facturas…) **no usan esta clave**: se ligan al identificador interno de la
  reserva proyectada, que nunca cambia.
- **Reserva en dos años:** una estancia que cruza el fin de año aparece en las tablas de ambos
  años **[visto: plugins `kpi` y `multipropiedad`, que la cuentan una vez]**. Se proyecta una sola
  vez:
  - **Regla principal:** la fila del año siguiente con `id_anni_prec` apunta a la del año anterior
    **[por verificar]**.
  - **Regla de respaldo:** misma unidad, llegada, salida y titular.
- **Identificador reutilizado:** si una clave reaparece con otra huella, se trata como una
  reserva nueva, y la anterior queda «eliminada en la principal». La huella es `idclienti` más
  `datainserimento`. Así un número reutilizado no hereda la limpieza ni el check-in de otra
  reserva. **[por verificar]** si la principal reutiliza números.
- **Reserva que desaparece:** se marca «eliminada en la principal» con fecha. **Nunca se borran
  sus datos propios** de forma automática; el administrador decide.
- **Reserva que pasa a `prenotacanc`:** cambia a «cancelada» y se genera el evento
  correspondiente.

---

# Parte D — Pruebas

- **Copias sintéticas versionadas.** Un generador de pruebas construye copias con datos
  inventados que cubren:
  - escapes de B3, valores de varias líneas y vacíos;
  - dos años y una reserva que cruza el fin de año;
  - versión 3.08 sin `relinventario.scadenza`;
  - principal sin `abilita_login`;
  - copia cortada, gzip dañado y fila con valores de menos;
  - versión no admitida y tablas obligatorias que faltan.
- **Protocolo:** un falso servidor HTTP con `Http::fake` de Laravel reproduce:
  - las respuestas correctas;
  - el formulario de acceso y la espera de 5 s;
  - el límite diario y el año inexistente;
  - redirecciones, tiempo agotado y tamaño excedido.

  Comprueba también que **nunca** sale un parámetro fuera de la lista blanca ni una petición a otro
  archivo.
- **Copias reales** (regla 12): se leen desde la ruta de la variable de entorno
  `ALOJAFLOW_COPIAS_REALES`. Si no está definida, esas pruebas se omiten. Solo comprueban
  conteos, totales y que no hay errores; nunca imprimen datos.
- **Principal real de prueba:** una instalación local de AlojaFlow 1.2.3 y otra de HotelDruid
  3.0.8 (de `upstream/downloads/`) para resolver los **[por verificar]** antes de programar.

---

# Preguntas abiertas [por verificar]

**Comprobado el 2026-11-03** contra AlojaFlow 1.2.3 real (instalación nueva con SQLite y la
copia ficticia de la demo, sin inicio de sesión):

- La principal **acepta POST** en los tres pasos.
- El marcador `<!-- Backup_created -->` aparece al crear la copia.
- Con `compresso=SI` llega gzip.
- `cancella_backup` borra `dati/hoteld_backup.php`. Queda un `hoteld_backup.php.lock` vacío que
  crea la propia 1.2.x; no tiene datos.
- La proyección es correcta: 17 unidades, 974 huéspedes, 1122 reservas y 4237 noches.
- Se detectan una reserva modificada y otra eliminada.

1. Que el POST pase también con el inicio de sesión activado y detrás del cortafuegos de un
   hosting (A3).
2. Respuesta exacta cuando el año no existe y el inicio de sesión está activado (A3).
3. Textos del límite diario y del error de escritura en cada idioma de la principal (A3).
4. Reutilización de `id_sessione` entre los pasos (A3).
5. Esquema 3.081 en HotelDruid 3.0.8 original (A1).
6. Formatos de `cat_persone`, `tariffa`, `tariffesettimanali`, `app_vicini`,
   `idprenota_compagna`, `assegnazioneapp` (C3, C5).
7. Semántica de `id_anni_prec` y si la principal reutiliza números de reserva (C9).
8. Cómo se liga `soldi` a las reservas (C6).
9. Valores de `relclienti.tipo` y claves útiles de `personalizza` (C4, C8).
10. Periodos semanales (`tipo_periodi = s`) (C2).
11. Codificación de instalaciones antiguas (B3).

# Licencia y créditos

La cabecera de HotelDruid dice AGPL v3 «o cualquier versión posterior **aceptada por Marco Maria
Francesco De Santis**, que actúa como apoderado según la sección 14»
**[visto: `crea_backup.php:3-20`]**. No es exactamente «AGPL v3 o posterior». AlojaFlow reescribe
la lógica sin copiar código (regla 4), así que sigue siendo AGPL-3.0-or-later. En
`THIRD-PARTY-NOTICES.md` se cita la licencia de HotelDruid con esa redacción exacta (D-016).
