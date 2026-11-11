<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

// Laravel solo trae estos mensajes en inglés. Aquí van los de las reglas que usa la aplicación;
// la que falte sale en el idioma de respaldo.
return [
    'accepted' => 'Debes aceptar :attribute.',
    'after' => ':Attribute debe ser posterior a :date.',
    'after_or_equal' => ':Attribute debe ser igual o posterior a :date.',
    'alpha_dash' => ':Attribute solo admite letras, números, guiones y guiones bajos.',
    'array' => ':Attribute debe ser una lista.',
    'before' => ':Attribute debe ser anterior a :date.',
    'before_or_equal' => ':Attribute debe ser igual o anterior a :date.',
    'boolean' => ':Attribute debe ser sí o no.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña no es correcta.',
    'date' => ':Attribute no es una fecha válida.',
    'date_format' => ':Attribute debe tener el formato :format.',
    'different' => ':Attribute y :other deben ser distintos.',
    'digits' => ':Attribute debe tener :digits dígitos.',
    'distinct' => ':Attribute está repetido.',
    'email' => ':Attribute debe ser un correo válido.',
    'exists' => 'El valor de :attribute no es válido.',
    'file' => ':Attribute debe ser un archivo.',
    'filled' => ':Attribute no puede estar vacío.',
    'gt' => [
        'numeric' => ':Attribute debe ser mayor que :value.',
        'string' => ':Attribute debe tener más de :value caracteres.',
    ],
    'gte' => [
        'numeric' => ':Attribute debe ser igual o mayor que :value.',
        'string' => ':Attribute debe tener :value caracteres o más.',
    ],
    'image' => ':Attribute debe ser una imagen.',
    'in' => 'El valor de :attribute no es válido.',
    'integer' => ':Attribute debe ser un número entero.',
    'lt' => [
        'numeric' => ':Attribute debe ser menor que :value.',
        'string' => ':Attribute debe tener menos de :value caracteres.',
    ],
    'lte' => [
        'numeric' => ':Attribute debe ser igual o menor que :value.',
        'string' => ':Attribute debe tener :value caracteres o menos.',
    ],
    'max' => [
        'array' => ':Attribute no puede tener más de :max elementos.',
        'file' => ':Attribute no puede pesar más de :max kilobytes.',
        'numeric' => ':Attribute no puede ser mayor que :max.',
        'string' => ':Attribute no puede tener más de :max caracteres.',
    ],
    'mimes' => ':Attribute debe ser de tipo: :values.',
    'min' => [
        'array' => ':Attribute debe tener al menos :min elementos.',
        'file' => ':Attribute debe pesar al menos :min kilobytes.',
        'numeric' => ':Attribute debe ser al menos :min.',
        'string' => ':Attribute debe tener al menos :min caracteres.',
    ],
    'not_in' => 'El valor de :attribute no es válido.',
    'numeric' => ':Attribute debe ser un número.',
    'password' => [
        'letters' => ':Attribute debe tener al menos una letra.',
        'mixed' => ':Attribute debe tener mayúsculas y minúsculas.',
        'numbers' => ':Attribute debe tener al menos un número.',
        'symbols' => ':Attribute debe tener al menos un símbolo.',
        'uncompromised' => 'Esta contraseña apareció en una filtración de datos. Elige otra.',
    ],
    'prohibited' => 'Este campo debe quedar vacío.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => ':Attribute es obligatorio.',
    'same' => ':Attribute y :other deben coincidir.',
    'size' => [
        'array' => ':Attribute debe tener :size elementos.',
        'file' => ':Attribute debe pesar :size kilobytes.',
        'numeric' => ':Attribute debe ser :size.',
        'string' => ':Attribute debe tener :size caracteres.',
    ],
    'string' => ':Attribute debe ser un texto.',
    'unique' => 'Ya existe ese valor de :attribute.',
    'url' => ':Attribute debe ser una dirección válida.',
    'uploaded' => 'No se pudo subir :attribute.',

    'custom' => [],

    'attributes' => [],
];
