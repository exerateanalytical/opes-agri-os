<?php

namespace App\Domain\Sales\Http\Resources;

use App\Models\DocumentLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentLine
 */
class DocumentLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'tax_rate_id' => $this->tax_rate_id,
            'tax_amount' => $this->tax_amount,
            'line_total' => $this->line_total,
            'sort_order' => $this->sort_order,
        ];
    }
}
