<?php

/*
|--------------------------------------------------------------------------
| Configuracion del modulo Invoice (FPL-12)
|--------------------------------------------------------------------------
|
| Este archivo contiene la configuracion estatica del modulo Invoice.
|
| IMPORTANTE — Datos que NO estan aqui:
|   Los montos de los cargos fijos (Machine Maintenance, Administration Fee,
|   SHIPPING COST) NO se definen en este archivo. Se gestionan en la tabla
|   `invoice_charge_types` (catalogo administrable desde la UI).
|   Decision P-12-02 RESUELTA / D-12-22: el default_amount de cada cargo
|   es editable por el Admin sin necesidad de modificar codigo ni hacer
|   despliegues. El seeder InvoiceChargeTypeSeeder carga los valores iniciales.
|
| Numeracion del Invoice:
|   Decision P-12-03 RESUELTA / D-12-23: el sistema arranca desde #00001.
|   Los Invoices emitidos en Excel (#01006 y anteriores) son documentos
|   externos y no se migran al sistema. Separar las series evita confusion
|   de auditoria entre documentos generados por el sistema y documentos
|   historicos de Excel. Todo Invoice numerado en el sistema fue generado
|   por el sistema FlexCon Tracker.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Formato de Numeracion del Invoice
    |--------------------------------------------------------------------------
    |
    | first_number: el primer numero de Invoice del sistema es el 1,
    |   que se formatea como '00001' (5 digitos con cero a la izquierda).
    |
    | El metodo Invoice::generateInvoiceNumber() usa:
    |   str_pad($next, 5, '0', STR_PAD_LEFT)
    |
    | Ejemplo de secuencia: 00001, 00002, ..., 00999, 01000, ..., 99999
    |
    */
    'number_format' => [
        'digits'       => 5,
        'pad_char'     => '0',
        'first_number' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Datos del Emisor (FlexCon)
    |--------------------------------------------------------------------------
    |
    | Nota sobre el email: el Invoice usa 'frank@flexconinc.com' (minuscula f),
    | a diferencia del Packing Slip que usa 'Frank@flexconinc.com' (mayuscula F).
    | Esto es intencional segun el PDF #01006 analizado (Decision 12.6).
    |
    */
    'issuer' => [
        'name'    => 'FLEXCON',
        'address' => '330 Rocky Woods Lane - Bigfork, Montana - 59911',
        'phone'   => 'PH# 425-466-2184',
        'email'   => 'frank@flexconinc.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Datos del Cliente (Sold To)
    |--------------------------------------------------------------------------
    */
    'sold_to' => [
        'name'    => 'S.E.I.P., Inc.',
        'address' => '915 Armorlite Dr.',
        'city'    => 'San Marcos, Ca. 92069',
    ],

    /*
    |--------------------------------------------------------------------------
    | Datos del Destinatario de Envio (Shipped To)
    |--------------------------------------------------------------------------
    */
    'shipped_to' => [
        'name'    => 'S.E.I.P., Inc.',
        'address' => '915 Armorlite Dr.',
        'city'    => 'San Marcos, Ca. 92069',
    ],

    /*
    |--------------------------------------------------------------------------
    | Lugar de Entrega FOB
    |--------------------------------------------------------------------------
    */
    'fob_location' => 'Tecate, Ca.',

    /*
    |--------------------------------------------------------------------------
    | Estado por defecto al crear un Invoice
    |--------------------------------------------------------------------------
    */
    'default_status' => 'draft',

];
