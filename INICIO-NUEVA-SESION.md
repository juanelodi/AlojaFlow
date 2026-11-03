# AlojaFlow nuevo — notas de arranque

Documento para iniciar la primera sesión en el entorno nuevo. Recoge lo decidido,
lo investigado y cómo trabajar. Fecha: 2026-11-03.

---

## 1. Qué es y qué se decidió

- **AlojaFlow nuevo** es un sistema de gestión de alojamientos (PMS) escrito **desde cero**,
  con licencia **AGPL v3 o posterior** (código abierto).
  - Se comercializa con servicio alojado (SaaS), instalación, migración de datos, soporte,
    formación y personalizaciones. La AGPL lo permite.
  - Quien use AlojaFlow (también por internet) tiene derecho a su código fuente: la
    aplicación ofrece un enlace de descarga del código de la versión instalada.
  - Sin licencia comercial ni módulos cerrados: todo el código es AGPL.
- **Motivo de empezar desde cero**: una base técnica, visual y de experiencia de uso
  nueva y actual, no un cambio de licencia.
- Del proyecto anterior se conservan el **nombre** y el **logo** (y los colores de marca).
- **Sin código viejo.** Se puede aprovechar la lógica de HotelDruid, del AlojaFlow 1.2.x
  y de otros proyectos compatibles (tarifas, disponibilidad, formato de copia…), pero
  siempre **reescrita en código moderno** que cumpla la regla 4. Ningún archivo viejo
  entra tal cual.
- **No se compra ninguna licencia por ahora.** Solo se usan librerías gratuitas con
  licencia compatible con la AGPL (regla 3). Lo que en otros sistemas es de pago se
  construye aquí (calendario por habitaciones, sincronización, motor de reservas).

### Fases

1. **Fase 1 — subordinado de solo lectura de HotelDruid.** HotelDruid (o AlojaFlow 1.2.x)
   es la instalación principal; AlojaFlow descarga su copia de datos y ofrece encima las
   funciones nuevas. Los datos propios de AlojaFlow van en tablas que la sincronización
   no toca.
2. **Fase 2 — núcleo propio.** Reservas, tarifas y clientes propios, para funcionar sin
   HotelDruid. Se diseña desde el principio (el modelo de datos ya lo contempla) y se
   construye después.

---

## 2. Reglas del proyecto (van al CLAUDE.md del repositorio nuevo)

1. Licencia del proyecto: **AGPL v3 o posterior**. Archivo `LICENSE` en la raíz y
   cabecera breve de licencia en cada archivo fuente.
2. Arquitectura, nombres y estructura nuevos (Laravel). Nombres en español o inglés; los
   italianos de HotelDruid solo dentro del módulo de sincronización.
3. Dependencias: solo licencias **compatibles con la AGPL v3**: MIT, BSD, Apache 2.0,
   LGPL v2.1+/v3, GPL v3, AGPL v3. **No**: GPL v2 "solo" (sin "o posterior"), OSL
   (QloApps), licencias propietarias o de pago, ni código sin licencia.
   Revisar con `composer licenses` y el equivalente de npm antes de cada versión.
4. **Código de otros repositorios** (HotelDruid, AlojaFlow 1.2.x y cualquier otro con
   licencia que cumpla la regla 3): se aprovecha **la lógica, no el código viejo**. Todo
   lo que entre debe cumplir estos mínimos de código moderno:
   - PHP 8.3 con `declare(strict_types=1)`, tipos en parámetros y retornos, enums,
     clases con una responsabilidad clara (nada de funciones globales ni variables
     globales);
   - base de datos solo con Eloquent o el query builder de Laravel y migraciones (nada
     de SQL armado concatenando cadenas);
   - validación con Form Requests, permisos con Policies, HTML solo en vistas Blade o
     componentes Livewire (nada de `echo` de HTML desde la lógica);
   - formato PSR-12 (Laravel Pint), sin errores en Larastan, y **con pruebas** que
     cubran la lógica adaptada;
   - nombres nuevos en español o inglés, interfaz responsiva y accesible.
   Si un fragmento no puede cumplirlo sin reescribirse entero, se reescribe entero.
   Cuando la lógica venga de otro proyecto, conservar su aviso de copyright y anotarlo en
   `THIRD-PARTY-NOTICES.md` (origen, licencia, archivos afectados).
5. **Contribuciones externas**: bajo la misma AGPL, con aceptación DCO (*Signed-off-by*
   en cada commit). No hace falta CLA.
6. Cada cambio con pruebas (Pest) y análisis estático (Larastan) en verde.
7. Mensajes, interfaz y documentación en español; preparado para más idiomas.
8. Enlace "Código fuente" visible en la aplicación (obligación de la AGPL para uso por
   red), que descarga el código de la versión instalada.
9. Créditos: mientras haya lógica derivada de HotelDruid, mencionarlo (Marco M. F. De
   Santis) en `THIRD-PARTY-NOTICES.md` y en la pantalla "Acerca de". Se puede decir
   "compatible con HotelDruid", sin usar su logo ni sugerir que es un producto oficial.
10. **Subordinación**: se documenta en `docs/especificacion/subordinacion.md`:
    - las llamadas HTTP que hace la subordinada a la principal (`crea_backup.php` con
      sus parámetros: crear, descargar —comprimida o no— y borrar la copia), sus
      respuestas y errores;
    - el formato del archivo de copia (estructura por base de datos, tablas y filas, y
      sus escapes);
    - la correspondencia entre las tablas de HotelDruid 3.081 y el modelo propio.
    El módulo se programa con código moderno (regla 4) y se prueba con copias de
    respaldo reales. **Nunca** se pide ni se instala el código fuente de la principal
    (`mostra_sorgente.php`).

---

## 3. Tecnología

| Capa | Elección | Licencia |
|---|---|---|
| Lenguaje | PHP 8.3 | PHP License |
| Framework | Laravel (versión estable actual) | MIT |
| Base de datos | MySQL 8.4 / MariaDB 11.4 (**sin SQLite**) | — |
| Interfaz interactiva | Livewire | MIT |
| Detalles de interfaz | Alpine.js | MIT |
| Estilos | Tailwind CSS | MIT |
| Panel de administración | Filament (gratuito) — o pantallas propias con Livewire si se prefiere todo propio | MIT |
| Autenticación y 2FA | Laravel Fortify | MIT |
| Roles y permisos | spatie/laravel-permission | MIT |
| Copias de seguridad | spatie/laravel-backup | MIT |
| Calendarios iCal (sincronizar con Airbnb, Booking, Vrbo) | sabre/vobject | BSD-3 |
| PDF (facturas, contratos) | barryvdh/laravel-dompdf (dompdf) | MIT / LGPL-2.1 (compatible) |
| Pagos (más adelante) | SDK oficiales de Stripe / Mercado Pago (verificar licencia) | — |
| Facturación electrónica México (si aplica) | paquetes de phpcfdi (cfditopdf, cfdi-expresiones, sat-catalogos) | MIT |
| Pruebas | Pest | MIT |
| Análisis estático | Larastan (PHPStan) | MIT |
| Formato de código | Laravel Pint | MIT |
| Integración continua | GitHub Actions | — |
| Entorno local | Laravel Herd (Windows, gratuito) + MySQL/MariaDB de WampServer | — |

Se construye en casa (no se compra): calendario por habitaciones (*tape chart*),
motor de reservas, sincronización iCal, informes.

Descartado por ahora: React/Vue separado, Docker, microservicios, app nativa (más adelante
una PWA), FullCalendar Premium, channel manager de pago.

### Entorno a preparar

- PHP 8.3 como principal (en el equipo actual el PATH tiene primero la 8.2).
- Composer, Node.js (para compilar Tailwind/Alpine), Git.
- Repositorio privado nuevo (p. ej. `E:\AlojaFlow` + GitHub privado), autor `juanelodi`.
- Instalación en hosting compartido (`public_html`): el paquete incluye `vendor/` y los
  recursos ya compilados; un solo cron para el programador de tareas de Laravel.

---

## 4. Investigación: qué hay en el mercado y qué se puede adaptar

De proyectos con licencia compatible (regla 3) se puede adaptar código siguiendo la
regla 4. De los incompatibles (OSL, propietarios) solo ideas y funciones.

### Proyectos de código abierto revisados

| Proyecto | Qué es | Licencia | Uso para nosotros |
|---|---|---|---|
| HotelDruid / AlojaFlow 1.2.x | El sistema actual: reservas, tarifas, subordinación, formato de copia | AGPL v3+ | Se aprovecha su lógica probada (tarifas, disponibilidad, formato de copia), reescrita en código moderno (regla 4) |
| QloApps | PMS + motor de reservas + web de hotel, PHP | OSL-3.0 (incompatible con AGPL) | Solo ideas: flujo de reserva web, gestión de habitaciones |
| OCA pms (Odoo) | PMS para propiedades medianas: inventario, reservas, check-in, planes de tarifas y disponibilidad, multipropiedad | AGPL-3 | Compatible (Python): adaptar el modelo de planes tarifarios, disponibilidad y multipropiedad |
| lprabodha/hotel-reservation | Reservas de hotel en Laravel 12, multihotel, roles | MIT (según README; verificar LICENSE) | Referencia de estructura Laravel; se puede adaptar código |
| CheesyTech/booking, rinvex/laravel-bookings | Paquetes genéricos de reservas para Laravel | MIT | Modelado de reservas por recurso; se puede adaptar código |
| Cal.com / Cal.diy | Agendas de citas, no hotelería | AGPL | Compatible, pero poco aplicable; ideas de interfaz de reserva |

Conclusión: no hay un PMS en Laravel moderno que sirva de base. Se construye una base
nueva y se aprovecha la lógica de HotelDruid, OCA pms y los paquetes MIT donde ahorre
trabajo, siempre reescrita en código moderno.

### Productos comerciales de referencia (para comparar funciones)

- **Little Hotelier** (alojamientos pequeños, < 20 hab.): PMS + channel manager + motor de
  reservas en un solo plan. Es el competidor más cercano por tamaño de cliente.
- **Cloudbeds** (independientes y medianos): PMS, distribución, motor, pagos e integraciones
  en una plataforma.
- **Mews, Sirvoy, Smoobu, Lodgify, Beds24, Hostaway**: revisar sus listas de funciones y
  demos al diseñar cada módulo.

### Canales y distribución

- **iCal** (gratis): sincronizar disponibilidad con Airbnb, Booking.com y Vrbo. Limitación
  conocida: retrasos y overbooking, sobre todo con Booking.com. Hay que avisar al usuario,
  sincronizar a menudo y detectar choques.
- **Channel manager por API** (de pago, fase posterior): Channex ofrece marca blanca para
  PMS (cuota fija mensual + coste por propiedad) y conecta con Booking.com, Expedia,
  Airbnb, etc. Se deja preparado el diseño (interfaz de "canal") para conectarlo cuando haya
  clientes.
- **Google Hotel — enlaces de reserva gratuitos**: Google no cobra por ellos. Requieren un
  feed ARI (disponibilidad, tarifas, inventario) o, para hoteles pequeños, cargar tarifas
  desde Google Business Profile. Objetivo de fase posterior: que AlojaFlow genere el feed.

### Motor de reservas directas: buenas prácticas

- Prioridad móvil: rapidez y barra de reserva fija en pantalla.
- Coherencia de precio desde el anuncio hasta el pago, sin sorpresas.
- Pocos pasos, precio total claro (impuestos incluidos), botón principal siempre visible.
- Sección "por qué reservar directo" y opiniones cerca del botón.
- Referencia: la conversión media de webs de hotel ronda el 1–3 % y la de móvil es menor;
  medirlo desde el primer día.

---

## 5. Catálogo de funciones (de la investigación y de lo que ya pidió el usuario)

Marcar en la primera sesión cuáles entran en la fase 1.

**Operación diaria**
- Calendario por habitaciones (arrastrar y soltar, colores por estado).
- Reservas: individuales y de grupo, estados, depósitos, origen (directa, OTA, teléfono).
- Check-in/check-out, check-in en línea, firma de contrato (con OTP: pendiente del proyecto
  anterior).
- Limpieza y mantenimiento: estado de habitaciones, avisos, tareas.

**Tarifas y disponibilidad**
- Temporadas, planes tarifarios, precio por ocupación.
- Restricciones: estancia mínima/máxima, cierre de llegada/salida.
- Cobro por habitación (pendiente del proyecto anterior).

**Huéspedes**
- Ficha e historial, documentos, preferencias.
- Registro de viajeros exigido por ley según el país (p. ej. SES.Hospedajes en España;
  verificar el equivalente en cada país objetivo).

**Dinero**
- Folios, cobros, pagos parciales, facturación (CFDI 4.0 si el mercado es México).
- Multimoneda.

**Venta**
- Motor de reservas directo (widget para la web del alojamiento).
- Sincronización iCal; más adelante channel manager por API y feed de Google.

**Gestión**
- Multipropiedad (panel con resumen de todas).
- Informes: ocupación, ADR, RevPAR, ingresos por canal.
- Correos automáticos con plantillas; enlace a WhatsApp.

**Plataforma**
- Usuarios, roles, permisos, 2FA, registro de auditoría.
- Varios idiomas.
- Copias de seguridad y actualizaciones desde el propio panel.
- **Subordinación a HotelDruid** (fase 1).

---

## 6. Cómo trabajar

### Modelo y nivel de esfuerzo

Modelo: **Opus 5.5** en todo el proyecto.

| Tipo de trabajo | Esfuerzo |
|---|---|
| Especificación, arquitectura, modelo de datos, sincronización con HotelDruid, tarifas y disponibilidad, seguridad | **high** (o **xhigh** en el modelo de datos y en la lógica de disponibilidad / overbooking) |
| Pantallas y CRUD normales, traducciones, ajustes de diseño | **medium** |
| Cambios pequeños y preguntas rápidas | **low** |

Para revisar cambios importantes antes de integrarlos: `/code-review high`.

### Método

1. **Primera sesión en modo plan**: no escribir código; producir el documento de producto y
   la especificación. Revisarlos tú y aprobarlos.
2. Una sesión por módulo (esqueleto, sincronización, calendario, tarifas…), para que el
   contexto no se mezcle.
3. Pasos pequeños: cada paso termina con pruebas en verde y un commit.
4. Un `CLAUDE.md` en el repositorio nuevo con las reglas de la sección 2 y la tecnología de
   la sección 3; se actualiza cuando se decida algo nuevo.
5. Carpeta `docs/` con la especificación y un registro de decisiones (qué se decidió, por
   qué y fecha). Sirve también como prueba de autoría.
6. Datos de prueba: copias de respaldo reales de HotelDruid (como datos, no código) para
   probar la sincronización.

### Orden de construcción

1. Documento de producto + especificación (protocolo de subordinación, formato de copia,
   modelo de datos propio, correspondencia de esquemas, pantallas de la fase 1).
2. Esqueleto Laravel: instalador, inicio de sesión, usuarios, roles, pruebas, CI.
3. Módulo de sincronización con HotelDruid.
4. Funciones nuevas de la fase 1, una a una.
5. Empaquetado e instalación en hosting compartido; actualizaciones desde el panel.
6. Revisión de licencias de dependencias y de THIRD-PARTY-NOTICES.md antes de la primera versión.

---

## 7. Instrucción para pegar en la primera sesión

Antes: copiar este archivo al repositorio nuevo como `docs/INICIO.md`.

> Vamos a crear AlojaFlow, un sistema de gestión de alojamientos, desde cero y con
> licencia AGPL v3 de código abierto. Lee `docs/INICIO.md` (este documento).
> Trabaja en modo plan: todavía no escribas código. Primero crea el `CLAUDE.md` con las
> reglas y la tecnología, y
> después propón el documento de producto y el índice de la especificación (protocolo de
> subordinación con HotelDruid, formato de copia, modelo de datos propio, pantallas de la
> fase 1). Hazme las preguntas que necesites para decidir el alcance de la fase 1 y el país
> o países objetivo.

---

## 8. Decisiones pendientes del usuario

- Qué funciones del catálogo entran en la fase 1.
- País o países objetivo (afecta a facturación electrónica y registro de viajeros).
- Modelo de venta: servicio alojado (SaaS), instalación, soporte; precios.
- Qué pasa con AlojaFlow 1.2.x (mantener o congelar) y con el sitio alojaflow.com y su wiki.
- Filament o pantallas de administración 100 % propias.

## Fuentes consultadas

- QloApps: https://alternativeto.net/software/qloapps/about · https://ideaproof.io/open-source/project/qloapps
- OCA pms (Odoo): https://pypi.org/project/odoo-addon-pms/
- Laravel booking (MIT): https://awesome.ecosyste.ms/projects/github.com%2Flprabodha%2Fhotel-reservation · https://packagist.org/packages/cheesytech/booking · https://codeclimate.com/github/rinvex/laravel-bookings/composer.json/source
- Comparativas PMS: https://hotelfriend.com/blogpost/hotel-software-small-hotels · https://costbench.com/best/best-hotel-management-small-hotels/ · https://hoteltechreport.com/news/best-hotel-pms-software
- iCal y overbooking: https://community.withairbnb.com/t5/Help-with-your-business/Creating-a-free-channel-manager-for-small-hosts/m-p/1998727
- Channex: https://channex.io/pricing.md · https://docs.channex.io/llms-full.txt
- Google Hotel: https://developers.google.com/hotels/hotel-prices/dev-guide/ari-overview · https://support.google.com/hotelprices/answer/10472393 · https://ppc.land/google-to-allow-individual-hotels-to-take-part-in-free-hotel-booking-links/
- Motor de reservas: https://bookingwhizz.com/blog/booking-engine-ux-kills-conversion · https://thekatalystlabs.pages.dev/insights/booking-engine-path-problem · https://www.roomstay.io/blog/optimising-hotel-average-conversion-rate
- phpcfdi: https://github.com/orgs/phpcfdi/repositories
- sabre/vobject: https://ftp.sjtu.edu.cn/remi/enterprise/8/remi/x86_64/repoview/php-sabre-vobject.html
