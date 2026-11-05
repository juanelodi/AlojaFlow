# Instalación y actualizaciones

**Estado:** borrador 2026-11-03 · decisiones D-032 a D-034 · desde la versión 1.0.2

Una sola pieza, el **paquete de versión** `alojaflow-x.y.z.zip`, sirve para instalar desde cero
y para actualizar. Pensado para hosting compartido sin SSH: el zip se sube por el panel o por
FTP.

## 1. El paquete

El zip tiene una sola carpeta, `alojaflow-x.y.z/`, con:

- lo versionado en git de esa versión, salvo `desarrollo/` (herramientas que no se instalan);
- `vendor/` **sin** las dependencias de desarrollo (`composer install --no-dev
  --optimize-autoloader`), sin `vendor/bin/`;
- los recursos que publica Filament y no están en git: `public/css/filament`,
  `public/js/filament`, `public/fonts/filament` y `public/css/alojaflow`;
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
  AlojaFlow (AGPL) genera su pareja de claves y pone aquí la suya.
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
apaga solo. **Sistema → Estado del sistema** dice cuál de los dos está funcionando.

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
| `alojaflow:finalizar-actualizacion [--actualizacion=ID] [--pendientes]` | Termina una actualización con los archivos puestos o deshace una que se cortó. El programador la lanza cada minuto con `--pendientes`, también en mantenimiento. |

## 7. Seguridad

- Solo administradores, con la contraseña para confirmar. Las URL de las fases van firmadas y
  caducan (30 minutos la primera y 1 hora la segunda).
- Auditoría: `actualizacion.preparar`, `actualizacion.completada`, `actualizacion.revertida` y
  `actualizacion.recuperada`.
- El zip subido se guarda con un nombre saneado en una carpeta privada. Nunca se ejecuta y no se
  extrae nada sin firma válida; solo se extrae lo que lista el manifiesto, con su ruta validada.
- La cookie que deja pasar al administrador durante el mantenimiento (`laravel_maintenance`) va
  sin cifrar, porque la lee `storage/framework/maintenance.php` antes de arrancar Laravel. Va
  firmada con un secreto nuevo en cada actualización.

## 8. Probar

- **Pruebas automáticas:**
  - `tests/Feature/ActualizacionTest.php`: actualización completa, reversión por migraciones,
    paquetes alterados, versiones, copia de git, interrupción, programador, panel, subida, aviso
    y limpieza;
  - `tests/Unit/PaqueteActualizacionTest.php`: rutas, manifiesto y firma;
  - los paquetes de prueba se firman con una clave de prueba (`tests/Support/ConstructorPaquete.php`).
- **Instalación de prueba en Windows** (la copia de desarrollo no se actualiza, D-034):
  - descomprimir el zip en `E:\AlojaFlow-prueba` con su propia base `alojaflow_prueba`;
  - servirla con `desarrollo/servidor-instalacion.php`, que sube los límites de subida
    (configuración `alojaflow-prueba` de `.claude/launch.json`, http://localhost:8001).
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
