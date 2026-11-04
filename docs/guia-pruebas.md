# Guía para probar AlojaFlow

Versión de prueba para usar en tu equipo con Windows. Cada propiedad trabaja de una de dos formas:
**independiente**, con AlojaFlow como su sistema, o **subordinada**, donde AlojaFlow lee los datos
de HotelDruid (o AlojaFlow 1.2.x) y **nunca escribe en él**.

## Probar rápido con la demo

1. Entra a **Configuración → Propiedades** y pulsa **Crear demo**. Tarda unos segundos.
2. Abre **Resumen**: aparece el «Hotel Bahía Serena (demo)» con llegadas, salidas y huéspedes
   alojados de hoy. Todos los datos son inventados, con correos `@example.com`.
3. Prueba esto:
   - **Hoy:** registra una entrada y una salida.
   - **Calendario:** pulsa un día libre para crear una reserva; el total sale de la tarifa.
   - **Otra vez la misma noche:** esa unidad aparece desactivada, y si insistes se rechaza.
   - **Una reserva:** cámbiale las fechas, regístrale un pago y cancélala.
   - **Tarifas:** cambia un precio o agrega una temporada y crea otra reserva en esas fechas.
4. Cuando termines, **Quitar demo** la borra sin tocar tus otras propiedades. La fecha de la demo
   es la del día en que la creas: para volver a tener llegadas de hoy, quítala y créala otra vez.

Para empezar una propiedad de verdad: **Propiedades → Crear** y elige **Independiente**. Luego da de
alta las **Tarifas**, después las **Unidades** y ya puedes crear reservas.

En una independiente, AlojaFlow tiene la **única copia** de los datos: descarga de vez en cuando una
copia desde **Sistema → Copias de seguridad** y guárdala fuera del servidor.

## Qué trae esta versión

| Pantalla | Qué hace |
|---|---|
| **Resumen** | Una tarjeta por propiedad: llegadas y salidas de hoy, ocupación de la noche, llegadas de la semana, reservas sin confirmar, pendiente de cobro, avisos y antigüedad de los datos |
| **Hoy** | Llegan, salen y siguen alojados en una fecha, con teléfono, enlace de WhatsApp y saldo. En una independiente, registrar entrada y salida |
| **Calendario** | Calendario por habitaciones de un mes, con colores por estado; se puede ver en el móvil. En una independiente, un día libre abre una reserva nueva |
| **Disponibilidad** | Unidades libres por fechas y número de personas. En una independiente, botón para reservar |
| **Indicadores** | Ocupación, ingresos, ADR, RevPAR, reservas, estancia media, por origen y por mes; descarga CSV |
| **Reservas / Huéspedes** | Listados con búsqueda y filtros, y ficha de cada uno. En una independiente se crean y se cambian, con pagos; en una subordinada, solo consulta |
| **Propiedades** | Alta eligiendo independiente o subordinada. En una subordinada: probar la conexión, sincronizar ahora y reanudar, con historial. Botones para crear y quitar la demo |
| **Unidades / Tarifas** | Habitaciones y precios: por noche con persona extra o por persona, con fin de semana, temporadas y estancia mínima |
| **Impuestos, cargos, canales, cupones, cajas, gastos, grupos** | El dinero de una propiedad independiente. Al crear una reserva, el total sale de todo esto |
| **Motor de reservas** | Página pública para reservar y pagar, con su enlace y su código para ponerlo en tu sitio. Llaves de Stripe y Mercado Pago |
| **Limpieza / Tareas** | El estado de cada habitación y lo que hay que hacer, para usarse en el teléfono. Reportar problemas con foto, y aviso por WhatsApp |
| **Plano** | Las habitaciones sobre un plano, con lo que pasa hoy en cada una |
| **Cierre del día** | Lo que quedó por registrar, lo cobrado por método y la ocupación; guarda la foto del día |
| **Mensajes** | Historial y cola. Sin correo configurado, aquí queda el texto listo para copiar o mandar por WhatsApp |
| **Plantillas, Automatizaciones, Avisos a otros sistemas, Correo** | Qué se manda, cuándo y a quién |
| **Calendarios iCal / Choques** | Enlaces para los portales, lectura de sus calendarios y aviso cuando una habitación está ocupada dos veces |
| **Llegadas en línea / Documentos legales** | Lo que mandan los huéspedes antes de llegar, los textos que aceptan y la prueba de la aceptación |
| **Facturas y recibos / Datos fiscales / Políticas** | Facturar desde la reserva, exportar para el contador; penalización al cancelar y marca de no-show |
| **Informes** | Doce informes con CSV |
| **Usuarios** | Usuarios con un rol en cada propiedad (gerente, recepción, limpieza, mantenimiento, contador) o administrador |
| **Actualizaciones** | Subir el zip de una versión nueva, revisarlo e instalarlo; historial con la copia de la base de datos de cada actualización |
| **Perfil** | Idioma, zona horaria, contraseña y verificación en dos pasos con app de códigos |
| **Acerca de / Código fuente** | Licencia, créditos y descarga del código de la versión instalada |

**Todavía no trae:**
- timbrado real de CFDI (falta un PAC), factura global, complementos de pago y notas de crédito;
- envío a SES.Hospedajes y VeriFactu de España;
- código de confirmación por WhatsApp o SMS;
- channel manager por API: con los portales se trabaja por iCal.

**Idiomas:** todo está en español e inglés. Lo que ve el huésped (motor de reservas, llegada en línea, mensajes,
documentos en PDF y textos legales de partida) también está en francés, italiano, portugués y alemán, sin revisar
por hablantes nativos. El panel del personal en esos cuatro idiomas sale en inglés, salvo los textos de Filament.

### Cosas nuevas que vale la pena probar

Con la demo creada (**Hotel Bahía Serena**):

1. **Motor:** abre `/reservar/<código de la propiedad>`, busca fechas, reserva y abre la página de la reserva.
2. **Pagos con Stripe:** con tu cuenta en modo de prueba, pon la llave `sk_test_…` y el secreto de avisos en
   **Motor de reservas**, y paga con la tarjeta de prueba `4242 4242 4242 4242`. **Esto no se ha podido probar
   con una cuenta real:** dime qué pasa. Mercado Pago, igual con sus credenciales de prueba.
3. **Limpieza:** en el teléfono, termina una tarea y reporta un problema con foto.
4. **Mensajes:** en una reserva, **Mandar mensaje** con una plantilla; sin correo configurado, míralo en **Mensajes**.
5. **Llegada en línea:** en una reserva, **Más → Enlace de llegada**, ábrelo en otra ventana y mándalo; revísalo
   en **Llegadas en línea**.
6. **Factura:** da de alta **Datos fiscales** con una serie y, en una reserva, **Más → Facturar**. Descarga PDF y XML.
7. **iCal:** en **Calendarios iCal → Enlaces para los portales**, abre un enlace; agrega como fuente el de otra
   habitación para ver un bloqueo y su choque.
8. **Pasar a independiente:** en una propiedad subordinada de prueba, en **Propiedades → Editar**.

### Demostración pública

Para enseñar el sistema en internet, en una instalación **aparte** y sin datos reales:

1. Instálala como cualquier otra y pon `ALOJAFLOW_MODO_DEMO=true` en su `.env`.
2. Corre `php artisan alojaflow:demo-reiniciar`: **borra toda la base** y la deja con la demo y su usuario.

El inicio de sesión ya viene lleno, hay un aviso en todas las pantallas, los correos van al log, y usuarios,
actualizaciones, copias, contraseñas y llaves de pago quedan fuera. Cada noche se borra sola. Con el modo apagado,
el comando se niega a correr.

## 1. Poner en marcha la base de datos

AlojaFlow usa el **MySQL 8.4 de WampServer**: abre **WampServer** desde el menú Inicio y espera
a que el icono se ponga verde. Ya están creadas las bases `alojaflow` (la que usas) y
`alojaflow_pruebas` (la de las pruebas automáticas), con el usuario `alojaflow`. Su contraseña
está en el archivo `.env`.

> Si no quieres abrir WampServer, puedes arrancar solo MySQL en una terminal y dejarla abierta:
>
> ```powershell
> C:\wamp64\bin\mysql\mysql8.4.7\bin\mysqld.exe --defaults-file="C:/wamp64/bin/mysql/mysql8.4.7/my.ini" --basedir="C:/wamp64/bin/mysql/mysql8.4.7" --datadir="C:/wamp64/bin/mysql/mysql8.4.7/data" --console
> ```

## 2. Arrancar AlojaFlow

En PowerShell, dentro de `E:\AlojaFlow`:

```powershell
C:\wamp64\bin\php\php8.3.28\php.exe -d xdebug.mode=off artisan serve
```

Abre **http://127.0.0.1:8000**.

**Tu usuario administrador.** Créalo una vez; te pide la contraseña sin mostrarla, con mínimo
12 caracteres:

```powershell
C:\wamp64\bin\php\php8.3.28\php.exe artisan alojaflow:crear-administrador tu@correo.com --nombre="Tu nombre"
```

Ya hay un usuario de prueba (`admin@alojaflow.test`) y una propiedad de prueba, «Hotel Demo
Zihuatanejo», con los datos ficticios de la demo de alojaflow.com. La contraseña está en
`.herramientas/credenciales-prueba.txt`. Puedes borrar los dos desde Usuarios y Propiedades.

## 3. Conectar tu HotelDruid

1. **Propiedades → Crear.** Escribe el nombre, el código, la zona horaria y la moneda.
2. **Instalación principal:** la dirección de la carpeta de HotelDruid, terminada en `/`.
   - **Tu AlojaFlow 1.2.3 local:** arranca su servidor con `powershell tools\dev.ps1` dentro de
     `E:\HotelDruid` y usa la dirección `http://localhost:8080/`. Activa «Permitir red local».
   - **Una instalación en internet:** usa la dirección `https://…/`.
   - **Usuario y contraseña:** los del **administrador** de HotelDruid. Déjalos vacíos si la
     instalación no tiene activado el inicio de sesión; AlojaFlow te avisará de que cualquiera
     puede descargar su copia.
3. **Guardar → Probar conexión → Sincronizar ahora.** En la parte de abajo de la propiedad ves el
   historial de sincronizaciones, con el código de error si algo falla (`SUB-…`; ver
   `docs/especificacion/subordinacion.md`).

Para que sincronice sola cada 30 minutos, o la frecuencia que elijas, deja abierta otra
terminal con el programador:

```powershell
C:\wamp64\bin\php\php8.3.28\php.exe -d xdebug.mode=off artisan schedule:work
```

En un hosting compartido, esto lo hace un único cron por minuto.

### Probar sin datos reales

La principal simulada sirve cualquier copia de HotelDruid; por ejemplo, la ficticia de la demo:

```powershell
$env:COPIA_HOTELDRUID = "E:\HotelDruid\respaldos\demo-alojaflow-2026-respaldo-original.php"
C:\wamp64\bin\php\php8.3.28\php.exe -S 127.0.0.1:8090 -t desarrollo\principal-simulada
```

En AlojaFlow, la principal es `http://127.0.0.1:8090/`, con «Permitir red local».

**Sobre la copia de la demo:**
- Es la del hotel **ficticio de Zihuatanejo**: 17 unidades y 974 huéspedes con correos
  `@example.com`.
- Es idéntica a la que trae `dist/AlojaFlow-1.1.4-demo.zip` y la genera `tools/demo_genera.py`.
- **No uses `respaldos/demo/`:** aunque se llame así, contiene los datos reales del alojamiento.

## 4. Qué conviene probar

- Las cifras del **Resumen** y de **Hoy** frente a lo que muestra HotelDruid para el mismo día.
- El **Calendario** de este mes y del siguiente, en el PC y en el móvil.
- Los **Indicadores** de un mes que conozcas bien.
- **Detectar cambios:**
  1. Cambia una reserva en HotelDruid (fechas o importe) y borra otra.
  2. Pulsa «Sincronizar ahora».
  3. En la ficha de la reserva, sección «Cambios detectados», aparece lo que cambió. La primera
     sincronización no genera cambios.
- **Usuarios:**
  1. Crea un usuario con rol **limpieza** en una propiedad.
  2. Entra con él en otro navegador o en una ventana privada: no debe ver Indicadores ni la
     configuración.
- **Verificación en dos pasos:** menú del usuario → Perfil → activar la app de códigos.
- **Credenciales malas:** pon una contraseña mala en la principal y sincroniza. La sincronización
  se pausa con `SUB-003` sin reintentar, para no bloquear la cuenta de HotelDruid. Al guardar la
  contraseña buena, se reanuda.

## 5. Probar las actualizaciones

Desde la 1.0.2, AlojaFlow se actualiza en **Sistema → Actualizaciones**: se sube el zip de la
versión nueva (`alojaflow-x.y.z.zip`), se revisa y se instala con tu contraseña. Antes de cambiar
nada se comprueban la firma del paquete y cada archivo, y se copia la base de datos. Si algo
falla, todo vuelve a como estaba. Detalles: `docs/especificacion/instalacion-actualizaciones.md`.

Tu copia `E:\AlojaFlow` es la de desarrollo (tiene git): ahí la pantalla avisa de que se actualiza
con git y no instala nada. Las actualizaciones se prueban en una **instalación hecha desde el
zip**, `E:\AlojaFlow-prueba`, con su propia base de datos `alojaflow_prueba`.

**Una sola vez:** crea esa base con el usuario `root` de MySQL. Puedes hacerlo en phpMyAdmin
(http://localhost/phpmyadmin) o en la consola de MySQL del menú de WampServer:

```sql
CREATE DATABASE alojaflow_prueba CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON alojaflow_prueba.* TO 'alojaflow'@'127.0.0.1';
```

**Para probar:**

1. Arranca la instalación de prueba en PowerShell, dentro de `E:\AlojaFlow`, y deja la ventana
   abierta:

   ```powershell
   C:\wamp64\bin\php\php8.3.28\php.exe -d xdebug.mode=off -d upload_max_filesize=512M -d post_max_size=512M -S 127.0.0.1:8001 -t E:\AlojaFlow-prueba\public desarrollo\servidor-instalacion.php
   ```

2. Abre **http://localhost:8001** y entra con los mismos usuarios que en tu copia: su base es una
   copia de la de desarrollo.
3. En **Sistema → Actualizaciones**, sube el zip (o cópialo a
   `E:\AlojaFlow-prueba\storage\app\private\actualizaciones\entrada\`). Pulsa **Revisar** y
   después **Actualizar a la …**.
4. Para repetir la prueba con la misma versión, marca **Reinstalar si es la misma versión**.

**Qué conviene probar:**
- que un zip alterado (por ejemplo, con un archivo cambiado dentro) se rechaza sin cambiar nada;
- cerrar el navegador justo al pulsar **Actualizar** y volver a entrar: aparece «Terminar la
  actualización»;
- descargar la copia de la base de datos desde el historial.

El zip de cada versión lo genera `artisan alojaflow:empaquetar` en `E:\AlojaFlow\dist\`.

## 6. Comprobaciones de calidad

```powershell
C:\wamp64\bin\php\php8.3.28\php.exe -d xdebug.mode=off vendor\bin\pest
C:\wamp64\bin\php\php8.3.28\php.exe -d xdebug.mode=off vendor\bin\phpstan analyse --memory-limit=1G
C:\wamp64\bin\php\php8.3.28\php.exe -d xdebug.mode=off vendor\bin\pint --test
```

Estado al entregar: **97 pruebas en verde**; Larastan (nivel 6) y Pint sin errores.

## 7. Lo que se comprobó y lo que falta

**Comprobado contra un AlojaFlow 1.2.3 real** (instalación nueva con la copia ficticia de la
demo):
- crear, descargar comprimida y borrar la copia, por **POST**;
- leer el esquema 3.081 y proyectar 17 unidades, 974 huéspedes, 1122 reservas y 4237 noches;
- detectar una reserva modificada y otra eliminada.

**Falta probar en una principal real:**
- con el **inicio de sesión activado** (usuario y contraseña);
- en un **hosting en internet** (HTTPS, cortafuegos);
- con **HotelDruid 3.0.8 original**;
- con años con **periodos semanales**.

Las demás preguntas abiertas están al final de `docs/especificacion/subordinacion.md`.

## 8. Si algo falla

- **Página de error:** el detalle queda en `storage/logs/laravel.log`.
- **«Esta página no existe» o 403:** el usuario no tiene rol en esa propiedad, o la pantalla es
  solo para administradores.
- **La aplicación se quedó en mantenimiento** después de actualizar:
  - espera 2 minutos: la termina el programador si está en marcha (`artisan schedule:work`);
  - o entra como administrador: verás «Terminar la actualización»;
  - o ejecuta `artisan alojaflow:finalizar-actualizacion` en esa instalación.
- **Estilos raros tras actualizar:** ejecuta
  `C:\wamp64\bin\php\php8.3.28\php.exe artisan filament:assets`.
