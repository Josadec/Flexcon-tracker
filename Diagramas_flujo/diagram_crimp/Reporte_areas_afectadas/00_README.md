# Reporte de Impacto — Reajuste del proceso de CRIMP

> **Tipo:** Análisis técnico de impacto (no contiene código aplicado).
> **Fecha:** 2026-06-06
> **Base del análisis:** Diagramas `1`–`5` de `diagram_crimp/` + `Notas-new-implementacion/Flujo.mkd`,
> mapeados contra el código real de Flexcon Tracker (Laravel 12 · Livewire 3 · Flux 2 · Volt · Tailwind · Spatie · dompdf).
> **Regla de oro de todo el cambio:** la lógica nueva aplica **solo a partes con CRIMP** (`parts.is_crimp = true`).
> Las partes **sin CRIMP conservan exactamente la lógica actual de "lote"**.

---

## Índice del reporte

| Archivo | Contenido |
| ------- | --------- |
| `00_README.md` (este) | Resumen ejecutivo, concepto "vieja forma vs nueva forma", mapa de módulos. |
| `01_modulos_afectados.md` | Análisis **módulo por módulo**: qué hace hoy, qué cambia, archivos exactos. |
| `02_tablas_modelos_rutas.md` | Inventario consolidado: **tablas a corregir/crear, modelos, rutas y vistas**. |
| `03_riesgos_y_decisiones.md` | Riesgos técnicos, preguntas abiertas y checklist de implementación. |

---

## 1. Resumen ejecutivo

El reajuste **no es un cambio cosmético de nombres**: redefine el modelo de datos del flujo de CRIMP.
En resumen, para partes con CRIMP:

1. **Renombrar "Lote" → "Viajero"** (visual y funcional) solo cuando `part.is_crimp = true`.
2. **Eliminar el concepto de Kit** del flujo de CRIMP. Hoy CRIMP depende del `Kit` (relación `kit_lot`)
   en Inspección, pesadas y semáforos. La nueva forma asocia **lotes de CRIMP directamente al viajero**.
3. **Nuevo campo "lote de fabricante"** por cada lote de CRIMP.
4. **Pesadas de Producción y Calidad sin selección de kit** (se comportan como las partes sin CRIMP).
5. **Empaque con dos pesadas separadas**: piezas/"manguitas" y CRIMP (dos CRUD nuevos), con cantidades
   completadas y sobrantes por separado.
6. **Nuevo correo "Empaque terminado CRIMP Viajero"** — **no existe infraestructura de correo todavía**
   (no hay `app/Mail` ni `app/Notifications` en el proyecto).

> El punto más delicado es **no romper el flujo de partes sin CRIMP**, porque hoy `Lot`, `Weighing`,
> `QualityWeighing` y `PackagingRecord` son **compartidos** por ambos flujos. Toda la lógica nueva debe
> estar condicionada por `part.is_crimp`.

---

## 2. La "vieja forma" vs la "nueva forma"

### 2.1 Modelo de datos actual (vieja forma)

```
PurchaseOrder ──> WorkOrder ──> Lot (lots)                 ← unidad base, para CRIMP y NO-CRIMP
                       │           │
                       │           ├──> Weighing (weighings)        [tiene kit_id]
                       │           ├──> QualityWeighing             [tiene kit_id]
                       │           ├──> PackagingRecord             [tiene kit_id, UN solo "packed_pieces"]
                       │           └──> kit_lot ⇄ Kit (kits)        ← SOLO CRIMP
                       └──> Kit (kits)
```

- **CRIMP:** el `Lot` se agrupa en un `Kit` (pivot `kit_lot`). La Inspección, las pesadas y los
  semáforos dependen del **estado del Kit** (`preparing → ready → released …`).
  Ver `Lot::canBeInspected()` ([app/Models/Lot.php:484](../../../app/Models/Lot.php#L484)).
- **NO-CRIMP:** "lote = kit". No hay registro Kit; se usa `lots.material_status`.

### 2.2 Modelo de datos propuesto (nueva forma, solo CRIMP)

```
PurchaseOrder ──> WorkOrder ──> Viajero (= Lot, tabla lots)  renombrado en UI para crimp
                                   │
                                   ├──> Lote de CRIMP (NUEVA tabla crimp_lots)  [crimp_lot_number,
                                   │        lote_fabricante, quantity, comments]   1 viajero → N lotes
                                   ├──> Pesada de Piezas/manguitas (NUEVA tabla en Empaque)
                                   ├──> Pesada de CRIMP (NUEVA tabla en Empaque)
                                   ├──> Weighing (producción, SIN kit_id)
                                   └──> QualityWeighing (calidad, SIN kit_id)

   ⚠️  El "Kit" SE ELIMINA del flujo crimp: lo sustituye la tabla nueva crimp_lots.
       kits / kit_lot / kit_approval_cycles / kit_incidents quedan solo como historial.
```

### 2.3 Tabla de equivalencias de conceptos

| Concepto | Vieja forma (CRIMP) | Nueva forma (CRIMP) | NO-CRIMP (sin cambios) |
| -------- | ------------------- | ------------------- | ---------------------- |
| Unidad principal | `Lot` ("Lote") agrupado en `Kit` | `Lot` mostrado como **"Viajero"** | `Lot` ("Lote") |
| Sub-unidad | `Kit` (agrupa lotes) | **Lote de CRIMP** = **nueva tabla `crimp_lots`** (cuelga del viajero); el `Kit` se elimina | — |
| Lote de fabricante | no existe | **nueva columna `crimp_lots.lote_fabricante`** (texto manual) | n/a |
| Liberación de material | estado del `Kit` (`released`) | estado a nivel **viajero** (como `material_status`) | `material_status` |
| Pesada producción | `Weighing` + `kit_id` | `Weighing` **sin** `kit_id` | `Weighing` sin kit |
| Pesada calidad | `QualityWeighing` + `kit_id` | `QualityWeighing` **sin** `kit_id` | sin kit |
| Empaque | 1 `PackagingRecord` (`packed_pieces`) | **2 pesadas**: piezas + CRIMP, con sobrantes | 1 `PackagingRecord` |
| Cierre / decisión | D1/D2/D3 sobre el lote | igual, pero validando **piezas Y CRIMP** | igual |
| Documento final | "Empaque Terminado" en pantalla (sin PDF) | + **correo "Empaque terminado CRIMP Viajero"** | igual |

---

## 3. Mapa de módulos afectados (resumen)

| # | Módulo / Área | Impacto | Detalle |
| - | ------------- | ------- | ------- |
| M1 | **Sent List — Materiales** (`SentListMaterialsView`) | 🔴 Alto | Crear viajero + lotes de CRIMP, eliminar modal de Kit, lote de fabricante. |
| M2 | **Materiales — Kits** (`KitManagement`) | 🟠 Medio | El `Kit` se elimina del flujo crimp; lo sustituye el CRUD de **Lote de CRIMP** (`crimp_lots`). |
| M3 | **Capacity Wizard** (`CapacityWizard`) | 🟠 Medio | Hoy genera Kits para crimp; debe generar viajero + lotes de CRIMP. |
| M4 | **Pesadas de Producción** (`WeighingManagement`) | 🟠 Medio | Quitar selección de kit para CRIMP; pesar a nivel viajero. |
| M5 | **Pesadas de Calidad** (`QualityWeighings`) | 🟠 Medio | Quitar selección de kit; pesar a nivel viajero. |
| M6 | **Empaque** (`SentListPackagingView` + decisiones) | 🔴 Alto | 2 CRUD de pesadas (piezas/CRIMP), sobrantes, confirmación. |
| M7 | **Inspección** (`InspectionList`, `Lot::canBeInspected()`) | 🟠 Medio | Hoy bloquea inspección si el Kit no está `released`; cambiar a nivel viajero. |
| M8 | **Semáforos / TV Monitor / Dashboards** (`TvMonitor`, `ComputesAreaStats`) | 🟠 Medio | Columna "Kit" para crimp usa estado del Kit; recalcular sin kit. |
| M9 | **Correos / Notificaciones** | 🔴 Alto (nuevo) | No existe `app/Mail`; crear template + envío "Empaque terminado CRIMP Viajero". |
| M10 | **Reportes / Trazabilidad / Lots-Kits CRUD** (`KitList`, `LotShow`, reportes) | 🟢 Bajo-Medio | Ajustar textos "lote→viajero" y trazabilidad que hoy pasa por Kit. |

🔴 Alto · 🟠 Medio · 🟢 Bajo. El detalle fino de cada uno está en `01_modulos_afectados.md`.

---

## 4. Tablas y modelos en una mirada

**Tablas a CREAR:**
- `crimp_lots` — **Lotes de CRIMP** hijos del viajero, con `lote_fabricante` (campo manual) + comentarios.
  Sustituye el rol del `Kit` para crimp.
- `packaging_piece_weighings` — pesadas de piezas/manguitas en Empaque.
- `packaging_crimp_weighings` — pesadas de CRIMP en Empaque.
- *(opcional)* tabla/registro del correo enviado (historial), si se requiere trazabilidad de envío.

**Tablas a CORREGIR (alterar):**
- `lots` — pasa a representar el **viajero** para crimp; evaluar campos de cantidades completadas/sobrantes
  de CRIMP y piezas a nivel viajero (a definir).
- `weighings` — `kit_id` deja de poblarse para CRIMP (no se borra; queda nullable/histórico).
- `quality_weighings` — `kit_id` deja de poblarse para CRIMP.
- `packaging_records` — `kit_id` deja de poblarse; evaluar si se reemplaza por las 2 tablas nuevas.

**Tablas que quedan LEGACY (crimp):** `kits`, `kit_lot`, `kit_approval_cycles`, `kit_incidents` — se conservan
para historial; dejan de usarse en el flujo crimp.

**Modelos afectados:** `Part`, `Lot` (viajero), `WorkOrder`, `Weighing`, `QualityWeighing`,
`PackagingRecord`, `Kit` (legacy) (+ nuevos: `CrimpLot`, `PackagingPieceWeighing`, `PackagingCrimpWeighing`, `Mailable`).

El detalle por tabla/modelo/ruta está en `02_tablas_modelos_rutas.md`.
