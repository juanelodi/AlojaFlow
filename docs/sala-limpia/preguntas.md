# Sala limpia: lo que la especificación no alcanzó a decidir

Lo que sigue no se resolvió mirando el código anterior. La primera parte son **hechos** que se vieron al pasar las
copias por el programa nuevo o al comparar las huellas, y que convendría pasar a `subordinacion.md`. La segunda son
**preguntas abiertas**: ahí el módulo hace lo más prudente que permite la especificación, y la respuesta debe llegar
como un cambio en ella.

## Hechos observados (para la especificación)

Vistos en la copia de demostración ficticia —su estructura y sus valores inventados— o, de la copia real, solo en
conteos.

| # | Dónde | Hecho | Qué hace el módulo |
|---|---|---|---|
| 1 | C5, `tariffa` | Viene como `nombre#@&importe` (por ejemplo `Doble#@&2400`) | `tarifa_nombre` es lo que va antes de `#@&` |
| 2 | C6, `casse` | La caja por omisión viene sin nombre (`nome_cassa` vacío) | Una caja sin nombre no se proyecta |
| 3 | C6, `soldi` | Hay filas de una reserva con `saldo_prenota` en cero (24 en la copia real) | Una fila sin importe no es un cobro y no se anota |
| 4 | C6, `costi` | La primera fila de cada año viene sin `tipo_costo` | Solo son movimientos las de tipo `e` o `s` |
| 5 | C9 | Las tablas de periodos de un año cubren más de un año (de 485 a 1096 periodos en la copia real) | Una estancia que cruza el fin de año cabe entera en la tabla del año en que empieza |
| 6 | C9 | La fila que esa estancia deja en la tabla del año siguiente trae un periodo de inicio que ese año no tiene (32 filas en la copia real; 31 traen `id_anni_prec`) | Queda como `reservas_sin_fechas` y no se proyecta: ya está con la clave de su año |
| 7 | C9 | `id_anni_prec` viene también en reservas que solo están en una tabla (207 filas lo traen) | Solo cuenta como repetida si la del año anterior también se proyectó |
| 8 | C6, `parentele` | Columnas `idparentele` y `nome_parentela` | De ahí sale el texto del parentesco |
| 9 | C8, `personalizza` | Columnas `idpersonalizza`, `idutente`, `valpersonalizza`, `valpersonalizza_num` | Solo se guardan en el espejo las de `idutente = 1`; la proyección no las usa |

Coinciden con la huella del código anterior, y la especificación no los dice:

- `sesso` empieza por `m` (hombre) o por `f` (mujer); lo demás queda sin sexo.
- `lingua` se guarda si es uno de los idiomas de AlojaFlow; si no, queda vacío.
- Los códigos de país (`nacionalidad`, `domicilio_pais`) quedan vacíos: solo se guarda el texto de la principal.
- El lugar de nacimiento y el de expedición del documento son ciudad y región, separadas por coma.
- El orden de un contacto es su posición entre los que tiene el huésped: teléfonos, correos y fax, en ese orden.
- El día de un cobro o de un movimiento es el de `data_transazione` en la hora de la principal.
- Sin `tasseperc`, el porcentaje de impuestos queda vacío, no en cero.
- Las claves de origen son `soldi:AAAA:id` para un cobro y `costi:AAAA:id` para un movimiento, como `casse:id`.

## Preguntas abiertas

1. **Año que no existe (SUB-006).** La especificación dice que la principal contesta «un aviso o una redirección» y
   que el mensaje exacto está por verificar. *Hoy:* la primera vez, si llega una redirección se prueba una vez con el
   año anterior; si tampoco, se avisa de la redirección (SUB-099). Un aviso en una página sale como SUB-099 con su
   texto. **Nunca se clasifica SUB-006.** ¿Con qué se reconoce?
2. **Textos del límite diario (SUB-005) y del error de escritura (SUB-007).** Solo se conoce el del límite en español
   de HotelDruid 3.0.8, por la prueba. *Hoy:* se reconocen por raíces de palabras, sin acentos: «limit…» junto a
   «backup», y «permis…/permess…/permission» junto a «escri…/scri…/writ…». ¿Cuáles son los textos en italiano,
   español e inglés?
3. **Texto del error de borrado (SUB-013).** No está en la especificación. *Hoy:* el borrado se da por fallido si la
   respuesta no es 200, si es el formulario de acceso o si dice «no se pudo / non è stato possibile / could not…»
   pegado al verbo de borrar. Un texto distinto dejaría el aviso sin abrir, y uno parecido en la página correcta lo
   abriría de más. Hay que verlo con una principal real.
4. **Respuesta vacía al crear la copia.** Al probar la conexión es SUB-003 (el usuario no tiene el privilegio de
   copia). Al crear, la tabla de A3 solo dice «otro → SUB-099», y eso se hace: no pausa y reintenta. ¿Debe ser
   SUB-003 también?
5. **Bloqueos del plugin de calendarios de una principal 1.2.x.** La especificación da tres señas (cliente «Bloqueo
   iCal», comentario con `[ical]`, importe 0) sin decir si hacen falta las tres. *Hoy:* la marca `[ical]` en el
   comentario y el importe en cero, sin mirar el nombre del cliente. Ninguna de las dos copias de la huella trae
   bloqueos, así que esto no quedó comparado con el código anterior.
6. **Cierres** (`regole` con `motivazione2 = x`, D-083). La prueba fija la clave, el tipo, las fechas y el motivo.
   *Hoy:* además quedan confirmados, con el número visible `C<id>/<año>` y sin importe. Ninguna copia trae cierres:
   una instalación que ya los tenga verá ese número ponerse al día en la primera sincronización, sin eventos.
7. **Parentesco de un acompañante.** Se toma `nome_parentela` del catálogo de la principal. Ninguna copia trae
   parentescos en `rclientiprenota`, así que no quedó comparado.
8. **Un servidor que no resuelve.** *Hoy* es SUB-001 (se reintenta), no SUB-016 (que pausa): un fallo pasajero del
   DNS no debería dejar la principal en pausa hasta que alguien la reanude. ¿De acuerdo?
9. **Copia pisada por otra.** La especificación pide reintentar una vez tras 30 a 90 segundos. *Hoy* no se duerme el
   proceso: en una sincronización programada se adelanta la siguiente vuelta a ese plazo. En una manual, quien la
   pidió ve el error y vuelve a pulsar.
10. **La primera proyección tras actualizar no genera ningún evento** (D-173). Es lo más seguro para una instalación
    que se actualiza sola; a cambio, lo que cambie en la principal en esa ventana se aplica sin anunciarse. La otra
    opción era callar solo los «modificada».

11. **Dónde se guarda la generación del proyector.** *Hoy* va como una cuenta más (`generacion_proyector`) en
    `ejecuciones_sincronizacion.filas`, para no añadir una migración a un cambio que debía ser solo del módulo. Una
    columna en `principales` sería más clara. ¿Se cambia?
12. **Una caja propia con el nombre de una caja de la principal.** El nombre de una caja es único en la propiedad. Si
    en una subordinada alguien creara a mano una caja que se llama igual que una de la principal, la proyección
    chocaría. No se resolvió porque haría falta decidir cuál de las dos cambia de nombre.

## Fuera de esta sesión

- **Créditos que siguen diciendo «lógica de HotelDruid»:** el texto de «Acerca de» (`ui.acerca.hoteldruid`, en los seis
  idiomas), las reglas 4 y 9 del `CLAUDE.md` y `docs/INICIO.md`. No se tocaron: se revisan aparte.
- **El usuario sin años y sin formulario**. La principal simulada siempre enseña el formulario y no
  imita esa página; la prueba de aceptación la cubre con una respuesta fingida. El 2026-11-11 el usuario probó la
  conexión y sincronizó con ese usuario contra la principal real, desde una instancia desechable: las dos bien, con
  el mismo total de reservas
  que su instalación en línea, y el borrado de la copia se dio por bueno (ver el [registro](README.md)). Sigue abierto qué contesta la principal cuando el borrado falla
  (pregunta 3).
