# Cambios

Numeración: 1.0.1, 1.0.2 … 1.0.99, y después 1.2 (ver `docs/decisiones.md`, D-030).

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
