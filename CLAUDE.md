# AlojaFlow — guía para Claude

AlojaFlow es un sistema de gestión de alojamientos (PMS) para alojamientos pequeños, escrito
**desde cero** en Laravel, con licencia **AGPL v3 o posterior**. Del proyecto anterior
(AlojaFlow 1.2.x, derivado de HotelDruid) solo se conservan el nombre, el logo y los colores.

- **Estado (2026-11-08): versión de prueba 1.0.40**, a la espera de que el usuario la pruebe
  (`docs/guia-pruebas.md`). Cada propiedad es independiente o subordinada (D-035). Producto
  aprobado; la especificación sigue en redacción. Las decisiones tomadas por delegación (D-024 a
  D-029) y D-036 se revisan con el usuario. El plan de [docs/plan-paridad.md](docs/plan-paridad.md) quedó completo en la 1.0.24, y lo que se eligió del núcleo heredado ([docs/nucleo-heredado.md](docs/nucleo-heredado.md), D-117), en la 1.0.29.
- **Idioma:** español de México en la conversación, la documentación, el código
  (identificadores y comentarios) y los mensajes de commit. Interfaz en español y en los
  idiomas de la regla 7.
- **Uso mundial** con paquetes de país. Fase 1: México, España, Estados Unidos y un paquete
  genérico (resto de Latinoamérica y otros países).
- **Una instalación por cliente** (también en el servicio alojado): una base de datos por
  cliente, con una o varias propiedades dentro.
- Contexto y razones: [docs/INICIO.md](docs/INICIO.md) · producto: [docs/producto.md](docs/producto.md) ·
  especificación: [docs/especificacion/README.md](docs/especificacion/README.md) ·
  decisiones con fecha: [docs/decisiones.md](docs/decisiones.md).

## Fases

1. **Fase 1 — subordinada de solo lectura.** HotelDruid 3.0.8 (esquema 3.081) o AlojaFlow 1.2.x
   es la instalación principal. AlojaFlow descarga su copia de respaldo, la carga y ofrece
   encima las funciones nuevas. **AlojaFlow nunca escribe en la principal.** Los datos propios
   de AlojaFlow van en tablas que la sincronización no toca. Alcance: los 11 módulos de
   [docs/producto.md](docs/producto.md) §8.
2. **Fase 2 — núcleo propio.** Reservas, tarifas y clientes propios para funcionar sin
   HotelDruid, más todo lo que escribe reservas (grupos, cupones, fusionar clientes, motor de
   reservas…). El modelo de datos lo contempla desde la fase 1. **Desde la 1.0.5 está completa
   (D-041 a D-043), y desde la 1.0.29 también se importa de un archivo (D-127):** propiedades independientes (`modo = nativa`)
   con su motor de reservas, y una subordinada puede pasar a independiente. Las subordinadas
   siguen como en la fase 1.

AlojaFlow 1.2.x queda **congelada** (solo correcciones de seguridad y errores): el esquema 3.081
de la copia no cambia mientras se construye la fase 1.

## Reglas

1. **Licencia AGPL-3.0-or-later.** Archivo `LICENSE` en la raíz y cabecera breve en cada archivo
   fuente (ver «Cabecera de licencia»).
2. **Todo nuevo.** Arquitectura, nombres y estructura nuevos sobre Laravel. Los nombres
   italianos de HotelDruid (`prenota`, `clienti`, `appartamenti`…) solo existen dentro del
   módulo de sincronización.
3. **Dependencias solo con licencia compatible con la AGPL v3:** MIT, BSD, Apache 2.0,
   LGPL v2.1+/v3, GPL v3, AGPL v3. **Prohibidas:** GPL v2 «solo» (sin «o posterior»), OSL
   (p. ej. QloApps), licencias propietarias o de pago y código sin licencia. Comprobar la
   licencia antes de añadir cualquier dependencia; antes de cada versión, revisarlas todas
   (`composer licenses` y `npx license-checker --summary`).
4. **La lógica sí, el código viejo no.** De HotelDruid, AlojaFlow 1.2.x u otro proyecto con
   licencia compatible se aprovecha la lógica, reescrita. Ningún archivo viejo entra tal cual.
   Mínimos de todo lo que entra:
   - PHP 8.3 con `declare(strict_types=1)`, tipos en parámetros, propiedades y retornos, enums;
     clases con una sola responsabilidad; sin funciones ni variables globales.
   - Base de datos solo con Eloquent o el query builder, y esquema solo con migraciones.
     Nunca SQL armado concatenando cadenas.
   - Validación con reglas de Livewire o Form Requests; `Validator` solo en las páginas públicas que responden en la
     misma página (D-056). Permisos con `App\Seguridad\Acceso` y los `can*` de Filament. HTML solo en vistas Blade
     o componentes Livewire (nada de `echo` de HTML desde la lógica).
   - PSR-12 con Laravel Pint, Larastan sin errores y pruebas que cubran la lógica adaptada.
   - Interfaz responsiva y accesible (contraste AA, foco visible, objetivos táctiles cómodos).

   Si un fragmento no puede cumplirlo sin reescribirse entero, se reescribe entero. Cuando la
   lógica venga de otro proyecto, conservar su aviso de copyright y anotarlo en
   `THIRD-PARTY-NOTICES.md` (origen, licencia, archivos afectados).
5. **Contribuciones** bajo la misma AGPL, con DCO: cada commit lleva `Signed-off-by`
   (`git commit -s`). No hace falta CLA.
6. **Cada cambio** con pruebas (Pest) y análisis estático (Larastan) en verde.
7. **Español primero, en varios idiomas.** Ningún texto visible escrito directamente en el
   código: todo pasa por los archivos de traducción (`lang/<idioma>/…`). El español es la
   fuente; idiomas de la fase 1: español, inglés, francés, italiano, portugués y alemán. Cada
   texto nuevo va en los seis: `tests/Unit/TraduccionesTest.php` falla si falta alguno (D-052). Cada
   usuario y cada huésped ven su idioma. Fechas, números, monedas y zonas horarias según la
   propiedad y el idioma, nunca con formatos fijos en el código.
8. **Enlace «Código fuente»** visible en la aplicación (obligación de la AGPL por uso en red),
   que descarga el código exacto de la versión instalada.
9. **Créditos.** Mientras haya lógica derivada de HotelDruid, mencionar a Marco M. F. De Santis
   en `THIRD-PARTY-NOTICES.md` y en la pantalla «Acerca de». Se puede decir «compatible con
   HotelDruid»; no usar su logo ni sugerir que es un producto oficial.
10. **Subordinación** documentada en
    [docs/especificacion/subordinacion.md](docs/especificacion/subordinacion.md): las llamadas
    HTTP a `crea_backup.php` (crear, descargar —comprimida o no— y borrar la copia) con sus
    respuestas y errores; el formato del archivo de copia; la correspondencia entre las tablas
    de HotelDruid 3.081 y el modelo propio. Se programa con código moderno (regla 4) y se
    prueba con copias reales. **Nunca** se pide ni se instala el código fuente de la principal
    (`mostra_sorgente.php`).

### Seguridad y datos personales (aprobadas 2026-11-03)

11. **La copia de HotelDruid es un archivo `.php` y nunca se incluye, evalúa ni ejecuta.** Se lee
    como texto con un analizador propio. Sus bloques `<file>` traen código PHP de configuración
    de la principal: se extraen solo los valores conocidos, sin ejecutar nada.
12. **Los datos personales reales no entran al repositorio.** Las copias reales (p. ej. las de
    `E:\HotelDruid\respaldos\`) se leen desde su ubicación, indicada por variable de entorno;
    nunca se copian al repositorio, a `docs/`, a las pruebas versionadas ni a los mensajes. Las
    pruebas versionadas usan copias sintéticas o anonimizadas.
13. **Las credenciales de la principal** se guardan cifradas (cast `encrypted`), nunca aparecen
    en registros ni en mensajes de error, y solo viajan por HTTPS (salvo `localhost` en
    desarrollo).

## Tecnología

Versiones fijadas en `composer.json` (2026-11-03): Laravel 13, Filament 5 (con Livewire 4),
spatie/laravel-permission 8, Pest 4, Larastan 3.

| Capa | Elección | Licencia |
|---|---|---|
| Lenguaje | PHP 8.3 | PHP License |
| Framework | Laravel 13 | MIT |
| Base de datos | MySQL 8.4 / MariaDB 11.4. **Sin SQLite, tampoco en las pruebas** | — |
| Interfaz interactiva | Livewire | MIT |
| Detalles de interfaz | Alpine.js | MIT |
| Estilos | Tailwind CSS | MIT |
| Panel de administración | Filament (gratuito) para catálogos y configuración; las pantallas operativas (calendario, «Hoy», limpieza…) y las públicas, propias con Livewire | MIT |
| Autenticación y 2FA | La de Filament (inicio de sesión, perfil, app de códigos con códigos de recuperación). Sustituye a Fortify (D-026) | MIT |
| Roles y permisos | spatie/laravel-permission | MIT |
| Copias de seguridad | Propias, sin `mysqldump` (`App\Copias`, D-038) | — |
| Calendarios iCal (Airbnb, Booking, Vrbo) | sabre/vobject | BSD-3 |
| PDF (recibos, contratos) | barryvdh/laravel-dompdf (dompdf) | MIT / LGPL-2.1 |
| Pagos en línea | Stripe, Mercado Pago, Redsys y PayPal por su API, sin SDK (`App\Pagos`, D-042, D-159) | — |
| Colas y tareas | driver `database` + programador de Laravel con un solo cron por minuto (sin Redis ni Supervisor) | — |
| Pruebas | Pest | MIT |
| Análisis estático | Larastan (PHPStan) | MIT |
| Formato de código | Laravel Pint | MIT |
| Integración continua | GitHub Actions | — |
| Entorno local | PHP 8.3 y MySQL 8.4 de WampServer (D-025) | — |

Se construye en casa: calendario por habitaciones (*tape chart*), motor de reservas,
sincronización iCal, informes. Descartado por ahora: React/Vue separado, Docker,
microservicios, app nativa (más adelante una PWA), FullCalendar Premium, channel manager de pago,
modo SPA de Filament (D-068).

Destino de instalación: hosting compartido (`public_html`). El paquete incluye `vendor/` y los
recursos ya compilados; un solo cron dispara el programador de tareas. Sin CDN en tiempo de
ejecución: todos los recursos se sirven desde la instalación. `public/.htaccess` los comprime y los
deja en caché (D-067, `docs/rendimiento-movil.md`).

**Paquetes de país.** Lo que cambia por país (registro de viajeros, impuestos al
hospedaje, privacidad, documentos de identidad, formatos) va detrás de interfaces con una
implementación por país (`Mexico`, `Espana`, `EstadosUnidos`, `Generico`). Nada de
`if ($pais === …)` repartidos por el código.

## Convenciones

- **Código en español:** clases, métodos, variables, tablas, columnas y rutas en español, sin
  acentos ni ñ (`Habitacion`, `anio`, `crearReserva()`, tabla `reservas`). Lo que imponen
  Laravel y los paquetes se queda como viene (`users`, `jobs`, `roles`, `handle()`,
  `render()`…).
- **Modelos Eloquent con `$table` explícito:** el pluralizador de Laravel es inglés
  (`Habitacion` daría `habitacions`). Las claves foráneas siguen el patrón `<modelo>_id`
  (`habitacion_id`).
- Los nombres italianos de HotelDruid solo aparecen en el módulo de sincronización (regla 2).
- **Cabecera de licencia** en cada archivo fuente, justo después de `<?php` y antes de
  `declare(strict_types=1);` (en Blade con `{{-- --}}`, en JS/CSS con `/* */`):

  ```php
  /*
   * AlojaFlow — sistema de gestión de alojamientos
   * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
   * SPDX-License-Identifier: AGPL-3.0-or-later
   */
  ```

## Cómo trabajar

- **Una sesión por módulo** (esqueleto, sincronización, calendario, tarifas…) para no mezclar
  contexto.
- **Pasos pequeños:** cada paso termina con pruebas en verde, Pint y Larastan limpios, y un
  commit firmado (`git commit -s`).
- **Cada decisión** se anota en `docs/decisiones.md` (fecha, qué, por qué). Si cambia una regla
  o la tecnología, se actualiza también este archivo.
- Revisar los cambios importantes antes de integrarlos con `/code-review high`.
- Esfuerzo recomendado (modelo Opus 5.5):

  | Trabajo | Esfuerzo |
  |---|---|
  | Especificación, arquitectura, sincronización con HotelDruid, tarifas, seguridad | high |
  | Modelo de datos y lógica de disponibilidad / overbooking | xhigh |
  | Pantallas y CRUD normales, traducciones, ajustes de diseño | medium |
  | Cambios pequeños y preguntas rápidas | low |

## Estructura del código

- `app/Sincronizacion/HotelDruid/` — módulo de subordinación: el único lugar con nombres
  italianos. Contiene:
  - `ValidadorDestino` (SSRF), `ClientePrincipal` (protocolo, con lista blanca), `Descompresor`
    y `LectorCopia` (formato);
  - `CargadorEspejo` (`hd_filas`), `Proyector` (modelo propio y eventos) y `Sincronizador`
    (ciclo completo).
- `app/Sincronizacion/AlojaFlow/` — copiar de otra instalación de AlojaFlow (D-073): `Exportador` y `GestorConexiones` del lado de la principal; `ClienteRemoto`, `Sincronizador` y `Proyector` del de
  la subordinada. `Subordinadores::de($principal)` elige con cuál se copia. Contrato en `docs/especificacion/subordinacion-alojaflow.md`.
- `app/Actualizacion/EsquemaAlDia.php` y `app/Http/Middleware/EsquemaActualizado.php` — la base se pone al día si subieron los archivos a mano (D-089).
- `app/Actualizacion/` — paquetes de versión y actualizaciones
  (`docs/especificacion/instalacion-actualizaciones.md`):
  - `Empaquetador` (construye el zip firmado) y `Revisor` (lo comprueba sin cambiar nada);
  - `Aplicador` (extrae e intercambia los archivos con diario) y `Actualizador` (las dos fases);
  - `CopiaBaseDatos`, `Firma`, `Manifiesto` y `Rutas` (qué no toca nunca una actualización);
  - en línea (D-140): `UltimaVersion` (el aviso diario) y `DescargaPorPedazos`;
  - sin nadie delante (D-151): `ActualizacionAutomatica` (a las 3:00, o la urgente de dev.alojaflow.com) e `InstaladorVersion`.
- `app/Reservas/` — propiedades independientes: `GestorReservas` es el único que escribe reservas,
  noches y pagos propios, `Cotizador` calcula el precio con la tarifa y sus temporadas, y `Mensualidades` lleva el calendario
  de cobros de una estancia larga (D-107, D-108). Una unidad se vende con su tarifa principal y otras (`Unidad::tarifasIds`, D-118); una tarifa puede salir de otra (`Tarifa::precios`, D-119). `ReglasCargo`, cuándo se cobra un cargo (D-121).
- `app/Reservas/GestorGrupos.php` y `app/Grupos/` — grupos: `CalculadorPrecio` (D-095), `CalendarioPagos`, `AccesoCoordinador` y `ContratoGrupo` (D-096), `ListaHuespedes`. `app/Comprobantes/`, comprobantes de pago (D-097). Página pública en `GrupoPublicoController`.
- `app/Reservas/UtilidadReserva.php` y `CargosSugeridos.php`, cargos de los canales y utilidad bruta y neta (D-149). `GestorPreciosTarifa.php` y `app/Servicios/CalendarioTarifas.php`, calendario de tarifas (D-148).
- `app/Reservas/ImportadorDatos.php`, traer huéspedes y reservas de un archivo (D-127). `app/Reservas/DetectorDuplicados.php` (D-088). `app/Propiedades/RegistrosTuristicos.php` (D-091). `app/Paises/CatalogoRegiones.php` (D-092).
- `app/Reservas/Restricciones.php` (D-084) e `ImportadorPrecios.php` (D-083). `app/Pagos/GestorEnlacesPago.php` (D-085). `app/Comunicacion/Opiniones.php` (D-087).
- `app/Reservas/GestorEstancias.php`, llegó y salió también en subordinadas, con `MarcaEstancia` (D-153). `UnidadesVinculadas.php`, la casa
  completa y sus cuartos: `GestorReservas::revisarVinculadas` las bloquea en orden antes de ocupar noches (D-155). `CodigosAcceso.php` (D-156).
- `app/Dinero/SegundaMoneda.php` y `PagoRecibido.php`, segunda moneda con tipo a mano (D-157). Un pedido de varias unidades del motor son
  reservas con `reserva_principal_id`; `CobrosEnLinea` cobra y reparte desde la primera (D-158).
- `app/Comunicacion/WhatsappCloud.php`, plantillas aprobadas por la API de Meta desde las automatizaciones (D-160).
- `app/Motor/` y `app/Pagos/` — motor de reservas y pasarelas (D-042). `app/Propiedades/Independizador.php`,
  pasar una subordinada a independiente (D-043).
- `app/Caja/GestorTurnos.php`, turnos y arqueo (D-080). `app/Sincronizacion/ProyeccionCaja.php`, la caja de la principal (D-078).
- `app/Limpieza/AvisoEquipo.php`, el parte para el equipo, y `Omisiones.php`, a quién no mostrar en cada lugar (D-074, D-144); la jornada lo toma de `Operacion::delDia`.
- `app/Limpieza/`, `app/Comunicacion/` (`Mensajero`, D-045), `app/Ical/` (D-046), `app/Llegada/` y
  `app/Privacidad/` (D-047): datos propios, que valen igual en independientes y subordinadas.
- `app/MejoraContinua/` — datos de uso con permiso y «Reportar o sugerir», hacia dev.alojaflow.com (D-150,
  `docs/especificacion/mejora-continua.md`): `Permiso`, `ArmadorLatido`, `RegistroErrores`, `ReportesMejora`, `EnvioMejora`.
  Nada de huéspedes; un dato nuevo del latido se agrega también a esa especificación.
- `app/Paises/` — **todo lo que cambia por país** va detrás de `Pais` (D-048). `app/Dinero/`, políticas (D-050). Por ahora no se factura
  en ningún país: de un pago sale un recibo (`GeneradorPdf::recibo`, D-131).
- `app/Autoridades/` — registro de viajeros: `GestorEnvios` concilia reservas con lo comunicado y `Ses/` es SES.Hospedajes (D-100).
- `app/Documentos/` — PDF (`GeneradorPdf`), documentos que escribe el alojamiento (`MotorDocumentos`, D-123) y `CamposPropios` (D-122).
- `app/Propietarios/` — propiedades de terceros: `CalculadorEstado` y `GestorEstados` (D-114, D-115), `AccesoPropietario` y `PortalPropietario` (D-116). `app/Seguridad/AccesoConClave.php`, enlace y clave de quien no es usuario del panel.
- `app/Camping/` — lo de un camping: `AtributosLugar` y `FiltroLugares` (D-110), `GestorLecturas` (D-111), `CupoExtras` (D-113). `app/Servicios/Residentes.php` (D-112).
- `app/Publico/` — lo que ve el huésped sin cuenta: `PaginaPublica` y `GestorFotos` (D-104), `MiReserva` (D-105), `GuiaDigital` (D-106), `CalendarioPublico` (D-126).
- `app/Archivos/GestorArchivos.php` — archivos privados, cifrados si llevan datos personales.
  `app/Seguridad/ClienteExterno.php`, peticiones a direcciones que escribe un usuario, solo a redes públicas.
- `instalador/instalar.php` — plantilla del instalador de un solo archivo (D-040, D-071); sin sintaxis
  moderna a propósito: **Pint no se corre sobre él**, y `pint.json` lo excluye. `app/Instalacion/Instalador.php`, lo que hace dentro de la aplicación.
- `app/Instalacion/Instancia.php` — modo instancia del servicio alojado (D-162): con `ALOJAFLOW_INSTANCIA`, el `.env`, el `storage` y las
  cachés salen de la carpeta del cliente y el código es compartido; sin actualizaciones propias. `alojaflow:instalar` y `alojaflow:enlace-entrada`.
- `app/Copias/GestorCopias.php` — copias de seguridad (D-038); `CopiaExterna`, `CifradoCopia` y `FirmaS3`, la copia cifrada fuera del servidor (D-152).
  `app/Programador/`, tareas sin cron (D-039).
- `app/Demo/` — `GeneradorDemo`, la propiedad de demostración con datos inventados (D-037), y `ModoDemo`,
  la demostración pública (D-044).
- `app/Models/` — modelos en español sobre `ModeloBase` (`creado_en`/`actualizado_en`). Las
  filas con `sincronizada = true` solo las cambia la sincronización
  (`Concerns/ProtegidaPorSincronizacion`).
- `app/Modulos/` — qué se puede prender y apagar en la instalación (`Modulo`, `Modulos`, D-069) y `PrimerosPasos`. Un recurso o una pantalla
  dice a qué módulo pertenece con `modulo()`; las rutas públicas, con el middleware `ModuloActivo`.
- `app/Seguridad/Acceso.php` — permisos por propiedad (roles de spatie con equipo =
  propiedad); el administrador ve todo, y `Acceso::esSuper` es quien instaló (D-070). Lo que manda un formulario como propiedad pasa por
  `Acceso::propiedadElegida`, y al guardar, `RecursoDePropiedad::comprobarReferencias`. `Limites`, lo que se le limita a cada quien (D-075);
  `Dispositivos`, «recordarme» (D-076); `AuditoriaModelos`, antes y después de lo que cambia el equipo (D-077); `EtiquetasAuditoria`, cómo se lee en cada idioma (D-133).
- `app/Http/Middleware/` — `CabecerasSeguridad` (D-057), `MismoOrigen` (motor sin CSRF) y `UsuarioActivo`
  (rutas con sesión fuera del panel).
- `app/Servicios/` — consultas de las pantallas operativas (Operacion, Calendario,
  Disponibilidad, Indicadores), `Informes` e `InformesGestion` (D-079), y `Grafica`, lo que dibuja `filament.partes.grafica-barras`.
- `app/Filament/Pages/` (pantallas propias, vistas en `resources/views/filament/pages/`) y
  `app/Filament/Resources/` (administración y listados). `app/Filament/Tablas/OrdenAutomatico.php`, toda columna de la base se ordena sola (D-137). `app/Servicios/Buscador.php`, buscar por cualquier palabra, y `FichaHuesped.php`.
- `resources/js/alojaflow.js` — lo que responde sin ir al servidor, sin Livewire ni Alpine (D-136): pares de fechas (`App\Filament\Campos\RangoFechas`), orden de `.af-tabla` y variables. `app/Documentos/CatalogoVariables.php`, la lista que se ofrece al escribir (D-139). `app/Limpieza/FormatoAviso.php` (D-138).
- `lang/es` (fuente) y los demás idiomas; `resources/views/publico/`, páginas sin sesión del huésped;
  `resources/css/alojaflow.css` (estilos propios).
- `tests/Support/ConstructorCopia.php` — genera copias sintéticas de HotelDruid para las pruebas;
  `ConstructorPaquete.php`, paquetes de versión firmados con una clave de prueba.
- `desarrollo/` — no se instala ni va en los paquetes:
  - `apache-prueba/`: Apache de Wamp que lee `.htaccess`, y la prueba de punta a punta del instalador;
  - `principal-simulada/`: principal falsa para demostraciones;
  - `rendimiento/`: mide con Lighthouse y compara la primera visita con la segunda (`docs/rendimiento-movil.md`);
  - `servidor-instalacion.php`: sirve una instalación hecha desde el zip con límites de subida altos.

## Comandos

Entorno: Windows 10. El `php` del `PATH` es el 8.2 de WinGet y el 8.3 de WinGet no tiene
`php.ini`. Usar el **PHP 8.3 de Wamp** (`C:\wamp64\bin\php\php8.3.28\php.exe`, abreviado `PHP`
abajo) con Xdebug apagado (`XDEBUG_MODE=off` o `-d xdebug.mode=off`). Composer está en
`.herramientas/composer.phar`, fuera del repositorio. MySQL 8.4 de Wamp: abrir WampServer, o
arrancar `mysqld.exe` como indica `docs/guia-pruebas.md`.

```bash
PHP .herramientas/composer.phar install   # dependencias (publica también los recursos de Filament)
PHP artisan migrate --seed                # tablas y roles
PHP artisan serve                         # http://127.0.0.1:8000
PHP artisan schedule:work                 # sincronización periódica en local
PHP artisan alojaflow:sincronizar --principal=ID
PHP artisan alojaflow:crear-administrador correo@ejemplo.com [--super]
PHP artisan alojaflow:demo [--quitar]     # propiedad de demostración con datos ficticios
PHP vendor/bin/pest                       # pruebas, contra la base MySQL alojaflow_pruebas
PHP vendor/bin/pint --test                # formato PSR-12 + cabecera de licencia + strict_types
PHP vendor/bin/phpstan analyse --memory-limit=1G   # Larastan nivel 6
PHP artisan filament:assets               # tras cambiar resources/css/alojaflow.css o resources/js/alojaflow.js
PHP artisan vendor:publish --tag=livewire:assets --force  # JavaScript de Livewire en public/ (lo hace composer al instalar)
PHP artisan alojaflow:empaquetar          # dist/alojaflow-x.y.z.zip firmado (git sin cambios)
PHP artisan alojaflow:actualizar ruta.zip # actualizar una instalación desde la consola
PHP .herramientas/composer.phar licenses  # licencias PHP
npx license-checker --summary             # licencias npm
```

**Trampas conocidas:**
- Filament inyecta los parámetros de los *closures* **por nombre**: hay que usar `$query`,
  `$data`, `$search`, `$state`, `$record`…, nunca `$q` ni `$datos`.
- Blade no admite `use` dentro de `@php`: se usa `@use('Clase')`.
- Una tabla propia (`.af-tabla`) se ordena sola en el navegador: lo que se lee distinto de como se ordena, como una fecha en palabras, lleva `data-orden`. Dentro de `.af-tabla-contenedor`, una fila con `colspan` la deja sin orden.
- Con `ONLY_FULL_GROUP_BY` y `DISTINCT`, MySQL exige que el `ORDER BY` esté en el `SELECT`.
- Las pruebas de pantallas deben entrar también con usuarios que no son administradores.
- En comillas dobles, un carácter no ASCII pegado a una variable forma parte de su nombre
  (`"$version»"` busca la variable `$version»`): escribir `{$version}`.
- En Windows, `ZipArchive::addFile()` no admite rutas de más de 260 caracteres y devuelve
  `false`: comprobar siempre lo que devuelve.
- La batería de pruebas pasa de 128 MB de memoria: `phpunit.xml` sube `memory_limit`.
- En `lang/`, una clave con punto (`'a.b' => 'x'`) está en el archivo pero `__()` no la encuentra y sale cruda: se anida.
- Un campo de Filament sin `->label()` toma su etiqueta del identificador en español, en todos los idiomas, aunque
  lleve `hiddenLabel()` (la leen los lectores de pantalla y los mensajes de validación).
- Cada prueba tiene su propio `storage_path()` y su disco `local`, que se borran al terminar (`tests/TestCase.php`): las
  tareas corren de verdad, y la limpieza, el reinicio de la demo y las copias borrarían los datos locales del equipo.
- Una prueba que cambia `$this->app['env']` enciende `ProgramadorPorVisitas`: cada petición correría todas las tareas.
  Esa prueba va con `withoutMiddleware(ProgramadorPorVisitas::class)`.
- En el teléfono, una pantalla sin `<form>` se ve antes de que arranque Alpine (D-067): lo que deba estar oculto hasta
  entonces lleva `x-cloak`, y un campo con `wire:model` que se vea de entrada, su `value`.
- `$this->get()` de las pruebas manda `Accept-Language: en-us`: las páginas públicas salen en inglés, y después de la petición `__()` también. Se compara con `__()`, no con el texto en español.
- En un archivo con `use Filament\Facades\Filament;`, `Filament\Actions\…` sin importar se busca dentro de la fachada: se importa la clase.
- Livewire añade dos comentarios a cada `@if` y `@foreach` de Blade. En un bucle de miles de celdas pesan: el del
  calendario va con PHP directo.
- Un `CheckboxList` envuelve su estado en una lista antes de `formatStateUsing`: un número guardado llega como `[5]`, y no se distingue del quinto valor. Con `->relationship()` se convierte en `mutateRelationshipDataBeforeFillUsing`; sin ella, en `fillForm`.
- En producción, `config()`, las rutas y los eventos salen de `bootstrap/cache`: los archivos nuevos no cuentan hasta borrarla. Por eso `EsquemaAlDia` lee la versión del archivo.
- Una fecha sin zona (`reservas.llegada`, cuotas) no se compara con `Propiedad::hoy()`, que sí la tiene: lo que vence hoy saldría vencido. Se comparan como texto `Y-m-d`.
- `Modelo::create()` no trae los valores por omisión de la base: si después se leen, `->refresh()`.
- `public/.htaccess`: nada de directivas que solo admita la configuración del servidor (`DeflateCompressionLevel`) y todo
  dentro de su `IfModule`. Cualquier error ahí es un 500 en todo el sitio; `RendimientoPanelTest` lo revisa.
- dompdf ignora `word-break: break-all`, y un texto largo sin espacios se sale de la página: `overflow-wrap: anywhere`.
- Una columna `minmax(7.5rem, 1fr)` no crece con su contenido: un importe se sale o, con `word-break`, se parte. Las cifras son `flex-wrap` con `white-space: nowrap`, y si no cabe, pasa al renglón siguiente.
- `@json` de Blade corta su expresión en las comas: un arreglo escrito ahí truena. Se arma en PHP y se imprime con `{!! !!}` y `JSON_HEX_TAG`.
- `form-action` de la CSP también se aplica a una redirección: una página con un botón que acaba en una pasarela no puede llevarlo (`CabecerasSeguridad`).
- `Http::fake` compara contra la dirección con su query: un GET con `?expand[]=…` necesita `*` al final del patrón.
- `$this->travel()` no mueve `time()`: en el código que se prueba así, `now()`.
- Eloquent guarda un `datetime` en la zona que traiga la instancia, sin convertirla: a las columnas en UTC, `->utc()`.
- El PHP de Wamp no trae certificados: toda petición https da «cURL error 60», y Actualizaciones dice que GitHub no respondió. En este equipo hace falta `curl.cainfo` y `openssl.cafile` en su `php.ini` o con `-d` (sirve el `ca-bundle.crt` de Git, en `usr/ssl/certs`).
- Livewire no acepta más de 1 MB por petición y cuenta su snapshot: un texto largo en una propiedad pública viaja dos veces, y al pasarse truena sin explicar nada. Se queda en Alpine y va como argumento (`ImportarDatos`).
- En el servicio alojado los clientes comparten origen (`app.alojaflow.com/<cliente>`, D-163): nada de lo que escribe
  un alojamiento se imprime con `{!! !!}` sin `Str::sanitizeHtml`, no hay HTML libre ni «código de seguimiento», un
  archivo subido solo se abre en el navegador si es JPG, PNG, WebP o PDF, y lo que se guarda en `localStorage` va con
  `alojaFlowLlave()`. Ninguna dirección fija desde la raíz (`href="/…"`, `fetch('/…')`): lo vigila `RutaBaseTest`.
- Un CSV con comas se abre en una sola columna donde Excel separa la lista con «;», y uno con «;», donde la separa con comas: lo que se abre en Excel va con `Csv::descargaParaExcel` (D-128), y lo que se suma, con `Csv::numero`. Y al guardar, las fechas quedan como en su país: se leen con `Pais::fechaConMesPrimero`.

## Referencias locales (solo lectura)

- `E:\HotelDruid\` — repositorio local de AlojaFlow 1.2.3 (derivado de HotelDruid 3.0.8).
  Sirve para **entender la lógica**, nunca para copiar código (regla 4):
  - `alojaflow/crea_backup.php` (genera la copia), `alojaflow/interconnessioni.php` (cliente de
    subordinación original), `alojaflow/plugins/multipropiedad/` (descarga copias de varias
    principales), `alojaflow/creadb.php` (esquema), `alojaflow/includes/funzioni_aggiorna.php`
    (migraciones del esquema).
  - `upstream/` — HotelDruid 3.0.8 original y su documentación oficial.
  - `docs/`, `PRODUCT.md`, `DESIGN.md` — wiki, análisis (seguridad, foro, proyectos similares)
    y sistema visual del tema anterior.
  - `respaldos/` — copias **reales** con datos personales (también `respaldos/demo/`, aunque se
    llame así): aplica la regla 12.
  - **Demo ficticia de Zihuatanejo** para probar y enseñar:
    - `respaldos/demo-alojaflow-2026-respaldo-original.php` (copia 3.081: 17 unidades, 974
      huéspedes `@example.com`, 1040 reservas y 82 canceladas de 2026);
    - es idéntica al `dati/hoteld_backup.php` de `dist/AlojaFlow-1.1.4-demo.zip`;
    - la genera `tools/demo_genera.py`.
- `branding/` — logos SVG finales.

## Versiones

- Numeración (D-030): **1.0.1**, 1.0.2 … 1.0.99, y después 1.2.
- Cada versión sube `version` en `config/alojaflow.php`, añade su entrada en `CAMBIOS.md` y se
  marca con la etiqueta de git `v1.0.x`.
- Las entregas de la fase 1 tienen nombre (Base, Operación, Huéspedes, Dinero), no número
  (D-031).
- **Publicar una versión** (D-033):
  1. subir `version` y escribir su entrada en `CAMBIOS.md`;
  2. commit y etiqueta `v1.0.x`;
  3. `PHP artisan alojaflow:empaquetar`: deja `dist/alojaflow-1.0.x.zip` firmado y revisado;
  4. probarlo en la instalación de prueba `E:\AlojaFlow-prueba` (Sistema → Actualizaciones).
- La **clave secreta de firma** (`.herramientas/clave-actualizaciones.secreta`) nunca se sube y
  necesita una copia de seguridad fuera del equipo: sin ella, las instalaciones no aceptan
  versiones nuevas.
- Lo que une las dos fases de una actualización no cambia entre versiones
  (`instalacion-actualizaciones.md` §4.2).

## Marca

- Verde azulado `#0F766E` (color principal, cuadro del logo), ámbar `#F59E0B` (ola), tinta
  `#0B1F2A` (texto). Logos en `branding/logo-final-*.svg`.
- Aspecto serio y moderno; el acabado de Google Workspace como referencia (claro, muy legible,
  botones cómodos y familiares). Nada lúdico.
