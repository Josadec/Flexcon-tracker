# Implementación — Cambio de Constraint en `price_tiers`

**Fecha:** 2026-04-19  
**Implementado por:** Mauricio Belmonte  
**Referencia:** `02_analisis_impacto_constraint_price_tiers.md`  
**Regla de desempate acordada:** Opción A — menor `tier_price` (favorable al cliente)

---

## Estado de los 6 Cambios Requeridos

| # | Componente | Estado | Detalle |
|---|-----------|--------|---------|
| 1 | Nueva migración de constraint | ✅ Completado | `2026_04_19_000000_update_price_tiers_unique_constraint.php` |
| 2 | `getPriceForQuantity()` — regla de desempate | ✅ Completado | `app/Models/Price.php` línea 152 |
| 3 | Auditoría de datos en producción | ✅ Automatizado | La migración valida duplicados y lanza excepción descriptiva si los hay |
| 4 | `getTiersArrayAttribute()` — rangos duplicados | ✅ Completado | `app/Models/Price.php` línea 230 |
| 5 | `PriceSeeder` — nuevo criterio `firstOrCreate` | ✅ Completado | `database/seeders/PriceSeeder.php` línea 131 |
| 6 | CSV `03_price_tiers_final.csv` — ids 130, 135, 145 | ✅ Resuelto por código | La Opción A aplica automáticamente el menor precio en rangos duplicados |

---

## Archivos Modificados

### Archivo nuevo
- `database/migrations/2026_04_19_000000_update_price_tiers_unique_constraint.php`

### Archivos editados
- `app/Models/Price.php`
- `database/seeders/PriceSeeder.php`

---

## Detalle de cada Cambio

### 1. Nueva migración

**Archivo:** `database/migrations/2026_04_19_000000_update_price_tiers_unique_constraint.php`

- `up()`: valida primero que no existan `tier_price` duplicados por `price_id`. Si los hay, lanza `RuntimeException` con el detalle de los conflictos. Si la BD está limpia, elimina `UNIQUE(price_id, min_quantity, max_quantity)` y crea `UNIQUE(price_id, tier_price)`.
- `down()`: rollback al constraint original.

---

### 2. `Price::getPriceForQuantity()` — Regla de desempate

**Archivo:** `app/Models/Price.php` — línea 152  
**Cambio:** Se agregó `->orderBy('tier_price', 'asc')` antes de `.first()`.

```php
// Antes
->first();

// Después — desempate explícito: menor precio gana (Opción A)
->orderBy('tier_price', 'asc')
->first();
```

**Impacto directo:** `PurchaseOrderService`, `POCreate`, `POEdit`, `InvoiceFromPackingSlipService` — todos heredan la corrección sin cambios adicionales.

---

### 3. `Price::getTiersArrayAttribute()` — Consistencia con Opción A

**Archivo:** `app/Models/Price.php` — línea 230  
**Cambio:** Reemplazado `->first()` por `->filter()->sortBy('tier_price')->first()`.

```php
// Antes
$tier = $this->tiers->first(fn ($t) => $t->min_quantity == $tierConfig['min']);

// Después — consistente con la regla de desempate
$tier = $this->tiers
    ->filter(fn ($t) => $t->min_quantity == $tierConfig['min'])
    ->sortBy('tier_price')
    ->first();
```

**Limitación documentada:** el formulario de edición solo muestra un tier por rango (el de menor precio). Los tiers duplicados del mismo rango no son editables desde la UI actual.

---

### 4. `PriceSeeder::createTiersForPrice()` — Nuevo criterio de unicidad

**Archivo:** `database/seeders/PriceSeeder.php` — línea 131  
**Cambio:** `firstOrCreate` ahora busca por `tier_price` (nuevo criterio único) y usa `min/max` como valores por defecto.

```php
// Antes — buscaba por (min, max): criterio obsoleto
$price->tiers()->firstOrCreate(
    ['min_quantity' => $tier['min'], 'max_quantity' => $tier['max']],
    ['tier_price' => $tierPrice]
);

// Después — busca por (tier_price): alineado con el nuevo constraint
$price->tiers()->firstOrCreate(
    ['tier_price' => $tierPrice],
    ['min_quantity' => $tier['min'], 'max_quantity' => $tier['max']]
);
```

---

## Pasos Operacionales Pendientes

Estos pasos no son código — requieren ejecución manual en cada entorno:

```
[ ] 1. Staging: php artisan migrate
        → Si falla: revisar price_id y tier_price duplicados reportados en el mensaje de error
        → Resolver duplicados manualmente y volver a correr

[ ] 2. Staging: importar 03_price_tiers_final.csv
        → Verificar manualmente price_ids 130, 135 y 145

[ ] 3. Validación funcional en staging
        → Crear una PO con una parte que tenga rangos duplicados
        → Confirmar que el precio mostrado es el menor tier_price

[ ] 4. Producción: php artisan migrate
[ ] 5. Producción: importar 03_price_tiers_final.csv
```

---

## Consulta SQL de Auditoría Previa (ejecutar antes de migrar en cada entorno)

```sql
SELECT price_id, tier_price, COUNT(*) AS duplicados
FROM price_tiers
GROUP BY price_id, tier_price
HAVING COUNT(*) > 1;
```

Si devuelve filas → resolverlas antes de correr `php artisan migrate`.

---

*Generado el 2026-04-19 como cierre de la implementación descrita en `02_analisis_impacto_constraint_price_tiers.md`.*
