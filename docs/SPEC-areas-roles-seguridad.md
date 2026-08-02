# SPEC — Análisis de áreas por rol: problemas UX/UI y backend + restricción de edición en el tablero SentList

**Fecha:** 2026-07-08
**Autor:** Análisis técnico (Claude)
**Alcance:** Dashboards por rol (Materiales, Inspección, Producción, Calidad, Empaques, Admin, Empleado) y el tablero de flujo de la **Lista Preliminar (SentList)**.
**Objetivo principal solicitado:** que en el tablero del SentList cada área **no pueda modificar los campos de las demás áreas**.

---

## 0. Resumen ejecutivo

El tablero SentList implementa un flujo por departamentos:

```
Materiales → Inspección → Producción → Calidad → Empaque
```

El modelo `SentList` **ya tiene** la compuerta correcta (`canDepartmentEdit()`), pero **no está cableada** en las vistas que realmente se usan. En la práctica:

1. **Ningún método de acción** de las 5 vistas de área (`SentListMaterialsView`, `SentListInspectionView`, `SentListProductionView`, `SentListQualityView`, `SentListPackagingView`) valida rol, departamento actual ni estado antes de escribir en la base de datos.
2. Los nombres de rol están definidos con **tres convenciones distintas e incompatibles** entre seeders, rutas, vistas y componentes → la autorización por rol es inconsistente y en varios casos **silenciosamente rota**.
3. Todas las escrituras usan `findOrFail($id)` **sin acotar al SentList actual** → un id manipulado permite editar/borrar registros de *otra* lista (IDOR).

**Severidad global: ALTA.** Cualquier usuario operativo autenticado puede alterar datos de una etapa que no le corresponde y avanzar/cerrar el flujo de otros.

---

## 1. Hallazgo raíz: caos de nombres de rol (BACKEND — CRÍTICO)

Los roles se crean con nombres distintos en tres seeders y se consultan con un cuarto criterio:

| Origen | Nombres usados |
|---|---|
| `database/seeders/RoleSeeder.php` | `Production`, `Quality`, `Materials`, `Shipping` (inglés, Capitalizado) |
| `database/seeders/AreaUsersSeeder.php` | `Produccion`, `Calidad`, `Materiales` (español, Capitalizado) |
| `database/seeders/MaterialsRoleSeeder.php` | `Materials`, `Quality` (inglés) |
| Middleware de rutas `routes/admin.php` | `role:admin\|Produccion\|Calidad\|Materiales\|Empaques` (español) |
| `SentListDepartmentView::getUserDepartment()` | `hasRole('materials'/'production'/'inspection'/'quality'/'shipping')` (inglés **minúsculas**) |
| `sent-lists/show.blade.php`, `Tutorial.php` | `hasRole('Materiales'/'Produccion'/'Calidad'/'Empaques')` (español) |

**Consecuencias (Spatie `hasRole` es *case-sensitive* y exacto):**

- Un usuario sembrado como `Production` **no pasa** el middleware `role:...|Produccion` → recibe 403 en `/production`, `/sent-lists`, etc. según qué seeder corrió.
- Un usuario `Produccion` **sí** pasa el middleware, pero `SentListDepartmentView::getUserDepartment()` consulta `hasRole('production')` (minúsculas) → **falla** → cae al `return SentList::DEPT_MATERIALS` por defecto (línea 64). Es decir, un usuario de Producción es tratado como Materiales.
- `hasRole('inspection')` no corresponde a **ningún** rol creado en ningún seeder.
- El rol de empaque se llama `Shipping` (seeder) pero se consulta como `Empaques` (rutas) y `shipping` (componente).

### Acción requerida
Definir **una sola convención canónica** de roles y usarla en todo el código. Recomendación: español Capitalizado, alineado con las constantes `DEPT_*` del modelo:

| Rol canónico | Constante departamento |
|---|---|
| `Materiales` | `SentList::DEPT_MATERIALS` (`materiales`) |
| `Inspeccion` | `SentList::DEPT_INSPECTION` (`inspeccion`) |
| `Produccion` | `SentList::DEPT_PRODUCTION` (`produccion`) |
| `Calidad` | `SentList::DEPT_QUALITY` (`calidad`) |
| `Empaques` | `SentList::DEPT_SHIPPING` (`envios`) |

- Consolidar en **un único** seeder de roles; eliminar/renombrar los roles inglés/minúsculas.
- Migración de datos: renombrar roles existentes en `roles` y reasignaciones en `model_has_roles`.
- Reemplazar todos los `hasRole('...')` dispersos por un helper único, p. ej. `User::departmentKey(): ?string` que mapee rol → constante `DEPT_*`.

---

## 2. Objetivo principal: restringir edición cruzada en el tablero SentList

### 2.1 Estado actual (problema)

- El modelo expone `SentList::canDepartmentEdit(string $department): bool` — verifica `current_department === $department` **y** que el estado no sea `confirmed`/`canceled`. **Es la compuerta correcta.**
- Solo se invoca en `SentListDepartmentView` (componente **genérico que ya no está en el flujo real**).
- Las 5 vistas de área que **sí** se renderizan desde `sent-lists/show.blade.php` (vía `@livewire`) **no la usan en absoluto**. Verificado: ninguna contiene `canDepartmentEdit`, `hasRole`, `authorize`, `abort` ni chequeo de `status`/`current_department` en sus métodos de acción.

Métodos de escritura sin protección (muestra):

- `SentListProductionView`: `saveWeighing`, `deleteWeighing`, `markLotComplete`, `reopenLot`, `sendToQuality`.
- `SentListQualityView`: `saveWeighing`, `deleteWeighing`, `sendToPackaging`.
- `SentListMaterialsView`: `saveLots`, `saveCrimpLots`, `saveMaterial`, `sendToInspection`.
- `SentListInspectionView`: `approveLot`, `rejectLot`, `returnToMaterials`, `sendToProduction`.
- `SentListPackagingView`: `savePackaging`, `deletePackaging`, `decisionCompleteLot` (borra pesadas), `closeList` (confirma la lista), `reopenLot`, etc.

### 2.2 Vector de abuso concreto

1. La ruta `GET /sent-lists/{sentList}` está protegida solo por `role:admin|Materiales|Produccion|Calidad|Empaques`; **no filtra por `current_department`**. Cualquier rol operativo abre **cualquier** lista, esté en la etapa que esté.
2. El `show.blade.php` decide qué pestañas mostrar (`$allowedTabs`) según el rol — **pero es control solo de UI**. Un request Livewire fabricado puede invocar métodos del componente igual.
3. Aun sin fabricar requests: como no se valida `current_department`, un usuario de Producción puede editar/pesar/enviar una lista que **ya pasó** por Producción y está en Calidad, o **antes** de que le llegue. Igual para las demás áreas.
4. El estado `confirmed`/`canceled` no bloquea escrituras: se puede seguir modificando una lista ya cerrada.

### 2.3 Solución propuesta (backend, autoritativa)

**Principio:** la autorización debe vivir en el servidor (en cada componente Livewire), no en Blade. Blade solo oculta lo que el backend además rechaza.

**Paso A — Trait o clase base para las vistas de área.**
Crear `App\Livewire\Concerns\GuardsSentListDepartment` con:

```php
protected function department(): string; // constante DEPT_* que cubre este componente

protected function assertCanEdit(): void
{
    $user = Auth::user();
    $dept = $this->department();

    // 1) El usuario pertenece al departamento de este componente (o es admin)
    abort_unless(
        $user->hasRole('admin') || $user->departmentKey() === $dept,
        403,
        'No pertenece a este departamento.'
    );

    // 2) La lista está actualmente en ese departamento y sigue editable
    abort_unless(
        $this->sentList->canDepartmentEdit($dept),
        403,
        'Esta lista no está en su etapa o ya fue cerrada.'
    );
}

public function canEdit(): bool // para usar en Blade (#[Computed])
{
    return (Auth::user()->hasRole('admin') || Auth::user()->departmentKey() === $this->department())
        && $this->sentList->canDepartmentEdit($this->department());
}
```

- Calidad e Inspección comparten rol/etapa contigua: `SentListInspectionView` usa `DEPT_INSPECTION`, `SentListQualityView` usa `DEPT_QUALITY`. Definir en `departmentKey()` que el rol `Calidad` cubre ambas etapas si esa es la regla de negocio (hoy `getUserDepartment` las mezcla; formalizarlo).

**Paso B — Llamar `assertCanEdit()` como primera línea de cada método de escritura** en las 5 vistas (`save*`, `delete*`, `approve*`, `reject*`, `mark*`, `reopen*`, `decision*`, `send*`, `closeList`, etc.).

**Paso C — Acotar todo `findOrFail` al SentList actual (anti-IDOR).**
En lugar de `Weighing::findOrFail($id)`, resolver contra los lotes de *esta* lista:

```php
$lotIds = $this->sentList->getEffectiveWorkOrders()
    ->flatMap->lots->pluck('id');

$weighing = Weighing::whereIn('lot_id', $lotIds)->findOrFail($id);
```

Aplicar el mismo patrón a `Lot`, `CrimpLot`, `QualityWeighing`, `PackagingRecord`, `PackagingPieceWeighing`, `PackagingCrimpWeighing`.

**Paso D — Blade solo como refuerzo.** En cada vista, envolver botones/inputs de edición con `@if($this->canEdit()) ... @else` (mostrar estado de solo lectura). Nunca confiar en esto como única barrera.

**Paso E — Endurecer la ruta.** Considerar un Policy `SentListPolicy@view`/`@update` y `->can('update', $sentList)` en el controlador/rutas, además del middleware de rol, para centralizar la regla "solo el departamento actual escribe".

### 2.4 Criterios de aceptación

- [ ] Un usuario de Producción que abre una lista en etapa `calidad` ve la vista en **solo lectura** y cualquier POST a `saveWeighing`/`sendToQuality` responde **403**.
- [ ] Una lista `confirmed`/`canceled` rechaza toda escritura desde cualquier área.
- [ ] `deleteWeighing($id)` con un `$id` de otra lista responde **404/403** (no borra).
- [ ] `moveToNextDepartment` solo lo ejecuta el departamento actual.
- [ ] Los nombres de rol son consistentes en seeders, rutas, componentes y Blade.

---

## 3. Hallazgos por área

### 3.1 SentList — Producción (`SentListProductionView`)
- **Backend/seguridad:** sin `assertCanEdit` (ver §2). `deleteWeighing` y `markLotComplete`/`reopenLot` usan `findOrFail` global (IDOR).
- **UX/UI:** `saveWeighing` fija `bad_pieces = 0` siempre → Producción no puede registrar piezas malas aunque el modelo las soporta; revisar si es intencional. Tras `saveWeighing` el modal se cierra pero no se recalcula "pendiente por pesar" como sí hace Calidad → inconsistencia entre áreas.

### 3.2 SentList — Calidad (`SentListQualityView`)
- **Backend/seguridad:** sin `assertCanEdit`. `deleteWeighing`/`editQualityWeighing` sin scope al SentList.
- **UX/UI:** buen patrón de "piezas restantes" (`remainingPieces`) — usarlo como referencia para Producción. `saveWeighing` no cierra el modal (llama `openWeighingModal` de nuevo); comportamiento distinto al de Producción → unificar.

### 3.3 SentList — Materiales (`SentListMaterialsView`)
- **Backend/seguridad:** sin `assertCanEdit`. `sendToInspection` marca `material_status='released'` en todos los lotes y resuelve rechazos sin verificar rol/etapa.
- **UX/UI:** validación de suma de lotes vs `Cant. WO` está en `SentListDepartmentView::saveLots` pero **no** en esta vista de Materiales → posible descuadre de cantidades. Confirmar cuál es la vista viva y unificar la validación.

### 3.4 SentList — Inspección (`SentListInspectionView`)
- **Backend/seguridad:** sin `assertCanEdit`. `approveLot`/`rejectLot`/`returnToMaterials`/`sendToProduction` escriben sin validar etapa.
- **UX/UI:** rol de inspección no existe como rol propio (§1); hoy Calidad cubre inspección de facto. Formalizar la regla y reflejarla en las pestañas.

### 3.5 SentList — Empaque (`SentListPackagingView`)
- **Backend/seguridad:** el componente más grande y más expuesto. `decisionCompleteLot` **borra** pesadas de Producción, Calidad y Empaque (`Weighing/QualityWeighing/PackagingRecord::where('lot_id',...)->delete()`) sin `assertCanEdit` ni confirmación de rol → un usuario no-empaque podría destruir historial. `closeList` confirma la lista sin validar rol/etapa.
- **UX/UI:** muchas acciones de decisión (`decisionCompleteCrimp/Pieces/Both/CloseAsIs`) sin estado de solo-lectura; alto riesgo de clics accidentales. Añadir confirmaciones destructivas.

### 3.6 Dashboards de hub (Producción/Calidad/Materiales/Empaque)
- **Backend:** son agregaciones **globales** (no acotan por usuario), lo cual es aceptable para tableros de área, pero dependen 100% del middleware de rol → ver §1 (si el rol no matchea, 403 o datos vacíos).
- **Calidad/Inspección — inconsistencia de datos:** `QualityAreaDashboard` e `InspectionList` filtran por `kits` con `Kit::STATUS_RELEASED` e `inspection_status`, pero el flujo CRIMP "ya no usa kit" (comentarios en las vistas de SentList: *"CRIMP ya no usa kit"*). Riesgo de contadores/listas **siempre vacíos o desactualizados** para partes CRIMP. Revisar y migrar el criterio a nivel viajero/lote.
- **Rendimiento:** varios `whereRaw` con subconsultas correlacionadas por lote (`ProductionHubDashboard`, `QualityAreaDashboard`) — aceptables a volumen bajo; vigilar N+1 y agregar índices en `weighings.lot_id`, `quality_weighings.lot_id` (con `deleted_at`).

### 3.7 Dashboard Empleado (`Employee/Dashboard`)
- **UX/UI:** mínimo (27 líneas). `mount` hace `$this->employee = Auth::user()` — verificar que la vista no exponga datos administrativos y que el rol `employee` no tenga acceso a rutas `/admin/*`.

### 3.8 AdminDashboard
- **Backend:** sin `mount`, todo en `render`. Revisar que las métricas globales no ejecuten consultas pesadas en cada render de Livewire (cachear si aplica).

---

## 4. Plan de implementación sugerido (orden)

1. **Unificar roles (§1)** — seeder único + migración de renombrado. *Bloqueante del resto.*
2. Añadir helper `User::departmentKey()` y trait `GuardsSentListDepartment` (§2.3 A).
3. Insertar `assertCanEdit()` en los métodos de escritura de las 5 vistas (§2.3 B).
4. Acotar `findOrFail` al SentList (§2.3 C) — anti-IDOR.
5. Refuerzo Blade `canEdit()` (solo-lectura visual) (§2.3 D).
6. Confirmaciones destructivas en Empaque (§3.5).
7. Revisar criterio kits→viajero en dashboards Calidad/Inspección (§3.6).
8. Tests de autorización (§2.4).

---

## 5. Archivos clave

- `app/Models/SentList.php` — `canDepartmentEdit()`, `moveToNextDepartment()`, `moveToPreviousDepartment()`.
- `app/Http/Controllers/SentListController.php` — `show()` (no filtra por departamento).
- `app/Livewire/Admin/SentLists/SentList{Materials,Inspection,Production,Quality,Packaging}View.php` — métodos de escritura sin guardas.
- `app/Livewire/Admin/SentLists/SentListDepartmentView.php` — único que usa `canDepartmentEdit` (genérico).
- `resources/views/sent-lists/show.blade.php` — `$allowedTabs` (control solo-UI).
- `routes/admin.php` — middleware de rol (nombres español).
- `database/seeders/{RoleSeeder,AreaUsersSeeder,MaterialsRoleSeeder}.php` — nombres de rol divergentes.
- `app/Livewire/Admin/{Production,Quality,Materials,Packaging,Inspection}/*` — dashboards de área.
