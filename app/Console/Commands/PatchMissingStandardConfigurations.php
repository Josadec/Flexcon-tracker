<?php

namespace App\Console\Commands;

use App\Models\Standard;
use App\Models\StandardConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PatchMissingStandardConfigurations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'standards:patch-missing-configs {--dry-run : Ejecutar en modo simulacion sin modificar datos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Busca Standards sin configuraciones y les crea una placeholder (persons_required=1, units_per_hour=1, is_default=true)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('[DRY-RUN] Modo simulacion activo. No se modificaran datos.');
            $this->newLine();
        }

        $this->info('Buscando Standards sin configuraciones...');
        $this->newLine();

        // Obtener todos los standard_id que ya tienen al menos una configuracion
        $standardsWithConfigs = DB::table('standard_configurations')
            ->distinct()
            ->pluck('standard_id')
            ->toArray();

        // Buscar standards sin configuraciones (excluye soft-deleted)
        $standardsWithoutConfigs = Standard::whereNotIn('id', $standardsWithConfigs)
            ->with('part')
            ->get();

        $count = $standardsWithoutConfigs->count();

        $this->info("Standards sin configuraciones encontrados: {$count}");
        $this->newLine();

        if ($count === 0) {
            $this->info('No hay standards pendientes de configurar. Todo esta al dia.');
            return Command::SUCCESS;
        }

        // Tabla de preview (primeras 10 filas)
        $preview = $standardsWithoutConfigs->take(10);
        $this->table(
            ['Standard ID', 'Part ID', 'Part Number', 'Active', 'Is Migrated'],
            $preview->map(fn($s) => [
                $s->id,
                $s->part_id,
                $s->part?->number ?? 'N/A',
                $s->active ? 'Si' : 'No',
                $s->is_migrated ? 'Si' : 'No',
            ])->toArray()
        );

        if ($count > 10) {
            $this->line("... y " . ($count - 10) . " mas.");
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn("[DRY-RUN] Se crearian {$count} configuraciones placeholder.");
            $this->warn('[DRY-RUN] Ejecute sin --dry-run para aplicar los cambios.');
            return Command::SUCCESS;
        }

        // Confirmar si no es dry-run y hay muchos registros
        if ($count > 50 && !$this->confirm("Se van a crear {$count} configuraciones placeholder. Continuar?", true)) {
            $this->warn('Operacion cancelada por el usuario.');
            return Command::SUCCESS;
        }

        $this->info('Creando configuraciones placeholder...');

        $created = 0;
        $errors   = 0;

        DB::beginTransaction();

        try {
            foreach ($standardsWithoutConfigs as $standard) {
                StandardConfiguration::create([
                    'standard_id'      => $standard->id,
                    'workstation_type' => StandardConfiguration::TYPE_MANUAL,
                    'workstation_id'   => null,
                    'persons_required' => 1,
                    'units_per_hour'   => 1,
                    'is_default'       => true,
                    'notes'            => 'Pendiente configurar - datos fuente vacios en CSV original',
                ]);
                $created++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error durante la creacion de configuraciones: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info("Configuraciones placeholder creadas: {$created}");

        if ($errors > 0) {
            $this->warn("Errores encontrados: {$errors}");
        }

        // Verificacion final
        $remaining = Standard::whereNotIn(
            'id',
            DB::table('standard_configurations')->distinct()->pluck('standard_id')->toArray()
        )->count();

        $this->newLine();
        $this->info("Verificacion: Standards sin configuraciones restantes: {$remaining}");

        return Command::SUCCESS;
    }
}
