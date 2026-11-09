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
**Por qué:** hay hostings sin cron o con mínimos de 10 minutos, y el usuario quiere que
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
panel en alemán. Revisa parcialmente la previsión de CLAUDE.md de traducirlo todo al final de la Base. **Superada por
D-052** en lo que toca al panel.

### D-052 · 2026-11-04 · El panel del personal, en los seis idiomas
Francés, italiano, portugués y alemán tienen todas las claves de `lang/es`, también las de validación. Una prueba exige
que cada texto nuevo llegue a los seis. Solo `legal.mexico.*` se queda en español e inglés: en otro idioma se parte de
los textos genéricos. Vocabulario, el mismo que ya veía el huésped: propiedad es *établissement*, *struttura*,
*alojamento* y *Unterkunft*; huésped es *client*, *ospite*, *hóspede* y *Gast*; se trata de *vous*, *tu*, tercera persona
y *Sie*; el portugués es el de Portugal. Lo que se guarda como dato sigue en el idioma en que se escribió, y
`THIRD-PARTY-NOTICES.md` se muestra en español. Las traducciones son mías, sin revisión de hablantes nativos, que
`docs/producto.md` §8.5 pide antes de publicarlas: queda pendiente, y el usuario pidió sacarlas así en la 1.0.7, que
sigue siendo versión de prueba. **Por qué:** lo pidió el usuario.

### D-053 · 2026-11-04 · Auditoría de noviembre: qué se corrigió y cómo se revisó
Revisión completa de seguridad, rendimiento, accesibilidad, diseño y buenas prácticas, con el resultado en
`docs/auditoria-2026-11.md`. El usuario pidió corregirlo todo y publicar la 1.0.8. Los ataques se hicieron contra una
copia local; en línea solo una revisión suave del servidor de prueba.

### D-054 · 2026-11-04 · Reservas sin pago que nadie revisa: caducan a las 48 horas
Una reserva del motor sin pago en línea y sin confirmar retenía sus noches para siempre: cualquiera podía llenar un
calendario con un formulario público. Ahora caduca sola, a las 48 horas por omisión. Se cambia en Motor de reservas; con
0 no caduca, y la ayuda de ese campo dice el riesgo. Además: tres reservas vigentes por correo, treinta nuevas por hora
en cada propiedad (con aviso al alojamiento), cinco por minuto y veinte por hora por dirección, y hasta 730 días por
delante. **Por qué:** es la única forma de que el motor siga sin registro y sin captcha. **Riesgo que queda:** alguien
con muchas direcciones y correos todavía puede apartar noches durante 48 horas. Bajar las horas lo reduce.

### D-055 · 2026-11-04 · Limpieza y mantenimiento no ven datos del huésped
Antes, el permiso de operación daba acceso a fichas, teléfonos, saldos y recibos. Ahora, sin el permiso de datos, Hoy y
el calendario muestran unidad, fechas, personas y nombre, sin teléfono, sin dinero y sin entrar a la reserva. Cierre del
día y choques piden el permiso de datos, y resolver un choque o marcar no-show, el de reservas. **Por qué:** quien limpia
necesita saber qué cuarto y cuándo, no cuánto debe el huésped. El nombre se queda porque en un alojamiento pequeño es como
se habla de las habitaciones.

### D-056 · 2026-11-04 · Permisos con `Acceso` y `can*`, no con Policies; validación pública con `Validator`
La regla 4 pedía Policies y Form Requests. Los permisos ya están centralizados por propiedad en `App\Seguridad\Acceso`, y
Filament los consulta con sus `can*`: repetirlos en Policies daría dos fuentes de verdad. Las páginas públicas (motor y
llegada) validan en el controlador porque responden en la misma página y el motor no puede usar la sesión dentro de un
iframe. Se cambia la regla en vez de forzar el código.

### D-057 · 2026-11-04 · Cabeceras de seguridad, con CSP que admite scripts en línea
`CabecerasSeguridad` pone CSP, `nosniff`, `Referrer-Policy: same-origin`, HSTS sin subdominios y `X-Frame-Options`.
Alpine y Livewire piden `'unsafe-inline'` y `'unsafe-eval'`, así que la política sirve sobre todo para que nada se cargue
ni se mande a otro sitio. El motor de reservas queda fuera del límite de iframes, porque se puede insertar en el sitio del
alojamiento, y fuera de `form-action`, porque redirige a la pasarela. HSTS no incluye subdominios: el dominio puede tener
otros sitios que no se conocen.

### D-058 · 2026-11-04 · Sin avatares de terceros
Filament pedía las iniciales a ui-avatars.com: cada visita le mandaba a un tercero el nombre del usuario, en contra de
«todos los recursos se sirven desde la instalación». `AvatarLocal` las genera aquí. Lo destapó la CSP.

### D-059 · 2026-11-04 · Espejo cifrado y fuera de las copias
`hd_filas` guardaba en claro documentos y domicilios de la principal, aunque la ficha los cifrara. Ahora cada fila va
cifrada con la llave de la aplicación, no se incluye en las copias (se rellena en la siguiente sincronización) y solo
guarda las preferencias de `idutente = 1`, como pedía la especificación. Sustituye en esto a D-027, que sigue valiendo en
lo demás. La migración vacía la tabla; la siguiente sincronización la llena otra vez.

### D-060 · 2026-11-04 · Registros por día y cachés de arranque
Un `laravel.log` único crecía hasta agotar la cuota del hosting: ahora hay uno por día y se conservan 14. La
configuración, las rutas, los eventos y los componentes se dejan en caché al instalar, actualizar y restaurar, porque
armarlos en cada visita era lo que más tardaba (1.3 a 1.9 s en el servidor de prueba). Quien edita `.env` a mano no tiene que saber de
cachés: `bootstrap/app.php` descarta la configuración guardada si `.env` es más nuevo.

### D-061 · 2026-11-04 · El menú, por tareas
Con 39 entradas en cuatro grupos, Configuración y Operación ya no se leían. Ahora son Operación, Reservas, Dinero,
Comunicación, Configuración y Sistema, con los tres últimos plegados de inicio. Los botones ya usan `#0F766E`: Filament
pinta con el tono 600, y el generado a partir del hexadecimal no daba contraste con el blanco, así que caía al 400 con
texto oscuro. La paleta es el teal de Tailwind recorrido un tono. Las franjas de color de las tarjetas y del calendario se cambiaron por una
marca redonda junto al nombre.

### D-062 · 2026-11-04 · Verificación en dos pasos: se recomienda, no se obliga
Un administrador sin ella ve un aviso en el inicio y una fila en Estado del sistema. `ALOJAFLOW_EXIGIR_DOS_PASOS=true`
la vuelve obligatoria para todo el personal. **Por qué no se obliga:** el personal de un alojamiento pequeño no siempre
tiene cómo usarla, y bloquear a quien no puede sería peor que el riesgo. Es decisión de cada instalación.

### D-063 · 2026-11-04 · Recuperar la contraseña, solo con correo configurado
Sin correo no hay página ni enlace; se sigue resolviendo con `alojaflow:crear-administrador`. Con correo, la respuesta es
la misma exista o no la cuenta. La contraseña pide al menos 12 caracteres en todos lados, como el instalador.

### D-064 · 2026-11-04 · Una vez instalado, el instalador solo se puede borrar
Si lleva más de una hora en el servidor ya no empieza nada: hay que volver a subirlo, y eso demuestra que es de quien
manda en el servidor. Con la instalación hecha, cualquier petición lo borra y no dice nada más. La firma del paquete se
comprueba en cada paso. **Riesgo que queda:** en la primera hora, quien adivine el nombre del archivo antes que el dueño
podría usarlo. Pedir una clave complicaría lo que debía ser lo más simple posible (pedido del usuario).

### D-065 · 2026-11-04 · Anonimizar llega a todo lo personal, salvo lo que obliga la ley
Ahora alcanza el PDF de la aceptación, los datos como acompañante, los avisos sin ficha, notas, referencias de pago y
datos fiscales guardados para reutilizar. Las facturas ya emitidas conservan su copia del receptor: la ley fiscal obliga
a guardarlas. A quien viajó de acompañante se le reconoce por el documento o, sin él, por el nombre: dos personas con el
mismo nombre en una misma reserva se tratarían igual, y se acepta por poco probable. La exportación ya no incluye a
quienes viajaron con esa persona.

### D-066 · 2026-11-04 · Aviso de versiones de PHP con fallos críticos
La auditoría encontró que PHP 8.3.28, el de Wamp, tiene CVE-2026-109618 y CVE-2026-109619: ejecución de código sin iniciar
sesión, más grave con mod_php (php.net, 2026-10-21; corregidas en 8.2.35, 8.3.36, 8.4.27 y 8.5.12). Estado del sistema lo
marca en rojo y el instalador avisa, sin bloquear: un hosting puede haber llevado el parche a un número anterior. La lista
está en `App\Instalacion\VersionPhp` y en el instalador; se actualiza cuando PHP publique otra corrección crítica.
`composer.json` no sube el mínimo por lo mismo. Además, la aplicación ya no dice su versión de PHP en las cabeceras.

### D-067 · 2026-11-04 · El panel en un teléfono con mala red
Hoy daba 56 puntos y 9.8 s en Lighthouse móvil. Había dos causas: nada se comprimía, y Filament deja el contenido invisible hasta que
corre todo el JavaScript. Ahora `public/.htaccess` comprime y deja en caché un año lo que lleva versión en la dirección. En
pantallas de menos de 64rem, el contenido se ve sin esperar a Alpine. Livewire se sirve como archivo, sin arrancar PHP. Ya no van
`echo.js` ni la precarga de la fuente, y las celdas del calendario pesan un quinto. **Por qué así:** no hay CDN ni se compila
nada en el hosting, y todo lo del `.htaccess` se ignora si falta el módulo. **Los formularios siguen esperando a Alpine:** sin
JavaScript sus campos salen vacíos y lo que se escriba se perdería. **Riesgos que quedan:** comprimir HTML con un token abre en teoría
la puerta a BREACH, pero hace falta espiar el tráfico y forzar miles de peticiones con la sesión de la víctima; la fuente hace un
pequeño salto al llegar la primera vez; y no se ha comprobado en el servidor de prueba si `mod_deflate` está disponible. Cifras, lo que se
descartó y cómo comprobarlo, en `docs/rendimiento-movil.md`.

### D-068 · 2026-11-04 · Sin modo SPA en el panel
Se midió `->spa()` en los cambios de pantalla, con mala red y CPU lenta. La pantalla nueva aparece unos 0.4 s más tarde, porque Livewire no
deja pintar hasta terminar, y responde solo unos 0.13 s antes. Cada cambio hace además una petición más a PHP, para recoger notificaciones.
Volver atrás es mucho más rápido (de 1.07 a 0.27 s), pero enseña una copia vieja: una reserva que otra persona canceló sigue en Hoy hasta que
algo la refresca. **Por qué no:** en un teléfono con mala red se pierde lo que más se nota, y en recepción una pantalla vieja engaña. **Lo
que no es el motivo:** no se rompió nada de lo que se probó, y se activa con una línea si esto cambia. Cifras y pruebas en
`docs/rendimiento-movil.md`.

### D-069 · 2026-11-05 · Sistema modular
Quince módulos en `App\Modulos\Modulo`; hoy, calendario, reservas, huéspedes, usuarios, copias, actualizaciones, privacidad y
«Código fuente» no se apagan. Son **por instalación**, no por propiedad, y solo los cambia el super administrador (Sistema →
Módulos). Apagar no borra nada: sale del menú, sus páginas no abren y sus tareas se detienen. En la primera entrada de quien
instaló salen cinco preguntas que deciden qué se prende (`PrimerasPreguntas`); se pueden repetir y saltar, y si se saltan queda todo
prendido. Mientras nadie decide, todo está prendido. En una instalación que se actualiza, lo que había sigue prendido; lo que llegue
con versiones futuras llega apagado y con aviso (`modulos.conocidos`). **Dos excepciones a propósito:** el aviso de un cobro sigue
entrando con el módulo de pagos apagado, porque ese dinero ya se movió, y `alojaflow:mensajes` sigue corriendo: sus eventos se dan
por vistos sin generar nada, para que al volver a prender no salga un mensaje de hace meses. Al apagar mensajes o avisos a otros
sistemas, lo que estaba por salir queda como omitido. **Por qué por instalación:** es lo que pidió el usuario, y una
instalación es de un solo cliente. **Riesgo que queda:** apagar un módulo con una pantalla abierta no la cierra hasta que se recargue.

### D-070 · 2026-11-05 · Super administrador
`users.es_superadministrador`, por encima de `es_administrador`. Tiene acceso a todo y es quien instala. Solo él cambia módulos,
instala actualizaciones y nombra o toca administradores: si un administrador pudiera cambiarle la contraseña a otro, entraría como él.
Nadie se quita a sí mismo el acceso, así que siempre queda uno. Copias, estado del sistema y lo demás siguen siendo de cualquier
administrador. Al actualizar, el administrador activo más antiguo queda como super administrador y los demás no: en casi todas las
instalaciones es quien instaló. `alojaflow:crear-administrador` lo crea si no queda ninguno, porque quien llega a la consola ya manda en
el servidor.

### D-071 · 2026-11-05 · Instalador chico y entrada directa
`instalar-alojaflow-x.y.z.php` ya no lleva el zip: pesa unos 90 KB y lo baja de GitHub por pedazos, con `Range`, para caber en el límite de
tiempo del hosting. **Da igual de dónde venga,** porque el manifiesto va firmado y cada archivo se comprueba contra él. Si el servidor no
sale a internet, ofrece subirlo por partes o por FTP, y `instalar-alojaflow-x.y.z-completo.php` sigue llevándolo dentro. La versión está fija en
cada instalador. La revisión del servidor solo se detiene si hay algo en rojo. El servidor de la base se busca solo (`localhost`, `127.0.0.1` y
`mysql.tudominio.com`), y el puerto va en «Avanzado». Al terminar, un enlace de un solo uso deja entrar sin escribir la contraseña. Caduca en 30
minutos, se guarda su huella y solo lo recibe el navegador dueño del instalador. El cron deja de pedirse al final y pasa a los primeros pasos,
con el PHP de consola que encontró el instalador. **Por qué:** el usuario pidió una instalación más sencilla y dijo sí a esta propuesta.
**Riesgo que queda:** el enlace viaja en la dirección; por eso sirve una vez y el botón no manda `Referer`.

### D-072 · 2026-11-05 · Subir el paquete por partes
La pantalla de actualizaciones parte el zip en pedazos de 1 MB, que llegan por `POST /actualizaciones/subir-parte`. Muchos hostings
limitan las subidas a 2 MB y 30 MB no caben. La lógica viene de `actualizar.php` de la 1.2.3, reescrita. Volver a mandar una parte no la
duplica, y reintentar sigue desde donde iba. Al terminar se revisa solo, con un mensaje de espera, porque en la 1.0.10 no decía nada entre el
paso 1 y el 2. Al instalar sale de inmediato una página de «Instalando…» que se queda mientras el navegador espera, porque antes parecía que
el botón no hizo nada.

### D-073 · 2026-11-05 · Subordinar otra instalación de AlojaFlow
Una instalación copia una propiedad de otra, de solo lectura, como con HotelDruid. **Se conecta con un código** que genera la principal y se pega en la subordinada,
en vez de usuario y contraseña: se puede revocar uno sin tocar a nadie más, y da acceso a una sola propiedad. El token va en una cabecera y la principal guarda su huella.
Se comparte propiedad por propiedad y solo las independientes, así que no hay cadenas. El documento de identidad no viaja salvo que esa conexión lo permita, y las
fotos y los archivos nunca. La principal solo expone GET. La subordinada pregunta primero por una revisión y no baja nada si no cambió, aunque cada 6 horas baja todo de todos
modos: la revisión no ve cualquier cambio. Lo que llega se trata como dato ajeno, y se proyecta en una sola transacción. **Por qué un contrato propio y no la copia de seguridad:** esa
copia lleva todos los datos de la instalación, y aquí se comparte solo una propiedad. **Lo que aún no viaja:** los pagos y la caja uno por uno; llegan con la 1.0.15, igual que
los de HotelDruid. El total pagado de cada reserva sí. **Riesgo que queda:** las dos puntas deben tener la 1.0.12 o posterior. Contrato en `docs/especificacion/subordinacion-alojaflow.md`.

### D-074 · 2026-11-05 · Aviso de entradas y salidas y clientes omitidos
El aviso se arma de las reservas de cada día y se puede retocar antes de mandarlo. Viene del plugin de la 1.2.3, reescrito, y es un módulo aparte: como lo nuevo, en una instalación
existente llega apagado. **Clientes omitidos** es una pantalla propia, con una casilla por lugar: aviso, informes y mensajes automáticos. El usuario pidió casillas y no una sola lista. Se omite
una ficha o todos los de un tipo, con un campo de la ficha y su valor, como los colores del calendario. **Omitir no oculta la reserva:** sigue ocupando sus fechas, porque de otro modo se vendería
dos veces. **En informes no cuentan sus noches ni ingresos, pero los cobros y la caja sí:** ese dinero es real y tiene que cuadrar. Solo se omite al titular, no a los acompañantes. Las tareas de
limpieza se siguen generando: alguien tiene que limpiar. **Listas de comprobación:** una por tipo de tarea, y cada tarea guarda su copia. **Mantenimiento recurrente:** genera su tarea una semana antes, y si
la fecha ya pasó no acumula las atrasadas. WhatsApp usa el prefijo del país de la propiedad si el teléfono no lo trae.

### D-075 · 2026-11-05 · Permisos por usuario, límites y roles editables
Encima del rol, cada usuario puede tener por propiedad permisos de más o de menos y límites, en `accesos_usuario`. Sin fila, tiene lo de su rol: nadie cambia al actualizar. **Solo donde ya tiene un rol:** los ajustes no abren una
propiedad a quien no tiene acceso a ella. Se mantiene D-055: quien administra lo ve todo y no tiene límites. **Registrar entrada y salida y cobrar no cuentan como modificar:** es el trabajo de recepción, y frenarlo pararía el mostrador. Las horas
se cuentan desde que se creó la reserva, no desde la llegada: es lo que evita que alguien cambie una venta días después. **«Solo sus propias» no esconde el calendario,** porque ocultar la ocupación llevaría a vender dos veces la misma unidad: ahí ve la barra sin nombre ni enlace. Las
reservas anteriores no tienen autor y no se inventa: quedan fuera para quien tenga ese límite. **Los roles de fábrica se pueden editar, no renombrar ni borrar:** su etiqueta se traduce y el sembrado los necesita. El sembrado solo corre al instalar, así que una
actualización no pisa lo editado; si una versión futura añade un permiso, lo reparte su migración, y puede sumarlo a un rol retocado.

### D-076 · 2026-11-05 · Dispositivos recordados en lugar del «recordarme» de Laravel
El de Laravel es un solo token por usuario, en todos sus equipos, que dura 400 días y no se puede revocar uno por uno. Cada dispositivo guarda ahora un selector y la huella de un validador, y la cookie cambia en cada uso. La cookie anterior vale 2 minutos,
porque al restaurar el navegador llegan varias pestañas juntas; después anula el dispositivo, porque pudo copiarse. Lógica de la 1.2.3, reescrita. **Al salir se olvida el dispositivo.** Cambiar la contraseña o desactivar al usuario los olvida todos. **Las cookies viejas de Laravel
siguen valiendo** hasta que esa persona salga: invalidarlas sacaría a todo el equipo al actualizar. **Un dispositivo recordado no pide el segundo paso,** igual que antes. Quitar la verificación a otro es solo del super administrador, con su contraseña, y nunca a sí mismo: ahí manda el perfil.

### D-077 · 2026-11-05 · Auditoría de cambios con antes y después
Además de las acciones con nombre, un oyente anota lo que hace una persona con sesión sobre una lista cerrada de modelos. **No se anota sin sesión** (tareas, huéspedes, motor), ni la sincronización, ni la demostración: el registro responde a «quién del equipo cambió esto», y anotar lo demás lo
llenaría de ruido. **Lo personal no se copia:** de huéspedes, contactos, datos fiscales, facturas y grupos solo queda el nombre del campo, y lo mismo para lo cifrado, nombres, correos y notas de cualquier modelo. Copiar esos valores haría del registro otra base de datos personales, sin
anonimizar y guardada un año: el derecho al olvido dejaría de cumplirse. A cambio, no se puede ver qué decía un dato personal antes. El plazo es editable, con 30 días de mínimo.

### D-078 · 2026-11-05 · La caja de la principal, fila por fila
Una subordinada copia cajas, cobros y movimientos en las mismas tablas de una independiente, marcados con `sincronizada`, en lugar de tablas aparte: el Cierre del día y los informes no necesitan saber de dónde vienen. Se ven con el permiso de ver datos y no se pueden cambiar. **En HotelDruid, el cobro está en `soldi` y `costi` es el libro de la caja:** lo que `costi` trae de una reserva no se cuenta otra vez, solo dice en qué caja entró; se casa por reserva, importe y fecha, y si no casa, el cobro queda sin caja. Su concepto lleva el apellido del huésped: se lee el número de reserva y no se guarda. **Otra instalación de AlojaFlow** los ofrece como colecciones nuevas y anuncia cuáles tiene: así una principal anterior se sigue copiando, sin subir la versión de la API. Se compara con lo que hay en vez de reescribirlo, porque casi siempre no cambió nada. Al pasar a independiente quedan como propios, y el saldo inicial solo cubre lo que no cuadre.

### D-079 · 2026-11-05 · Informes: gráficas sin JavaScript y un pronóstico a la vista
Las barras son HTML y CSS, no Chart.js: se ven igual al imprimir, no cargan nada en el teléfono y la tabla de abajo tiene todos los datos. Dos colores, comprobados contra daltonismo en claro y en oscuro; cada valor va escrito. Con dos monedas no hay gráfica, porque no se comparan en una escala. **El pronóstico es lo vendido más lo que el año pasado todavía entró desde esta fecha:** sencillo y explicable, y necesita un año de historia. No se usa un modelo: nadie podría revisarlo. Cuentas por cobrar enseña el nombre del huésped solo a quien puede ver sus datos. Ingresos contra gastos e impuestos cuentan a los clientes omitidos: ese dinero es real.

### D-080 · 2026-11-05 · Turnos de caja y bitácora
Lo que entra a un turno se mide por **cuándo se capturó**, no por la fecha del pago: un cobro de ayer anotado hoy está en el cajón de hoy. Solo el efectivo de esa caja entra al arqueo. **Al cerrar no se muestra lo esperado:** quien cuenta primero escribe lo que hay, porque si no el conteo se acomoda al número. La base de datos impide dos turnos abiertos en una caja, y el acta no se edita. Solo en independientes: en una subordinada, la caja de verdad está en la principal. **La bitácora no es un chat:** es lo que un turno le deja dicho al siguiente. Vale en subordinadas, porque no toca reservas. Es texto libre y puede llevar nombres, así que se borra a los dos años, y en la auditoría solo queda que cambió.

### D-081 · 2026-11-05 · Empresas y agencias
Por propiedad, como los huéspedes: cada una tiene su lista de precios y sus condiciones. La reserva sigue teniendo un huésped titular; la empresa es quien reserva o paga. Comisión y días de crédito se proponen o se usan para saber cuándo vence, y **con reservas no se borra**, porque se perdería quién debe. Las cuentas por cobrar y el estado de cuenta salen de las reservas: no hay una contabilidad aparte. Facturarle a la empresa llega con la 1.0.17.

### D-082 · 2026-11-05 · Reglas de impuesto y una foto de lo cobrado
Vigencia por fecha de llegada, tope de noches y edad mínima, en el impuesto. Sin fechas de nacimiento en la reserva, la edad mínima usa cuántos menores se capturaron, y el campo solo aparece si algún impuesto lo necesita. Con tope, un porcentaje se reparte parejo entre las noches: el precio de cada una ya no se sabe si alguien cambió el total a mano. **Cada reserva guarda lo que se cobró (`reservas_impuestos`)**, despejándolo de su total: si cambian el total a mano, el impuesto lo sigue, y si mañana cambia la tasa, el informe no cambia. Las reservas anteriores no se rellenan, porque se inventaría con las tasas de hoy. **El catálogo de ISH es un punto de partida:** para 30 de 32 entidades viene de una fuente secundaria (elcontribuyente.mx), y Querétaro, Hidalgo, Oaxaca y Baja California Sur tienen cifras que no coinciden entre fuentes. Solo la Ciudad de México y Quintana Roo se cotejaron con su ley (praxiumconsultores.com). Por eso cada tasa enseña su fuente y la ley donde comprobarla, y un asesor fiscal sigue pendiente.

### D-083 · 2026-11-05 · Lo de todos los días de la 1.2.3: mensajes, depósitos, bloqueos y precios
Vuelven con la misma idea: **nada sale solo**. Mensajes por periodo y recordatorios de depósito preparan el texto y una persona pulsa; un cliente omitido sí se puede escribir a mano, porque lo omitido es lo automático. Las variables propias se guardan por propiedad y no pisan las del sistema. **Bloquear fechas usa reservas de tipo bloqueo,** no otra tabla: el calendario, el motor y la base de datos ya las entienden, y la que tiene algo se salta sin mover nada. Los cierres de HotelDruid (`regole` con `motivazione2 = x`) se proyectan igual. **Importar precios parte las temporadas** en lugar de solo poner una encima: como vale la que empieza más tarde, una que empezara antes no habría sustituido a las que ya hubiera en ese rango. El archivo lo lee el navegador y llega como texto: así no pasa por el disco, y se vuelve a revisar al importar. El cierre guarda solo números de reserva: el cierre se conserva, y con él no hace falta conservar el nombre de nadie.

### D-084 · 2026-11-05 · Restricciones de venta
Por fechas y, opcionalmente, por unidad. **El motor las cumple; a recepción solo se le avisa:** en el motor nadie puede decidir, y en recepción un caso especial lo resuelve quien atiende. Las noches de preparación miran las noches antes de llegar y después de salir, en esa unidad. Solo restringen lo que se vende por el motor: no se reprograma nada de lo ya reservado.

### D-085 · 2026-11-05 · Enlaces de pago por importe libre
Para cobrar un anticipo, un extra o un daño: la página del motor solo cobra lo que falta. Usan el mismo `enlaces_publicos` y las mismas pasarelas. **Se pagan una vez y vencen:** cobrar de más por un enlace viejo cuesta más trabajo que hacer otro. Un extra puede dejar la reserva con saldo a favor sin ser un pago duplicado, así que el aviso de duplicado solo salta si el mismo enlace se paga dos veces. La página pública enseña el concepto, el importe y el número de reserva, no el nombre del huésped: quien tenga el enlace no tiene por qué saberlo.

### D-086 · 2026-11-05 · Automatizaciones con condiciones
Saldo, canal, datos de llegada y documentos aceptados, además del estado. Pueden crear una tarea o una nota importante en la bitácora en vez de un correo, y entonces la plantilla es opcional. **«Esperar» es lo que resuelve los datos de acceso:** antes de la llegada se revisa cada día hasta que se cumpla, y la clave de envío es la fecha de la reserva, así que sale una sola vez. Sin esperar, la regla mira solo el día que toca, como antes.

### D-087 · 2026-11-05 · Encuesta a los huéspedes
En una tabla propia y con un módulo aparte, porque mucha gente no la va a querer. **A todos se les ofrece la página pública de reseñas, contesten lo que contesten:** mandar solo a los contentos falsea las reseñas y los portales lo prohíben. La automatización que se prepara queda apagada: un correo a todos los huéspedes es una decisión de quien la va a usar. El comentario puede llevar datos personales: entra en la exportación y se borra al anonimizar.

### D-088 · 2026-11-05 · Duplicados y anonimizar por antigüedad
**El detector solo propone:** dos personas pueden llamarse igual, y fusionar no se deshace. Una sola palabra no cuenta como nombre, y no entran las fichas de una principal, que se fusionan allá. Tampoco quien solo ve lo suyo: fusionar junta fichas ajenas. **Anonimizar por antigüedad pide contraseña y el permiso de ajustes, no el de huéspedes:** uno por uno basta una palabra, pero aquí un descuido borra a cientos. Va por tandas de 200 para no pasar del tiempo de una petición. La ficha también tiene que ser vieja: alguien que dejó sus datos ayer no lleva años sin venir. El tipo de cliente de la 1.2.x se configuraba en un archivo del servidor; aquí se escribe en la ficha, con lo que ya existe en `campos_huesped` y `colores_tipo_cliente`.

### D-089 · 2026-11-05 · La base se pone al día sola
Si alguien sube los archivos a mano, la primera petición lo arregla (`EsquemaAlDia` y su middleware). **La marca es una versión en un archivo** para no tocar la base en cada petición, y la versión nueva se lee de `config/alojaflow.php` como texto, porque en producción la configuración está en caché y seguiría diciendo la anterior. **Por eso primero se quitan las cachés y se redirige:** esa petición arrancó con las rutas viejas, y migrar y atender se hace en la siguiente, que arranca limpia. Con un candado, la otra petición espera. **Un fallo no se repite en cada visita:** la copia se hace una vez y la migración se reintenta a lo mucho cada minuto, porque si no, la copia llenaría el disco. La copia previa no es requisito: los archivos ya están arriba y sin migrar la aplicación no abre. El instalador y el actualizador dejan la marca al terminar.

### D-090 · 2026-11-05 · Instalable sin service worker, y colores por usuario
`manifest.webmanifest` e iconos, sin service worker: datos viejos en una recepción son peores que no abrir, y sin conexión no hay nada útil que hacer con una reserva. Las direcciones del manifiesto son relativas porque la aplicación puede vivir en una subcarpeta. **Los colores de los estados son del usuario, no de la propiedad,** porque son una preferencia de quien mira. Solo pasan colores `#rrggbb` (van dentro de un `<style>`), y la letra se calcula por contraste: nadie tiene que saber de accesibilidad para elegir uno.

### D-091 · 2026-11-05 · Número de registro turístico
Tabla propia y no un campo, porque hay registros por propiedad y por unidad, y el mismo alojamiento puede tener varios. Vale también en una subordinada: el registro no depende de quién lleve las reservas. En las páginas públicas se enseñan todos; en un documento, los de la propiedad y los de la unidad de esa reserva. `alojaflow:limpiar` revisa cada noche y guardar revisa al momento, para que renovar quite el aviso sin esperar. No se valida el formato del número: cambia por país y estado, y un rechazo equivocado impediría cumplir la ley. **`funciones-nuevas.md` también lo pedía en las exportaciones:** un CSV no tiene dónde llevar un pie, así que irá en cada archivo oficial que lo pida (España, en la 1.0.20).

### D-092 · 2026-11-05 · Regiones con código ISO
`CatalogoRegiones` con ISO 3166-2 de México, Estados Unidos, Canadá, España, Francia, Italia, Alemania y Portugal. Son nombres propios y no se traducen. No se hacen todos los países de Europa de memoria: uno mal escrito es peor que escribirlo a mano, y por eso `propiedades.subdivision` crece a 100 caracteres. Cambiar de país borra la región, y en el domicilio del huésped es solo una sugerencia, porque ahí escribe lo que dice su identificación.

### D-093 · 2026-11-05 · Días y franja de sincronización
En la hora de la propiedad, no en la del servidor. **Una franja que cruza la medianoche es del día en que empieza:** la de 22:00 a 06:00 del lunes sigue el martes hasta las 6, porque así se lee en el formulario. Marcar todos los días o ninguno es lo mismo, sin límite: nadie quiere una principal que no se copie nunca. Copiar a mano no pasa por aquí.

### D-094 · 2026-11-05 · Cada habitación de un grupo sigue siendo una reserva normal
Los informes, la caja, la disponibilidad y las automatizaciones no tienen que saber de grupos. Lo que es del grupo va aparte, en `grupo_habitaciones`: el precio por noche propio y quién va. **El titular sigue siendo el coordinador**, y el responsable es un dato de la lista: crear una ficha por cada nombre que escribe un tercero llenaría de duplicados. El calendario enseña al responsable si lo hay. **Los pagos se reparten en las reservas**, no se llevan aparte como en la 1.2.3, para que la caja y los informes cuadren con todo lo demás. Un grupo cancelado no se revive: sus noches ya pudieron venderse, y por eso se copia.

### D-095 · 2026-11-05 · Precio del grupo
La lógica es la de `hd_grp_calcula`, reescrita en `App\Grupos\CalculadorPrecio`, con una diferencia: **la suma de las reservas es siempre el total**, porque los centavos que no se pueden repartir van a la última. Los extras se reparten entre las que pagan: una habitación regalada se queda en cero. Con la tarifa normal y sin nada que la cambie no se toca ninguna reserva, porque recepción pudo haber puesto un precio a mano; al cambiar los precios del grupo o pedirlo con el botón sí se recalcula, y el botón lo advierte. Las cuotas no guardan si están pagadas: lo pagado se aplica a las primeras en vencer, y así confirmar o quitar un pago nunca deja el calendario desfasado.

### D-096 · 2026-11-05 · El coordinador entra con enlace y clave, sin cuenta
**Hacen falta las dos cosas.** No es un usuario del panel: su sesión vale solo para ese grupo, y cambiar la clave saca a quien tuviera la anterior. La clave que se le da se guarda cifrada para poder enseñársela de nuevo al alojamiento hasta que la cambie; la que él elige no se guarda en claro y nadie puede verla. Generar una nueva cambia también el enlace. Cinco errores bloquean 15 minutos y veinte por conexión la bloquean una hora, siempre con el mismo mensaje y comprobando la clave igual, para que lo bloqueado no se note. Caduca unos días tras la salida. El contrato es una plantilla (`contrato_grupo`, que no se le pide al huésped que llega) y **la huella es del texto ya con los datos del grupo:** si cambia el precio, no es lo que se aceptó. Cambiar el idioma de la página no pide aceptar otra vez lo mismo: se sigue el idioma en que se aceptó.

### D-097 · 2026-11-05 · Comprobantes de pago
Un comprobante no es un pago hasta que alguien lo confirma, y no cuenta en ningún saldo. Es la misma tabla para grupos y para reservas del motor. **Mientras se revisa, la reserva no caduca:** ya mandaron el dinero. Si se rechaza, le vuelve a correr el plazo, para que un comprobante falso no aparte noches para siempre. **La imagen se vuelve a dibujar** con GD y se guarda cifrada: se mira el tipo real, se cuentan los píxeles antes de abrirla y se aplica la orientación del teléfono, que se perdería. PDF no: no hay cómo limpiarlo. Límite de 20 por hora y 300 por objeto. Las imágenes se borran a los 24 meses de la salida, y con ellas los nombres de la lista y los mensajes del grupo; las reservas y los importes se quedan.

### D-098 · 2026-11-05 · PAC: cada entidad el suyo, y el certificado se queda allá
**SW Sapien, por su API y sin SDK, con su servicio de emisión** (`/cfdi33/issue/v4`): recibe el XML sin sellar y lo sella con el certificado que el alojamiento subió a su cuenta. Se eligió porque así AlojaFlow nunca toca un CSD ni su contraseña, que son lo más delicado que tiene un contribuyente, y porque un hosting compartido no es lugar para guardarlos. **Cada entidad fiscal guarda su token,** cifrado junto con su configuración: dos razones sociales no comparten cuenta. Se guarda solo si funciona, para que un error de dedo se vea al conectar y no al facturarle a un huésped. El modo de la entidad decide si va al ambiente de pruebas o al real. Lo que devuelve el PAC es lo único que se cree: **los datos del timbre en el PDF se leen del XML timbrado,** no se guardan aparte, para que no puedan diferir; el QR, que viene de fuera y va a un PDF, solo si es base64. **Timbrar lleva candado:** cada timbre es un CFDI vigente ante el SAT, y dos clics dejarían uno sin dueño. **Cancelar no deshace nada:** se pide con la clave del SAT y queda como cancelada; consultar si el receptor la aceptó queda para después. Rehacer el PDF no puede hacer fallar lo fiscal, que ya pasó: si falla, se anota.

### D-099 · 2026-11-05 · Cobros que tardan: OXXO y transferencia
Stripe usa métodos dinámicos: lo que enseña se activa en su panel, y aquí solo se le dice lo que hace falta (días de la ficha, meses, y el cliente que exige la transferencia). **Un cobro «en espera» no es un pago:** no cuenta en ningún saldo, pero sus noches no se sueltan a los 30 minutos. Se apartan lo que dure la ficha más una hora; una transferencia, que no vence, 72 horas; y nunca después del día de llegada, porque una ficha sin pagar no debe apartar noches que ya pasaron. Tampoco se ofrece una ficha más larga que lo que falta para llegar. **Solo se toca el plazo de quien ya tiene uno:** una reserva hecha por recepción, sin plazo, no empieza a caducar porque alguien abrió un enlace de pago. Si la retención ya había vencido, el vale existe y puede pagarlo: se intenta recuperar, como con un pago tardío. **Mientras haya una ficha abierta no se abre otra,** porque se podrían pagar las dos; el costo es que no puede cambiar a tarjeta hasta que venza. Se anota el medio real del pago, y lo que no es tarjeta ni transferencia queda como «otro». Mercado Pago ya ofrecía estos medios en su propia pantalla, y sus fichas pasan por el mismo camino. El cliente de Stripe lleva nombre y correo porque Stripe los pide para una CLABE personal; se guarda su id en el huésped para no crear uno por cobro, y si cambian las llaves se crea otro.

### D-100 · 2026-11-05 · Registro de viajeros: se concilia, no se engancha
Hay muchas formas de crear o cambiar una reserva, y hay que comunicarlas todas en 24 horas (RD 933/2021). En lugar de un gancho en cada una, **`GestorEnvios` compara cada 10 minutos lo que hay con lo que se comunicó, y hace lo que falte**: así tampoco se pierde nada si el servicio estaba caído. El protocolo va detrás de `RegistroViajeros`, que da cada `Pais`; SES.Hospedajes es SOAP escrito a mano, sin pedirle la extensión al hosting. **`envios_autoridad` no guarda lo que se mandó:** se arma de la reserva cada vez, para no tener otra copia de los documentos de la gente; una huella dice si cambió algo y hay que anular y mandar otra, porque el servicio no modifica. **Lo que alguien corrige a mano va cifrado en el envío, y no toca la ficha:** en una subordinada ni se podría, y es para este trámite. Una comunicación con datos de menos no se manda: se dice qué falta antes, porque el servicio lo avisaría un día después por correo. Solo se comunica lo que es un contrato: una reserva confirmada, no una retención del motor. Lo anterior a conectarse no se manda de golpe. Del pago se manda tipo y fecha, sin titular ni medio, que son opcionales: AlojaFlow no tiene los números. **Certificado:** el servidor no manda el intermedio de la FNMT, y va en la instalación.

### D-101 · 2026-11-05 · Tasa turística
Son impuestos normales por persona y noche, con tres reglas nuevas: tarifa fijada el día de la reserva (Cataluña, art. 34.3), descuento en temporada baja y descuento desde cierto día (Baleares). **Se calculan noche por noche**, porque una estancia puede cruzar de temporada. Barcelona se da con el recargo ya sumado, como dice su art. 35.2; el de otros municipios se suma a mano, porque cambia por ordenanza. **En la factura van en su línea, con unidades y tarifa y sin IVA**, y no dentro del hospedaje. `reservas_impuestos` guarda unidades y exentas, para declarar sin recalcular. El catálogo solo trae lo cotejado con la ley: Cataluña y Baleares. El archivo de la ATC cuenta las estancias en el semestre en que empiezan, y lo dice: repartirlas noche a noche pide saber el reglamento, y un error ahí es peor que avisar.

### D-102 · 2026-11-05 · Redsys y Bizum
Redirección con `HMAC_SHA256_V1`, sin SDK. Redsys no se abre con un enlace sino con un formulario firmado que manda el navegador, así que hay una página puente (`motor.ir`), a la que se llega con la referencia firmada del cobro. **Un número de pedido nuevo cada vez que se abre:** Redsys no deja repetirlo, y quien vuelve de una tarjeta rechazada tiene que poder intentarlo. El cobro se reconoce por `Ds_MerchantData`, que va firmado. Solo se ofrece en euros. Bizum es otra pasarela con el mismo terminal, para que el botón y el medio anotado sean los suyos. Se hizo un cobro completo en su entorno de pruebas real, con el comercio y la tarjeta de pruebas que publican, y su resultado firmado confirmó la reserva.

### D-103 · 2026-11-05 · VeriFactu: todavía no
AlojaFlow en España emite recibos sin valor fiscal, y exporta las facturas por tipo de impuesto para pasarlas a un programa certificado. **No se genera el registro encadenado:** la ley dice 2027, Hacienda anunció octubre de 2028 sin que esté en el BOE, y hacerlo pide una declaración responsable del productor que hay que ver con un asesor, más aún en software libre con muchas instalaciones. Hacerlo mal sería peor que no tenerlo.

### D-104 · 2026-11-05 · La página pública es la de reservar
No hay otra página: la que ya se comparte se vuelve la del alojamiento, porque quien no tiene sitio web es justo quien más la necesita y dos páginas se desactualizan. Los textos van por idioma en `propiedades.pagina_publica`, y lo no traducido sale en el idioma de la propiedad: mejor en otro idioma que vacío. **Las fotos se vuelven a dibujar**, con lo mismo que los comprobantes: una foto de teléfono trae dónde se tomó, y son públicas. Se guardan como cualquier archivo, para entrar en las copias y al borrar la propiedad, pero se sirven sin sesión desde su propia ruta, con caché de un año: una foto nueva es otra dirección. Dos tamaños, porque la página debe abrir en un teléfono con mala señal. La tira de fotos no lleva JavaScript. Sin mapa incrustado: traería cookies de un tercero y rompería la promesa de no cargar nada de fuera; es un enlace.

### D-105 · 2026-11-05 · Mi reserva: un enlace, y pedir no cambia nada
Sin cuenta, con un enlace personal que ya tiene quien recibe el correo, y por eso no enseña documentos ni nada que no esté ya en ese correo. Caduca una semana después de la salida y sobrevive a un cambio de fechas: es el que tiene guardado. **Pedir un cambio solo le avisa al alojamiento**, como nota de la reserva, aviso y correo: mover una reserva tiene precio y disponibilidad, y decide una persona. **Cancelar solo está apagado de entrada**, y aun prendido solo vale cuando no hay nada que decidir: sin pagos que devolver, antes del día de llegada y sin penalización. Esto vale también en subordinadas, salvo pagar y cancelar, que escriben en la principal. Es un módulo, junto con la guía.

### D-106 · 2026-11-05 · La guía tiene dos puertas
El QR de la unidad es público en la práctica, porque cualquiera puede fotografiarlo y no caduca; Mi reserva es personal y caduca. **Lo delicado solo sale por la segunda, y solo durante la estancia.** Se puede además condicionar a que no deba nada, como las automatizaciones de datos de acceso. Cambiar el código deja de validar el impreso, por si alguien se llevó la foto. El QR es SVG, para imprimirlo nítido, con `chillerlan/php-qrcode` (MIT/Apache 2.0), que ya venía con Filament para la verificación en dos pasos.

### D-107 · 2026-11-05 · El mes es de calendario, y la temporada manda
Quien renta por mes dice «12 000 al mes», no «400 la noche»: un mes va de un día al mismo día del mes siguiente y cuesta lo mismo con 28 que con 31 noches. Lo que sobra se cobra a treintavos. El umbral es de 7 y 28 noches, los que usan los portales. **Una noche en una temporada solo usa precio largo si esa temporada tiene el suyo**: si la temporada alta heredara el precio del mes, tres semanas de Navidad saldrían regaladas sin que nadie lo decidiera. Se reparte redondeando lo acumulado, para que la suma de las noches dé el precio exacto. El fin de semana no encarece un precio largo. Se ignoran con el módulo apagado. El tope «no cobrar desde N noches» del impuesto es un dato de cada impuesto y no de cada país: lo llena quien sabe qué aplica en su lugar.

### D-108 · 2026-11-05 · Un calendario de cobros que no se marca
Una cuota no guarda si está pagada: lo pagado se aplica a las primeras en vencer, igual que en los grupos, y por eso comparten `CalendarioPagos::repartir`. Así no hay dos verdades, y un pago de lo que sea, por caja, enlace o comprobante, cuenta solo. Guarda las fechas que cubre y no un texto, para decirlas en el idioma de cada quien; el concepto es solo para lo que no es un periodo. Solo en independientes (en una subordinada los pagos los lleva la principal) y fuera de los grupos, que tienen el suyo. **Los recordatorios son automatizaciones**, con dos disparadores nuevos, y no otra cosa aparte: se cambian donde ya se cambia todo. Se prenden con un botón y no solos, porque mandar correos a huéspedes lo decide el alojamiento. Tienen margen de dos días por si no corrieron las tareas, y uno por pago; **lo que ya estaba vencido cuando se capturó o cuando se creó la regla no se reclama**, porque generar el calendario de una estancia ya empezada le mandaría a alguien un reclamo por algo que quizá solo falta anotar. Mi reserva cobra en línea lo vencido o el siguiente pago; el depósito, si es mayor.

### D-109 · 2026-11-05 · Una factura por pago, con los mismos conceptos
Lo que se cobra cada mes se factura cada mes, cuando se pide y no solo: una factura necesita datos que solo tiene quien la pide. La factura apunta a su cuota desde `facturas.cuota_reserva_id` y no al revés, para que una cancelada conserve de dónde vino y la cuota se pueda volver a facturar. **Lleva lo mismo que la reserva, en proporción**, redondeando lo acumulado para que sume justo lo de la cuota. Se comprueba con la cuota bloqueada, porque dos clics darían dos documentos fiscales. No se mezclan los dos caminos: con la reserva entera facturada no se factura un pago, y al revés. Lo facturado ya no se edita ni se quita, y generar otra vez reparte solo lo que falta, desde donde se quedó.

### D-110 · 2026-11-05 · Lo que tiene un lugar, y lo que trae el huésped
Los atributos van en una columna JSON de la unidad y no en una tabla por cada uno: son pocos, solo se leen enteros y solo importan en un camping. Un largo se guarda como lo dicen allá y no se convierte, porque el país lo decide y nadie mezcla pies con metros en su propio camping: detrás de `Pais::unidadLargo`. **El filtro descarta solo lo que sabe que no sirve:** un lugar que no dice su largo se ofrece, porque se puede preguntar y una reserva perdida no se recupera. Una oferta junta varios lugares y solo promete lo que tienen todos. El equipo del huésped va cifrado en la reserva: las placas dicen de quién es un vehículo. **El plano público no dice quién está**, solo si se puede elegir, y se sirve solo en formatos comunes: un SVG puede llevar código y lo abriría cualquiera. Elegir es un cargo del catálogo, y no un campo más, para que salga en la factura como cualquier otro. Si el lugar elegido se ocupa, se le dice y no se le da otro: justo eso era lo que quería decidir.

### D-111 · 2026-11-05 · Nadie paga lo que gastó el de antes
Se cobra lo que subió el medidor a quien está en el lugar, y solo si la lectura anterior se tomó desde que llegó; si no, esta lectura es su punto de partida. Se pierde lo gastado entre su llegada y la primera lectura si nadie leyó al llegar: es preferible a cobrarle a alguien el consumo de otro. El día de la salida responde quien se va. La tarifa y el importe se guardan al cobrar: mañana puede cambiar el precio. Son cargos sin concepto de catálogo, y por eso el formulario de la reserva ya no los borra al guardar, y su botón de aplicar la tarifa los conserva. **Con calendario, son un pago aparte** que no se reparte y se factura tal cual: repartir 300 pesos de luz entre cuatro facturas de hospedaje no diría la verdad. Solo se quita la última de cada medidor, porque las demás son el punto de partida de otra. Sin lectura automática: importar de medidores inteligentes requiere un servicio de pago.

### D-112 · 2026-11-05 · Un residente es una estancia larga
No hay otro tipo de reserva: es quien se queda 28 noches o más, con el calendario, los recordatorios y las facturas de la 1.0.22. Solo se agregan un precio de temporada completa y una lista. El precio se reparte entre las noches de la temporada solo si las cubre todas; lo que sobre se cobra normal. **El reglamento ya se firma en el registro de llegada**: la lista dice si falta y le manda ese enlace, para no tener dos caminos a una misma firma. Los mensajes masivos son los de Mensajes por periodo, con un filtro más: uno por uno y sin mandarse solos, como decidimos en D-083.

### D-113 · 2026-11-05 · El cupo se cuenta por noche
Un extra apartado ocupa cada noche de la estancia, y para unas fechas manda el peor día. Se comprueba con las filas bloqueadas, para no vender dos veces la última bicicleta. **El motor no vende de más; el panel solo avisa**, como con las restricciones: un caso especial lo decide quien atiende. Marcar la casilla de un extra con cupo también pasa por la comprobación, por si alguien arma el formulario a mano.

### D-114 · 2026-11-05 · Un dueño es un acuerdo sobre unas unidades
Una unidad tiene un solo dueño y lo dice en su fila: varios dueños de una misma unidad o participaciones complican todo lo demás y nadie lo ha pedido. El acuerdo es comisión, si va sobre lo que queda tras el portal, y un cargo fijo; una unidad puede tener su propia comisión, y un cargo del catálogo puede ser del administrador. Solo en independientes: en una subordinada, los números los lleva la principal. **El lado fiscal no entra:** la factura de la comisión, las retenciones y los formularios de cada país necesitan un asesor que nadie ha consultado, y el informe tampoco los investigó. Tampoco contabilidad fiduciaria. **Como la unidad no guarda de quién fue antes,** quien la cambia de dueño cambia lo que se calcule desde entonces, también para meses pasados: lo ya mandado no se mueve, pero avisa que difiere.

### D-115 · 2026-11-05 · Un estado de cuenta es una foto
Se calcula por mes, y **una estancia entre dos meses se reparte por noches, redondeando lo acumulado**: si no, los meses no sumarían lo cobrado. Solo cuenta lo confirmado: lo que sigue sin confirmar puede vencerse. El ingreso del dueño es el total sin impuestos cobrados ni cargos del administrador. Se guarda lo calculado, para que lo que el dueño ya vio no cambie solo cuando alguien mueva una reserva: el borrador se recalcula, lo mandado no. Si después difiere, se avisa, y reabrirlo lo vuelve a borrador con lo de hoy: los ajustes automáticos entre periodos son justo lo que hace frágiles a estos módulos. Anotar el pago no mueve dinero. El PDF se hace de la foto, sin guardarlo. No lleva el nombre del huésped, porque el dueño ve este mismo detalle.

### D-116 · 2026-11-05 · El portal del dueño es el del coordinador
Enlace secreto y clave, sin ser usuario del panel: los permisos del panel son por propiedad y un dueño es solo una parte de ella. Por eso `AccesoConClave` salió de `AccesoCoordinador`, y los dos comparten todo. Ve lo de sus unidades sin datos de huéspedes, y no hay un ajuste para verlos: quien se hospeda no contrató con él. **Sus estancias son bloqueos que dicen de quién son**, con la misma comprobación de noches ocupadas que cualquier reserva; se le avisa al alojamiento, porque esas noches dejan de venderse. Las libera mientras no hayan empezado, y también se avisa. Mientras siga con la clave que le dieron, que también conoce el alojamiento, no entra a nada de su cuenta. El comprobante de un gasto baja como archivo, sin abrirse en la página.

### D-117 · 2026-11-05 · Qué entra del núcleo heredado de HotelDruid
El usuario eligió las 10 recomendadas de [nucleo-heredado.md](nucleo-heredado.md): cambiar fechas arrastrando, camas extra, varias tarifas por unidad, tarifas que salen de otra, días y condiciones en los cargos, documentos propios, campos propios, calendario público e importar de un archivo. **Cierra el «por decidir» de `producto.md`:** punto de venta, inventario y almacenes no entran. Lo demás de la lista se queda fuera hasta que alguien lo pida. Publicar en GitHub queda para cuando él lo diga: su permiso era para las versiones del plan anterior.

### D-118 · 2026-11-05 · Una tarifa principal y otras
`unidades.tarifa_id` se queda como la principal, y las demás van en `tarifa_unidad`. Sin principal, cada pantalla tendría que elegir una, y una unidad con una sola tarifa, que es casi todas, no debe preguntar nada: el campo de tarifa de la reserva solo sale si hay más de una. La reserva guarda `tarifa_id` además del nombre, y **se queda con ella hasta que alguien elija otra**: retirar una tarifa o mover la reserva no cambia lo que se pactó ni su política. Pedir una tarifa que esa unidad no vende da la principal: un formulario manipulado no se lleva otro precio. En el motor hay una oferta por tarifa, la más barata por lo que cuesta esa estancia: el precio base no dice nada de una que sale de otra. Un lugar del plano se ofrece con la más barata de las suyas. El mínimo y el máximo de personas filtran en el motor y solo avisan en el panel, igual que la estancia mínima: un caso especial lo decide quien atiende.

### D-119 · 2026-11-05 · Una tarifa puede salir de otra
Lleva porcentaje e importe por noche: la «no reembolsable» es un porcentaje, y un desayuno es un importe. Cobra con el modo, los precios, las temporadas y las estancias largas de su base, y conserva lo que es suyo: nombre, descripción, personas, política y su propia estancia mínima, de la que vale la mayor. Sin fotos propias usa las de la base, porque es la misma habitación. **Una sola vuelta:** la base no puede salir de otra, y lo que ya tiene hijas no se puede volver hija; dos vueltas harían imposible entender un precio. La base inactiva sigue dando precios: inactiva quiere decir que no se vende ella. Borrarla no se deja, y por eso la llave no restringe: borrar una propiedad se lleva todas sus tarifas de una vez. Importar precios no acepta las que salen de otra.

### D-120 · 2026-11-05 · Condiciones por tarifa y no reembolsable
La política que vale es la del canal, luego la de la tarifa (o la de su base) y luego la general: lo que vendió un portal se rige por lo que dijo el portal. **La política propia de una tarifa también decide el anticipo del motor,** su depósito, aunque sea cero: si no, una «no reembolsable» se reservaría con el anticipo general y una «paga al llegar» no podría existir. `no_reembolsable` es una casilla y no 365 días de plazo, porque con un plazo una reserva lejana seguiría siendo gratis de cancelar.

### D-121 · 2026-11-05 · Cuándo se cobra un cargo
Noches de la semana, cada cuántas noches, fechas, noches mínimas, tarifas y unidades. Son columnas en el cargo y no un lenguaje de reglas: HotelDruid tenía muchas más combinaciones, como días alternos, incompatibilidades o «si faltan menos de tantos días», pero la mayoría se resuelven con estas. **Las reglas deciden lo que se suma solo y lo que se ofrece en el motor; en el panel, lo que alguien añade a mano se cobra igual, por toda la estancia:** un caso especial lo decide quien atiende. La noche cuenta por el día en que duermen, no por el de la mañana siguiente. Las noches se cuentan primero por día y fechas, y luego se divide entre «cada cuántas»: 7 noches cada 3 son 2 veces, no 3, porque el cargo se cobra cuando ya pasaron esas noches. Un porcentaje con fechas cae sobre todo el alojamiento si alguna noche entra: repartirlo por noche sería otra regla que nadie pidió.

### D-122 · 2026-11-05 · Campos propios en su tabla
Texto, o sí o no, de un huésped o de una reserva. Los valores van en `valores_campo_propio` y no en la fila: la sincronización reescribe huéspedes y reservas de una subordinada, y `campos_huesped` son los de la principal y se borran al sincronizar. Por eso hay una ventana en la ficha, para lo que viene de la principal y no tiene formulario. La clave nace del nombre y no cambia, porque ya puede estar escrita en un documento. Pueden ser datos personales: salen en la exportación, se borran al anonimizar, también los de sus reservas, y pasan con la fusión sin pisar lo que ya tenga. **No se piden en el registro de llegada del huésped:** cada campo es de quien atiende, y ese formulario es por país.

### D-123 · 2026-11-05 · Documentos sin lenguaje de plantillas
Se escriben con el editor del panel, sin repeticiones `[r]` ni condiciones `[c]` como en HotelDruid, porque casi nadie las usaba bien. **Solo variables entre llaves, y lo que usa datos de una lista se repite:** la fila, el punto de lista o el párrafo. Una fila con datos de un huésped sale por cada huésped; en uno de varias reservas, lo que usa datos de una reserva sale por cada una, y dentro de eso, por huésped. Nada se ejecuta: el texto no pasa por Blade, y sale por `Str::sanitizeHtml` antes de llenarse; los datos se escapan al ponerlos, y dompdf va sin PHP, sin JavaScript y sin nada remoto. Solo salen en PDF: en la pantalla, un `gerente` podría dejarle código a otro. Sin permiso de huéspedes, el número de identificación sale con los últimos cuatro dígitos. El de varias reservas respeta los límites de cada usuario y a los omitidos de los informes, y se niega con más de 500 reservas: no caben en la memoria de un hosting compartido. El contrato de partida es de muestra: nadie lo ha revisado.

### D-124 · 2026-11-05 · Cambiar fechas arrastrando un extremo
Se suelta en la nueva llegada o en la última noche, porque eso es lo que se ve en la barra. **El total no cambia solo,** igual que al editar: un precio acordado no se pisa. Si con la tarifa fuera otro, el aviso lo dice y un botón lo aplica, recalculando también los cargos; el cálculo es el de `GestorReservas::aplicarTarifa`, en el servidor, y no el número que mande el navegador. Lo de un grupo no se arrastra: su precio depende de todas sus habitaciones. A quien ya llegó solo se le mueve la salida, y a quien ya salió, nada.

### D-125 · 2026-11-05 · Camas extra
`capacidad` sigue siendo lo normal, y `camas_extra` lo que cabe de más: el tope es la suma. El precio de un grupo por capacidad sigue con la normal. Para cobrarlas hay dos caminos, y no se mezclan solos: la persona extra de la tarifa, que ya existía, o un cargo «por cama extra», que se multiplica por las camas que se usan. Si se usan los dos se cobra dos veces, y por eso la ayuda de la unidad lo dice.

### D-126 · 2026-11-05 · Calendario público
Solo dice si hay lugar o no, sin cuántos: es lo que hace falta para elegir fechas, y lo demás le serviría a la competencia. Aun así deja ver la ocupación, y por eso llega apagado y se prende en los ajustes del motor. Va de este mes hasta donde se puede reservar. Sin JavaScript, como el resto del motor, y un día con lugar es un enlace a la búsqueda de una noche. No mira restricciones de llegada ni estancia mínima: esas las dice la búsqueda con sus fechas.

### D-127 · 2026-11-05 · Importar de un archivo
Solo CSV, sin leer Excel: una librería para eso pesa mucho en un hosting compartido, y guardar como CSV es un paso. Las columnas son las de la plantilla, en su orden: acomodarlas la primera vez es más sencillo de explicar que una pantalla para emparejarlas, y cada sistema exporta distinto. **Se revisa antes y se vuelve a revisar al importar,** porque lo que viene del navegador no se da por bueno, y la vista previa muestra las fechas y los importes como se entendieron: ahí se ve un formato mal leído. Cada línea es aparte: una que falla no detiene a las demás, y solo ella se queda en el cuadro, para corregirla sin repetir lo que ya entró. **Va con `GestorReservas(registrar: false)`,** sin eventos: de ahí salen los mensajes de «reserva creada», y nadie espera un correo de una reserva de hace meses. Por lo mismo no suma cargos obligatorios: el total es el que dice el archivo. Los mensajes por fecha sí corren: una reserva futura recibe los de antes de su llegada, y una pasada no recibe nada, porque su fecha ya pasó. Con SES.Hospedajes conectado, lo vigente se comunica como cualquier otra. Antes hace una copia de seguridad, porque no hay deshacer. Las canceladas se omiten sin revisarlas: ocuparían noches al crearse, no aportan, y su unidad puede ya no existir. Lo pagado entra con la fecha de llegada, o la de hoy si aún no llega: un pago con fecha futura no cuadraría en ningún informe. Es «Ajustes» y no «Reservas» quien puede, porque se lleva mucho por delante de una vez; en la demostración pública no está.

**El texto no es una propiedad de Livewire.** Livewire no acepta más de 1 MB por petición (`payload.max_size`) y cuenta su snapshot: en una propiedad el texto viajaría dos veces, y al pasarse da un error que no dice nada. Se queda en el navegador y va una vez, como argumento. Por eso el límite es de 500 KB y 1000 filas, que sobra. Importar precios sí lleva el texto y sus filas en cada petición: bajó a 150 KB.

**Excel.** Las plantillas van en UTF-16 con tabuladores (`Csv::paraExcel`): con comas, donde la lista se separa con «;» todo saldría en una columna. Y al guardar, Excel escribe las fechas como en su país, así que `Pais::fechaConMesPrimero` dice cómo leer 03/04/2027 según la propiedad. Como fecha ambigua no se puede detectar, por eso se muestra lo entendido.
### D-128 · 2026-11-05 · Lo que se exporta se abre en columnas en cualquier Excel
La 1.0.29 arregló las plantillas de importar, pero lo demás seguía dependiendo del país: con comas todo salía en una columna en España, y con punto y coma, en México. Lo que es para abrir en Excel pasa a UTF-16 con tabuladores (`Csv::descargaParaExcel`), que no depende de eso. Se quedan como están el CSV estándar de facturas, el libro de facturas (D-103) y la declaración de estancias: los lee otro programa, y llevan el formato que él pide. Por lo mismo sale la opción «Excel en español» de exportar facturas, que además obligaba a saber cómo tiene configurado su Excel quien la elige.

**Decimales.** Excel lee los números con el separador de su Windows, que desde aquí no se sabe. Lo más cercano es `Dinero::separadorDecimal`, el del idioma y la moneda: el mismo con que la pantalla enseña ese dinero. Solo se aplica a lo que se suma (`Csv::numero`); los informes y el estado de cuenta de una empresa ya traían texto con su moneda. Si no coincide, 1000.50 queda como texto en un Excel de coma decimal, y 1000,50 puede leerse como 100050 en uno de punto decimal. Se acepta y se avisa en `CAMBIOS.md`: quien tenga ese caso cuenta con el CSV estándar en facturas, y pedir el separador en cada descarga estorbaría a todos los demás.

UTF-16 ocupa el doble, y Livewire arma la descarga completa en memoria. Con lo que maneja un alojamiento pequeño no importa; una auditoría enorme se puede pedir por tramos de fechas.

### D-129 · 2026-11-05 · Los documentos no nombran el servidor de prueba
El repositorio es público: lo que dice dónde está la instalación de prueba y con qué hosting se cambió por «el servidor de prueba» o «un hosting real». Sigue en el historial y en los paquetes ya publicados, y no hay usuarios, contraseñas ni nombres de base. La guía del instalador sí nombra hostings, porque es para quien use cada uno, y no dice cuál usa este proyecto.

### D-130 · 2026-11-05 · El sitio alojaflow.com y su wiki
Sitio estático con Astro, y buscador con Pagefind, que corre en el navegador y no manda a nadie lo que se escribe. Va en español y en inglés, y vive fuera de este repositorio: no se empaqueta con el sistema, y su código de capturas no tiene por qué ser público. La portada ofrece la descarga gratis y servicios, sin precios, porque siguen sin decidirse. Dice que es una versión de prueba y qué falta verificar, con la misma lista de `CAMBIOS.md`: una promesa fiscal sin probar le costaría más a un alojamiento que a nosotros. No hay demo en vivo: las capturas son reales, con datos ficticios, de una instalación aislada. Google Analytics queda preparado y apagado hasta tener su ID, y solo se carga con consentimiento.

### D-131 · 2026-11-05 · Por ahora no se factura: de un pago sale un recibo
El usuario decidió no meterse en facturación en ningún país: «solo un recibo normal». Se quita todo lo de facturas, sin apagarlo nada más, porque lo no verificado estorba más de lo que ayuda: el timbrado nunca se probó de verdad, y prometerlo era el pendiente más delicado. Se van `App\Facturacion`, las pantallas de Facturas y Datos fiscales, el módulo, el botón Facturar, la factura por cuota, el libro para España, el campo RFC del registro de llegada en México, y las tablas. Sustituye a D-049, D-098, D-103 y D-109, y a la parte de facturas de D-081.

**El recibo ya existía** (`GeneradorPdf::recibo`, uno por pago) y ahora lleva su número. Sigue diciendo que no es un comprobante fiscal: en un país donde hay que facturar, que no parezca que sustituye a la factura. Los identificadores fiscales de huésped, empresa y propietario se quedan: son datos que vienen de la principal o que el alojamiento anota, y no facturan nada.

**Lo emitido se borra al actualizar.** La migración se lleva las filas, y los XML y PDF del disco. Dejarlas sin pantallas ni forma de borrar habría sido peor, con datos personales que nadie ve, y no hay facturas de verdad: esta es una versión de prueba y nunca se timbró ninguna. Queda la copia previa de la base que hace la actualización. `down()` devuelve las tablas vacías, y el código está en la etiqueta v1.0.30 por si se retoma.

**El permiso `facturacion.gestionar` pasa a `cuentas.gestionar`,** con el mismo id: quien lo tenía conserva los estados de cuenta y el calendario de cobros.

### D-132 · 2026-11-05 · alojaflow.com: Tag Manager con permiso, y nada que se mueva con el scroll
El usuario dio su contenedor de Google Tag Manager. Se carga desde el archivo de scripts del sitio y solo si quien visita acepta el aviso: el sitio ya prometía que sin consentimiento no se carga nada de Google, y su política de seguridad no admite scripts en línea. Con él se abre esa política a Tag Manager y Analytics y se dejan de exigir los Trusted Types, porque `gtm.js` escribe directo en el DOM.

El usuario sentía que la portada se trababa al bajar. Con la tarjeta de video de este equipo no se perdían cuadros, así que se quitó lo que podía sentirse así: lo que se movía a otra velocidad que el scroll, los textos fijos, el desenfoque de la cabecera y la ola que se volvía a pintar. Si una animación depende del scroll, cada golpe de rueda se ve como un tirón, aunque vaya a 60 cuadros.

### D-133 · 2026-11-05 · La auditoría y la demo en cada idioma
Al sacar las capturas en inglés de la wiki se vio que dos pantallas seguían en español con el sistema en inglés. Regla 7.

**Auditoría:** se siguen guardando códigos (`reserva.crear`, `Reserva`, nombres de columna), y se traducen al leerlos (`EtiquetasAuditoria`). Así el registro no depende del idioma de quien hizo el cambio, y sirve para quien lo revisa después en otro. Una acción sin nombre propio se lee por su verbo, porque al lado ya dice sobre qué fue. Del detalle se traducen las claves que el programa escribe y las columnas más comunes; hay 280 columnas auditables, y traducirlas todas a seis idiomas para una pantalla técnica no vale la pena: las demás salen con su nombre, sin guiones bajos.

**Demo:** nace en el idioma de quien la crea, también sus plantillas y documentos. Sigue siendo un hotel en México con sus impuestos, porque es lo que está probado. El español no cambia.

### D-134 · 2026-11-05 · alojaflow.com de noche, con vectores y animaciones
El usuario pidió la portada y el wiki «más modernos, con efectos, vectores y animaciones», oscuros por omisión o con modo noche, porque el sitio se veía seco. Solo el sitio: el sistema se queda como está.

**Oscuro por omisión**, sin mirar la preferencia del sistema del visitante, con un interruptor que recuerda lo elegido. El modo claro es el mismo pueblo de día. Se rehízo el sitio sobre tokens de tema (`DESIGN.md` del sitio).

**El mundo es la recepción de noche:** un pueblo vectorial cuyas ventanas se encienden, con el techo en A y la ola del logo, y un tablero de fichas que voltean. Lo que se mueve demuestra algo del producto: entra una reserva, se avisa de un choque, se rechaza la segunda venta de una noche.

**D-132 sigue en pie:** nada ligado a la posición del scroll. Las animaciones van por tiempo o se disparan una vez, solo con `transform` y `opacity`, por capas enteras, y se pausan fuera de la vista. Sin JavaScript todo se lee, y con el movimiento reducido todo está quieto. Falta probarlo en un teléfono de verdad.

---

### D-135 · 2026-11-06 · Un error propio no se le achaca a la principal

SUB-099 servía para dos cosas: lo que la principal respondió raro y lo que falló de este lado. Una falla al procesar la copia decía «la principal dio una respuesta que AlojaFlow no reconoce». Ahora es SUB-098. Los textos de la principal se comparan sin acentos: HotelDruid 3.0.8 escribe «lìmite».

### D-136 · 2026-11-06 · Un archivo de JavaScript propio, sin compilar

Los pares de fechas y el orden de las tablas necesitan responder sin ir al servidor: una ida y vuelta llega tarde si alguien ya está abriendo el segundo calendario. Va en `resources/js/alojaflow.js`, sin Livewire ni Alpine y sin paso de compilación, como la hoja de estilos. Es una mejora: sin él todo funciona y el servidor valida igual.

### D-137 · 2026-11-06 · Toda columna de la base se ordena sola

Pedir `->sortable()` columna por columna hacía que faltara en la mayoría. `OrdenAutomatico` lo decide con el esquema: columna de la tabla o de una relación de la que depende la fila, y no cifrada. Una prueba ordena por cada columna de cada lista, para que una columna nueva no truene.

### D-138 · 2026-11-06 · El formato del aviso se guarda por propiedad

Es un parte de un equipo: que cada quien lo saque distinto confunde. Va en `Ajustes`, sin tabla nueva. Los iconos son emojis porque el aviso es texto para WhatsApp. El orden de día y mes lo da el idioma (regla 7): `06/11/26` en México y `11/06/26` en Estados Unidos.

### D-139 · 2026-11-06 · La lista de variables es la que vale

`CatalogoVariables` es la lista que se ofrece al escribir, con lo que hace cada una, y una prueba comprueba que todas se sustituyen de verdad. Se prefirió a las *merge tags* de Filament: dejan otra marca en el texto y habrían obligado a cambiar los documentos ya escritos, y no explican cada variable.

### D-140 · 2026-11-06 · La actualización en línea avisa y baja; lo demás ya existía

Subir un zip de 30 MB era el paso más pesado para quien no es técnico, y comprobar la firma, hacer copia y volver atrás ya estaba hecho. Se añaden solo dos piezas: una consulta diaria a GitHub Releases y una descarga por pedazos de 8 MB. De ahí se sigue por el mismo camino, con su revisión y la contraseña (`instalacion-actualizaciones.md` §3.1.1).

- **La firma da la seguridad, no el sitio.** Por eso lo que responde GitHub solo sirve para avisar: ni la versión ni las notas deciden qué se instala.
- **No se manda ningún dato.** Es un `GET` sin parámetros. Se usa `releases/latest` de la API pública de GitHub en lugar de un servidor propio: no hay que mantenerlo, y no hay manera de contar instalaciones. El aviso se apaga con una casilla.
- **La excepción al «solo desde el host de `url_paquete`»:** GitHub redirige los archivos de sus versiones a `release-assets.githubusercontent.com`, así que sin ella no bajaría nada. Vale solo si el paquete viene de `github.com`.
- **La firma se comprueba antes de llegar a `entrada/`,** además de al revisar. Un zip dañado o una versión vieja con firma buena se borran ahí, y otro intento empieza de cero: si no, pulsar otra vez encontraría el mismo zip malo.
- **El servidor decide qué versión baja:** la petición no lleva parámetros, para que nadie pueda pedirle otra.
- **Nunca se instala sola.** Primero un clic; más adelante, quizá una opción para las de seguridad.
- **Subir a mano se queda,** para el servidor que no sale a internet.
- **Límite conocido:** las notas de la publicación salen en el idioma en que se escribieron (español), no en el de quien las lee; igual que las de `CAMBIOS.md`.

### D-141 · 2026-11-06 · En el teléfono, el calendario abre por día

Las barras exigen precisión y arrastrar choca con desplazar, así que el teléfono abre una vista de día, unidad por unidad. La computadora sigue abriendo por habitaciones, y «Día» y «Mes» se pueden elegir en cualquier pantalla. Mientras nadie elija lo decide el ancho, con CSS: se ve bien antes de que arranque Alpine, y el teléfono carga el mes solo la primera vez. Se prefirió a saber el teléfono por el navegador (*user agent*), que falla, y a pedir la vista con JavaScript, que dibuja dos veces. No se añadió cambiar reservas de día desde esa vista: se hace desde su ficha.

### D-142 · 2026-11-06 · Un error del motor abre la página en el formulario, sin JavaScript

El formulario del motor se manda a su propia ancla (`#datos`). Con un error, el navegador abre la respuesta ahí, con un resumen que lleva a cada campo, y cada campo dice qué hacer. **Por qué:** el motor no depende de JavaScript ni de la sesión (D-042), y en el teléfono los errores quedaban una pantalla arriba de los campos. El motor pide además siete dígitos en el teléfono; `GestorReservas` no cambia.

### D-143 · 2026-11-07 · El calendario abre por habitaciones, también en el teléfono

Sustituye a D-141 en lo que abre primero. El usuario reportó que «el calendario ya no muestra el calendario». En pantalla ancha sí salía; en una ventana angosta o un teléfono abría la lista del día, y además escribía `?vista=dia` en la dirección, que se quedaba. Ahora abre siempre el mes; «Día» sigue a un toque, y lo recuerda ese navegador. La vista ya no va en la dirección. **No se pudo comprobar** que esa fuera la causa en su instalación: si sigue fallando, hace falta saber en cuál y con qué ancho.

### D-144 · 2026-11-07 · Omitir en todo o por secciones

Se añaden «En todo» (lo nuevo llega con eso) y las secciones Hoy, Calendario y Limpieza. Hoy incluye el Resumen, el cierre del día, y depósitos y no-shows. Las omisiones que ya estaban en el aviso pasan también a Hoy: es lo que el usuario esperaba. **Sigue sin borrarse nada:** en el calendario la barra dice «Ocupada», sin nombre ni enlace, porque si desapareciera se vendería dos veces (D-074). **También se quedan:** las listas de Reservas y Huéspedes, para poder encontrarlo y deshacerlo; cobros y caja, porque ese dinero es real; y el registro de viajeros ante la autoridad, que es legal. Se omite solo al titular.

### D-145 · 2026-11-07 · Ficha del huésped por año y mes, plegada

Años y meses plegados de entrada, y cada uno dice cuántas reservas, noches y dinero lleva. Con `details` del navegador, sin JavaScript. El bloque de formato del aviso también va plegado y al final de la pantalla, porque casi nunca se cambia. `.af-plegable` lleva su triángulo: con `flex` el navegador lo quitaba y no se notaba que se abre.

### D-146 · 2026-11-07 · Grupo a partir de reservas ya creadas

Se busca al cliente y se eligen sus reservas vigentes sin grupo, que no hayan salido. **Fechas, unidad y precio no se tocan:** ya se vendieron así, y el cliente queda como coordinador. Solo reservas de un mismo titular, como se pidió, y solo propias: las de una subordinada las manda la principal.

### D-147 · 2026-11-07 · Plantillas y documentos ya vienen creados

Se amplían a 11 mensajes y 5 documentos, a partir de lo habitual en PMS como HotelDruid. Una propiedad nueva los recibe al crearse. Una existente que ya los usaba recibe al actualizar solo los nuevos, para no regresar lo que renombró o borró. **Ningún mensaje sale solo:** crear la plantilla no crea un envío. «Activar envíos recomendados» prende tres (confirmación, recordatorio y gracias), solo si alguien lo pide y si en ese momento no sale ya otro mensaje: serían correos dobles.

### D-148 · 2026-11-07 · Calendario de tarifas

Una fila por tarifa y una columna por día, agrupadas por propiedad, como las tablas de precios de los PMS. Calcula con el mismo `Cotizador` que cobra, para que no pueda decir un precio distinto. Pulsar un día pone precio a un rango: es una temporada, y **las que se enciman se recortan**, porque el `Cotizador` da prioridad a la que empieza más tarde y el día podría no decir lo último que se escribió. Lo vendido no cambia. A una tarifa derivada no se le pone precio: sale de otra. Solo independientes.

### D-149 · 2026-11-07 · Cargos de los canales y utilidad de la reserva

Cada canal lleva los cargos que quiera (porcentaje o fijo; sobre tarifa base, total sin impuestos, total final o sobre la comisión) y su comisión dice sobre qué se calcula. **Utilidad bruta:** lo que paga el huésped, menos impuestos cobrados por cuenta de otro, comisión y cargos. **Neta:** la bruta menos retenciones, que van aparte porque no son un gasto sino impuesto adelantado. Es criterio nuestro, sin revisar por un contador. Los gastos del alojamiento no entran: no son de una reserva.

Se calcula con los cargos de hoy, sin foto en cada reserva: cambiar un cargo cambia también las cifras de las pasadas. La comisión es la guardada en la reserva y, si está en cero, la del canal: una reserva anterior a configurarlo también la paga. Los impuestos son los guardados al vender. Un canal se liga a la reserva por el nombre de su origen, como ya hacían los impuestos. Canales pasa a valer también en subordinadas: son datos propios.

**Sugerencias, con lo que se pudo comprobar (2026-11-07, casi todo fuentes secundarias):** Booking, comisión de 15 % (10 a 25 %) sobre el total con limpieza, y cargo de pago de 1.1 a 3.1 % solo con Payments by Booking.com (llega apagado). Airbnb, tarifa única de 15.5 %, 16 % en México según una fuente; las fechas de la transición no coinciden entre fuentes. Expedia: no hay cifra pública, va por contrato, y se deja vacía. México: 4 % de ISR y 8 % de IVA con RFC, 20 % y 16 % sin él (LISR 113-A, LIVA 18-J), para persona física; no se leyó el texto oficial vigente, y el IVA de 16 % sobre la comisión es un supuesto a confirmar con la factura del portal. Lo de cada país va en `Pais::cargosDeCanales`.

### D-150 · 2026-11-08 · Mejora continua: datos de uso con permiso, y reportes con imágenes

Una instalación puede mandar al equipo de AlojaFlow, una vez al día, datos de diagnóstico y de uso, y
quien la usa puede reportar errores o sugerir cambios con hasta diez imágenes. Lo recibe
`dev.alojaflow.com`, un sistema aparte del equipo. **Decidido con el usuario:** se pide permiso con una casilla al instalar (marcada,
explica qué se manda, se puede desmarcar); quien actualiza decide en un aviso del panel y, mientras no
decide, no sale nada. No es obligatorio. Las reservas van en número exacto, por origen, y se cuentan las
visitas a cada pantalla. No hay formulario público: los reportes salen solo de las instalaciones.

**Nunca** sale nada de huéspedes, importes ni contraseñas; los errores se sanean aquí y otra vez allá, y
de una consulta va el SQL con `?`. La instalación es anónima (un UUID al azar) salvo que elija
identificarse, y «Olvidar mi instalación» borra todo allá. Los reportes no dependen del permiso: los manda
quien los escribe, y sus imágenes se borran de aquí al llegar. Todo va en segundo plano y, si el
receptor no responde, se reintenta cada hora sin afectar al alojamiento. Detalle en
`docs/especificacion/mejora-continua.md`.

### D-151 · 2026-11-08 · Actualizaciones automáticas a las 3:00 y urgentes desde dev.alojaflow.com

Pedido del usuario: cada instalación puede prender las **actualizaciones automáticas**, que instalan una
versión nueva a las 3:00 de la instalación, y el equipo puede ordenar una **actualización urgente** a todas
cuando corrige una vulnerabilidad. Las automáticas llegan apagadas; las urgentes, prendidas, y se pueden
apagar en *Sistema → Actualizaciones*. **Por qué es seguro:** la orden solo dice «pasen a la versión X»;
cada instalación baja ese zip de GitHub y lo instala solo si está firmado con la clave de AlojaFlow y es
más nuevo que el suyo, así que quien tomara dev.alojaflow.com no podría instalar nada propio ni regresar a
una versión vieja. Consultar la orden no manda ningún dato. **Límite:** sin cron no se instala nada solo
(tarda más que una visita); se avisa en el panel. La zona horaria es la elegida o la de la primera
propiedad, porque la instalación corre en UTC. Detalle en `instalacion-actualizaciones.md` §3.4.

Junto con esto, en «Reportar o sugerir» (D-150) solo se aceptan JPG y PNG, revisados por su contenido y
vueltos a codificar antes de guardarse; hay un tipo «informe»; cada persona manda hasta diez por hora, y
el apartado *Mejora continua* lo ve todo el equipo (los ajustes, solo el administrador).

### D-152 · 2026-11-08 · Copias fuera del servidor, cifradas

Pedido del usuario. Además de la copia en el propio servidor, cada copia programada se sube a un
almacenamiento compatible con S3 (DreamObjects, Backblaze B2, Wasabi, Cloudflare R2) o a WebDAV, **cifrada
antes de salir** con una clave que escribe el administrador (Argon2id + libsodium *secretstream*, formato
`AFCOPIA1`): quien tenga acceso al almacenamiento no ve nada. Se guardan siete diarias (por día de la
semana) y doce mensuales. La firma de S3 es propia (SigV4, sin SDK) y toda petición pasa por
`ClienteExterno`. Antes de guardar los datos se sube un archivo de prueba. **La clave no se puede
recuperar:** sin ella, las copias de fuera no sirven; el panel lo dice. Para restaurar, *Copias de
seguridad* acepta el `.afcopia` con su clave, y `alojaflow:descifrar-copia` lo hace en la consola. El latido
de la mejora continua dice solo si la última subida salió bien.

### D-153 · 2026-11-08 · Llegadas y salidas también en las subordinadas

En una subordinada la reserva es de la principal y AlojaFlow nunca escribe en ella, por eso *Hoy* no dejaba
marcar quién llegó. Ahora se anota una **marca propia** (`marcas_estancia`), como el no-show (D-074), y vale
igual que la de la principal: si la principal ya registró la llegada, aquí solo se anota la salida. Al pasar
a independiente, las marcas pasan a la reserva. *Hoy* dice «Ya llegaron 3 de 5» y «Ya salieron 2 de 4», y
cada reserva muestra la hora en la de la propiedad.

### D-154 · 2026-11-08 · Calendario: colores por pago, situación, fijar y elegir dos fechas

Pedido del usuario, como en la tabla de HotelDruid:

- **Colorear por pago:** pagada por completo, anticipo pagado (falta el resto), sin anticipo y sin
  confirmar. Lo ve solo quien tiene permiso de datos de la reserva; el detalle de cada barra dice cuánto
  se ha pagado. Los colores de cada estado, por estado y por pago, se cambian en el perfil.
- **«Situación a las 8:05 del 01/11/2026»**, y en una subordinada, de cuándo es la copia de la principal.
- **Fijar** la columna de las unidades y la fila de los días (fijas de entrada).
- **Elegir llegada y última noche con dos clics**, solo hasta el primer día ocupado; dos veces el mismo día
  es una noche y Esc lo deja.

Las opciones las recuerda el navegador y van en `<html>`, que Livewire no vuelve a pintar; sin
`alojaflow.js`, cada día libre abre una reserva de una noche, como antes.

### D-155 · 2026-11-08 · Unidades vinculadas

Una unidad puede **componerse de otras** (la casa completa y sus cuartos): ocupar una cierra las otras, y
dos que comparten un cuarto también chocan. Un solo nivel. Al guardar una reserva se bloquean las unidades
vinculadas siempre en el mismo orden y se revisan sus noches con una lectura con candado: dos reservas que
llegan a la vez no se cuelan. La búsqueda, el motor, el calendario público, el iCal que va a los portales y el
calendario por habitaciones lo toman en cuenta, y lo que ya chocaba cuando se vincularon sale en *Choques*.
Los indicadores siguen contando cada unidad por separado.

### D-156 · 2026-11-08 · Códigos de acceso por reserva

El código de la cerradura o de la caja de llaves: uno por reserva (escrito o generado al azar) o uno fijo
por unidad. Va **cifrado**, la auditoría dice que cambió pero no cuál es, y es un dato propio, así que vale
también en subordinadas. El huésped lo ve en *Mi reserva* desde el día antes de llegar hasta que sale, y va
en los mensajes con `{codigo_acceso}`.

### D-157 · 2026-11-08 · Segunda moneda con tipo de cambio a mano

Cada propiedad puede tener una segunda moneda (USD en la costa) y su tipo de cambio, que escribe el
alojamiento. Todo se cobra y se suma en la moneda de la propiedad; la segunda se **muestra** junto a los
precios (motor, *Mi reserva*, ficha de la reserva) y se puede **recibir efectivo** en ella: el pago guarda
lo recibido y el tipo de ese día, el recibo lo dice, y en la caja ese efectivo se cuenta aparte, no en el
esperado en pesos.

### D-158 · 2026-11-08 · Varias unidades en una reserva del motor

El huésped puede pedir hasta N unidades (3 de entrada; 1 lo apaga) con un solo pago. Se crea **una reserva
por unidad**, como siempre, con las personas repartidas lo más parejo posible, y las demás apuntan a la
primera (`reserva_principal_id`), que cobra lo pendiente de todas; al confirmarse el pago se reparte entre
ellas. Todo en una transacción: si no hay lugar para todas, no se aparta ninguna. Sin cupón, sin extras y
sin elegir lugar en el plano: eso es de una sola unidad.

### D-159 · 2026-11-08 · PayPal en el motor

Con su API de órdenes (Orders v2) y sin SDK: la orden lleva la referencia firmada del cobro, el huésped la
aprueba en PayPal y se **captura al volver** o, si cerró la ventana, con el aviso de que la aprobó. El
resultado siempre sale de la API de PayPal, no de la dirección; si se da el identificador del webhook, la
firma de cada aviso se comprueba con su propia API. Comprobado solo con respuestas simuladas, como las
demás pasarelas: falta un cobro de prueba con una cuenta Sandbox.

### D-160 · 2026-11-08 · WhatsApp por la API de Meta

Meta solo deja que el negocio escriba primero con **plantillas aprobadas** en WhatsApp Manager. Una
automatización nueva, «Plantilla de WhatsApp al huésped», manda una plantilla con las variables en su orden
(`{{1}}`, `{{2}}`…) y el idioma de la plantilla; para otro idioma, otra automatización con la condición nueva
«solo si el huésped habla…», que vale también para los correos. Cada propiedad pone el identificador de su
número y un token permanente, que se guarda cifrado; sin teléfono, sin plantilla o sin configurar, el envío
queda omitido con la razón. Las respuestas de los huéspedes siguen en el WhatsApp del alojamiento.

### D-161 · 2026-11-08 · Más errores para la mejora continua

Pedido del usuario: registrar todos los errores o problemas que se puedan. Con el permiso de D-150, también
se anotan los **errores de JavaScript** del panel (como mucho cinco por página, sin repetir y sin nada de lo
escrito) y los **avisos del registro** de nivel *warning* o más que no traen excepción. Van saneados como
los demás.

### D-162 · 2026-11-08 · Modo instancia, para el servicio alojado

El servicio alojado (AlojaFlow App, un programa aparte que manda órdenes por la consola) corre **una instancia por
cliente sobre un código compartido**: cada cliente tiene su base, su usuario de MySQL, su `.env`, su `storage` y
sus cachés de arranque en una carpeta, y el código de cada versión existe una sola vez. AlojaFlow arranca como
instancia cuando `ALOJAFLOW_INSTANCIA` apunta a esa carpeta (`App\Instalacion\Instancia`); sin la variable, una
instalación normal no cambia.

- **No arranca** si la carpeta no es de una instancia o si el entorno trae otra `APP_KEY` u otra base: Laravel no
  reemplaza una variable que ya está en el entorno, y la instancia usaría los datos de otro. Sin `putenv`, el
  `.env` de la instancia no pasa a los procesos que lanza el programador.
- **Sin actualizaciones propias:** el código lo comparten todas, así que el panel, la consola y las tareas de
  actualizar se apagan; las hace AlojaFlow App. Las tareas «por visita» tampoco corren: el programador lo lanza el
  agente de su servidor. `filament:optimize` no se corre en una instancia: escribiría en el código de todas.
- `alojaflow:instalar` instala sin navegador (tablas, datos base y super administrador con una contraseña al azar)
  y `alojaflow:enlace-entrada` da el enlace de un solo uso de D-071. Al entrar así la primera vez, AlojaFlow pide
  crear la contraseña (D-164).

Por qué así y no una base compartida con un identificador de cliente: lo pide la regla de una base por cliente;
un olvido en una consulta no puede mostrar los datos de otro; exportar, restaurar o borrar a un cliente es una
base y una carpeta; y es el mismo AlojaFlow que se instala en un hosting.

### D-163 · 2026-11-09 · En el servicio alojado, cada cliente en una ruta del mismo dominio

El usuario decidió que cada cliente del servicio alojado viva en `app.alojaflow.com/<cliente>` (se estudiaron
también los subdominios, que separan mejor a los clientes en el navegador). AlojaFlow corre ahí como en una
carpeta del hosting: el servidor web le dice su ruta con `SCRIPT_NAME`, y `APP_URL` la lleva.

- **Todas las direcciones con la ruta.** AlojaFlow ya las armaba así (con `route()`, `url()` y `APP_URL`);
  `RutaBaseTest` comprueba que ninguna salga sin ella. Nada de direcciones fijas desde la raíz del dominio.
- **Galletas por ruta.** Las pone el `.env` de la instancia (`SESSION_PATH=/<cliente>`); la sesión,
  `XSRF-TOKEN` y «recordarme» la respetan sin cambios.
- **Lo que se guarda en el navegador lleva la ruta:** `alojaFlowLlave('calendario.vista')` da
  `alojaflow/<cliente>.calendario.vista`. Sin ruta, la llave de siempre (`alojaflow.calendario.vista`).
- **Nada de lo que escribe un alojamiento se ejecuta en el navegador.** Todos los clientes comparten el origen,
  y la política de contenido permite scripts en línea (Livewire y Alpine): un XSS en la página de uno
  alcanzaría a quien tenga sesión en otro. Revisado: Blade escapa todo; lo que va sin escapar es código propio
  (colores validados, JSON-LD con `JSON_HEX_TAG`, QR) o HTML limpiado con `Str::sanitizeHtml` (documentos
  propios); no hay HTML libre ni «código de seguimiento»; un archivo subido solo se abre en el navegador si es
  JPG, PNG, WebP o PDF. Queda como regla para lo que se agregue.

### D-164 · 2026-11-09 · Crear la contraseña al primer ingreso

`alojaflow:instalar` (D-162) deja al dueño con una contraseña al azar que nadie conoce, y el perfil pide la actual
para cambiarla. Ahora el usuario queda marcado (`users.debe_crear_contrasena`) y, al entrar con el enlace de un solo
uso, AlojaFlow lo lleva a **«Crea tu contraseña»**: la escribe dos veces, sin la actual, con las mismas reglas que el
perfil (12 caracteres o más). Mientras no la crea, cualquier pantalla del panel lo regresa ahí (`ContrasenaPorCrear`);
salir y la verificación en dos pasos que exija el panel siguen disponibles. Queda en la auditoría.

- La marca solo la pone `alojaflow:instalar`. El instalador web no la pone: ahí la contraseña ya se escribió.
- Cambiar la contraseña por cualquier otro camino (recuperarla por correo) quita la marca.
- Si el dueño cierra sin crearla, AlojaFlow App le pide otro enlace con `alojaflow:enlace-entrada` y se le vuelve a pedir.

### D-165 · 2026-11-09 · El correo de la plataforma en el servicio alojado

En una instancia (D-162), el correo del `.env` es el del proveedor de la plataforma (`avisos.alojaflow.com`) y
**cuenta como configurado**: los mensajes a huéspedes, los avisos, el código de la llegada y «¿Olvidaste tu
contraseña?» funcionan desde el primer día. Sale con el remitente de la plataforma —el proveedor no deja pasar
el de otro dominio— y con el nombre de la propiedad; las respuestas van al correo de la propiedad (Responder a).
Comunicación → Correo lo explica, y Estado del sistema y los primeros pasos lo dan por hecho. Con `MAIL_MAILER`
`log` o `array` la plataforma no tiene correo. En una instalación propia el `.env` sigue sin contar.

- El servidor SMTP del alojamiento sigue siendo opcional y, si lo pone, se usa ese con su remitente.
- **En una instancia, ese servidor debe estar en una red pública** (`ValidadorDestino::validarServidor`, como
  `ClienteExterno`): al guardarlo, y otra vez en cada envío, que se hace **a la IP ya revisada** con el
  certificado comprobado por el nombre. Así nadie usa el correo para tocar la red interna del servidor
  compartido, ni cambia el nombre a otra IP después de la revisión. En una instalación propia puede seguir en la
  red local.

### D-166 · 2026-11-09 · El soporte de AlojaFlow dentro de una instancia

Del diseño del servicio alojado (AFAPP, `seguridad.md` §4). **Por omisión nadie del equipo entra.** Solo en una
instancia:

- **El permiso lo da el cliente:** Sistema → Soporte de AlojaFlow (administradores) tiene «Permitir que el soporte
  de AlojaFlow entre durante 24 horas», extenderlo, retirarlo (saca en ese momento a quien esté dentro) y la lista
  de entradas de los últimos 90 días.
- **`alojaflow:acceso-soporte --nombre= --minutos= [--emergencia=] [--json]`**, que pide AlojaFlow App, crea o
  reactiva al usuario «Soporte AlojaFlow — nombre» y responde un **enlace de un solo uso que caduca en 5 minutos**,
  en su propio lugar (pedirlo no gasta el del dueño). Lo anota en la auditoría; todo lo que haga queda ahí a ese
  nombre. Con permiso, nunca dura más que el permiso; sin él, la instancia lo rechaza aunque lo pida la consola.
- **Emergencia:** con `--emergencia="motivo"` entra sin permiso, el motivo queda en la auditoría y los super
  administradores reciben un correo en ese momento (si no hay correo, solo la auditoría).
- **Al vencer** (`users.soporte_hasta`) ya no entra aunque la tarea no haya corrido (`User::puedeEntrar`), y la tarea
  de cada minuto lo desactiva (lo que cierra sus sesiones).
- **Qué puede hacer:** administrador, no super administrador (no cambia módulos ni nombra administradores). Ve al
  equipo pero **no crea ni cambia usuarios**: no debe quedarle un acceso propio al vencer el suyo. Tampoco da
  permiso al soporte.

### D-167 · 2026-11-09 · En una instancia no se enseña lo que administra AlojaFlow App

En el servicio alojado, el servidor, el `.env` y las tareas los lleva AlojaFlow App. Al cliente no se le enseña:

- **la descarga del `.env`** en Copias de seguridad (guarda los secretos de la plataforma, como el correo; la acción
  tampoco responde si se llama a mano);
- **el programador, el cron y el PHP de consola** en Estado del sistema, ni el paso «cron» de los primeros pasos;
- **el instalador:** revisado, el panel no lo ofrece en ningún lado (solo existe `instalar.php`, que se sube al
  hosting, y el `App\Instalacion\Instalador` que usa), y la portada de una instancia no lo sirve (AFAPP). Las
  actualizaciones ya estaban apagadas (D-162).

«Código fuente» y «Acerca de» se quedan: la AGPL pide que cada cliente pueda bajar el código exacto con que corre.

### D-168 · 2026-11-09 · La mejora continua de una instancia alojada

En el servicio alojado todas las instancias comparten `app.alojaflow.com`, así que `dev.alojaflow.com` no las
distingue por el dominio. Una instancia agrega **`alojada: {codigo}`** —el código del cliente
(`ALOJAFLOW_CODIGO_INSTANCIA`, que ya escribe AlojaFlow App, o el nombre de su carpeta)— al registro, al latido, a
los errores y a los reportes. Sin datos de huéspedes, como siempre; el latido lo enseña en «Ver lo que se envía».

**El permiso lo decide el contrato, no una casilla:** en una instancia se manda salvo que el `.env` diga
`ALOJAFLOW_MEJORA_CONTINUA=false`. Lo que dejó `alojaflow:instalar` en el ajuste no cuenta, no aparece el aviso para
decidir, y la pantalla lo explica sin interruptor ni «Olvidar mi instalación». En una instalación propia, todo sigue
como en D-150. La especificación está en `docs/especificacion/mejora-continua.md`.

## Pendientes

- Precios y modelo de venta.
- Titular del copyright en las cabeceras de licencia.
- Proveedor de correo y canal del OTP.
- Inventario, almacenes y punto de venta: fase 2 o posterior.
