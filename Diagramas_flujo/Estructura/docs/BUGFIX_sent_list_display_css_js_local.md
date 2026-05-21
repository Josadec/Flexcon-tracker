# Análisis Técnico: CSS/JS no carga en /admin/sent-lists/display/sl/{id}

**Fecha de análisis:** 2026-05-20
**Estado:** RESUELTO

---

## Problema reportado

La URL `http://flexcon-tracker.test:8088/admin/sent-lists/display/sl/4` no renderizaba
correctamente los modales ni los scripts interactivos de Livewire en la máquina local de
Josadec. En la máquina de Mauricio (maubr_95@hotmail.com) la misma ruta funcionaba sin problemas.

El síntoma: la página cargaba visualmente, pero los modales y los `wire:click` no respondían
(comportamiento de "bloqueado").

---

## Arquitectura de la ruta

```
URL: /admin/sent-lists/display/sl/4
 |
 +-- routes/admin.php : Route::get('/sent-lists/display/sl/{sentList}', ShippingListDisplay)
 |                       -> name('sent-lists.display.sl')
 |
 +-- app/Livewire/Admin/SentLists/ShippingListDisplay.php
 |     mount(): fija $focusedSentListId = 4
 |     render(): query WorkOrders via sent_list_id o purchaseOrder.sentLists pivot
 |
 +-- resources/views/components/layouts/app.blade.php
 |     -> x-layouts.admin.sidebar
 |     -> @include('partials.head') (línea 5) — contiene @vite
 |     -> @fluxScripts (línea 398) — Flux UI + Livewire JS
 |
 +-- public/build/manifest.json (generado 2026-05-19 22:12)
       app-BR8IqSZk.css (Tailwind + Flux CSS)
       app-DE1wUeus.js  (Alpine.js + Livewire client)
```

---

## Causa raíz confirmada

### APP_URL sin puerto — URLs de assets inconsistentes

**Archivo:** `.env` línea 5
**Valor incorrecto:** `APP_URL=http://flexcon-tracker.test`
**Valor correcto:** `APP_URL=http://flexcon-tracker.test:8088`

Apache escucha en el puerto **8088** según `/xampp/apache/conf/extra/httpd-vhosts.conf`:
```apache
<VirtualHost *:8088>
  DocumentRoot "C:\xampp\htdocs\flexcon-tracker\public"
  ServerName flexcon-tracker.test
</VirtualHost>
```

Sin el puerto en `APP_URL`, el helper `asset()` de Laravel generaba URLs sin puerto en
contextos CLI (artisan, queues, cron) y en cualquier situación donde `request()->root()`
no estuviera disponible. Esto causaba inconsistencias en la generación de URLs para:

- Assets de Vite: `http://flexcon-tracker.test/build/assets/...` (puerto 80 → 404)
- Endpoints de Livewire AJAX (`/livewire-e9556783/update`)
- Signed URLs y verificaciones de sesión

**Verificación antes del fix:**
```
php artisan tinker --execute="echo asset('build/assets/app-BR8IqSZk.css');"
# Output: http://flexcon-tracker.test/build/assets/app-BR8IqSZk.css  ← SIN PUERTO
```

**Verificación después del fix:**
```
php artisan tinker --execute="echo asset('build/assets/app-BR8IqSZk.css');"
# Output: http://flexcon-tracker.test:8088/build/assets/app-BR8IqSZk.css  ← CON PUERTO
```

---

## Por qué funcionaba en la máquina de Mauricio

Mauricio probablemente accede vía puerto 80 (Apache por defecto), donde `APP_URL` sin
puerto es correcto. Su `APP_URL=http://flexcon-tracker.test` coincide con el acceso sin
puerto. Las URLs de assets se generan sin puerto y funcionan porque el servidor escucha en 80.

---

## Verificaciones realizadas durante el análisis

| Componente | Resultado | Estado |
|---|---|---|
| Sintaxis PHP `ShippingListDisplay.php` | `No syntax errors detected` | ✅ OK |
| `public/hot` (Vite dev server) | No existe | ✅ OK |
| `public/build/manifest.json` | Existe (2026-05-19 22:12) | ✅ OK |
| `public/vendor/livewire/` | No existe (correcto) | ✅ OK |
| `mod_rewrite` en Apache | `LoadModule rewrite_module` activo | ✅ OK |
| OPcache PHP | Deshabilitado (`;zend_extension=opcache`) | ✅ OK |
| Conexión a base de datos | `DB OK` | ✅ OK |
| SentList ID 4 en DB | Encontrado | ✅ OK |
| Migraciones pendientes | Ninguna | ✅ OK |
| `workstation_type` en `purchase_orders` | Columna existe | ✅ OK |
| `Lot::canBeInspected()` | Método existe | ✅ OK |
| `Lot::getQualitySemaphoreStatus()` | Método existe | ✅ OK |
| `Lot::getNextPendingAction()` | Método existe | ✅ OK |
| `PurchaseOrder::sentLists()` | Relación BelongsToMany existe | ✅ OK |
| Query render() con focusedSentListId=4 | Retorna 11 WOs sin errores | ✅ OK |
| CSS asset HTTP (200) | `http://...8088/build/assets/app-BR8IqSZk.css` | ✅ OK |
| Livewire JS HTTP (200, 554KB) | `http://...8088/livewire-e9556783/livewire.js` | ✅ OK |
| Flux JS HTTP (200, 128KB) | `http://...8088/flux/flux.js` | ✅ OK |

---

## Fix aplicado

**Archivo:** `C:\xampp\htdocs\flexcon-tracker\.env`

```diff
- APP_URL=http://flexcon-tracker.test
+ APP_URL=http://flexcon-tracker.test:8088
```

Seguido de:
```bash
php artisan optimize:clear
```

---

## Causas históricas adicionales (ya corregidas)

### 1. PHP Syntax Error — commit 58a93dc (2026-05-17)

```
PHP Fatal error: syntax error, unexpected token "<<" at ShippingListDisplay.php:2217
```

El token `<<` (heredoc mal escrito) causaba que PHP no compilara el componente, haciendo
fallar TODAS las rutas del sitio con HTTP 500. Los assets no se cargaban porque Laravel
no podía registrar las rutas y devolvía una página de error plana.

**Estado:** Corregido por Josadec en commit `58a93dc`.

### 2. Assets de Livewire versionados en git — commit 603a0c1 (2026-05-19)

Los archivos `public/vendor/livewire/` estaban en git tracking. En cada `git pull`, la
versión antigua del JS de Livewire sobreescribía la instalada por Composer, causando
desfaces de versión entre máquinas y rompiendo los modales.

**Estado:** Corregido por Mauricio en commit `603a0c1` (`.gitignore` actualizado).

---

## Checklist de diagnóstico para el futuro

Si el problema vuelve a ocurrir, verificar en este orden:

1. `php -l app/Livewire/Admin/SentLists/ShippingListDisplay.php` → sin errores de sintaxis
2. `ls public/hot` → NO debe existir
3. `ls public/build/manifest.json` → debe existir con fecha reciente
4. `php artisan tinker --execute="echo asset('test');"` → debe incluir el puerto correcto
5. `php artisan migrate:status` → sin migraciones pendientes
6. Verificar `APP_URL` en `.env` incluye el puerto de Apache
7. `php artisan optimize:clear` si cambió algo
8. Reiniciar Apache desde XAMPP panel para limpiar estado
9. Hard refresh en el browser (Ctrl+Shift+R) para limpiar cache del browser

---

## Nota sobre diferencias entre entornos

| Variable | Josadec (esta máquina) | Mauricio (otra máquina) |
|---|---|---|
| Puerto Apache | 8088 | 80 (estándar) |
| `APP_URL` antes del fix | `http://flexcon-tracker.test` (sin puerto) | `http://flexcon-tracker.test` (sin puerto, correcto para puerto 80) |
| `APP_URL` después del fix | `http://flexcon-tracker.test:8088` ✅ | Sin cambio necesario |

El mismo valor `APP_URL=http://flexcon-tracker.test` funciona correctamente cuando Apache
usa el puerto 80 (estándar), pero falla silenciosamente cuando se usa un puerto no estándar
como 8088.
