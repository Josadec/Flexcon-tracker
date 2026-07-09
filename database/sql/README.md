# Reset de Corrida — Flexcon Tracker

Guía para **reiniciar desde 0** toda la parte operativa (PO, WO, Sent Lists, Lotes,
Kits, Pesajes, Empaque, Packing Slips, Invoices) **conservando** la configuración
maestra (usuarios, máquinas, turnos, piezas, precios, etc.).

> ⚠️ **Esto es irreversible.** Haz el respaldo del Paso 1 sí o sí antes de continuar.

---

## ¿Qué se conserva y qué se limpia?

| Se **CONSERVA** (maestros) | Se **LIMPIA** (la corrida) |
|---|---|
| Usuarios, roles/permisos, firmas de usuario | Purchase Orders + PDFs firmados |
| Máquinas, mesas, semiautomáticos, áreas, departamentos | Work Orders + logs de estado |
| Turnos, break times, holidays | Sent Lists (listas de envío) + rechazos |
| Piezas, precios, estándares, configuraciones | Lotes, Crimp Lots + logs |
| Catálogos (statuses_wo, invoice_charge_types) | Kits + incidencias + ciclos de aprobación |
| Horas extra (`over_times`) | Pesajes (quality, packaging, crimp, piece) |
| Auditoría (`audit_trails`) | Packing Slips + items |
| | Invoices + items |
| | Contador de producción (`productions`) |

### Sobre los archivos (storage)
- **Invoices, Packing Slips y Shipping Lists NO se guardan en disco** — la app los
  genera al vuelo cada vez que se descargan. Al truncar sus tablas ya quedan en 0,
  no hay archivos que borrar.
- **Los únicos archivos físicos** que se acumulan son los **PDFs de Purchase Orders**
  (subidos y firmados) en `storage/app/public/purchase-orders/`.
- La carpeta `storage/app/public/signatures/` **NO se toca**: contiene las firmas
  guardadas de los usuarios (dato maestro).

---

## Archivos en esta carpeta

| Archivo | Qué hace | Dónde corre |
|---|---|---|
| `reset_corrida.sql` | Limpia los **datos** de la base de datos | MySQL / phpMyAdmin |
| `reset_storage.ps1` | Borra los **PDFs de PO** en `storage/` | PowerShell |

---

## Cómo ejecutarlo (todo desde PowerShell)

Abre PowerShell y ubícate en la raíz del proyecto:

```powershell
cd c:\xampp\htdocs\Laravel\Flexcon-tracker
```

### Paso 1 — Respaldo (obligatorio)

```powershell
& "c:\xampp\mysql\bin\mysqldump.exe" -u root "flexcon_db" > backup_antes_de_reset.sql
```

Verifica que el archivo `backup_antes_de_reset.sql` se haya creado y **no esté vacío**
antes de seguir.

### Paso 2 — Limpiar los datos (SQL)

```powershell
Get-Content database\sql\reset_corrida.sql | & "c:\xampp\mysql\bin\mysql.exe" -u root "flexcon_db"
```

### Paso 3 — Limpiar los archivos físicos (storage)

```powershell
powershell -ExecutionPolicy Bypass -File database\sql\reset_storage.ps1
```

---

## Ajustes según tu entorno

1. **Nombre de la base de datos:** los comandos usan `flexcon-tracker`.
   Si tu `DB_DATABASE` (en el archivo `.env`) es otro, cámbialo en los Pasos 1 y 2.

2. **Contraseña de MySQL:** XAMPP por default usa usuario `root` **sin contraseña**
   (así están los comandos). Si tu `.env` tiene `DB_PASSWORD`, agrega `-p`:

   ```powershell
   Get-Content database\sql\reset_corrida.sql | & "d:\xampp\mysql\bin\mysql.exe" -u root -p "flexcon-tracker"
   ```

   Te pedirá la contraseña al ejecutar (no la escribas dentro del comando).

3. **Ruta de storage distinta:** si tu proyecto guarda archivos fuera de
   `storage/app/public`, ajusta la variable `$publicRoot` dentro de `reset_storage.ps1`.

---

## Alternativa: correr el SQL desde phpMyAdmin

Si prefieres no usar la terminal para el Paso 2:

1. Abre phpMyAdmin y selecciona la base de datos `flexcon-tracker`.
2. Ve a la pestaña **SQL**.
3. Abre `reset_corrida.sql`, copia **todo** su contenido y pégalo en el cuadro.
4. Clic en **Continuar**.

El Paso 1 (respaldo) puedes hacerlo desde la pestaña **Exportar**, y el Paso 3
(storage) igual se corre desde PowerShell.

---

## Resultado

Después de los 3 pasos, todos los contadores arrancan de nuevo: el próximo PO, WO,
lote, packing slip e invoice vuelven a empezar en su número inicial, con toda la
configuración maestra intacta.
