@props([
    'tone'  => 'neutral', // neutral | primary | danger | success
    'label' => '',        // obligatorio: describe la acción para lectores de pantalla
])

@php
    $tones = [
        'neutral' => 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600',
        'primary' => 'bg-sky-50 text-sky-700 hover:bg-sky-100 dark:bg-sky-900/40 dark:text-sky-300 dark:hover:bg-sky-900/70',
        'success' => 'bg-green-50 text-green-700 hover:bg-green-100 dark:bg-green-900/40 dark:text-green-300 dark:hover:bg-green-900/70',
        'danger'  => 'bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-900/40 dark:text-red-300 dark:hover:bg-red-900/70',
    ];
@endphp

{{-- Botón sólo-icono. Siempre lleva `label`: sin texto visible, el nombre accesible es lo único que queda. --}}
<button {{ $attributes->merge([
    'type'       => 'button',
    'title'      => $label,
    'aria-label' => $label,
    'class'      => 'ui-icon-btn '.($tones[$tone] ?? $tones['neutral']),
]) }}>
    {{ $slot }}
</button>
