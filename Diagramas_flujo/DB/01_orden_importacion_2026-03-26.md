# Orden de Importacion Masiva desde Excel/CSV

## Metadata

| Campo      | Valor                          |
|------------|--------------------------------|
| Proyecto   | Flexcon-Tracker                |
| Fecha      | 2026-03-26                     |
| Version    | 1.0                            |
| Autor      | Equipo de Arquitectura         |
| Alcance    | Importacion masiva de parts, prices, price_tiers, standards y standard_configurations |

---

## Pregunta Principal: Las maquinas/mesas van antes o despues que los parts?

**Respuesta:** Las maquinas y mesas deben darse de alta **ANTES** que los `standards`, pero los `standards` son **OPCIONALES** para importar `parts` y `prices`.

Esto se debe a la estructura de dependencias entre tablas:

- `parts` es una tabla raiz, sin FK hacia equipos. Se puede importar en cualquier momento.
- `prices` depende unicamente de `parts.id`.
- `price_tiers` depende unicamente de `prices.id`.
- `standards` depende de `parts.id` mas FKs **NULLABLE** a `tables.id`, `semi__automatics.id` y `machines.id`.
- `standard_configurations` depende de `standards.id`.

Conclusion directa: **Los parts van primero, independientemente de si las maquinas existen o no.**

---

## Mapa de Dependencias

```
parts                        (raiz, sin dependencias)
  └── prices                 (FK -> parts.id)
        └── price_tiers      (FK -> prices.id)
  └── standards              (FK -> parts.id, FK NULLABLE -> tables.id, semi__automatics.id, machines.id)
        └── standard_configurations  (FK -> standards.id)

machines                     (independiente, referenciado por standards)
tables                       (independiente, referenciado por standards)
semi__automatics              (independiente, referenciado por standards)
```

---

## Escenario A: Importacion Completa (parts + prices + standards)

Usar este escenario cuando **ya existan** maquinas, mesas y semi-automaticas dadas de alta en el sistema.

| Paso | Tabla                    | Requisito previo                           |
|------|--------------------------|--------------------------------------------|
| 1    | `machines`               | Ninguno (dar de alta manualmente o importar) |
| 2    | `tables`                 | Ninguno (dar de alta manualmente o importar) |
| 3    | `semi__automatics`        | Ninguno (dar de alta manualmente o importar) |
| 4    | `parts`                  | Ninguno                                    |
| 5    | `prices`                 | `parts` importados                         |
| 6    | `price_tiers`            | `prices` importados                        |
| 7    | `standards`              | `parts` importados + equipos dados de alta |
| 8    | `standard_configurations`| `standards` importados                     |

**Orden de ejecucion:**

```
1. machines
2. tables
3. semi__automatics
4. parts
5. prices
6. price_tiers
7. standards
8. standard_configurations
```

---

## Escenario B: Importacion Parcial Inmediata (solo parts + prices, SIN standards)

Usar este escenario cuando las maquinas/mesas **AUN NO** estan dadas de alta, pero se necesita importar el catalogo de parts y precios de forma inmediata.

| Paso | Tabla         | Requisito previo       |
|------|---------------|------------------------|
| 1    | `parts`       | Ninguno                |
| 2    | `prices`      | `parts` importados     |
| 3    | `price_tiers` | `prices` importados    |

**Orden de ejecucion:**

```
1. parts
2. prices
3. price_tiers
```

Los `standards` y `standard_configurations` se importaran en una segunda fase, una vez que las maquinas, mesas y semi-automaticas esten registradas en el sistema.

---

## Conclusion: Situacion Actual del Proyecto

Las maquinas y mesas **AUN NO estan dadas de alta** en el sistema.

Por esta razon, se procedera con el **Escenario B** como accion inmediata:

1. Importar `parts` desde CSV.
2. Importar `prices` desde CSV.
3. Importar `price_tiers` desde CSV.

La importacion de `standards` y `standard_configurations` queda **pendiente** para una segunda fase, una vez que los equipos esten registrados.

---

## Proximos Pasos Pendientes

- [ ] Completar el registro de maquinas (`machines`) en el sistema.
- [ ] Completar el registro de mesas (`tables`) en el sistema.
- [ ] Completar el registro de semi-automaticas (`semi__automatics`) en el sistema.
- [ ] Preparar el archivo CSV de `standards` con las columnas correctas, incluyendo las FKs nullable a equipos.
- [ ] Preparar el archivo CSV de `standard_configurations`.
- [ ] Ejecutar el Escenario A completo una vez que los equipos esten disponibles.
- [ ] Validar integridad referencial despues de cada importacion.

---

## Notas Tecnicas Adicionales

- Las FKs de `standards` hacia `tables.id`, `semi__automatics.id` y `machines.id` son **NULLABLE**, lo que significa que un standard puede existir sin estar vinculado a un equipo especifico.
- Al importar `standards` en el Escenario A, si una maquina referenciada no existe, la importacion fallara con error de FK violation.
- Se recomienda validar los archivos CSV con datos de prueba antes de la importacion masiva en produccion.
- Los seeders de Laravel deben respetar este mismo orden para evitar errores de integridad referencial.
