<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\{Shift, SentList, User, Lot, Kit, Part};
use App\Services\CapacityCalculatorService;
use App\Services\CarryoverService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CapacityWizard extends Component
{
    // Wizard state
    public int $currentStep = 1;

    // Step 1 - Disponibilidad de horas
    public array $selectedShifts = [];
    public int $numPersons = 0;  // Ahora se calcula automáticamente
    public array $loadedEmployees = [];  // Empleados cargados por turno
    public string $startDate = '';
    public string $endDate = '';
    public float $totalAvailableHours = 0;
    public array $shiftDetails = [];

    // Step 2 - Cálculo de horas necesarias (por número de parte)
    /**
     * Work order items with configuration details
     * 
     * Structure: [
     *   'part_id' => int,
     *   'part_number' => string,
     *   'part_description' => string,
     *   'quantity' => int,
     *   'required_hours' => float,
     *   'configuration' => [
     *     'workstation_type' => string,
     *     'workstation_type_label' => string,
     *     'persons_required' => int,
     *     'units_per_hour' => int
     *   ]
     * ]
     */
    public array $workOrderItems = [];
    public float $totalRequiredHours = 0;
    public float $remainingHours = 0;
    public float $suggestedOvertime = 0;

    // Modal PO state
    public bool $showPOModal = false;
    public array $selectedPOs = [];
    public array $poConfigurations = []; // Store selected configuration for each PO
    public string $poSearchTerm = '';

    // Step 3 - Lista Preliminar
    public ?int $generatedSentListId = null;
    public array $lotNumbers = []; // Números de lote para cada PO (múltiples por índice)
    
    // Modal de Lotes
    public bool $showLotModal = false;
    public ?int $currentLotIndex = null; // Índice del item actual para agregar lotes
    public array $tempLots = []; // Lotes temporales para el modal
    public string $lotModalError = ''; // Error de validación dentro del modal de lotes

    // Kit data (for crimp parts)
    public array $kitNumbers = []; // Kit numbers per PO index
    public bool $showKitModal = false;
    public ?int $currentKitIndex = null;
    public array $tempKits = []; // Kits temporales para el modal
    public string $kitModalError = ''; // Error de validación dentro del modal de kits

    // Step 4 - Fechas programadas de envío
    public ?string $scheduledShipDate = null; // UNA fecha para toda la lista

    // UI state
    public string $errorMessage = '';
    public string $successMessage = '';
    public array $warnings = [];

    protected CapacityCalculatorService $service;

    public function boot(CapacityCalculatorService $service)
    {
        $this->service = $service;
    }

    public function mount()
    {
        $this->startDate = now()->format('Y-m-d');
        $this->endDate = now()->addDays(4)->format('Y-m-d');
    }

    // ==========================================
    // Navigation Methods
    // ==========================================

    public function nextStep()
    {
        if ($this->currentStep === 1) {
            if (!$this->validateStep1()) return;
            $this->calculateAvailableHours();
        } elseif ($this->currentStep === 2) {
            if (!$this->validateStep2()) return;
        } elseif ($this->currentStep === 3) {
            // No validation needed for step 3, just move to step 4
        }

        if ($this->currentStep < 4) {
            $this->currentStep++;
            $this->clearMessages();
        }
    }

    public function previousStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
            $this->clearMessages();
        }
    }

    public function goToStep(int $step)
    {
        if ($step >= 1 && $step <= 4 && $step <= $this->currentStep) {
            $this->currentStep = $step;
            $this->clearMessages();
        }
    }

    // ==========================================
    // Step 1 Methods - Disponibilidad de horas
    // ==========================================

    protected function validateStep1(): bool
    {
        if (empty($this->selectedShifts)) {
            $this->errorMessage = 'Debe seleccionar al menos un turno.';
            return false;
        }

        // Permitir continuar con 0 personas (se mostrará advertencia en la vista)
        if ($this->numPersons < 0) {
            $this->errorMessage = 'El número de personas no puede ser negativo.';
            return false;
        }

        if (!$this->validateDateRange()) {
            return false;
        }

        return true;
    }

    public function validateDateRange(): bool
    {
        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);

        if ($end->lt($start)) {
            $this->errorMessage = 'La fecha de fin debe ser posterior a la fecha de inicio.';
            return false;
        }

        $days = $start->diffInDays($end) + 1;
        if ($days > 5) {
            $this->errorMessage = 'El rango de fechas no puede exceder 5 días.';
            return false;
        }

        return true;
    }

    public function calculateAvailableHours()
    {
        try {
            $shifts = Shift::with('breakTimes')
                ->whereIn('id', $this->selectedShifts)
                ->get();

            $this->shiftDetails = [];
            foreach ($shifts as $shift) {
                $netHours = $this->service->calculateShiftNetHours($shift);
                $this->shiftDetails[] = [
                    'id' => $shift->id,
                    'name' => $shift->name,
                    'start_time' => Carbon::parse($shift->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($shift->end_time)->format('H:i'),
                    'net_hours' => $netHours,
                ];
            }

            $this->totalAvailableHours = $this->service->calculateTotalAvailableHours(
                $this->selectedShifts,
                $this->numPersons,
                Carbon::parse($this->startDate),
                Carbon::parse($this->endDate)
            );

            $this->remainingHours = $this->totalAvailableHours;
            $this->errorMessage = '';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error al calcular horas: ' . $e->getMessage();
        }
    }

    public function updatedSelectedShifts()
    {
        $previousPersons = $this->numPersons;

        // Cargar empleados para los turnos seleccionados
        $this->loadEmployeesForShifts();

        if (!empty($this->selectedShifts) && $this->currentStep === 1) {
            $this->calculateAvailableHours();
        }

        // CAP-4: avisar si el personal cambió y ya hay partes agregadas, ya que
        // sus horas se calcularon con el personal anterior.
        if (!empty($this->workOrderItems) && $this->numPersons !== $previousPersons) {
            $this->warnings[] = 'El personal disponible cambió a ' . $this->numPersons
                . '. Revisa las partes agregadas: sus horas se calcularon con el personal anterior.';
        }
    }

    // ==========================================
    // Employee Loading Methods
    // ==========================================

    /**
     * Carga los empleados activos asociados a los turnos seleccionados.
     * Agrupa los empleados por turno para mostrar en la vista.
     */
    protected function loadEmployeesForShifts(): void
    {
        if (empty($this->selectedShifts)) {
            $this->loadedEmployees = [];
            $this->numPersons = 0;
            return;
        }

        // Obtener empleados activos con rol 'employee' de los turnos seleccionados
        $employees = User::active()
            ->role('employee')
            ->whereIn('shift_id', $this->selectedShifts)
            ->with('shift:id,name')
            ->orderBy('shift_id')
            ->orderBy('name')
            ->get();

        // Agrupar por turno
        $grouped = [];
        foreach ($employees as $employee) {
            $shiftId = $employee->shift_id;
            if (!isset($grouped[$shiftId])) {
                $grouped[$shiftId] = [
                    'shift_id' => $shiftId,
                    'shift_name' => $employee->shift->name ?? 'Sin turno',
                    'employees' => [],
                ];
            }
            $grouped[$shiftId]['employees'][] = [
                'id' => $employee->id,
                'name' => $employee->name,
                'last_name' => $employee->last_name,
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'position' => $employee->position,
            ];
        }

        $this->loadedEmployees = array_values($grouped);
        $this->numPersons = $employees->count();
    }

    /**
     * Obtiene el total de empleados cargados.
     */
    public function getTotalEmployeesProperty(): int
    {
        return $this->numPersons;
    }

    /**
     * Obtiene el conteo de empleados por turno.
     */
    public function getEmployeeCountByShiftProperty(): array
    {
        $counts = [];
        foreach ($this->loadedEmployees as $group) {
            $counts[$group['shift_id']] = count($group['employees']);
        }
        return $counts;
    }

    // ==========================================
    // Step 2 Methods - Agregar por número de parte
    // ==========================================

    protected function validateStep2(): bool
    {
        if (empty($this->workOrderItems)) {
            $this->errorMessage = 'Debe agregar al menos un número de parte.';
            return false;
        }
        return true;
    }

    // ==========================================
    // PO Modal Methods
    // ==========================================

    public function openPOModal()
    {
        $this->showPOModal = true;
        $this->poSearchTerm = '';

        // Pre-seleccionar los POs que ya están en la lista
        $this->selectedPOs = array_values(array_column($this->workOrderItems, 'po_id'));

        // Pre-cargar las configuraciones ya seleccionadas para cada PO que está en la lista
        $this->poConfigurations = [];
        foreach ($this->workOrderItems as $item) {
            if (!empty($item['po_id']) && !empty($item['configuration']['id'])) {
                $this->poConfigurations[$item['po_id']] = $item['configuration']['id'];
            }
        }
    }

    public function closePOModal()
    {
        $this->showPOModal = false;
        $this->selectedPOs = [];
        $this->poConfigurations = [];
    }

    public function togglePOSelection(int $poId)
    {
        if (in_array($poId, $this->selectedPOs)) {
            // Remove from selection
            $this->selectedPOs = array_values(array_diff($this->selectedPOs, [$poId]));
            unset($this->poConfigurations[$poId]);
        } else {
            // Add to selection
            $this->selectedPOs[] = $poId;
        }
    }

    public function setConfigurationForPO(int $poId, int $configId)
    {
        $this->poConfigurations[$poId] = $configId;
    }

    public function addSelectedPOs()
    {
        if (empty($this->selectedPOs)) {
            $this->errorMessage = 'Debe seleccionar al menos un PO.';
            return;
        }

        try {
            $pos = \App\Models\PurchaseOrder::with(['part.standards.configurations', 'workOrder'])
                ->whereIn('id', $this->selectedPOs)
                ->get();

            $addedCount = 0;

            foreach ($pos as $po) {
                $existingPoIndex = array_search($po->id, array_column($this->workOrderItems, 'po_id'));
                if ($existingPoIndex !== false) {
                    $this->warnings[] = "PO {$po->po_number}: ya estaba agregado.";
                    continue;
                }

                $standard = $po->part->standards()->where('active', true)->first();

                if (!$standard || !$standard->hasMigratedConfigurations()) {
                    $this->warnings[] = "PO {$po->po_number}: Part {$po->part->number} has no configurations.";
                    continue;
                }

                // Use pending_quantity for carryover POs, full quantity for new ones
                $isCarryover = $po->hasActiveCarryover();
                $planningQty = $isCarryover ? $po->workOrder->pending_quantity : $po->quantity;

                $configId = $this->poConfigurations[$po->id] ?? null;

                if ($configId) {
                    $config = $standard->configurations()->find($configId);
                    if (!$config) {
                        $this->warnings[] = "PO {$po->po_number}: Selected configuration not found.";
                        continue;
                    }

                    if ($config->persons_required > $this->numPersons) {
                        $this->warnings[] = "PO {$po->po_number}: Configuration requires {$config->persons_required} persons but only {$this->numPersons} available.";
                        continue;
                    }

                    $requiredHours = $config->calculateRequiredHours($planningQty);
                } else {
                    $result = $this->service->calculateRequiredHours(
                        $po->part_id,
                        $planningQty,
                        $this->numPersons
                    );

                    $requiredHours = $result['required_hours'];
                    $config = $standard->configurations()->find($result['configuration']['id']);
                }

                if ($config) {
                    $validation = $config->validateCapacity();
                    if (!$validation['is_valid']) {
                        $this->warnings[] = "PO {$po->po_number}: {$validation['message']}";
                    }
                }

                $this->workOrderItems[] = [
                    'part_id'          => $po->part_id,
                    'part_number'      => $po->part->number,
                    'part_description' => $po->part->description,
                    'is_crimp'         => (bool) ($po->part->is_crimp ?? false),
                    'quantity'         => $planningQty,
                    'required_hours'   => $requiredHours,
                    'po_id'            => $po->id,
                    'po_number'        => $po->po_number,
                    'wo'               => $po->wo,
                    'is_carryover'     => $isCarryover,
                    'original_qty'     => $po->quantity,
                    'sent_pieces'      => $po->workOrder?->sent_pieces ?? 0,
                    'configuration'    => [
                        'id'                    => $config->id,
                        'workstation_type'      => $config->workstation_type,
                        'workstation_type_label'=> $config->workstation_type_label,
                        'persons_required'      => $config->persons_required,
                        'units_per_hour'        => $config->units_per_hour,
                    ],
                ];

                $addedCount++;
            }

            $this->calculateDifference();
            $this->closePOModal();

            $this->successMessage = "{$addedCount} PO(s) agregado(s) exitosamente.";
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error al agregar POs: ' . $e->getMessage();
        }
    }

    public function getAvailablePOsProperty()
    {
        $query = \App\Models\PurchaseOrder::with(['part.standards.configurations', 'workOrder'])
            ->where('status', \App\Models\PurchaseOrder::STATUS_APPROVED)
            ->whereHas('part.standards', function($q) {
                $q->where('active', true)
                  ->has('configurations');
            })
            ->whereHas('workOrder.status', function($q) {
                $q->where('name', 'Open');
            })
            ->where(function ($q) {
                // New PO: never in any SentList
                $q->whereDoesntHave('sentLists')
                  // Carryover: was in a prior SentList, WO incomplete, not in an active pending list
                  ->orWhere(function ($q2) {
                      $q2->whereHas('sentLists')
                         ->whereHas('workOrder', function ($woQ) {
                             $woQ->whereColumn('sent_pieces', '<', 'purchase_orders.quantity');
                         })
                         ->whereDoesntHave('sentLists', function ($slQ) {
                             $slQ->where('status', \App\Models\SentList::STATUS_PENDING);
                         });
                  });
            })
            // Exclude WOs that were assigned directly via legacy flow (sent_list_id set, not via wizard)
            ->whereDoesntHave('workOrder', function($q) {
                $q->whereNotNull('sent_list_id');
            });

        if (!empty($this->poSearchTerm)) {
            $query->where(function($q) {
                $q->where('po_number', 'like', "%{$this->poSearchTerm}%")
                  ->orWhere('wo', 'like', "%{$this->poSearchTerm}%")
                  ->orWhereHas('part', function($partQuery) {
                      $partQuery->where('number', 'like', "%{$this->poSearchTerm}%")
                                ->orWhere('description', 'like', "%{$this->poSearchTerm}%");
                  });
            });
        }

        return $query->orderBy('po_number')->get();
    }

    public function removeWorkOrderItem(int $index)
    {
        if (isset($this->workOrderItems[$index])) {
            unset($this->workOrderItems[$index]);
            $this->workOrderItems = array_values($this->workOrderItems);
            $this->calculateDifference();
            $this->successMessage = 'Item eliminado.';
        }
    }

    public function calculateDifference()
    {
        $this->totalRequiredHours = array_sum(array_column($this->workOrderItems, 'required_hours'));
        $this->remainingHours = $this->totalAvailableHours - $this->totalRequiredHours;

        if ($this->remainingHours < 0) {
            $this->suggestedOvertime = abs($this->remainingHours);
        } else {
            $this->suggestedOvertime = 0;
        }
    }

    // ==========================================
    // Step 3 Methods - Lista Preliminar
    // ==========================================

    public function openLotModal(int $index)
    {
        $this->currentLotIndex = $index;
        $this->lotModalError = '';
        // Cargar lotes existentes o inicializar con uno vacío
        $existingLots = $this->lotNumbers[$index] ?? [];
        
        if (empty($existingLots)) {
            $this->tempLots = [['number' => '', 'quantity' => '']];
        } else {
            // Ensure existing lots have the new structure
            $this->tempLots = array_map(function($lot) {
                if (is_array($lot) && isset($lot['number'])) {
                    return $lot;
                }
                // Convert old format (string) to new format
                return ['number' => $lot, 'quantity' => ''];
            }, $existingLots);
        }
        
        $this->showLotModal = true;
    }

    public function closeLotModal()
    {
        $this->showLotModal = false;
        $this->currentLotIndex = null;
        $this->tempLots = [];
        $this->lotModalError = '';
    }

    public function addLotInput()
    {
        $this->tempLots[] = ['number' => '', 'quantity' => ''];
    }

    public function removeLotInput(int $lotIndex)
    {
        unset($this->tempLots[$lotIndex]);
        $this->tempLots = array_values($this->tempLots);

        if (empty($this->tempLots)) {
            $this->tempLots = [['number' => '', 'quantity' => '']];
        }
    }

    public function saveLots()
    {
        if ($this->currentLotIndex === null) {
            return;
        }

        $this->lotModalError = '';

        // Filtrar lotes vacíos (sin número)
        $filteredLots = array_values(array_filter($this->tempLots, function($lot) {
            return !empty(trim($lot['number'] ?? ''));
        }));

        // CAP-1: cada lote debe tener cantidad > 0 y la suma no puede exceder
        // la cantidad del PO/item.
        $itemQty = (int) ($this->workOrderItems[$this->currentLotIndex]['quantity'] ?? 0);
        $sum = 0;
        foreach ($filteredLots as $lot) {
            $qty = (int) ($lot['quantity'] ?? 0);
            if ($qty < 1) {
                $this->lotModalError = 'Cada lote debe tener una cantidad mayor a 0.';
                return;
            }
            $sum += $qty;
        }
        if ($sum > $itemQty) {
            $this->lotModalError = 'La suma de cantidades de los lotes (' . number_format($sum)
                . ') excede la cantidad del PO (' . number_format($itemQty) . ').';
            return;
        }

        if (!empty($filteredLots)) {
            $this->lotNumbers[$this->currentLotIndex] = $filteredLots;
        } else {
            unset($this->lotNumbers[$this->currentLotIndex]);
        }

        $this->closeLotModal();
    }

    public function setLotNumber(int $index, string $lotNumber)
    {
        if (!empty(trim($lotNumber))) {
            $this->lotNumbers[$index] = [['number' => $lotNumber, 'quantity' => '']];
        } else {
            unset($this->lotNumbers[$index]);
        }
    }

    // ==========================================
    // Kit Modal Methods (for crimp parts)
    // ==========================================

    public function openKitModal(int $index)
    {
        $this->currentKitIndex = $index;
        $this->kitModalError = '';
        $existingKits = $this->kitNumbers[$index] ?? [];

        if (empty($existingKits)) {
            $this->tempKits = [['number' => '', 'quantity' => '']];
        } else {
            $this->tempKits = array_map(function ($kit) {
                if (is_array($kit) && isset($kit['number'])) {
                    return $kit;
                }
                return ['number' => $kit, 'quantity' => ''];
            }, $existingKits);
        }

        $this->showKitModal = true;
    }

    public function closeKitModal()
    {
        $this->showKitModal = false;
        $this->currentKitIndex = null;
        $this->tempKits = [];
        $this->kitModalError = '';
    }

    public function addKitInput()
    {
        $this->tempKits[] = ['number' => '', 'quantity' => ''];
    }

    public function removeKitInput(int $kitIndex)
    {
        unset($this->tempKits[$kitIndex]);
        $this->tempKits = array_values($this->tempKits);

        if (empty($this->tempKits)) {
            $this->tempKits = [['number' => '', 'quantity' => '']];
        }
    }

    public function saveKits()
    {
        if ($this->currentKitIndex === null) {
            return;
        }

        $this->kitModalError = '';

        $filteredKits = array_values(array_filter($this->tempKits, function ($kit) {
            return !empty(trim($kit['number'] ?? ''));
        }));

        // CAP-1: cada kit debe tener una cantidad > 0
        foreach ($filteredKits as $kit) {
            if ((int) ($kit['quantity'] ?? 0) < 1) {
                $this->kitModalError = 'Cada kit debe tener una cantidad mayor a 0.';
                return;
            }
        }

        if (!empty($filteredKits)) {
            $this->kitNumbers[$this->currentKitIndex] = $filteredKits;
        } else {
            unset($this->kitNumbers[$this->currentKitIndex]);
        }

        $this->closeKitModal();
    }

    /**
     * CAP-1: valida las cantidades de lotes y kits de todos los items.
     * Devuelve el primer mensaje de error encontrado, o null si todo es válido.
     */
    protected function validateLotKitQuantities(): ?string
    {
        foreach ($this->workOrderItems as $index => $item) {
            $itemQty = (int) ($item['quantity'] ?? 0);
            $label = $item['po_number'] ?? $item['part_number'] ?? ('#' . ($index + 1));

            // Lotes
            if (!empty($this->lotNumbers[$index]) && is_array($this->lotNumbers[$index])) {
                $sum = 0;
                foreach ($this->lotNumbers[$index] as $lot) {
                    if (!is_array($lot) || empty(trim($lot['number'] ?? ''))) {
                        continue;
                    }
                    $qty = (int) ($lot['quantity'] ?? 0);
                    if ($qty < 1) {
                        return "PO {$label}: cada lote debe tener una cantidad mayor a 0.";
                    }
                    $sum += $qty;
                }
                if ($sum > $itemQty) {
                    return "PO {$label}: la suma de lotes (" . number_format($sum)
                        . ') excede la cantidad (' . number_format($itemQty) . ').';
                }
            }

            // Kits
            if (!empty($this->kitNumbers[$index]) && is_array($this->kitNumbers[$index])) {
                foreach ($this->kitNumbers[$index] as $kit) {
                    if (!is_array($kit) || empty(trim($kit['number'] ?? ''))) {
                        continue;
                    }
                    if ((int) ($kit['quantity'] ?? 0) < 1) {
                        return "PO {$label}: cada kit debe tener una cantidad mayor a 0.";
                    }
                }
            }
        }

        return null;
    }

    public function generateSentList()
    {
        if (empty($this->workOrderItems)) {
            $this->errorMessage = 'No hay items para generar la lista.';
            return;
        }

        // Validar que se haya ingresado la fecha programada de envío
        if (empty($this->scheduledShipDate)) {
            $this->errorMessage = 'Debe ingresar la fecha programada de envío.';
            return;
        }

        // CAP-1: validación final de cantidades de lotes y kits
        $quantityError = $this->validateLotKitQuantities();
        if ($quantityError !== null) {
            $this->errorMessage = $quantityError;
            return;
        }

        // CAP-2: dejar constancia si la lista excede la capacidad disponible
        $overtimeNeeded = max(0, round($this->totalRequiredHours - $this->totalAvailableHours, 2));
        $notes = 'Lista preliminar generada desde Capacity Wizard';
        if ($overtimeNeeded > 0) {
            $notes .= ' — Excede la capacidad disponible en ' . number_format($overtimeNeeded, 2)
                . ' h (requiere tiempo extra).';
        }

        try {
            DB::transaction(function () use ($notes) {
                // Crear SentList como Lista Preliminar
                $sentList = SentList::create([
                    'po_id' => null, // Ya no se usa, ahora es relación many-to-many
                    'shift_ids' => $this->selectedShifts,
                    'num_persons' => $this->numPersons,
                    'start_date' => $this->startDate,
                    'end_date' => $this->endDate,
                    'total_available_hours' => $this->totalAvailableHours,
                    'used_hours' => $this->totalRequiredHours,
                    'remaining_hours' => max(0, $this->remainingHours),
                    'status' => SentList::STATUS_PENDING,
                    'current_department' => SentList::DEPT_MATERIALS,
                    'notes' => $notes,
                ]);

                // Sync shifts
                $sentList->shifts()->sync($this->selectedShifts);

                // Attach purchase orders with their details and create Lot records
                foreach ($this->workOrderItems as $index => $item) {
                    if (isset($item['po_id'])) {
                        // Obtener el PO para acceder al work_order_id
                        $purchaseOrder = \App\Models\PurchaseOrder::with('workOrder')->find($item['po_id']);
                        
                        // Procesar lotes con nueva estructura (number + quantity)
                        $lotNumbersString = null;
                        $lotNumbersArray = [];
                        
                        if (isset($this->lotNumbers[$index]) && is_array($this->lotNumbers[$index])) {
                            foreach ($this->lotNumbers[$index] as $lot) {
                                if (is_array($lot) && !empty($lot['number'])) {
                                    $lotNumbersArray[] = $lot;
                                }
                            }
                            
                            // Crear string para pivot table (solo números de lote)
                            $lotNumbersString = implode(', ', array_map(function($lot) {
                                return $lot['number'];
                            }, $lotNumbersArray));
                        }
                        
                        $carryoverService = app(CarryoverService::class);
                        if ($item['is_carryover'] ?? false) {
                            $pivotData = $carryoverService->buildCarryoverPivotData(
                                $purchaseOrder,
                                $item['quantity'],
                                $item['required_hours'],
                                $lotNumbersString
                            );
                        } else {
                            $pivotData = $carryoverService->buildStandardPivotData(
                                $item['quantity'],
                                $item['required_hours'],
                                $lotNumbersString
                            );
                        }

                        $sentList->purchaseOrders()->attach($item['po_id'], $pivotData);
                        
                        // Actualizar fecha programada de envío del Work Order (UNA para toda la lista)
                        if ($purchaseOrder && $purchaseOrder->workOrder && !empty($this->scheduledShipDate)) {
                            $purchaseOrder->workOrder->update([
                                'scheduled_send_date' => $this->scheduledShipDate,
                            ]);
                        }
                        
                        // Crear registros Lot reales para que aparezcan en /admin/lots
                        $createdLotIds = [];
                        if ($purchaseOrder && $purchaseOrder->workOrder && !empty($lotNumbersArray)) {
                            $workOrder = $purchaseOrder->workOrder;
                            $partDescription = $purchaseOrder->part->description ?? 'Sin descripción';
                            
                            foreach ($lotNumbersArray as $lot) {
                                $lotNumber = trim($lot['number'] ?? '');
                                $lotQuantity = isset($lot['quantity']) && $lot['quantity'] !== '' ? intval($lot['quantity']) : 0;
                                
                                if (!empty($lotNumber)) {
                                    $existingLot = Lot::where('work_order_id', $workOrder->id)
                                        ->where('lot_number', $lotNumber)
                                        ->first();
                                    
                                    if (!$existingLot) {
                                        $newLot = Lot::create([
                                            'work_order_id' => $workOrder->id,
                                            'lot_number' => $lotNumber,
                                            'description' => $partDescription,
                                            'quantity' => $lotQuantity,
                                            'status' => Lot::STATUS_PENDING,
                                            'comments' => "Generado automáticamente desde Capacity Wizard",
                                        ]);
                                        $createdLotIds[] = $newLot->id;
                                    } else {
                                        $createdLotIds[] = $existingLot->id;
                                    }
                                }
                            }
                        }
                        
                        // Crear registros Kit para partes crimp y asociarlos a los lotes
                        $isCrimp = $item['is_crimp'] ?? false;
                        if ($isCrimp && $purchaseOrder && $purchaseOrder->workOrder) {
                            $workOrder = $purchaseOrder->workOrder;
                            $kitNumbersArray = [];
                            
                            if (isset($this->kitNumbers[$index]) && is_array($this->kitNumbers[$index])) {
                                foreach ($this->kitNumbers[$index] as $kit) {
                                    if (is_array($kit) && !empty($kit['number'])) {
                                        $kitNumbersArray[] = $kit;
                                    }
                                }
                            }
                            
                            foreach ($kitNumbersArray as $kit) {
                                $kitNumber = trim($kit['number'] ?? '');
                                $kitQuantity = isset($kit['quantity']) && $kit['quantity'] !== '' ? intval($kit['quantity']) : 0;
                                
                                if (!empty($kitNumber)) {
                                    $existingKit = Kit::where('work_order_id', $workOrder->id)
                                        ->where('kit_number', $kitNumber)
                                        ->first();
                                    
                                    if (!$existingKit) {
                                        $newKit = Kit::create([
                                            'work_order_id' => $workOrder->id,
                                            'kit_number' => $kitNumber,
                                            'quantity' => $kitQuantity,
                                            'status' => Kit::STATUS_PREPARING,
                                            'current_approval_cycle' => 1,
                                        ]);
                                        
                                        // Associate kit with all lots of this WO
                                        if (!empty($createdLotIds)) {
                                            $newKit->lots()->syncWithoutDetaching($createdLotIds);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                $this->generatedSentListId = $sentList->id;
            });

            $this->successMessage = '¡Lista preliminar generada exitosamente!';
            $this->errorMessage = '';

            // CAP-2: advertir si la lista quedó por encima de la capacidad
            if ($overtimeNeeded > 0) {
                $this->warnings[] = 'La lista se generó excediendo la capacidad por '
                    . number_format($overtimeNeeded, 2) . ' h. Considera registrar tiempo extra.';
            }
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error al generar la lista: ' . $e->getMessage();
        }
    }

    public function resetWizard()
    {
        $this->reset([
            'currentStep',
            'selectedShifts',
            'numPersons',
            'loadedEmployees',
            'totalAvailableHours',
            'shiftDetails',
            'workOrderItems',
            'totalRequiredHours',
            'remainingHours',
            'suggestedOvertime',
            'generatedSentListId',
            'errorMessage',
            'successMessage',
            'lotNumbers',
            'showLotModal',
            'currentLotIndex',
            'tempLots',
            'kitNumbers',
            'showKitModal',
            'currentKitIndex',
            'tempKits',
        ]);

        $this->numPersons = 0;
        $this->startDate = now()->format('Y-m-d');
        $this->endDate = now()->addDays(4)->format('Y-m-d');
    }

    public function viewSentList()
    {
        if ($this->generatedSentListId) {
            return redirect()->route('admin.sent-lists.show', $this->generatedSentListId);
        }
    }

    protected function clearMessages()
    {
        $this->errorMessage = '';
        $this->successMessage = '';
        $this->warnings = [];
    }

    public function render()
    {
        return view('livewire.admin.capacity-wizard', [
            'shifts' => Shift::active()->get(),
            'parts'  => Part::whereHas('standards', fn($q) => $q->where('active', true))->get(),
        ]);
    }
}
