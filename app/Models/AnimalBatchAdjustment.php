<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a batch's count history — mortality, culling, a sale, a
 * hatch — each with the count it left the batch at. `AnimalBatch::current_count`
 * remains the fast-read running total; this is what lets a business
 * reconstruct when and why it changed, not just what it is now.
 */
class AnimalBatchAdjustment extends Model
{
    use BelongsToCompany;
    use HasUlids;

    protected $guarded = ['id'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AnimalBatch::class, 'animal_batch_id');
    }
}
