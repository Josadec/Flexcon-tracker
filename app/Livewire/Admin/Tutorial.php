<?php

namespace App\Livewire\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Tutorial extends Component
{
    public string $activeSection = 'admin';

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->hasRole('admin')) {
            $this->activeSection = 'admin';
        } elseif ($user->hasRole('Produccion')) {
            $this->activeSection = 'produccion';
        } elseif ($user->hasRole('Calidad')) {
            $this->activeSection = 'calidad';
        } elseif ($user->hasRole('Materiales')) {
            $this->activeSection = 'materiales';
        } elseif ($user->hasRole('Empaques')) {
            $this->activeSection = 'empaques';
        }
    }

    public function render(): View
    {
        return view('livewire.admin.tutorial', [
            'tabs' => $this->tabs(),
            'sectionMeta' => $this->sectionMeta(),
            'quickActions' => $this->quickActions(),
            'roleLabel' => $this->roleLabel(),
        ]);
    }

    protected function tabs(): array
    {
        return [
            ['key' => 'general', 'label' => 'General', 'color' => 'zinc', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            ['key' => 'admin', 'label' => 'Administración', 'color' => 'blue', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
            ['key' => 'produccion', 'label' => 'Producción', 'color' => 'orange', 'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z'],
            ['key' => 'calidad', 'label' => 'Calidad', 'color' => 'green', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['key' => 'materiales', 'label' => 'Materiales', 'color' => 'purple', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
            ['key' => 'empaques', 'label' => 'Empaques', 'color' => 'pink', 'icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4'],
        ];
    }

    protected function sectionMeta(): array
    {
        return [
            'general' => [
                'eyebrow' => 'Vista general',
                'title' => 'Empieza por el flujo completo del sistema',
                'description' => 'Usa esta sección para entender cómo se conectan compras, órdenes de trabajo, materiales, producción, calidad y empaques antes de entrar a tu módulo.',
            ],
            'admin' => [
                'eyebrow' => 'Rol actual: Administración',
                'title' => 'Administra catálogos, usuarios y operación',
                'description' => 'Aquí conviene empezar por Purchase Orders, Work Orders y usuarios. Desde ahí controlas la configuración base y el seguimiento operativo.',
            ],
            'produccion' => [
                'eyebrow' => 'Rol actual: Producción',
                'title' => 'Prioriza pesadas, kits y ejecución en piso',
                'description' => 'Esta guía te lleva por las acciones más frecuentes de Producción: capturar, revisar lotes y mantener el avance visible para el resto de áreas.',
            ],
            'calidad' => [
                'eyebrow' => 'Rol actual: Calidad',
                'title' => 'Enfócate en inspección, pesadas y liberación',
                'description' => 'Empieza por inspección y quality weighings. Es la ruta más rápida para aprobar, rechazar y documentar hallazgos sin perder trazabilidad.',
            ],
            'materiales' => [
                'eyebrow' => 'Rol actual: Materiales',
                'title' => 'Consulta surtimiento, listas y disponibilidad',
                'description' => 'El orden recomendado es revisar dashboard, surtimiento y listas preliminares para coordinar materiales sin atrasar producción.',
            ],
            'empaques' => [
                'eyebrow' => 'Rol actual: Empaques',
                'title' => 'Organiza empaque, Packing Slips y despacho',
                'description' => 'Primero revisa qué está listo para empacar, luego genera el FPL-10 y finalmente usa la cola de despacho y el monitor TV para seguimiento visual.',
            ],
        ];
    }

    protected function quickActions(): array
    {
        return [
            'general' => [
                ['label' => 'Listas preliminares', 'description' => 'Ver el flujo compartido entre áreas.', 'href' => route('admin.sent-lists.display')],
                ['label' => 'Monitor TV', 'description' => 'Abrir métricas en tiempo real.', 'href' => route('admin.sent-lists.tv')],
                ['label' => 'Dashboard', 'description' => 'Volver al panel principal.', 'href' => route('admin.dashboard')],
            ],
            'admin' => [
                ['label' => 'Purchase Orders', 'description' => 'Arranca por el módulo de entrada del proceso.', 'href' => route('admin.purchase-orders.index')],
                ['label' => 'Work Orders', 'description' => 'Gestiona la ejecución operativa.', 'href' => route('admin.work-orders.index')],
                ['label' => 'Usuarios', 'description' => 'Administra accesos y roles.', 'href' => route('admin.users.index')],
            ],
            'produccion' => [
                ['label' => 'Dashboard Producción', 'description' => 'Ve el estado actual del área.', 'href' => route('admin.production.index')],
                ['label' => 'Pesadas', 'description' => 'Captura y consulta producción.', 'href' => route('admin.production.weighings')],
                ['label' => 'Kits', 'description' => 'Revisa kits y trazabilidad.', 'href' => route('admin.kits.index')],
            ],
            'calidad' => [
                ['label' => 'Dashboard Calidad', 'description' => 'Resumen de pendientes y aprobaciones.', 'href' => route('admin.quality.index')],
                ['label' => 'Inspección', 'description' => 'Aprueba o rechaza lotes.', 'href' => route('admin.quality.inspection')],
                ['label' => 'Quality Weighings', 'description' => 'Registra pesadas de calidad.', 'href' => route('admin.quality.weighings')],
            ],
            'materiales' => [
                ['label' => 'Dashboard Materiales', 'description' => 'Consulta carga y prioridades.', 'href' => route('admin.materials.index')],
                ['label' => 'Gestión Materiales', 'description' => 'Opera el surtimiento del área.', 'href' => route('admin.materials.manage')],
                ['label' => 'Listas preliminares', 'description' => 'Coordina con las demás áreas.', 'href' => route('admin.sent-lists.display')],
            ],
            'empaques' => [
                ['label' => 'Dashboard Empaques', 'description' => 'Visualiza el trabajo listo para empacar.', 'href' => route('admin.packaging.index')],
                ['label' => 'Packing Slips', 'description' => 'Gestiona documentos FPL-10.', 'href' => route('admin.shipping-list.index')],
                ['label' => 'Shipping Queue', 'description' => 'Controla la salida final.', 'href' => route('admin.shipping.queue')],
            ],
        ];
    }

    protected function roleLabel(): string
    {
        return match ($this->activeSection) {
            'admin' => 'Administración',
            'produccion' => 'Producción',
            'calidad' => 'Calidad',
            'materiales' => 'Materiales',
            'empaques' => 'Empaques',
            default => 'General',
        };
    }
}
