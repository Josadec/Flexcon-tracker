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
            line-height: 1.2;
        }

        .banner-center .doc-title {
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 3px;
            line-height: 1.2;
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

        /* Barra de contacto */
        .contact-bar {
            background-color: #ffffff;
            border-bottom: 1px solid #c0ccd8;
            padding: 3px 10px;
            display: table;
            width: 100%;
        }

        .contact-bar .contact-cell {
            display: table-cell;
            vertical-align: middle;
            font-size: 7.5pt;
            padding-right: 20px;
            white-space: nowrap;
        }

        .contact-bar .contact-cell strong {
            font-weight: bold;
        }

        /* Bloque Sold To / Shipped To / Referencias */
        .client-block {
            padding: 5px 10px 4px 10px;
            border-bottom: 1px solid #c0ccd8;
            display: table;
            width: 100%;
        }

        .client-col {
            display: table-cell;
            vertical-align: top;
            width: 33%;
            font-size: 7.5pt;
            line-height: 1.5;
        }

        .client-col .client-label {
            font-weight: bold;
            font-size: 8pt;
            color: #1e3a5f;
            text-transform: uppercase;
            border-bottom: 1px solid #1e3a5f;
            margin-bottom: 2px;
            padding-bottom: 1px;
        }

        /* Barra de Invoice: numero en rojo + fecha */
        .invoice-id-bar {
            background-color: #ffffff;
            border-bottom: 2px solid #1e3a5f;
            padding: 4px 10px;
            display: table;
            width: 100%;
        }

        .invoice-id-bar .inv-number {
            display: table-cell;
            vertical-align: middle;
            font-size: 11pt;
            font-weight: bold;
            color: #cc0000;
            letter-spacing: 1px;
        }

        .invoice-id-bar .inv-date {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 8pt;
            font-weight: bold;
            color: #1a1a1a;
            white-space: nowrap;
        }

        /* ============================================================
           PAGINACION (numero de pagina fijo abajo)
           ============================================================ */
        #page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            display: table;
            width: 100%;
            font-size: 7.5pt;
            color: #555555;
            padding: 2px 10px;
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
            font-size: 7.5pt;
            color: #555555;
        }

        #page-footer .page-number:after {
            content: counter(page) " de " counter(pages);
        }

        /* ============================================================
           CONTENIDO PRINCIPAL
           El margin-top debe compensar la altura del encabezado fijo.
           Ajustar si el header cambia de altura.
           Encabezado aproximado: banner(~58px) + address(~22px) +
             contact(~20px) + client(~60px) + invoice-id(~26px) = ~186px
           ============================================================ */
        #main-content {
            margin-top: 192px;
            margin-bottom: 22px;
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
            font-size: 7.5pt;
            margin-top: 4px;
        }

        .items-table thead tr th {
            background-color: #1e3a5f;
            color: #ffffff;
            padding: 4px 5px;
            text-align: left;
            font-size: 7pt;
            font-weight: bold;
            border: 1px solid #1e3a5f;
            white-space: nowrap;
        }

        .items-table thead tr th.col-right {
            text-align: right;
        }

        /* Filas de producto */
        .items-table tbody tr.data-row td {
            padding: 3px 5px;
            border: 1px solid #d1d9e0;
            vertical-align: top;
            color: #1a1a1a;
        }

        .items-table tbody tr.data-row:nth-child(even) td {
            background-color: #f7f9fb;
        }

        .items-table tbody tr.data-row td.col-right {
            text-align: right;
            white-space: nowrap;
        }

        .items-table tbody tr.data-row td.col-qty {
            text-align: right;
            font-weight: bold;
            white-space: nowrap;
        }

        /* Filas de cargos fijos */
        .items-table tbody tr.charge-row td {
            padding: 3px 5px;
            border: 1px solid #d1d9e0;
            vertical-align: middle;
            background-color: #fafbfc;
            color: #1a1a1a;
        }

        .items-table tbody tr.charge-row td.charge-label {
            font-style: italic;
            color: #444444;
        }

        .items-table tbody tr.charge-row td.col-right {
            text-align: right;
            white-space: nowrap;
        }

        /* Separador entre items de producto y cargos fijos */
        .items-table tbody tr.separator-row td {
            padding: 0;
            height: 3px;
            background-color: #c0ccd8;
            border: none;
        }

        /* Fila Grand Total */
        .items-table tbody tr.grand-total-row td {
            padding: 4px 5px;
            border: 1px solid #1e3a5f;
            background-color: #1e3a5f;
            color: #ffffff;
            font-weight: bold;
            font-size: 8pt;
        }

        .items-table tbody tr.grand-total-row td.total-label {
            text-align: right;
            letter-spacing: 1px;
        }

        .items-table tbody tr.grand-total-row td.total-qty {
            text-align: right;
            white-space: nowrap;
        }

        .items-table tbody tr.grand-total-row td.total-amount {
            text-align: right;
            white-space: nowrap;
            font-size: 8.5pt;
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
                <div class="company-name">{{ $issuer['name'] ?? 'FLEXCON' }}</div>
                <div class="doc-title">INVOICE</div>
            </div>
            <div class="banner-right">
                <div class="doc-ref">Clave: FPL-12</div>
                <div class="doc-ref">Revision: 01</div>
            </div>
        </div>

        {{-- Barra de direccion --}}
        <div class="address-bar">
            330 Rocky Woods Lane &bull; Bigfork, Montana &bull; 59911
        </div>

        {{-- Barra de contacto --}}
        <div class="contact-bar">
            <div class="contact-cell">
                <strong>{{ $issuer['phone'] ?? 'PH# 425-466-2184' }}</strong>
            </div>
            <div class="contact-cell">
                {{ $issuer['email'] ?? 'frank@flexconinc.com' }}
            </div>
        </div>

        {{-- Bloque Sold To / Shipped To / Referencia PS --}}
        <div class="client-block">
            <div class="client-col">
                <div class="client-label">Sold to</div>
                <div>{{ $invoice->sold_to_name }}</div>
                @foreach (explode("\n", $invoice->sold_to_address) as $line)
                    <div>{{ trim($line) }}</div>
                @endforeach
            </div>
            <div class="client-col">
                <div class="client-label">Shipped to</div>
                <div>{{ $invoice->shipped_to_name }}</div>
                @foreach (explode("\n", $invoice->shipped_to_address) as $line)
                    <div>{{ trim($line) }}</div>
                @endforeach
            </div>
            <div class="client-col" style="text-align:right;">
                @if ($invoice->packingSlip)
                    <div style="font-weight:bold; font-size:7.5pt;">
                        Packing Slip #{{ $invoice->packingSlip->ps_number }}
                    </div>
                @endif
                <div style="margin-top:4px;">
                    <strong>F.O.B:</strong> {{ $invoice->fob_location }}
                </div>
            </div>
        </div>

        {{-- Barra de identificacion del Invoice --}}
        <div class="invoice-id-bar">
            <div class="inv-number">Invoice#{{ $invoice->invoice_number }}</div>
            <div class="inv-date">DATE: {{ $invoice->invoice_date?->format('F-d-Y') ?? '-' }}</div>
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
                    <th style="width:22%;">DESCRIPTION</th>
                    <th style="width:10%;">Item No.</th>
                    <th style="width:9%;">LOT NO.</th>
                    <th style="width:7%;">P.O No.</th>
                    <th style="width:7%;">W.O No.</th>
                    <th style="width:9%;" class="col-right">QUANTITY</th>
                    <th style="width:10%;" class="col-right">UNIT COST</th>
                    <th style="width:10%;" class="col-right">TOTAL</th>
                </tr>
            </thead>
            <tbody>

                {{-- Filas de producto (is_fixed_charge = false) --}}
                @foreach ($productItems as $item)
                    <tr class="data-row">
                        <td>{{ $item->description ?? '-' }}</td>
                        <td>{{ $item->item_number ?? '-' }}</td>
                        <td>{{ $item->lot_number ?? '-' }}</td>
                        <td>{{ $item->po_number ?? '-' }}</td>
                        <td>{{ $item->wo_number ?? '-' }}</td>
                        <td class="col-qty">{{ number_format($item->quantity) }}</td>
                        <td class="col-right">{{ number_format((float) $item->unit_cost, 4) }}</td>
                        <td class="col-right">{{ number_format((float) $item->line_total, 2) }}</td>
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
                        {{-- La descripcion del cargo ocupa las 5 primeras columnas --}}
                        <td colspan="5" class="charge-label">{{ $charge->description }}</td>
                        <td class="col-right">&nbsp;</td>
                        <td class="col-right">{{ number_format((float) $charge->unit_cost, 2) }}</td>
                        <td class="col-right">{{ number_format((float) $charge->line_total, 2) }}</td>
                    </tr>
                @endforeach

                {{-- Fila Grand Total --}}
                <tr class="grand-total-row">
                    <td colspan="4">&nbsp;</td>
                    <td class="total-label">TOTAL:</td>
                    <td class="total-qty">{{ number_format((int) $invoice->total_quantity) }}</td>
                    <td>&nbsp;</td>
                    <td class="total-amount">
                        ${{ number_format((float) $invoice->grand_total, 2) }}
                    </td>
                </tr>

            </tbody>
        </table>

    </div>{{-- /#main-content --}}

</body>
</html>
