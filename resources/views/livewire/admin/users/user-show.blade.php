@php
    $role = $user->roles->first()?->name;
    $roleTone = match ($role) {
        'admin' => 'accent',
        'employee' => 'neutral',
        null => 'warn',
        default => 'info',
    };
@endphp

<x-ui.page eyebrow="Administración" :title="$user->full_name" :subtitle="$user->email"
    back="{{ route('admin.users.index') }}" backLabel="Volver a usuarios">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.users.edit', $user) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar usuario
        </x-ui.btn>
    </x-slot:actions>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Ficha --}}
    <x-ui.section title="Información del usuario">
        <x-slot:aside>
            <div class="flex items-center gap-2">
                <x-ui.badge :tone="$roleTone">{{ $role ?? 'Sin rol' }}</x-ui.badge>
            </div>
        </x-slot:aside>

        <div class="mb-4 flex items-center gap-4">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-slate-100 text-lg font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-200"
                aria-hidden="true">{{ $user->initials }}</span>
            <div class="min-w-0">
                <p class="truncate text-base font-bold text-slate-900 dark:text-white">{{ $user->full_name }}</p>
                <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
            </div>
        </div>

        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Nombre completo" :value="$user->full_name" />
            <x-ui.kv label="Correo electrónico" :value="$user->email" />
            <x-ui.kv label="Cuenta" :value="$user->account ?: '—'" help="Clave corta con la que se le identifica en planta." />
            <x-ui.kv label="Rol" :value="$role ?? 'Sin rol asignado'" :tone="$role ? 'neutral' : 'warn'" />
            <x-ui.kv label="Alta" :value="$user->created_at?->format('d/m/Y H:i') ?? '—'" />
            <x-ui.kv label="Última actualización" :value="$user->updated_at?->format('d/m/Y H:i') ?? '—'" />
        </dl>

        @unless ($role)
            <x-ui.note tone="warn" class="mt-4">
                Este usuario no tiene rol asignado: puede iniciar sesión, pero no verá ningún módulo.
                Asígnale uno desde <strong>Editar usuario</strong>.
            </x-ui.note>
        @endunless
    </x-ui.section>

    {{-- Áreas a su cargo --}}
    <x-ui.section title="Áreas a su cargo"
        hint="Áreas en las que este usuario aparece como responsable.">

        @if ($user->areas->isNotEmpty())
            <x-ui.table>
                <x-slot:head>
                    <tr>
                        <x-ui.th>Área</x-ui.th>
                        <x-ui.th>Departamento</x-ui.th>
                    </tr>
                </x-slot:head>

                @foreach ($user->areas as $area)
                    <tr wire:key="area-{{ $area->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $area->name }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $area->department?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        @else
            <x-ui.empty icon="box" title="Sin áreas a su cargo"
                hint="El área se asigna desde la edición del usuario, y sólo aplica cuando el rol es Supervisor." />
        @endif
    </x-ui.section>
</x-ui.page>
