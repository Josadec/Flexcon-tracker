<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La auditoría deja de morirse con el usuario.
 *
 * `audit_trails.user_id` tenía `onDelete('cascade')` y la pantalla de usuarios
 * hace un borrado real: eliminar a una persona borraba TODO su rastro. Con una
 * retención de 5 años por ISO eso es justo lo contrario de lo que hace falta.
 *
 * Dos cambios: la FK pasa a poner NULL en vez de arrastrar la fila, y se guarda
 * un duplicado del nombre y el correo en la propia entrada, para que siga
 * diciendo quién hizo qué aunque esa cuenta ya no exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            // Snapshot: el nombre de quien actuó, congelado en el momento.
            $table->string('user_name')->nullable()->after('user_id');
            $table->string('user_email')->nullable()->after('user_name');
        });

        // Rellenar el snapshot de lo que ya está registrado. Se hace con el
        // constructor de consultas y no con SQL a mano: el `UPDATE ... JOIN` de
        // MySQL no existe en SQLite, y las pruebas corren sobre SQLite.
        DB::table('users')->select('id', 'name', 'last_name', 'email')->orderBy('id')
            ->chunk(200, function ($usuarios) {
                foreach ($usuarios as $usuario) {
                    DB::table('audit_trails')
                        ->where('user_id', $usuario->id)
                        ->whereNull('user_name')
                        ->update([
                            'user_name' => trim($usuario->name.' '.($usuario->last_name ?? '')),
                            'user_email' => $usuario->email,
                        ]);
                }
            });

        // Filas cuyo usuario ya no existe: la FK nueva no las admitiría.
        $idsVigentes = DB::table('users')->pluck('id');
        DB::table('audit_trails')
            ->whereNotNull('user_id')
            ->when($idsVigentes->isNotEmpty(), fn ($q) => $q->whereNotIn('user_id', $idsVigentes))
            ->update(['user_id' => null]);

        Schema::table('audit_trails', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('audit_trails', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('audit_trails', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Volver a `cascade` obligaría a borrar las entradas sin usuario, que es
        // exactamente lo que esta migración vino a impedir: se dejan huérfanas.
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_name', 'user_email']);
        });

        Schema::table('audit_trails', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
