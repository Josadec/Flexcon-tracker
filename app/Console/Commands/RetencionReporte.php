<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Informa qué información ha superado el plazo de conservación.
 *
 * NO BORRA NADA, y esa es la decisión: el ISO obliga a conservar 5 años, no a
 * destruir al quinto año y un día. Este comando sirve para saber qué hay y
 * poder demostrar que se sabe, que es lo que pregunta un auditor.
 */
class RetencionReporte extends Command
{
    protected $signature = 'flexcon:retencion-reporte {--anios= : Años a considerar (por defecto, los de config/retencion.php)}';

    protected $description = 'Informa qué información supera el plazo de conservación. No borra nada.';

    public function handle(): int
    {
        $anios = (int) ($this->option('anios') ?: config('retencion.anios', 5));
        $corte = now()->subYears($anios);

        $this->info("Política de conservación: {$anios} años.");
        $this->line('Se considera antiguo lo anterior al '.$corte->format('d/m/Y').'.');
        $this->newLine();

        $filas = [];
        $totalAntiguo = 0;

        foreach (config('retencion.entidades', []) as $etiqueta => $def) {
            if (! Schema::hasTable($def['tabla']) || ! Schema::hasColumn($def['tabla'], $def['fecha'])) {
                continue;
            }

            $total = DB::table($def['tabla'])->count();
            $antiguos = DB::table($def['tabla'])->whereNotNull($def['fecha'])
                ->where($def['fecha'], '<', $corte)->count();
            $masAntiguo = DB::table($def['tabla'])->whereNotNull($def['fecha'])->min($def['fecha']);

            $totalAntiguo += $antiguos;

            $filas[] = [
                $etiqueta,
                number_format($total),
                number_format($antiguos),
                $masAntiguo ? \Illuminate\Support\Carbon::parse($masAntiguo)->format('d/m/Y') : '—',
            ];
        }

        $this->table(['Información', 'Registros', 'Fuera de plazo', 'Más antiguo'], $filas);

        if ($totalAntiguo === 0) {
            $this->info('Nada supera el plazo: no hay que archivar nada todavía.');
        } else {
            $this->warn("Hay {$totalAntiguo} registros fuera del plazo de {$anios} años.");
            $this->line('Para exportarlos antes de tocar nada: php artisan flexcon:archivar '.$corte->year);
        }

        $this->newLine();
        $this->line('Este comando NO borra. El borrado automático está desactivado a propósito');
        $this->line('(ver config/retencion.php y docs/RETENCION.md).');

        return self::SUCCESS;
    }
}
