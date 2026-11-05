<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Informes',
    'informe' => 'Informe',
    'vacio' => 'No hay datos para este periodo.',
    'descargar_pdf' => 'Descargar PDF',
    'imprimir' => 'Imprimir',
    'impuesto_principal' => 'Impuestos de la principal (:porcentaje %)',
    'tramos' => [
        'sin_vencer' => 'Sin vencer',
        'hasta_30' => 'Hasta 30 días',
        'hasta_60' => 'De 31 a 60 días',
        'mas_de_60' => 'Más de 60 días',
    ],
    'ayudas' => [
        'pickup' => 'Las noches que se reservaron entre esas fechas, sea cuando sea la estancia.',
        'pronostico' => 'Lo vendido hoy, más lo que el año pasado todavía entró desde esta fecha. Es una proyección sencilla: necesita al menos un año de historia.',
        'por_cobrar' => 'Vence al salir o, si la paga una empresa, cuando acaba su crédito. Muestra hasta 500.',
        'impuestos' => 'Por fecha de llegada. Cuenta las reservas creadas o cambiadas desde la versión 1.0.15; de una principal, según su porcentaje.',
        'ingresos_gastos' => 'Lo que entró y salió de verdad, por la fecha de cada pago o movimiento.',
        'resumen_anio' => 'Ocupación: noches entre noches disponibles. ADR: ingresos entre noches con importe. RevPAR: ingresos entre noches disponibles.',
    ],
    'nombres' => [
        'resumen_anio' => 'Resumen mes por mes',
        'pickup' => 'Lo que se vendió (pick-up)',
        'pronostico' => 'Pronóstico de ocupación',
        'por_cobrar' => 'Cuentas por cobrar',
        'ingresos_gastos' => 'Ingresos contra gastos',
        'impuestos' => 'Impuestos cobrados',
        'limpieza_persona' => 'Limpieza por persona',
        'grupos' => 'Grupos',
        'unidades' => 'Por habitación',
        'canales' => 'Por canal, netos de comisión',
        'anio_anterior' => 'Contra el año anterior',
        'futuro' => 'Reservas a futuro',
        'cobros' => 'Cobros por método',
        'estancia' => 'Estancia y anticipación',
        'dia_semana' => 'Por día de la semana',
        'cancelaciones' => 'Cancelaciones y no-show',
        'limpieza' => 'Limpieza y mantenimiento',
        'huespedes' => 'Huéspedes',
        'anticipos' => 'Anticipos pendientes',
        'gastos' => 'Gastos e ingresos',
    ],
    'columnas' => [
        'revpar' => 'RevPAR',
        'vendidas' => 'Noches vendidas',
        'total_anterior' => 'Total del año pasado',
        'a_esta_fecha' => 'El año pasado a esta fecha',
        'pronostico' => 'Pronóstico',
        'debe' => 'Quién debe',
        'vence' => 'Vence',
        'dias_vencida' => 'Días vencida',
        'pendiente' => 'Pendiente',
        'cobros' => 'Cobros',
        'otros_ingresos' => 'Otros ingresos',
        'gastos' => 'Gastos',
        'entradas' => 'Entradas',
        'impuesto' => 'Impuesto',
        'cobrado' => 'Cobrado',
        'cobrado_canal' => 'Lo cobró el portal',
        'persona' => 'Persona',
        'grupo' => 'Grupo',
        'unidad' => 'Habitación', 'noches' => 'Noches', 'ocupacion' => 'Ocupación', 'ingresos' => 'Ingresos', 'adr' => 'Precio medio',
        'canal' => 'Canal', 'reservas' => 'Reservas', 'comision' => 'Comisión', 'neto' => 'Neto',
        'propiedad' => 'Propiedad', 'noches_anterior' => 'Noches año anterior', 'ingresos_anterior' => 'Ingresos año anterior', 'variacion' => 'Variación',
        'mes' => 'Mes', 'metodo' => 'Método', 'pagos' => 'Pagos', 'importe' => 'Importe',
        'estancia' => 'Noches por estancia', 'personas' => 'Personas por reserva', 'anticipacion' => 'Días de anticipación',
        'dia' => 'Día', 'canceladas' => 'Canceladas', 'tasa' => 'Tasa', 'valor_perdido' => 'Valor cancelado', 'no_show' => 'No llegaron',
        'tipo' => 'Tipo', 'total' => 'Total', 'hechas' => 'Hechas', 'cumplimiento' => 'Cumplimiento',
        'pais' => 'País', 'huespedes' => 'Huéspedes', 'repiten' => 'Repiten',
        'reserva' => 'Reserva', 'llegada' => 'Llegada', 'anticipo' => 'Anticipo', 'pagado' => 'Pagado', 'falta' => 'Falta',
        'concepto' => 'Concepto', 'movimientos' => 'Movimientos',
    ],
];
