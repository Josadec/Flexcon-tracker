<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Años que se conserva la información
    |--------------------------------------------------------------------------
    |
    | Requisito del ISO y del cliente: 5 años. Pasado ese plazo la información
    | ya no es obligatoria, pero SEGUIR CONSERVÁNDOLA NO ROMPE NADA — lo que
    | rompe es borrarla antes de tiempo.
    |
    */

    'anios' => env('RETENCION_ANIOS', 5),

    /*
    |--------------------------------------------------------------------------
    | Qué se cuenta como información a conservar
    |--------------------------------------------------------------------------
    |
    | Cada entrada: la tabla y la fecha por la que se mide su antigüedad.
    | El informe las recorre para decir qué ha superado el plazo.
    |
    */

    'entidades' => [
        'Órdenes de compra' => ['tabla' => 'purchase_orders', 'fecha' => 'created_at'],
        'Órdenes de trabajo' => ['tabla' => 'work_orders', 'fecha' => 'created_at'],
        'Listas de envío' => ['tabla' => 'sent_lists', 'fecha' => 'created_at'],
        'Viajeros' => ['tabla' => 'lots', 'fecha' => 'created_at'],
        'Pesadas de producción' => ['tabla' => 'weighings', 'fecha' => 'weighed_at'],
        'Pesadas de calidad' => ['tabla' => 'quality_weighings', 'fecha' => 'weighed_at'],
        'Registros de empaque' => ['tabla' => 'packaging_records', 'fecha' => 'packed_at'],
        'Packing slips' => ['tabla' => 'packing_slips', 'fecha' => 'document_date'],
        'Facturas' => ['tabla' => 'invoices', 'fecha' => 'invoice_date'],
        'Historial' => ['tabla' => 'audit_trails', 'fecha' => 'created_at'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Borrado automático
    |--------------------------------------------------------------------------
    |
    | DESACTIVADO A PROPÓSITO, y no es una configuración pendiente.
    |
    | `lots.work_order_id` tiene borrado en cascada: podar una orden de compra
    | antigua arrastraría en silencio sus viajeros, sus pesadas y su historial.
    | Una tarea programada que hace eso de madrugada no es mantenimiento, es una
    | bomba. Archivar primero y borrar a mano, si algún día hace falta.
    |
    */

    'borrado_automatico' => false,

    /** Dónde se dejan los archivos exportados antes de cualquier borrado. */
    'ruta_archivo' => 'archivo',
];
