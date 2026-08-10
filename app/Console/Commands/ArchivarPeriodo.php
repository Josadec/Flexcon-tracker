<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Exporta a fichero la información de un año.
 *
 * Es el paso previo obligatorio a cualquier borrado, y el entregable que se le
 * enseña a un auditor: «esto es lo de 2021, aquí está». El comando NO borra
 * nada — sacar el archivo y borrar son dos decisiones distintas, y la segunda
 * es humana.
 */
class ArchivarPeriodo extends Command
{
    protected $signature = 'flexcon:archivar {anio : Año a exportar, p. ej. 2021}';

    protected $description = 'Exporta a JSON la información de un año, antes de cualquier borrado. No borra nada.';

    public function handle(): int
    {
        $anio = (int) $this->argument('anio');

        if ($anio < 2000 || $anio > (int) now()->year) {
            $this->error('Año fuera de rango.');

            return self::FAILURE;
        }

        $carpeta = trim(config('retencion.ruta_archivo', 'archivo'), '/')."/{$anio}";
        $desde = "{$anio}-01-01 00:00:00";
        $hasta = "{$anio}-12-31 23:59:59";
        $totalRegistros = 0;

        foreach (config('retencion.entidades', []) as $etiqueta => $def) {
            if (! Schema::hasTable($def['tabla']) || ! Schema::hasColumn($def['tabla'], $def['fecha'])) {
                continue;
            }

            $filas = DB::table($def['tabla'])
                ->whereBetween($def['fecha'], [$desde, $hasta])
                ->get();

            if ($filas->isEmpty()) {
                continue;
            }

            Storage::put(
                "{$carpeta}/{$def['tabla']}.json",
                $filas->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $totalRegistros += $filas->count();
            $this->line("  · {$etiqueta}: ".number_format($filas->count()).' registros');
        }

        if ($totalRegistros === 0) {
            $this->warn("No hay información de {$anio} que archivar.");

            return self::SUCCESS;
        }

        // Un índice legible junto a los datos: sin esto, dentro de tres años
        // nadie sabrá qué contiene la carpeta ni con qué criterio se hizo.
        Storage::put("{$carpeta}/_archivo.txt", implode("\n", [
            "Archivo de {$anio} — Flexcon Tracker",
            'Generado el '.now()->format('d/m/Y H:i'),
            'Registros exportados: '.number_format($totalRegistros),
            '',
            'Este archivo es una COPIA. No se ha borrado nada de la base de datos.',
            'La política de conservación está en docs/RETENCION.md.',
        ]));

        $this->newLine();
        $this->info('Archivo generado: '.Storage::path($carpeta));
        $this->line('Nada se ha borrado de la base de datos.');

        return self::SUCCESS;
    }
}
