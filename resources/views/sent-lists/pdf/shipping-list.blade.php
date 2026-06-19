@php
    use App\Models\Lot;

    // Colores de banda por tipo de estación (igual criterio que la pantalla).
    $bandColors = [
        'Atrasados'                  => '#F4C7A1', // durazno
        'Mesas'                      => '#BDD7EE', // azul claro
        'Maquinas'                   => '#A9D08E', // verde
        'Maquinas Semi-Automaticas'  => '#C6E0B4', // verde claro
        'Sin Clasificar'             => '#D9D9D9', // gris
    ];

    $fmt = fn ($n) => number_format((int) $n);
    $date = fn ($d) => $d ? $d->format('m/d/y') : '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 7.5px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }

        /* Membrete */
        .title-bar { background: #1F3864; color: #fff; text-align: center; font-size: 15px; font-weight: bold; padding: 5px; }
        .head-table td { border: 1px solid #000; vertical-align: middle; }
        .doc-title { text-align: center; font-size: 18px; font-weight: bold; }
        .logo-cell { text-align: center; font-weight: bold; font-size: 13px; color: #1F3864; }
        .clave-label { background: #1F3864; color: #fff; font-weight: bold; padding: 2px 4px; font-size: 8px; }
        .clave-val { padding: 2px 4px; font-size: 8px; text-align: center; }

        /* Tabla de datos */
        .data thead th {
            background: #fff; border: 1px solid #000; padding: 3px 2px;
            font-size: 7px; font-weight: bold; text-align: center; vertical-align: middle;
        }
        .data td { border: 1px solid #000; padding: 2px 3px; vertical-align: top; }
        .band td { font-weight: bold; font-size: 9px; padding: 3px 4px; }
        .num { text-align: right; }
        .ctr { text-align: center; }
        .yellow { background: #FFFF00; font-weight: bold; text-align: right; }
        .item-no { color: #C00000; font-weight: bold; text-align: center; }
        .qty-cell { background: #D9D9D9; text-align: right; }
        .wo-main td { font-weight: bold; }
        .sub td { color: #222; }
        .note { font-style: italic; }
        .total-row td { font-weight: bold; }
        .footer-msg { text-align: center; color: #C00000; font-weight: bold; font-size: 8px; margin-top: 6px; }
    </style>
</head>
<body>

    {{-- ====================== MEMBRETE ====================== --}}
    <table class="head-table">
        <tr>
            <td colspan="3" class="title-bar">ENSAMBLES FORMULA</td>
        </tr>
        <tr>
            <td style="width: 18%;" class="logo-cell">EF</td>
            <td style="width: 64%;" class="doc-title">LISTA DE ENVIO</td>
            <td style="width: 18%; padding: 0;">
                <table style="width:100%;">
                    <tr><td class="clave-label" style="width:50%;">Clave:</td><td class="clave-val">FPL-02</td></tr>
                    <tr><td class="clave-label">Revisión:</td><td class="clave-val">06</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div style="height: 4px;"></div>

    {{-- ====================== TABLA DE DATOS ====================== --}}
    <table class="data">
        <thead>
            <tr>
                <th style="width: 3%;">DOC</th>
                <th style="width: 8%;">WO #</th>
                <th style="width: 8%;">Item #</th>
                <th style="width: 13%;">Descripción</th>
                <th style="width: 6%;">Cantidad WO</th>
                <th style="width: 13%;">Piezas Enviadas</th>
                <th style="width: 6%;">Cantidad pendiente</th>
                <th style="width: 6%;">Cantidad a Enviar</th>
                <th style="width: 7%;">Fecha Progr. A Enviar</th>
                <th style="width: 7%;">Fecha de Envío</th>
                <th style="width: 7%;">Fecha de Apertura</th>
                <th style="width: 4%;">Eq</th>
                <th style="width: 3%;">PR</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($groups as $bandName => $workOrders)
                {{-- Banda de categoría --}}
                <tr class="band" style="background: {{ $bandColors[$bandName] ?? '#D9D9D9' }};">
                    <td colspan="13">{{ $bandName }}</td>
                </tr>

                @foreach ($workOrders as $wo)
                    @php
                        $po        = $wo->purchaseOrder;
                        $part      = $po?->part;
                        $isCrimp   = (bool) ($part?->is_crimp ?? false);
                        $cantWO    = (int) $wo->original_quantity;
                        $enviadas  = (int) $wo->sent_pieces;
                        $pendiente = max(0, $cantWO - $enviadas);
                        $lots      = $wo->lots;
                        $unitLabel = $isCrimp ? 'Viajero' : 'Lote';
                    @endphp

                    {{-- Fila principal del WO --}}
                    <tr class="wo-main">
                        <td class="ctr">WO</td>
                        <td>{{ $po?->wo ?? $wo->wo_number }}</td>
                        <td class="ctr">{{ $part?->number }}</td>
                        <td>{{ $part?->description }}</td>
                        <td class="num">{{ $fmt($cantWO) }}</td>
                        <td class="num">{{ $enviadas > 0 ? $fmt($enviadas) : '' }}</td>
                        <td class="num">{{ $fmt($pendiente) }}</td>
                        <td class="yellow">{{ $fmt($pendiente) }}</td>
                        <td class="ctr">{{ $date($wo->scheduled_send_date) }}</td>
                        <td class="ctr">{{ $date($wo->actual_send_date) }}</td>
                        <td class="ctr">{{ $date($wo->opened_date ?? $wo->created_at) }}</td>
                        <td class="ctr">{{ $wo->eq }}</td>
                        <td class="ctr">{{ $wo->pr }}</td>
                    </tr>

                    {{-- Sub-filas: CRIMP = una fila por Lote de CRIMP (CrimpLot) por viajero; no-CRIMP = una fila por Lote --}}
                    @if ($isCrimp)
                        @foreach ($lots as $lot)
                            @php
                                $crimps = $lot->crimpLots;
                                $viajeroCell = $lot->lot_number . ')' . $fmt($lot->quantity);

                                // Comentario del Viajero (nivel Lot), ocultando la nota auto-generada del Wizard.
                                $rawLotComment = trim((string) ($lot->comments ?? ''));
                                $isAutoLotNote = $rawLotComment !== ''
                                    && str_contains(
                                        mb_strtolower($rawLotComment),
                                        mb_strtolower('Generado automáticamente desde Capacity Wizard')
                                    );
                                $viajeroComment = $isAutoLotNote ? '' : $rawLotComment;
                            @endphp
                            @forelse ($crimps as $ci => $crimp)
                                <tr class="sub">
                                    <td></td>
                                    <td>{{ $unitLabel }}</td>
                                    <td class="ctr" style="font-weight:bold;">{{ $viajeroCell }}</td>
                                    <td>{{ $crimp->lote_fabricante }}</td>
                                    <td class="qty-cell">{{ $crimp->crimp_lot_number . ')' . $fmt($crimp->quantity) }}</td>
                                    <td class="note">@if ($ci === 0 && $viajeroComment !== ''){{ $viajeroComment }}@if ($crimp->comments)<br>@endif@endif{{ $crimp->comments }}</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            @empty
                                {{-- Viajero sin lotes de CRIMP: muestra solo la fila del viajero --}}
                                <tr class="sub">
                                    <td></td>
                                    <td>{{ $unitLabel }}</td>
                                    <td class="ctr" style="font-weight:bold;">{{ $viajeroCell }}</td>
                                    <td></td>
                                    <td class="qty-cell">{{ $fmt($lot->quantity) }}</td>
                                    <td class="note">{{ $viajeroComment }}</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            @endforelse
                        @endforeach
                    @else
                        @foreach ($lots as $lot)
                            <tr class="sub">
                                <td></td>
                                <td>{{ trim(($po?->wo ?? '') . ' ' . $lot->lot_number) }}</td>
                                <td class="item-no"></td>
                                <td>{{ $part?->description }}</td>
                                <td class="qty-cell">{{ $fmt($lot->quantity) }}</td>
                                @php
                                    // Ocultar SOLO la nota auto-generada por el Capacity Wizard.
                                    // Las notas reales escritas por usuarios sí se muestran.
                                    $rawComment = trim((string) ($lot->comments ?? ''));
                                    $isAutoNote = $rawComment !== ''
                                        && str_contains(
                                            mb_strtolower($rawComment),
                                            mb_strtolower('Generado automáticamente desde Capacity Wizard')
                                        );
                                    $displayComment = $isAutoNote ? '' : $rawComment;
                                @endphp
                                <td class="note">{{ $displayComment }}</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endforeach
                    @endif

                    {{-- Total del WO --}}
                    @if ($lots->isNotEmpty())
                        <tr class="total-row">
                            <td></td>
                            <td></td>
                            <td class="item-no">{{ $po?->po_number }}</td>
                            <td></td>
                            <td class="num" style="border-top:2px solid #000;">Total: {{ $fmt($lots->sum('quantity')) }}</td>
                            <td colspan="8"></td>
                        </tr>
                    @endif
                @endforeach
            @empty
                <tr><td colspan="13" class="ctr" style="padding: 12px;">No hay listas de envío activas para mostrar.</td></tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
