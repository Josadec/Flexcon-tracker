@props([
    'label' => '',
    'value' => '',
])

{{-- Celda de la tira de contexto del <x-ui-modal> (look Paso 5). --}}
<div class="bg-white dark:bg-gray-800 px-4 py-2.5">
    <div class="text-[10px] uppercase text-gray-400 dark:text-gray-500">{{ $label }}</div>
    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $value !== '' ? $value : $slot }}</div>
</div>
