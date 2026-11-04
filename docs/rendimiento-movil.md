# Rendimiento del panel en un teléfono con mala red

**Fecha:** 2026-11-04 · **Decisión:** D-067 · Resuelve lo que la auditoría de noviembre dejó pendiente
(`docs/auditoria-2026-11.md`): Hoy daba 56 puntos y 9.8 s hasta el contenido principal en Lighthouse móvil.

## Resultado

Una instalación nueva hecha con el paquete, como la que midió la auditoría:

| Página, móvil | Auditoría | Ahora |
|---|---|---|
| Hoy | 56 puntos · 9.8 s | **99 puntos · 1.5 s** |
| Calendario | sin medir | **99 puntos · 1.5 s** |

Para comparar cada paso, la misma demo en cada estado. «Sin compresión» es como la midió la auditoría; «con compresión», el
código de antes en un servidor que comprime por su cuenta. Son puntos y tiempo hasta el contenido principal (LCP).

**Lighthouse limitando la red y la CPU de verdad** (mediana de tres). Es lo más cercano a un teléfono:

| Pantalla | Sin compresión | Con compresión | Ahora |
|---|---|---|---|
| Hoy | 39 · 10.4 s | 64 · 4.4 s | **82 · 2.1 s** |
| Calendario | 34 · 12.2 s | 63 · 4.6 s | **84 · 2.0 s** |
| Calendario, 40 unidades | | 49 · 5.5 s | **79 · 2.1 s** |
| Reservas | 32 · 12.1 s | 60 · 4.6 s | **78 · 1.9 s** |
| Crear reserva | | 64 · 4.5 s | 67 · 4.3 s |

**La segunda visita**, que es la de todos los días y Lighthouse no mide. LCP, mediana de tres:

| Pantalla | Sin compresión | Con compresión | Ahora |
|---|---|---|---|
| Hoy | 2.5 s | 1.4 s | **0.8 s** |
| Calendario | | 1.5 s | **0.8 s** |
| Reservas | | 1.6 s | **0.7 s** |

**Lighthouse simulado** (mediana de cinco), la misma prueba de la auditoría:

| Pantalla | Sin compresión | Con compresión | Ahora |
|---|---|---|---|
| Hoy | 59 · 9.8 s | 91 · 3.3 s | **98 · 1.5 s** |
| Calendario | 55 · 11.1 s | 89 · 3.3 s | **93 · 3.1 s** (ver abajo) |
| Reservas | 54 · 10.8 s | 88 · 3.4 s | **96 · 1.5 s** |
| Crear reserva | | 86 · 3.8 s | 87 · 3.7 s |
| Inicio de sesión | | 100 · 1.4 s | 100 · 1.4 s |
| Hoy, escritorio | | 100 · 0.7 s | 100 · 0.6 s |

**Lo que casi no mejora:** el tiempo hasta que la página responde del todo, porque depende de bajar e interpretar Livewire, Alpine y
Filament. Con Alpine ya arrancado: 4.0 s la primera visita (antes 4.1 s) y 1.2 s las siguientes (igual). El bloqueo del hilo principal
sigue en unos 650 ms con la CPU limitada, repartido por todo Alpine, sin un punto caro que quitar. En el calendario de 40 unidades sí
baja, de 1521 a 773 ms.

## Cómo se midió

- La rama y una copia de `main`, cada una en el Apache de Wamp (`desarrollo/apache-prueba/`), con el mismo PHP y la misma base
  privada, en producción y con las cachés de arranque, con el dominio apuntando a `public/` como en DreamHost.
- La demo: 12 unidades y 301 reservas. Para el hotel grande se le añadieron 28 unidades y 167 reservas. Un cron simulado apaga las
  tareas por visita, para que no metan ruido.
- Lighthouse 13.6, perfil móvil (`desarrollo/rendimiento/medir.mjs`). **Simulado** mide en un equipo rápido y calcula lo que tardaría.
  **Limitando de verdad** (`--aplicada`) son 1.47 Mbps, 562 ms por petición y la CPU cuatro veces más lenta.
- `desarrollo/rendimiento/visita-repetida.mjs` aplica esos mismos límites y carga cada pantalla dos veces.
- También se construyó el paquete con el `Empaquetador` y se instaló con `probar-instalador.php`.

**Lo que no se midió:** DreamHost ni un teléfono real. Allí el servidor tarda más en responder que aquí (0.15 s en este equipo) y esa
diferencia se suma a todas las cifras.

**Por qué no basta el simulado.** En el calendario salen 3.1 s en tres de cinco pasadas y 1.5 s en las otras dos, con la misma página.
Todas pintan a los 0.4 s, pero en un servidor local el JavaScript llega antes de pintar: si termina unos milisegundos antes, Lighthouse
da por hecho que el contenido depende de él y le suma su descarga. Limitando de verdad no pasa: siempre son 2.0 s.

## Qué pesaba

El `/hoy` de la demo, en bytes transferidos:

| Recurso | Sin comprimir | Comprimido | Nota |
|---|---|---|---|
| `css/filament/filament/app.css` | 676 KB | 68 KB | Bloquea el primer pintado. |
| `livewire.min.js` | 256 KB | 85 KB | Lleva Alpine dentro. Lo servía PHP. |
| HTML | 234 KB | 24 KB | |
| `js/filament/support/support.js` | 192 KB | 65 KB | |
| `js/filament/filament/echo.js` | 90 KB | 24 KB | Nadie lo usa. |
| Inter, `latin` | 67 KB | 67 KB | `woff2` ya va comprimido. Se precargaba. |
| `js/filament/tables/tables.js` | 30 KB | 10 KB | |
| `css/alojaflow/alojaflow.css` | 20 KB | 5 KB | |
| `js/filament/filament/app.js` | 19 KB | 7 KB | |
| Logos, `notifications`, `schemas`, `actions`, `index.css` e icono | 39 KB | 13 KB | |
| **Total** | **1623 KB** | **368 KB** | Ahora, 344 KB. |

El HTML de cada pantalla, sin comprimir y comprimido:

| Pantalla | Antes | Ahora |
|---|---|---|
| Hoy | 234 KB · 24 KB | igual |
| Reservas | 438 KB · 31 KB | igual: es de Filament |
| Calendario, 12 unidades | 527 KB · 31 KB | 259 KB · 25 KB |
| Calendario, 40 unidades | 1427 KB · 58 KB | 504 KB · 35 KB |

## Las dos causas

**Nada se comprimía.** `public/.htaccess` era el de Laravel, así que dependía de la configuración de cada hosting. Solo con comprimir,
Hoy pasa de 59 a 91 puntos.

**El contenido estaba invisible hasta el final.** Con `sidebarCollapsibleOnDesktop()`, Filament deja `.fi-main-ctn` en `opacity: 0` hasta
que arranca Alpine, y Alpine viene en el último script. Sirve para que en el escritorio no salte el contenido al saber si la barra
lateral está plegada. En el teléfono, aunque el HTML y los estilos ya hayan llegado, solo se ve la barra de arriba hasta que baja e
interpreta todo el JavaScript. Por eso el contenido tardaba el doble que el primer pintado, también con compresión.

## Qué se cambió

1. **`public/.htaccess` comprime y deja en caché.** Brotli si el hosting lo tiene, si no gzip. Lo que lleva versión en la dirección
   (`?v=`, `?id=`) y las fuentes, un año y sin volver a comprobarlo; el logo y el icono, una semana. Se probó sin los módulos, con Brotli y
   gzip a la vez, con solo los permisos que ya pedía Laravel, y con el dominio en `public/` y en la raíz de la instalación.
2. **En el teléfono, el contenido se ve sin esperar a Alpine** (`alojaflow.css`, menos de 64rem, pantallas sin `<form>`). Allí la barra
   lateral va por encima y no mueve nada. Se revisaron 19 pantallas sin JavaScript y no sale nada fuera de lugar.
3. **Los formularios se quedan como estaban.** Sin JavaScript sus campos salen vacíos y lo que se escriba se perdería al arrancar
   Livewire. Por eso Crear reserva casi no mejora.
4. **Los filtros traen su valor en el HTML**, para que las fechas no aparezcan vacías mientras tanto.
5. **Sin `echo.js`** (`App\Filament\RecursosDelPanel`). Filament lo manda siempre y solo sirve para avisos en vivo, que no están
   configurados. Si alguien configura `filament.broadcasting.echo`, vuelve solo.
6. **Sin precargar Inter.** La precarga competía con `app.css` por el ancho de banda: quitarla adelanta el primer pintado de 2.2 a 1.8 s
   junto con lo demás. El precio es un pequeño salto cuando llega la fuente, solo en la primera visita: CLS de 0.03 en Calendario y 0.003
   en Hoy (bueno es menos de 0.1).
7. **Livewire como archivo** en `public/vendor/livewire`. Antes, cada primera visita arrancaba PHP entero para mandarlo. Lo publican
   `composer install` y `alojaflow:empaquetar`, y va en el paquete (1.8 MB más en el zip). Si no está, Livewire vuelve a su ruta.
8. **`alojaflow.css` lleva su propia versión.** Con la caché de un año, la de Filament ya no servía: un cambio en esta hoja no le llegaría
   a nadie hasta actualizar Filament.
9. **Calendario.** Un enlace solo en los días libres; lo que tapa una barra es un `<div>` que recibe lo que se suelta. Arrastrar se atiende
   una vez en el contenedor y no con dos oyentes de Alpine por celda. Las celdas de cada unidad pasaron de 27 a 5 KB, y lo de cada día se
   calcula una vez: con 40 unidades, de 400 a 220 ms de respuesta. De paso, el teclado ya no pasa por enlaces tapados.

## Qué no se hizo, y por qué

- **Recortar `app.css`.** Hay unos 100 KB de componentes que AlojaFlow no usa (editor enriquecido, markdown, builder, asistente). Comprimidos
  son 10 KB, unos 50 ms con esta red, a cambio de un paso más al empaquetar y de que un error deje sin estilos una pantalla que casi nadie
  abre.
- **Quitar espacios del HTML.** En Reservas ahorra 4.5 KB comprimidos, unos 20 ms, y hay que cuidar `<pre>`, `<textarea>` y `white-space:
  pre-wrap`. No vale el riesgo.
- **Cargar `tables.js` y `schemas.js` solo donde hagan falta.** Son 12 KB comprimidos y una ventana modal puede pedirlos después.
- **Fuente del sistema en el teléfono.** Ahorraría 67 KB, pero cambia el aspecto: es una decisión de diseño.
- **Evitar el salto de la fuente** con `font-display: optional`. La hoja `inter/index.css` es de Filament y los nombres de sus archivos
  cambian con cada versión; reescribirla a mano se rompería sin avisar.
- **Comprimir de antemano con Brotli al empaquetar.** Ahorraría unos 45 KB en la primera visita de cada versión, pero exige Node para
  empaquetar y más reglas en `.htaccess`.
- **Comprimir desde PHP** para los hostings sin `mod_deflate`. Los recursos estáticos, que es lo que más pesa, seguirían sin comprimir.
- **Modo SPA de Filament** (`->spa()`). Ahorraría reinterpretar estilos y scripts en cada cambio de pantalla, pero cambia cómo se navega en
  todo el panel. Hay que probarlo aparte.

## Riesgo aceptado: comprimir páginas con un token

Comprimir HTML que lleva el token CSRF y refleja datos de la dirección abre en teoría la puerta a BREACH. Hace falta espiar el tráfico y
forzar miles de peticiones con la sesión de la víctima, y la cookie `SameSite=Lax` no viaja en las que otro sitio lanza por debajo. Se
acepta, como casi cualquier sitio que comprime.

## Comprobarlo en un hosting

Sin iniciar sesión, cambiando el dominio. Tiene que decir `content-encoding: gzip` (o `br`) y el `cache-control` con `immutable`:

```bash
curl -sI -H "Accept-Encoding: gzip" "https://app.alojaflow.com/css/filament/filament/app.css?v=1" | grep -iE "content-encoding|cache-control"
```

Si no sale `content-encoding`, ese hosting no tiene `mod_deflate` para este sitio y hay que pedírselo. Un servidor nginx no lee
`.htaccess`: hay que poner lo mismo en su configuración.

## Repetir la medición

Lighthouse no es dependencia del proyecto: `npm install --prefix carpeta-temporal lighthouse@13`. Con el Apache de
`desarrollo/apache-prueba/` y una copia con `APP_ENV=production` y datos inventados:

```bash
php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan filament:optimize
```

```bash
php desarrollo/rendimiento/sesion.php correo@ejemplo.com > cookie.txt
```

```bash
LIGHTHOUSE_CLI=carpeta-temporal/node_modules/lighthouse/cli/index.js node desarrollo/rendimiento/medir.mjs --base=http://127.0.0.1:8004 --cookie=cookie.txt --rutas=/hoy,/calendario --aplicada --salida=medidas
```

```bash
node desarrollo/rendimiento/visita-repetida.mjs --base=http://127.0.0.1:8004 --cookie=cookie.txt --rutas=/hoy,/calendario
```

En Git Bash, antepón `MSYS_NO_PATHCONV=1`, o convertirá `/hoy` en una ruta de Windows. Antes de correr las pruebas, `php artisan
optimize:clear`: con la configuración en caché, Pest usaría la base de esa copia.
