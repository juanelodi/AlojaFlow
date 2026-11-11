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

*(se completa al terminar)*

## Cómo quedó organizado

*(se completa al terminar)*

## Comprobación

*(se completa al terminar)*
