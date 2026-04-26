<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Partes - Precios</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:8pt; color:#1a1a2e; }
        .header { background:#1E3A5F; color:#fff; padding:12px 18px; display:table; width:100%; }
        .h-logo { display:table-cell; vertical-align:middle; width:50px; }
        .h-logo img { width:42px; }
        .h-text { display:table-cell; vertical-align:middle; padding-left:10px; }
        .h-text h1 { font-size:13pt; font-weight:bold; }
        .h-text p { font-size:8pt; opacity:.85; margin-top:2px; }
        .h-date { display:table-cell; vertical-align:middle; text-align:right; font-size:7pt; opacity:.8; white-space:nowrap; }
        .period { background:#3B6EA5; color:#fff; padding:5px 18px; font-size:7.5pt; }
        .sec { background:#1E3A5F; color:#fff; padding:4px 10px; font-size:8.5pt; font-weight:bold; margin-top:12px; }
        .stats { display:table; width:100%; margin-top:0; }
        .sc { display:table-cell; vertical-align:top; width:25%; padding-right:4px; }
        .sc:last-child { padding-right:0; }
        .stat-card { background:#EBF1F8; border-left:3px solid #3B6EA5; padding:6px 10px; margin-top:4px; }
        .stat-card .lbl { font-size:7pt; color:#64748b; }
        .stat-card .val { font-size:13pt; font-weight:bold; color:#1E3A5F; }
        table.data { width:100%; border-collapse:collapse; font-size:7.5pt; margin-top:0; }
        table.data thead tr { background:#3B6EA5; color:#fff; }
        table.data thead th { padding:5px 6px; text-align:left; }
        table.data tbody tr:nth-child(even) { background:#EBF1F8; }
        table.data tbody tr:nth-child(odd)  { background:#fff; }
        table.data tbody td { padding:4px 6px; border-bottom:1px solid #e2e8f0; }
        table.data .c { text-align:center; }
        table.data .r { text-align:right; }
        .b-active   { color:#15803d; font-weight:bold; }
        .b-expiring { color:#b45309; font-weight:bold; }
        .b-expired  { color:#b91c1c; font-weight:bold; }
        .b-future   { color:#1d4ed8; font-weight:bold; }
        .footer { margin-top:16px; border-top:1px solid #cbd5e1; padding-top:5px; font-size:6.5pt; color:#94a3b8; text-align:center; }
        .no-data { text-align:center; padding:12px; color:#94a3b8; font-style:italic; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-logo">@if(file_exists($logoPath))<img src="{{ $logoPath }}" alt="Flexcon">@endif</div>
    <div class="h-text">
        <h1>REPORTE — PARTES POR FECHA EFECTIVA</h1>
        <p>Estado de precios respecto a la fecha de referencia</p>
    </div>
    <div class="h-date">Generado: {{ $generated_at }}</div>
</div>

<div class="period">
    Estado: {{ ucfirst($price_status) }}  |  Fecha referencia: {{ $reference_date }}  |  Días por vencer: {{ $expiring_days }}
    @if($search) | Búsqueda: "{{ $search }}" @endif
</div>

<div class="sec">RESUMEN</div>

<div class="stats">
    <div class="sc">
        <div class="stat-card"><div class="lbl">Vigentes</div><div class="val b-active">{{ $stats['vigentes'] }}</div></div>
    </div>
    <div class="sc">
        <div class="stat-card"><div class="lbl">Por vencer</div><div class="val b-expiring">{{ $stats['por_vencer'] }}</div></div>
    </div>
    <div class="sc">
        <div class="stat-card"><div class="lbl">Vencidos</div><div class="val b-expired">{{ $stats['vencidos'] }}</div></div>
    </div>
    <div class="sc">
        <div class="stat-card"><div class="lbl">Futuros</div><div class="val b-future">{{ $stats['futuros'] }}</div></div>
    </div>
</div>

<div class="sec">DETALLE</div>

@if($rows->isEmpty())
    <div class="no-data">Sin resultados para los filtros seleccionados</div>
@else
    <table class="data">
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th style="width:13%">N° Parte</th>
                <th style="width:13%">N° Ítem</th>
                <th style="width:30%">Descripción</th>
                <th style="width:12%">Estación</th>
                <th class="r" style="width:10%">Precio Muestra</th>
                <th class="c" style="width:9%">Fecha Efectiva</th>
                <th class="c" style="width:9%">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $r)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r->number }}</td>
                    <td>{{ $r->item_number }}</td>
                    <td>{{ $r->description }}</td>
                    <td>{{ $r->workstation }}</td>
                    <td class="r">${{ number_format($r->sample_price, 4) }}</td>
                    <td class="c">{{ $r->effective_date }}</td>
                    <td class="c">
                        @switch($r->estado)
                            @case('Vigente')    <span class="b-active">{{ $r->estado }}</span> @break
                            @case('Por vencer') <span class="b-expiring">{{ $r->estado }}</span> @break
                            @case('Vencido')    <span class="b-expired">{{ $r->estado }}</span> @break
                            @case('Futuro')     <span class="b-future">{{ $r->estado }}</span> @break
                            @default            {{ $r->estado }}
                        @endswitch
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">Flexcon Tracker — Reporte de Partes por Fecha Efectiva</div>
</body>
</html>
