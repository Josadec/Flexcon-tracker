<div>
    @if ($show)
        <x-ui-modal wire:key="modal-production-statuses" title="Estados de producción"
            subtitle="Catálogo compartido por mesas, semi-automáticos y máquinas. Los cambios aplican en los tres."
            close="close" maxWidth="4xl">

            @if ($feedback)
                <x-ui.note :tone="$feedbackTone === 'danger' ? 'danger' : 'success'">{{ $feedback }}</x-ui.note>
            @endif

            {{-- Alta / edición --}}
            @if ($showForm)
                <x-ui.section :title="$editingId ? 'Editar estado' : 'Nuevo estado'"
                    hint="El nombre debe ser único; el orden decide en qué posición aparece en los desplegables.">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,2fr)_8rem_7rem]">
                        <x-ui.field label="Nombre" required :error="$errors->first('name')">
                            <input wire:model="name" type="text" class="w-full" placeholder="Ej: En proceso">
                        </x-ui.field>

                        <x-ui.field label="Color" required
                            hint="Se usa en las etiquetas." :error="$errors->first('color')">
                            <div class="flex items-center gap-2">
                                <input wire:model.live="color" type="color"
                                    class="h-10 w-12 shrink-0 cursor-pointer rounded-lg border border-slate-300 bg-white p-1 dark:border-slate-600 dark:bg-slate-800"
                                    aria-label="Elegir color">
                                <input wire:model="color" type="text" class="w-full font-mono uppercase" maxlength="7">
                            </div>
                        </x-ui.field>

                        <x-ui.field label="Orden" required :error="$errors->first('order')">
                            <input wire:model="order" type="number" min="0" step="1"
                                class="w-full text-right tabular-nums">
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Descripción" optional class="mt-4" :error="$errors->first('description')">
                        <textarea wire:model="description" rows="2" class="w-full"
                            placeholder="Para qué sirve este estado..."></textarea>
                    </x-ui.field>

                    <x-ui.check label="Estado activo" class="mt-4"
                        hint="Sólo los activos aparecen al elegir el estado de una mesa o máquina.">
                        <input wire:model="active" type="checkbox">
                    </x-ui.check>

                    <div class="mt-4 flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end dark:border-slate-700">
                        <x-ui.btn variant="secondary" size="sm" wire:click="cancelForm">Cancelar</x-ui.btn>
                        <x-ui.btn variant="primary" size="sm" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                            {{ $editingId ? 'Guardar cambios' : 'Crear estado' }}
                        </x-ui.btn>
                    </div>
                </x-ui.section>
            @endif

            {{-- Catálogo --}}
            <x-ui.section title="Catálogo" hint="Ordenados como aparecen en los desplegables.">
                <x-slot:aside>
                    @unless ($showForm)
                        <x-ui.btn variant="success" size="sm" wire:click="startCreate">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Nuevo estado
                        </x-ui.btn>
                    @endunless
                </x-slot:aside>

                @if ($statuses->isNotEmpty())
                    <x-ui.table>
                        <x-slot:head>
                            <tr>
                                <x-ui.th class="w-16">Orden</x-ui.th>
                                <x-ui.th>Estado</x-ui.th>
                                <x-ui.th class="w-40">En uso</x-ui.th>
                                <x-ui.th class="w-28">Activo</x-ui.th>
                                <x-ui.th align="right" class="w-24">Acciones</x-ui.th>
                            </tr>
                        </x-slot:head>

                        @foreach ($statuses as $status)
                            @php
                                $inUse = $status->tables_count + $status->semi_automatics_count + $status->machines_count;
                            @endphp
                            <tr wire:key="status-{{ $status->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                <td class="px-4 py-3 tabular-nums text-slate-500 dark:text-slate-400">{{ $status->order }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="size-3 shrink-0 rounded-full ring-1 ring-inset ring-black/10"
                                            style="background-color: {{ $status->color }}" aria-hidden="true"></span>
                                        <span class="min-w-0">
                                            <span class="block font-semibold text-slate-900 dark:text-white">{{ $status->name }}</span>
                                            @if ($status->description)
                                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $status->description }}</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                                    @if ($inUse === 0)
                                        Sin usar
                                    @else
                                        {{ $status->tables_count }} mesas ·
                                        {{ $status->semi_automatics_count }} semi ·
                                        {{ $status->machines_count }} máquinas
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($status->active)
                                        <x-ui.badge tone="good" dot>Activo</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="neutral" dot>Inactivo</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    {{-- Eliminar sólo aparece si el estado no está en uso: así el
                                         botón visible siempre es un botón que funciona. --}}
                                    <x-ui.row-actions label="el estado {{ $status->name }}"
                                        :delete="$inUse === 0 ? 'delete('.$status->id.')' : null"
                                        deleteConfirm="¿Eliminar el estado «{{ $status->name }}»?">
                                        <x-ui.icon-btn tone="primary" label="Editar el estado {{ $status->name }}"
                                            wire:click="startEdit({{ $status->id }})">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </x-ui.icon-btn>
                                    </x-ui.row-actions>
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @else
                    <x-ui.empty icon="doc" title="Todavía no hay estados de producción"
                        hint="Sin estados no se puede indicar en qué punto del flujo trabaja una mesa o una máquina.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" wire:click="startCreate">Crear el primero</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                @endif
            </x-ui.section>

            <x-slot:note>
                Un estado en uso no se puede eliminar; desactívalo para que deje de ofrecerse sin perder el histórico.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="close">Cerrar</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
</div>
