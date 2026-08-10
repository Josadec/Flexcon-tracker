@php
    $wo          = $viajero->workOrder?->purchaseOrder?->wo ?? $viajero->workOrder?->wo_number ?? '—';
    $description = $viajero->workOrder?->purchaseOrder?->part?->description ?? '—';
    $partNumber  = $viajero->workOrder?->purchaseOrder?->part?->number ?? '—';
    $piezas      = $viajero->getPackagedPiecesTotal();
    $crimp       = $viajero->getPackagedCrimpTotal();
    $sobPiezas   = $viajero->getPackagedPiecesSurplus();
    $sobCrimp    = $viajero->getPackagedCrimpSurplus();
    // Sobrante DECLARADO manualmente por Empaque (Paso 5), agregado por viajero.
    $hasDeclared    = $viajero->crimpLots->contains(fn($cl) => $cl->hasSurplusCaptured());
    $declaredPiezas = (int) $viajero->crimpLots->filter(fn($cl) => $cl->surplus_pieces !== null)->sum('surplus_pieces');
    $declaredCrimp  = (int) $viajero->crimpLots->filter(fn($cl) => $cl->surplus_crimps !== null)->sum('surplus_crimps');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empaque terminado CRIMP — Viajero {{ $viajero->lot_number }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#7c3aed;color:#ffffff;padding:16px 20px;border-radius:8px 8px 0 0;">
            <h1 style="margin:0;font-size:18px;">Empaque terminado — CRIMP</h1>
            <p style="margin:4px 0 0;font-size:13px;opacity:.9;">Viajero {{ $viajero->lot_number }} &middot; Orden {{ $wo }}</p>
        </div>

        <div style="background:#ffffff;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 8px 8px;padding:20px;">
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <tr>
                    <td style="padding:6px 0;color:#6b7280;width:45%;">No. de orden (WO)</td>
                    <td style="padding:6px 0;font-weight:bold;">{{ $wo }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#6b7280;">Viajero</td>
                    <td style="padding:6px 0;font-weight:bold;">{{ $viajero->lot_number }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#6b7280;">Parte</td>
                    <td style="padding:6px 0;">{{ $partNumber }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#6b7280;">Descripción</td>
                    <td style="padding:6px 0;">{{ $description }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#6b7280;">No. de etiquetas</td>
                    <td style="padding:6px 0;">{{ $labelCount !== null ? number_format($labelCount) : '—' }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#6b7280;">Cantidad en viajero</td>
                    <td style="padding:6px 0;font-weight:bold;">{{ number_format($viajero->quantity) }}</td>
                </tr>
            </table>

            <table style="width:100%;border-collapse:collapse;margin-top:16px;font-size:14px;">
                <thead>
                    <tr style="background:#f9fafb;">
                        <th style="text-align:left;padding:8px;border:1px solid #e5e7eb;color:#374151;">Concepto</th>
                        <th style="text-align:right;padding:8px;border:1px solid #e5e7eb;color:#374151;">Piezas</th>
                        <th style="text-align:right;padding:8px;border:1px solid #e5e7eb;color:#374151;">CRIMP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding:8px;border:1px solid #e5e7eb;">Completadas</td>
                        <td style="padding:8px;border:1px solid #e5e7eb;text-align:right;font-weight:bold;color:#059669;">{{ number_format($piezas) }}</td>
                        <td style="padding:8px;border:1px solid #e5e7eb;text-align:right;font-weight:bold;color:#7c3aed;">{{ number_format($crimp) }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px;border:1px solid #e5e7eb;">Sobrante (calculado)</td>
                        <td style="padding:8px;border:1px solid #e5e7eb;text-align:right;color:#ea580c;">{{ number_format($sobPiezas) }}</td>
                        <td style="padding:8px;border:1px solid #e5e7eb;text-align:right;color:#ea580c;">{{ number_format($sobCrimp) }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px;border:1px solid #e5e7eb;">Sobrante declarado por Empaque</td>
                        <td style="padding:8px;border:1px solid #e5e7eb;text-align:right;color:#b45309;">{{ $hasDeclared ? number_format($declaredPiezas) : '—' }}</td>
                        <td style="padding:8px;border:1px solid #e5e7eb;text-align:right;color:#b45309;">{{ $hasDeclared ? number_format($declaredCrimp) : '—' }}</td>
                    </tr>
                </tbody>
            </table>

            @if ($extraComments)
                <div style="margin-top:16px;padding:12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;font-size:13px;">
                    <strong style="color:#6b7280;">Comentarios:</strong><br>
                    {{ $extraComments }}
                </div>
            @endif

            <table style="width:100%;border-collapse:collapse;margin-top:16px;font-size:13px;color:#6b7280;">
                <tr>
                    <td style="padding:4px 0;">Empacadora</td>
                    <td style="padding:4px 0;text-align:right;color:#111827;">{{ $packerName ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="padding:4px 0;">Fecha</td>
                    <td style="padding:4px 0;text-align:right;color:#111827;">{{ now()->format('d/m/Y H:i') }}</td>
                </tr>
            </table>

            <p style="margin:20px 0 0;font-size:12px;color:#9ca3af;">
                Notificación automática del sistema Flexcon Tracker — Empaque CRIMP.
            </p>
        </div>
    </div>
</body>
</html>
