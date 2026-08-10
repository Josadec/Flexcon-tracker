<?php

namespace App\Livewire\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Guía de uso del sistema.
 *
 * El contenido vive aquí como datos (no como markup) por dos razones: la vista
 * queda corta y legible, y cada paso se dibuja siempre con la misma anatomía
 * —número, quién lo hace, qué logra, qué teclea y una maqueta de la pantalla—,
 * que es lo que permite seguirla sin leerla completa.
 */
class Tutorial extends Component
{
    public string $activeSection = 'crimp';

    public function mount(): void
    {
        $user = auth()->user();

        // Cada quien entra directo a lo suyo; CRIMP es lo primero para el resto.
        $this->activeSection = match (true) {
            $user->hasRole('Produccion') => 'produccion',
            $user->hasRole('Calidad')    => 'calidad',
            $user->hasRole('Materiales') => 'materiales',
            $user->hasRole('Empaques')   => 'empaques',
            default                      => 'crimp',
        };
    }

    public function goTo(string $section): void
    {
        if (array_key_exists($section, $this->sections())) {
            $this->activeSection = $section;
        }
    }

    public function render(): View
    {
        return view('livewire.admin.tutorial', [
            'sections' => $this->sections(),
            'section' => $this->sections()[$this->activeSection],
            'crimpSteps' => $this->crimpSteps(),
            'standardSteps' => $this->standardSteps(),
            'roleGuide' => $this->roleGuides()[$this->activeSection] ?? null,
            'legend' => $this->legend(),
        ]);
    }

    /** Pestañas de la guía. */
    public function sections(): array
    {
        return [
            'crimp' => [
                'label' => 'Flujo CRIMP',
                'eyebrow' => 'El proceso completo, paso a paso',
                'title' => 'Viajero con CRIMP: de la orden a la entrega',
                'description' => 'Ocho pasos. Cada uno dice quién lo hace, en qué pantalla y qué se captura. Si sólo vas a leer una sección, que sea ésta.',
            ],
            'estandar' => [
                'label' => 'Flujo estándar',
                'eyebrow' => 'Partes sin CRIMP',
                'title' => 'Viajero sin CRIMP: el flujo de siempre',
                'description' => 'Las partes que no son CRIMP siguen el flujo original, sin lotes de CRIMP ni doble pesada. Aquí están las diferencias.',
            ],
            'materiales' => [
                'label' => 'Materiales',
                'eyebrow' => 'Tu área',
                'title' => 'Materiales: liberar, decidir y recibir',
                'description' => 'Tres momentos son tuyos: liberar el material al inicio, decidir qué hacer al cerrar el viajero y recibir lo que sobró.',
            ],
            'produccion' => [
                'label' => 'Producción',
                'eyebrow' => 'Tu área',
                'title' => 'Producción: pesar lo que se fabricó',
                'description' => 'Tu trabajo en el sistema es registrar las pesadas del viajero. De ahí sale lo que Calidad verifica.',
            ],
            'calidad' => [
                'label' => 'Calidad',
                'eyebrow' => 'Tu área',
                'title' => 'Calidad: inspeccionar y verificar',
                'description' => 'Primero liberas la inspección del viajero, después verificas por peso lo que Producción reportó.',
            ],
            'empaques' => [
                'label' => 'Empaques',
                'eyebrow' => 'Tu área',
                'title' => 'Empaques: pesar, confirmar y entregar',
                'description' => 'Tú capturas el empaque (pasos 4 y 5), entregas el viajero (paso 7) y regresas los sobrantes (paso 8).',
            ],
            'admin' => [
                'label' => 'Administración',
                'eyebrow' => 'Configuración',
                'title' => 'Administración: catálogos y arranque del proceso',
                'description' => 'Órdenes de compra, viajeros y los catálogos de los que dependen todas las áreas.',
            ],
        ];
    }

    /** Qué significan los colores del semáforo. Se repite arriba de todo. */
    public function legend(): array
    {
        return [
            ['done', 'Verde', 'Ya se hizo. No tienes que tocar nada.'],
            ['pending', 'Ámbar parpadeando', 'Te toca a ti. Da clic ahí.'],
            ['blocked', 'Rojo', 'Está detenido: alguien más tiene que actuar antes.'],
            ['idle', 'Gris', 'Todavía no le toca.'],
        ];
    }

    /**
     * Los 8 pasos del viajero con CRIMP, en el orden del diagrama del proceso.
     */
    public function crimpSteps(): array
    {
        return [
            [
                'n' => 1,
                'actor' => 'Administración',
                'where' => 'Órdenes de compra',
                'title' => 'Crear el viajero de la orden',
                'summary' => 'Se da de alta la orden y su viajero, con la cantidad total de piezas a producir.',
                'does' => [
                    'Entra a <strong>Órdenes de compra</strong> y abre la orden del cliente.',
                    'Genera el <strong>viajero</strong> con la cantidad total de la orden.',
                    'Verifica que la parte esté marcada como <strong>CRIMP</strong> en el catálogo: eso es lo que activa este flujo.',
                ],
                'after' => 'El viajero aparece en la lista de envío y el semáforo de Materiales se pone en ámbar.',
                'warning' => 'si la parte no está marcada como CRIMP, el viajero seguirá el flujo estándar y no verás lotes de CRIMP en ningún lado.',
                'shot' => [
                    'screen' => 'Órdenes de compra',
                    'path' => '/admin/purchase-orders',
                    'blocks' => [
                        ['toolbar', 'Buscar orden…', 'action' => 'Nueva orden', 'mark' => 2],
                        ['table', ['Orden', 'Parte', 'Cantidad', 'Estado'], 'rows' => 3, 'mark' => 3, 'markCol' => 1, 'markText' => 'CRIMP'],
                        ['note', 'La etiqueta CRIMP en la parte es la que cambia todo el flujo.'],
                    ],
                    'caption' => 'La parte marcada como CRIMP es la que abre los pasos 2 a 8.',
                ],
            ],
            [
                'n' => 2,
                'actor' => 'Materiales',
                'where' => 'Tablero de listas de envío',
                'title' => 'Agregar los lotes de CRIMP al viajero',
                'summary' => 'Se registra qué lotes de CRIMP van a surtir ese viajero y con cuántas piezas cada uno.',
                'does' => [
                    'En el renglón del viajero, da clic en la celda de <strong>Material</strong>.',
                    'En el modal <strong>Lotes de CRIMP</strong>, agrega un renglón por lote: número, lote de fabricante (opcional) y cantidad.',
                    'Mira el <strong>restante</strong>: te dice cuánto falta por asignar contra la cantidad del viajero.',
                    'Guarda. Puedes volver a este modal para editar o agregar lotes después.',
                ],
                'after' => 'Los lotes quedan colgados del viajero y se ven en el tablero y en las pantallas de las demás áreas.',
                'warning' => 'el restante es informativo, no un tope. Puedes guardar aunque no cuadre con la cantidad del viajero: piezas y CRIMP son cantidades distintas.',
                'shot' => [
                    'screen' => 'Tablero',
                    'path' => '/admin/sent-lists/display',
                    'blocks' => [
                        ['table', ['Viajero', 'Material', 'Prod.', 'Cal.', 'Emp.'], 'rows' => 2, 'mark' => 1, 'markCol' => 1, 'markText' => 'Gestionar'],
                        ['modal', 'Lotes de CRIMP', 'blocks' => [
                            ['cards', ['Cantidad del viajero', 'Asignado', 'Restante'], 'mark' => 3, 'markIndex' => 2],
                            ['fields', ['N.º de lote de CRIMP', 'Cantidad'], 'mark' => 2, 'markIndex' => 0],
                            ['button', 'Guardar lotes', 'mark' => 4],
                        ]],
                    ],
                    'caption' => 'El restante se actualiza mientras escribes la cantidad.',
                ],
            ],
            [
                'n' => 3,
                'actor' => 'Materiales',
                'where' => 'Tablero de listas de envío',
                'title' => 'Entregar el CRIMP a Empaque',
                'summary' => 'Se libera el material del viajero: es el permiso para que el proceso siga.',
                'does' => [
                    'Entrega físicamente los lotes de CRIMP a Empaque.',
                    'En el tablero, marca el material del viajero como <strong>liberado</strong>.',
                ],
                'after' => 'El semáforo de Material se pone verde y Calidad ya puede inspeccionar el viajero.',
                'warning' => 'mientras el material no esté liberado, la inspección de Calidad queda bloqueada y nadie puede avanzar.',
                'shot' => [
                    'screen' => 'Tablero',
                    'path' => '/admin/sent-lists/display',
                    'blocks' => [
                        ['lights', [['Material', 'pending'], ['Producción', 'idle'], ['Calidad', 'blocked'], ['Empaque', 'idle']], 'mark' => 2, 'markIndex' => 0],
                        ['note', 'Antes: Material en ámbar, Calidad bloqueada.'],
                        ['lights', [['Material', 'done'], ['Producción', 'pending'], ['Calidad', 'idle'], ['Empaque', 'idle']]],
                        ['note', 'Después de liberar: Material verde y el proceso corre.'],
                    ],
                    'caption' => 'Liberar el material es lo que desbloquea a Calidad.',
                ],
            ],
            [
                'n' => 4,
                'actor' => 'Empaque',
                'where' => 'Tablero o pestaña Empaque',
                'title' => 'Pesar y empacar los lotes de CRIMP',
                'summary' => 'Se capturan las dos pesadas del viajero: las piezas y el CRIMP.',
                'does' => [
                    'En el renglón del viajero, da clic en <strong>Empacar / Confirmar</strong>.',
                    'Elige el <strong>lote de CRIMP</strong> con el que estás trabajando. Si el viajero trae varios, aquí es donde se divide.',
                    'Captura los renglones de <strong>pesada de piezas</strong>: peso en kg y número de piezas.',
                    'Captura los renglones de <strong>pesada de CRIMP</strong>, del mismo modo.',
                ],
                'after' => 'Las pesadas quedan guardadas contra ese lote de CRIMP y puedes seguir capturando más.',
                'warning' => 'las dos tablas van asociadas al lote de CRIMP que elegiste arriba. Si cambias de lote, cambia también dónde se guardan las pesadas.',
                'shot' => [
                    'screen' => 'Confirmación de empaque',
                    'path' => '/admin/sent-lists/display',
                    'blocks' => [
                        ['modal', 'Confirmación de empaque', 'blocks' => [
                            ['cards', ['Descripción', 'N.º de orden', 'Cantidad del viajero'], 'mark' => null],
                            ['cards', ['Lote CRIMP A', 'Lote CRIMP B', 'División del lote'], 'mark' => 2, 'markIndex' => 0],
                            ['table', ['#', 'Peso kg', 'Piezas'], 'rows' => 2, 'mark' => 3, 'markCol' => 2, 'markText' => 'Agregar'],
                            ['table', ['#', 'Peso kg', 'CRIMP'], 'rows' => 2, 'mark' => 4, 'markCol' => 2, 'markText' => 'Agregar'],
                        ]],
                    ],
                    'caption' => 'Un solo modal: eliges el lote y capturas las dos pesadas.',
                ],
            ],
            [
                'n' => 5,
                'actor' => 'Empaque',
                'where' => 'Mismo modal, abajo',
                'title' => 'Confirmar piezas y CRIMP, y avisar por correo',
                'summary' => 'Se cierra la captura y sale la hoja de «Empaque terminado» con el aviso a Materiales.',
                'does' => [
                    'Cuando ya no falte capturar nada, da clic en <strong>Confirmar</strong>.',
                    'Revisa la hoja de <strong>Empaque terminado</strong>: totales de piezas y de CRIMP.',
                    'Captura el <strong>número de etiquetas</strong> y algún comentario si hace falta (los dos son opcionales).',
                    'Da clic en <strong>Confirmar y notificar</strong> para que salga el correo.',
                ],
                'after' => 'El correo se manda a Empaques, a Materiales y a quien empacó; el viajero pasa al paso 6.',
                'warning' => 'el correo se manda cuando tú lo pides, no solo. Si no das clic en «Confirmar y notificar», nadie se entera.',
                'shot' => [
                    'screen' => 'Confirmación de empaque',
                    'path' => '/admin/sent-lists/display',
                    'blocks' => [
                        ['button', 'Confirmar', 'mark' => 1],
                        ['modal', 'Empaque terminado', 'blocks' => [
                            ['cards', ['Piezas empacadas', 'CRIMP empacado', 'Sobrantes'], 'mark' => 2, 'markIndex' => 0],
                            ['fields', ['N.º de etiquetas', 'Comentarios'], 'mark' => 3, 'markIndex' => 0],
                            ['button', 'Confirmar y notificar', 'mark' => 4],
                        ]],
                    ],
                    'caption' => 'La hoja resume lo capturado antes de mandar el aviso.',
                ],
            ],
            [
                'n' => 6,
                'actor' => 'Materiales',
                'where' => 'Tablero, columna Seguimiento',
                'title' => 'Tomar la decisión: cerrar, completar o nuevo lote',
                'summary' => 'Con los sobrantes y faltantes a la vista, Materiales decide qué se hace con el viajero.',
                'does' => [
                    'Da clic en el indicador ámbar de <strong>Decisión</strong>, en la columna Seguimiento.',
                    'Lee la <strong>tabla de resumen</strong>: total, empacadas, sobrantes y faltantes, en piezas y en CRIMP.',
                    'Elige una de las tres tarjetas: <strong>Cerrar</strong>, <strong>Completar</strong> o <strong>Nuevo lote</strong>.',
                    'Si eliges Completar, escoge qué se completa: sólo CRIMP, sólo piezas o ambos.',
                ],
                'after' => 'La decisión queda grabada y el viajero avanza al paso 7.',
                'warning' => 'este paso es de Materiales. Si eres de Empaque verás el estado, pero el botón no te va a abrir el modal.',
                'shot' => [
                    'screen' => 'Toma de decisión',
                    'path' => '/admin/sent-lists/display',
                    'blocks' => [
                        ['lights', [['Empaque', 'done'], ['Decisión', 'pending'], ['Viajero', 'idle'], ['Sobrantes', 'idle']], 'mark' => 1, 'markIndex' => 1],
                        ['modal', 'Paso 6 · Toma de decisión', 'blocks' => [
                            ['table', ['Concepto', 'Total', 'Empacadas', 'Sobrantes', 'Faltantes'], 'rows' => 2, 'mark' => 2, 'markCol' => 3, 'markText' => '300'],
                            ['cards', ['D1 · Cerrar', 'D2 · Completar', 'D3 · Nuevo lote'], 'mark' => 3, 'markIndex' => 1],
                            ['cards', ['D2a CRIMP', 'D2b Piezas', 'D2c Ambos'], 'mark' => 4, 'markIndex' => 0],
                        ]],
                    ],
                    'caption' => 'Completar despliega las tres variantes: CRIMP, piezas o ambos.',
                ],
            ],
            [
                'n' => 7,
                'actor' => 'Empaque',
                'where' => 'Tablero, columna Seguimiento',
                'title' => 'Entregar el viajero',
                'summary' => 'Se confirma que el viajero físico regresó, con el resumen de lo que pasó.',
                'does' => [
                    'Da clic en el indicador de <strong>Viajero</strong> en la columna Seguimiento.',
                    'Revisa el resumen: lo empacado, los sobrantes y la decisión que tomó Materiales.',
                    'Da clic en <strong>Marcar viajero como recibido</strong>.',
                ],
                'after' => 'Queda registrado quién lo recibió y cuándo. Si te equivocaste, en el mismo modal puedes revertir la entrega.',
                'shot' => [
                    'screen' => 'Entrega de viajero',
                    'path' => '/admin/sent-lists/display',
                    'blocks' => [
                        ['lights', [['Empaque', 'done'], ['Decisión', 'done'], ['Viajero', 'pending'], ['Sobrantes', 'idle']], 'mark' => 1, 'markIndex' => 2],
                        ['modal', 'Paso 7 · Entrega de viajero', 'blocks' => [
                            ['cards', ['Empacado', 'Sobrantes', 'Decisión'], 'mark' => 2, 'markIndex' => 2],
                            ['button', 'Marcar viajero como recibido', 'mark' => 3],
                        ]],
                    ],
                    'caption' => 'El modal muestra el resumen antes de confirmar la entrega.',
                ],
            ],
            [
                'n' => 8,
                'actor' => 'Materiales',
                'where' => 'Tablero, columna Seguimiento',
                'title' => 'Regresar los sobrantes',
                'summary' => 'Lo que sobró de piezas o de CRIMP se devuelve a Materiales y se cierra el ciclo.',
                'does' => [
                    'Empaque entrega físicamente las piezas o el CRIMP que sobró.',
                    'En el tablero, da clic en el indicador de <strong>Sobrantes</strong>.',
                    'Confirma la recepción.',
                ],
                'after' => 'El ciclo del viajero queda cerrado y todos sus indicadores en verde.',
                'shot' => [
                    'screen' => 'Sobrantes',
                    'path' => '/admin/sent-lists/display',
                    'blocks' => [
                        ['lights', [['Empaque', 'done'], ['Decisión', 'done'], ['Viajero', 'done'], ['Sobrantes', 'pending']], 'mark' => 1, 'markIndex' => 3],
                        ['button', 'Confirmar recepción de sobrantes', 'mark' => 2],
                        ['lights', [['Empaque', 'done'], ['Decisión', 'done'], ['Viajero', 'done'], ['Sobrantes', 'done']]],
                        ['note', 'Todo en verde: el viajero terminó su ciclo.'],
                    ],
                    'caption' => 'Cuando los cuatro indicadores están verdes, ya no hay nada pendiente.',
                ],
            ],
        ];
    }

    /** Diferencias del viajero sin CRIMP contra el flujo de arriba. */
    public function standardSteps(): array
    {
        return [
            [
                'n' => 1,
                'actor' => 'Materiales',
                'where' => 'Tablero de listas de envío',
                'title' => 'Liberar el material',
                'summary' => 'Igual que en CRIMP, pero sin lotes de CRIMP: sólo se libera el material del viajero.',
                'does' => [
                    'En el renglón del viajero, marca el material como <strong>liberado</strong>.',
                ],
                'after' => 'Calidad puede inspeccionar.',
                'shot' => [
                    'screen' => 'Tablero',
                    'path' => '/admin/sent-lists/display',
                    'blocks' => [
                        ['lights', [['Material', 'pending'], ['Producción', 'idle'], ['Calidad', 'blocked'], ['Empaque', 'idle']], 'mark' => 1, 'markIndex' => 0],
                    ],
                    'caption' => 'Sin paso de lotes de CRIMP: se libera y sigue.',
                ],
            ],
            [
                'n' => 2,
                'actor' => 'Producción',
                'where' => 'Pesadas de producción',
                'title' => 'Registrar las pesadas',
                'summary' => 'Producción captura lo fabricado: piezas buenas y malas por pesada.',
                'does' => [
                    'Abre el viajero y da clic en <strong>Registrar pesada</strong>.',
                    'Captura las piezas pesadas y las que salieron malas.',
                ],
                'after' => 'Calidad ve pendiente lo que registraste.',
                'shot' => [
                    'screen' => 'Pesadas',
                    'path' => '/admin/production/weighings',
                    'blocks' => [
                        ['table', ['Viajero', 'Piezas', 'Buenas', 'Malas'], 'rows' => 3, 'mark' => 1, 'markCol' => 1, 'markText' => 'Pesar'],
                        ['fields', ['Piezas pesadas', 'Piezas malas'], 'mark' => 2, 'markIndex' => 0],
                    ],
                    'caption' => 'La pesada se guarda contra el viajero.',
                ],
            ],
            [
                'n' => 3,
                'actor' => 'Calidad',
                'where' => 'Inspección y pesadas de calidad',
                'title' => 'Inspeccionar y verificar',
                'summary' => 'Calidad libera la inspección del viajero y luego verifica por peso lo reportado.',
                'does' => [
                    'En <strong>Inspección</strong>, aprueba o rechaza el viajero.',
                    'En <strong>Pesadas de calidad</strong>, verifica las cantidades y captura scrap o rework si aplica.',
                ],
                'after' => 'El viajero queda listo para Empaque.',
                'warning' => 'si el material no está liberado, la inspección aparece bloqueada con el motivo.',
                'shot' => [
                    'screen' => 'Inspección',
                    'path' => '/admin/quality/inspection',
                    'blocks' => [
                        ['table', ['Viajero', 'Piezas', 'Estado'], 'rows' => 3, 'mark' => 1, 'markCol' => 2, 'markText' => 'Aprobar'],
                        ['cards', ['Aprobado', 'Rechazado', 'Rework'], 'mark' => 2, 'markIndex' => 0],
                    ],
                    'caption' => 'Aprobar o rechazar es lo que mueve el semáforo de Calidad.',
                ],
            ],
            [
                'n' => 4,
                'actor' => 'Empaque',
                'where' => 'Tablero o pestaña Empaque',
                'title' => 'Empacar y cerrar',
                'summary' => 'Empaque captura las piezas empacadas y el viajero se cierra con una de tres decisiones.',
                'does' => [
                    'Captura las <strong>piezas empacadas</strong> (mínimo 1).',
                    'Cierra el viajero con la decisión que corresponda.',
                ],
                'after' => 'El viajero queda cerrado y listo para su lista de envío.',
                'warning' => 'aquí no hay pesada de CRIMP ni división por lote: es una sola captura de piezas.',
                'shot' => [
                    'screen' => 'Empaque',
                    'path' => '/admin/sent-lists/display',
                    'blocks' => [
                        ['fields', ['Piezas empacadas', 'Comentarios'], 'mark' => 1, 'markIndex' => 0],
                        ['cards', ['Cerrar', 'Completar', 'Nuevo lote'], 'mark' => 2, 'markIndex' => 0],
                    ],
                    'caption' => 'Tres decisiones, sin las variantes de CRIMP.',
                ],
            ],
        ];
    }

    /** Guía por área: dónde entra cada rol y qué le toca. */
    public function roleGuides(): array
    {
        return [
            'materiales' => [
                'intro' => 'Tu tablero de trabajo es el listado de envío. Ahí ves todos los viajeros y cuáles te están esperando.',
                'steps' => [3 => 'Liberar el material', 2 => 'Cargar los lotes de CRIMP', 6 => 'Tomar la decisión', 8 => 'Recibir los sobrantes'],
                'links' => [
                    ['Panel de Materiales', 'Tus pendientes de un vistazo.', 'admin.materials.index'],
                    ['Gestión de materiales', 'Los viajeros de tu área.', 'admin.materials.manage'],
                    ['Tablero', 'Donde se liberan materiales y se toman las decisiones.', 'admin.sent-lists.display'],
                ],
            ],
            'produccion' => [
                'intro' => 'Tu trabajo en el sistema es una cosa: registrar las pesadas del viajero conforme se fabrica.',
                'steps' => ['—' => 'Registrar pesadas del viajero'],
                'links' => [
                    ['Panel de Producción', 'Qué está pendiente de pesar.', 'admin.production.index'],
                    ['Pesadas', 'Capturar y consultar pesadas.', 'admin.production.weighings'],
                    ['Tablero', 'Ver el avance de todas las áreas.', 'admin.sent-lists.display'],
                ],
            ],
            'calidad' => [
                'intro' => 'Dos momentos son tuyos: liberar la inspección del viajero y verificar por peso lo que reportó Producción.',
                'steps' => ['A' => 'Inspeccionar el viajero', 'B' => 'Verificar por peso'],
                'links' => [
                    ['Panel de Calidad', 'Pendientes de inspección y verificación.', 'admin.quality.index'],
                    ['Inspección', 'Aprobar o rechazar viajeros.', 'admin.quality.inspection'],
                    ['Pesadas de calidad', 'Verificación por peso, scrap y rework.', 'admin.quality.weighings'],
                ],
            ],
            'empaques' => [
                'intro' => 'Tú tienes la parte más larga del flujo CRIMP: capturas el empaque, mandas el aviso y entregas el viajero.',
                'steps' => [4 => 'Pesar piezas y CRIMP', 5 => 'Confirmar y notificar', 7 => 'Entregar el viajero'],
                'links' => [
                    ['Panel de Empaques', 'Lo que está listo para empacar.', 'admin.packaging.index'],
                    ['Tablero', 'Donde se captura el empaque CRIMP.', 'admin.sent-lists.display'],
                    ['Packing Slips', 'Documentos FPL-10 de salida.', 'admin.shipping-list.index'],
                ],
            ],
            'admin' => [
                'intro' => 'Tú preparas el terreno: las órdenes, los viajeros y los catálogos de los que dependen las cuatro áreas.',
                'steps' => [1 => 'Crear la orden y el viajero', '—' => 'Mantener catálogos y accesos'],
                'links' => [
                    ['Órdenes de compra', 'La entrada de todo el proceso.', 'admin.purchase-orders.index'],
                    ['Viajeros', 'Seguimiento de las órdenes de trabajo.', 'admin.work-orders.index'],
                    ['Partes', 'Aquí se marca una parte como CRIMP.', 'admin.parts.index'],
                    ['Usuarios', 'Altas, roles y áreas.', 'admin.users.index'],
                    ['Reportes', 'Exportar por departamento y período.', 'admin.reports.index'],
                ],
            ],
        ];
    }
}
