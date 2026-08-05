<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use BelongsToCompany;
    use HasUlids;
    use SoftDeletes;

    public const TYPES = ['ngo', 'donor', 'government', 'cooperative_partner', 'other'];

    public const STATUSES = ['active', 'inactive'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'address' => 'array',
        ];
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(PartnerInteraction::class);
    }

    public function grantProjects(): HasMany
    {
        return $this->hasMany(GrantProject::class);
    }
}
