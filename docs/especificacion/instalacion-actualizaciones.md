# Instalación y actualizaciones

**Estado:** borrador 2026-11-03 · decisiones D-032 a D-034 · desde la versión 1.0.2

Una sola pieza, el **paquete de versión** `alojaflow-x.y.z.zip`, sirve para instalar desde cero
y para actualizar. Pensado para hosting compartido sin SSH: el panel avisa de la versión nueva y la baja
solo (§3.1.1), y también se puede subir el zip por el panel o por FTP.

## 1. El paquete

El zip tiene una sola carpeta, `alojaflow-x.y.z/`, con:

- lo versionado en git de esa versión, salvo `desarrollo/` (herramientas que no se instalan);
- `vendor/` **sin** las dependencias de desarrollo (`composer install --no-dev
  --optimize-autoloader`), sin `vendor/bin/`;
- los recursos que publica Filament y no están en git: `public/css/filament`,
  `public/js/filament`, `public/fonts/filament`, `public/css/alojaflow` y `public/js/alojaflow`;
- el JavaScript de Livewire, `public/vendor/livewire`, para que lo sirva Apache sin arrancar PHP
  (D-067);
- el esqueleto de `storage/` y `bootstrap/cache/` (sus `.gitignore`), para instalar desde cero;
- `manifiesto.json`: producto, versión, fecha, `php_minimo`, extensiones de PHP necesarias y el
  SHA-256 de cada archivo. Es JSON canónico (claves ordenadas): son los bytes que se firman;
- `manifiesto.firma`: firma Ed25519 (libsodium) del manifiesto, en base64.

**Reglas** (`app/Actualizacion/Rutas.php`, `Manifiesto.php`):

- Las rutas son relativas, solo con `A-Z a-z 0-9 _ . @ + ~ , = - /`, sin segmentos vacíos, `.`,
  `..` ni acabados en punto (en Windows «carpeta.» es «carpeta»), de hasta 240 caracteres.
- No lleva enlaces simbólicos.
- El manifiesto no se lista a sí mismo.
- Límites (`config/alojaflow.php`, `actualizaciones`): 300 MB el zip, 900 MB descomprimido y
  60 000 archivos.

La 1.0.2 tiene unos 15 000 archivos: 21 MB comprimida y 64 MB descomprimida.

### 1.1 Construirlo

`php artisan alojaflow:empaquetar`, en la copia de desarrollo (`app/Actualizacion/Empaquetador.php`):

1. exige que git no tenga cambios sin confirmar (con `--permitir-cambios`, solo para pruebas) y
   avisa si `CAMBIOS.md` no tiene la sección de la versión;
2. publica los recursos de Filament (`filament:assets`) y de Livewire
   (`vendor:publish --tag=livewire:assets`);
3. copia lo versionado a una carpeta de trabajo temporal y ejecuta allí `composer install --no-dev`;
4. calcula el manifiesto, lo firma con la clave secreta y escribe `dist/alojaflow-x.y.z.zip` y
   `dist/alojaflow-x.y.z.zip.sha256`;
5. revisa el zip como lo haría una instalación vacía de una versión anterior.

Tarda unos dos minutos.

### 1.2 Claves de firma

- La **clave pública** va en `config/alojaflow.php` (`actualizaciones.clave_publica`). Se puede
  cambiar con `ALOJAFLOW_CLAVE_PUBLICA_ACTUALIZACIONES`. Quien distribuya su propia versión de
  AlojaFlow, dentro de lo que permite la FSL, genera su pareja de claves y pone aquí la suya.
- La **clave secreta** vive solo en `.herramientas/clave-actualizaciones.secreta` (fuera de git)
  y **debe** tener una copia de seguridad fuera del equipo. El empaquetador comprueba que es la
  pareja de la pública.
- Si se pierde o se filtra, las instalaciones existentes ya no aceptan paquetes nuevos firmados
  con otra clave. Hay que hacer una versión con la nueva clave pública e instalarla una vez a
  mano, copiando sus archivos por FTP.

## 2. Instalación desde cero

### 2.1 Con el instalador (lo normal)

`alojaflow:empaquetar` deja también dos instaladores de **un solo archivo** (plantilla: `instalador/instalar.php`, D-071):

- `dist/instalar-alojaflow-x.y.z.php` (unos 90 KB): baja el zip firmado de GitHub (`alojaflow.actualizaciones.url_paquete`).
- `dist/instalar-alojaflow-x.y.z-completo.php`: lleva el zip dentro, después de `__halt_compiler`, para el servidor que no sale a internet.

1. Se sube ese archivo a la carpeta del dominio o subdominio, y se abre en el navegador.
2. **Revisa el servidor** y dice en lenguaje claro qué falta: PHP, extensiones, permisos, espacio,
   memoria, límites, HTTPS, si la carpeta ya tiene otros archivos y si puede bajar el paquete. Lo rojo impide seguir.
   Si no hay nada en rojo pasa directo al siguiente paso, con el detalle a un clic.
3. **Pide solo lo básico:** nombre, usuario y contraseña de la base, con una guía por hosting, y nombre, correo y
   contraseña del super administrador, con un texto que explica lo que es (D-070). El servidor se busca solo
   (`localhost`, `127.0.0.1`, `mysql.tudominio.com`); servidor y puerto van en «Avanzado». Antes de seguir prueba la conexión,
   la versión de la base, que esté vacía y que el usuario pueda crear tablas.
4. **Consigue el paquete:** el suyo, uno junto a él, o lo baja por pedazos de 8 MB. Si no puede bajarlo, ofrece
   subirlo desde la computadora en partes de 1 MB, o por FTP.
5. **Hace lo demás:** comprueba la firma, copia los archivos por tandas (no le afecta un límite de
   30 segundos), escribe el `.env` con su `APP_KEY`, crea las tablas y al super administrador, comprueba
   que el sistema abre y se borra solo.
6. **Entrada directa:** el botón final lleva un enlace de un solo uso que caduca en 30 minutos. Primero salen las preguntas
   que deciden qué módulos se prenden (D-069), y luego los primeros pasos en el inicio, entre ellos el cron.

**Dónde queda instalado.**

- **El dominio apunta a la carpeta donde se subió** (lo más sencillo): la instalación queda ahí y el
  `.htaccess` de la raíz manda todo a `public/`. El navegador comprueba que `composer.json`,
  `.env.example` y `artisan` ya no se pueden abrir **antes de escribir el `.env`**. Si no están
  protegidos (por ejemplo, en nginx), el instalador se detiene y pide apuntar el dominio a `public/`,
  donde ya dejó una copia suya para continuar.
- **El dominio ya apunta a una carpeta `public/`** vacía: la instalación va en la carpeta de arriba.
- **Una subcarpeta** (`dominio.com/sistema/`) también funciona: `public/index.php` corrige la ruta.

**Seguridad del instalador.**

- Va sin sintaxis moderna: se puede leer en un PHP viejo para avisar de que es viejo.
- Solo lo usa el navegador que lo abrió primero (cookie y candado en `.alojaflow-instalacion/`, que
  caduca a las 6 horas).
- No toca una instalación terminada (`storage/app/private/instalado.json`).
- Solo extrae lo que lista el manifiesto firmado, y comprueba cada archivo.

Para probarlo como en un hosting real: `desarrollo/apache-prueba/` arranca el Apache de Wamp, que sí
lee `.htaccess`, y `probar-instalador.php` hace todo el recorrido.

### 2.2 A mano

1. Descomprimir el zip: su carpeta es la instalación. La raíz pública del dominio **debe** ser
   `public/` o la carpeta de la instalación con su `.htaccess`.
2. Crear la base MySQL 8 o MariaDB 10.6+ y el `.env` a partir de `.env.example`:
   - `APP_KEY`, con `php artisan key:generate`;
   - `APP_URL`;
   - `DB_*`.
3. `php artisan migrate --force --seed` y `php artisan alojaflow:crear-administrador correo`.
4. Un cron cada minuto: `php artisan schedule:run`.

`manifiesto.json` y `manifiesto.firma` quedan en la raíz. La siguiente actualización los usa para
saber qué archivos obsoletos puede borrar. Al primer arranque Laravel genera
`bootstrap/cache/packages.php` solo; no hace falta `package:discover` ni `filament:assets`.

### 2.3 El programador sin cron

Las tareas programadas corren con el cron (`schedule:run`). Si no hay cron, o el hosting no lo
permite, corren por turnos al terminar de atender una visita, como mucho una vez por minuto y con un
tiempo máximo (`App\Programador\TareasPorVisita`, D-039). Cuando el cron da señales de vida, esto se
apaga solo. **Sistema → Estado del sistema** dice cuál de los dos está funcionando. La primera tarea del horario,
`esquema`, pone la base al día si los archivos cambiaron sin pasar por el navegador (D-171), como cada petición.
Durante una actualización solo arrancan las tareas que corren en mantenimiento, aunque la ronda haya empezado antes
(D-172).

**Con un cron que no corre cada minuto** (DreamHost: cada 10), o cuando no se sabe en qué minuto arranca, se usa
`php artisan alojaflow:tareas-pendientes` en lugar de `schedule:run` (D-169). Toma el mismo horario
(`routes/console.php`) y corre cada tarea que **tocó desde su última vez**, una sola vez aunque le haya tocado
varias: la copia de las 3:10 corre en la primera ronda después de las 3:10, empiece a la hora que empiece.

- La última vez de cada tarea se guarda en `ajustes` (`programador.ultima.*`); `schedule:run` también la anota, así
  que los dos no repiten lo de hora fija. Una tarea que nunca ha corrido cuenta solo lo que le tocó en los últimos
  15 minutos (`--ventana=`).
- Se marca antes de correr, bajo un candado: dos rondas encimadas no corren la misma tarea. Las que llevan
  `withoutOverlapping` siguen respetando su candado.
- Una tarea que falla no detiene a las demás y no se repite hasta que le vuelva a tocar. Tampoco una que no se puede
  revisar (su filtro falla): sale como `fallida`.
- Antes de las tareas pone la base al día, como hace cada petición (`EsquemaActualizado`, D-089): en una instancia
  el código cambia sin que nadie la abra. Si hubo algo que hacer, el JSON lo trae como la tarea `esquema`. Si el
  proceso arrancó con las cachés de la versión anterior, las quita y no corre nada hasta la ronda siguiente.
- `--json` responde `{"inicio", "duracion_ms", "corridas", "fallidas", "tareas": [{"tarea", "estado", "debia",
  "duracion_ms", "error"?}]}`, con `estado` `correcta`, `fallida` u `ocupada` (la corre otro proceso) y `error`
  solo la clase de la excepción o `exit N`. Sale con **0** si nada falló, **1** si falló alguna y **2** si la
  ventana no es válida.
- Anota el latido del programador como `schedule:run`: con él las tareas por visita (arriba) se apagan solas.

### 2.4 Como instancia del servicio alojado (D-162)

AlojaFlow App corre una instancia por cliente sobre un código compartido. Una instancia es una carpeta:

```
instancias/posadaluna/
├── .env        su llave, su base (con su propio usuario de MySQL) y su dirección
├── cache/      config.php, routes-v7.php y events.php (su bootstrap/cache)
├── storage/    sus archivos, sesiones, caché y registros
└── publico/    index.php: fija ALOJAFLOW_INSTANCIA y requiere el public/index.php de la versión
```

- `ALOJAFLOW_INSTANCIA` (en `$_SERVER` o en el entorno) apunta a la carpeta. `bootstrap/app.php` toma de ahí el
  `.env`, el `storage` y las cachés, y `public/index.php` la página de mantenimiento.
- Si la carpeta no es de una instancia, o el entorno trae otra `APP_KEY` u otra base, **no arranca**.
- El código de la versión es de solo lectura y lo comparten todas: `packages.php`, `services.php` y los
  componentes de Filament se generan al instalar la versión; una instancia no los toca.
- No hay actualizaciones propias ni tareas por visita (§2.3): las actualizaciones las lanza AlojaFlow App, y las
  tareas, con `alojaflow:tareas-pendientes` (§2.3): sus instancias arrancan una tras otra, no todas en el minuto
  exacto.
- Alta sin navegador: `php artisan alojaflow:instalar --nombre= --correo= --idioma= --json` (si ya estaba
  instalada no toca nada) y `php artisan alojaflow:enlace-entrada --json` (enlace de un solo uso, 30 minutos).
- **Cada instancia vive en una ruta** del mismo dominio, `app.alojaflow.com/<cliente>` (D-163): el servidor web
  pasa `SCRIPT_NAME=/<cliente>/index.php`, `APP_URL` lleva la ruta y `SESSION_PATH=/<cliente>` deja las
  galletas solo ahí. Como todas comparten el origen del navegador, nada de lo que escribe un alojamiento se
  ejecuta en él.

## 3. Actualizar

### 3.1 Desde el panel

**Sistema → Actualizaciones**, solo para el super administrador (`app/Filament/Pages/Actualizaciones.php`):

1. **Subir** el zip.
   - Va por partes de 1 MB (`App\Actualizacion\SubidaPorPartes`, D-072), así que no le afecta `upload_max_filesize`.
     Si se corta, se reintenta desde donde iba. Al terminar se revisa solo.
   - También se puede copiar por FTP a `storage/app/private/actualizaciones/entrada/`.
   - Los zips de esa carpeta aparecen en la lista. Su nombre se sanea y se pueden eliminar.
2. **Revisar** sin cambiar nada:
   - firma, versión, requisitos y cada archivo;
   - qué hará: archivos nuevos, que cambian, obsoletos que se borran y sin cambios;
   - las notas de esa versión en `CAMBIOS.md`.
3. **Actualizar**, confirmando con la contraseña.

El historial muestra la fecha, las versiones, el estado, el usuario y el detalle de cada
actualización, con su copia de la base de datos para descargar.

### 3.1.1 En línea (D-140)

Se añade solo lo que faltaba, **avisar** y **bajar**. Lo que da la seguridad es la firma, no el sitio de donde baja:
después de bajar se sigue el mismo camino de §3.1 (revisar, contraseña, las dos fases de §4). **Nunca se instala sola.**

**Avisar** (`App\Actualizacion\UltimaVersion`, comando `alojaflow:buscar-version`):

- Una vez al día (03:50, o por visitas si no hay cron) se hace un `GET` a `actualizaciones.url_ultima_version`, que por omisión es
  `https://api.github.com/repos/juanelodi/AlojaFlow/releases/latest`. Sin parámetros ni cuerpo: no viaja ningún dato de la
  instalación ni su versión. GitHub solo ve la dirección del servidor, y eso lo dice la pantalla.
- La respuesta se guarda en el ajuste `actualizaciones.ultima_version`: versión, notas de la publicación y enlace, además de cuándo se preguntó.
  Si la versión no es más nueva que la instalada, no se avisa. Al instalarla, el aviso se va solo, sin borrar nada.
- **Se apaga** con la casilla de Sistema → Actualizaciones (ajuste `actualizaciones.avisar`). «Buscar ahora» funciona aunque esté apagada.
- **Si GitHub no responde** (sin red, 500, límite de la API, otra cosa en lugar de la respuesta): el comando termina bien, queda anotado en la pantalla y en el
  registro, y se conserva lo último que se supo. En la demo no se pregunta.
- **Quién lo ve:** solo el super administrador. En Actualizaciones, con lo que cambia; en el inicio (`Resumen`), un aviso con enlace, y una insignia en el menú.
- Las notas son texto de fuera: se muestran como las de `CAMBIOS.md`, sin HTML ni enlaces inseguros, y el enlace solo se acepta si es `https://`.

**Bajar** (`App\Actualizacion\DescargaPorPedazos`, `POST /actualizaciones/descargar-pedazo`), con el botón «Actualizar ahora»:

- **Por pedazos de 8 MB, uno por petición,** como el instalador: el JavaScript de la pantalla los pide uno tras otro y muestra el avance. Una descarga larga la
  cortaría el hosting.
- **Se reanuda:** lo bajado queda en `storage/app/private/actualizaciones/descargas/alojaflow-x.y.z.zip.parcial`, y su tamaño dice desde dónde seguir, aunque se cierre la
  página. Cada pedazo se escribe aparte y se suma solo si es el rango pedido y llegó completo. Si un pedazo falla, el navegador lo intenta dos veces más antes de avisar.
- **No se elige qué bajar:** el servidor usa la última versión conocida; la petición no lleva parámetros.
- **De dónde:** solo la dirección de `actualizaciones.url_paquete`. Cada redirección se comprueba con su host, y siempre con `App\Seguridad\ClienteExterno`: https, redes
  públicas y la IP fija en la conexión. La única excepción es necesaria para que funcione: GitHub guarda los archivos de sus versiones en
  `*.githubusercontent.com` (hoy `release-assets.githubusercontent.com`) y redirige ahí, así que ese dominio vale cuando el paquete viene de `github.com`, y solo entonces.
  Quien ponga otro `ALOJAFLOW_URL_PAQUETE` debe servir el zip sin redirigir a otro host.
- **Antes de la lista de paquetes:** al completarse se comprueba la firma, y que el manifiesto sea justo de esa versión (`Revisor::firmadoPorQuienPublica`). Si no, se borra y otro
  intento empieza de cero. Así, un zip dañado o una versión vieja con firma buena nunca llegan a `entrada/`. Cada archivo y los requisitos los revisa después `Revisor::revisar`.
- **Otros casos:** un servidor que no entiende de rangos (200) deja el archivo completo; lo que pase del tope de 300 MB se corta y se borra. En una copia de desarrollo o con una actualización en marcha no se baja.
- Lo que nadie terminó de bajar lo borra `alojaflow:limpiar` a los 7 días; también se borra lo que quedó de otra versión.

Al terminar, el zip está en `entrada/` igual que uno subido a mano y se revisa sin otro clic. **Subir el zip a mano se conserva** para el servidor que no sale a internet.

### 3.2 Desde la consola

```bash
php artisan alojaflow:actualizar ruta/al/alojaflow-x.y.z.zip [--reinstalar] [--force]
```

Hace la misma revisión y las mismas dos fases. La segunda corre en otro proceso, ya con el código
nuevo.

### 3.3 Reglas

- Solo se instalan paquetes firmados con la clave de quien publica. Además, el zip no puede traer
  nada fuera del manifiesto y cada archivo tiene que coincidir con su hash.
- No se instalan versiones anteriores. La misma solo se instala marcando «Reinstalar».
- **Nunca se escriben ni se borran:**
  - `.env` y `.env.*` (salvo `.env.example`);
  - `storage/` y `bootstrap/cache/`;
  - `public/storage` y `public/hot`;
  - `.git/`, `.herramientas/`, `.claude/`, `node_modules/` y `dist/`.
- **Archivos con cambios locales:**
  - un obsoleto (está en el manifiesto instalado pero no en el nuevo) solo se borra si sigue
    igual que en la versión instalada; si se cambió en la instalación, se conserva y se avisa;
  - si se va a sustituir un archivo con cambios locales, se avisa y el original queda en la copia.
- Sin manifiesto instalado válido no se borra ningún obsoleto, y se avisa.
- En una copia de desarrollo (con `.git`) no se actualiza ni desde el panel ni desde la consola
  (D-034).

### 3.4 Sin nadie delante (D-151)

`alojaflow:actualizacion-automatica` corre cada hora con la tarea programada. Instalar tarda más de lo
que dura una visita, así que **sin cron no se instala nada solo**: solo se consulta la orden urgente
(`--solo-consultar`, en `TareasPorVisita`) para avisar en el panel.

- **Automáticas** (*Sistema → Actualizaciones*, apagadas al empezar): a las 3:00 de la instalación se
  pregunta a GitHub por la última versión y, si es más nueva, se instala. La hora es la de la zona elegida
  ahí; si nadie eligió, la de la primera propiedad.
- **Urgentes** (prendidas al empezar, se pueden apagar): cuando el equipo de AlojaFlow corrige una
  vulnerabilidad, ordena en dev.alojaflow.com pasar a esa versión. Cada instalación lo consulta cada hora
  (`GET` a `url_urgente`, sin ningún dato) y la instala sin esperar a las 3:00. Quien puede actualizar ve
  el aviso en todas las pantallas mientras esté pendiente.

En los dos casos: solo una versión con formato `x.y.z` y **más nueva** que la instalada, bajada de
`url_paquete` (GitHub) por `DescargaPorPedazos` y revisada por su firma como siempre; luego
`alojaflow:actualizar --force`, con su copia de la base y su diario. Quien controlara dev.alojaflow.com no
podría instalar nada que no sea una versión publicada y firmada. Nada en una copia con `.git`, con una
actualización en marcha ni en la demostración. Si falla, se anota (`actualizacion.automatica` en la
auditoría y en la pantalla) y esa versión no se reintenta en 20 horas.

## 4. Cómo se aplica: dos fases

Cada fase es una petición firmada, solo para administradores y exenta del modo mantenimiento
(`app/Http/Controllers/ActualizacionController.php`, `bootstrap/app.php`). El código está en
`app/Actualizacion/Actualizador.php` y `Aplicador.php`.

**Fase 1, aplicar** (código viejo, `GET /actualizaciones/{id}/aplicar`):

1. El estado pasa de `preparada` a `aplicando` una sola vez. Lo decide la base de datos, así que
   recargar la página no repite nada.
2. Se comprueba que el zip no cambió desde que se preparó y se revisa otra vez.
3. Los archivos nuevos o que cambian se extraen a `…/actualizaciones/{id}/nuevo/` y se comprueba
   su hash. La aplicación sigue funcionando mientras tanto.
4. Entra en mantenimiento con la página ya generada (`down --render`), que se muestra aunque la
   aplicación no arranque: 503, `Retry-After` y recarga cada 15 s. Después se copia la base de
   datos a `base-datos.sql.gz`.
5. Intercambio: cada original se mueve (rename) a `anterior/` y cada archivo nuevo a su sitio; los
   obsoletos van a `anterior/`. Antes de cada paso se anota en `diario.log`. Son segundos.
6. Se borran las cachés de arranque (`bootstrap/cache/*.php`), que pueden nombrar clases de la
   versión anterior.
7. El estado pasa a `archivos_aplicados` y el navegador va a la fase 2 con la cookie que le deja
   seguir dentro durante el mantenimiento.

**Fase 2, finalizar** (código nuevo, `GET /actualizaciones/{id}/finalizar`):

1. `package:discover` y `migrate --force`.
2. Se borran las cachés de configuración, rutas, eventos, vistas y componentes de Filament, y al terminar se vuelven a
   crear las de arranque (D-060).
3. El manifiesto firmado se copia a la raíz y el zip sale de la carpeta de entrada.
4. Fin del mantenimiento (`up`) y estado `completada`.

### 4.1 Si algo falla

| Cuándo | Qué pasa |
|---|---|
| Al revisar | No se cambia nada. |
| Al extraer, copiar la base de datos o intercambiar | Se deshace según el diario, fin del mantenimiento y estado `fallida`. |
| En las migraciones | Se deshace según el diario, fin del mantenimiento y estado `revertida`. Las migraciones de MySQL no se deshacen solas: el detalle pide restaurar la copia de la base de datos si la aplicación da errores. |
| Se cierra el navegador entre las dos fases | Queda en mantenimiento con los archivos nuevos. El programador la termina pasados 2 minutos. El administrador, con su cookie, ve en todas las pantallas «Terminar la actualización». |
| El proceso muere a medias (tiempo límite del hosting) | El diario permite deshacerla. El programador lo hace pasados 30 minutos, cuando se suelta el candado. |
| Sin cron ni panel | `php artisan alojaflow:finalizar-actualizacion`. Sin SSH: borrar `storage/framework/down` por FTP para salir del mantenimiento y restaurar `anterior/` a mano. |

El diario tiene una línea por paso, `acción<TAB>ruta`, con las acciones `sustituye`, `crea` y
`borra`. Se deshace del último paso al primero y se puede repetir sin daño: cada paso mira lo que
encuentra.

### 4.2 Contrato entre versiones

La fase 2 de la versión nueva termina lo que empezó la fase 1 de la anterior. Por eso **no deben
cambiar entre versiones**:

- los estados y sus valores;
- la carpeta de cada actualización: `nuevo/`, `anterior/`, `diario.log` y su formato,
  `manifiesto.json`, `manifiesto.firma` y `base-datos.sql.gz`;
- las rutas `actualizaciones/{id}/aplicar` y `actualizaciones/{id}/finalizar`, exentas del
  mantenimiento;
- la tabla `actualizaciones`.

Si alguna vez hace falta cambiarlo, la versión nueva tiene que aceptar las dos formas.

## 5. Copias y conservación

- `storage/app/private/actualizaciones/{id}/` guarda la copia de la base de datos, los archivos
  anteriores, el diario y el manifiesto.
- Se conservan las de las **3 últimas** actualizaciones (`copias_conservadas`), porque llevan
  datos personales. `alojaflow:limpiar` borra las demás.
- La copia de la base de datos es SQL comprimido, sin depender de `mysqldump`. No lleva los
  datos de `cache`, `sessions` ni las colas, ni las columnas generadas.
- **Restaurar la base de datos:** `gunzip -c base-datos.sql.gz | mysql -u USUARIO -p BASE`, o
  importarla con phpMyAdmin.
- **Restaurar los archivos:** copiar `anterior/` sobre la instalación.

## 6. Comandos

| Comando | Qué hace |
|---|---|
| `alojaflow:empaquetar [--permitir-cambios] [--clave=] [--composer=] [--salida=]` | Construye y revisa el zip firmado de la versión de `config/alojaflow.php` (§1.1) |
| `alojaflow:actualizar {zip} [--reinstalar] [--force]` | Actualiza desde la consola (§3.2) |
| `alojaflow:buscar-version` | Pregunta cuál es la última versión para avisar (§3.1.1). No instala nada. Una vez al día, si no se apagó. |
| `alojaflow:tareas-pendientes [--ventana=15] [--json]` | Corre las tareas programadas que tocaron desde su última vez, sin depender del minuto (§2.3, D-169). |
| `alojaflow:finalizar-actualizacion [--actualizacion=ID] [--pendientes]` | Termina una actualización con los archivos puestos o deshace una que se cortó. El programador la lanza cada minuto con `--pendientes`, también en mantenimiento. |

## 7. Seguridad

- Solo administradores, con la contraseña para confirmar. Las URL de las fases van firmadas y
  caducan (30 minutos la primera y 1 hora la segunda).
- Auditoría: `actualizacion.preparar`, `actualizacion.completada`, `actualizacion.revertida` y
  `actualizacion.recuperada`.
- El zip subido o bajado se guarda con un nombre saneado en una carpeta privada. Nunca se ejecuta y no se
  extrae nada sin firma válida; solo se extrae lo que lista el manifiesto, con su ruta validada.
- La cookie que deja pasar al administrador durante el mantenimiento (`laravel_maintenance`) va
  sin cifrar, porque la lee `storage/framework/maintenance.php` antes de arrancar Laravel. Va
  firmada con un secreto nuevo en cada actualización.

## 8. Probar

- **Pruebas automáticas:**
  - `tests/Feature/ActualizacionTest.php`: actualización completa, reversión por migraciones,
    paquetes alterados, versiones, copia de git, interrupción, programador, panel, subida, aviso
    y limpieza;
  - `tests/Feature/ActualizacionEnLineaTest.php`: aviso, GitHub caído, descarga cortada y reanudada, firma inválida, redirecciones y permisos, con `Http::fake`;
  - `tests/Unit/PaqueteActualizacionTest.php`: rutas, manifiesto y firma;
  - los paquetes de prueba se firman con una clave de prueba (`tests/Support/ConstructorPaquete.php`).
- **Instalación de prueba en Windows** (la copia de desarrollo no se actualiza, D-034):
  - descomprimir el zip en `E:\AlojaFlow-prueba` con su propia base `alojaflow_prueba`;
  - servirla con `desarrollo/servidor-instalacion.php`, que sube los límites de subida
    (configuración `alojaflow-prueba` de `.claude/launch.json`, http://localhost:8001).
- **Comprobado el 2026-11-06** contra GitHub de verdad, con una instalación local que simulaba la 1.0.32: preguntó, avisó de la 1.0.33 y bajó su zip de
  30 MB en 4 pedazos, siguiendo la redirección, con la firma real y sin pasar de 66 MB de memoria. La pantalla muestra el avance y la revisión. No se instaló: el aplicar
  es el de siempre. Falta probarla en un hosting de verdad. El PHP de Wamp no trae certificados: para esta prueba en Windows hay que darle `curl.cainfo` y
  `openssl.cafile`, o la pantalla dirá que GitHub no respondió.
- **Comprobado el 2026-11-03** con una clave de prueba:
  - instalación desde el zip de la 1.0.2;
  - de la 1.0.2 a la 1.0.3 por consola, con una migración nueva y un archivo borrado;
  - una 1.0.4 con una migración que falla, revertida;
  - de la 1.0.3 a la 1.0.5 desde el panel;
  - la página de mantenimiento y la descarga de la copia.

## 9. Pendiente

- Avisar en el panel de que hay una versión nueva y descargarla desde ahí, con la firma como
  garantía.
- Publicar los zips firmados como *releases* de GitHub.
