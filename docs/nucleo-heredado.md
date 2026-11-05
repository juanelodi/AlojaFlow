# El núcleo heredado de HotelDruid: qué entra

Con la 1.0.24 terminó el plan de [plan-paridad.md](plan-paridad.md), que trajo lo que AlojaFlow 1.2.3 había agregado
**encima** de HotelDruid. Faltaba revisar lo de abajo: lo que HotelDruid 3.0.8 ya traía de fábrica (reservas, tarifas,
costes, documentos, usuarios…). Esta lista es para que el dueño del proyecto decida qué entra.

**Lo que se decidió (2026-11-05, D-117):** entran los números 2, 4, 5, 6, 10, 11, 13, 16, 28 y 32, en el orden del final de este documento. Quedaron hechos entre la 1.0.25 y la 1.0.29. Lo demás se queda fuera hasta que alguien lo pida.

**A quién le importa.** Solo a las propiedades independientes. En una subordinada, todo esto lo sigue haciendo la
principal, y AlojaFlow nada más lo copia y lo muestra.

**De dónde salió.** Del manual y del código de `E:\HotelDruid\` (solo para entender la lógica, regla 4), comparando
función por función con el código de AlojaFlow de hoy. Lo que dice «hoy» se comprobó buscándolo en el código. Las
recomendaciones son mías, no decisiones.

**Cómo leerla.**

- **Hoy:** ✔ ya está, ◐ en parte, ✘ no está.
- **Tamaño:** chico, mediano o grande, según cuánto del sistema toca.
- **Recomiendo:** *Sí*, *Sí, más simple*, *Si alguien lo pide* o *No*.

## Lo que falta decidir

### Reservas

| # | Qué hace en HotelDruid | Hoy en AlojaFlow | Tamaño | Recomiendo |
|---|---|---|---|---|
| 1 | **Asignación automática.** Le das una lista de habitaciones y elige una por capacidad y prioridad; después la puede mover para aprovechar los huecos | ◐ El motor prueba varias y toma una libre. El panel tiene «Elegir la primera libre», por capacidad y por el orden de las unidades. Lo que falta es limitarla a una lista y moverla después | Grande | **No:** lo útil ya está. Mover sola una reserva ya hecha es justo lo que no conviene: un huésped al que ya le dijiste su cabaña no debe cambiar sin que nadie decida |
| 2 | **Cambiar las fechas arrastrando** el inicio o el final de la reserva en el calendario | ◐ Arrastrar mueve la reserva completa; las fechas se cambian en el formulario | Chico | **Sí** |
| 3 | **Varias habitaciones en una sola operación** | ◐ En el panel, con Grupos. En el motor, cada reserva es de una unidad | Mediano | **Si alguien lo pide:** Grupos ya cubre lo del panel; en el motor es un carrito completo |
| 4 | **Camas extra.** Un coste que cuenta como cama y deja rebasar la capacidad de la habitación | ◐ Persona extra en la tarifa, pero la capacidad es un tope fijo | Chico | **Sí, más simple:** un tope normal y un tope con camas extra por unidad, con su cargo |

### Tarifas

| # | Qué hace en HotelDruid | Hoy en AlojaFlow | Tamaño | Recomiendo |
|---|---|---|---|---|
| 5 | **Varias tarifas para una misma unidad** (con desayuno, no reembolsable, para 4 personas), con mínimo y máximo de personas | ✘ Cada unidad tiene una sola tarifa | Grande | **Sí.** Es la falta más grande: sin esto, el motor no puede ofrecer dos precios para el mismo cuarto. Toca cotización, motor y formulario |
| 6 | **Tarifa basada en otra** (la misma, más o menos un porcentaje) | ✘ | Chico, si va con el 5 | **Sí**, junto con el 5 |
| 7 | **Alquiler por semanas completas**, con día de cambio fijo | ◐ Precio por semana, mínimo de noches y días de llegada: se puede imitar | Chico | **No:** ya alcanza con lo que hay |
| 8 | **Moneda secundaria:** cobrar en otra moneda al cambio que configures | ✘ Una moneda por propiedad | Mediano | **Si alguien lo pide.** Si hoy te pagan en dólares, dime y cambia a *Sí*. Toca pagos, caja, informes y factura |
| 9 | **Fianza, comisión y tasa por cada tarifa** | ◐ Fianza por política y reserva, comisión por canal, impuestos por propiedad | Chico | **No:** ya está cubierto de otra forma |

### Costes añadidos

| # | Qué hace en HotelDruid | Hoy en AlojaFlow | Tamaño | Recomiendo |
|---|---|---|---|---|
| 10 | **Días en que aplica un coste:** todos, todos menos uno, días alternos, ciertos días de la semana o a escoger. Multiplicar por personas o por un número a escoger | ◐ Por estancia, por noche, por persona, por persona y noche, o porcentaje. Siempre toda la estancia | Mediano | **Sí, más simple:** días de la semana y cuántas veces (desayuno sin domingos, limpieza a media estancia) |
| 11 | **Condiciones de un coste:** solo en ciertas fechas, con ciertas tarifas o unidades, incompatible con otros, con un máximo, solo si la estancia dura tanto o si faltan menos de tantos días | ◐ Obligatorio u opcional, visible o no en el motor, cupo por día | Mediano | **Sí, más simple:** fechas, unidades o tarifas, y noches mínimas |
| 12 | **Descuentos como coste negativo** | ✔ Descuento en la reserva, cupones, precio por semana y por mes | — | **No:** ya alcanza |

### Documentos

| # | Qué hace en HotelDruid | Hoy en AlojaFlow | Tamaño | Recomiendo |
|---|---|---|---|---|
| 13 | **Plantillas propias de documentos** (HTML, RTF, texto, correo) con variables, repeticiones y condiciones: contratos, recibos, hojas para el personal, formularios | ◐ Plantillas de mensajes con variables, textos legales editables y PDF fijos (confirmación, recibo, factura, estado de cuenta) | Grande | **Sí, más simple:** documentos propios en HTML, con variables y una repetición por huésped o por reserva, sin el lenguaje de condiciones ni RTF |
| 14 | **Partes de viajeros de Italia** (Alloggiati Web, ROSS1000) | ✘ España sí, con SES. No hay paquete de Italia | Grande | **Si vendes en Italia:** sería un paquete de país completo, con su investigación |
| 15 | **Clientes que cumplen años hoy** (receta con un documento) | ✘ | Chico | **Si alguien lo pide:** cabría como otro disparador de las automatizaciones |

### Clientes

| # | Qué hace en HotelDruid | Hoy en AlojaFlow | Tamaño | Recomiendo |
|---|---|---|---|---|
| 16 | **Campos propios** en clientes y reservas (texto o sí/no), que salen en los documentos | ✘ | Mediano | **Sí:** cada alojamiento pide un dato distinto (voucher de WiFi, placa, habitación preferida) |
| 17 | **Catálogos editables** de países, regiones, ciudades, documentos y parentescos, por usuario | ◐ Catálogos fijos en cada paquete de país | Mediano | **No:** se mantienen mejor en el paquete |

### Usuarios

| # | Qué hace en HotelDruid | Hoy en AlojaFlow | Tamaño | Recomiendo |
|---|---|---|---|---|
| 18 | **Privilegios por tarifa y por coste:** este usuario solo puede usar estas tarifas | ✘ Roles, casillas y límites, por propiedad; no por tarifa | Mediano | **Si alguien lo pide.** Si entra el 5, conviene revisarlo |
| 19 | **Personalizar por usuario:** columnas, líneas por tabla, formatos, columna de grupo | ◐ Idioma por usuario; columnas que se pueden ocultar | Chico | **No:** ya alcanza |
| 20 | **Palabras a reemplazar** en la pantalla (decir «cabaña» en vez de «habitación») | ✘ | Mediano | **Si alguien lo pide** |
| 21 | **Mensajes internos** entre usuarios | ◐ Bitácora por turno y tareas | Mediano | **No:** para eso ya usan WhatsApp |

### Dinero y ventas

| # | Qué hace en HotelDruid | Hoy en AlojaFlow | Tamaño | Recomiendo |
|---|---|---|---|---|
| 22 | **Punto de venta táctil,** con categorías, cargando a una reserva o a la caja | ◐ Cargos en la reserva y movimientos en la caja, sin pantalla de venta | Grande | **No:** ya lo dejaste fuera del plan. `producto.md` lo tiene «por decidir» y esto lo cierra |
| 23 | **Inventario por habitación y almacén,** con consumo automático (minibar, restaurante) | ◐ Listas de comprobación, sin cantidades | Grande | **No,** por lo mismo |
| 24 | **PayPal** (módulo de pago de HotelDruid) | ✘ Stripe, Mercado Pago, Redsys y Bizum | Mediano | **Si alguien lo pide.** Hace falta una cuenta de pruebas de PayPal |
| 25 | **Captura de tarjeta** para cobrarla después a mano | ✘ | Grande | **No:** guardar tarjetas en un hosting compartido te mete en normas de seguridad que no conviene cargar |

### Estadísticas

| # | Qué hace en HotelDruid | Hoy en AlojaFlow | Tamaño | Recomiendo |
|---|---|---|---|---|
| 26 | **Comparar hasta 5 años** en una gráfica | ◐ Contra el año anterior | Chico | **Si alguien lo pide** |
| 27 | **Estadísticas de un subgrupo de habitaciones** | ◐ Por propiedad y por habitación, sin juntar varias | Chico | **Si alguien lo pide** |

### Web e integraciones

| # | Qué hace en HotelDruid | Hoy en AlojaFlow | Tamaño | Recomiendo |
|---|---|---|---|---|
| 28 | **Calendario público de disponibilidad,** para ver sin reservar, y **tabla pública de precios** | ◐ El motor busca por fechas y la página pública enseña fotos | Chico | **Sí, el calendario:** ayuda a quien todavía no tiene fechas. La tabla de precios, no |
| 29 | **HTML y CSS propios** en las páginas públicas | ◐ Se incrusta en otra página, sin HTML propio | Mediano | **No:** abre una puerta de seguridad en páginas que piden datos |
| 30 | **API** para que otro programa saque documentos, reservas, clientes y caja | ◐ Webhooks de salida, y el contrato entre instalaciones | Mediano | **Si alguien lo pide,** de solo lectura y con llave |
| 31 | **Channel manager** (Booking, Expedia, Octorate por XML) | ✘ iCal | Grande | **No:** ya lo descartaste en el plan |
| 32 | **Importar reservas y huéspedes de un archivo** (Excel o CSV). HotelDruid no lo trae, pero es lo que `CLAUDE.md` llama «importación histórica»: quien viene de otro sistema | ◐ Quien viene de HotelDruid sí pasa todo (subordinar y luego independizar, D-043). Hay importar precios y exportar en cada informe | Mediano | **Sí:** sin esto, quien viene de otro sistema empieza a mano |

### Reglas de asignación

La wiki de HotelDruid duda de lo que hacen las reglas 3 y 4, así que no entran en la lista como tales. La **regla 1**
(varios inmuebles, cierres) ya la cubren las propiedades, los bloqueos y las restricciones; la **regla 2** (tarifa y
apartamento) es el número 5; preguntar antes de asignar «con motivo» se queda fuera con el número 1.

## Lo que ya está y no hay que decidir

- **Reservas:** alta, cambio, cancelación, mover arrastrando, entrada y salida con hora, huéspedes y acompañantes, origen, bloqueos.
- **Tarifas:** temporadas, fin de semana, persona extra, estancia mínima, cierres de llegada y salida, importar precios.
- **Costes:** por estancia, noche, persona o porcentaje, con cupo por día (el aparcamiento de la receta del foro).
- **Clientes:** alta sin reserva, empresas, duplicados y fusión, exportar (informe de huéspedes en CSV), plantillas y mensajes por periodo.
- **Documentos:** facturas numeradas y guardadas, recibos, contratos, SES Hospedajes en España, hoja de limpieza.
- **Usuarios:** roles editables, casillas por usuario, límites, sesiones, dos pasos, auditoría.
- **Dinero:** cajas, turnos, gastos e ingresos, historial de pagos.
- **Estadísticas:** ocupación, ADR, RevPAR, por mes, habitación, canal y método de pago, contra el año anterior, gráficas.
- **Web:** motor con confirmación inmediata o a mano, página pública con fotos, se incrusta, Stripe, Mercado Pago, Redsys y Bizum, iCal.
- **Sistema:** copias de seguridad, actualizaciones, correo por SMTP, seis idiomas, tema, enlace al código fuente.

## Lo que no aplica

- **El año de gestión** (`creaanno`, tablas por año): el diseño nuevo no separa por años.
- **Páginas generadas** con las credenciales de la base dentro, para otro servidor: el motor las sustituye, y sin ese riesgo.
- **PostgreSQL, SQLite, prefijos de tabla, instalar en un equipo sin servidor:** la tecnología ya está decidida.
- **Habitaciones contiguas** (`app_vicini`): HotelDruid mismo lo dejó sin terminar.

## Lo que haría, en orden

1. **Varias tarifas por unidad** (5, 6): lo más grande, y lo que más le cambia al motor.
2. **Cargos con días y condiciones** (10, 11).
3. **Documentos propios y campos propios** (13, 16): van juntos, porque los campos salen en los documentos.
4. **Una versión de cosas chicas** (2, 4, 28).
5. **Importar de un archivo** (32).

Todo lo demás, solo si alguien lo pide o si cambias de opinión. Para decidir basta con los números: «sí a 5, 6 y 16,
no a lo demás».
