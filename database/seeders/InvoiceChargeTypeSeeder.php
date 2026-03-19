<?php

namespace Database\Seeders;

use App\Models\InvoiceChargeType;
use Illuminate\Database\Seeder;

class InvoiceChargeTypeSeeder extends Seeder
{
    /**
     * Carga los tipos de cargo fijo iniciales del modulo Invoice (FPL-12).
     *
     * Estos tres tipos corresponden a los cargos observados en todos los
     * Invoices analizados (Excel 2025 + PDF #01006 de marzo 2026).
     *
     * Decision P-12-02 RESUELTA (D-12-22):
     *   Los montos de los cargos fijos son EDITABLES por el Admin desde la UI
     *   de gestion de tipos de cargo (/admin/invoice-charge-types) sin necesidad
     *   de modificar codigo ni hacer despliegues. Este seeder solo establece los
     *   valores iniciales. El historial de montos cobrados queda preservado en los
     *   snapshots de invoice_items.unit_cost de cada Invoice emitido.
     *
     * Fuente de los montos iniciales:
     *   Invoice #01006 (March-11-2026) — el mas reciente confirmado por PDF real.
     *   Machine Maintenance: $1,200 (era $800 en Invoices de 2025).
     *   Administration Fee: $250.
     *   SHIPPING COST: $450.
     *
     * El seeder es idempotente: usa firstOrCreate para evitar duplicados
     * si se ejecuta mas de una vez (ej: en ambiente de desarrollo).
     */
    public function run(): void
    {
        $types = [
            [
                'code'           => 'machine_maintenance',
                'label'          => 'Machine Maintenance',
                'default_amount' => 1200.00,
                // Decision P-12-02 RESUELTA (D-12-22): $1,200 es el valor inicial del seeder,
                // basado en Invoice #01006 (el mas reciente confirmado por PDF real).
                // Este default_amount ES EDITABLE por el Admin desde la UI de gestion de
                // tipos de cargo (/admin/invoice-charge-types) sin necesidad de modificar
                // codigo ni hacer despliegues. Si el precio sube en el futuro, el Admin
                // actualiza este campo y todos los Invoices nuevos usaran el nuevo valor.
                // Los Invoices anteriores conservan su snapshot historico en invoice_items.unit_cost.
                'is_active'      => true,
                'always_include' => true,
                'sort_order'     => 1,
                'notes'          => 'Cargo fijo de mantenimiento de maquinaria. '
                                  . 'Valor inicial: $1,200 segun Invoice #01006 (marzo 2026). '
                                  . 'Era $800 en Invoices de 2025. '
                                  . 'EDITABLE por Admin desde /admin/invoice-charge-types. '
                                  . 'Decision P-12-02: el precio puede variar en el tiempo.',
            ],
            [
                'code'           => 'administration_fee',
                'label'          => 'Administration Fee',
                'default_amount' => 250.00,
                'is_active'      => true,
                'always_include' => true,
                'sort_order'     => 2,
                'notes'          => 'Cargo fijo de administracion. '
                                  . 'Confirmado en Invoice #01006 (marzo 2026).',
            ],
            [
                'code'           => 'shipping_cost',
                'label'          => 'SHIPPING COST',
                'default_amount' => 450.00,
                'is_active'      => true,
                // Decision: always_include = true por defecto. Pendiente P-12-09:
                // si el cliente confirma que hay envios sin este cargo, cambiar a false
                // y el usuario debera agregarlo manualmente en cada Invoice que aplique.
                'always_include' => true,
                'sort_order'     => 3,
                'notes'          => 'Cargo de envio. '
                                  . 'Confirmado en Invoice #01006 (marzo 2026). '
                                  . 'Considerar cambiar always_include=false si no aplica en envios locales (P-12-09 pendiente).',
            ],
        ];

        foreach ($types as $data) {
            InvoiceChargeType::firstOrCreate(
                ['code' => $data['code']],
                $data
            );
        }

        $this->command->info('InvoiceChargeTypeSeeder: 3 tipos de cargo iniciales cargados.');
    }
}
