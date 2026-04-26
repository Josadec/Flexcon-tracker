<?php

namespace App\Livewire\Admin\Reports;

use App\Models\Part;
use App\Models\Price;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class PartsReport extends Component
{
    use WithPagination;

    public string $reportType = 'prices';

    public string $priceStatus = 'all';
    public ?string $referenceDate = null;
    public int $expiringDays = 30;

    public string $partsStatus = 'all';

    public string $search = '';
    public int $perPage = 15;

    public string $format = 'pdf';

    public function mount(): void
    {
        $this->referenceDate = now()->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedReportType(): void
    {
        $this->resetPage();
        $this->search = '';
    }

    public function updatedPriceStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPartsStatus(): void
    {
        $this->resetPage();
    }

    public function getDownloadUrl(): string
    {
        $params = http_build_query(array_filter([
            'report_type'    => $this->reportType,
            'price_status'   => $this->reportType === 'prices' ? $this->priceStatus : null,
            'reference_date' => $this->reportType === 'prices' ? $this->referenceDate : null,
            'expiring_days'  => $this->reportType === 'prices' ? $this->expiringDays : null,
            'parts_status'   => $this->reportType === 'parts' ? $this->partsStatus : null,
            'search'         => $this->search ?: null,
        ], fn ($v) => $v !== null && $v !== ''));

        $route = $this->format === 'excel'
            ? 'admin.reports.parts.excel'
            : 'admin.reports.parts.pdf';

        return route($route) . '?' . $params;
    }

    public function render()
    {
        if ($this->reportType === 'prices') {
            $rows = $this->getPricesQuery()->paginate($this->perPage);
        } else {
            $rows = $this->getPartsQuery()->paginate($this->perPage);
        }

        return view('livewire.admin.reports.parts-report', [
            'rows' => $rows,
        ]);
    }

    public function getPricesQuery()
    {
        $reference = Carbon::parse($this->referenceDate ?: now());
        $expiringLimit = $reference->copy()->addDays($this->expiringDays);

        $query = Price::query()
            ->with('part')
            ->whereHas('part');

        if ($this->priceStatus === 'active') {
            $query->where('active', true)
                  ->where('effective_date', '<=', $reference);
        } elseif ($this->priceStatus === 'expired') {
            $query->where('effective_date', '<', $reference)
                  ->where('active', false);
        } elseif ($this->priceStatus === 'expiring') {
            $query->where('active', true)
                  ->where('effective_date', '<=', $reference)
                  ->whereDate('effective_date', '>=', $reference->copy()->subDays($this->expiringDays));
        } elseif ($this->priceStatus === 'future') {
            $query->where('effective_date', '>', $reference);
        }

        if ($this->search !== '') {
            $search = $this->search;
            $query->whereHas('part', function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhere('item_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('effective_date', 'desc');
    }

    public function getPartsQuery()
    {
        $query = Part::query();

        if ($this->partsStatus === 'active') {
            $query->where('active', true);
        } elseif ($this->partsStatus === 'inactive') {
            $query->where('active', false);
        }

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhere('item_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('number');
    }
}
