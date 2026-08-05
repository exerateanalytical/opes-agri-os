<?php

namespace App\Domain\Sales\Http\Requests;

use App\Models\Item;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Item::class);
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'type' => ['sometimes', Rule::in(['product', 'service'])],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('company_id', $companyId)],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('items', 'sku')->where('company_id', $companyId)],
            'barcode' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['sometimes', 'string', 'max:50'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')->where('company_id', $companyId)],
            'track_stock' => ['sometimes', 'boolean'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
