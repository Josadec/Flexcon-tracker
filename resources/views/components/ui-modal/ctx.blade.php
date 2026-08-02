@props([
    'label' => '',
    'value' => '',
])

{{-- Celda de la tira de contexto del <x-ui-modal>: identifica el registro que se está editando. --}}
<div class="bg-white px-4 py-2.5 dark:bg-slate-800">
    <div class="text-[11px] font-semibold uppercase leading-4 tracking-wide text-slate-400 dark:text-slate-500">{{ $label }}</div>
    <div class="mt-0.5 truncate text-sm font-bold text-slate-800 dark:text-slate-100" title="{{ $value !== '' ? $value : strip_tags($slot) }}">{{ $value !== '' ? $value : $slot }}</div>
</div>
