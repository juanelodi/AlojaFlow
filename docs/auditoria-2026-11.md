# Auditoría de noviembre de 2026

Revisión de la versión 1.0.7. Todo lo que aquí aparece como corregido sale en la 1.0.8, y cada corrección de seguridad
tiene su prueba. Las decisiones están en `docs/decisiones.md`, de D-053 a D-065.

## Cómo se hizo

- **Código, leído por personas:** cuatro revisores independientes, uno por zona: superficie pública, permisos del panel,
  instalador y copias, integraciones y datos.
- **Herramientas sobre el código:** Semgrep (reglas PHP, OWASP y secretos), gitleaks (historial y paquete),
  `composer audit`, Larastan en nivel máximo, PHPMD, phpcpd.
- **Ataques automáticos, solo contra una copia local desechable:** OWASP ZAP con y sin sesión (spider y escaneo activo),
  nuclei y sqlmap.
- **Accesibilidad:** axe-core en 92 pantallas, en claro y oscuro, en escritorio y móvil (368 revisiones).
- **Rendimiento:** consultas y tiempos por pantalla con una demo de 1341 reservas, y Lighthouse.
- **Pruebas:** 411 en verde, con 85 % de las líneas cubiertas (Xdebug). Cada corrección de seguridad tiene su prueba.
- **Revisión de código** de todo el cambio: encontró diez puntos más, ya corregidos.
- **En línea:** solo `app.alojaflow.com`, sin entrar al panel y sin ataques: cabeceras, certificado y archivos expuestos.
  `alojaflow.com` quedó fuera del alcance.
- **Sin tocar:** las copias reales con datos personales (regla 12).

## Resumen

| Gravedad | Hallazgos | Estado |
|---|---|---|
| Crítica | 0 | — |
| Alta | 6 | Corregidos |
| Media | 16 | Corregidos |
| Baja | 26 | Corregidos, salvo los aceptados más abajo |

En el código de AlojaFlow no se encontró inyección SQL, XSS con script, ejecución de código, XXE ni inyección de cabeceras de
correo. Semgrep, ZAP y sqlmap no reportaron vulnerabilidades explotables, y `composer audit` y gitleaks salieron limpios.
nuclei solo reportó el fallo de PHP de la sección siguiente.

## Lo primero: la versión de PHP

nuclei detectó **CVE-2026-109618 y CVE-2026-109619** en el PHP 8.3.28 del equipo local: ejecución remota de código sin
iniciar sesión, publicada por php.net el 2026-10-21. No es un fallo de AlojaFlow, pero toma el servidor entero. Se corrige
en **8.2.35, 8.3.36, 8.4.27 y 8.5.12**, y php.net dice que no hay mitigación completa aparte de actualizar. El Apache de la
copia local se caía justo con esa prueba.

Estado del sistema y el instalador ahora avisan si la versión está afectada (D-066). **Hay que comprobar la versión que
usa cada instalación en internet.**

## Seguridad: gravedad alta

1. **Fechas sin límite en el motor.** Una salida en el año 9999 ponía al servidor a calcular millones de noches, sin
   iniciar sesión. Ahora hay tope de noches y de días por delante, antes de calcular nada.
2. **Reservas anónimas que no caducaban.** Con el formulario público se podía llenar el calendario. Ahora caducan y tienen
   límites por dirección, por correo y por propiedad (D-054).
3. **Rutas de archivo inventadas por el navegador.** Al subir fotos, una ruta manipulada leía y borraba archivos privados
   de otros. Ahora solo se toma lo que está en la carpeta de subidas.
4. **Calendarios iCal apuntando a otra propiedad.** Un gerente podía bloquear unidades de una propiedad donde no trabaja.
   Al guardar se comprueba que cada dato sea de esa propiedad.
5. **Limpieza y mantenimiento podían cancelar reservas** marcando no-show, y veían datos que no necesitan (D-055).
6. **El instalador se podía retomar.** Un candado vencido se le daba al primero que llegara, aun con `.env` escrito, y
   `terminar` funcionaba tras instalar. Ahora tiene plazo y, ya instalado, solo se borra (D-064).

## Seguridad: gravedad media

- **Sin cabeceras de seguridad** (HSTS, CSP, marcos, tipo de contenido). Ahora están en toda la aplicación (D-057).
- **Ficha ajena por correo:** escribir el correo de otra persona mostraba su nombre. Ahora el nombre también tiene que
  coincidir.
- **Usuario desactivado:** conservaba la descarga de la copia y de archivos con una sesión abierta. Ahora esas rutas lo
  comprueban, y desactivar o cambiar la contraseña cierra las demás sesiones.
- **Datos de otra propiedad:** cambiando la propiedad en el formulario se leían huéspedes y catálogos ajenos.
- **Direcciones internas:** el filtro dejaba pasar rangos que no son públicos, incluido el de metadatos de algunas nubes.
- **Espejo en claro:** `hd_filas` guardaba documentos sin cifrar (D-059).
- **Anonimizar dejaba datos** y **exportar incluía a terceros** (D-065).
- **Descargas sin tope real:** una respuesta comprimida esquivaba el límite y podía llenar el disco.
- **El ajuste de cifrado del correo no hacía nada:** se podía degradar la conexión y la contraseña viajaba en claro.
- **Auditoría visible con direcciones** en la demostración pública.
- **Avatares:** Filament mandaba el nombre de cada usuario a ui-avatars.com en cada visita (D-058).

## Seguridad: gravedad baja

Pagos (cobros duplicados, pago sobre una reserva cancelada a mano, referencia sin firma), cupones (mensajes que revelaban
qué códigos existen y usos sin bloqueo), `?idioma[]=x` que daba error 500, llegada en línea (disco lleno al reenviar,
datos precargados en un enlace filtrado), textos de terceros sin escapar en notificaciones, valores de SQL en los
registros, tokens de avisos visibles para recepción, auditoría sin purga, SQL arbitrario al restaurar una copia, firma del
instalador comprobada una sola vez, restaurar la copia más antigua la borraba, cabecera Host en los enlaces de los
correos, límites por dirección que IPv6 esquivaba, un idioma raro en una ficha que frenaba todos los mensajes, y fechas
hostiles de una principal que agotaban la memoria.

## Errores que no eran de seguridad

- **Una propiedad que ya había sincronizado no se podía independizar:** la comprobación miraba la última sincronización
  buena en lugar de una en curso. Afectaba a todas.

## Rendimiento

- Cada pantalla hacía de 80 a 174 consultas; una misma, hasta 113 veces. Ahora los permisos se recuerdan durante la
  petición.
- `whereDate` impedía usar los índices en Hoy, Calendario, Informes y otras 26 consultas.
- Abrir Limpieza intentaba insertar 37 tareas en cada visita.
- La configuración y las rutas se armaban en cada visita; ahora quedan en caché (D-060).

Lighthouse sobre una instalación nueva, con accesibilidad y buenas prácticas en 100 en todas las páginas medidas:

| Página | Rendimiento | Tiempo hasta el contenido principal |
|---|---|---|
| Motor de reservas, móvil | 100 | 0.8 s |
| Inicio de sesión, móvil | 71 | 4.7 s |
| Hoy, escritorio | 92 | 1.7 s |
| Hoy, móvil | 56 | 9.8 s |

La prueba móvil simula un teléfono lento con mala red. Esta auditoría dejó sin resolver el peso del panel en esas condiciones;
se resolvió después (D-067, `docs/rendimiento-movil.md`): en una instalación nueva, Hoy en móvil pasó a 99 puntos y 1.5 s. En
SEO, las páginas privadas y la demo salen bajo porque piden no indexarse, a propósito.

## Accesibilidad

Contraste en el tema oscuro y en el menú activo, objetivos táctiles de menos de 24 px, tablas con desplazamiento que no se
alcanzaba con el teclado, enlace vacío en Propiedades. Los avisos de contraste bajaron de 1849 a ninguno. Quedan dos avisos en
el código de Filament: el botón de filtros con contador y la cabecera vacía de la columna de acciones. Las celdas libres del
calendario miden menos de 24 px de alto cuando hay una barra encima; es propio de un calendario por habitaciones.

## Diseño y experiencia

- Botones en el verde de la marca, no en aguamarina.
- Menú por tareas (D-061).
- En el teléfono, las listas son tarjetas con sus datos, no una tabla de la que solo se ven dos columnas.
- Crear una propiedad: se elige el país, y lo demás se propone.
- Páginas de error propias y traducidas. Antes decían «Not Found».
- Recuperar la contraseña (D-063), y los avisos que ningún proceso puede resolver se cierran a mano.
- Títulos («Reserva 301» en lugar de «Ver 301»), e informes que dicen de qué propiedad es cada fila.

## Buenas prácticas

Integración continua en GitHub Actions, que `CLAUDE.md` daba por hecha y no existía; textos de consola traducidos;
registros por día; lógica repetida en una sola pieza; HTML fuera del código PHP.

## Riesgos que quedan, aceptados

- **Motor público:** alguien con muchas direcciones y correos puede apartar noches durante 48 horas (D-054).
- **Instalador:** en su primera hora en el servidor, quien adivine el nombre del archivo podría usarlo (D-064).
- **Verificación en dos pasos:** recomendada, no obligada (D-062).
- **Tareas sin cron:** cualquier visita puede dispararlas (D-039). Solo corren las programadas y con turnos.
- **CSP con `unsafe-inline` y `unsafe-eval`:** lo exigen Alpine y Livewire (D-057).
- **Nombre del huésped** visible para limpieza y mantenimiento (D-055).

## Lo que esta auditoría no cubre

- **Servicios reales:** Stripe, Mercado Pago, SMTP y portales iCal solo se probaron con respuestas simuladas. El CFDI no
  se ha timbrado.
- **El escaneo activo no entra bien a Livewire:** sus peticiones van firmadas, así que el panel se revisó sobre todo
  leyendo el código y con pruebas que simulan la manipulación. sqlmap revisó a fondo las rutas públicas; en el panel, la
  prueba se cortó por tiempo en la búsqueda de reservas, sin haber encontrado nada.
- **nuclei** corrió con las plantillas de PHP, Laravel, exposición y configuración, no con todas.
- **Textos legales y traducciones:** sin revisión de abogado ni de hablante nativo.
- **`alojaflow.com`:** fuera del alcance.
