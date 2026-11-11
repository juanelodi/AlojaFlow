<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

// Textos de partida, no asesoría legal: el alojamiento los revisa con su abogado antes de publicarlos.
return [
    'documentos' => [
        'ine' => 'INE',
        'pasaporte' => 'Pasaporte',
        'licencia' => 'Licencia de conducir',
        'fmm' => 'Forma migratoria (FMM)',
        'dni' => 'DNI',
        'nie' => 'NIE',
        'identificacion' => 'Documento de identidad',
        'otro' => 'Otro',
    ],
    'campos' => [
        'procedencia' => 'Ciudad de procedencia',
        'motivo_viaje' => 'Motivo del viaje',
        'placas' => 'Placas del vehículo',
        'soporte' => 'Número de soporte',
        'parentesco' => 'Parentesco (en menores)',
    ],
    'mexico' => [
        'aviso_privacidad' => [
            'titulo' => 'Aviso de privacidad',
            'cuerpo' => <<<'TEXTO'
{propiedad}, con domicilio en {domicilio}, es responsable del tratamiento de tus datos personales.

DATOS QUE RECABAMOS
Identificación y contacto: nombre, nacionalidad, fecha de nacimiento, domicilio, correo y teléfono. Identificación oficial: tipo, número y, si decides mandarla, su fotografía. Datos de tu estancia y de tus pagos. Los datos de tarjeta los procesa directamente la empresa de pagos; no los guardamos.

No recabamos datos personales sensibles. Te pedimos no anotarlos en los comentarios.

PARA QUÉ LOS USAMOS
Finalidades necesarias: gestionar tu reserva y tu estancia, registrarte como huésped, cobrar y atender aclaraciones.
Finalidades voluntarias: enviarte promociones. Si no lo deseas, escríbenos a {correo}; esto no afecta tu reserva.

CON QUIÉN LOS COMPARTIMOS
Con proveedores que nos ayudan a prestar el servicio (hospedaje de datos, correo, pagos) y con las autoridades cuando la ley lo exige. No vendemos tus datos.

TUS DERECHOS (ARCO)
Puedes acceder a tus datos, rectificarlos, cancelarlos u oponerte a su uso, así como revocar tu consentimiento, escribiendo a {correo} o llamando al {telefono}. Indica tu nombre, cómo acreditas tu identidad y qué pides. Te responderemos en los plazos de la ley.

CUÁNTO TIEMPO LOS GUARDAMOS
La fotografía de tu identificación, hasta 30 días después de tu salida. Los datos del registro, un año. Los de tus pagos, lo que exige la ley.

CAMBIOS
Publicaremos cualquier cambio a este aviso en nuestra página o te lo daremos a conocer al hacer tu registro.
TEXTO,
        ],
        'contrato' => [
            'titulo' => 'Condiciones de hospedaje',
            'cuerpo' => <<<'TEXTO'
Estas condiciones rigen tu hospedaje en {propiedad}.

1. PRECIO. Es el que aparece en tu reserva, con los impuestos indicados. Se paga en la moneda indicada.
2. ENTRADA Y SALIDA. En los horarios que te informamos. Una salida tardía sin aviso puede generar un cargo.
3. PERSONAS. Solo pueden alojarse las personas registradas. Una identificación oficial vigente puede ser requerida al llegar.
4. CANCELACIÓN. Aplica la política de cancelación que se te dio a conocer al reservar.
5. DAÑOS. El huésped responde por los daños que cause a la habitación y al mobiliario.
6. OBJETOS DE VALOR. Te recomendamos usar la caja de seguridad o avisarnos de ellos al llegar.
7. RECLAMACIONES. Escríbenos a {correo}. Tienes derecho a acudir a la Procuraduría Federal del Consumidor.
TEXTO,
        ],
        'reglamento' => [
            'titulo' => 'Reglamento interno',
            'cuerpo' => <<<'TEXTO'
Para la comodidad de todos, en {propiedad} te pedimos:

- Respetar el descanso de los demás huéspedes, sobre todo de noche.
- No fumar en las habitaciones.
- Registrar a todas las personas que se alojen.
- Cuidar las instalaciones y avisar de cualquier desperfecto.
- Avisar antes de traer mascotas: no todas las habitaciones las admiten.
TEXTO,
        ],
        'politica_cancelacion' => [
            'titulo' => 'Política de cancelación',
            'cuerpo' => <<<'TEXTO'
Puedes cancelar sin costo hasta [número] días antes de tu llegada. Después se cobrará [porcentaje] % del total. Si no te presentas, se cobrará la primera noche.

Para cancelar, escríbenos a {correo} o llámanos al {telefono} con tu número de reserva. Las devoluciones se hacen por el mismo medio de pago.
TEXTO,
        ],
    ],
    'generico' => [
        'aviso_privacidad' => [
            'titulo' => 'Aviso de privacidad',
            'cuerpo' => <<<'TEXTO'
{propiedad} ({domicilio}) es responsable de tus datos personales.

Los usamos para gestionar tu reserva y tu estancia, registrarte como huésped, cobrar y cumplir la ley. Los compartimos solo con los proveedores que nos ayudan a prestar el servicio y con las autoridades cuando la ley lo exige. No los vendemos.

La fotografía de tu identificación se conserva hasta 30 días después de tu salida; los datos del registro, un año.

Puedes acceder a tus datos, corregirlos o pedir que los borremos escribiendo a {correo}.

Este texto es una base: debe adaptarse a la ley de tu país.
TEXTO,
        ],
        'contrato_grupo' => [
            'titulo' => 'Contrato de grupo',
            'cuerpo' => <<<'TEXTO'
Este contrato regula el alojamiento del grupo «{grupo}» ({codigo}) en {propiedad}, que coordina {coordinador}.

1. ESTANCIA. Del {llegada} al {salida}, con {habitaciones} habitaciones.
2. PRECIO. El total es {total}. Pagos acordados:
{pagos}
3. LISTA DE HUÉSPEDES. El coordinador dirá quién va en cada habitación a más tardar el {fecha_limite}.
4. CANCELACIÓN. Aplica la política de cancelación que se le dio a conocer.
5. CONDICIONES. El coordinador da a conocer a su grupo el reglamento del alojamiento. Cada huésped responde por los daños que cause.
6. RECLAMACIONES. Escríbenos a {correo}.
TEXTO,
        ],
        'contrato' => [
            'titulo' => 'Condiciones de hospedaje',
            'cuerpo' => <<<'TEXTO'
Estas condiciones rigen tu hospedaje en {propiedad}.

1. El precio es el que aparece en tu reserva.
2. La entrada y la salida se hacen en los horarios que te informamos.
3. Solo pueden alojarse las personas registradas.
4. Aplica la política de cancelación que se te dio a conocer al reservar.
5. El huésped responde por los daños que cause.

Para cualquier reclamación, escríbenos a {correo}.
TEXTO,
        ],
        'reglamento' => [
            'titulo' => 'Reglamento interno',
            'cuerpo' => <<<'TEXTO'
Para la comodidad de todos, en {propiedad} te pedimos respetar el descanso de los demás, no fumar en las habitaciones, registrar a todas las personas que se alojen y avisarnos de cualquier desperfecto.
TEXTO,
        ],
        'politica_cancelacion' => [
            'titulo' => 'Política de cancelación',
            'cuerpo' => <<<'TEXTO'
Puedes cancelar sin costo hasta [número] días antes de tu llegada. Después se cobrará [porcentaje] % del total. Si no te presentas, se cobrará la primera noche. Para cancelar, escríbenos a {correo}.
TEXTO,
        ],
    ],
];
