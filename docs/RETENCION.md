# Política de conservación de información

**Plazo: 5 años**, por requisito del ISO y del cliente.

Este documento es lo que se le enseña a un auditor. Describe qué se conserva,
cómo se garantiza y qué se puede demostrar.

---

## 1. Qué se conserva

Todo el flujo productivo, durante 5 años como mínimo:

| Información | Dónde vive |
|---|---|
| Órdenes de compra y de trabajo | `purchase_orders`, `work_orders` |
| Listas de envío | `sent_lists` |
| Viajeros / lotes | `lots` |
| Pesadas de producción y de calidad | `weighings`, `quality_weighings` |
| Registros de empaque | `packaging_records` y las dos tablas de CRIMP |
| Packing slips y facturas | `packing_slips`, `invoices` |
| Historial de cambios | `audit_trails` |

El catálogo vive en `config/retencion.php`. Añadir una entidad es añadir una línea.

---

## 2. Cómo se garantiza que no se borre antes de tiempo

Esto es el 90 % de la política, y es código, no procedimiento:

- **La orden de trabajo no se puede borrar si ya produjo algo.**
  `WorkOrder::getDeleteBlockReason()` bloquea el borrado cuando hay pesadas,
  empaque, packing slip o decisiones de cierre. El borrado normal es lógico
  (`softDeleteWithRelations()`), así que se puede recuperar. El guard vive en el
  modelo, no en la pantalla: ningún código nuevo puede saltárselo por descuido.

- **Dar de baja a una persona no borra su rastro.**
  `audit_trails.user_id` pone NULL en vez de arrastrar la fila, y cada entrada
  guarda copia del nombre y el correo de quien actuó. Antes esto era un borrado
  en cascada: eliminar un usuario borraba toda su auditoría.

- **El historial no se puede modificar ni borrar.**
  El modelo `AuditTrail` lanza excepción ante cualquier intento de editar o
  eliminar una entrada. Es lo que lo convierte en evidencia: un registro
  corregible no prueba nada.

- **No hay ninguna tarea programada que borre datos.**
  Y hay una prueba automática que falla si alguien añade una.

---

## 3. Qué se puede demostrar, y cómo

- **Quién hizo qué y cuándo**: pantalla **Historial** (`/admin/historial`), abierta
  a todos los departamentos. Se busca por orden de trabajo, orden de compra,
  número o descripción de parte, número de viajero, o nombre de la persona.
- **El expediente de un registro concreto**: la ficha de cada viajero y de cada
  orden lleva su línea de tiempo completa.
- **Por qué se corrigió algo ya cerrado**: toda reapertura exige un motivo
  escrito y queda registrada, junto con la cadena de documentos que se deshizo.

---

## 4. Herramientas de operación

```bash
# Qué información ha superado los 5 años. No borra nada.
php artisan flexcon:retencion-reporte

# Exportar a fichero un año completo, antes de tocar nada.
php artisan flexcon:archivar 2021

# Traer al historial los registros antiguos que aún no estén en él.
php artisan flexcon:historial-importar
```

---

## 5. Borrado: por qué NO es automático

`config/retencion.php` trae `borrado_automatico => false`, y no es una opción
pendiente de activar.

`lots.work_order_id` tiene borrado en cascada: podar una orden de compra antigua
arrastraría en silencio sus viajeros, sus pesadas y su historial. Una tarea
programada que hace eso de madrugada no es mantenimiento, es una bomba.

Si algún día hay que liberar espacio: **archivar primero** con el comando de
arriba, verificar el fichero, y borrar a mano lo que corresponda.

---

## 6. Lo que este documento NO cubre — y hay que resolver fuera del código

**Respaldos.** Conservar 5 años exige poder recuperar la base de datos si el
servidor falla. Ningún guard de la aplicación sustituye a un respaldo.

Preguntas abiertas, que un auditor va a hacer:

1. ¿Con qué frecuencia se respalda la base y dónde se guarda la copia?
2. ¿Esa copia está fuera del servidor de producción?
3. ¿Cuándo se probó por última vez una **restauración completa**, y quién firmó
   la prueba?

Sin respuesta a la 3, la retención de 5 años no es demostrable por mucho código
que se escriba.
