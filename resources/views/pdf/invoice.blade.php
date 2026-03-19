<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice->invoice_number }}</title>
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

        /* ============================================================
           BANNER PRINCIPAL
           Layout: logo-left | center (FLEXCON + INVOICE) | right (Clave/Rev table)
           ============================================================ */
        .banner {
            background-color: #1e3a5f;
            color: #ffffff;
            padding: 10px 10px;
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .banner-left {
            display: table-cell;
            vertical-align: middle;
            width: 140px;
        }

        .banner-left img {
            max-height: 80px;
            max-width: 130px;
        }

        .banner-center {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            padding: 2px 0;
        }

        .banner-center .company-name {
            font-size: 32pt;
            font-weight: bold;
            letter-spacing: 4px;
            line-height: 1.0;
            color: #ffffff;
        }

        .banner-center .doc-title {
            font-size: 16pt;
            font-weight: bold;
            letter-spacing: 6px;
            line-height: 1.1;
            color: #c8d8ea;
        }

        /* Tabla Clave/Revision en el banner derecho */
        .banner-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 110px;
        }

        .clave-table {
            display: table;
            border-collapse: collapse;
            margin-left: auto;
            border: 2px solid #ffffff;
        }

        .clave-row {
            display: table-row;
        }

        .clave-label {
            display: table-cell;
            background-color: #2d5a8e;
            color: #ffffff;
            font-size: 7.5pt;
            font-weight: bold;
            padding: 3px 6px;
            border: 1px solid #ffffff;
            white-space: nowrap;
            vertical-align: middle;
        }

        .clave-value {
            display: table-cell;
            background-color: #ffffff;
            color: #1a1a1a;
            font-size: 7.5pt;
            font-weight: bold;
            padding: 3px 8px;
            border: 1px solid #cccccc;
            white-space: nowrap;
            vertical-align: middle;
            text-align: center;
            min-width: 40px;
        }

        /* ============================================================
           BARRA DE DIRECCION
           ============================================================ */
        .address-bar {
            background-color: #ffffff;
            border-bottom: 1px solid #c0ccd8;
            text-align: center;
            padding: 3px 10px;
            font-size: 8pt;
            color: #1a1a1a;
        }

        /* ============================================================
           BARRA DE CONTACTO: PH# izquierda | email derecha
           ============================================================ */
        .contact-bar {
            background-color: #ffffff;
            border-bottom: 1px solid #c0ccd8;
            padding: 3px 10px;
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .contact-left {
            display: table-cell;
            vertical-align: middle;
            font-size: 8pt;
            color: #1a1a1a;
        }

        .contact-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 8pt;
            font-weight: bold;
            color: #1a1a1a;
        }

        /* ============================================================
           BLOQUE SOLD TO / SHIPPED TO / REFERENCIAS
           3 columnas: sold-to | shipped-to | packing slip + FOB
           ============================================================ */
        .client-block {
            padding: 5px 10px 5px 10px;
            border-bottom: 1px solid #c0ccd8;
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .client-col {
            display: table-cell;
            vertical-align: top;
            width: 33.33%;
            font-size: 8pt;
            line-height: 1.5;
            color: #1a1a1a;
        }

        .client-col .client-label {
            font-size: 8pt;
            color: #1a1a1a;
            margin-bottom: 1px;
        }

        /* Columna derecha: packing slip en negrita + FOB */
        .client-col-right {
            display: table-cell;
            vertical-align: top;
            width: 33.33%;
            font-size: 8pt;
            line-height: 1.8;
            color: #1a1a1a;
            text-align: right;
        }

        .ps-number {
            font-weight: bold;
            font-size: 8.5pt;
        }

        .ps-hash {
            color: #cc0000;
            font-weight: bold;
        }

        /* ============================================================
           BARRA INVOICE # y DATE
           Invoice# en rojo a la izquierda | DATE en negro bold a la derecha
           ============================================================ */
        .invoice-id-bar {
            background-color: #ffffff;
            border-bottom: 1px solid #cccccc;
            padding: 5px 10px;
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .inv-number {
            display: table-cell;
            vertical-align: middle;
            font-size: 14pt;
            font-weight: bold;
            color: #cc0000;
            letter-spacing: 1px;
        }

        .inv-date {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 10pt;
            font-weight: bold;
            color: #1a1a1a;
            white-space: nowrap;
        }

        /* ============================================================
           PIE DE PAGINA FIJO
           "N de M" a la izquierda | "FPL-12 Rev 01" a la derecha
           ============================================================ */
        #page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            display: table;
            width: 100%;
            table-layout: fixed;
            font-size: 8pt;
            color: #1a1a1a;
            padding: 3px 10px;
            border-top: 1px solid #c0ccd8;
            background: #ffffff;
        }

        #page-footer .footer-left {
            display: table-cell;
            vertical-align: middle;
            text-align: left;
        }

        #page-footer .footer-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 8pt;
            color: #1a1a1a;
        }

        #page-footer .page-number:after {
            content: counter(page) " de " counter(pages);
        }

        /* ============================================================
           CONTENIDO PRINCIPAL
           margin-top debe compensar la altura total del header fijo:
             banner(~70px) + address(~22px) + contact(~22px)
             + client(~62px) + invoice-id(~34px) = ~210px
           ============================================================ */
        #main-content {
            margin-top: 255px;
            margin-bottom: 24px;
            padding: 0 10px;
        }

        /* ============================================================
           TABLA DE ITEMS
           8 columnas: DESCRIPTION | Item No. | LOT NO. | P.O No. |
                       W.O No. | QUANTITY | UNIT COST | TOTAL
           ============================================================ */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-top: 2px;
        }

        /* Encabezado: fondo azul oscuro, texto blanco, bold */
        .items-table thead tr th {
            background-color: #1e3a5f;
            color: #ffffff;
            padding: 4px 5px;
            text-align: center;
            font-size: 7.5pt;
            font-weight: bold;
            border: 1px solid #1e3a5f;
            white-space: nowrap;
        }

        .items-table thead tr th.th-left {
            text-align: left;
        }

        .items-table thead tr th.th-right {
            text-align: right;
        }

        /* Filas de producto: texto negro, borde gris claro */
        .items-table tbody tr.data-row td {
            padding: 3px 5px;
            border: 1px solid #cccccc;
            vertical-align: middle;
            color: #1a1a1a;
            text-align: center;
            font-size: 8pt;
        }

        .items-table tbody tr.data-row td.td-left {
            text-align: left;
        }

        .items-table tbody tr.data-row td.td-right {
            text-align: right;
            white-space: nowrap;
        }

        /* Filas de cargos fijos: descripcion centrada en primeras columnas */
        .items-table tbody tr.charge-row td {
            padding: 3px 5px;
            border: 1px solid #cccccc;
            vertical-align: middle;
            color: #1a1a1a;
            font-size: 8pt;
            background-color: #ffffff;
        }

        .items-table tbody tr.charge-row td.charge-label {
            text-align: center;
            color: #1a1a1a;
        }

        .items-table tbody tr.charge-row td.td-right {
            text-align: right;
            white-space: nowrap;
        }

        /* Separador visual entre items de producto y cargos fijos */
        .items-table tbody tr.separator-row td {
            padding: 0;
            height: 2px;
            background-color: #cccccc;
            border: none;
        }

        /* Fila Grand Total: fondo azul oscuro, texto blanco, bold grande */
        .items-table tbody tr.grand-total-row td {
            padding: 5px 5px;
            border: 1px solid #1e3a5f;
            background-color: #1e3a5f;
            color: #ffffff;
            font-weight: bold;
            font-size: 9pt;
        }

        .items-table tbody tr.grand-total-row td.total-empty {
            background-color: #1e3a5f;
        }

        .items-table tbody tr.grand-total-row td.total-qty {
            text-align: right;
            white-space: nowrap;
            font-size: 10pt;
        }

        .items-table tbody tr.grand-total-row td.total-spacer {
            background-color: #1e3a5f;
        }

        .items-table tbody tr.grand-total-row td.total-amount {
            text-align: right;
            white-space: nowrap;
            font-size: 10pt;
            font-weight: bold;
        }
    </style>
</head>
<body>

    {{-- ================================================================
         ENCABEZADO FIJO (se repite en cada pagina)
         ================================================================ --}}
    <div id="page-header">

        {{-- Banner azul marino: logo | FLEXCON/INVOICE | Clave/Rev --}}
        <div class="banner">
            <div class="banner-left">
                @if (file_exists($logoPath))
                    <img src="{{ $logoPath }}" alt="Flexcon Logo">
                @else
                    <span style="font-size:9pt; font-weight:bold; color:#ffffff;">FLEXCON</span>
                @endif
            </div>
            <div class="banner-center">
                <div class="company-name">FLEXCON</div>
                <div class="doc-title">INVOICE</div>
            </div>
            <div class="banner-right">
                <div class="clave-table">
                    <div class="clave-row">
                        <div class="clave-label">Clave:</div>
                        <div class="clave-value">FPL-12</div>
                    </div>
                    <div class="clave-row">
                        <div class="clave-label">Revisi&oacute;n:</div>
                        <div class="clave-value">01</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Barra de direccion --}}
        <div class="address-bar">
            330 Rocky Woods Lane &bull; Bigfork, Montana &bull; 59911
        </div>

        {{-- Barra de contacto: PH# izquierda | email derecha --}}
        <div class="contact-bar">
            <div class="contact-left">
                {{ $issuer['phone'] ?? 'PH# 425-466-2184' }}
            </div>
            <div class="contact-right">
                {{ $issuer['email'] ?? 'frank@flexconinc.com' }}
            </div>
        </div>

        {{-- Bloque Sold To / Shipped To / Packing Slip + FOB --}}
        <div class="client-block">
            <div class="client-col">
                <div class="client-label">Sold to:</div>
                <div>{{ $invoice->sold_to_name }}</div>
                @foreach (explode("\n", $invoice->sold_to_address) as $line)
                    @if (trim($line) !== '')
                        <div>{{ trim($line) }}</div>
                    @endif
                @endforeach
            </div>
            <div class="client-col">
                <div class="client-label">Shipped to:</div>
                <div>{{ $invoice->shipped_to_name }}</div>
                @foreach (explode("\n", $invoice->shipped_to_address) as $line)
                    @if (trim($line) !== '')
                        <div>{{ trim($line) }}</div>
                    @endif
                @endforeach
            </div>
            <div class="client-col-right">
                @if ($invoice->packingSlip)
                    <div class="ps-number">Packing Slip <span class="ps-hash">#{{ $invoice->packingSlip->ps_number }}</span></div>
                @endif
                <div>&nbsp;</div>
                <div>&nbsp;</div>
                <div><strong>F.O.B:</strong> {{ $invoice->fob_location }}</div>
            </div>
        </div>

        {{-- Barra de Invoice # y DATE --}}
        <div class="invoice-id-bar">
            <div class="inv-number">Invoice#{{ $invoice->invoice_number }}</div>
            <div class="inv-date">DATE : {{ $invoice->invoice_date?->format('F-d-Y') ?? '-' }}</div>
        </div>

    </div>{{-- /#page-header --}}

    {{-- ================================================================
         PIE DE PAGINACION FIJO
         ================================================================ --}}
    <div id="page-footer">
        <div class="footer-left">
            <span class="page-number"></span>
        </div>
        <div class="footer-right">FPL-12&nbsp;&nbsp;Rev 01</div>
    </div>

    {{-- ================================================================
         CONTENIDO PRINCIPAL
         ================================================================ --}}
    <div id="main-content">

        <table class="items-table">
            <thead>
                <tr>
                    <th class="th-left" style="width:22%;">DESCRIPTION</th>
                    <th style="width:10%;">Item No.</th>
                    <th style="width:9%;">LOT NO.</th>
                    <th style="width:7%;">P.O  No.</th>
                    <th style="width:7%;">W.O No.</th>
                    <th class="th-right" style="width:9%;">QUANTITY</th>
                    <th class="th-right" style="width:10%;">UNIT COST</th>
                    <th class="th-right" style="width:10%;">TOTAL</th>
                </tr>
            </thead>
            <tbody>

                {{-- Filas de producto (is_fixed_charge = false) --}}
                @foreach ($productItems as $item)
                    <tr class="data-row">
                        <td class="td-left">{{ $item->description ?? '-' }}</td>
                        <td>{{ $item->item_number ?? '-' }}</td>
                        <td>{{ $item->lot_number ?? '-' }}</td>
                        <td>{{ $item->po_number ?? '-' }}</td>
                        <td>{{ $item->wo_number ?? '-' }}</td>
                        <td class="td-right">{{ number_format($item->quantity) }}</td>
                        <td class="td-right">{{ number_format((float) $item->unit_cost, 4) }}</td>
                        <td class="td-right">{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach

                {{-- Separador visual entre productos y cargos fijos --}}
                @if ($fixedChargeItems->isNotEmpty())
                    <tr class="separator-row">
                        <td colspan="8"></td>
                    </tr>
                @endif

                {{-- Filas de cargos fijos (is_fixed_charge = true) --}}
                @foreach ($fixedChargeItems as $charge)
                    <tr class="charge-row">
                        {{-- La descripcion ocupa las primeras 5 columnas, centrada --}}
                        <td colspan="5" class="charge-label">{{ $charge->description }}</td>
                        <td class="td-right">{{ number_format((float) $charge->unit_cost, 0) }}</td>
                        <td class="td-right">&nbsp;</td>
                        <td class="td-right">{{ number_format((float) $charge->line_total, 2) }}</td>
                    </tr>
                @endforeach

                {{-- Fila Grand Total --}}
                <tr class="grand-total-row">
                    <td colspan="4" class="total-empty">&nbsp;</td>
                    <td class="total-empty">&nbsp;</td>
                    <td class="total-qty">{{ number_format((int) $invoice->total_quantity) }}</td>
                    <td class="total-spacer">&nbsp;</td>
                    <td class="total-amount">
                        ${{ number_format((float) $invoice->grand_total, 2) }}
                    </td>
                </tr>

            </tbody>
        </table>

    </div>{{-- /#main-content --}}

</body>
</html>
