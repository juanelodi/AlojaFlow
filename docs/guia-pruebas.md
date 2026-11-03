# Guía para probar AlojaFlow 1.0.1 (entrega Base)

Versión de prueba para usar en tu equipo con Windows. Es la **fase 1**: AlojaFlow lee los datos
de HotelDruid (o AlojaFlow 1.2.x) y **nunca escribe en él**.

## Qué trae esta versión

| Pantalla | Qué hace |
|---|---|
| **Resumen** | Una tarjeta por propiedad: llegadas y salidas de hoy, ocupación de la noche, llegadas de la semana, reservas sin confirmar, pendiente de cobro, avisos y antigüedad de los datos |
| **Hoy** | Llegan, salen y siguen alojados en una fecha, con teléfono, enlace de WhatsApp y saldo |
| **Calendario** | Calendario por habitaciones de un mes, con colores por estado; se puede ver en el móvil |
| **Disponibilidad** | Unidades libres por fechas y número de personas |
| **Indicadores** | Ocupación, ingresos, ADR, RevPAR, reservas, estancia media, por origen y por mes; descarga CSV |
| **Reservas / Huéspedes** | Listados con búsqueda y filtros, y ficha de cada uno (solo consulta) |
| **Propiedades** | Alta de propiedades y de su principal de HotelDruid; probar la conexión, sincronizar ahora y reanudar; historial de sincronizaciones |
| **Usuarios** | Usuarios con un rol en cada propiedad (gerente, recepción, limpieza, mantenimiento, contador) o administrador |
| **Perfil** | Idioma, zona horaria, contraseña y verificación en dos pasos con app de códigos |
| **Acerca de / Código fuente** | Licencia, créditos y descarga del código de la versión instalada |

**Todavía no trae:**
- limpieza y mantenimiento, llegada en línea, mensajes, iCal y facturación (entregas Operación, Huéspedes y Dinero);
- pantalla de cierre del día;
- instalador para hosting compartido ni copias de seguridad desde el panel.

La interfaz está completa en español e inglés; en francés, italiano, portugués y alemán solo
están traducidos los textos de Filament.

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

## 5. Comprobaciones de calidad

```powershell
C:\wamp64\bin\php\php8.3.28\php.exe -d xdebug.mode=off vendor\bin\pest
C:\wamp64\bin\php\php8.3.28\php.exe -d xdebug.mode=off vendor\bin\phpstan analyse --memory-limit=1G
C:\wamp64\bin\php\php8.3.28\php.exe -d xdebug.mode=off vendor\bin\pint --test
```

Estado al entregar: **54 pruebas en verde**; Larastan (nivel 6) y Pint sin errores.

## 6. Lo que se comprobó y lo que falta

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

## 7. Si algo falla

- **Página de error:** el detalle queda en `storage/logs/laravel.log`.
- **«Esta página no existe» o 403:** el usuario no tiene rol en esa propiedad, o la pantalla es
  solo para administradores.
- **Estilos raros tras actualizar:** ejecuta
  `C:\wamp64\bin\php\php8.3.28\php.exe artisan filament:assets`.
