<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimalHealthRecord extends Model
{
    use BelongsToCompany;
    use HasUlids;

    public const TYPES = ['vaccination', 'treatment', 'checkup', 'illness'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'administered_on' => 'date',
            'next_due_on' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }
}
