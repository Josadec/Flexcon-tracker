<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unifica los nombres de rol a la convención canónica ÚNICA (español Capitalizado),
 * usada por rutas, sidebar, show.blade y User::sentListDepartments().
 *
 * Renombra los roles heredados en inglés / minúsculas y, cuando el rol canónico ya
 * existe, FUSIONA: reasigna usuarios (model_has_roles) y permisos (role_has_permissions)
 * al rol canónico y elimina el duplicado. Evita filas pivote duplicadas.
 */
return new class extends Migration
{
    /**
     * old => canonical. 'inspeccion'/'inspection' se fusionan en Calidad
     * (Calidad cubre Inspección y Calidad — etapas contiguas).
     */
    private array $map = [
        'Production'  => 'Produccion',
        'production'  => 'Produccion',
        'Shipping'    => 'Empaques',
        'shipping'    => 'Empaques',
        'Materials'   => 'Materiales',
        'materials'   => 'Materiales',
        'Quality'     => 'Calidad',
        'quality'     => 'Calidad',
        'inspection'  => 'Calidad',
        'inspeccion'  => 'Calidad',
        'Inspeccion'  => 'Calidad',
    ];

    public function up(): void
    {
        foreach ($this->map as $old => $canonical) {
            $oldRole = DB::table('roles')->where('name', $old)->where('guard_name', 'web')->first();
            if (! $oldRole) {
                continue;
            }

            $canonicalRole = DB::table('roles')->where('name', $canonical)->where('guard_name', 'web')->first();

            // Caso simple: el canónico no existe → renombrar en sitio.
            if (! $canonicalRole) {
                DB::table('roles')->where('id', $oldRole->id)->update([
                    'name'       => $canonical,
                    'updated_at' => now(),
                ]);
                continue;
            }

            // Caso fusión: mover asignaciones al rol canónico y borrar el duplicado.
            $this->mergeRole($oldRole->id, $canonicalRole->id);
            DB::table('roles')->where('id', $oldRole->id)->delete();
        }

        // Invalida el cache de permisos/roles de Spatie tras la reescritura.
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function mergeRole(int $fromId, int $toId): void
    {
        // Usuarios (model_has_roles): copiar los que no dupliquen.
        $assignments = DB::table('model_has_roles')->where('role_id', $fromId)->get();
        foreach ($assignments as $a) {
            $exists = DB::table('model_has_roles')
                ->where('role_id', $toId)
                ->where('model_type', $a->model_type)
                ->where('model_id', $a->model_id)
                ->exists();

            if (! $exists) {
                DB::table('model_has_roles')->insert([
                    'role_id'    => $toId,
                    'model_type' => $a->model_type,
                    'model_id'   => $a->model_id,
                ]);
            }
        }
        DB::table('model_has_roles')->where('role_id', $fromId)->delete();

        // Permisos (role_has_permissions): copiar los que no dupliquen.
        $perms = DB::table('role_has_permissions')->where('role_id', $fromId)->get();
        foreach ($perms as $p) {
            $exists = DB::table('role_has_permissions')
                ->where('role_id', $toId)
                ->where('permission_id', $p->permission_id)
                ->exists();

            if (! $exists) {
                DB::table('role_has_permissions')->insert([
                    'role_id'       => $toId,
                    'permission_id' => $p->permission_id,
                ]);
            }
        }
        DB::table('role_has_permissions')->where('role_id', $fromId)->delete();
    }

    public function down(): void
    {
        // Irreversible de forma segura: la fusión pierde la separación original de roles.
        // No-op intencional; recrear roles heredados vía seeders si se requiere.
    }
};
