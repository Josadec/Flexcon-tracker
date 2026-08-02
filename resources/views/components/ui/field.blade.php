@props([
    'label'    => '',
    'for'      => null,
    'hint'     => null,   // ayuda breve bajo el campo (qué escribir, de dónde sale el dato)
    'required' => false,
    'optional' => false,
    'error'    => null,   // mensaje de validación ya resuelto (p.ej. $errors->first('campo'))
])

{{--
    Envoltura de un campo de captura: etiqueta, control, ayuda y error, siempre
    en el mismo orden y con el mismo espaciado.

    <x-ui.field label="Piezas pesadas" required hint="Sólo piezas buenas." :error="$errors->first('prodWeighedPieces')">
        <input type="number" wire:model="prodWeighedPieces" class="w-full">
    </x-ui.field>
--}}
<div {{ $attributes->class('min-w-0') }}>
    <label @if($for) for="{{ $for }}" @endif
        class="mb-1.5 flex items-baseline gap-1 text-xs font-semibold text-slate-700 dark:text-slate-200">
        <span>{{ $label }}</span>
        @if ($required)
            <span class="text-red-600 dark:text-red-400" title="Campo obligatorio">*</span>
        @elseif ($optional)
            <span class="font-normal text-slate-400 dark:text-slate-500">(opcional)</span>
        @endif
    </label>

    {{ $slot }}

    @if ($hint)
        <p class="mt-1 text-[11px] leading-4 text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif
    @if ($error)
        <p class="mt-1 flex items-start gap-1 text-[11px] font-medium leading-4 text-red-600 dark:text-red-400">
            <svg class="mt-px size-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <path fill-rule="evenodd" d="M18 10A8 8 0 11.999 10 8 8 0 0118 10zm-9 3a1 1 0 102 0 1 1 0 00-2 0zm.25-8.25a.75.75 0 011.5 0v4.5a.75.75 0 01-1.5 0v-4.5z" clip-rule="evenodd"/>
            </svg>
            <span>{{ $error }}</span>
        </p>
    @endif
</div>
