<?php

namespace App\Livewire\Stock;

use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A batch's chain of custody: every movement recorded against its
 * `batch_number`, in the order it happened. Reads what Agri M1 already
 * captures on every stock movement — no new schema, just a screen that
 * asks for it. See docs/architecture/agri-platform-roadmap.md.
 */
class Trace extends Component
{
    #[Url]
    public string $itemId = '';

    #[Url]
    public string $batchNumber = '';

    public function mount(): void
    {
        Gate::authorize('products.view');
    }

    public function render(): View
    {
        $movements = collect();

        if ($this->itemId !== '' && $this->batchNumber !== '') {
            $movements = StockMovement::query()
                ->where('item_id', $this->itemId)
                ->where('batch_number', $this->batchNumber)
                ->orderBy('occurred_at')
                ->get();
        }

        return view('livewire.stock.trace', [
            'movements' => $movements,
            'items' => Item::query()
                ->where('type', 'product')->where('track_stock', true)
                ->orderBy('name')->get(['id', 'name', 'unit']),
            'batches' => $this->itemId !== ''
                ? StockMovement::query()
                    ->where('item_id', $this->itemId)
                    ->whereNotNull('batch_number')
                    ->distinct()
                    ->orderBy('batch_number')
                    ->pluck('batch_number')
                : collect(),
        ])->layout('components.layouts.app', ['title' => 'Trace a batch', 'active' => 'products']);
    }
}
