# Cambios

Numeración: 1.0.1, 1.0.2 … 1.0.99, y después 1.2 (ver `docs/decisiones.md`, D-030).

## 1.0.6 — 2026-11-04

Correcciones de seguridad encontradas al revisar la 1.0.5. Actualiza si ya la instalaste.

- **Mercado Pago:** quien tuviera un pago aprobado podía aplicarlo a otra reserva abriendo una dirección
  preparada. Ahora el cobro lo dice el propio pago, y solo vale dentro de su propiedad.
- **Exportaciones CSV:** un nombre que empezara con «=» podía ejecutarse como fórmula al abrir el archivo en Excel.

## 1.0.5 — 2026-11-04

La versión más grande hasta ahora: AlojaFlow ya funciona como sistema completo de una propiedad independiente,
y añade limpieza, mensajes, iCal, llegada en línea, facturación e informes en cualquier propiedad.

**Lo que hay que saber antes de usarlo en real**
- **Stripe y Mercado Pago** están programados según su documentación, pero solo se probaron con respuestas
  simuladas: haz un cobro de prueba con llaves de prueba.
- **Las facturas CFDI 4.0 no están timbradas:** falta contratar un PAC. El PDF lo dice en grande.
- **Los textos legales son de partida:** revísalos con un abogado antes de publicarlos.

**Propiedades independientes**
- Tarifas por noche con persona extra o por persona, con fin de semana, temporadas y estancia mínima.
- Impuestos incluidos o que se suman, cargos adicionales, canales con comisión, cupones, cajas, gastos e ingresos.
- Grupos, acompañantes, notas internas, fusionar fichas de huéspedes y arrastrar reservas en el calendario.
- Confirmación de reserva y recibos en PDF.
- **Pasar a independiente:** una propiedad subordinada conserva sus datos y deja de leer la principal.

**Motor de reservas:** una página pública por propiedad para buscar, reservar y pagar, también dentro de tu
sitio con un iframe. Se configura en **Configuración → Motor de reservas**.

**Limpieza:** pantalla para el teléfono con el estado de cada habitación, tareas automáticas de salida y de
estancia, problemas con fotos y aviso por WhatsApp.

**Mensajes:** correo de salida desde el panel, plantillas por idioma, envíos automáticos por evento o por fecha
y avisos firmados a otros sistemas. Sin correo configurado, los mensajes quedan listos para copiar.

**Calendarios iCal:** un enlace por habitación para los portales, lectura de Airbnb, Booking.com y Vrbo, y aviso
de choques.

**Llegada en línea:** el huésped manda sus datos por un enlace, acepta los documentos legales y, si lo activas,
lo confirma con un código por correo. Identificaciones cifradas y borradas a los 30 días.

**Privacidad:** exportar y anonimizar los datos de un huésped; plazos de conservación aplicados cada noche.

**Dinero:** políticas de cancelación y no-show, datos fiscales, series, facturas y recibos, exportación para el
contador y doce informes con CSV.

**También:** cierre del día, plano de habitaciones, colores por tipo de cliente y modo de demostración pública.
Lo que ve el huésped ya está en francés, italiano, portugués y alemán.

**Correcciones**
- Las fechas de las tablas y los decimales salen como en México.
- Una copia de seguridad podía borrarse a sí misma si había otra en el mismo segundo.
- Borrar una propiedad ahora borra también sus archivos del disco. Si tiene facturas, no se borra.

## 1.0.4 — 2026-11-04

Instalar AlojaFlow ya es subir un archivo y abrirlo en el navegador.

**Instalador de un solo archivo:** `instalar-alojaflow-1.0.4.php` analiza el servidor, pide solo la
base de datos y tu usuario, hace todo lo demás y se borra solo. Ya no hace falta apuntar el dominio a
`public/` ni usar SSH, y funciona en la raíz de un dominio, en un subdominio o en una subcarpeta.

**Copias de seguridad:** en **Sistema → Copias de seguridad**, crear, descargar, subir y restaurar
copias completas, con una automática cada noche. Antes de restaurar se guarda el estado actual. Aparte
se puede descargar el archivo de configuración, que lleva la llave de cifrado.

**Sin cron también funciona:** las tareas programadas corren por turnos cuando alguien usa el sistema.

**Estado del sistema y auditoría:** dos pantallas nuevas en **Sistema**: qué le falta a la instalación
(con la línea del cron lista para copiar) y quién cambió qué.

**Correcciones:** los mensajes de validación salen en español.

## 1.0.3 — 2026-11-04

AlojaFlow también trabaja por su cuenta: al crear una propiedad eliges si es independiente o si
se subordina a HotelDruid o AlojaFlow 1.2.x.

**Propiedades independientes**
- **Unidades y tarifas:** se dan de alta en Configuración. Cada tarifa tiene un precio base por
  noche y temporadas con otro precio.
- **Huéspedes:** se crean y se editan, también desde la propia reserva. El documento se guarda
  cifrado y sus datos de contacto quedan listos para buscar.
- **Reservas y bloqueos:** se crean desde Reservas, Hoy, el Calendario (pulsando un día libre) o
  Disponibilidad. El total se calcula con la tarifa y se puede cambiar a mano.
- **Sin doble reserva:** la base de datos rechaza una segunda reserva en la misma unidad y noche,
  aunque lleguen dos a la vez. Si dos personas editan la misma reserva, se avisa a la segunda.
- **Operación:** cancelar y reactivar, registrar entrada y salida (también desde Hoy), y registrar
  cobros y devoluciones que actualizan el saldo.
- **Permisos:** gerente y recepción gestionan reservas y huéspedes; el gerente, también unidades y
  tarifas. Las reservas no se borran: se cancelan.
- **Borrar una propiedad independiente** pide escribir su código, porque no hay otra copia.

**Propiedades subordinadas:** siguen igual, de solo consulta y sin escribir nunca en la principal.

**Demo con datos ficticios:** en Propiedades, «Crear demo» arma el «Hotel Bahía Serena (demo)» con
12 unidades, 5 tarifas, 60 huéspedes inventados y reservas alrededor de la fecha de hoy. «Quitar
demo» lo borra sin tocar lo demás. También con `php artisan alojaflow:demo` y `--quitar`.

**Todavía no:** cargos adicionales, precios por persona o por semana, asignación automática de
unidad, grupos, cupones, gastos y caja, documentos, motor de reservas, iCal, copias de seguridad
completas desde el panel y pasar una propiedad subordinada a independiente.

## 1.0.2 — 2026-11-03

Actualizar AlojaFlow desde el panel con el zip de cada versión.

**Actualizaciones**
- Pantalla **Sistema → Actualizaciones**, solo para administradores: se sube el zip de la versión
  (o se copia por FTP), se revisa y se instala confirmando con la contraseña.
- Solo se instalan paquetes oficiales: el manifiesto va firmado y se comprueba cada archivo
  antes de cambiar nada. No se instalan versiones anteriores, y la misma solo con «Reinstalar».
- Nunca se tocan `.env`, `storage/` ni los datos. Antes de cambiar nada se copian la base de
  datos (descargable desde el historial) y los archivos que se sustituyen o borran.
- El mantenimiento dura segundos: los archivos se extraen y comprueban antes de parar la
  aplicación. Si algo falla, todo vuelve a como estaba; si fallan las migraciones, también.
- Si se cierra el navegador a medias, el programador termina la actualización, y si el proceso
  se corta, la deshace. El administrador sigue dentro durante el mantenimiento y ve el aviso para
  terminarla.
- Historial con la copia de la base de datos de cada actualización (se guardan las tres últimas).
- Comandos `alojaflow:actualizar` (lo mismo desde la consola), `alojaflow:finalizar-actualizacion`
  y `alojaflow:empaquetar` (construye el zip firmado de una versión).
- Página de mantenimiento propia, en el idioma de la aplicación.

## 1.0.1 — 2026-11-03

Primera versión de prueba: fase 1, entrega **Base**. AlojaFlow lee los datos de una instalación
de HotelDruid o AlojaFlow 1.2.x, sin escribir nunca en ella.

**Sincronización con HotelDruid**
- Se da de alta la principal de cada propiedad: dirección, usuario y contraseña cifrados,
  frecuencia y compresión.
- La copia se crea, se descarga y se borra del servidor de la principal, por POST y solo con los
  parámetros permitidos.
- La copia se lee sin ejecutarla y se proyecta en unidades, huéspedes, reservas y noches.
- Se detectan reservas nuevas, modificadas, canceladas, eliminadas y reactivadas. La primera
  carga no cuenta como cambios.
- **Avisos:**
  - sincronización fallida;
  - principal sin inicio de sesión;
  - copia que no se pudo borrar.
- **Pausa sin reintentar** si la principal rechaza las credenciales.
- Botones «Probar conexión», «Sincronizar ahora» y «Reanudar», historial de sincronizaciones y
  sincronización programada.

**Pantallas**
- **Resumen** de todas las propiedades: el día, pendiente de cobro, avisos y antigüedad de los
  datos.
- **Hoy:** llegan, salen y siguen alojados, con teléfono, WhatsApp y saldo.
- **Calendario** por habitaciones de un mes, también en el móvil.
- **Disponibilidad** por fechas y personas.
- **Indicadores:** ocupación, ingresos, ADR, RevPAR, por origen y por mes; CSV.
- **Reservas** y **Huéspedes** en modo consulta, con «Abrir en la principal».

**Plataforma**
- Inicio de sesión con verificación en dos pasos.
- Perfil con idioma y zona horaria.
- Usuarios con un rol por propiedad.
- Registro de auditoría de los cambios de configuración.
- Interfaz en español e inglés; dinero con el formato de cada moneda.
- Descarga pública del código fuente de la versión instalada (AGPL); «Acerca de» con los
  créditos.
- Comando `alojaflow:crear-administrador` y principal simulada para demostraciones.
