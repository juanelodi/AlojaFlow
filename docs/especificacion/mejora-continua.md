# Mejora continua

Desde la 1.0.38 (D-150). Una instalación de AlojaFlow puede mandar al equipo de AlojaFlow datos de
diagnóstico y de uso, y quien la usa puede reportar errores o sugerir cambios con imágenes. El receptor
es `https://dev.alojaflow.com/api/v1` (`ALOJAFLOW_MEJORA_URL`; vacío, no se manda nada).

## 1. Permiso

- **Al instalar:** una casilla, marcada, explica qué se manda y pide permiso. Se puede desmarcar.
- **Al actualizar desde una versión anterior:** el administrador ve un aviso en el panel con «Sí, enviar»
  y «No, gracias». **Mientras no decide, no se manda nada.**
- **Después:** *Sistema → Mejora continua* (solo administrador) para cambiarlo, identificarse, ver
  exactamente lo que se manda y «Olvidar mi instalación», que borra en el receptor todo lo suyo.
- Sin permiso no se junta nada: ni errores ni visitas. Lo que hubiera quedado se borra.
- En la demostración pública (`ModoDemo`) nunca se manda nada.
- **En el servicio alojado** (una instancia, D-162) no hay casilla ni aviso: lo decide el contrato
  (D-168). `ALOJAFLOW_MEJORA_CONTINUA` en el `.env` de la instancia (por omisión, sí) lo apaga. La pantalla lo
  explica y sigue enseñando lo que se manda; no tiene «Olvidar mi instalación».

## 2. Qué se manda

**Nunca:** nada de huéspedes (nombres, correos, teléfonos, documentos, fechas de estancias), importes,
contraseñas, claves, el contenido de `.env` ni nombres del personal.

| Qué | Cuándo | Contenido |
|---|---|---|
| Latido | Una vez al día, a una hora que depende de la instalación | **Entorno:** versiones de AlojaFlow, PHP y base de datos, extensiones y límites de PHP, servidor web (solo la familia), HTTPS, zona horaria, cron o por visitas, cola, disco libre y tamaño de la base. **Uso:** propiedades por modo y país, unidades, usuarios activos e idiomas, módulos prendidos, reservas creadas en 30 días por origen (número exacto), pasarelas y otras integraciones configuradas, visitas por pantalla en 30 días, funciones usadas. **Salud:** última copia y si la de fuera del servidor subió bien (D-152), sincronización, última actualización, fuentes iCal con error, correos fallidos. |
| Errores | Cada hora, si hubo | Lo que reporta Laravel: clase, mensaje **saneado**, archivo y línea relativos, traza sin argumentos, ruta, método, rol (no el usuario) e idioma. De una consulta, el SQL con `?`, nunca sus valores. El mismo error el mismo día va una vez, con cuántas veces pasó. También (D-161) los errores de JavaScript del panel —tipo, mensaje saneado, archivo, línea, pila y pantalla con los números cambiados por `{n}`, como mucho cinco por página— y los avisos del registro de nivel *warning* o más sin excepción. |
| Visitas | En el latido | Por nombre de pantalla y día; una escritura por página abierta (`ContarPantallas`). |
| Identificación | En el latido, solo si se eligió | Nombre del alojamiento, dominio y correo de contacto. Al quitarla, el receptor la borra. |
| Marca de alojada | En el registro, el latido, los errores y los reportes, solo de una instancia | `alojada: {codigo}`: el código del cliente en el servicio alojado (`ALOJAFLOW_CODIGO_INSTANCIA`, o el nombre de su carpeta). Todas comparten `app.alojaflow.com`: el receptor las distingue por él. |

`App\MejoraContinua\Saneador` quita del mensaje de error correos, números de 6 o más cifras, lo que va
entre comillas (salvo nombres de código, como `key "tarifa"`) y las rutas del servidor. El receptor
vuelve a limpiar lo mismo.

## 3. Reportar o sugerir

Cualquiera del equipo del alojamiento, desde el menú de usuario o el apartado *Mejora continua → Reportar o
sugerir*: tipo (error, sugerencia, informe, pregunta u otro), título, descripción, pasos, hasta 10 imágenes y
contacto opcional. **Solo JPG o PNG:** el formulario lo exige y `ImagenSegura` lo revisa por el contenido
(no por el nombre), las vuelve a codificar sin metadatos y las reduce a 2560 px; lo que no pasa se borra.
Cada persona manda hasta diez por hora. Se
agregan solos la pantalla de donde se abrió, el idioma, el rol, el navegador y el tamaño de la pantalla.
«Adjuntar el diagnóstico» viene marcado si hay permiso.

- No depende del permiso de los datos de uso: lo manda quien lo escribe.
- Se guarda en `reportes_mejora` y se manda en el momento; si no hay conexión, cada hora.
- Las imágenes se borran de la instalación en cuanto llegan.
- La respuesta del equipo llega a la misma pantalla: el latido avisa si hay respuestas nuevas y entonces se
  consultan. El administrador ve los de todos; los demás, los suyos.

## 4. Protocolo v1 (resumen)

| Petición | Para qué |
|---|---|
| `POST instalaciones` `{uuid, version}` → `{ficha}` | Registro con un UUID al azar. La ficha se guarda cifrada (`Ajustes::guardarSecreto`). |
| `POST latidos` | El latido; responde `ultima_respuesta_en`. |
| `POST errores` `{errores: [...]}` | Hasta 100 por envío. |
| `POST reportes` → `{folio}` | El texto; la `clave` (UUID) evita duplicados al reintentar. |
| `POST reportes/{folio}/imagenes` | Una imagen por petición (`imagen`, `orden` 1–10). |
| `GET reportes[?desde=]` | Estado y respuesta de los reportes de la instalación. |
| `DELETE instalaciones/yo` | «Olvidar mi instalación». |

Una instancia alojada agrega `alojada: {codigo}` al cuerpo de `POST instalaciones`, `POST latidos`,
`POST errores` y `POST reportes` (D-168).

Cabeceras: `X-AlojaFlow-Instalacion: <uuid>` y `Authorization: Bearer <ficha>`. Solo HTTPS (salvo
`localhost`). Con un 401 la instalación olvida su ficha y se registra de nuevo la siguiente vez.

## 5. Piezas

- `app/MejoraContinua/`: `Permiso`, `Saneador`, `ClienteReceptor`, `ArmadorLatido`, `RegistroErrores`,
  `ReportesMejora` y `EnvioMejora` (lo de cada hora).
- `alojaflow:mejora-continua` (cada hora; `--latido` lo manda ya), también en `TareasPorVisita`.
- `App\Http\Middleware\ContarPantallas`, y el gancho de `report()` en `bootstrap/app.php`.
- Pantallas `MejoraContinua` y `ReportarSugerir`; el aviso para decidir en `filament.avisos.mejora-continua`.
- Tablas `mejora_errores` (se borran a los 7 días si no salieron), `mejora_pantallas` (35 días) y
  `reportes_mejora`.
