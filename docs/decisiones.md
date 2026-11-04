# Registro de decisiones

Qué se decidió, por qué y cuándo. Una entrada por decisión; si una se cambia, se añade otra
nueva que la sustituye (no se borra la anterior). Las **propuestas** esperan la aprobación del
usuario.

---

### D-001 · 2026-11-03 · Reescribir desde cero con AGPL v3 o posterior
Sistema nuevo, todo el código AGPL, sin licencia comercial ni módulos cerrados. Se comercializa
con servicios (alojado, instalación, migración, soporte, formación, personalización). Del
proyecto anterior se conservan el nombre, el logo y los colores.
**Por qué:** una base técnica, visual y de uso nueva; la AGPL permite vender servicios.
Fuente: [INICIO.md](INICIO.md) §1.

### D-002 · 2026-11-03 · Solo dependencias gratuitas y compatibles con la AGPL
MIT, BSD, Apache 2.0, LGPL v2.1+/v3, GPL v3, AGPL v3. Lo que en otros sistemas es de pago se
construye aquí. **Por qué:** evitar costos y conflictos de licencia. Fuente: INICIO.md §2.

### D-003 · 2026-11-03 · Laravel, Livewire, Alpine, Tailwind y MySQL/MariaDB, sin SQLite
**Por qué:** un solo lenguaje y un solo despliegue que corre en hosting compartido. Fuente:
INICIO.md §3.

### D-004 · 2026-11-03 · Fase 1 subordinada de solo lectura; fase 2 núcleo propio
**Por qué:** los alojamientos ya operan con HotelDruid; adoptarlo sin migrar reduce el riesgo.
Fuente: INICIO.md §1.

### D-005 · 2026-11-03 · Una instalación por cliente, también en el servicio alojado
Cada cliente tiene su instalación y su base de datos, con una o varias propiedades dentro.
**Por qué:** encaja con el hosting compartido, aísla los datos de cada cliente y el enlace
«Código fuente» corresponde a la versión instalada. Descartado: multiinquilino. Decidió: usuario.

### D-006 · 2026-11-03 · Uso mundial con paquetes de país; fase 1: México, España, EE. UU. y genérico
Lo que depende del país va detrás de interfaces con una implementación por país; se irán
añadiendo países con el tiempo. **Por qué:** mercados principales México, Estados Unidos,
España y Latinoamérica; el paquete genérico cubre al resto mientras llega el suyo. Decidió: usuario.

### D-007 · 2026-11-03 · Código en español; interfaz en seis idiomas
Identificadores (clases, métodos, tablas, columnas) en español, sin acentos ni ñ; lo que imponen
Laravel y los paquetes se queda como viene. Interfaz en español (fuente), inglés, francés,
italiano, portugués y alemán. **Por qué:** pedido del usuario; el alemán lo sugirió Claude por
ser un mercado emisor de turismo importante. Sustituye a la propuesta inicial de identificadores
en inglés. Decidió: usuario.

### D-008 · 2026-11-03 · La fase 1 incluye todas las funciones nuevas que caben en solo lectura
Las funciones del catálogo y de los plugins de AlojaFlow 1.2.x se unifican en 11 módulos (M1–M11,
[producto.md](producto.md) §8.2), sin repetir funciones parecidas. **Por qué:** pedido del
usuario. Decidió: usuario.

### D-009 · 2026-11-03 · Lo que escribe en HotelDruid pasa a la fase 2
Grupos, cupones, fusionar clientes, anonimizar en la principal y bloqueos iCal dentro de la
principal. En la fase 1 se siguen usando los plugins de la principal 1.2.x. **Por qué:** la
fase 1 nunca escribe en la principal. Decidió: usuario.

### D-010 · 2026-11-03 · Filament para la administración
Filament para catálogos y configuración; las pantallas operativas y las públicas, propias con
Livewire. **Por qué:** ahorra meses en pantallas de administración y ya trae los seis idiomas.
Decidió: usuario.

### D-011 · 2026-11-03 · AlojaFlow 1.2.x congelada
Solo correcciones de seguridad y errores; el esquema 3.081 de la copia no cambia. **Por qué:** la
fase 1 necesita una base fija que leer. Decidió: usuario.

---

## Aprobadas con el documento de producto (2026-11-03)

Las propuso Claude y el usuario las aprobó junto con los borradores del producto y del índice.

### D-012 · 2026-11-03 · Reglas 11 a 13 de seguridad y datos personales
La copia nunca se ejecuta (es un `.php`); los datos personales reales no entran al repositorio;
las credenciales de la principal van cifradas y solo por HTTPS. Ver `CLAUDE.md`. **Por qué:** la
copia trae código PHP de la principal y los respaldos reales tienen datos de huéspedes.

### D-013 · 2026-11-03 · Tres capas de datos: espejo, proyección y propios
Los módulos solo usan el modelo propio; la sincronización escribe el espejo y la proyección.
**Por qué:** en la fase 2 las reservas propias conviven con las de HotelDruid sin reescribir los
módulos. Detalle: [especificacion/README.md](especificacion/README.md) §03.

### D-014 · 2026-11-03 · Sin CDN en tiempo de ejecución
Todos los recursos se compilan y se sirven desde la instalación. **Por qué:** funciona igual sin
servicios externos y no envía datos de navegación a terceros (mismo principio que en 1.2.x).

### D-015 · 2026-11-03 · Fase 1 en cuatro entregas (Base, Operación, Huéspedes, Dinero; nombres de D-031)
**Por qué:** la fase 1 es grande; cada entrega se instala y sirve por sí misma. Detalle:
[producto.md](producto.md) §9.

---

## Aprobadas con la especificación de subordinación (2026-11-03)

Las propuso Claude en `subordinacion.md` y el usuario las aprobó.

### D-016 · 2026-11-03 · Licencia de HotelDruid citada con su redacción exacta
La cabecera de HotelDruid dice AGPL v3 «o cualquier versión posterior aceptada por Marco Maria
Francesco De Santis», que actúa como apoderado según la sección 14. No dice «o posterior» a
secas, como anotaba INICIO.md. AlojaFlow reescribe la lógica sin copiar código y sigue siendo
AGPL-3.0-or-later; `THIRD-PARTY-NOTICES.md` cita la licencia de HotelDruid con esa redacción.
**Por qué:** exactitud en los créditos; lo encontró Claude al leer `crea_backup.php`.

### D-017 · 2026-11-03 · Reglas del cliente de subordinación
Peticiones por POST; lista blanca de parámetros (imposible enviar `upload_backup`,
`ripristina_backup` u otros que cambien la principal); ningún reintento automático tras un
rechazo de credenciales; se descartan al leer `utenti`, `sessioni`, `transazioni*`, `contratti`
e `interconnessioni`; el archivo descargado se borra siempre. **Por qué:** la principal manda la
contraseña en la URL si se usa GET, bloquea la cuenta con 7 intentos fallidos y su copia trae
hashes de contraseñas, sesiones (en 3.0.8) y claves de API. Detalle:
[especificacion/subordinacion.md](especificacion/subordinacion.md).

---

## Aprobadas por delegación (modelo de datos, 2026-11-03)

El 2026-11-03 el usuario pidió seguir sin esperar sus aprobaciones hasta tener una versión que
pueda probar (D-024). Estas decisiones se dan por aprobadas con esa autorización y se revisan
con la versión de prueba. Detalle y razones:
[especificacion/modelo-datos.md](especificacion/modelo-datos.md).

### D-018 · 2026-11-03 · Convenciones de datos
`creado_en` y `actualizado_en` en DATETIME y UTC (las tablas del framework conservan
`created_at`); importes en BIGINT en la unidad menor de la moneda, con su código ISO 4217;
enumeraciones como VARCHAR con enum de PHP; `id` BIGINT y códigos aleatorios para todo lo que
ven personas de fuera. **Por qué:** código en español, sin errores de redondeo, sin el límite de
2038 y sin exponer números secuenciales.

### D-019 · 2026-11-03 · Ocupación por noches con índice único
`reservas_noches` guarda una fila por noche, con un índice único sobre (`unidad_id`, `fecha`,
`exclusiva`). **Por qué:** en la fase 2 la base de datos rechaza la doble reserva aunque lleguen
dos peticiones a la vez; en la fase 1 (`exclusiva` nula) admite los solapes que ya traiga la
principal; además sirve para ocupación, ADR y RevPAR.

### D-020 · 2026-11-03 · Huéspedes por propiedad en la fase 1
Cada ficha de huésped pertenece a una propiedad. **Por qué:** cada principal tiene sus propios
clientes, y así los permisos por propiedad son simples. Una ficha común se estudia en la fase 2.

### D-021 · 2026-11-03 · Traducciones y catálogos
Textos traducibles en columnas JSON con `spatie/laravel-translatable` (MIT); documentos legales,
una fila por idioma y versión; países, monedas e idiomas sin tabla, con `symfony/intl` (MIT).
**Por qué:** menos tablas, y los documentos legales necesitan una huella por texto aceptado.
Añade dos dependencias.

### D-022 · 2026-11-03 · Tokens y documentos de identidad cifrados con índice HMAC
Los tokens de enlaces públicos y los números de documento se guardan cifrados, con un HMAC
aparte para buscarlos. **Por qué:** se pueden mostrar o buscar sin guardar nada en claro.

### D-023 · 2026-11-03 · `origen` separado de `sincronizada`; reservas siempre en una unidad
`origen` dice de dónde vino una fila; `sincronizada`, si hoy la controla la principal. En la
fase 2 toda reserva nativa va asignada a una unidad (reasignable), como en HotelDruid. **Por
qué:** migrar una propiedad a la fase 2 no mueve filas, y el índice único de D-019 basta para
garantizar la disponibilidad.

---

## Construcción de la versión de prueba (2026-11-03)

### D-024 · 2026-11-03 · Seguir sin aprobaciones hasta una versión de prueba
El usuario pidió continuar sin esperar sus aprobaciones hasta tener una versión usable para
probarla él. Se construye la **entrega Base** ([producto.md](producto.md) §9): plataforma
(inicio de sesión, usuarios, roles), propiedades y sincronización con HotelDruid, calendario,
«Hoy» e indicadores básicos. La especificación restante se escribe a medida que cada módulo
la necesita. Decidió: usuario.

### D-025 · 2026-11-03 · Entorno local con Wamp
PHP 8.3.28 de Wamp (trae todas las extensiones; el PHP 8.3 de WinGet no tiene `php.ini`),
MySQL 8.4.7 de Wamp y Composer en `.herramientas/` (fuera del repositorio). Laravel Herd no está
instalado. El motor de tablas se fija en InnoDB en la configuración, porque el `my.ini` de Wamp
pone MyISAM por omisión. **Por qué:** es lo que hay en el equipo y no necesita permisos de
administrador.

### D-026 · 2026-11-03 · Inicio de sesión y 2FA con Filament, no con Fortify
Filament 5 ya trae inicio de sesión, perfil y verificación en dos pasos (app de códigos con
códigos de recuperación). Las pantallas operativas son páginas propias dentro del mismo panel.
**Por qué:** un solo inicio de sesión y una sola sesión para toda la aplicación, y una dependencia
menos. Las páginas públicas para huéspedes (entrega Huéspedes) irán fuera del panel.

### D-027 · 2026-11-03 · Espejo genérico: una tabla `hd_filas` con las columnas en JSON
Sustituye a «una tabla `hd_*` por cada tabla de HotelDruid» (modelo-datos.md §4.3). Cada fila
de la copia se guarda con su tabla, su año y sus columnas en JSON. **Por qué:** no depende de las
columnas de cada versión de HotelDruid (3.08 y 3.081 difieren), es mucho menos código y la
proyección lee tabla por tabla en orden.

### D-028 · 2026-11-03 · Identidad de una reserva = su fecha de alta en la principal; la primera carga no genera eventos
La huella de identidad es solo `datainserimento`, y no el cliente más la fecha (modelo-datos.md
§6): cambiar el titular de una reserva es una modificación, no una reserva nueva. La primera
sincronización de una principal no genera eventos. **Por qué:** si no, las automatizaciones de la
entrega Operación escribirían a todos los huéspedes antiguos.

### D-029 · 2026-11-03 · Código SUB-016 «dirección no permitida»
Error propio para las URL que rechaza la validación SSRF (http sin red local, IP privada,
credenciales o consulta en la URL). Pausa la principal, como SUB-003. **Por qué:** distinguirlo
de un fallo de conexión.

### D-030 · 2026-11-03 · Numeración de versiones: 1.0.1, 1.0.2 … 1.0.99 y después 1.2
La primera versión que se entrega es la **1.0.1**: es la versión de prueba que antes se llamaba
0.1.0. Desde ahí solo sube el último número (1.0.2, 1.0.3 … 1.0.10 … 1.0.99) y después se pasa a
la **1.2**. Cada versión queda en `CAMBIOS.md`, en `config/alojaflow.php` (`version`) y en una
etiqueta de git `v1.0.x`. **Ojo:** la AlojaFlow anterior ya usa los números 1.2.x (está en la
1.2.3); hay que decidir antes de llegar a la 1.0.99 si el salto a la 1.2 continúa esa serie o
empieza otra. Decidió: usuario. **Resuelto en D-032.**

### D-031 · 2026-11-03 · Entregas con nombre, no con número
Las entregas de la fase 1 se llaman **Base, Operación, Huéspedes y Dinero** (producto.md §9).
Sustituye a los nombres «1.0, 1.1, 1.2, 1.3» de D-015, que se confundían con las versiones.
**Por qué:** con D-030, «1.2» es un número de versión.

### D-032 · 2026-11-03 · La AlojaFlow 1.2.x queda parada; la numeración nueva es independiente
El usuario dio por parada definitivamente la AlojaFlow anterior (1.2.x): esta empieza desde cero.
Su numeración (D-030) es independiente, y la 1.2 a la que se llegue después de la 1.0.99 no
continúa aquella serie. La subordinación a HotelDruid y a instalaciones 1.2.x como principal
sigue igual. Decidió: usuario.

### D-033 · 2026-11-03 · Actualizaciones con un paquete firmado y en dos fases
Cada versión se publica como un zip con un manifiesto (el SHA-256 de cada archivo) firmado con
Ed25519. Las instalaciones solo aceptan paquetes firmados con la clave de quien publica; la
pública va en `config/alojaflow.php` y la secreta, fuera del repositorio. La actualización se
aplica en dos fases, en peticiones o procesos distintos:
- **aplicar**, con el código viejo: extrae y comprueba los archivos antes del mantenimiento, copia
  la base de datos e intercambia los archivos anotando cada paso en un diario;
- **finalizar**, ya con el código nuevo: migraciones y cachés.

**Por qué:**
- en hosting compartido sin SSH, el zip por el panel o por FTP es el único camino;
- la firma impide instalar código alterado;
- las dos fases evitan migrar con código a medio cambiar;
- el diario permite volver atrás aunque el proceso muera.

Detalle: [especificacion/instalacion-actualizaciones.md](especificacion/instalacion-actualizaciones.md).

### D-034 · 2026-11-03 · Una copia de desarrollo (git) no se actualiza con el zip
Si la instalación tiene `.git`, ni el panel ni `alojaflow:actualizar` instalan paquetes (salvo con
`ALOJAFLOW_ACTUALIZAR_COPIA_GIT=true`): ese código se actualiza con git. Las actualizaciones se
prueban en una instalación hecha desde el zip (`E:\AlojaFlow-prueba`). **Por qué:** sustituir
archivos desde un zip dejaría cambios en el árbol de git y quitaría las dependencias de
desarrollo de `vendor/`.

### D-035 · 2026-11-04 · Modo independiente: el núcleo mínimo de la fase 2 se adelanta
Al crear una propiedad se elige entre **independiente** (`modo = nativa`: AlojaFlow es su sistema)
y **subordinada** (como hasta ahora). El modo no se cambia después; pasar de subordinada a
independiente será la migración de `modelo-datos.md` §5. Se adelantan unidades, tarifas, huéspedes,
reservas, bloqueos, entrada y salida, y pagos. Lo demás de la fase 2 sigue pendiente (ver
`CAMBIOS.md`, 1.0.3). Toda escritura de reservas pasa por `App\Reservas\GestorReservas`. La lógica
se toma de AlojaFlow 1.2.3 sin copiar código ni pantallas (regla 4). **Por qué:** lo pidió el
usuario al probar la 1.0.2: el sistema debe servir también a quien no tiene HotelDruid.
Decidió: usuario. **Cuidado:** en una independiente, AlojaFlow tiene la única copia de los datos,
y todavía no hay copia de seguridad completa desde el panel.

### D-036 · 2026-11-04 · Tarifas simples por noche con temporadas
`tarifas` (precio base por noche) y `tarifas_temporadas` (precio entre dos fechas), con
`unidades.tarifa_id`. El total de la reserva se reparte en partes iguales entre sus noches, como en
la proyección. Sustituye por ahora a `planes_tarifarios`, `precios` y `tipos_unidad` del diseño de
la fase 2, que resuelven canales y planes derivados que aún no existen. **Por qué:** cubre lo
habitual de un alojamiento pequeño con mucho menos código. Propuesta de Claude: se revisa con el
usuario. `reservas.version` hace el bloqueo optimista de `modelo-datos.md` §7.

### D-037 · 2026-11-04 · Demo como propiedad aparte, generada y sin usuario público
`propiedades.es_demo` marca una propiedad con datos inventados por `App\Demo\GeneradorDemo`, con
fechas alrededor del día en que se crea y sin Faker, que no va en el paquete. Se crea y se quita
desde Propiedades o con `alojaflow:demo`. No genera eventos ni auditoría de reservas: las
automatizaciones futuras no deben escribir a sus huéspedes. No crea un usuario `demo` con contraseña
conocida, como la 1.2.x. **Por qué:** el usuario quiere probar sin riesgo, y una contraseña pública en
una instalación que puede tener datos reales sería un riesgo.

### D-038 · 2026-11-04 · Copias de seguridad propias, sin spatie/laravel-backup
Zip con la base (`CopiaBaseDatos`, sin depender de `mysqldump`), los archivos subidos y una ficha.
Se crean a mano o cada noche, se conservan las últimas 14 y se pueden restaurar desde el panel:
antes se guarda el estado actual y, si falla, se vuelve a él. No llevan el `.env`; se descarga aparte
con la contraseña. **Por qué:** en hosting compartido no suele haber `mysqldump`, y el paquete de
spatie lo necesita. Sustituye a la dependencia prevista en CLAUDE.md.

### D-039 · 2026-11-04 · Las tareas programadas también corren sin cron
Si el cron no ha dado señales en 30 minutos, las tareas corren al terminar de atender una visita.
**Por qué:** hay hostings sin cron o con mínimos de 10 minutos (DreamHost), y el usuario quiere que
instalar sea lo más simple posible: el cron pasa a ser una mejora, no un requisito.

### D-040 · 2026-11-04 · Instalador de un solo archivo
`instalar-alojaflow-x.y.z.php` lleva dentro el zip firmado. Solo pide la base de datos y el
administrador, analiza el servidor y se borra solo. Sustituye al instalador por pasos dentro de
Laravel que preveía INICIO.md. **Por qué:** lo pidió el usuario: lo más sencillo posible. Un archivo
evita subir 15 000 por FTP; la copia por tandas evita los límites de tiempo.

### D-041 · 2026-11-04 · Tarifas completas y dinero de las propiedades independientes
Revisa D-036: cada tarifa elige si cobra por noche con persona extra o por persona, y puede tener
precio de fin de semana, estancia mínima y temporadas con esos mismos campos. El presupuesto
(`App\Reservas\Cotizador`) suma alojamiento, cargos, descuentos e impuestos, y guarda en
`reservas_cargos` el nombre y el importe de cada cargo: un cambio de catálogo no toca lo ya vendido.
**Por qué:** lo decidió el usuario («cada tarifa elige»); un motor de reservas no puede vender
sin este cálculo. Los impuestos pueden ir incluidos en el precio o sumarse.

### D-042 · 2026-11-04 · Motor de reservas sin sesión y con la página de pago de la pasarela
Páginas públicas en Blade con CSS propio, sin Livewire ni JavaScript obligatorio. Los errores se
muestran en la misma respuesta y los avisos van en la dirección, porque dentro de un iframe de otro
sitio no hay cookies; por eso esas rutas no llevan CSRF (no dan permisos: quien las usa solo puede
pedir una reserva para sí, y tienen límite de peticiones y campo trampa). Se vende por tarifa y el
sistema asigna la unidad. Con pasarela, la reserva se retiene `minutos_retencion` y
`alojaflow:reservas-vencidas` libera las que no se pagan.
Stripe Checkout y Mercado Pago Checkout Pro por su API REST, sin SDK: los datos de la tarjeta nunca
pasan por aquí. Solo cuenta lo que confirma la pasarela (su API o un aviso con firma válida), se
comprueban importe y moneda, y anotar el pago es idempotente. Si el pago llega tarde y las noches ya
se vendieron, se abre un aviso para que el alojamiento lo resuelva. **Por qué:** lo pidió el usuario;
una pasarela con SDK agrega peso y el hosting compartido no siempre lo soporta.
**Sin verificar con cuentas reales:** solo se ha probado con respuestas simuladas, según la
documentación de cada pasarela. Hay que hacer un cobro de prueba con llaves de prueba antes de usarlo.

### D-043 · 2026-11-04 · Una propiedad subordinada puede pasar a independiente, sin vuelta atrás
Acción en la propiedad, con copia de seguridad antes y escribiendo el código. Se niega si hay noches
con dos reservas en la misma unidad, porque desde ese momento la base de datos lo impide (D-019): se
resuelven en la principal. Lo pagado arranca como un pago «saldo inicial» por reserva. La principal se
borra de AlojaFlow con sus credenciales, y a ella no se le escribe nada. Las filas conservan `origen` y
`clave_origen` para saber de dónde vinieron. **Por qué:** quien deja HotelDruid no debe volver a capturar
todo. No se ofrece volver a subordinar: los datos ya habrán divergido.

### D-044 · 2026-11-04 · Demostración pública con usuario a la vista
Revisa D-037 a petición del usuario. `ALOJAFLOW_MODO_DEMO=true` solo en una instalación aparte, sin
datos reales: inicio de sesión ya lleno, aviso permanente, los correos van al log y `alojaflow:demo-reiniciar`
borra toda la base cada noche. Quedan fuera usuarios, actualizaciones, copias, estado del sistema, crear
o editar propiedades, llaves de pago, contraseñas y verificación en dos pasos. El comando se niega a
correr sin el modo activo. **Por qué:** se quiere poder enseñar el sistema sin riesgo. El riesgo que
preocupaba en D-037 (una contraseña pública en una instalación con datos reales) se evita porque
es otra instalación y se borra sola.

### D-045 · 2026-11-04 · Correo desde el panel y mensajes listos para copiar
El servidor SMTP se configura en **Configuración → Correo**, con la contraseña cifrada en `ajustes`:
quien no sabe editar un `.env` también puede. Sin configurar, ningún mensaje se pierde: queda en
**Mensajes** como «Listo para copiar», con enlaces a su correo y a WhatsApp. WhatsApp va por enlace
(`wa.me`), sin API de Meta. Las plantillas guardan su texto en JSON por idioma, como pide D-021, pero
sin `spatie/laravel-translatable`: son dos columnas y no compensa otra dependencia. **Por qué:** lo pidió
el usuario. Los mensajes por evento y fecha se generan en `envios`; la clave única impide duplicados.
No se escribe por lo ocurrido antes de crear la regla, ni a quien ya se fue.

### D-046 · 2026-11-04 · iCal: lo ocupado en un portal no se vende, pero no impide reservar a mano
Las fechas de un portal se guardan como `bloqueos_externos`, separados de las reservas. El motor y las
listas de libres no las ofrecen. La recepción puede reservar encima a sabiendas: aparece un choque y un
aviso. El calendario exportado solo lleva fechas, con identificadores que no revelan nada, e incluye lo
ocupado en otros portales para cerrarlo también allá. Las direcciones se guardan cifradas, se consultan
solo hacia redes públicas con la IP fija, y un fallo de lectura conserva lo que había. **Por qué:** iCal
tarda en propagarse y un portal puede fallar: una regla dura dejaría a recepción sin poder trabajar,
y borrar bloqueos ante un error abriría dobles reservas.

### D-047 · 2026-11-04 · Llegada en línea sin cuenta, documentos inmutables y datos personales con plazo
El huésped entra con un enlace con token (D-022) que solo vale para su reserva y vence al día
siguiente de su salida. Lo que manda se queda en tablas propias: en una subordinada nunca llega a la
principal, y en una independiente pasa a las fichas solo si el personal lo decide. Número de documento,
fotos, copias firmadas y exportaciones van cifrados.
Publicar un documento legal fija su texto y su huella; un cambio es otra versión. Cada aceptación guarda
la huella, la hora, la IP, cómo se verificó y un PDF de lo aceptado. El código por correo es opcional y
necesita correo configurado; sin él basta la casilla, para no dejar a nadie sin poder registrarse. No
hay OTP por WhatsApp ni SMS: necesitarían un proveedor de pago.
Los plazos de §8 se aplican cada noche. Anonimizar respeta lo que viene de la principal: allá hay que
hacerlo, y la pantalla lo dice. **Por qué:** es un dato personal delicado y la regla 12 pide guardar lo
mínimo el menor tiempo.

### D-048 · 2026-11-04 · Todo lo legal, detrás de un paquete de país
`App\Paises\Pais` da tipos de documento, campos extra, plazos y textos de partida; `Paises::de()` es el
único sitio que elige el paquete. México es el completo, con aviso de privacidad al estilo LFPDPPP y
tarjeta de registro; España, Estados Unidos y Genérico son básicos y `legalRevisado()` dice que no. Los
textos son de partida y **hay que revisarlos con un abogado** antes de publicarlos: la pantalla lo avisa.
Sin `symfony/intl` (decenas de MB de datos para un selector): nacionalidad y país van como texto.
No se envía nada a autoridades: SES.Hospedajes de España queda fuera, sin su tabla `envios_autoridad`.

### D-049 · 2026-11-04 · Facturación: CFDI 4.0 preparado, sin timbrar, con un conector para el PAC
Una factura emitida es inmutable y lleva copia de emisor y receptor; el número se toma bloqueando la serie
dentro de la misma transacción, sin huecos. Los impuestos se sacan del total de la reserva y lo que sobra
por redondeo va al primero: base + impuestos siempre da lo cobrado. `App\Facturacion\Cfdi40` genera el XML
con IVA en traslados e ISH en el complemento de impuestos locales; sello y certificado quedan vacíos. El
PDF dice «SIN TIMBRAR» hasta que un PAC lo timbre. `ConectorPac` es la interfaz; hoy está `SinPac`, que dice
con claridad que falta. Fuera de México se emiten recibos sin valor fiscal; VeriFactu queda fuera.
**Por qué:** el usuario pidió dejarlo preparado sin contratar un PAC todavía. **Sin verificar:** el XML no se ha
validado contra el XSD del SAT ni probado con un PAC; las claves (90111800, E48) las debe confirmar un contador.

### D-050 · 2026-11-04 · Políticas que calculan pero no cobran solas
La política del canal va antes que la predeterminada. Al cancelar se muestra la penalización y lo que hay que
devolver o cobrar, pero mover el dinero lo decide recepción con un pago o una devolución. Marcar «No llegó» en una
independiente cancela y libera noches; en una subordinada solo se anota. **Por qué:** cobrar solo una penalización
por un error de captura sería peor que recordarla. El cierre del día guarda una foto y no bloquea correcciones,
porque en un hotel pequeño siempre hay algo que corregir al día siguiente.

### D-051 · 2026-11-04 · Primero se traduce lo que ve el huésped
Motor, llegada en línea, mensajes de partida, documentos y textos legales genéricos van en los seis idiomas; una
prueba lo comprueba, junto con que cada traducción conserve sus variables. El panel del personal está en español
e inglés, y en los otros cuatro cae al inglés. Las traducciones son mías, sin revisión de hablantes nativos. **Por qué:**
México va primero; un huésped extranjero sí necesita leer su idioma, y el personal de un hotel mexicano no usa el
panel en alemán. Revisa parcialmente la previsión de CLAUDE.md de traducirlo todo al final de la Base.

---

## Pendientes

- Precios y modelo de venta.
- El sitio alojaflow.com y su wiki.
- Titular del copyright en las cabeceras de licencia.
- PAC para CFDI, proveedor de correo y canal del OTP.
- Inventario, almacenes y punto de venta: fase 2 o posterior.
