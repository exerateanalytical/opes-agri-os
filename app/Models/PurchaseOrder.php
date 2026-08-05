<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use BelongsToCompany;
    use HasUlids;
    use SoftDeletes;

    public const STATUSES = ['draft', 'issued', 'partially_received', 'received', 'cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /** A Contact of type=supplier — no dedicated Supplier table. */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function recalculateTotals(): void
    {
        $subtotal = round(
            $this->lines()->get()->sum(fn (PurchaseOrderLine $line) => (float) $line->quantity * (float) $line->unit_cost),
            2,
        );

        $this->forceFill(['subtotal' => $subtotal, 'total' => $subtotal])->save();
    }
}
