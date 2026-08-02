@props([
    'show'         => null,  // URL de "Ver"
    'edit'         => null,  // URL de "Editar"
    'delete'       => null,  // llamada Livewire de "Eliminar", p.ej. "deletePart(3)"
    'deleteConfirm'=> '¿Eliminar este registro? Esta acción no se puede deshacer.',
    'label'        => 'este registro', // para los tooltips
])

{{--
    Grupo de acciones de una fila. Siempre el mismo orden (ver · editar ·
    eliminar) y la destructiva siempre al final, separada por color.
    El slot permite añadir acciones extra antes de las estándar.
--}}
<div {{ $attributes->class('flex items-center justify-end gap-1.5') }}>
    {{ $slot }}

    @if ($show)
        <a href="{{ $show }}" wire:navigate class="ui-icon-btn bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
            title="Ver {{ $label }}" aria-label="Ver {{ $label }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
        </a>
    @endif

    @if ($edit)
        <a href="{{ $edit }}" wire:navigate class="ui-icon-btn bg-sky-50 text-sky-700 hover:bg-sky-100 dark:bg-sky-900/40 dark:text-sky-300 dark:hover:bg-sky-900/70"
            title="Editar {{ $label }}" aria-label="Editar {{ $label }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        </a>
    @endif

    @if ($delete)
        <button type="button" wire:click="{{ $delete }}" wire:confirm="{{ $deleteConfirm }}"
            class="ui-icon-btn bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-900/40 dark:text-red-300 dark:hover:bg-red-900/70"
            title="Eliminar {{ $label }}" aria-label="Eliminar {{ $label }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </button>
    @endif
</div>
