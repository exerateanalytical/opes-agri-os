<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrantProject extends Model
{
    use BelongsToCompany;
    use HasUlids;
    use SoftDeletes;

    public const STATUSES = ['planned', 'active', 'completed', 'cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'spent_amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GrantTransaction::class);
    }

    /** What's left of the grant after what has actually been spent. */
    public function balance(): float
    {
        return round((float) $this->received_amount - (float) $this->spent_amount, 2);
    }
}
