# 03 — Riesgos, Decisiones abiertas y Checklist

> La columna/etiqueta **"Paso"** referencia el flujo de los **diagramas 1-2** (8 pasos):
> **1** crear viajero · **2** agregar lotes de CRIMP (+ lote de fabricante) · **3** entregar a Empaque ·
> **4** pesar CRIMP + piezas "manguitas" · **5** confirmar + correo · **6** decisión (D1/D2a-c/D3) ·
> **7** entrega de viajero · **8** regresar sobrantes. `§4`/`§5` = pesadas Producción/Calidad; `T` = transversal.

---

## A. Riesgos técnicos (con dónde se materializan)

| Paso | # | Riesgo | Dónde | Mitigación |
| ---- | - | ------ | ----- | ---------- |
| **T** | R1 | **Afectar partes SIN crimp** al tocar lógica compartida. `Lot`, `Weighing`, `QualityWeighing`, `PackagingRecord` son **comunes** a ambos flujos. | Todos los módulos | Condicionar **todo** por `part->is_crimp`. Tests de regresión del flujo no-crimp antes y después. |
| **gate** | R2 | **Desacoplar el Kit** rompe gates existentes. Hoy la Inspección de crimp **exige** `Kit` `released` (`Lot::canBeInspected`). Si se quita mal, los viajeros crimp **no avanzan** o avanzan sin control. | M7 (`Lot::canBeInspected`) | Reemplazar el gate por uno a nivel viajero antes de quitar el kit del alta. |
| **§4,§5,T** | R3 | **Reportes/semáforos "vacíos"** al agrupar por `kit_id` que ya no se puebla para crimp. | M4, M5, M8, M10 | Repuntar agrupaciones a viajero / lote de CRIMP. |
| **4-6** | R4 | **Doble fuente de verdad en cantidades** de empaque: hoy `PackagingRecord.packed_pieces` es un único total; ahora hay piezas Y CRIMP por separado. | M6 | Definir si `packaging_records` se reemplaza o coexiste; centralizar el cálculo en el modelo `Lot`. |
| **6** | R5 | **`updateSentPieces()` y trazabilidad** asumen el modelo lote→kit/ciclos. | `WorkOrder::updateSentPieces` [:283](../../../app/Models/WorkOrder.php#L283) | Revisar el cálculo de "completado" cuando el empaque valida piezas vs CRIMP. |
| **5** | R6 | **Correo sin infraestructura**: no hay `app/Mail`, transporte SMTP por confirmar. | M9 | Verificar `MAIL_*` y crear el Mailable + template; definir disparador. |
| **T** | R7 | **Confusión de conceptos** (lote, viajero, lote de CRIMP, lote de fabricante, WO, kit). | UX general | Glosario único + rótulos condicionados por crimp. |
| **deploy** | R8 | **Migración de datos vivos**: viajeros/lotes crimp en proceso al desplegar. | Deploy | Plan de corte: dejar terminar los crimp en curso con el flujo viejo, o script de migración. |

---

## B. Decisiones abiertas (requieren respuesta del cliente)

### B.1 Sobre el viajero y los lotes de CRIMP · **diagrama pasos 1-2**
- ¿El **viajero** se mapea al actual `Lot` (recomendado) o es una entidad totalmente nueva?
- ¿El número de viajero es **manual o autogenerado**? (hoy `lot_number` es autogenerado).
- ¿Puede haber **más de un viajero por WO**? (hoy un WO tiene N lotes — encaja).
- ¿La cantidad del viajero es **fija** desde su creación?
- ¿La **suma de lotes de CRIMP** debe cuadrar con la cantidad del viajero? (validación).
- ¿El **lote de fabricante** es obligatorio? ¿Editable/borrable después?

### B.2 Sobre el Kit · **legacy (se elimina del flujo crimp)**
- ¿Se **conserva** el CRUD de Kits como histórico (recomendado) o se retira del menú?
- ¿Hay partes crimp **en proceso** que aún dependan del kit al momento del despliegue?

### B.3 Sobre pesadas (producción / calidad) · **§4-5 del flujo (`Flujo.mkd`)**
- ¿Las pesadas viejas con `kit_id` se conservan como historial? (recomendado: **sí**).
- ¿Para crimp ya **no** debe aparecer ningún campo de kit? (confirmado por `Flujo.mkd`, validar UI).

### B.4 Sobre empaque · **diagrama pasos 4-6**
- ¿Las pesadas de piezas y CRIMP se capturan **manualmente** o se calculan por peso?
- ¿La **cantidad completada** se calcula desde las pesadas o se captura?
- ¿Los **sobrantes** se calculan o se capturan?
- ¿Se permite **cerrar** si piezas y CRIMP **no coinciden**? ¿Qué pasa si hay más de uno que del otro?
- Confirmar las **fórmulas del Paso 6** (diagrama 4): `Completar CRIMP = piezas sobrantes − CRIMP sobrante`;
  D3 redondeo a múltiplos de 100 hacia abajo.

### B.5 Sobre el correo · **diagrama paso 5**
- ¿**Destinatarios**? (diagrama 3 sugiere Empaque + Materiales).
- ¿Envío **automático** al cerrar empaque o **manual**?
- ¿El **No. de etiquetas** se captura, calcula o se toma de otro módulo? (marcado "pendiente de validar").
- ¿Se requiere **PDF adjunto** o solo cuerpo? ¿Se guarda **historial** del envío?

---

## C. Orden de implementación sugerido (para minimizar riesgo)

| Orden | Actividad | Paso diagrama |
| ----- | --------- | ------------- |
| 1 | **Levantamiento + decisiones** (sección B) — bloqueante | — |
| 2 | **BD**: crear `crimp_lots`, `packaging_piece_weighings`, `packaging_crimp_weighings` (+ campos en `lots`) | 2, 4 |
| 3 | **Modelos + relaciones** (`CrimpLot`, pesadas nuevas, helpers en `Lot`), todo condicionado por `is_crimp` | 1-2, 4 |
| 4 | **M7 Inspección**: reescribir el gate `canBeInspected()` a nivel viajero (antes de quitar kit del alta) | gate |
| 5 | **M1 Materiales**: viajero + lotes de CRIMP + lote de fabricante; quitar alta de kit para crimp | **1-2** |
| 6 | **M3 Capacity Wizard**: generar viajero + lotes de CRIMP | **1-2** |
| 7 | **M4/M5 Pesadas**: quitar selección de kit para crimp | **§4-§5** |
| 8 | **M6 Empaque**: 2 CRUD de pesadas, confirmación, decisiones D1/D2/D3 | **4-7** |
| 9 | **M9 Correo**: Mailable + template + disparador | **5** |
| 10 | **M8 Semáforos/TV/Dashboards**: recalcular columna kit para crimp | T |
| 11 | **M10 Reportes/Trazabilidad**: repuntar agrupaciones y rótulos | T |
| 12 | **Regresión** completa de NO-CRIMP + pruebas E2E de CRIMP (recorrer los 8 pasos) | 1-8 |

---

## D. Checklist de "no romper NO-CRIMP"

- [ ] Crear un caso de prueba con una parte `is_crimp = false` y verificar que **todo** el flujo
      (lotes, pesadas, empaque, cierre, semáforos) queda **idéntico** antes y después.
- [ ] Confirmar que cada bloque nuevo está dentro de un `if ($part->is_crimp)` (o equivalente).
- [ ] Verificar que `weighings.kit_id` / `quality_weighings.kit_id` siguen funcionando para datos viejos.
- [ ] Verificar que los lotes no-crimp **no** muestran "viajero" ni campos de lote de fabricante.

---

## E. Relación con el documento de propuesta

Este reporte profundiza la **Feature 1 — Reajuste del proceso de CRIMP** del archivo
[`5-Nuevas_features.mkd`](../5-Nuevas_features.mkd), que estaba marcada como *"BLOQUEADA hasta recibir el
diagrama del cliente"*. Con los diagramas `1`–`4` y `Notas-new-implementacion/Flujo.mkd` ya disponibles,
el alcance queda mapeado contra el código y listo para cotizar/planificar.

La estimación preliminar del cliente (`Flujo.mkd` §14-15) es de **91–173 horas** (≈ 12–30 días laborales),
consistente con el número de módulos de impacto medio/alto identificados aquí (M1, M6 y M9 concentran el grueso).
