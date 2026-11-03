# Registro de decisiones

Qué se decidió, por qué y cuándo. Una entrada por decisión; si una se cambia, se añade otra
nueva que la sustituye (no se borra la anterior). Las **propuestas** esperan la aprobación del
usuario.

---

### D-001 · 2026-11-03 · Reescribir desde cero con AGPL v3 o posterior
Sistema nuevo, todo el código AGPL, sin licencia comercial ni módulos cerrados. Se comercializa
con servicios (alojado, instalación, migración, soporte, formación, personalización). Del
proyecto anterior se conservan el nombre, el logo y los colores.
**Por qué:** una base técnica, visual y de uso nueva; la AGPL permite vender servicios.
Fuente: [INICIO.md](INICIO.md) §1.

### D-002 · 2026-11-03 · Solo dependencias gratuitas y compatibles con la AGPL
MIT, BSD, Apache 2.0, LGPL v2.1+/v3, GPL v3, AGPL v3. Lo que en otros sistemas es de pago se
construye aquí. **Por qué:** evitar costos y conflictos de licencia. Fuente: INICIO.md §2.

### D-003 · 2026-11-03 · Laravel, Livewire, Alpine, Tailwind y MySQL/MariaDB, sin SQLite
**Por qué:** un solo lenguaje y un solo despliegue que corre en hosting compartido. Fuente:
INICIO.md §3.

### D-004 · 2026-11-03 · Fase 1 subordinada de solo lectura; fase 2 núcleo propio
**Por qué:** los alojamientos ya operan con HotelDruid; adoptarlo sin migrar reduce el riesgo.
Fuente: INICIO.md §1.

### D-005 · 2026-11-03 · Una instalación por cliente, también en el servicio alojado
Cada cliente tiene su instalación y su base de datos, con una o varias propiedades dentro.
**Por qué:** encaja con el hosting compartido, aísla los datos de cada cliente y el enlace
«Código fuente» corresponde a la versión instalada. Descartado: multiinquilino. Decidió: usuario.

### D-006 · 2026-11-03 · Uso mundial con paquetes de país; fase 1: México, España, EE. UU. y genérico
Lo que depende del país va detrás de interfaces con una implementación por país; se irán
añadiendo países con el tiempo. **Por qué:** mercados principales México, Estados Unidos,
España y Latinoamérica; el paquete genérico cubre al resto mientras llega el suyo. Decidió: usuario.

### D-007 · 2026-11-03 · Código en español; interfaz en seis idiomas
Identificadores (clases, métodos, tablas, columnas) en español, sin acentos ni ñ; lo que imponen
Laravel y los paquetes se queda como viene. Interfaz en español (fuente), inglés, francés,
italiano, portugués y alemán. **Por qué:** pedido del usuario; el alemán lo sugirió Claude por
ser un mercado emisor de turismo importante. Sustituye a la propuesta inicial de identificadores
en inglés. Decidió: usuario.

### D-008 · 2026-11-03 · La fase 1 incluye todas las funciones nuevas que caben en solo lectura
Las funciones del catálogo y de los plugins de AlojaFlow 1.2.x se unifican en 11 módulos (M1–M11,
[producto.md](producto.md) §8.2), sin repetir funciones parecidas. **Por qué:** pedido del
usuario. Decidió: usuario.

### D-009 · 2026-11-03 · Lo que escribe en HotelDruid pasa a la fase 2
Grupos, cupones, fusionar clientes, anonimizar en la principal y bloqueos iCal dentro de la
principal. En la fase 1 se siguen usando los plugins de la principal 1.2.x. **Por qué:** la
fase 1 nunca escribe en la principal. Decidió: usuario.

### D-010 · 2026-11-03 · Filament para la administración
Filament para catálogos y configuración; las pantallas operativas y las públicas, propias con
Livewire. **Por qué:** ahorra meses en pantallas de administración y ya trae los seis idiomas.
Decidió: usuario.

### D-011 · 2026-11-03 · AlojaFlow 1.2.x congelada
Solo correcciones de seguridad y errores; el esquema 3.081 de la copia no cambia. **Por qué:** la
fase 1 necesita una base fija que leer. Decidió: usuario.

---

## Propuestas pendientes de aprobación

### D-012 · 2026-11-03 · Reglas 11 a 13 de seguridad y datos personales
La copia nunca se ejecuta (es un `.php`); los datos personales reales no entran al repositorio;
las credenciales de la principal van cifradas y solo por HTTPS. Ver `CLAUDE.md`. **Por qué:** la
copia trae código PHP de la principal y los respaldos reales tienen datos de huéspedes.

### D-013 · 2026-11-03 · Tres capas de datos: espejo, proyección y propios
Los módulos solo usan el modelo propio; la sincronización escribe el espejo y la proyección.
**Por qué:** en la fase 2 las reservas propias conviven con las de HotelDruid sin reescribir los
módulos. Detalle: [especificacion/README.md](especificacion/README.md) §03.

### D-014 · 2026-11-03 · Sin CDN en tiempo de ejecución
Todos los recursos se compilan y se sirven desde la instalación. **Por qué:** funciona igual sin
servicios externos y no envía datos de navegación a terceros (mismo principio que en 1.2.x).

### D-015 · 2026-11-03 · Fase 1 en cuatro entregas (1.0 Base, 1.1 Operación, 1.2 Huéspedes, 1.3 Dinero)
**Por qué:** la fase 1 es grande; cada entrega se instala y sirve por sí misma. Detalle:
[producto.md](producto.md) §9.

---

## Pendientes

- Precios y modelo de venta.
- El sitio alojaflow.com y su wiki.
- Titular del copyright en las cabeceras de licencia.
- PAC para CFDI, proveedor de correo y canal del OTP.
- Inventario, almacenes y punto de venta: fase 2 o posterior.
