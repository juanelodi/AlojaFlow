# Subordinar otra instalación de AlojaFlow

Una instalación (la **subordinada**) copia una propiedad de otra (la **principal**), de solo lectura. Decisión: D-073. La de HotelDruid está en
[subordinacion.md](subordinacion.md).

## Código de conexión

La principal lo genera en la propiedad, en «Compartir con otra instalación». Es `AF1-` seguido de un JSON en base64url, sin relleno:

```json
{"u": "https://hotel.example.com/", "t": "<64 caracteres hexadecimales>"}
```

- `u` es `APP_URL` de la principal, con la barra final.
- `t` es el token. La principal guarda solo su SHA-256 (`conexiones_compartidas.token_hash`), así que no se puede volver a mostrar.
- La subordinada guarda `u` en `principales.url_base` y `t` cifrado en `principales.contrasena` (regla 13). Solo por https, salvo red local en desarrollo.

## API de la principal

Todo es `GET`, con `Authorization: Bearer <token>`, bajo `api/subordinacion/v1/`. No hay nada para escribir. Límite: 240 peticiones por minuto.

| Respuesta | Cuándo | La subordinada |
|---|---|---|
| 401 | Token inválido o revocado | SUB-018, se pausa |
| 404 | Módulo de subordinación apagado, versión anterior, o la propiedad ya no está activa e independiente | SUB-017, reintenta y avisa a los 3 fallos |
| 429, 5xx | Ocupada o en mantenimiento | SUB-001, reintenta |

### `GET estado`

```json
{"api": 1, "version": "1.0.12", "revision": "<sha-256>", "propiedad": {"nombre": "", "pais": "", "moneda": "", "zona_horaria": "", "idioma": ""}}
```

- Si `api` no es la que conoce la subordinada: SUB-011, se pausa. **Subir `api` rompe a quien tenga una versión anterior:** para agregar campos no hace falta.
- `revision` cambia si cambian las unidades, huéspedes, contactos, reservas, bloqueos o el permiso de documentos. Si es la misma, una sincronización programada no baja nada. Aun así baja
  todo cada 6 horas, porque la revisión no ve cualquier cambio.

### `GET datos/{coleccion}?despues=<id>`

`{"filas": [...], "siguiente": <id o null>}`, de 500 en 500 y por identificador.

| Colección | Campos |
|---|---|
| `unidades` | `id`, `codigo`, `nombre`, `piso`, `edificio`, `capacidad`, `orden`, `notas`, `activa`, `creado_en` |
| `huespedes` | Los datos de la ficha, `contactos` y `campos`. Con `incluir_documentos`, también `documento_numero` y los demás datos del documento |
| `reservas` | Fechas, unidad, titular, estado, importes en centavos, `acompanantes`, `creado_en`, `actualizado_en` |
| `bloqueos` | Fechas ocupadas en los portales: `id`, `unidad_id`, `llegada`, `salida`, `uid`, `fuente` |

**Nunca viajan:** fotos y archivos, el código público de cada reserva (abre su página y su botón de pago), pagos y caja uno por uno (llegan en la 1.0.15), aceptaciones, usuarios, ajustes, llaves.

## En la subordinada

`App\Sincronizacion\AlojaFlow\Sincronizador`:

1. Pregunta `estado`, y se detiene ahí si nada cambió.
2. Baja las cuatro colecciones a archivos temporales, que se borran al terminar pase lo que pase. Llevan datos personales, como la copia de HotelDruid.
3. `Proyector` lo aplica todo en una transacción: si algo se corta, lo anterior sigue intacto. Recorta y comprueba cada campo, porque viene de otro servidor.

- Las filas quedan con `sincronizada = true` y `origen = otra_instalacion`. `clave_origen` es el id en la principal, y `b:<id>` en los bloqueos, que se ven como una reserva de tipo bloqueo.
- Mientras la subordinada no tenga reservas, toma la moneda y la zona horaria de la principal.
- La primera carga no genera eventos. Después, como con HotelDruid: creada, modificada, cancelada, eliminada, reactivada.
- `Independizador` funciona igual: la propiedad pasa a ser independiente, y la conexión de la principal se queda hasta que alguien la revoque.
