{{--
    Modal de administración de estados de WO.

    Es la ÚNICA forma de administrar el catálogo de estados: la pantalla
    /admin/statuses-wo y su entrada de menú se eliminaron. Vive dentro de la
    pantalla donde los estados se usan (WO show) para no sacar al empleado del
    WO que está mirando. Misma anatomía de modal que el alta de precios de la
    ficha de la parte.
--}}
<div>
    @if ($show)
        @php
            $total   = count($rows);
            $inUse   = collect($rows)->where('work_orders_count', '>', 0)->count();
            $canEdit = auth()->user()?->can(\App\Livewire\Admin\StatusesWO\StatusWOManager::PERMISSION_EDIT) ?? false;
            $canAdd  = auth()->user()?->can(\App\Livewire\Admin\StatusesWO\StatusWOManager::PERMISSION_CREATE) ?? false;
            $canDrop = auth()->user()?->can(\App\Livewire\Admin\StatusesWO\StatusWOManager::PERMISSION_DELETE) ?? false;
        @endphp

        <x-ui-modal title="Estados de Work Order"
            subtitle="Cambia el color, el nombre o la nota de cada estado. Se aplica en todas las pantallas donde se muestra la píldora del estado."
            close="close" maxWidth="5xl">

            <x-slot:context>
                <x-ui-modal.ctx label="Estados en el catálogo" :value="$total" />
                <x-ui-modal.ctx label="Estados usados por alguna WO" :value="$inUse" />
                <x-ui-modal.ctx label="Estados que se pueden eliminar" :value="$total - $inUse" />
                <x-ui-modal.ctx label="Dónde se ve el color" value="WO · Manage PO · Lista de envío" />
            </x-slot:context>

            @if ($feedback)
                <x-ui.note :tone="$feedbackTone">{{ $feedback }}</x-ui.note>
            @endif

            {{-- 1. Colores y datos de los estados existentes --}}
            <x-ui.section step="1" title="Colores de los estados"
                hint="El color es el fondo de la píldora del estado; el texto va en blanco.">

                @if ($total === 0)
                    <x-ui.empty icon="box" title="Todavía no hay estados de WO"
                        hint="Sin estados no se puede clasificar ninguna orden de trabajo." />
                @else
                    <div class="space-y-3">
                        @foreach ($rows as $i => $row)
                            <div wire:key="statuswo-row-{{ $row['id'] }}"
                                class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">

                                <div class="flex items-start gap-3">
                                    <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-3">
                                        <x-ui.field label="Nombre" required
                                            :error="$errors->first('rows.'.$i.'.name')">
                                            <input wire:model="rows.{{ $i }}.name" type="text"
                                                class="w-full" @disabled(! $canEdit)>
                                        </x-ui.field>

                                        <x-ui.field label="Color" required
                                            hint="Hexadecimal, p. ej. #3B82F6."
                                            :error="$errors->first('rows.'.$i.'.color')">
                                            <div class="flex items-center gap-2">
                                                <input wire:model.live.debounce.400ms="rows.{{ $i }}.color"
                                                    type="color" class="h-10 w-12 shrink-0 cursor-pointer p-1"
                                                    aria-label="Selector de color de {{ $row['name'] }}"
                                                    @disabled(! $canEdit)>
                                                <input wire:model.live.debounce.400ms="rows.{{ $i }}.color"
                                                    type="text" maxlength="7" spellcheck="false"
                                                    class="w-full font-mono uppercase"
                                                    aria-label="Código de color de {{ $row['name'] }}"
                                                    @disabled(! $canEdit)>
                                            </div>
                                        </x-ui.field>

                                        <x-ui.field label="Nota" optional
                                            hint="Para qué se usa este estado."
                                            :error="$errors->first('rows.'.$i.'.comments')">
                                            <input wire:model="rows.{{ $i }}.comments" type="text"
                                                class="w-full" @disabled(! $canEdit)>
                                        </x-ui.field>
                                    </div>

                                    @if ($canDrop)
                                        @php $inUseByWos = $row['work_orders_count'] > 0; @endphp
                                        <div class="pt-6">
                                            {{-- Deshabilitado, no oculto: así se ve que la acción existe y
                                                 por qué está bloqueada (la FK es RESTRICT). --}}
                                            <x-ui.icon-btn tone="danger"
                                                :label="$inUseByWos
                                                    ? 'No se puede eliminar ' . $row['name'] . ': lo usan ' . $row['work_orders_count'] . ' WOs'
                                                    : 'Eliminar el estado ' . $row['name']"
                                                :disabled="$inUseByWos"
                                                @class(['opacity-40' => $inUseByWos])
                                                wire:click="deleteStatus({{ $row['id'] }})"
                                                wire:confirm="¿Eliminar el estado «{{ $row['name'] }}»? Esta acción no se puede deshacer.">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </x-ui.icon-btn>
                                        </div>
                                    @endif
                                </div>

                                {{-- Vista previa: exactamente cómo se verá la píldora en el WO. --}}
                                <div class="mt-3 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3 dark:border-slate-700">
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Vista previa</span>
                                    <span class="inline-block rounded-full px-2.5 py-1 text-xs font-semibold text-white"
                                        style="background-color: {{ preg_match(\App\Models\StatusWO::COLOR_REGEX, $row['color']) ? $row['color'] : \App\Models\StatusWO::DEFAULT_COLOR }}">
                                        {{ $row['name'] !== '' ? $row['name'] : 'Sin nombre' }}
                                    </span>

                                    @if (\App\Models\StatusWO::colorIsLight($row['color']))
                                        <span class="text-[11px] font-medium text-amber-600 dark:text-amber-400">
                                            Color muy claro: el texto blanco de la píldora casi no se lee.
                                        </span>
                                    @endif

                                    <span class="ml-auto text-[11px] text-slate-400">
                                        {{ $row['work_orders_count'] }}
                                        {{ $row['work_orders_count'] === 1 ? 'WO usa este estado' : 'WOs usan este estado' }}
                                    </span>

                                    @if ($canEdit)
                                        <x-ui.btn variant="ghost" size="sm" wire:click="resetRowColor({{ $i }})">
                                            Color por defecto
                                        </x-ui.btn>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.section>

            {{-- 2. Alta de un estado nuevo --}}
            @if ($canAdd)
                <x-ui.section step="2" title="Agregar un estado"
                    hint="Sólo si el flujo necesita una etapa que hoy no existe.">
                    <x-slot:aside>
                        <x-ui.btn variant="{{ $showNewForm ? 'secondary' : 'success' }}" size="sm"
                            wire:click="toggleNewForm">
                            @if ($showNewForm)
                                Cancelar
                            @else
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Nuevo estado
                            @endif
                        </x-ui.btn>
                    </x-slot:aside>

                    @if ($showNewForm)
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-ui.field label="Nombre" required
                                :error="$errors->first('newName')">
                                <input wire:model="newName" type="text" class="w-full" placeholder="p. ej. En revisión">
                            </x-ui.field>

                            <x-ui.field label="Color" required
                                hint="Hexadecimal, p. ej. #3B82F6."
                                :error="$errors->first('newColor')">
                                <div class="flex items-center gap-2">
                                    <input wire:model.live.debounce.400ms="newColor" type="color"
                                        class="h-10 w-12 shrink-0 cursor-pointer p-1"
                                        aria-label="Selector de color del estado nuevo">
                                    <input wire:model.live.debounce.400ms="newColor" type="text" maxlength="7"
                                        spellcheck="false" class="w-full font-mono uppercase"
                                        aria-label="Código de color del estado nuevo">
                                </div>
                            </x-ui.field>

                            <x-ui.field label="Nota" optional
                                :error="$errors->first('newComments')">
                                <input wire:model="newComments" type="text" class="w-full">
                            </x-ui.field>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Vista previa</span>
                            <span class="inline-block rounded-full px-2.5 py-1 text-xs font-semibold text-white"
                                style="background-color: {{ preg_match(\App\Models\StatusWO::COLOR_REGEX, $newColor) ? $newColor : \App\Models\StatusWO::DEFAULT_COLOR }}">
                                {{ $newName !== '' ? $newName : 'Sin nombre' }}
                            </span>
                            <x-ui.btn variant="success" size="sm" wire:click="createStatus"
                                wire:loading.attr="disabled" wire:target="createStatus">
                                Crear estado
                            </x-ui.btn>
                        </div>
                    @else
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Hoy hay {{ $total }} {{ $total === 1 ? 'estado' : 'estados' }}. Un estado sólo se puede
                            eliminar mientras ninguna WO lo esté usando.
                        </p>
                    @endif
                </x-ui.section>
            @endif

            <x-slot:note>
                Al guardar, el color nuevo se ve de inmediato en este WO y en el resto de pantallas
                (listado de WOs, Lista de envío) sin recargar el catálogo.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="close">Cerrar</x-ui.btn>
                @if ($canEdit && $total > 0)
                    <x-ui.btn variant="primary" wire:click="saveAll"
                        wire:loading.attr="disabled" wire:target="saveAll">
                        Guardar cambios
                    </x-ui.btn>
                @endif
            </x-slot:footer>
        </x-ui-modal>
    @endif
</div>
