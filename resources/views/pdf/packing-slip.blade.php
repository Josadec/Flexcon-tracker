<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Packing Slip {{ $packingSlip->ps_number }}</title>
    <style>
        /* ============================================================
           RESET Y BASE
           ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            color: #1a1a1a;
            background: #ffffff;
        }

        /* ============================================================
           ENCABEZADO FIJO — se repite en cada pagina via position:fixed
           dompdf requiere position:fixed + top:0 para repeticion de header
           ============================================================ */
        #page-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #ffffff;
        }

        /* Banner azul marino principal */
        .banner {
            background-color: #1e3a5f;
            color: #ffffff;
            padding: 6px 10px;
            display: table;
            width: 100%;
        }

        .banner-left {
            display: table-cell;
            vertical-align: middle;
            width: 120px;
        }

        .banner-left img {
            max-height: 45px;
            max-width: 110px;
        }

        .banner-center {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
        }

        .banner-center .company-name {
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 1px;
            line-height: 1.3;
        }

        .banner-center .doc-title {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 2px;
            line-height: 1.3;
        }

        .banner-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            white-space: nowrap;
            font-size: 8pt;
            width: 130px;
        }

        .banner-right .doc-ref {
            font-size: 7.5pt;
            line-height: 1.5;
        }

        /* Barra de direccion */
        .address-bar {
            background-color: #f0f4f8;
            border-bottom: 1px solid #c0ccd8;
            text-align: center;
            padding: 3px 10px;
            font-size: 7.5pt;
            color: #2d3748;
        }

        /* Barra de datos del documento */
        .doc-info-bar {
            background-color: #ffffff;
            border-bottom: 2px solid #1e3a5f;
            padding: 4px 10px;
            display: table;
            width: 100%;
        }

        .doc-info-bar .info-cell {
            display: table-cell;
            vertical-align: middle;
            font-size: 7.5pt;
            padding-right: 10px;
            white-space: nowrap;
        }

        .doc-info-bar .info-cell strong {
            font-weight: bold;
        }

        /* Bloque Sold To / Shipped To */
        .ship-block {
            padding: 5px 10px 4px 10px;
            border-bottom: 1px solid #c0ccd8;
            display: table;
            width: 100%;
        }

        .ship-col {
            display: table-cell;
            vertical-align: top;
            width: 50%;
            font-size: 7.5pt;
            line-height: 1.5;
        }

        .ship-col .ship-label {
            font-weight: bold;
            font-size: 8pt;
            color: #1e3a5f;
            text-transform: uppercase;
            border-bottom: 1px solid #1e3a5f;
            margin-bottom: 2px;
            padding-bottom: 1px;
        }

        /* ============================================================
           PAGINACION (numero de pagina fijo abajo)
           ============================================================ */
        #page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: right;
            font-size: 7.5pt;
            color: #555555;
            padding: 2px 10px;
            border-top: 1px solid #c0ccd8;
            background: #ffffff;
        }

        #page-footer .page-number:after {
            content: counter(page) " De " counter(pages);
        }

        /* ============================================================
           CONTENIDO PRINCIPAL
           El margin-top debe compensar la altura del encabezado fijo.
           Ajustar si el header cambia de altura.
           ============================================================ */
        #main-content {
            /* Espacio reservado para el encabezado fijo (~160px) */
            margin-top: 165px;
            /* Espacio reservado para pie de pagina fijo (~18px) */
            margin-bottom: 20px;
            padding: 0 10px;
        }

        /* ============================================================
           TABLA DE ITEMS
           ============================================================ */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
            margin-top: 4px;
        }

        .items-table thead tr th {
            background-color: #1e3a5f;
            color: #ffffff;
            padding: 4px 5px;
            text-align: left;
            font-size: 7.5pt;
            font-weight: bold;
            border: 1px solid #1e3a5f;
            white-space: nowrap;
        }

        .items-table thead tr th.col-qty {
            text-align: right;
        }

        /* Filas de datos normales */
        .items-table tbody tr.data-row td {
            padding: 3px 5px;
            border: 1px solid #d1d9e0;
            vertical-align: top;
            color: #1a1a1a;
        }

        .items-table tbody tr.data-row:nth-child(even) td {
            background-color: #f7f9fb;
        }

        .items-table tbody tr.data-row td.col-qty {
            text-align: right;
            font-weight: bold;
            white-space: nowrap;
        }

        /* Fila de total por grupo PO */
        .items-table tbody tr.subtotal-row td {
            padding: 3px 5px;
            border: 1px solid #b0c4d8;
            background-color: #dce8f3;
            font-weight: bold;
            font-size: 7.5pt;
            color: #1e3a5f;
        }

        .items-table tbody tr.subtotal-row td.subtotal-label {
            text-align: right;
        }

        .items-table tbody tr.subtotal-row td.subtotal-value {
            text-align: right;
            white-space: nowrap;
        }

        /* Fila separadora vacia entre grupos */
        .items-table tbody tr.spacer-row td {
            padding: 2px 0;
            border: none;
            background: #ffffff;
        }

        /* Evitar salto de pagina dentro de un grupo PO */
        .po-group {
            page-break-inside: avoid;
        }

        /* ============================================================
           PIE DE FIRMA — solo en la ultima pagina (incluido en el flujo
           del documento, NO como elemento fixed, para aparecer solo al
           final del contenido).
           ============================================================ */
        .signature-footer {
            margin-top: 16px;
            border-top: 2px solid #1e3a5f;
            padding-top: 8px;
        }

        .signature-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }

        .sig-row {
            display: table-row;
        }

        .sig-cell {
            display: table-cell;
            border: 1px solid #7a9ab5;
            padding: 6px 8px;
            vertical-align: top;
            width: 50%;
            font-size: 7.5pt;
        }

        .sig-cell .sig-label {
            font-weight: bold;
            color: #1e3a5f;
            font-size: 7.5pt;
            margin-bottom: 2px;
        }

        .sig-cell .sig-value {
            min-height: 18px;
            border-bottom: 1px solid #9ba8b5;
            margin-top: 2px;
        }

        /* Fila de una sola celda que ocupa todo el ancho */
        .sig-cell-full {
            display: table-cell;
            border: 1px solid #7a9ab5;
            padding: 6px 8px;
            vertical-align: top;
            font-size: 7.5pt;
        }

        .sig-row-single {
            display: table-row;
        }
    </style>
</head>
<body>

    {{-- ================================================================
         ENCABEZADO FIJO (se repite en cada pagina)
         ================================================================ --}}
    <div id="page-header">

        {{-- Banner azul marino --}}
        <div class="banner">
            <div class="banner-left">
                @if (file_exists($logoPath))
                    <img src="{{ $logoPath }}" alt="Flexcon Logo">
                @else
                    <span style="font-size:10pt; font-weight:bold; color:#ffffff;">FLEXCON</span>
                @endif
            </div>
            <div class="banner-center">
                <div class="company-name">ENSAMBLES FORMULA</div>
                <div class="doc-title">SHIPPING LIST</div>
            </div>
            <div class="banner-right">
                <div class="doc-ref">Clave: FPL-10</div>
                <div class="doc-ref">Rev: 02</div>
            </div>
        </div>

        {{-- Barra de direccion --}}
        <div class="address-bar">
            330 Rocky Woods Lane &bull; Bigfork, Montana &bull; 59911
        </div>

        {{-- Barra de datos del documento --}}
        <div class="doc-info-bar">
            <div class="info-cell">
                <strong>PH#</strong> 425-466-2184
            </div>
            <div class="info-cell">
                <strong>Email:</strong> Frank@flexconinc.com
            </div>
            <div class="info-cell">
                <strong>Packing Slip #</strong> {{ $packingSlip->ps_number }}
            </div>
            <div class="info-cell">
                <strong>F.O.B:</strong> Tecate, Ca.
            </div>
            <div class="info-cell">
                <strong>DATE:</strong> {{ $packingSlip->document_date?->format('m/d/Y') ?? '-' }}
            </div>
        </div>

        {{-- Bloque Sold To / Shipped To --}}
        <div class="ship-block">
            <div class="ship-col">
                <div class="ship-label">Sold To</div>
                <div>S.E.I.P., Inc.</div>
                <div>915 Armorlite Dr.</div>
                <div>San Marcos, Ca. 92069</div>
            </div>
            <div class="ship-col">
                <div class="ship-label">Shipped To</div>
                <div>S.E.I.P., Inc.</div>
                <div>915 Armorlite Dr.</div>
                <div>San Marcos, Ca. 92069</div>
            </div>
        </div>

    </div>{{-- /#page-header --}}

    {{-- ================================================================
         PIE DE PAGINACION FIJO
         ================================================================ --}}
    <div id="page-footer">
        <span class="page-number"></span>
    </div>

    {{-- ================================================================
         CONTENIDO PRINCIPAL
         ================================================================ --}}
    <div id="main-content">

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:12%;">Work Order</th>
                    <th style="width:14%;">PO #</th>
                    <th style="width:10%;">Item no</th>
                    <th style="width:28%;">Description</th>
                    <th style="width:10%;" class="col-qty">Quantity</th>
                    <th style="width:13%;">Date</th>
                    <th style="width:13%;">Label Spec</th>
                </tr>
            </thead>
            <tbody>
                @php $groupCount = 0; $totalGroups = $itemsGroupedByPo->count(); @endphp

                @foreach ($itemsGroupedByPo as $poNumber => $poItems)
                    @php
                        $groupCount++;
                        $groupTotal = $poItems->sum('quantity_packed');
                    @endphp

                    {{-- Filas de datos del grupo PO --}}
                    @foreach ($poItems as $item)
                        @php
                            $labelSpec = $item->label_spec
                                ?: ($item->lot?->workOrder?->purchaseOrder?->part?->label_spec ?: '-');
                        @endphp
                        <tr class="data-row">
                            <td>{{ $item->wo_number_ps ?? '-' }}</td>
                            <td>{{ $item->lot?->workOrder?->purchaseOrder?->po_number ?? '-' }}</td>
                            <td>{{ $item->lot?->workOrder?->purchaseOrder?->part?->item_number ?? '-' }}</td>
                            <td>{{ $item->lot?->workOrder?->purchaseOrder?->part?->number ?? '-' }}</td>
                            <td class="col-qty">{{ number_format($item->quantity_packed) }}</td>
                            <td>{{ $item->lot_date_code ?? '-' }}</td>
                            <td>{{ $labelSpec }}</td>
                        </tr>
                    @endforeach

                    {{-- Fila de subtotal por grupo PO --}}
                    <tr class="subtotal-row">
                        <td colspan="4" class="subtotal-label">Total:</td>
                        <td class="subtotal-value">{{ number_format($groupTotal) }}</td>
                        <td colspan="2"></td>
                    </tr>

                    {{-- Fila de separacion entre grupos (no despues del ultimo) --}}
                    @if ($groupCount < $totalGroups)
                        <tr class="spacer-row">
                            <td colspan="7">&nbsp;</td>
                        </tr>
                    @endif

                @endforeach
            </tbody>
        </table>

        {{-- ============================================================
             PIE DE FIRMA — aparece al final del contenido (ultima pagina)
             ============================================================ --}}
        <div class="signature-footer">
            <div class="signature-grid">
                {{-- Fila 1 --}}
                <div class="sig-row">
                    <div class="sig-cell">
                        <div class="sig-label">Total de cajas 404-10003</div>
                        <div class="sig-value">&nbsp;</div>
                    </div>
                    <div class="sig-cell">
                        <div class="sig-label">Revision de Empaque realizado por:</div>
                        <div class="sig-value">&nbsp;</div>
                    </div>
                </div>
                {{-- Fila 2 --}}
                <div class="sig-row">
                    <div class="sig-cell">
                        <div class="sig-label">Total cajas 20x20x8-1/2</div>
                        <div class="sig-value">&nbsp;</div>
                    </div>
                    <div class="sig-cell">
                        <div class="sig-label">Inspeccion de Empaque realizado por:</div>
                        <div class="sig-value">&nbsp;</div>
                    </div>
                </div>
                {{-- Fila 3 --}}
                <div class="sig-row">
                    <div class="sig-cell-full" colspan="2" style="width:100%;">
                        <div class="sig-label">Revision CM:</div>
                        <div class="sig-value">&nbsp;</div>
                    </div>
                </div>
            </div>
        </div>

    </div>{{-- /#main-content --}}

</body>
</html>
