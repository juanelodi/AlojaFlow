# Sala limpia: reescritura del módulo de HotelDruid

Registro de cómo se reescribió `app/Sincronizacion/HotelDruid/` y `tests/Support/ConstructorCopia.php`
sin ver el código de HotelDruid ni el código anterior que salió de su lógica. Decisión: D-173.

- **Fecha:** 2026-11-10.
- **Quién:** una sesión de Claude Code (modelo Claude Fable 5.2, `claude-fable-5-2`; sesión
  `cdc0d1e4-c264-4ef9-bb1d-f518bcf1f214`), por encargo de juanelodi, que escribió las reglas de abajo y
  revisa el resultado.
- **Rama:** `sala-limpia-hoteldruid`, a partir de `25fbc1e` (versión 1.0.43).

## Por qué

El módulo anterior se escribió con la lógica de HotelDruid a la vista (AGPL, © Marco M. F. De Santis;
regla 4 del `CLAUDE.md`). Para que AlojaFlow no dependa de esa lógica, el módulo se vuelve a escribir desde
la especificación del protocolo y del formato, que describe hechos y no código.

## Qué se reescribió

Los 14 archivos:

- `app/Sincronizacion/HotelDruid/`: `CargadorEspejo`, `ClientePrincipal`, `CodigoError`, `Conversor`,
  `Descompresor`, `ErrorSincronizacion`, `Esquema`, `LectorCopia`, `Proyector`, `ResultadoProyeccion`,
  `ResumenCopia`, `Sincronizador` y `ValidadorDestino`;
- `tests/Support/ConstructorCopia.php`.

## Reglas de la sesión

Prohibido durante toda la sesión:

1. Abrir, listar o buscar nada en `E:\HotelDruid\` (código, `upstream/`, wiki, manual, `docs/`), ni el código de
   HotelDruid o de AlojaFlow 1.2.x en internet. Únicas excepciones: las dos copias de la huella, y solo para
   pasarlas por el programa.
2. Leer el cuerpo de los 14 archivos, o sus versiones en git: nada de `git show`, `git log -p`, `git diff`,
   `git blame` ni `git stash` sobre esas rutas. Tampoco búsquedas dentro de ellos, salvo el comando de firmas.
3. Seguir las citas «[visto: archivo:líneas]» de la especificación: son la huella de quien la escribió.

Permitido:

- `docs/especificacion/subordinacion.md` (la fuente principal), `modelo-datos.md`,
  `subordinacion-alojaflow.md`, `docs/decisiones.md` y `CLAUDE.md`;
- todo el código que no se reescribe, y las pruebas existentes salvo `ConstructorCopia`: son las pruebas de
  aceptación;
- la lista de firmas públicas, solo firmas y sin cuerpos.

Lo que la especificación no alcanza a decidir no se adivina con el código viejo: va a
[preguntas.md](preguntas.md), y la respuesta llega como un cambio en la especificación.

## Qué se leyó antes de retirar el módulo

- **Firmas públicas** ([firmas-anteriores.txt](firmas-anteriores.txt)): la salida, sin tocar, de

  ```
  grep -n -E '^\s*((final|abstract|readonly) )*(class|interface|enum|trait) |^\s*case |^\s*public ' app/Sincronizacion/HotelDruid/*.php tests/Support/ConstructorCopia.php
  ```

  y, al final del archivo, los parámetros de las firmas de varias líneas, completados por cómo las llaman el
  resto del código y las pruebas.
- **Especificación y decisiones:** `subordinacion.md`, `modelo-datos.md`, `subordinacion-alojaflow.md`,
  `decisiones.md` y `CLAUDE.md`.
- **Código propio que usa el módulo o hace lo mismo con otra principal:** `app/Sincronizacion/AlojaFlow/`,
  `Subordinadores`, `Subordinador`, `ConFallosDeEjecucion`, `ContextoSincronizacion`, `NochesProyectadas`,
  `ProyeccionCaja`, `App\Support\{Huellas,Contactos,Dinero}`, `ClienteExterno`, `AjustesCorreo`, `Correo`,
  `AccionesSincronizacion`, `Modulos`, los modelos y enums, `config/alojaflow.php`, `lang/es/sincronizacion.php`,
  `lang/es/avisos.php`, las migraciones y `desarrollo/principal-simulada/crea_backup.php`.
- **Pruebas de aceptación:** `tests/Feature/SincronizacionTest.php`, `PantallasTest.php`,
  `SeguridadDatosTest.php`, `tests/Unit/LectorCopiaTest.php`, `DescompresorTest.php`,
  `ValidadorDestinoTest.php` y las demás que sustituyen el resolutor de `ValidadorDestino`.

## Lo que se vio de más, y cómo se trató

Se anota todo, también lo que no cambia nada:

1. **El comando de firmas también muestra ocho líneas `case '…':` de un `switch` de `LectorCopia`** (líneas 77
   a 200 del archivo anterior). No son firmas: son los nombres de los estados con que el lector anterior
   recorría la copia. Se dejan en `firmas-anteriores.txt` porque son la salida exacta del comando. El lector
   nuevo no se organiza con esos estados ni con un `switch` (ver «Cómo quedó organizado»).
2. **Una búsqueda recorrió la carpeta sin enseñar su contenido.** Para saber quién usa el módulo se corrió
   `grep -rl` (solo nombres de archivo) del espacio de nombres sobre `app`, `tests` y `resources`, y a su
   salida se le quitaron las rutas de los 14 archivos. No se mostró ninguna línea de ellos. Las búsquedas
   siguientes excluyeron la carpeta desde el principio.
3. **Dos rutas de `E:\HotelDruid\` que dio el usuario para la copia real.** La primera resultó ser una carpeta:
   solo se comprobó que lo era, sin listarla. La segunda fue la carpeta de respaldos: se listaron los nombres y
   tamaños de lo que tiene en su primer nivel, para tomar la única copia real que hay ahí, y no se abrió ningún
   archivo. Fuera de eso, de `E:\HotelDruid\` solo se usaron las dos copias, servidas por la principal simulada.
4. **Lo que se miró de las copias, ya dentro del programa nuevo.** De la demo ficticia, en el espejo de una base
   desechable: los nombres de las columnas de `prenota`, `casse`, `parentele`, `costi` y `personalizza`, y cómo
   vienen `tariffa`, `nome_cassa`, `tipo_costo` y `provenienza_costo`. De la copia real, solo conteos: cuántas
   reservas traen `id_anni_prec` o un periodo que no existe, cuántos periodos tiene cada año y cuántos cobros
   tienen importe cero. Lo que salió de ahí está en [preguntas.md](preguntas.md).
5. **La revisión final** (`/code-review`) se hizo sobre los archivos nuevos y contra el commit que retira el
   módulo (`b3c938d`), no contra `main`: una comparación con `main` habría enseñado el código retirado.

## Huella de caja negra

Con el código anterior funcionando, y sin leerlo, se sincronizaron dos copias, cada una en una base nueva y
desechable, contra la principal simulada (`desarrollo/principal-simulada/`):

- la demo ficticia (`demo-alojaflow-2026-respaldo-original.php`), en `alojaflow_sala_limpia_demo`;
- una copia real indicada por el usuario, en `alojaflow_sala_limpia_real`. De ella solo se guardaron conteos
  y sumas de verificación; nunca se abrió, se copió ni se citó.

De cada base se guardó, **fuera del repositorio**: cuántas filas quedaron en cada tabla, y por cada columna de
unidades, huéspedes, contactos, reservas, noches, acompañantes, cargos, pagos, cajas y movimientos, una suma
SHA-256, cuántos valores no nulos y distintos, el total de las numéricas y el reparto de las que son un
catálogo propio (estado, tipo, método). Las llaves foráneas se tradujeron a la clave de origen y se dejaron
fuera las columnas que dependen del momento o del azar (`id`, `creado_en`, `codigo_publico`…), para que dos
corridas se puedan comparar. De la demo, que es inventada, se guardó además una suma por celda, para saber en
qué fila está una diferencia, y un volcado de la base ya sincronizada, que solo sirve para probar que el
código nuevo no altera lo que dejó el anterior (el caso de una instalación que se actualiza).

Los resultados y la comparación con el código nuevo están en «Comprobación», más abajo.

## Batería antes de empezar

Con el código anterior, en `25fbc1e`: **1614 pruebas en verde** (50 592 aserciones), en 22 min 43 s.

## Commits

| Commit | Qué |
|---|---|
| `53ea40b` | Firmas públicas y registro inicial |
| `b3c938d` | **Se retira el módulo**: los 14 archivos, y nada más |
| `1aa2786` | El módulo, escrito de nuevo desde la especificación |
| `09bc0ab` | `ConstructorCopia`, escrito de nuevo desde la parte B |
| `5fa0677` | Pruebas de la parte D |
| `e4f9400` | La respuesta de la principal se reconoce tal como llegó |
| `b803eb3` | Avisos, D-173 y este registro |
| `9b6120d` | Lo que salió de la revisión |

El último commit de la rama cierra este registro.

## Cómo quedó organizado

Las 13 clases de antes siguen con su nombre y sus firmas públicas, porque las usa el resto de AlojaFlow. Por dentro
se organizaron sin saber cómo estaban, y se añadieron piezas nuevas:

| Parte | Clases | Cómo trabaja |
|---|---|---|
| Errores | `CodigoError`, `ErrorSincronizacion` | El mensaje es el detalle o el texto del código; nunca lleva la excepción del cliente HTTP |
| Destino (A6) | `ValidadorDestino` | Tabla propia de redes que no son de internet, y las IPv4 que van dentro de una IPv6 |
| Protocolo (A3) | `ClientePrincipal`, `RespuestaPrincipal` *(nueva)* | El cliente solo sabe pedir, con parámetros de una lista cerrada; reconocer lo que contestó la principal va aparte |
| Compresión (B7) | `Descompresor` | Descomprime por pedazos chicos, con tope, y distingue el gzip cortado |
| Formato (B1–B9) | `LectorCopia`, `LineasCopia` *(nueva)*, `Esquema`, `ResumenCopia` | Recorre la copia de arriba abajo, una sección tras otra (cabecera, versión, archivos, tablas, cierre), sobre un cursor de líneas que no reserva memoria por adelantado |
| Espejo | `CargadorEspejo`, `Espejo` *(nueva)* | Uno escribe por lotes, el otro lee tabla por tabla |
| Conversión (C2) | `Conversor` | Textos, fechas, horas a UTC e importes |
| Proyección (parte C) | `Proyector` y, en `Proyeccion/` *(nuevas)*: `Contexto`, `CalendarioPeriodos`, `UnidadesPrincipal`, `HuespedesPrincipal`, `ReservasPrincipal`, `CajaPrincipal`; `ResultadoProyeccion` | El proyector solo ordena; cada pieza proyecta lo suyo |
| Ciclo (A4, A5) | `Sincronizador` | Con `ConFallosDeEjecucion`, que ya era código propio |
| Pruebas | `tests/Support/ConstructorCopia.php` | Solo con las columnas que nombra la especificación |

## Comprobación

### Pruebas

- Las de aceptación que ya existían (`SincronizacionTest`, `LectorCopiaTest`, `DescompresorTest`,
  `ValidadorDestinoTest`, `PantallasTest` y las demás que pasan por el módulo) pasan sin tocarlas.
- Se añadieron `tests/Feature/SubordinacionHotelDruidTest.php` y `tests/Unit/FormatoCopiaTest.php`, con lo que
  pide la parte D y no tenía prueba.
- Batería completa con el código nuevo: *(ver «Resultado final»)*.

### Huellas: código anterior contra código nuevo

Las dos copias, cada una en una base recién creada, con el código nuevo. Se compararon con las del código anterior
columna por columna.

| | Demo ficticia | Copia real |
|---|---|---|
| Unidades | 17 | 6 |
| Huéspedes | 974 | 1645 |
| Contactos | 1948 | 2080 |
| Reservas | 1122 | 1831 |
| Noches | 4237 | 7987 |
| Acompañantes | 1122 | 2012 |
| Cobros | 1628 | 2643 |
| Movimientos de caja | 0 | 7 |
| Cajas y cargos | 0 | 0 |
| Filas en el espejo | 5874 | 16 389 |
| Columnas comparadas | 129 | 144 |
| **Columnas iguales** | **127** | **142** |
| Eventos en la segunda pasada | 0 | 0 |

**Diferencias que quedan, y su causa:**

1. **`huespedes.huella_datos` y `reservas.huella_datos`.** Es una suma de los campos proyectados, y el código nuevo
   la calcula a su manera: no hay forma de saber cómo la armaba el anterior sin leerlo, y no hace falta. Las
   columnas de las que sale son iguales una por una. En la copia real coincide en 234 reservas, tantas como las
   que no tienen acompañantes.
2. **`generacion_proyector` entre las cuentas de cada ejecución.** Es nueva: dice qué generación del proyector dejó
   esa carga (D-173).

**Diferencias que salieron por el camino y ya no están:**

- `reservas.tarifa_nombre`: la primera versión guardaba `tariffa` entera, y trae `nombre#@&importe`. La
  especificación deja ese formato «por verificar»; se vio en la demo y ahora se guarda el nombre.
- Cobros de más en la copia real (2667 contra 2643): eran exactamente los 24 de importe cero, que el código anterior
  no anotaba. Sumaban cero. Ahora tampoco se anotan.

Lo demás salió igual a la primera: unidades, huéspedes, contactos, reservas (también `huella_identidad` y las fechas
en UTC), noches con sus importes, acompañantes, movimientos y las incidencias (`reservas_sin_fechas`: 32).

### Una instalación que se actualiza

Es el caso que importa: una base que dejó el código anterior y recibe el nuevo. Se probó con las dos, sincronizando
con el código nuevo encima de lo que había dejado el anterior (la demo, restaurada de su volcado; la real, en la
misma base de la huella):

| | Demo | Real |
|---|---|---|
| Filas con `actualizado_en` nuevo (reservas, huéspedes, unidades, cobros, movimientos) | 0 | 0 |
| Eventos generados | 0 | 0 |
| Columnas distintas después | solo las dos huellas de datos | solo las dos huellas de datos |
| Eventos en la sincronización siguiente | 0 | 0 |

### En el navegador y como instancia

Con una instancia desechable (`ALOJAFLOW_INSTANCIA`, su propio `.env`, su `storage` y su base) y la principal
simulada sirviendo la demo: «Probar conexión» respondió «La principal respondió y las credenciales valen», y
«Sincronizar ahora», «Sincronización correcta: 1122 reservas, 0 cambios». La huella de esa base es la misma, el
calendario pinta las reservas y en el `storage` de la instancia no quedó la copia descargada.

### Lo que no se pudo comprobar aquí

- **El usuario sin años y sin formulario**: la principal simulada no lo imita. Lo cubre una prueba con
  una respuesta fingida, pero falta verlo contra la principal real, junto con lo que contesta al borrar la copia.
- **Bloqueos de calendario, grupos, cierres, cajas con nombre y parentescos**: ninguna de las dos copias los trae. Los
  cubren las pruebas, no la huella.
- Las preguntas de [preguntas.md](preguntas.md).

### Revisión

`/code-review high` sobre lo añadido desde `b3c938d`. Diez hallazgos; se corrigieron ocho, con su prueba:

- la señal de éxito de cada paso se busca antes que el formulario de acceso;
- unidades, reservas y cajas se casan por nombre sin distinguir mayúsculas ni acentos, como los índices de la base;
- los textos se recortan también a los bytes de una columna TEXT;
- dos cajas pueden intercambiar su nombre;
- lo que pase al pedir el borrado no tapa el error de la descarga;
- el extracto de una página no se queda vacío si una expresión regular falla;
- una sola consulta para saber quién tiene cada código de unidad.

Quedaron sin tocar, a decidir: la generación del proyector va entre las cuentas de la ejecución y no en una columna
propia (pediría una migración), y el cifrado del documento y la forma de un evento están escritos igual en los dos
proyectores (unificarlos toca `App\Sincronizacion\AlojaFlow\Proyector`, que no era parte de esto).

Después de la revisión se repitieron las dos huellas y la prueba de actualización: mismos resultados.

### Resultado final

- **Pest:** **1642 pruebas en verde** (50 675 aserciones), en 21 min 38 s: las 1614 de antes y 28 nuevas.
- **Pint:** limpio en los archivos del módulo y sus pruebas.
- **Larastan:** sin errores en todo el proyecto.
- Sin `push`, sin etiqueta, sin paquete y sin versión nueva: queda en la rama `sala-limpia-hoteldruid` para revisarse.

### Limpieza

Al terminar se borraron las bases desechables (`alojaflow_sala_limpia_*`), la instancia de prueba y el volcado de
la demo. Las huellas, que solo tienen conteos y sumas, quedaron fuera del repositorio, en la carpeta temporal de la
sesión; lo que importa de ellas está en las tablas de arriba.
