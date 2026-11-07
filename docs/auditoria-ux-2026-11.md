# Auditoría de experiencia de uso

**Fecha:** 2026-11-06 · **Sobre:** la rama `mejoras-ux-1.0.34`, a partir de la 1.0.33 · **Decisiones:** D-135 a D-139

## Cómo se hizo y qué no cubre

- **Se abrió de verdad** cada pantalla del panel con un administrador, a 390 px (teléfono) y las principales a 1366 px, sobre una propiedad independiente con la demo. Se midió por programa: desbordes de la página, tamaño de lo que se toca, campos sin etiqueta y tablas más anchas que la pantalla.
- **El instalador** se abrió en su primera pantalla real, con el archivo de la 1.0.33. **No se recorrió** una instalación completa.
- **No se probó** con personas, ni en un teléfono físico, ni con lector de pantalla, ni en Safari: el teléfono se emuló con el ancho de pantalla en Chromium. Una propiedad subordinada no se recorrió en esta pasada.
- **El detector** de la guía de diseño no encontró nada en las vistas: revisa patrones de código, no la experiencia.

Por eso lo de abajo son hallazgos de una revisión experta y de mediciones, no resultados de un estudio con usuarios. El paso siguiente que más información daría es ver a una persona de recepción usarlo diez minutos.

## Criterios

| Tema | Lo que se aplicó | Fuente |
|---|---|---|
| Revisión general | Las 10 heurísticas: estado visible, lenguaje del usuario, control, consistencia, prevenir errores, reconocer en vez de recordar, eficiencia, diseño mínimo, errores que se entienden, ayuda | [NN/g, heurísticas](https://www.nngroup.com/articles/ten-usability-heuristics/) y [en aplicaciones complejas](https://www.nngroup.com/articles/usability-heuristics-complex-applications/) |
| Mensajes de error | Junto a lo que falló, con la causa y qué hacer; nunca «ocurrió un error» | [NN/g, mensajes de error](https://www.nngroup.com/articles/error-message-guidelines/), [en formularios](https://www.nngroup.com/articles/errors-forms-design-guidelines/) |
| Formularios | Nada de reglas secretas: lo que se exige se dice antes; no adivinar | [NN/g, formularios](https://www.nngroup.com/articles/web-form-design/), [carga cognitiva](https://www.nngroup.com/articles/4-principles-reduce-cognitive-load/) |
| Lo que se toca | Mínimo 24 px por norma (AA) y 44 px como objetivo real en el teléfono | [WCAG 2.2, 2.5.8](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html) |
| Tablas ordenables | Botón dentro del título, y `aria-sort` solo en la columna ordenada | [WAI-ARIA, tabla ordenable](https://www.w3.org/TR/2021/NOTE-wai-aria-practices-1.2-20211129/examples/table/sortable-table.html), [patrón de tabla](https://www.w3.org/WAI/ARIA/apg/patterns/table/) |
| Tablas grandes | Barra con buscar, filtros y columnas; primera columna fija al desplazar; columnas por importancia | [LogRocket](https://blog.logrocket.com/ux-design/data-table-design-best-practices/), [UXPin](https://uxpin.com/studio/blog/table-ux), [UX World](https://uxdworld.com/?p=18180) (blogs, no estudios) |
| Rangos de fechas | La final nunca antes de la inicial, y no borrar lo que ya se eligió | [patrón de rango de fechas](https://skillselion.com/skills/thedaviddias/ux-patterns-for-developers/date-range), [fechas en el teléfono](https://uxdesign.cc/design-guidelines-for-mobile-date-pickers-8e8d87026215) (guías, no estudios) |

## Resultado de la medición

| Dimensión | Nota (0–4) | Lo principal |
|---|---|---|
| Accesibilidad | 3 | Ningún campo sin etiqueta. Faltaba lo táctil: «Ver» medía 43×24 px |
| Rendimiento | 3 | No se volvió a medir. El JavaScript nuevo pesa 14 KB sin comprimir y carga diferido |
| Temas | 3 | Colores por variables, con modo oscuro. Lo nuevo los sigue |
| Teléfono | 3 | Una sola pantalla se desbordaba (Mensualidades). Las filas ocupaban una pantalla cada una |
| Coherencia | 3 | Dos paginaciones distintas, y orden solo en algunas columnas |
| **Total** | **15/20** | **Bien; lo débil era el trabajo diario en listas y en el teléfono** |

## Corregido en esta rama

| Prioridad | Dónde | Qué pasaba | Ahora |
|---|---|---|---|
| Bloquea | Sincronización | Con HotelDruid 3.0.8 en español el límite diario salía como SUB-099 («lìmite», con otro acento) y seguía reintentando | Se reconoce (SUB-005) y se reanuda solo. El aviso dice en qué paso falló y qué respondió, y enlaza al historial. Una falla propia es SUB-098 y no culpa a la principal |
| Mayor | Todas las fechas | La salida podía quedar antes de la llegada y abría en el mes de hoy | Sigue a la llegada y abre en su mes, en todos los pares del sistema |
| Mayor | Listas | «Ver» y «Editar» quedaban fuera de la pantalla en una tabla ancha | Se quedan fijos a la derecha |
| Mayor | Reservas en el teléfono | Una reserva ocupaba una pantalla entera | Tarjeta de cuatro renglones, con botones de 44 px |
| Mayor | Reservas y huéspedes | Se buscaba por poco y casi no había filtros | Cualquier palabra, en cualquier orden, también desde el buscador de arriba; 12 filtros en reservas y 4 en huéspedes |
| Mayor | Listas | Orden solo en algunas columnas | Toda columna de la base, sola, sin pedirlo una por una |
| Mayor | Informes y tablas propias | No se podían ordenar | Clic en el título, con los meses en orden de tiempo |
| Mayor | Documentos y plantillas | Las variables eran una lista de nombres sin explicar | Lista junto al texto: dice qué hace cada una, se busca, y se pone al tocarla |
| Mayor | Ficha del huésped | Una lista de texto, sin código | Código, cuentas y reservas por año, con llamar, WhatsApp y correo |
| Mayor | Crear reserva en el teléfono | 3,230 px de alto, y las fechas después de propiedad, tipo y estado | Primero fechas, personas y unidad, con cuántas quedan libres. Lo poco usado va plegado y se abre si trae datos o un error: de 3,230 a unos 2,050 px con todos los módulos encendidos |
| Mayor | Motor: errores | Una lista arriba, lejos de los campos, con textos como «Correo debe ser un correo válido» | Cada campo dice qué hacer, la página vuelve abierta en el formulario y el resumen lleva a cada campo. Sin JavaScript |
| Mayor | Motor: repetir datos | Al volver con un error se perdía la casilla de condiciones; «Volver» y el aviso de «sin lugar» perdían filtros e idioma | Se conserva todo |
| Mayor | Motor: oscuro | Enlaces azul oscuro casi invisibles, e iconos del calendario y botón de archivo en claro | Se leen bien: la página declara `color-scheme` y los enlaces usan el color del texto |
| Mayor | Motor: buscar | La descripción alejaba el formulario y empujaba los resultados | Formulario y resultados primero; las fotos pasan abajo al buscar |
| Mayor | Motor: confirmación | El botón de pagar quedaba debajo de toda la tabla | Primero el pago; y aviso de guardar la página |
| Menor | Aviso de entradas y salidas | Fecha larga fija, días contados a mano | Dos calendarios y formato elegible |
| Menor | Motor | «Hasta 1 personas», «1 personas» | Singular y plural, en los seis idiomas |
| Menor | Motor | Se aceptaba un teléfono con tres dígitos | Pide al menos siete, y dice cómo escribirlo |
| Menor | Motor | No se sabía cuánto faltaba ni si se cobraba al enviar | Pasos arriba, y «todavía no se cobra nada» junto al botón |
| Menor | Motor | Cupón siempre a la vista, botones pequeños en el teléfono, calendario que olvidaba las personas, fecha inicial sin contar la antelación, foco invisible en listas y plegables | Corregidos, con `autocomplete`, `inputmode` y `autocapitalize` en cada campo |
| Menor | «Elegir la primera libre» | Sin fechas o sin lugar no hacía nada | Dice por qué |
| Menor | Mensualidades | La página se desbordaba en el teléfono | Corregido en todas: lo oculto de una tabla ya no se sale |
| Menor | Disponibilidad | «1 noches» | «1 noche» |
| Menor | Hoy | Un número suelto debajo del nombre | «Número 98» |
| Menor | Informe de anticipos | La fecha salía como la guarda la base | Con el formato de cada idioma |
| Menor | Formularios | Un campo obligatorio ofrecía una X para vaciarlo | Solo en los opcionales |
| Menor | Filtros | «Ocultar eliminadas en la principal» salía aunque no hubiera subordinadas | Solo si las hay |
| Pulido | Todas las tablas | Fechas como «3 de feb. de 2027», con filas y columnas de más | «3 feb 2027», y filas más compactas |
| Pulido | Listas | Paginación distinta en cada una | 10, 20, 50 o 100, y al volver a una lista sigue como estaba |

## Pendiente, por módulo

Ordenado por lo que más ayudaría a quien trabaja a diario. Nada de esto está hecho.

| Módulo | Prioridad | Qué falta |
|---|---|---|
| **Actualizaciones** | Mayor | Actualizar desde la pantalla, sin subir el zip. Espera la decisión de abajo |
| **Calendario** | Mayor | En el teléfono las barras se tocan con precisión y arrastrar choca con desplazar. Falta una vista de lista o de día |
| **Hoy** | Mayor | En el teléfono cada llegada es una tarjeta dentro de otra tarjeta: conviene el mismo trato que Reservas |
| **Ficha de la reserva** | Mayor | Mismo tratamiento que la del huésped: título sin «Ver», y lo importante arriba |
| **Instalador** | Menor | Mostrar la contraseña en vez de pedirla dos veces. Falta recorrerlo completo |
| **Informes** | Menor | Paginar en el navegador los informes largos y buscar dentro de ellos |
| **Configuración** | Menor | Cerca de 40 entradas de menú: falta un buscador de ajustes |
| **Motor público** | Menor | Recorrerlo con pasarelas en modo de prueba: en esta pasada solo se vio el botón, sin cobrar. Tampoco se revisó el plano de lugares de un camping con imagen. Los extras no actualizan el total en pantalla hasta reservar |
| **Limpieza, Plano, Tareas** | Menor | Se ven bien en el teléfono; falta probarlas con quien limpia |
| **Propiedad subordinada** | Menor | Recorrerla entera, con el aviso de SUB-099 a la vista |
| **Accesibilidad** | Menor | Probar con lector de pantalla y con teclado de punta a punta |

## La actualización en línea (por decidir)

Hoy se sube un zip por partes. La pieza difícil ya existe: comprueba la firma, hace copia y vuelve atrás si falla. Lo recomendado es añadir solo lo que falta:

1. **Avisar:** una vez al día se pregunta a GitHub cuál es la última versión, sin mandar ningún dato. El super administrador ve el aviso con lo que cambia.
2. **Bajar:** con el botón «Actualizar ahora» se baja el zip por pedazos, como ya hace el instalador, con barra de avance: en un hosting compartido una descarga larga la cortaría el servidor.
3. **Aplicar:** el mismo camino de siempre (firma, copia, cambio y vuelta atrás), con la confirmación de la contraseña.

**La firma es lo que da seguridad, no el sitio de donde baja.** Se conserva subir el zip a mano para el servidor que no sale a internet. Nunca se actualiza sola al principio: primero un clic, y más adelante una opción para las de seguridad.
