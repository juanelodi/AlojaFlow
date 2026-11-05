# Cambios

Numeración: 1.0.1, 1.0.2 … 1.0.99, y después 1.2 (ver `docs/decisiones.md`, D-030).

## 1.0.28 — 2026-11-05

**Calendario**
- **Las fechas se cambian arrastrando:** toma el borde izquierdo de una barra para cambiar la llegada, o el derecho para cambiar la salida.
- El total no cambia solo. Si con la tarifa fuera otro, te lo dice y un botón lo aplica, con sus cargos.
- Lo de un grupo se sigue cambiando desde el grupo, y a quien ya llegó solo se le puede cambiar la salida.

**Camas extra**
- En cada unidad, cuántas personas más caben con cama extra. El motor y las reservas ya cuentan con ellas.
- Un cargo del catálogo puede ser «solo por cama extra»: se cobra una vez por cada cama que se use.

**Calendario público (Motor de reservas → Ajustes)**
- Para quien todavía no tiene fechas: una página con los días que tienen lugar, mes por mes. Pulsar uno lleva a sus precios.
- **Llega apagado:** solo dice si hay lugar o no, pero deja ver cómo va la ocupación.

**Demostración:** dos dobles con cama extra, y el calendario público prendido.

**Sin verificar:** arrastrar solo se probó con ratón. En un teléfono se siguen cambiando desde la reserva.

## 1.0.27 — 2026-11-05

**Documentos propios y campos propios.** Es un módulo nuevo y llega apagado: se prende en Sistema → Módulos.

**Campos propios (Configuración → Campos propios)**
- Lo que quieras guardar de un huésped o de una reserva y el sistema no pide: la placa del coche, si es vegetariano. Texto, o sí o no.
- Se capturan en su formulario y en su ficha, y salen en la ficha, en los documentos y en los mensajes, con su variable.
- **También en una propiedad subordinada:** no se escribe nada en la principal.
- Entran en lo que se exporta y se anonimiza de un huésped, y pasan con él al fusionar.

**Documentos propios (Configuración → Documentos propios)**
- Un contrato, una hoja de registro, la lista de llegadas: los escribes como cualquier texto, en cada idioma, con variables como {huesped} o {llegada}.
- **Lo que se repite se repite solo:** una fila de tabla con datos de un huésped sale una vez por cada huésped. Lo mismo con cargos, pagos y, en uno de varias reservas, con cada reserva.
- Se bajan en PDF: el de una reserva, desde esa reserva, en «Más acciones»; el de varias, desde la lista, eligiendo fechas y si son las que llegan, salen o se hospedan.
- Tres de partida: contrato, hoja de registro y lista de llegadas. **El contrato es solo un punto de partida: no lo ha revisado ningún abogado.**
- El número de identificación solo sale completo para quien tiene permiso de ver huéspedes.

**Demostración:** trae los documentos de partida y dos campos propios.

**Sin verificar:** no se ha probado con un alojamiento real, ni impreso en papel.

## 1.0.26 — 2026-11-05

**Cargos con días y condiciones.** Un cargo ya no tiene que cobrarse siempre igual. En Dinero → Cargos adicionales, cada uno tiene una parte nueva, «Cuándo se cobra», y si la dejas vacía todo sigue como antes.

**Días**
- **Solo ciertas noches de la semana**, en los cargos por noche: un desayuno que no se sirve los domingos.
- **Cada cuántas noches:** un cambio de sábanas cada 3 noches se cobra 2 veces en una estancia de 7.

**Condiciones**
- **Solo en ciertas fechas.** Un cargo por noche cuenta las noches que caen dentro; los demás se cobran si alguna noche cae dentro.
- **Solo si la estancia dura al menos tantas noches.**
- **Solo con ciertas tarifas o en ciertas unidades:** un desayuno obligatorio que solo va con la tarifa «con desayuno».

**Cómo se aplican**
- Un cargo obligatorio solo se suma cuando cumple sus reglas.
- En el motor, un cargo opcional solo se ofrece si cumple sus reglas, y se dice qué noches cuenta.
- En el panel, quien atiende puede añadirlo a mano de todos modos: entonces se cobra por toda la estancia.

**Demostración:** un cargo de limpieza a media estancia, cada 3 noches.

**Sin verificar:** no se ha probado con un alojamiento real ni en un teléfono de verdad.

## 1.0.25 — 2026-11-05

**Varias tarifas por unidad:** una misma habitación se puede vender de varias maneras, como «con desayuno» o «no reembolsable». Es lo primero de lo que elegiste del sistema anterior.

**Varias tarifas (Configuración → Tarifas y Unidades)**
- Cada unidad tiene su tarifa principal y puede tener otras. Se asignan desde la unidad, o desde la tarifa marcando varias unidades a la vez.
- Una tarifa puede pedir un mínimo y un máximo de personas.
- **En el motor** sale una opción por tarifa, la más barata primero, y solo las que admiten a esas personas.
- **Al crear una reserva**, si la unidad tiene más de una, eliges con cuál. Si las personas no son las de la tarifa, te avisa y puedes guardarla igual.
- La reserva recuerda con cuál se vendió: si después retiras la tarifa o cambias la reserva de unidad, se queda con la suya hasta que elijas otra.

**Tarifas que salen de otra**
- «Basada en otra tarifa»: cobra con los precios y las temporadas de aquella, más un porcentaje, un importe por noche o los dos. Si cambias aquella, esta cambia sola.
- No se puede borrar una tarifa de la que salen otras, ni importarle precios a la que sale de otra.

**Condiciones por tarifa**
- Cada tarifa puede llevar su política de cancelación. La de un canal sigue mandando sobre ella, y ella sobre la general.
- **Esa política también decide el anticipo del motor:** su depósito.
- Política **no reembolsable:** si cancelan, en cualquier momento, se cobra el total.
- En el motor, las condiciones de cada tarifa se leen antes de reservar.

**Informes:** uno nuevo, «Por tarifa». **Demostración:** las dobles también se venden «no reembolsable» y «con desayuno».

**Sin verificar:** el anticipo del 100 % con una pasarela solo se ha probado con respuestas simuladas. No se ha probado en un teléfono de verdad.

## 1.0.24 — 2026-11-05

**Propietarios: si administras unidades de otras personas.** Es un módulo nuevo y llega apagado: se prende en Sistema → Módulos. Con esta versión termina el plan de funciones.

**Dueños y su acuerdo (Dinero → Propietarios)**
- La ficha del dueño, con su comisión, si es sobre lo que queda tras el portal o sobre todo el ingreso, y un cargo fijo al mes.
- Sus unidades, y una comisión distinta para la que la tenga. Si una unidad cambia de dueño, lo que ya mandaste no cambia, pero te avisa que difiere.
- En cada cargo del catálogo puedes decir que es tuyo y no del dueño, como la limpieza.

**Gastos (Dinero → Gastos e ingresos)**
- Un gasto se le puede cargar a un dueño y a una unidad, con su comprobante. Se descuenta en su estado de cuenta de ese mes.

**Estados de cuenta (Dinero → Estados de cuenta)**
- **Se generan solos para el mes que digas**, para todos los dueños: ingresos, comisión de portales, tu comisión, gastos, cargo fijo y lo que le toca. Solo cuenta lo confirmado.
- **Una estancia entre dos meses se reparte por noches**, y los meses suman justo lo cobrado. Los impuestos no cuentan como ingreso del dueño.
- **Borrador, revisión y envío:** al mandarlo queda en su portal y le llega un aviso. Después ya no cambia; si cambió una reserva o se anotó un gasto, te lo avisa y puedes volver a abrirlo.
- Anotas lo que le pagaste, con fecha y referencia. **AlojaFlow no mueve dinero.** Se baja en PDF y CSV.

**Su portal**
- Un enlace y una clave que cambia la primera vez, como el coordinador de un grupo. No es un usuario del panel.
- Ve los próximos 30 días de sus unidades, sus estancias, ocupación e ingresos del mes, sus gastos con comprobante y sus estados de cuenta.
- **Nunca ve quién se queda en su unidad**, ni nada de otras unidades.

**Estancias del dueño**
- Desde su portal se aparta sus fechas, si el acuerdo lo deja, y las libera mientras no hayan empezado. En los dos casos te llega un aviso.
- En el panel, un bloqueo puede ser «estancia del dueño». Sale en su estado de cuenta, sin ingreso.

**Demostración:** las cabañas son de un dueño ficticio, con su estado de cuenta del mes pasado.

**Sin verificar:** nada de esto lo ha revisado un contador. **No hace el lado fiscal:** ni la factura de tu comisión, ni retenciones, ni los formularios de cada país: confírmalo con el tuyo. No lleva contabilidad fiduciaria ni cuentas separadas por dueño. El correo al dueño se revisó en la cola de envíos, sin mandarlo. No se ha probado con un dueño real ni en un teléfono de verdad.

## 1.0.23 — 2026-11-05

**Campings.** Es un módulo nuevo y llega apagado: se prende en Sistema → Módulos. Todo es opcional, y varias cosas sirven también a cabañas y casas de huéspedes.

**Qué tiene cada lugar (en Unidades)**
- Tipo, luz con sus amperes, agua, drenaje, acceso, largo máximo, mascotas y accesibilidad. Se le enseña al huésped.
- **El motor filtra por eso:** qué necesita y cuánto mide su equipo. Lo que no cabe no se le ofrece.
- En Estados Unidos, los largos van en pies; en los demás países, en metros.

**El equipo del huésped**
- Tipo, largo, vehículos y placas, en el formulario de reservar si lo prendes en Motor de reservas, y en la reserva del panel.
- Si no cabe en el lugar, el motor no se lo ofrece y el panel te avisa. Las placas quedan cifradas, salen en la copia de sus datos y se borran al anonimizar.

**Elegir el lugar en el plano**
- Los planos de Operación → Plano se pueden publicar en el motor: los lugares libres en sus fechas salen en color, y hay una lista con lo mismo para quien no quiera atinarle a un punto en el teléfono.
- **De lo ocupado solo dice que no se puede elegir. Nunca quién está.** Solo se publica el dibujo del plano.
- Puedes cobrar por elegir: uno de tus cargos, que se suma solo a quien elige un lugar concreto.

**Lecturas de luz, agua y gas (Operación → Lecturas de medidor)**
- Capturas en una sola pantalla lo que marca cada medidor, y ves lo que se va a cobrar antes de guardar.
- **Se cobra a quien está en el lugar, y nunca lo que gastó el de antes:** la primera lectura de cada huésped es su punto de partida. Conviene leer al llegar y al salir.
- Se suma a su reserva. **Con calendario de cobros, va como un pago aparte**, que se factura tal cual.
- Si te equivocas, quitas la última lectura y lo cobrado se devuelve al total. Lo ya facturado no.

**Temporada y residentes (Operación → Residentes)**
- Un precio de temporada completa en cada temporada: quien se queda toda paga eso.
- La lista de quienes se quedan un mes o más, con lo que deben, lo que toca pagar y si firmaron el reglamento. Si falta, un botón le manda el enlace por WhatsApp.
- **Escribirles a todos:** Mensajes por periodo ahora también tiene «Residentes».

**Extras con cupo (en Cargos)**
- Bicicletas, kayaks, leña: cuántos hay cada día. El motor ofrece lo que queda y no vende de más, ni con dos huéspedes a la vez.
- En el panel solo te avisa: un caso especial lo decides tú.

**Corregido:** editar una reserva y guardarla borraba cualquier cargo que no viniera del catálogo. Hasta hoy no había ninguno, pero las lecturas lo serían.

**Sin verificar:** no se ha probado en un camping de verdad, ni el plano en un teléfono de verdad con un dibujo real. No hay lectura automática de medidores: se capturan a mano. No hay modo sin conexión, como acordamos. Lo cobrado por consumo puede tener reglas o impuestos propios en tu lugar: confírmalo con tu contador.

## 1.0.22 — 2026-11-05

**Estancias largas y mensualidades.** Es un módulo nuevo y llega apagado: se prende en Sistema → Módulos.

**Precio por semana y por mes (en cada tarifa)**
- Por semana desde 7 noches y por mes desde 28. **Un mes completo cuesta lo mismo con 28 o con 31 noches**; lo que sobra se cobra a treintavos.
- **Una temporada sin estos precios cobra su precio normal**, también en una estancia larga: así el precio del mes no regala la temporada alta. Si quieres, cada temporada puede tener los suyos.
- El motor los enseña y dice cuál aplicó. El formulario de la reserva, también.

**Calendario de cobros (en cada reserva)**
- «Generar calendario» reparte el total en pagos por mes o por semana, desde la fecha que digas. El último cubre lo que no llene un periodo.
- Se puede ajustar a mano: cambiar un pago, agregar otro como un depósito, quitarlo. Si deja de sumar el total, lo avisa.
- **Lo pagado se aplica solo a los primeros en vencer.** No hay que marcar nada: registras el pago como siempre y ves qué está pagado, parcial, atrasado o pendiente.

**Mensualidades (Dinero → Mensualidades)**
- Lo vencido y lo que vence en 30 días, de todas las estancias largas, con un botón de WhatsApp con el recordatorio ya escrito.
- **Recordatorios por correo con un clic:** 3 días antes y al día siguiente de vencer. Salen de Automatizaciones y Plantillas, donde puedes cambiar días y texto. Solo a quien debe.

**Una factura por pago**
- Cada pago se puede facturar aparte, con sus fechas y con los impuestos y cargos en proporción. No se puede facturar dos veces, ni pasar del total. Lo facturado ya no se puede cambiar: generar otra vez reparte solo lo que falta.

**El huésped (en Mi reserva)**
- Ve sus pagos y su estado, paga en línea lo que toca (lo vencido o el siguiente) y manda su comprobante de transferencia. Mi reserva ahora también dice cómo pagar: antes solo lo decía la página del motor.

**Impuestos:** un campo nuevo, «No cobrar desde (noches)», para los lugares que no cobran un impuesto en una estancia larga. Viene vacío: lo que aplique en tu lugar, confírmalo con tu contador.

**Demostración:** trae una estancia de tres meses con un pago vencido, para verlo funcionando.

**Sin verificar:** no se ha probado con un cobro real ni con un PAC: las facturas de cuota se revisaron en el XML y el PDF, sin timbrar. El correo de los recordatorios se revisó en la cola de envíos, sin mandarlo. No se ha probado en un teléfono de verdad ni con un huésped real. Nada de esto lo ha revisado un contador: confirma con el tuyo cómo facturar por pagos en tu país.

## 1.0.21 — 2026-11-05

**Lo que ve el huésped: una página con fotos, «Mi reserva» y una guía digital.**

**Página pública (Configuración → Página pública y Fotos)**
- La misma de reservar, ahora con **fotos** de la propiedad y de cada tarifa, que se recorren con el dedo.
- **Descripción, dónde está y qué ofrece**, en los seis idiomas; lo que no traduzcas sale en el idioma de la propiedad.
- **Botones de WhatsApp, llamar, correo y mapa**, y las **condiciones de cancelación** antes de reservar.
- **Sirve aunque no tengas sitio web:** compartes el enlace, y los buscadores entienden qué es.
- Las fotos se reducen y se les quita la ubicación y lo demás que traigan escondido. En el teléfono baja la pequeña.

**Mi reserva (módulo nuevo, con la guía)**
- Una página para cada huésped, sin cuenta, con un enlace personal: `{enlace_mi_reserva}` en tus plantillas. Deja de valer una semana después de la salida.
- Ve su reserva, lo que debe, su ficha de pago, la confirmación en PDF, el formulario de llegada y la guía.
- **Puede pedirte algo:** otra hora, otras fechas, una cuna. Te llega como nota de la reserva, aviso en el inicio y correo. **No cambia nada solo.**
- **Puede cancelar solo si tú lo prendes**, y solo sin pagos hechos, antes del día de llegada y dentro de la cancelación gratuita. En lo demás te escribe.

**Guía digital (Configuración → Guía digital)**
- Por partes: Wi-Fi, reglas, cómo llegar, cómo usar el equipo, recomendaciones, contactos. Para todas las unidades o para una, en los seis idiomas.
- **Cada parte sale cuando sirve:** antes, durante o al salir.
- **Un código QR por unidad**, para imprimirlo y dejarlo a la vista.
- **Lo delicado, como la clave de la puerta, nunca sale en el QR**, que cualquiera puede fotografiar: solo en Mi reserva, mientras dura la estancia y, si quieres, cuando ya no deba nada.

**Corregido:** en un enlace de pago, Chrome podía bloquear el paso a la página de la pasarela, por una regla de seguridad de la propia página. Salía desde la 1.0.16, y no se había visto porque nunca se ha probado con un cobro real de Stripe o Mercado Pago.

**Sin verificar:** no se ha probado en un teléfono de verdad ni con un huésped real. Los correos al alojamiento se revisaron en la cola de envíos, sin mandarlos. El lector de QR de un teléfono no se probó: el código se comprobó decodificándolo en el equipo.

## 1.0.20 — 2026-11-05

**España: comunicar a SES.Hospedajes, tasa turística, Redsys y Bizum.** Los detalles y las fuentes están en `docs/paises/espana.md`.

**SES.Hospedajes (Operación → Registro de viajeros)**
- Te conectas con las credenciales del servicio web del Ministerio del Interior. Solo se guardan si funcionan, y la contraseña va cifrada.
- Cada reserva confirmada y cada entrada se comunican solas en unos minutos. El plazo legal es de 24 horas.
- Si se cancela, se anula lo comunicado. Si cambian las fechas, las personas o los documentos, se anula y se manda otra.
- **Lo que falta se dice antes de mandarlo:** qué persona y qué dato, y se corrige ahí mismo. Un DNI o NIE con la letra mal no se manda. El municipio del INE se busca solo con la ciudad y el código postal.
- Si la autoridad rechaza algo, o no contesta, o se pasa el plazo, sale en los avisos del inicio.
- Lo anterior a conectarte no se manda.
- Se puede probar antes en su ambiente de pruebas, que no cuenta como comunicado.

**Tasa turística (Impuestos → Proponer por lugar)**
- **Cataluña** (Barcelona con su recargo y el resto) e **Illes Balears**, por tipo de alojamiento, con las tarifas de cada periodo cotejadas con la ley.
- Los impuestos por persona y noche ahora saben de temporada baja, de estancias largas y de tarifa fijada al reservar.
- **En la factura van aparte**, con las unidades y la tarifa, y sin IVA encima. Antes se mezclaban con el hospedaje.
- **Informes** dice cuántas estancias se gravaron y cuántas quedaron exentas. **Archivo para la ATC** genera el CSV de la carga masiva del semestre.

**Cobros**
- **Redsys**, el TPV de tu banco, y **Bizum** con el mismo terminal, en Motor de reservas, si cobras en euros. Se paga en su página: los datos de la tarjeta no pasan por AlojaFlow.

**Facturas**
- Exportar **para tu programa de facturación**: una fila por factura y tipo de impuesto. **No es VeriFactu:** AlojaFlow no genera el registro encadenado. Se hará cuando el aplazamiento anunciado esté en el BOE y lo revise un asesor.

**Formulario de llegada:** en España ya no se le exige documento a un menor, que no tiene.

**Errores de fechas corregidos, que no eran de España.** La fecha de llegada no tiene zona horaria y se comparaba con «hoy» en la zona de la propiedad:
- **Al este de Greenwich no se podía registrar la entrada el día de llegada.** Decía que era antes de tiempo. Toda Europa.
- **Al oeste, como en México, la cancelación gratuita contaba un día de menos:** una reserva que todavía estaba en plazo se cobraba.
- Un mantenimiento recurrente que tocaba hoy saltaba al siguiente, y el último día del horizonte de limpieza podía quedarse fuera.

**Comprobado de verdad:** **un cobro completo en el entorno de pruebas de Redsys**, con su comercio y su tarjeta de pruebas, desde el botón de la reserva hasta quedar confirmada, con su firma de vuelta y su código de autorización. Con Bizum, su entorno abre sin error, sin llegar a pagar. Los XML de SES validan contra los esquemas XSD 3.1.3 del Ministerio, y sus dos ambientes responden y su certificado se comprueba con la cadena que va en la instalación.

**Sin verificar:** **nunca se ha mandado nada a SES.Hospedajes con credenciales de verdad:** el servicio está simulado con el formato de los ejemplos de su documento. Tampoco se ha cobrado con un comercio real de Redsys ni llegado a pagar con Bizum, y el aviso de Redsys no llega a un equipo local. Los códigos de catálogo de SES vienen de repositorios públicos que coinciden, no del documento oficial. La tarifa de Baleares es la de la ley consolidada: el Govern anunció cambios que no se pudieron confirmar. **No lo ha revisado un asesor.**

## 1.0.19 — 2026-11-05

**México: timbrar facturas de verdad, y cobrar en OXXO, a meses y por transferencia.**

**Facturas**
- **Conectar PAC**, en Datos fiscales: pegas el token de tu cuenta de SW Sapien. Solo se guarda si funciona, va cifrado y te dice cuántos timbres te quedan. Cada razón social usa su propia cuenta. El certificado de sello digital (CSD) se queda en SW: AlojaFlow no lo recibe.
- **Timbrar** guarda el XML y el folio fiscal que devuelve el PAC, y rehace el PDF, que ya no dice «sin timbrar»: lleva el código QR, los sellos, los certificados, la cadena original y la dirección para verificarla en el SAT.
- **Cancelar una timbrada** pide uno de los motivos del SAT (01 a 04). El 01 pide además cuál la sustituye, y solo deja elegir una ya timbrada. Se guarda el acuse. El PDF de una cancelada lo dice arriba, en rojo; también el de un recibo.
- **Dos clics no timbran dos veces.**

**Cobros en pesos**
- **OXXO, meses sin intereses y transferencia SPEI con Stripe.** Se prenden en Motor de reservas, en una sección que solo aparece si tu moneda es el peso, y además hay que activarlos en tu panel de Stripe. Lo que Stripe enseña depende de tu cuenta.
- **Meses sin intereses te cuestan aparte:** según Stripe, 5 % a 3 meses (mínimo 300 pesos), 7.5 % a 6 (600), 10 % a 9 (900), 12.5 % a 12 (1,200), 17.5 % a 18 (1,800) y 22.5 % a 24 (2,400).
- **Las noches esperan.** Quien elige OXXO o transferencia ya no pierde su reserva a los 30 minutos: sigue apartada lo que dure su ficha, que ve en la página de su reserva. Se confirma sola cuando llega el dinero, y se anota como lo que fue: transferencia, u «otro» si pagó en la tienda.
- **Sin apartar de más.** Una ficha de OXXO no dura más que lo que falta para llegar, y las noches se sueltan a más tardar al terminar el día de llegada. Si la ficha vence sin pagarse, se sueltan al poco, y puede volver a intentarlo.
- **Mercado Pago hace lo mismo** con sus fichas de OXXO y transferencias, que ya ofrecía y que hasta ahora soltaban la reserva a los 30 minutos.
- **Una reserva sin plazo no empieza a caducar por esto:** si la hizo recepción y mandó un enlace de pago, sigue como estaba.

**No está igual que en la 1.2.3:** se conecta SW Sapien por su servicio de emisión, sin subir el certificado a AlojaFlow. Otro PAC es otra clase detrás de la misma interfaz.

**Límites que conviene saber:** quien ya tiene una ficha no puede cambiar a tarjeta desde su página hasta que venza: dos a la vez se podrían pagar las dos. Para SPEI, Stripe guarda un cliente con el nombre y el correo del huésped; al anonimizarlo, AlojaFlow olvida el vínculo, pero ese registro sigue en Stripe y se borra desde su panel. Cancelar se pide al SAT y queda como cancelada: si el receptor tiene que aceptarla, esa respuesta no se consulta todavía. Siguen fuera la factura global, el complemento de pago y que el huésped se facture solo.

**Sin verificar:** **nunca se ha timbrado de verdad ni se ha cobrado con estos medios.** SW, Stripe y Mercado Pago se programaron con sus documentos y se probaron con respuestas simuladas, porque no hay cuentas de prueba. Lo que sí se comprobó: las rutas de timbrar, cancelar y saldo de SW existen en sus dos ambientes, y sin token contestan el error de credenciales con el formato que se esperaba; un CFDI generado valida contra los XSD del SAT, salvo el número de certificado, que pone el PAC, y el PDF se revisó a ojo con sellos de tamaño real. Ese XSD no revisa las reglas de negocio del SAT: esas solo se ven al timbrar. Antes de usarlo en real, una factura y un cobro en el ambiente de pruebas de cada uno.

## 1.0.18 — 2026-11-05

**Grupos completos, con su propia página para el coordinador, y comprobantes de transferencia también en la reserva.**

**Lo que hay en grupos**
- **Precio del grupo:** la tarifa de cada unidad, un total que se reparte por noches, o un precio por noche según cuántos caben. Una gratis cada N (las más baratas), descuento y extras. La suma de las reservas es siempre el total del grupo.
- **Tentativo, confirmado o cancelado.** Cancelar el grupo cancela sus reservas. También se cancela, se cambia de unidad o de fechas cada habitación por separado, solo si lo nuevo está libre.
- **Al crear el grupo** se ve qué unidades están ocupadas en esas fechas y por quién, con «Seleccionar todas las libres».
- **Calendario de pagos:** anticipo y el resto en pagos iguales, o a mano. Se ve qué está pagado, parcial, pendiente o atrasado, y avisa si no suma el total.
- **Lista de huéspedes:** quién va en cada habitación. Se descarga a Excel y se vuelve a cargar pegándola, hay lista de llegada para imprimir, y con fecha límite puede liberar sola lo que siga sin responsable.
- **Copiar el grupo a otras fechas**, con sus precios, sin pagos ni lista.
- **Seguimiento de grupos:** pagos por confirmar, pagos vencidos o por vencer con recordatorio por WhatsApp, listas incompletas, quién llega esta semana y mensajes sin leer.
- **En el calendario**, las reservas de un grupo llevan contorno punteado de su color, y dicen quién va en esa habitación.
- **Página del coordinador:** con un enlace secreto y una clave que se muestra una vez y puede mandarse por WhatsApp. Él cambia la clave al entrar, y desde ahí nadie más la ve. Ve sus cuentas con barra de avance, su calendario y los datos para pagar; llena quién va en cada habitación, sin pasar de su capacidad; avisa por WhatsApp a cada responsable; sube sus pagos con comprobante, y se escribe con el alojamiento.
- **Contrato del grupo:** una plantilla que se llena con los datos del grupo, más el aviso de privacidad. Sin aceptarlos no se usa la página, y se guardan el nombre, la fecha, la dirección IP y una copia en PDF. Si cambia el texto (por ejemplo, el precio), hay que aceptarlo otra vez. Se crea como borrador en Documentos legales: **revísalo antes de publicarlo.**
- **Seguridad de esa página:** cinco errores bloquean la entrada 15 minutos y veinte por conexión la bloquean una hora, siempre con el mismo mensaje. Caduca unos días después de la salida y se puede desactivar.

**Comprobantes de pago**
- **También en la página de la reserva del motor:** si dijiste cómo pagar, el huésped sube la imagen de su transferencia. Mientras la revisas, la reserva no caduca. Se puede apagar en Motor de reservas.
- **En Dinero → Comprobantes de pago** están todos los que faltan por revisar. Confirmar lo vuelve un pago normal, en la caja que elijas; rechazar pide un motivo, que el huésped lee en su página y le llega por correo, y le da otra vez su plazo para mandar otro.
- **Solo imágenes** JPG, PNG o WebP de hasta 5 MB. Se mira qué son de verdad, no cómo se llaman, y se vuelven a dibujar: así no llega nada escondido ni la ubicación de la foto. Se guardan cifradas, y se borran 24 meses después de la salida, igual que los nombres de la lista y los mensajes.

**No está igual que en la 1.2.3:** los pagos del grupo se reparten en sus reservas en lugar de llevarse aparte, para que la caja y los informes sigan cuadrando. Un comprobante en PDF no se acepta, porque no se puede volver a dibujar: que manden una foto.

**Sin verificar:** nada de esto se probó en un hosting real ni con un coordinador de verdad. Los correos se revisaron en la cola de envíos, sin mandarlos; el WhatsApp es un enlace que abre la conversación. El contrato de partida no es asesoría legal.

## 1.0.17 — 2026-11-05

**Herramientas para ordenar los datos: fichas duplicadas, anonimizar por antigüedad, exportar facturas, regiones y número de registro. Y la base que se actualiza sola si subes una versión a mano.**

**Lo que hay**
- **Fichas duplicadas** (Reservas): fichas que pueden ser la misma persona, por nombre sin acentos, correo, teléfono o documento. Eliges con cuál te quedas y las demás se juntan ahí. No se puede deshacer, y la pantalla lo dice antes.
- **Anonimizar por antigüedad:** quienes llevan N años sin venir (5 por omisión), ni de titular ni de acompañante, y sin nada reservado a futuro. Se ve a quién alcanza antes de hacerlo, y pide tu contraseña. Es para quien administra.
- **Tipo de cliente en propiedades independientes:** en la ficha del huésped escribes datos como «Tipo: VIP», y si coinciden con Colores de clientes, sus reservas se marcan en el calendario.
- **Exportar facturas:** ahora con correo, código postal, llegada, salida, pagado y pendiente, y en dos formatos: Excel en español (punto y coma y coma decimal) o CSV estándar. Pagado y pendiente son los de las reservas, y pueden no coincidir con el documento.
- **Facturar a la empresa de la reserva:** si la reserva es de una empresa, la factura propone sus datos.
- **Días y franja de sincronización:** en la principal puedes elegir qué días y a qué horas se copia, en la hora de la propiedad, también de noche (de 22:00 a 06:00). Copiar a mano no tiene ese límite.
- **Estado o región de la propiedad:** de una lista con códigos ISO en México, Estados Unidos, Canadá, España, Francia, Italia, Alemania y Portugal; los demás países la escriben. Con ella, el catálogo de impuestos de hospedaje ya propone el tuyo. Y en el domicilio del huésped la lista sugiere los estados de su país.
- **Número de registro o licencia** (Registros turísticos), de la propiedad o de una unidad, con vigencia. Se enseña en las páginas públicas y en los documentos, y avisa 30 días antes de vencer. También responsable y autoridad para quejas, que algunos países piden tener a la vista.
- **Si subes una versión a mano** (por FTP, sin usar Actualizaciones), al abrir la primera página se hace una copia, se pone al día la base y se sigue. Un formulario que iba a medio camino te lleva al inicio, porque no se procesó. Si no se puede, sale una página que lo explica.
- **Instalable en el teléfono:** «Agregar a la pantalla de inicio» la abre como aplicación, con su icono. Necesita conexión: no guarda datos para sin conexión, porque datos viejos en una recepción son peores que no abrir.
- **Tema:** al imprimir, siempre claro. Cada usuario puede elegir los colores de los estados del calendario en su perfil, y la letra se ajusta sola para que se lea. La barra de guardar queda fija al crear y editar una reserva.

**Sin verificar:** nada de esto se probó en un hosting real; subir a mano se probó en una instalación de este equipo. Instalar en el teléfono se revisó por el manifiesto y los iconos, no en un teléfono de verdad. Las regiones de Europa son solo esos cuatro países, y si necesitas otro, se escribe.

## 1.0.16 — 2026-11-05

**Lo que se usa todos los días: Hoy y el cierre más completos, mensajes por periodo, depósitos, bloqueos, restricciones, enlaces de pago, automatizaciones que deciden y encuesta a los huéspedes.**

**Llega apagado:** Opiniones es un módulo nuevo. Préndelo en Sistema → Módulos.

**Lo que hay**
- **Hoy** suma lo pendiente de cobro del día y lo que falta por limpiar.
- **Cierre del día:** quiénes faltan (llegadas sin entrada, salidas sin registrar o con saldo, sin confirmar que llegan en 3 días), con enlace a cada reserva, y la caja por caja y método.
- **Mensajes por periodo:** eliges fechas y plantilla, y por cada reserva tienes WhatsApp, copiar y correo. Nada se manda solo. Y **variables propias** en las plantillas, como `{wifi}`.
- **Depósitos y no-shows:** los depósitos que faltan, con recordatorio por WhatsApp y los vencidos en rojo; quién no llegó en los últimos 7 días; cuánto cuesta cancelar hoy una reserva, y el texto de cada política para copiar. Nada se cancela ni se cobra solo.
- **Bloquear fechas** desde el calendario: varias unidades a la vez. Si una ya tiene algo, se salta y te avisa. Lo que una principal HotelDruid tiene cerrado también se ve como bloqueo.
- **Importar precios** desde un CSV o pegando de Excel, con la plantilla de los precios de hoy y revisión antes de importar.
- **Avisos en el Resumen:** quien salió esta semana con saldo, noches con dos reservas en una subordinada y propiedades con monedas distintas.
- **Restricciones de venta:** cerrar llegadas o salidas, días de entrada, estancia máxima, anticipación máxima y noches de preparación. El motor las cumple siempre; a recepción solo se le avisa.
- **Enlaces de pago por importe libre** desde la reserva: un anticipo, un extra, un daño. Se pagan una vez, vencen, y el cobro entra a la caja que elijas.
- **Automatizaciones con condiciones:** saldo, canal, datos de llegada y documentos aceptados. Pueden crear una tarea o una nota en la bitácora en lugar de un correo, y los datos de acceso pueden esperar a que paguen.
- **Encuesta a los huéspedes:** una plantilla con el enlace y la automatización apagada, para que la revises. A todos se les pregunta lo mismo y a todos se les ofrece tu página de reseñas.

**Sin verificar:** los enlaces de pago se probaron con respuestas simuladas de Stripe, no con una cuenta real; ni en un hosting real. Los cierres de la principal se probaron con copias armadas para la prueba: la copia de demostración no trae ninguno.

## 1.0.15 — 2026-11-05

**La caja completa, también de la principal, y los informes con gráficas y reportes nuevos.**

**Llegan apagados:** Bitácora y Empresas son módulos nuevos. Préndelos en Sistema → Módulos.

**Lo que hay**
- **La caja de la principal, uno por uno:** en una subordinada ya se ven los cobros de cada reserva, los gastos e ingresos y las cajas, de HotelDruid o de otro AlojaFlow, en el Cierre del día, los informes y Gastos e ingresos. Se ven, no se cambian. Al pasar a independiente se quedan como propios.
- **Informes nuevos:** resumen mes por mes con ocupación, ADR y RevPAR; lo que se vendió (pick-up); pronóstico de ocupación; cuentas por cobrar, con antigüedad; ingresos contra gastos; impuestos cobrados; limpieza por persona; y grupos.
- **Gráficas de barras** en los informes donde ayudan, y **PDF e impresión**, además del CSV. Si hay dos monedas no se dibuja: no se pueden comparar. Queda la tabla.
- **Turnos de caja:** abres con un fondo, al cerrar cuentas el efectivo y se calcula el faltante o sobrante. El acta se guarda y no se edita, y el Cierre del día muestra los arqueos. Al cobrar se propone la caja de tu turno.
- **Bitácora:** lo que un turno le deja dicho al siguiente. Lo importante sale en Hoy hasta que alguien lo resuelva. Se borra a los dos años.
- **Empresas y agencias:** ficha con identificación fiscal, comisión y días de crédito. Se eligen en la reserva y tienen su estado de cuenta.
- **Reglas de impuesto:** vigencia, solo las primeras noches y edad mínima. Un canal puede marcar que **el portal ya cobra los impuestos**, para no sumarlos otra vez.
- **Proponer el ISH por estado,** para México, con su fuente. Solo la Ciudad de México y Quintana Roo se cotejaron con su ley: las demás son tasas orientativas de una fuente secundaria, y la pantalla lo dice. **Compruébala antes de cobrarla.** Asesor fiscal: pendiente.

**Comprobado** con la copia de demostración de Zihuatanejo, de datos ficticios: 1,628 cobros, y su suma es igual a lo pagado de sus 1,122 reservas.

**Sin verificar:** en un hosting real; ni con una principal que use varias cajas o gastos sueltos, porque esa copia no los tiene y eso solo se probó con copias armadas para la prueba. El informe de impuestos cuenta las reservas que se creen o cambien desde esta versión: de las anteriores no se guardó el desglose.

## 1.0.14 — 2026-11-05

**Permisos a la medida de cada persona, roles que puedes cambiar y control de dónde está abierta cada cuenta.**

**Nadie pierde nada al actualizar:** cada quien sigue pudiendo lo mismo hasta que le pongas un ajuste.

**Lo que hay**
- **Roles,** en Configuración: cambia lo que puede cada rol de fábrica o crea los tuyos. Un rol en uso o de fábrica no se borra.
- **Ajustes por persona,** en Usuarios, dentro de cada propiedad y plegados: permisos de más o de menos, y estos límites:
  - *Solo sus propias reservas y huéspedes.* También en Hoy y en los buscadores. En el calendario ve qué está ocupado, sin nombres. Las reservas de antes de esta versión no tienen autor, así que no las verá.
  - *No cambiar reservas ya iniciadas.*
  - *Horas para cambiar o para cancelar,* contadas desde que se creó la reserva.
  - Registrar la entrada, la salida y los pagos no cuenta como cambiar. El administrador no tiene límites.
- **Sesiones y dispositivos,** en el menú de tu cuenta: dónde está abierta, con opción de cerrar, olvidar o «Cerrar mis demás sesiones y dispositivos». Cada quien ve lo suyo y el administrador, lo de todos.
- **«Recordarme» más seguro:** caduca (30 días, o los que elijas; con 0 se apaga) y la cookie cambia en cada uso. Una cookie copiada deja de servir, y cambiar la contraseña olvida todos los dispositivos. Quienes ya tenían sesión recordada siguen dentro hasta que salgan.
- **Quitar la verificación en dos pasos a otro usuario,** si perdió su teléfono: solo el super administrador, con su contraseña, y queda anotado.
- **Auditoría completa:** lo que cambia el equipo queda con su antes y después. Se exporta a CSV y eliges cuánto se guarda; lo normal es un año. De huéspedes, empresas y grupos solo queda qué campo cambió, sin el dato.

**Sin verificar:** en un hosting real con https. La rotación de la cookie se probó en este equipo, con un solo navegador.

## 1.0.13 — 2026-11-05

**Vuelve el aviso de entradas y salidas, y ya puedes omitir clientes eligiendo dónde.**

**Llega apagado:** es un módulo nuevo. Préndelo en Sistema → Módulos, en «Aviso de entradas y salidas».

**Lo que hay**
- **Aviso de entradas y salidas:** el parte para el equipo de hasta 3 meses, con quién sale, quién llega y qué se limpia. Con casillas para añadir nombre, personas, origen, noches, teléfono y comentario.
  El texto se puede retocar y se manda por WhatsApp o se copia; el teléfono se recuerda en ese navegador. Quien limpia no ve teléfonos ni comentarios.
- **Clientes omitidos,** en Reservas: una casilla por lugar, y un cliente o todos los de un tipo.
  - *Aviso:* no sale en el parte.
  - *Informes e indicadores:* sus noches e ingresos no cuentan. Los cobros y la caja sí, porque ese dinero es real.
  - *Mensajes automáticos:* no le llegan. Los que mandes a mano, sí.
  - Su reserva sigue ocupando las fechas en el calendario y su limpieza se sigue generando.
- **Limpieza durante la estancia en días sueltos,** además de diaria o por días de la semana.
- **WhatsApp con el prefijo del país de la propiedad** si el teléfono no lo trae. Antes, esos teléfonos se quedaban sin enlace.
- **Listas de comprobación** por tipo de tarea, como casillas en la pantalla de Limpieza. Solo en las tareas que se creen desde ahora.
- **Mantenimiento recurrente:** cambiar filtros, fumigar… Una semana antes se crea su tarea. Prendido con Limpieza.

**Sin verificar:** en un hosting real ni con un teléfono de verdad. Los prefijos de país salen de la lista de la UIT, escrita de memoria y comprobada solo para México, España y Estados Unidos.

## 1.0.12 — 2026-11-05

**Una instalación de AlojaFlow ya puede subordinar a otra.** La subordinada tiene una copia de solo lectura de una propiedad, y le suma lo suyo: limpieza, llegada en línea, mensajes.
Nunca escribe en la principal, y las reservas se cambian allá.

**Cómo se usa**
- En la principal: prende el módulo de Subordinación, edita la propiedad y usa «Compartir con otra instalación». Sale un código, que solo se ve una vez.
- En la subordinada: crea una propiedad subordinada, elige «Otra instalación de AlojaFlow» y pega el código.
- Se puede revocar cada conexión sin tocar las demás. Se comparte una propiedad a la vez, y solo las independientes.
- El documento de identidad no viaja salvo que lo actives en esa conexión, y las fotos y los archivos nunca.
- Si nada cambió, no baja nada: gasta muy poco del hosting. Las dos instalaciones deben tener esta versión o una posterior.

**Lo que todavía no viaja:** los pagos y la caja uno por uno; llega en la 1.0.15. El total pagado de cada reserva sí.


**Lo que se comprobó:** con dos instalaciones reales en este equipo, con el Apache de Wamp y en red local. **Sin verificar:** entre dos hostings reales con https.

## 1.0.11 — 2026-11-05

**AlojaFlow ahora es modular, se instala más fácil y se actualiza diciéndote qué está pasando.**

**Lo que vas a notar al actualizar**
- **Hay un super administrador.** El administrador activo más antiguo pasa a serlo. Es el único que cambia los módulos, instala
  actualizaciones y nombra administradores. Si tienes otros administradores, conservan lo demás; para que actualicen, nómbralos en Usuarios.
- **Módulos, en Sistema → Módulos.** Todo lo que ya usabas sigue prendido. Apaga lo que no te sirva: sale del menú, sus páginas no abren y
  sus tareas se detienen. No se borra ningún dato.
- **Subir una actualización ya no depende del límite de tu hosting.** Va en partes de 1 MB, con barra de avance. Si se corta, sigue desde donde
  iba. Al terminar de subir te dice que esperes y la revisa sola, y al instalar sale una página de «Instalando…» en vez de quedarse quieta.
- **Primeros pasos en el inicio.** Una lista de lo que falta para empezar, que sale de tus datos y se puede ocultar.

**Instalar es más sencillo**
- `instalar-alojaflow-1.0.11.php` pesa unos 90 KB y baja lo demás de GitHub. Si tu servidor no sale a internet, te ofrece subir el paquete por
  partes o por FTP, y `instalar-alojaflow-1.0.11-completo.php` sigue llevándolo dentro.
- La revisión del servidor solo se detiene si hay algo en rojo. El servidor de la base se busca solo, con una guía por hosting.
- Quien instala es el super administrador, y el instalador lo explica.
- Al terminar entras con un botón, sin volver a escribir tu contraseña. Primero salen cinco preguntas para prender solo lo que te sirve; se pueden
  saltar y repetir. El cron dejó de pedirse al final: está en los primeros pasos.

**Esta vez todavía subes con la pantalla de tu versión actual,** con su límite de tamaño: si no alcanza, copia el zip por FTP a
`storage/app/private/actualizaciones/entrada/`. Lo nuevo se ve a partir de la siguiente actualización.

**También se corrigió**
- El aviso de «AlojaFlow se actualizó» no salía en un hosting real, y lo que siguiera en esa petición usaba otra sesión.

**Por dentro**
- Decisiones D-069 a D-072. Con este bloque arranca el plan de `docs/plan-paridad.md`.
- Una actualización desde la 1.0.10 termina con el código nuevo antes de crear la columna del super administrador: mientras no exista, también la
  termina un administrador.

**Lo que se comprobó:** en este equipo, con el Apache de Wamp: instalar con el instalador chico contra un servidor local con rangos, las preguntas, subir
29 MB por partes y actualizar una instalación 1.0.10 real a esta. **Sin verificar:** no se ha probado en DreamHost ni en otro hosting real. Lo que dice la guía
de la base de datos sobre cada panel se escribió de memoria, sin entrar a cada uno.

## 1.0.10 — 2026-11-04

Corrige los nueve avisos de seguridad que Symfony publicó el 4 de noviembre, y que también afectan a la 1.0.9 y a las anteriores.
**Actualiza si ya tienes una instalación en internet.** No cambia la base de datos ni nada de cómo funciona, y trae también las mejoras de la 1.0.9.

- `symfony/http-foundation`, `http-kernel`, `routing` y `html-sanitizer` pasan a la 7.4.23. Es el único cambio de código.
- **Lo que se comprobó:** AlojaFlow no define rutas por dominio ni proxies de confianza, ni usa las reglas de acceso ni los fragmentos de Symfony, y
  su propio código no usa `html-sanitizer`, aunque Filament sí lo trae. Las direcciones que señala el aviso sobre enlaces con otro dominio daban
  404 en la 1.0.9, con el dominio en `public/`, en la raíz o en una subcarpeta, y ningún enlace apuntaba fuera.
- **Lo que no:** no se analizó a fondo cada aviso, porque el parche es la solución para todos.

## 1.0.9 — 2026-11-04

**El panel carga mucho más rápido en un teléfono con mala red.** En una instalación nueva, Hoy pasó de 56 a 99 puntos en Lighthouse móvil
y de 9.8 a 1.5 s hasta el contenido principal. Con la red y la CPU limitadas de verdad, de 10.4 a 2.1 s, y de 2.5 a 0.8 s en las visitas
siguientes. No cambia la base de datos. Cifras y método en `docs/rendimiento-movil.md`.

**Lo que vas a notar**
- En el teléfono, las pantallas sin formulario se ven en cuanto llega la página. Los botones pueden tardar un momento más en responder, hasta
  que termina de cargar. Los formularios siguen esperando, para que no se pierda lo que escribas.
- La primera vez, el texto puede dar un pequeño salto cuando llega la fuente.
- El calendario pesa menos de la mitad y responde antes: con 40 unidades, de 1.4 MB a 0.5 MB. Un día ocupado ya no es un enlace, así que con
  el teclado solo se pasa por los días libres.

**Comprueba tu hosting.** Esto depende de que comprima, y casi todos pueden. Después de actualizar, este comando debe mostrar
`content-encoding` y `immutable`; sin compresión todo funciona igual, solo que más lento:

```bash
curl -sI -H "Accept-Encoding: gzip" "https://tu-dominio/css/filament/filament/app.css?v=1" | grep -iE "content-encoding|cache-control"
```

**Por dentro**
- `public/.htaccess` comprime con Brotli o gzip, y deja en caché un año lo que lleva versión en la dirección. Sin esos módulos, se ignora.
- Livewire se sirve como archivo, sin arrancar PHP. Ya no se cargan `echo.js`, que nadie usaba, ni la precarga de la fuente.
- Se midió el modo SPA de Filament y se descartó: la pantalla aparecía más tarde y, al volver atrás, enseñaba datos viejos (D-068).
- El código fuente descargable incluye `public/.htaccess`.
- Las pruebas ya no tocan los archivos, las copias ni las actualizaciones del equipo donde corren.

**Sin verificar:** no se ha medido en DreamHost ni en un teléfono real.

## 1.0.8 — 2026-11-04

Resultado de una auditoría completa de seguridad, rendimiento, accesibilidad y diseño (`docs/auditoria-2026-11.md`). No
se encontró nada crítico en AlojaFlow, pero sí seis fallos graves: **actualiza si ya tienes una instalación en internet**.

**Revisa tu versión de PHP.** PHP publicó el 21 de octubre correcciones de dos fallos críticos que permiten tomar el
servidor sin iniciar sesión. Necesitas **8.3.36, 8.4.27 u 8.5.12**, o más. Estado del sistema ahora te lo dice en rojo.

**Lo que cambia en cómo funciona**
- **Las reservas del motor sin pago caducan a las 48 horas** si nadie las confirma, y sus noches vuelven a estar libres.
  Se cambia en Motor de reservas; con 0 no caducan.
- **Limpieza y mantenimiento ya no ven** teléfonos, saldos ni fichas de los huéspedes, ni pueden marcar no-show.
- **Contraseñas de al menos 12 caracteres** al cambiarlas, como ya pedía el instalador. Las actuales siguen valiendo.
- **El menú está ordenado por tareas:** Operación, Reservas, Dinero, Comunicación, Configuración y Sistema.
- **Descargar una copia de seguridad pide la contraseña.**
- **El enlace de llegada ya no muestra** el código de la reserva, el correo ni el teléfono del huésped.

**Seguridad**
- Motor de reservas: fechas absurdas ya no ponen a trabajar al servidor; límites por dirección, por correo y por
  propiedad; solo acepta envíos de su propio sitio; escribir el correo de otra persona ya no muestra su nombre; los
  cupones ya no revelan qué códigos existen.
- Panel: una ruta de archivo inventada ya no lee ni borra archivos privados; ningún formulario deja usar datos de otra
  propiedad; el usuario desactivado pierde el acceso de inmediato; cambiar la contraseña cierra las demás sesiones.
- Instalador: pasada una hora en el servidor hay que volver a subirlo, y una vez instalado solo se puede borrar.
- Cabeceras de seguridad en toda la aplicación, https obligatorio cuando la instalación es https, y el motor sigue
  funcionando dentro de un iframe.
- Pagos: volver a pulsar «Pagar» sigue en el mismo cobro; un pago sobre una reserva cancelada a mano no la reactiva y
  avisa al alojamiento.
- Correo: el ajuste de cifrado no hacía nada. Ahora, con TLS, sin cifrado no se manda.
- Restaurar una copia solo ejecuta lo que AlojaFlow escribe al crearla, y ya no borra la copia si era la más antigua.
- Datos personales: el espejo de la principal va cifrado, anonimizar llega a todo lo que faltaba (PDF de aceptación,
  acompañantes, notas, avisos), exportar ya no incluye a terceros, y los nombres ya no se mandan a ui-avatars.com.
- Direcciones internas y de metadatos de nube rechazadas en calendarios y avisos; descargas con tope real.

**Errores**
- Una propiedad que ya había sincronizado no se podía independizar.
- Un idioma raro en una ficha de la principal podía frenar todos los mensajes.
- Los avisos de pagos y de correos fallidos no se podían cerrar: ahora tienen «Ya lo revisé».

**Más rápido**
- Muchas menos consultas por pantalla, fechas que sí usan los índices y cachés de arranque al instalar y actualizar. Si
  editas `.env` a mano, se toma en cuenta solo.
- Los registros van por día y se conservan 14: un archivo único llegaba a llenar la cuota.

**Más fácil**
- Botones en el verde de la marca, y mejor contraste en el tema oscuro.
- En el teléfono, las listas son tarjetas con sus datos.
- Crear una propiedad: eliges el país y se proponen moneda, zona horaria, idioma y reglas.
- Recuperar la contraseña por correo, si hay correo configurado.
- Páginas de error traducidas; informes que dicen de qué propiedad es cada fila; Estado del sistema comprueba si hay
  archivos expuestos en internet.

**Sin verificar:** Stripe, Mercado Pago, SMTP y los portales iCal solo se probaron con respuestas simuladas.

## 1.0.7 — 2026-11-04

El panel del personal, en los seis idiomas, y correcciones que salieron al revisarlo. No cambia la base de datos.

- **Panel del personal en francés, italiano, portugués y alemán.** Antes salía en inglés. Cada usuario elige su idioma
  en el perfil. Estas traducciones no las ha revisado un hablante nativo.
- **Automatizaciones:** en cuatro de sus disparadores, la lista «Cuándo se manda» mostraba una clave interna en lugar del
  texto, en todos los idiomas.
- **Hoy y Limpieza:** la fecha larga decía «Mittwoch 4 de November de 2026»; ahora se escribe según el idioma.
- **Tablas de Hoy:** el estado de cada reserva se veía cortado («Confir…»); ahora se lee completo.
- **Accesibilidad:** once campos con la etiqueta oculta se anunciaban en español a los lectores de pantalla.
- **Títulos:** «Crear Reserva» ahora es «Crear reserva», igual que en las demás pantallas; en italiano, «Nuovo
  prenotazione» ahora es «Crea prenotazione».

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
